<?php
declare(strict_types=1);

/**
 * Розділення замовлення між магазинами.
 *
 * Два рівні, завжди:
 *   • головне замовлення (parent_id IS NULL) — те, що бачить покупець: контакти,
 *     доставка, промокод, підсумок і зведений статус;
 *   • підзамовлення (parent_id = головне) — завдання одного магазину: свої позиції,
 *     свій статус, свій продавець.
 *
 * Позиції (order_items) лежать тільки в підзамовленнях. Навіть коли магазин один,
 * підзамовлення теж створюється: тоді передача позиції в інший магазин нічого не
 * перебудовує, а доступи продавців, сповіщення й статуси працюють одним шляхом.
 *
 * Ціни фіксуються при оформленні й не змінюються від передач між магазинами —
 * покупець платить рівно те, що бачив у кошику.
 */
class OrderFlow
{
    public const STATUSES = [
        'new' => 'Нове', 'processing' => 'Обробляється', 'shipped' => 'В дорозі',
        'done' => 'Доставлено', 'canceled' => 'Скасовано',
    ];

    public const DELIVERY = ['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'];

    /** Просування: головне замовлення показує найменш просунуте з підзамовлень */
    private const RANK = ['new' => 0, 'processing' => 1, 'shipped' => 2, 'done' => 3];

    /** Поля, які підзамовлення успадковує від головного (контакти й доставка не змінюються) */
    private const INHERITED = ['user_id', 'name', 'phone', 'email', 'delivery',
        'city', 'np_office', 'address', 'comment', 'promo_code'];

    public static function statusLabel(?string $status): string
    { return self::STATUSES[$status] ?? (string)$status; }

    public static function deliveryLabel(?string $delivery): string
    { return self::DELIVERY[$delivery] ?? (string)$delivery; }

    /**
     * Куди везти, одним рядком: «Київ, Відділення №5».
     *
     * Назви способу доставки тут немає — вона йде окремим полем, а тут лише
     * адреса. Самовивіз повертає порожній рядок: адреси в нього немає, і
     * вигадувати її не треба (порожній рядок шаблон приберає сам).
     */
    public static function deliveryAddress(array $o): string
    {
        $parts = ($o['delivery'] ?? '') === 'pickup' ? []
            : [$o['city'] ?? '', $o['np_office'] ?? '', $o['address'] ?? ''];
        return implode(', ', array_filter(array_map('trim', array_map('strval', $parts)), fn($p) => $p !== ''));
    }

    // ------------------------------------------------------------------ читання

    public static function order(int $id): ?array
    { return DB::row('SELECT * FROM orders WHERE id = ?', [$id]); }

