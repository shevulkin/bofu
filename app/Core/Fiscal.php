<?php
declare(strict_types=1);

/**
 * Фіскальні чеки: що саме ми пробиваємо у «Вчасно.Касі» й що з цього виходить.
 *
 * Чек належить ПІДЗАМОВЛЕННЮ, а не замовленню. Гроші отримує конкретна точка
 * своєю касою, і фіскальний номер, зміна та Z-звіт належать саме тому ПРРО.
 * Замовлення, розділене між двома магазинами, — це два продажі й два чеки,
 * як і дві накладні (див. Shipments, влаштовані так само й з тієї самої
 * причини).
 *
 * Vchasno вміє лише говорити з API. Тут вирішується, що йому сказати: які
 * рядки в чеку, як розкласти знижку, яка податкова група, коли пробивати
 * автоматично, а коли чекати на кнопку.
 *
 * ТРИ СТАНИ ЧЕКА, і третій — найважливіший:
 *   done    — ПРРО повернув фіскальний номер, чек існує в ДПС;
 *   error   — ПРРО відмовив по суті (не зійшлась сума, немає ключа): чека немає,
 *             можна виправити й пробити знову;
 *   pending — відповіді не було (мережа, таймаут). Чек МІГ пробитись. Саме
 *             для цього кожен рядок має свій незмінний tag: повтор із тим
 *             самим tag «Вчасно.Каса» впізнає як той самий запит і поверне
 *             той самий чек, а не пробʼє другий. Інакше обірваний зв’язок
 *             коштував би подвійного чека й ручного повернення в кабінеті.
 */
class Fiscal
{
    /** Скільки разів повторюємо непевний чек, перш ніж покликати людину */
    private const MAX_ATTEMPTS = 5;

    /** Крок округлення готівки: копійки дрібніші за 10 в обігу не ходять */
    private const CASH_STEP = 0.10;

    // ─────────────────────────────────────────────────────────────── читання