    public static function children(int $parentId): array
    {
        return DB::all(
            'SELECT o.*, s.name AS store_name, s.city AS store_city
             FROM orders o LEFT JOIN stores s ON s.id = o.store_id
             WHERE o.parent_id = ? ORDER BY o.seq, o.id', [$parentId]);
    }

    public static function items(int $orderId): array
    { return DB::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]); }

    /**
     * Що замовили — списком для сповіщення: «• Мед липовий, 0.5 л × 2».
     *
     * Довгі замовлення обрізаємо: у месенджері повідомлення однаково згорнеться,
     * а продавцю з першого погляду треба зрозуміти, чи є в нього це на складі —
     * решту він побачить в адмінці. Кількість показуємо завжди, навіть «× 1»:
     * без неї рядок «× 2» серед інших читається як частина назви.
     */
    public static function itemsSummary(int $orderId, int $limit = 8): string
    {
        $items = self::items($orderId);
        $lines = [];
        foreach (array_slice($items, 0, $limit) as $i) {
            $title = trim((string)$i['title']);
            if (($i['variant_name'] ?? '') !== '') $title .= ', ' . $i['variant_name'];
            $lines[] = '• ' . $title . ' × ' . (int)$i['qty'];
        }
        if (count($items) > $limit) $lines[] = '• …та ще ' . (count($items) - $limit) . ' поз.';
        return implode("\n", $lines);
    }

    /**
     * Чого не вистачило на складі: [['title'=>…, 'qty'=>5, 'taken'=>2, 'lack'=>3], …].
     *
     * Сайт дозволяє замовити більше, ніж є, — і це свідомо: виробник доробить.
     * Але мовчки такий продаж лишати не можна: хтось має або передати позицію
     * туди, де товар є, або стати за станок. stock_taken = NULL означає
     * замовлення, оформлене до появи цього обліку, — його не чіпаємо.
     */
    public static function shortage(int $orderId): array
    {
        $out = [];
        foreach (self::items($orderId) as $i) {
            if ($i['stock_taken'] === null) continue;
            $lack = (int)$i['qty'] - (int)$i['stock_taken'];
            if ($lack <= 0) continue;
            $title = trim((string)$i['title']);
            if (($i['variant_name'] ?? '') !== '') $title .= ', ' . $i['variant_name'];
            $out[] = ['title' => $title, 'qty' => (int)$i['qty'],
                      'taken' => (int)$i['stock_taken'], 'lack' => $lack];
        }
        return $out;
    }

    /** «Мед липовий, 0.5 л — 3 з 5» — одним рядком через кому */
    public static function shortageLine(array $lack): string
    {
        return implode(', ', array_map(
            fn($s) => $s['title'] . ' — ' . $s['lack'] . ' з ' . $s['qty'], $lack));
    }

    /**
     * Те саме для месенджера: окремим блоком і зі знаком, бо це єдиний рядок
     * сповіщення, який вимагає дії, а не просто повідомляє.
     */
    public static function shortageSummary(int $orderId): string
    {
        $lack = self::shortage($orderId);
        if (!$lack) return '';
        $lines = ['⚠️ Не вистачає на складі:'];
        foreach ($lack as $s) $lines[] = '• ' . $s['title'] . ' — є ' . $s['taken'] . ' з ' . $s['qty'];
        $lines[] = 'Передайте позицію іншому магазину або довиробіть.';
        return implode("\n", $lines);
    }

    /** Позиції всього замовлення (з усіх підзамовлень) — для списків і листів */
    public static function allItems(int $parentId): array
    {
        return DB::all(
            'SELECT i.*, o.store_id, s.name AS store_name
             FROM order_items i
             JOIN orders o ON o.id = i.order_id
             LEFT JOIN stores s ON s.id = o.store_id
             WHERE o.parent_id = ? OR o.id = ? ORDER BY o.seq, i.id', [$parentId, $parentId]);
    }

    /** Головне замовлення для будь-якого рядка (для головного — воно саме) */
    public static function head(array $order): array
    {
        return $order['parent_id'] ? (self::order((int)$order['parent_id']) ?? $order) : $order;
    }

    public static function events(int $parentId): array
    {
        return DB::all(
            'SELECT ev.*, u.name AS user_name FROM order_events ev
             LEFT JOIN users u ON u.id = ev.user_id
             WHERE ev.parent_id = ? ORDER BY ev.id DESC', [$parentId]);
    }

    public static function log(int $parentId, ?int $orderId, string $type, string $message, ?int $userId = null): void
    {
        DB::insert('order_events', [
            'parent_id' => $parentId, 'order_id' => $orderId, 'user_id' => $userId,
            // Роль, у якій діяли: одна людина може працювати і як адмін, і як продавець
            // точки, тож саме імені для історії замало. Пишемо лише тоді, коли дію
            // виконав власник поточної сесії, — інакше приписали б чужу роль.
            'role' => ($userId !== null && $userId === Auth::id()) ? Auth::role() : null,
            'type' => $type, 'message' => $message, 'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ оформлення

    /**
     * Оформлення: головне замовлення + підзамовлення по магазинах.
     * $head — готовий рядок orders, $rows — рядки Cart::detailed(),
     * $pickupStore — магазин самовивозу (тоді все виконує саме він).
     * Повертає ['id' => головне, 'children' => [підзамовлення]].
     */
    public static function place(array $head, array $rows, ?int $pickupStore): array
    {
        $parentId = DB::tx(function () use ($head, $rows, $pickupStore) {
            $parentId = DB::insert('orders', $head + ['parent_id' => null, 'seq' => 0]);

            // 1) кожна позиція дістає магазин-виконавця
            $groups = [];
            foreach ($rows as $r) {
                $pid = (int)$r['product']['id'];
                $vid = isset($r['variant']['id']) ? (int)$r['variant']['id'] : null;
                $sid = self::pickStore($pid, $vid, (int)$r['qty'], $pickupStore);
                $groups[$sid][] = $r;
            }
            ksort($groups); // магазини йдуть за id — порядок підзамовлень стабільний

            // 2) підзамовлення на кожен магазин
            $seq = 0;
            foreach ($groups as $sid => $group) {
                $seq++;
                $childId = self::createChild($parentId, $head, $sid ?: null, $seq);
                foreach ($group as $r) {
                    $pid = (int)$r['product']['id'];
                    $vid = isset($r['variant']['id']) ? (int)$r['variant']['id'] : null;
                    $qty = (int)$r['qty'];
                    // товар з варіантами, але без вибраного варіанта (старий кошик) —
                    // списувати нема з чого, і рахувати нестачу теж не по чому
                    $countable = !($vid === null && Catalog::hasVariants($pid));
                    $taken = $countable ? self::moveStock($pid, $vid, $sid ?: null, -$qty) : null;
                    DB::insert('order_items', [
                        'order_id' => $childId,
                        'product_id' => $pid, 'variant_id' => $vid,
                        'title' => $r['product']['name'], 'variant_name' => $r['variant']['name'] ?? null,
                        'price' => $r['price'] ?? 0, 'qty' => $qty, 'sum' => $r['sum'] ?? 0,
                        'stock_taken' => $taken,
                    ]);
                }
                // Продали більше, ніж лежало на складі. Пишемо в історію одразу:
                // сповіщення можна проґавити чи вимкнути, а замовлення однаково
                // комусь виконувати — і за тиждень має бути видно, чому воно
                // затрималось.
                $lack = self::shortage($childId);
                if ($lack) self::log($parentId, $childId, 'shortage',
                    'Замовлено понад залишок: ' . self::shortageLine($lack)
                    . '. Передайте позицію магазину, де товар є, або довиробіть її.');
            }

            self::recalcTotals($parentId);
            $names = [];
            foreach (self::children($parentId) as $c) $names[] = ($c['store_name'] ?? 'Магазин') . ' — ' . $c['number'];
            self::log($parentId, null, 'created',
                count($names) > 1
                    ? 'Замовлення розділено між магазинами: ' . implode(', ', $names)
                    : 'Замовлення прийнято: ' . ($names[0] ?? '—'));
            return $parentId;
        });

        return ['id' => $parentId, 'children' => self::children($parentId)];
    }

    /** Нове (порожнє) підзамовлення магазину під тим самим головним */
    private static function createChild(int $parentId, array $head, ?int $storeId, int $seq): int
    {
        $data = ['parent_id' => $parentId, 'seq' => $seq,
            'number' => $head['number'] . '/' . $seq, 'token' => null,
            'store_id' => $storeId, 'status' => 'new',
            'subtotal' => 0, 'discount' => 0, 'total' => 0,
            'created_at' => now()];
        foreach (self::INHERITED as $f) $data[$f] = $head[$f] ?? null;
        return DB::insert('orders', $data);
    }

    /**
     * Магазин-виконавець позиції: самовивіз — тільки обраний; інакше той, де вистачає
     * на всю кількість, далі — де хоч щось є, і як запасний варіант — магазин за
     * замовчуванням (позиція «під замовлення»).
     */
    public static function pickStore(int $productId, ?int $variantId, int $qty, ?int $preferred): int
    {
        if ($preferred) return $preferred;
        $active = self::activeStoreIds();
        $byStore = array_intersect_key(Catalog::stockByStore($productId, $variantId), array_flip($active));
        arsort($byStore);
        foreach ($byStore as $sid => $have) if ($have >= $qty) return (int)$sid;
        foreach ($byStore as $sid => $have) if ($have > 0) return (int)$sid;
        return self::defaultStoreId();
    }

    /** @return int[] */
    public static function activeStoreIds(): array
    {
        static $ids = null;
        return $ids ??= array_map(fn($r) => (int)$r['id'], DB::all('SELECT id FROM stores WHERE active = 1 ORDER BY sort, id'));
    }

    /** Магазин для позицій, яких немає ніде (виготовлення на замовлення). 0 = магазинів немає взагалі */
    public static function defaultStoreId(): int
    {
        $id = (int)(Settings::get('default_store_id', '0') ?: 0);
        if ($id && in_array($id, self::activeStoreIds(), true)) return $id;
        return self::activeStoreIds()[0] ?? 0;
    }

    // ------------------------------------------------------------------ підсумки й статуси

    /**
     * Суми підзамовлень. Позиції фіксовані, тож перераховуємо лише розкладку:
     * знижка головного ділиться пропорційно, залишок від округлення — найбільшій частині,
     * щоб сума підзамовлень завжди дорівнювала сумі головного.
     */
    public static function recalcTotals(int $parentId): void
    {
        $parent = self::order($parentId);
        if (!$parent) return;
        $children = self::children($parentId);
        if (!$children) return;

        $subs = [];
        foreach ($children as $c) {
            $s = 0.0;
            foreach (self::items((int)$c['id']) as $it) $s += (float)$it['sum'];
            $subs[(int)$c['id']] = round($s, 2);
        }
        $total = array_sum($subs);
        $discount = (float)$parent['discount'];

        arsort($subs); // найбільша частина йде першою — їй дістанеться залишок округлення
        $left = round($discount, 2);
        $i = 0; $n = count($subs);
        foreach ($subs as $cid => $sub) {
            $i++;
            $d = ($i === $n || $total <= 0) ? $left : round($discount * $sub / $total, 2);
            $d = min($d, $sub, $left);
            $left = round($left - $d, 2);
            DB::update('orders', [
                'subtotal' => $sub, 'discount' => $d, 'total' => max(0, round($sub - $d, 2)),
            ], 'id = ?', [$cid]);
        }
    }

    /**
     * Зведений статус головного: найменш просунутий серед живих підзамовлень,
     * усі скасовані — скасовано. Тобто «Доставлено» зʼявляється лише тоді,
     * коли всі магазини закрили свої частини.
     */
    public static function aggregateStatus(int $parentId): ?string
    {
        $sts = array_column(self::children($parentId), 'status');
        if (!$sts) return null;
        $live = array_values(array_filter($sts, fn($s) => $s !== 'canceled'));
        if (!$live) return 'canceled';
        usort($live, fn($a, $b) => (self::RANK[$a] ?? 0) <=> (self::RANK[$b] ?? 0));
        return $live[0];
    }

    /** Підтягнути головне під підзамовлення. Повертає новий статус, якщо він змінився */
    public static function syncParent(int $parentId, ?int $userId = null): ?string
    {
        $parent = self::order($parentId);
        $new = self::aggregateStatus($parentId);
        if (!$parent || $new === null || $new === $parent['status']) return null;
        DB::update('orders', ['status' => $new], 'id = ?', [$parentId]);
        self::log($parentId, null, 'status',
            'Статус замовлення оновлено автоматично: ' . self::statusLabel($new) .
            ' (усі магазини опрацювали свої частини)', $userId);
        Notify::fire('order_status', [
            'number' => $parent['number'], 'status' => self::statusLabel($new),
        ], $parent['store_id'] ? (int)$parent['store_id'] : null);
        return $new;
    }

    /**
     * Зміна статусу. Для підзамовлення — своє + автопідтягування головного,
     * для головного — каскадом на всі підзамовлення (щоб «скасувати все» було одним рухом).
     */
    public static function setStatus(int $orderId, string $status, ?int $userId = null): bool
    {
        if (!isset(self::STATUSES[$status])) return false;
        $order = self::order($orderId);
        if (!$order) return false;

        if ($order['parent_id']) {
            if ($order['status'] === $status) return false;
            DB::update('orders', ['status' => $status], 'id = ?', [$orderId]);
            $store = $order['store_id'] ? DB::val('SELECT name FROM stores WHERE id = ?', [$order['store_id']]) : null;
            self::log((int)$order['parent_id'], $orderId, 'status',
                ($store ? $store . ': ' : '') . 'статус підзамовлення ' . $order['number'] . ' → ' . self::statusLabel($status), $userId);
            Notify::fire('order_status', [
                'number' => $order['number'], 'status' => self::statusLabel($status),
            ], $order['store_id'] ? (int)$order['store_id'] : null);
            self::syncParent((int)$order['parent_id'], $userId);
            return true;
        }

        // головне: розкладаємо на всі частини, що ще в роботі
        $changed = 0;
        foreach (self::children($orderId) as $c) {
            if ($c['status'] === $status) continue;
            if ($c['status'] === 'canceled' && $status !== 'canceled') continue; // скасоване не воскрешаємо
            DB::update('orders', ['status' => $status], 'id = ?', [$c['id']]);
            Notify::fire('order_status', [
                'number' => $c['number'], 'status' => self::statusLabel($status),
            ], $c['store_id'] ? (int)$c['store_id'] : null);
            $changed++;
        }
        if ($changed) self::log($orderId, null, 'status',
            'Статус проставлено всім підзамовленням: ' . self::statusLabel($status), $userId);
        self::syncParent($orderId, $userId);
        return $changed > 0;
    }

    // ------------------------------------------------------------------ передача позиції

    /**
     * Передати позицію іншому магазину: вона переїжджає в його підзамовлення
     * (створюється, якщо його ще немає), залишки повертаються старому магазину
     * й списуються в нового. Ціна позиції не змінюється.
     */
    public static function transferItem(int $itemId, int $toStoreId, ?int $userId = null): string
    {
        $result = DB::tx(function () use ($itemId, $toStoreId, $userId) {
            $item = DB::row('SELECT * FROM order_items WHERE id = ?', [$itemId]);
            if (!$item) throw new RuntimeException('Позицію не знайдено.');
            $from = self::order((int)$item['order_id']);
            if (!$from || !$from['parent_id']) throw new RuntimeException('Передавати можна лише позиції підзамовлення.');
            $parentId = (int)$from['parent_id'];
            $parent = self::order($parentId);
            if (!$parent) throw new RuntimeException('Головне замовлення не знайдено.');
            if ((int)$from['store_id'] === $toStoreId) throw new RuntimeException('Позиція вже в цьому магазині.');
            $store = DB::row('SELECT * FROM stores WHERE id = ? AND active = 1', [$toStoreId]);
            if (!$store) throw new RuntimeException('Магазин недоступний.');
            if (in_array($from['status'], ['done', 'canceled'], true))
                throw new RuntimeException('Підзамовлення вже закрите — передавати з нього не можна.');

            // Залишки: повертаємо старому магазину рівно те, що з нього колись
            // зняли, а не всю кількість позиції. Інакше передача позиції,
            // проданої понад залишок, дарувала б старій точці неіснуючий товар:
            // зняли 2 з 5 — повернули б 5. NULL — замовлення до цього обліку,
            // там поводимось як раніше й вважаємо, що взяли все.
            $pid = $item['product_id'] ? (int)$item['product_id'] : null;
            $vid = $item['variant_id'] ? (int)$item['variant_id'] : null;
            $qty = (int)$item['qty'];
            $got = null;
            if ($pid && !($vid === null && Catalog::hasVariants($pid))) {
                $taken = $item['stock_taken'] === null ? $qty : (int)$item['stock_taken'];
                self::moveStock($pid, $vid, $from['store_id'] ? (int)$from['store_id'] : null, $taken);
                $got = self::moveStock($pid, $vid, $toStoreId, -$qty);
                DB::update('order_items', ['stock_taken' => $got], 'id = ?', [$itemId]);
            }

            // підзамовлення-отримувач: чинне того ж магазину або нове
            $to = DB::row(
                "SELECT * FROM orders WHERE parent_id = ? AND store_id = ? AND status NOT IN ('done','canceled')
                 ORDER BY seq LIMIT 1", [$parentId, $toStoreId]);
            $created = false;
            if (!$to) {
                $seq = (int)(DB::val('SELECT MAX(seq) FROM orders WHERE parent_id = ?', [$parentId]) ?? 0) + 1;
                $to = self::order(self::createChild($parentId, $parent, $toStoreId, $seq));
                $created = true;
            }

            DB::update('order_items', ['order_id' => $to['id']], 'id = ?', [$itemId]);

            // підзамовлення без позицій більше не існує як завдання магазину
            $emptied = !DB::val('SELECT id FROM order_items WHERE order_id = ? LIMIT 1', [$from['id']]);
            if ($emptied) {
                DB::update('order_events', ['order_id' => null], 'order_id = ?', [$from['id']]);
                DB::delete('orders', 'id = ?', [$from['id']]);
            }

            self::recalcTotals($parentId);
            $fromName = $from['store_id'] ? (DB::val('SELECT name FROM stores WHERE id = ?', [$from['store_id']]) ?? '—') : 'Без магазину';
            $title = $item['title'] . ($item['variant_name'] ? ' · ' . $item['variant_name'] : '') . ' × ' . (int)$item['qty'];
            self::log($parentId, (int)$to['id'], 'transfer',
                'Позицію «' . $title . '» передано: ' . $fromName . ' → ' . $store['name'] .
                ' (' . $to['number'] . ($created ? ', нове підзамовлення' : '') . ')' .
                ($emptied ? '. Підзамовлення ' . $from['number'] . ' закрито — позицій не лишилось.' : '') .
                // Передали, а легше не стало: у новій точці товару теж бракує.
                // Сказати про це треба одразу, інакше позицію ганятимуть по колу.
                ($got !== null && $got < $qty
                    ? '. Увага: у «' . $store['name'] . '» теж не вистачає — є ' . $got . ' з ' . $qty . '.'
                    : ''), $userId);
            self::syncParent($parentId, $userId);

            return ['child' => self::order((int)$to['id']), 'created' => $created, 'store' => $store['name']];
        });

        // магазину-отримувачу це нове завдання — сповіщаємо як про нове замовлення
        if ($result['child']) self::notifyNew($result['child']);
        return ($result['created'] ? 'Створено підзамовлення для магазину «' : 'Позицію передано магазину «')
            . $result['store'] . '».';
    }

    /**
     * Зміна залишку в магазині. Тільки для наявних рядків store_stock: якщо магазин
     * ніколи не мав цього товару, списання нічого не робить (позиція «під замовлення»),
     * і повернення так само не має вигадувати залишок з нічого.
     *
     * Повертає, скільки штук справді пройшло. При списанні це може бути менше за
     * замовлене — рівно стільки, скільки лежало на складі. Раніше різниця просто
     * зникала в GREATEST(0, …), і про продаж понад залишок не дізнавався ніхто:
     * ні продавець, ні наступна передача позиції, яка повертала магазину більше,
     * ніж з нього колись зняли.
     */
    private static function moveStock(?int $productId, ?int $variantId, ?int $storeId, int $delta): int
    {
        if (!$productId || !$storeId || $delta === 0) return 0;
        $cond = $variantId === null ? 'variant_id IS NULL' : 'variant_id = ?';
        $where = [$productId, $storeId];
        if ($variantId !== null) $where[] = $variantId;

        $have = DB::val("SELECT SUM(qty) FROM store_stock WHERE product_id = ? AND store_id = ? AND $cond", $where);
        if ($have === null) return 0; // рядка немає — магазин цього товару не тримає

        $applied = $delta < 0 ? min((int)$have, -$delta) : $delta;
        if ($applied <= 0) return 0;

        $fn = DB::driver() === 'sqlite' ? 'MAX' : 'GREATEST';
        $expr = $delta < 0 ? "$fn(0, qty - ?)" : 'qty + ?';
        DB::query("UPDATE store_stock SET qty = $expr WHERE product_id = ? AND store_id = ? AND $cond",
            array_merge([$applied], $where));
        return $applied;
    }

    /** Сповіщення магазину про його частину замовлення */
    public static function notifyNew(array $child): void
    {
        $store = $child['store_name'] ?? ($child['store_id']
            ? (DB::val('SELECT name FROM stores WHERE id = ?', [$child['store_id']]) ?? '—') : 'Не призначено');
        Notify::fire('order_new', [
            'number' => $child['number'], 'name' => $child['name'], 'phone' => $child['phone'],
            'delivery' => self::deliveryLabel($child['delivery']),
            'address' => self::deliveryAddress($child),
            'items' => self::itemsSummary((int)$child['id']),
            // Порожньо, коли все на складі, — і рядок зникне сам (Notify::interpolate)
            'shortage' => self::shortageSummary((int)$child['id']),
            'total' => number_format((float)$child['total'], 2, '.', ' '),
            'store' => $store,
        ], $child['store_id'] ? (int)$child['store_id'] : null);
    }
}