    /** Останній чек продажу цієї частини (той, що показуємо в картці) */
    public static function forOrder(int $orderId): ?array
    {
        return DB::row("SELECT * FROM fiscal_receipts WHERE order_id = ? AND type = 'sell'
                        ORDER BY id DESC", [$orderId]);
    }

    /** Усі чеки частини разом із поверненнями — стрічкою, найновіші зверху */
    public static function allFor(int $orderId): array
    {
        return DB::all('SELECT * FROM fiscal_receipts WHERE order_id = ? ORDER BY id DESC', [$orderId]);
    }

    /** Чеки всього замовлення: [id підзамовлення => [чеки]] */
    public static function forParent(int $parentId): array
    {
        $out = [];
        foreach (DB::all('SELECT * FROM fiscal_receipts WHERE parent_id = ? ORDER BY id', [$parentId]) as $r) {
            $out[(int)$r['order_id']][] = $r;
        }
        return $out;
    }

    public static function byId(int $id): ?array
    {
        return DB::row('SELECT * FROM fiscal_receipts WHERE id = ?', [$id]);
    }

    /** Чи є на цю частину живий чек продажу (пробитий або ще непевний) */
    public static function hasSale(int $orderId): bool
    {
        return (bool)DB::val("SELECT 1 FROM fiscal_receipts
                              WHERE order_id = ? AND type = 'sell' AND status IN ('done','pending') LIMIT 1",
                             [$orderId]);
    }

    /** Чи повернуто вже цей чек */
    public static function refunded(int $receiptId): bool
    {
        return (bool)DB::val("SELECT 1 FROM fiscal_receipts
                              WHERE of_receipt_id = ? AND status IN ('done','pending') LIMIT 1", [$receiptId]);
    }

    /** Непевні чеки, які час перепитати — для cron */
    public static function due(int $limit = 50): array
    {
        return DB::all("SELECT * FROM fiscal_receipts
                        WHERE status = 'pending' AND attempts < ?
                        ORDER BY updated_at, id LIMIT " . max(1, $limit), [self::MAX_ATTEMPTS]);
    }

    public static function statusLabel(array $r): string
    {
        return match ((string)$r['status']) {
            'done' => $r['type'] === 'return' ? 'Повернення проведено' : 'Чек фіскалізовано',
            'error' => 'Помилка',
            default => 'Відповіді ще немає',
        };
    }

    // ──────────────────────────────────────────────────────────── податкова група

    /**
     * Податкова група позиції: своя в товару, інакше типова цього магазину,
     * інакше загальна.
     *
     * Магазин попереду загальної навмисно: точки можуть належати різним ФОПам,
     * і платник ПДВ поруч із неплатником — звичайна для мережі річ. Товарна
     * група попереду обох: підакцизне чи пільгове не залежить від того, з якої
     * полиці його взяли.
     */
    public static function taxGroup(?array $product, ?int $storeId): int
    {
        $own = $product['taxgrp'] ?? null;
        if ($own !== null && isset(Vchasno::TAX_GROUPS[(int)$own])) return (int)$own;
        return self::storeTaxGroup($storeId);
    }

    /** Типова група магазину (або загальна, якщо в точки своєї немає) */
    public static function storeTaxGroup(?int $storeId): int
    {
        if ($storeId) {
            $own = DB::val('SELECT vchasno_taxgrp FROM stores WHERE id = ?', [$storeId]);
            if ($own !== null && isset(Vchasno::TAX_GROUPS[(int)$own])) return (int)$own;
        }
        $g = (int)Settings::get('vchasno_taxgrp', '2');
        return isset(Vchasno::TAX_GROUPS[$g]) ? $g : 2;
    }

    // ─────────────────────────────────────────────────────────────── рядки чека

    /**
     * Рядки чека з позицій підзамовлення.
     *
     * Знижку розкладаємо по рядках, а не ставимо загальною на чек: у чека
     * загальна знижка рахується інакше (див. їхню документацію — сума оплат
     * має відрізнятись від суми чека рівно на неї), і одна помилка в цьому
     * місці валить увесь чек. Порядкова знижка ж дає рівно те, що бачив
     * покупець у кошику: перераховану ціну кожної позиції.
     *
     * Залишок від округлення дістається найбільшому рядку — той самий прийом,
     * що й у OrderFlow::recalcTotals, і з тієї самої причини: сума рядків
     * мусить збігатися з сумою чека до копійки, інакше ПРРО відмовить.
     *
     * @return array{rows:array,sum:float}
     */
    public static function rows(int $orderId, ?int $storeId, float $discount): array
    {
        $items = DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
        if (!$items) return ['rows' => [], 'sum' => 0.0];

        $sums = [];
        foreach ($items as $i => $it) $sums[$i] = round((float)$it['sum'], 2);
        $total = array_sum($sums);

        // Найбільшій позиції — залишок: розподіл «по копійці» помітний саме на
        // ній найменше, а дрібну позицію знижка могла б з’їсти в мінус.
        $order = $sums;
        arsort($order);
        $left = round(min($discount, $total), 2);
        $cuts = [];
        $n = count($order); $k = 0;
        foreach ($order as $i => $sum) {
            $k++;
            $d = ($k === $n || $total <= 0) ? $left : round($discount * $sum / $total, 2);
            $cuts[$i] = max(0.0, min($d, $sum, $left));
            $left = round($left - $cuts[$i], 2);
        }

        $rows = []; $sum = 0.0;
        foreach ($items as $i => $it) {
            $product = $it['product_id']
                ? DB::row('SELECT * FROM products WHERE id = ?', [(int)$it['product_id']])
                : null;
            $variant = $it['variant_id']
                ? DB::row('SELECT * FROM product_variants WHERE id = ?', [(int)$it['variant_id']])
                : null;

            $name = trim((string)$it['title']);
            if (($it['variant_name'] ?? '') !== '') $name .= ', ' . $it['variant_name'];

            $row = [
                'name' => Vchasno::clean($name, 128),
                'cnt' => (float)(int)$it['qty'],
                'price' => Vchasno::money((float)$it['price']),
                'disc' => Vchasno::money($cuts[$i] ?? 0),
                'taxgrp' => self::taxGroup($product, $storeId),
            ];
            // Коди — те, за чим позиція в чеку сходиться з номенклатурою
            // кабінету «Вчасно.Каси». Належать фасовці, якщо вона є: етикетку
            // зі штрихкодом клеять на банку, а не на «мед узагалі».
            $code = trim((string)($variant['sku'] ?? '')) ?: trim((string)($product['sku'] ?? ''));
            $barcode = trim((string)($variant['barcode'] ?? '')) ?: trim((string)($product['barcode'] ?? ''));
            $uktzed = trim((string)($product['uktzed'] ?? ''));
            if ($code !== '') $row['code'] = Vchasno::clean($code, 64);
            if ($barcode !== '') $row['code1'] = Vchasno::clean($barcode, 64);
            if ($uktzed !== '') $row['code2'] = Vchasno::clean($uktzed, 32);

            $rows[] = $row;
            $sum = round($sum + round($row['cnt'] * $row['price'], 2) - $row['disc'], 2);
        }
        return ['rows' => $rows, 'sum' => $sum];
    }

    // ──────────────────────────────────────────────────────────────── перешкоди

    /**
     * Чого бракує, щоб пробити чек. Список, а не «так/ні»: продавцю треба
     * знати, що саме заповнити, і чи це взагалі його робота.
     *
     * @return string[] порожній масив = все на місці
     */
    public static function missing(array $child, array $parent): array
    {
        $out = [];
        $storeId = $child['store_id'] ? (int)$child['store_id'] : null;
        if (!Vchasno::enabled($storeId)) {
            $out[] = $storeId
                ? 'у цього магазину немає токена каси (Магазини → картка точки або Налаштування)'
                : 'у налаштуваннях немає токена «Вчасно.Каси»';
        }
        if ((string)$child['status'] === 'canceled') $out[] = 'частину скасовано — продажу не було';
        if (!DB::val('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [(int)$child['id']])) {
            $out[] = 'у частині немає позицій';
        }
        if (round((float)$child['total'], 2) <= 0) $out[] = 'сума частини нульова — фіскалізувати нічого';
        if (self::hasSale((int)$child['id'])) $out[] = 'чек на цю частину вже пробито';
        return $out;
    }

    // ─────────────────────────────────────────────────────────────── продаж

    /**
     * Пробити чек продажу.
     *
     * $in: ['pay_type' => int, 'got' => float (готівкою від покупця), 'cashier' => string]
     *
     * @return array{ok:bool,error:string,receipt:?array}
     */
    public static function sell(array $child, array $parent, array $in = [], ?int $userId = null): array
    {
        $gaps = self::missing($child, $parent);
        if ($gaps) return ['ok' => false, 'error' => 'Чек не пробито: ' . implode('; ', $gaps) . '.', 'receipt' => null];

        $storeId = $child['store_id'] ? (int)$child['store_id'] : null;
        $built = self::rows((int)$child['id'], $storeId, (float)$child['discount']);
        if (!$built['rows']) return ['ok' => false, 'error' => 'У частині немає позицій.', 'receipt' => null];

        $payType = (int)($in['pay_type'] ?? 0);
        if (!isset(Vchasno::PAY_TYPES[$payType])) $payType = 0;

        $sum = $built['sum'];
        // Готівка ходить кроком у 10 копійок. Округлення — окремий рядок чека
        // (round), а не мовчазна зміна ціни: покупець має бачити, звідки взялась
        // різниця, а ДПС — що товар коштує стільки ж, скільки на полиці.
        $paid = $payType === 0 && Settings::bool('vchasno_cash_round', true)
            ? round(round($sum / self::CASH_STEP) * self::CASH_STEP, 2)
            : $sum;
        $round = Vchasno::money($paid - $sum);

        $pay = ['type' => $payType, 'sum' => Vchasno::money($paid)];
        if ($payType === 0) {
            $got = Vchasno::money(max(0.0, (float)($in['got'] ?? 0)));
            // Решта = скільки дали понад чек. Нуль не надсилаємо: «решта 0.00»
            // у чеку виглядає як помилка касира, а не як точний розрахунок.
            if ($got > $paid) $pay['change'] = Vchasno::money($got - $paid);
        }

        $receipt = ['sum' => Vchasno::money($sum), 'rows' => $built['rows'], 'pays' => [$pay]];
        if (abs($round) >= 0.005) $receipt['round'] = $round;
        $footer = Vchasno::clean((string)Settings::get('vchasno_comment_down', ''), 120);
        if ($footer !== '') $receipt['comment_down'] = $footer;

        $fiscal = [
            'task' => Vchasno::TASK_SELL,
            'cashier' => Vchasno::cashierName($in['cashier'] ?? null),
            'receipt' => $receipt,
        ];

        return self::send([
            'order_id' => (int)$child['id'], 'parent_id' => (int)$parent['id'], 'store_id' => $storeId,
            'type' => 'sell', 'of_receipt_id' => null,
            'sum' => Vchasno::money($paid), 'pay_type' => $payType,
            'change' => (float)($pay['change'] ?? 0),
        ], $fiscal, self::userinfo($parent), $userId);
    }

    // ─────────────────────────────────────────────────────────────── повернення

    /**
     * Чек повернення на весь пробитий чек.
     *
     * Часткових повернень тут навмисно немає: у нас скасовують позицію (і тоді
     * замовлення перераховується), а не «половину чека». Коли така потреба
     * з’явиться, це буде окрема дія з вибором рядків, а не прапорець тут.
     *
     * Полів «Національного кешбеку» (purchase_receipt_fisn і сусідні) не
     * надсилаємо: вони обов’язкові лише для тієї програми, а зайве поле в
     * чеку — це зайва нагода отримати відмову на ділянці, яка нас не стосується.
     */
    public static function refund(array $receipt, ?int $userId = null, string $cashier = ''): array
    {
        if ((string)$receipt['type'] !== 'sell' || (string)$receipt['status'] !== 'done') {
            return ['ok' => false, 'error' => 'Повертати можна лише пробитий чек продажу.', 'receipt' => null];
        }
        if (self::refunded((int)$receipt['id'])) {
            return ['ok' => false, 'error' => 'Повернення на цей чек уже проведено.', 'receipt' => null];
        }
        $child = OrderFlow::order((int)$receipt['order_id']);
        $parent = $child ? OrderFlow::head($child) : null;
        if (!$child || !$parent) return ['ok' => false, 'error' => 'Замовлення не знайдено.', 'receipt' => null];

        // Рядки беремо з того, що надсилали, а не збираємо заново: позиції
        // могли передати в інший магазин або перерахувати після продажу, і
        // повернення мусить бути дзеркалом чека, а не поточного стану бази.
        $sent = json_decode((string)$receipt['payload'], true);
        $rows = $sent['fiscal']['receipt']['rows'] ?? null;
        if (!is_array($rows) || !$rows) {
            $built = self::rows((int)$child['id'], $child['store_id'] ? (int)$child['store_id'] : null,
                (float)$child['discount']);
            $rows = $built['rows'];
        }
        if (!$rows) return ['ok' => false, 'error' => 'Немає рядків для повернення.', 'receipt' => null];

        $payType = (int)$receipt['pay_type'];
        $sum = Vchasno::money((float)$receipt['sum']);
        $back = ['sum' => $sum, 'rows' => $rows, 'pays' => [['type' => $payType, 'sum' => $sum]]];
        $round = Vchasno::money((float)($sent['fiscal']['receipt']['round'] ?? 0));
        if (abs($round) >= 0.005) $back['round'] = $round;
        $back['comment_down'] = Vchasno::clean('Повернення за чеком ' . (string)$receipt['fiscal_number'], 120);

        return self::send([
            'order_id' => (int)$child['id'], 'parent_id' => (int)$parent['id'],
            'store_id' => $receipt['store_id'] ? (int)$receipt['store_id'] : null,
            'type' => 'return', 'of_receipt_id' => (int)$receipt['id'],
            'sum' => $sum, 'pay_type' => $payType, 'change' => 0,
        ], [
            'task' => Vchasno::TASK_RETURN,
            'cashier' => Vchasno::cashierName($cashier),
            'receipt' => $back,
        ], self::userinfo($parent), $userId);
    }

    // ────────────────────────────────────────────────────────────────── відправка

    /**
     * Записати намір, надіслати, розібрати відповідь.
     *
     * Рядок у базі з’являється ДО запиту й одразу з власним tag. Це не облік
     * заради обліку: якщо відповідь не дійде, у нас лишиться і те, що ми
     * надсилали, і мітка, за якою ПРРО впізнає повтор. Порядок навпаки
     * («спершу спитаємо, потім запишемо») втрачає саме ті чеки, заради яких
     * усе це й потрібно.
     */
    private static function send(array $meta, array $fiscal, array $userinfo, ?int $userId): array
    {
        $tag = bin2hex(random_bytes(16));
        $body = ['tag' => $tag, 'source' => 'BOFU'] + ($userinfo ? ['userinfo' => $userinfo] : []);
        $payload = $body + ['fiscal' => $fiscal];

        $id = DB::insert('fiscal_receipts', $meta + [
            'tag' => $tag,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending', 'attempts' => 0, 'error' => null,
            'created_by_user_id' => $userId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = Vchasno::execute($fiscal, $body, $meta['store_id'] ?? null);
        return self::apply((int)$id, $r, $userId);
    }

    /**
     * Повторити непевний чек — тим самим tag і тим самим тілом.
     *
     * Саме тут окупається збережений payload: ПРРО зіставляє повтор із першою
     * спробою за tag і, якщо чек тоді таки пробився, віддає його ж. Зібрати
     * тіло заново було б помилкою: позиції могли змінитись, і «той самий»
     * запит перестав би бути тим самим.
     */
    public static function retry(array $receipt, ?int $userId = null): array
    {
        if ((string)$receipt['status'] === 'done') {
            return ['ok' => true, 'error' => '', 'receipt' => $receipt];
        }
        $payload = json_decode((string)$receipt['payload'], true);
        if (!is_array($payload) || !isset($payload['fiscal'])) {
            return ['ok' => false, 'error' => 'Запит не збережено — повторити нічим. Пробийте чек заново.', 'receipt' => $receipt];
        }
        $fiscal = $payload['fiscal'];
        unset($payload['fiscal']);
        $r = Vchasno::execute($fiscal, $payload, $receipt['store_id'] ? (int)$receipt['store_id'] : null);
        return self::apply((int)$receipt['id'], $r, $userId);
    }

    /**
     * Розкласти відповідь ПРРО по рядку чека.
     *
     * Три виходи, і різниця між ними вся в тому, що робити далі:
     * фіскальний номер — готово; мережа мовчить — лишаємо pending і повторимо;
     * відмова по суті — error, тут уже потрібна людина.
     */
    private static function apply(int $id, array $r, ?int $userId): array
    {
        $receipt = self::byId($id);
        if (!$receipt) return ['ok' => false, 'error' => 'Чек зник із бази.', 'receipt' => null];

        $info = (array)($r['data']['info'] ?? []);
        $attempts = (int)$receipt['attempts'] + 1;

        if ($r['ok'] && ($info['doccode'] ?? '') !== '') {
            DB::update('fiscal_receipts', [
                'status' => 'done',
                'fiscal_number' => (string)$info['doccode'],
                'rro_number' => (string)($info['fisid'] ?? ''),
                'shift_link' => isset($info['shift_link']) ? (int)$info['shift_link'] : null,
                'doc_no' => isset($info['docno']) ? (int)$info['docno'] : null,
                'receipt_dt' => (string)($info['dt'] ?? Vchasno::dt()),
                'qr' => (string)($info['qr'] ?? Vchasno::checkUrl((string)$info['doccode'])),
                'cancel_id' => (string)($info['cancelid'] ?? ''),
                'is_offline' => !empty($info['isoffline']) ? 1 : 0,
                // dtype 0 — тестова каса. Це не дрібниця: такий чек не має
                // юридичної сили, і продавець мусить бачити різницю, поки
                // не переставили токен на бойову.
                'is_test' => ((int)($info['dtype'] ?? 1)) === 0 ? 1 : 0,
                'error' => null, 'attempts' => $attempts, 'updated_at' => now(),
            ], 'id = ?', [$id]);
            $fresh = self::byId($id);
            self::logToOrder($fresh, $userId);
            return ['ok' => true, 'error' => '', 'receipt' => $fresh];
        }

        // «Все добре» без фіскального номера — теж невідомість, а не помилка:
        // чек міг пробитись, а номер загубитись дорогою. Списати таке в
        // «не вийшло» означало б пробити другий чек на той самий продаж.
        $unclear = Vchasno::unclear($r) || $r['ok'];
        DB::update('fiscal_receipts', [
            'status' => $unclear ? 'pending' : 'error',
            'error' => mb_substr($r['error'] ?: 'Каса не відповіла', 0, 500),
            'attempts' => $attempts, 'updated_at' => now(),
        ], 'id = ?', [$id]);
        $fresh = self::byId($id);
        self::alert($fresh, $unclear);
        return ['ok' => false, 'error' => $r['error'], 'receipt' => $fresh];
    }

    /** Куди надіслати покупцю посилання на чек — якщо є куди й якщо ввімкнено */
    private static function userinfo(array $parent): array
    {
        if (!Settings::bool('vchasno_send_link', true)) return [];
        $out = [];
        $email = trim((string)($parent['email'] ?? ''));
        // Службова пошта офлайн-акаунта нікому не належить — лист у нікуди
        if ($email !== '' && !str_ends_with($email, '@offline.local')
            && filter_var($email, FILTER_VALIDATE_EMAIL)) $out['email'] = $email;
        $phone = AuthTokens::normPhoneAny((string)($parent['phone'] ?? ''));
        if ($phone) $out['phone'] = $phone;
        return $out;
    }

    /** Надіслати покупцю посилання на вже пробитий чек (просить пізніше — «загубив») */
    public static function sendLink(array $receipt, string $channel, string $recipient): array
    {
        if ((string)$receipt['status'] !== 'done' || (string)$receipt['fiscal_number'] === '') {
            return ['ok' => false, 'error' => 'Чек ще не пробито — надсилати нічого.'];
        }
        $r = Vchasno::sendLink((string)$receipt['fiscal_number'], $channel, $recipient,
            $receipt['store_id'] ? (int)$receipt['store_id'] : null);
        return ['ok' => $r['ok'], 'error' => $r['error']];
    }

    // ─────────────────────────────────────────────────────────────── наслідки

    /** Запис в історію замовлення: чек — така сама подія, як накладна чи статус */
    private static function logToOrder(array $receipt, ?int $userId): void
    {
        $store = $receipt['store_id']
            ? (string)(DB::val('SELECT name FROM stores WHERE id = ?', [(int)$receipt['store_id']]) ?? '')
            : '';
        $what = $receipt['type'] === 'return' ? 'чек повернення' : 'фіскальний чек';
        OrderFlow::log((int)$receipt['parent_id'], (int)$receipt['order_id'], 'fiscal',
            ($store !== '' ? $store . ': ' : '') . $what . ' ' . $receipt['fiscal_number']
            . ' на ' . price_fmt((float)$receipt['sum'])
            . (!empty($receipt['is_test']) ? ' (ТЕСТОВА каса — чек без сили)' : '')
            . (!empty($receipt['is_offline']) ? ' (проведено офлайн, піде в ДПС при зв’язку)' : ''),
            $userId);
    }

    /**
     * Сказати вголос, що чека немає.
     *
     * Непробитий чек — це не «щось не зберіглось»: покупець уже пішов із
     * товаром, а продаж не потрапив у ДПС. Мовчазна помилка тут коштує
     * штрафу, тому вона йде і в історію замовлення, і в сповіщення.
     */
    private static function alert(array $receipt, bool $unclear): void
    {
        $order = OrderFlow::order((int)$receipt['order_id']);
        $number = (string)($order['number'] ?? $receipt['order_id']);
        $text = $unclear
            ? 'каса не відповіла, чек ' . ($receipt['type'] === 'return' ? 'повернення ' : '')
              . 'у невідомому стані — перепитаємо автоматично'
            : 'чек не пробито: ' . (string)$receipt['error'];
        OrderFlow::log((int)$receipt['parent_id'], (int)$receipt['order_id'], 'fiscal', $text,
            $receipt['created_by_user_id'] ? (int)$receipt['created_by_user_id'] : null);

        if ($unclear) return;   // повтор іще попереду — тривожити рано
        Notify::fire('fiscal_error', [
            'number' => $number,
            'sum' => price_fmt((float)$receipt['sum']),
            'error' => (string)$receipt['error'],
            // Порожньо, коли абсолютної адреси немає (локальна машина, cron без
            // bot_site_url): рядок із самих підстановок зникне сам, а «/bin/admin/…»
            // виглядав би як посилання й нікуди не вів
            'link' => BotAuth::siteUrl() !== ''
                ? BotAuth::siteUrl() . base_url('/admin/orders/' . (int)$receipt['parent_id'])
                : '',
        ], $receipt['store_id'] ? (int)$receipt['store_id'] : null);
    }

    // ────────────────────────────────────────────────────────── автоматичний чек

    /**
     * Чек одразу після продажу на касі.
     *
     * Автоматично — лише те, за що гроші отримали при оформленні: видача з рук
     * у точці. Замовлення з каси на доставку оплатять при отриманні (і чек по
     * післяплаті пробиває перевізник), тож пробивати його тут означало б
     * фіскалізувати гроші, яких ще немає.
     *
     * Помилка чека не скасовує замовлення: товар уже віддали. Вона повертається
     * рядком, який каса покаже продавцю, і лишається кнопкою в картці.
     *
     * @return string[] помилки, які варто показати продавцю
     */
    public static function afterPosSale(array $children, array $parent, array $pay, ?int $userId): array
    {
        if (!Settings::bool('vchasno_auto_pos', true)) return [];
        $out = [];
        foreach ($children as $child) {
            $fresh = OrderFlow::order((int)$child['id']);
            if (!$fresh) continue;
            $storeId = $fresh['store_id'] ? (int)$fresh['store_id'] : null;
            // Магазин без каси — не помилка: точка може працювати без ПРРО
            // (продає лише послуги, або каса ще не заведена). Мовчки пропускаємо.
            if (!Vchasno::enabled($storeId)) continue;
            $r = self::sell($fresh, $parent, $pay, $userId);
            if (!$r['ok']) $out[] = $r['error'];
        }
        return $out;
    }
}
