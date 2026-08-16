<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Cart, Catalog, Customers, OrderFlow, AuthTokens, Newsletter, Pos, Promo, Settings,
    Shipments, NovaPoshta, RateLimit;

/**
 * Адмінка замовлень.
 * Адмін працює з головними замовленнями (і бачить усі частини), продавець — зі своїми
 * підзамовленнями, але з правом відкрити головне й побачити картину цілком.
 */
class Orders
{
    public const STATUSES = OrderFlow::STATUSES;

    /** Магазини поточного користувача; null = адмін (усі) */
    private static function myStores(): ?array
    {
        return Auth::can('orders.manage') ? null : Auth::storeIds();
    }

    /** Чи може користувач змінювати це підзамовлення */
    public static function canManage(array $order): bool
    {
        if (Auth::can('orders.manage')) return true;
        if (!$order['parent_id']) return false; // головне веде лише той, хто керує замовленням цілком
        if (!Auth::can('orders.status')) return false;
        return in_array((int)$order['store_id'], Auth::storeIds(), true);
    }

    /** Доступ до сторінки: своє підзамовлення або головне, у якому є своя частина */
    private static function canSee(array $order): bool
    {
        if (Auth::can('orders.manage')) return true;
        // продавець бачить картину по мережі, але правити зможе лише свої точки —
        // це вирішує canManage, а не видимість
        if (Auth::can('orders.view_all')) return true;
        $ids = Auth::storeIds();
        if (!$ids) return false;
        if ($order['parent_id']) return in_array((int)$order['store_id'], $ids, true);
        $in = implode(',', array_map('intval', $ids));
        return (bool)DB::val("SELECT 1 FROM orders WHERE parent_id = ? AND store_id IN ($in) LIMIT 1", [$order['id']]);
    }

    public static function index(): never
    {
        $mine = self::myStores();
        $status = $_GET['status'] ?? 'all';
        // за замовчуванням кабінет заточений під свої точки; «всі» — коли треба
        // подивитись картину по мережі або забрати позицію собі
        $seesAll = $mine !== null && Auth::can('orders.view_all');
        $scope = ($_GET['scope'] ?? 'mine') === 'all' && $seesAll ? 'all' : 'mine';
        $params = [];
        if ($mine === null)       $where = 'o.parent_id IS NULL';      // адмін — замовлення цілком
        elseif ($scope === 'all') $where = 'o.parent_id IS NOT NULL';  // уся мережа, чуже — лише читання
        elseif (!$mine)           $where = '1=0';
        else                      $where = 'o.parent_id IS NOT NULL AND o.store_id IN (' . implode(',', array_map('intval', $mine)) . ')';
        if ($status !== 'all' && isset(self::STATUSES[$status])) { $where .= ' AND o.status = ?'; $params[] = $status; }

        /*
         * Фільтр за точкою.
         *
         * Адмін дивиться головні замовлення, і магазин у них не записаний —
         * він у частинах. Тому для нього умова йде через частини: «замовлення,
         * у якому щось виконує ця точка». Продавець дивиться самі частини, і
         * там магазин лежить у рядку.
         *
         * Список точок теж різний: у своєму режимі продавець фільтрує лише
         * серед власних (у нього їх може бути кілька), в режимі мережі —
         * серед усіх. Пропонувати фільтр, який гарантовано дасть порожньо,
         * означає підказувати неіснуючу роботу.
         */
        $pickable = DB::all('SELECT id, name, city FROM stores ORDER BY sort, id');
        if ($mine !== null && $scope === 'mine') {
            $pickable = array_values(array_filter($pickable, fn($s) => in_array((int)$s['id'], $mine, true)));
        }
        $store = (int)($_GET['store'] ?? 0);
        if ($store && !in_array($store, array_map(fn($s) => (int)$s['id'], $pickable), true)) $store = 0;
        if ($store) {
            if ($mine === null) {
                $where .= ' AND EXISTS (SELECT 1 FROM orders part WHERE part.parent_id = o.id AND part.store_id = ?)';
            } else {
                $where .= ' AND o.store_id = ?';
            }
            $params[] = $store;
        }

        /*
         * Фільтр за станом посилки — питання, які продавець ставить собі
         * щоранку: що ще не відправлено і що вже чекає на покупця. Статус
         * замовлення на них не відповідає: «В дорозі» стоїть і в тієї частини,
         * що годину як виїхала, і в тієї, що третій день лежить у відділенні.
         *
         * Рахується по підзамовленнях (накладна належить їм), тому для адміна,
         * який дивиться головні, умова йде через EXISTS по частинах.
         */
        $ship = (string)($_GET['ship'] ?? 'all');
        // Адмін дивиться головні замовлення, продавець — свої частини; накладна
        // ж лежить завжди на частині. Тому й звʼязок різний: у головного — по
        // всіх його частинах, у частини — по ній самій.
        $link = $mine === null ? 'sh.parent_id = o.id' : 'sh.order_id = o.id';
        $has = static fn(string $cond) => "EXISTS (SELECT 1 FROM shipments sh WHERE $link" . ($cond !== '' ? " AND $cond" : '') . ')';
        if ($ship === 'none') {
            // «без накладної» — лише те, що взагалі мало б їхати поштою:
            // самовивіз і закрите тут лише заважали б
            $where .= " AND o.delivery = 'np' AND o.status NOT IN ('done','canceled') AND NOT " . $has('');
        } elseif ($ship === 'transit') {
            $where .= ' AND ' . $has("sh.phase IN ('new','transit')");
        } elseif ($ship === 'arrived') {
            $where .= ' AND ' . $has("sh.phase = 'arrived'");
        } elseif ($ship === 'problem') {
            $where .= ' AND ' . $has("sh.phase = 'problem'");
        } else {
            $ship = 'all';
        }

        $orders = DB::all(
            "SELECT o.*, s.name AS store_name, p.number AS parent_number, au.name AS assigned_name
             FROM orders o
             LEFT JOIN stores s ON s.id = o.store_id
             LEFT JOIN orders p ON p.id = o.parent_id
             LEFT JOIN users au ON au.id = o.assigned_user_id
             WHERE $where ORDER BY o.id DESC", $params);

        $items = []; $children = [];
        foreach ($orders as $o) {
            $id = (int)$o['id'];
            $items[$id] = $o['parent_id'] ? OrderFlow::items($id) : OrderFlow::allItems($id);
            if (!$o['parent_id']) $children[$id] = OrderFlow::children($id);
        }

        // Накладні одним запитом на всю сторінку. Ключ — підзамовлення, тож
        // і адмінський рядок (де під ним частини), і продавецький (де рядок
        // і є частина) беруть звідси однаково.
        $shipments = [];
        $parentIds = [];
        foreach ($orders as $o) $parentIds[] = (int)($o['parent_id'] ?: $o['id']);
        foreach (Shipments::forParents($parentIds) as $rows) {
            foreach ($rows as $s) $shipments[(int)$s['order_id']] = $s;
        }

        View::show('admin/orders/index', [
            'orders' => $orders, 'items' => $items, 'children' => $children,
            'shipments' => $shipments,
            'ship' => $ship,
            'store' => $store, 'stores_filter' => $pickable,
            'status' => $status, 'statuses' => self::STATUSES,
            'is_seller_view' => $mine !== null,
            'my_store_ids' => $mine ?? [],
            'sees_all' => $seesAll, 'scope' => $scope,
            'page_title' => 'Замовлення — адмінка',
        ], 'layouts/admin');
    }

    // ------------------------------------------------------------------ каса: продаж за покупця

    /**
     * Каса: продавець набирає замовлення за покупця — той подзвонив або
     * прийшов у точку.
     *
     * Один екран, а не майстер із кроків: ліворуч плитка товарів із пошуком і
     * полем сканера, праворуч чек. Асортимент на кілька десятків позицій цілком
     * влазить в екран, і тап по плитці — найшвидший спосіб набрати чек: без
     * пошуку, без сканера, без ходіння сайтом.
     *
     * Покупець — поле чека, а не вхідні двері. Більшість продажів на місці
     * анонімні, і питати «хто це?» перед кожною банкою меду означало б зайвий
     * крок у найчастішому випадку. Номер можна вписати будь-коли: на початку,
     * посеред набору чи перед самим оформленням.
     *
     * Той самий чек живий і на вітрині: смужка внизу приймає товар звідусіль,
     * тож покупцеві можна показати картку товару з фото й описом, не втрачаючи
     * набране (див. Pos і partials/pos_bar).
     */
    public static function pos(): never
    {
        Auth::requireCap('orders.create');
        $stores = self::createStores();
        $errors = [];
        if ($stores && is_post()) $errors = self::posAction($stores);   // ajax-дії виходять усередині

        // Поки продаж не почато, показуємо касу робочої точки продавця: саме
        // її цінник і залишки він побачить, коли покладе перший товар.
        $storeId = Pos::active() ? Pos::storeId() : (int)(Auth::workStoreId() ?? ($stores[0]['id'] ?? 0));
        if ($stores && !in_array($storeId, array_map(fn($s) => (int)$s['id'], $stores), true)) {
            $storeId = (int)$stores[0]['id'];
        }
        $cat = (int)($_GET['cat'] ?? 0);
        $d = Pos::data();

        View::show('admin/orders/pos', [
            'stores' => $stores,
            'store_id' => $storeId,
            'source' => $d['source'] ?? 'offline',
            'active' => Pos::active(),
            'cats' => Catalog::categories(),
            'cat' => $cat,
            'tiles' => $stores ? Pos::tiles($storeId, $cat ?: null) : [],
            'lines' => $stores ? Pos::lines($storeId) : [],
            'totals' => $stores ? Cart::total($storeId) : ['subtotal' => 0, 'discount' => 0, 'total' => 0],
            'customer' => self::posCustomer(),
            'form' => self::posForm(),
            // Сканер без жодного заповненого коду не знайде нічого й виглядатиме
            // зламаним. Тому касa каже про це сама — і веде туди, де це чинять.
            'has_codes' => self::anyCodes(),
            'errors' => $errors,
            'step' => self::posStep($errors),
            'np_enabled' => Settings::get('np_api_key') !== null && Settings::get('np_api_key') !== '',
            'page_title' => 'Каса — адмінка',
        ], 'layouts/admin');
    }

    /**
     * З якого кроку відкрити касу.
     *
     * Сторінка перезавантажується двічі за продаж: коли міняють точку і коли
     * оформлення повернулось із помилкою. В обох випадках кидати людину на
     * початок означало б відбирати зроблене, тому крок їде у формі й
     * повертається сюди.
     *
     * Помилка оформлення завжди веде на третій крок — саме там кнопка, з якої
     * її отримали, і саме там її виправляють. Свіжий чек із товаром — на
     * другий: продаж уже почато, і питати «хто покупець» знову ні до чого.
     */
    private static function posStep(array $errors): int
    {
        if ($errors) return 3;
        $step = (int)($_POST['step'] ?? 0);
        if ($step >= 1 && $step <= 3) return $step;
        return Cart::count() > 0 ? 2 : 1;
    }

    /** Чи заповнений бодай один код — інакше сканер шукатиме в порожнечі */
    private static function anyCodes(): bool
    {
        $filled = "(sku IS NOT NULL AND sku <> '') OR (barcode IS NOT NULL AND barcode <> '')";
        return (bool)(DB::val("SELECT 1 FROM products WHERE $filled LIMIT 1")
            ?? DB::val("SELECT 1 FROM product_variants WHERE $filled LIMIT 1"));
    }

    /** Точки, від імені яких ця людина може продавати: адмін — усі активні, продавець — свої */
    private static function createStores(): array
    {
        $all = Catalog::stores();
        $mine = self::myStores();
        if ($mine === null) return $all;
        return array_values(array_filter($all, fn($s) => in_array((int)$s['id'], $mine, true)));
    }

    /** Хто покупець цього чека — разом із тим, що варто знати продавцю */
    private static function posCustomer(): array
    {
        $d = Pos::data();
        $uid = $d['user_id'] ?? null;
        $phone = (string)($d['phone'] ?? '');
        $orders = $uid ? Customers::orderCount((int)$uid) : 0;
        // Для рядка стану беремо імʼя АКАУНТА, а не набране в формі: продавець
        // перевіряє саме те, що причепив потрібну людину. Своє введене імʼя він
        // і так бачить у полі поруч, а в замовленні це імʼя отримувача — воно
        // цілком може відрізнятись (купують у подарунок, замовляють на маму).
        $account = $uid ? DB::val('SELECT name FROM users WHERE id = ?', [(int)$uid]) : null;

        return [
            'user_id' => $uid,
            'phone' => $phone,
            'name' => (string)($d['name'] ?? ''),
            // «У нього вже 4 замовлення» — те, за чим продавець упізнає свого
            'orders' => $orders,
        ] + self::posCustomerState($uid, $phone, (string)($account ?? ''), $orders);
    }

    /**
     * Стан покупця одним рядком — і те саме речення показується скрізь: під
     * полем телефону, поруч із кнопкою оформлення й у відповіді на пошук.
     *
     * Три стани, і між ними не має бути сумнівів. Найдорожчий — «новий»:
     * продавець мусить розуміти, що акаунт зʼявиться, а не що номер кудись
     * пропав. Тому це сказано словами, а не кольором поля.
     *
     * @return array{state:string,note:string}
     */
    private static function posCustomerState(?int $uid, string $phone, string $name, int $orders): array
    {
        if ($uid) {
            return ['state' => 'found', 'icon' => '✓',
                    'note' => 'Наш покупець: ' . ($name !== '' ? $name : 'без імені')
                        . ' · замовлень уже ' . $orders . '. Це замовлення теж піде в його історію'];
        }
        if ($phone !== '') {
            return ['state' => 'new', 'icon' => '+',
                    'note' => 'Такого номера ще немає. При оформленні створимо акаунт на ' . $phone
                        . ' — покупець зможе входити на сайт цим номером і бачити свої замовлення'];
        }
        return ['state' => 'anon', 'icon' => '—',
                'note' => 'Покупець анонімний: замовлення ні до кого не прикріпиться. '
                    . 'Впишіть номер, якщо треба, щоб покупка потрапила в його історію'];
    }

    /** Поля оформлення. Живуть у формі, а не в сесії: їх заповнюють один раз, у кінці */
    private static function posForm(): array
    {
        $delivery = (string)($_POST['delivery'] ?? 'pickup');
        if (!isset(OrderFlow::DELIVERY[$delivery])) $delivery = 'pickup';
        return [
            'delivery' => $delivery,
            'email' => trim((string)($_POST['email'] ?? '')),
            'city' => trim((string)($_POST['np_city'] ?? '')),
            'city_ref' => trim((string)($_POST['city_ref'] ?? '')),
            'np_office' => trim((string)($_POST['np_office'] ?? '')),
            // Ref відділення — те, з чого потім створюється накладна. Без нього
            // замовлення з каси довелося б доадресовувати руками в картці.
            'np_office_ref' => trim((string)($_POST['np_office_ref'] ?? '')),
            'np_type' => ($_POST['np_type'] ?? 'warehouse') === 'courier' ? 'courier' : 'warehouse',
            'np_street' => trim((string)($_POST['np_street'] ?? '')),
            'np_street_ref' => trim((string)($_POST['np_street_ref'] ?? '')),
            'np_house' => trim((string)($_POST['np_house'] ?? '')),
            'np_flat' => trim((string)($_POST['np_flat'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'comment' => trim((string)($_POST['comment'] ?? '')),
            'promo_code' => Promo::fromInput($_POST['promo_code'] ?? ''),
            // «товар віддано» стоїть у продажу на місці: там замовлення
            // закривається тим самим рухом, яким створюється
            'handed' => is_post() ? !empty($_POST['handed']) : true,
        ];
    }

    /**
     * Дії каси. Дрібні (додати, змінити кількість, скан, покупець) відповідають
     * JSON і не перезавантажують екран: у чеку по десятку рухів на продаж, і
     * кожен із них не має коштувати мигання сторінки.
     *
     * @return string[] помилки оформлення (решта дій виходить усередині)
     */
    private static function posAction(array $stores): array
    {
        $action = (string)($_POST['_action'] ?? '');
        $allowed = array_map(fn($s) => (int)$s['id'], $stores);
        $storeId = (int)($_POST['store_id'] ?? 0);
        if (!in_array($storeId, $allowed, true)) $storeId = (int)(Auth::workStoreId() ?? $stores[0]['id']);
        if (!in_array($storeId, $allowed, true)) $storeId = (int)$stores[0]['id'];

        // Точку продажу міняють до першого товару; далі вона вже в чеку
        if (Pos::active()) Pos::setStore($storeId);
        if (isset($_POST['source'])) Pos::setSource((string)$_POST['source']);

        switch ($action) {
            case 'add':
            case 'scan':
                Pos::ensure($storeId);
                Pos::setStore($storeId);
                self::posAdd($action);          // не повертається
            case 'qty':
                Pos::ensure($storeId);
                Cart::setQty((string)($_POST['key'] ?? ''), (int)($_POST['qty'] ?? 0));
                self::posJson('');
            case 'customer':
                Pos::ensure($storeId);
                self::posCustomerAction();      // не повертається
            case 'cancel':
                Pos::stop();
                flash('success', 'Продаж скасовано, чек порожній.');
                redirect('/admin/orders/new');
            case 'save':
                return self::placeManual($storeId);
        }
        return [];   // зміна точки чи способу — просто перемальовуємо екран
    }

    /** Додавання позиції: тапом по плитці або сканером */
    private static function posAdd(string $action): never
    {
        $pid = (int)($_POST['product_id'] ?? 0);
        $vid = (int)($_POST['variant_id'] ?? 0) ?: null;
        $title = '';

        if ($action === 'scan') {
            $code = trim((string)($_POST['code'] ?? ''));
            $found = Pos::byCode($code);
            if (!$found) {
                // Показуємо сам код: без нього продавцю нічого перенести в
                // картку товару, а порівняти — тим більше.
                $near = Pos::nearMiss($code);
                self::posJson('', 'Код ' . mb_substr($code, 0, 20) . ' не знайдено. '
                    . ($near
                        // Найчастіша причина: остання цифра введена з опискою
                        ? 'Схожий код записаний у «' . $near . '» — там остання цифра інша. Перевірте Каталог → Коди й штрихкоди.'
                        : 'Впишіть його в картку товару: Каталог → Коди й штрихкоди.'));
            }
            if ($found['pick']) self::posJson('', 'Це код товару з фасовками — оберіть потрібну в пошуку або на плитці.');
            $pid = $found['product_id'];
            $vid = $found['variant_id'];
            $title = $found['title'];
        }

        $p = DB::row('SELECT name FROM products WHERE id = ? AND active = 1', [$pid]);
        if (!$p) self::posJson('', 'Товар не знайдено або він вимкнений.');
        if ($title === '') {
            $title = (string)$p['name'];
            if ($vid) {
                $vn = DB::val('SELECT name FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $pid]);
                if ($vn !== null) $title .= ', ' . $vn;
            }
        }

        $added = Cart::add($pid, $vid, max(1, (int)($_POST['qty'] ?? 1)));
        if ($added <= 0) {
            $limit = Cart::limit($pid, $vid);
            self::posJson('', $limit
                ? 'У чеку вже вся наявна кількість — ' . $limit . ' шт.'
                : 'Цього товару немає на складі. Виправте залишок або продайте під замовлення.');
        }
        self::posJson('+ ' . $title);
    }

    /**
     * Покупець чека: знайти за номером і причепити.
     *
     * Знайшли — показуємо, кого саме, і скільки в нього замовлень: продавець має
     * бачити, що причепив правильну людину, а не «щось знайшлось». Не знайшли —
     * кажемо прямо, і акаунт зʼявиться при оформленні (Customers::resolve), бо
     * до того часу продаж може й не відбутись.
     */
    private static function posCustomerAction(): never
    {
        $raw = trim((string)($_POST['phone'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = $raw === '' ? null : AuthTokens::normPhoneAny($raw);

        // Номер, який не є номером, не має тихо перетворитись на «аноніма»:
        // продавець вважатиме, що покупця записано, а замовлення виявиться
        // нічиїм. Тому непридатний номер — це помилка, і попереднє значення
        // покупця лишається на місці, поки його не виправлять.
        if ($raw !== '' && !$phone) {
            self::posJson('', (string)AuthTokens::phoneProblem($raw));
        }

        $found = Customers::find($phone);
        Pos::setCustomer($found ? (int)$found['id'] : null, $phone,
            $found && $name === '' ? (string)$found['name'] : $name);
        self::posJson();
    }

    /** Стан чека для екрана каси й для смужки на вітрині */
    public static function posJson(string $added = '', string $error = ''): never
    {
        $storeId = Pos::storeId() ?: null;
        $lines = [];
        foreach (Pos::lines($storeId) as $r) {
            $lines[] = [
                'key' => $r['key'],
                'title' => $r['product']['name'],
                'variant_name' => $r['variant']['name'] ?? '',
                'qty' => (int)$r['qty'],
                'price_label' => price_fmt($r['price']),
                'sum_label' => price_fmt($r['sum']),
            ];
        }
        $totals = Cart::total($storeId);
        $c = self::posCustomer();
        json_response([
            'ok' => $error === '',
            'lines' => $lines,
            'count' => Cart::count(),
            'total' => $totals['total'],
            'total_label' => price_fmt($totals['total']),
            // Покупець їде у КОЖНІЙ відповіді, а не лише у відповідь на пошук:
            // інакше рядок під полем показував би стан, який був три дії тому.
            'customer' => Pos::label(),
            'customer_state' => $c['state'],
            'customer_note' => $c['note'],
            'customer_icon' => $c['icon'],
            // Нормалізований номер повертаємо в поле: продавець бачить рівно те,
            // що запишеться в замовлення, а не те, що він набрав з пробілами
            'phone' => $c['phone'],
            'name' => $c['name'],
            'added' => $added, 'error' => $error,
        ]);
    }

    /**
     * Оформлення чека. Повертає перелік помилок; коли їх немає — не
     * повертається взагалі, а йде редіректом на створене замовлення.
     *
     * @return string[]
     */
    private static function placeManual(int $storeId): array
    {
        if (!Pos::active()) return ['Чек порожній — додайте товари.'];
        $form = self::posForm();
        $d = Pos::data();
        $errors = [];

        $rows = Pos::lines($storeId);
        if (!$rows) $errors[] = 'Чек порожній — додайте товари';
        foreach ($rows as $r) {
            $title = $r['product']['name'] . ($r['variant'] ? ', ' . $r['variant']['name'] : '');
            // Ціна «За запитом» у чеку перетворилась би на нуль — це не знижка,
            // а невказана ціна, і вирішувати її треба в картці товару.
            if ($r['price'] === null) $errors[] = 'Ціна не вказана: ' . $title;
        }

        // Номер беремо з поля, а не з сесії: воно перед очима, і саме йому
        // вірить продавець. Інакше номер, набраний за секунду до натискання
        // «Оформити» (пошук ще не встиг відповісти), тихо не потрапив би в
        // замовлення — і воно вийшло б нічиїм.
        $rawPhone = trim((string)($_POST['phone'] ?? ($d['phone'] ?? '')));
        $phone = $rawPhone === '' ? null : AuthTokens::normPhoneAny($rawPhone);
        if ($rawPhone !== '' && !$phone) {
            $errors[] = 'Номер «' . $rawPhone . '» не годиться. '
                . AuthTokens::phoneProblem($rawPhone)
                . '. Виправте або очистіть поле — тоді покупець буде анонімним';
        }

        // Немає номера — покупець лишається анонімним, і це дозволено рівно там,
        // де працює: людина забирає товар з рук просто зараз. Дзвінок без номера
        // неможливий за визначенням, а доставку нікому підтвердити й нікому
        // віддати посилку.
        if (!$phone && $rawPhone === '') {
            if (($d['source'] ?? 'offline') === 'phone') $errors[] = 'Запишіть номер, з якого дзвонять — без нього замовлення нікому підтвердити';
            elseif ($form['delivery'] !== 'pickup') $errors[] = 'Без номера можлива лише видача на місці: посилку нікому вручити';
        }

        $name = trim((string)($_POST['name'] ?? '')) ?: trim((string)($d['name'] ?? ''));
        if ($name === '' && $form['delivery'] !== 'pickup') $errors[] = 'Вкажіть імʼя отримувача — воно потрібне для відправлення';
        if ($name === '') $name = 'Покупець';

        $email = $form['email'] === '' ? null : Newsletter::normEmail($form['email']);
        if ($form['email'] !== '' && !$email) $errors[] = 'Email виглядає некоректним — виправте або лишіть порожнім';

        // Промокод перевіряємо номером, а не акаунтом: акаунта може ще не бути,
        // а ліміт «раз на людину» рахується і за номером теж (Promo::usedBy).
        $promo = null;
        if (trim($form['promo_code']) !== '') {
            [$promo, $promoError] = Promo::check($form['promo_code'], $d['user_id'] ?? null, $phone);
            if (!$promo) $errors[] = $promoError;
        }

        if ($errors) return $errors;

        // Продаж із порожнього складу означає, що склад розійшовся з дійсністю.
        // Мовчки списати «в мінус» не можна: наступний покупець побачить на
        // сайті товар, якого немає. Тому — те саме правило, що й на вітрині.
        $short = OrderFlow::unavailable($rows);
        if ($short) return ['Товару немає на складі: ' . OrderFlow::unavailableLine($short)
            . '. Виправте залишки в картці товару або приберіть позицію.'];

        $subtotal = 0.0; $discount = 0.0;
        foreach ($rows as $r) {
            $sum = (float)$r['sum'];
            $subtotal += $sum;
            $discount += Promo::cut($sum, $promo, Promo::ownPercent($r));
        }

        $userId = Customers::resolve($phone, $name);
        $number = 'BOFU-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        try {
            $placed = OrderFlow::place([
                'number' => $number, 'token' => bin2hex(random_bytes(16)), 'user_id' => $userId,
                'name' => $name, 'phone' => $phone ?? '', 'email' => $email,
                'delivery' => $form['delivery'],
                'city' => $form['city'] ?: null,
                'np_office' => $form['np_office'] ?: null,
                'address' => $form['address'] ?: null,
                // Довідникові посилання Нової Пошти — лише для доставки нею:
                // у самовивозі вони порожні, і накладна там ні до чого
                'city_ref' => $form['delivery'] === 'np' ? ($form['city_ref'] ?: null) : null,
                'np_type' => $form['np_type'],
                'np_office_ref' => $form['delivery'] === 'np' && $form['np_type'] === 'warehouse'
                    ? ($form['np_office_ref'] ?: null) : null,
                'np_street' => $form['np_type'] === 'courier' ? ($form['np_street'] ?: null) : null,
                'np_street_ref' => $form['np_type'] === 'courier' ? ($form['np_street_ref'] ?: null) : null,
                'np_house' => $form['np_type'] === 'courier' ? ($form['np_house'] ?: null) : null,
                'np_flat' => $form['np_type'] === 'courier' ? ($form['np_flat'] ?: null) : null,
                'comment' => $form['comment'] ?: null,
                // Магазин у головному — це місце самовивозу, як і на вітрині.
                // Виконавцем він стає окремо, третім аргументом place().
                'store_id' => $form['delivery'] === 'pickup' ? $storeId : null,
                'source' => $d['source'] ?? 'offline', 'created_by_user_id' => Auth::id(),
                'status' => 'new', 'promo_code' => $promo['code'] ?? null,
                'subtotal' => $subtotal, 'discount' => $discount,
                'total' => max(0, $subtotal - $discount),
                'created_at' => now(),
            ], $rows, $storeId);
        } catch (\RuntimeException $e) {
            return [$e->getMessage()];
        }

        if ($promo) Promo::recordUse($promo, (int)$placed['id'], $userId, $phone);

        OrderFlow::log((int)$placed['id'], null, 'created',
            'Оформив продавець — ' . mb_strtolower(OrderFlow::sourceLabel($d['source'] ?? 'offline'))
            . ($phone ? '' : ', покупець без номера') . '.', Auth::id());

        // Товар уже в руках покупця — замовлення закривається тим самим рухом.
        // Статус ставимо через setStatus(), а не UPDATE: на ньому висять історія,
        // зведений статус головного й облік промокоду.
        if ($form['handed'] && $form['delivery'] === 'pickup') {
            foreach ($placed['children'] as $c) OrderFlow::setStatus((int)$c['id'], 'done', Auth::id());
        } else {
            // Сповіщення «нове замовлення» має сенс лише там, де його ще комусь
            // виконувати. Продавцю, який щойно сам його й пробив, воно ні до чого.
            foreach ($placed['children'] as $c) OrderFlow::notifyNew($c);
        }

        // Чек закрито: власний кошик продавця повертається, смужка на вітрині гасне
        Pos::stop();

        flash('success', 'Замовлення ' . $number . ' оформлено'
            . ($userId ? '' : ' (покупець анонімний)') . '.');
        redirect('/admin/orders/' . $placed['id']);
    }

    /**
     * Пошук товару для каси (JSON).
     *
     * Окремий рядок на кожну фасовку: продавець шукає «мед 0.5», а не товар,
     * усередині якого потім ще обирати фасування. Ціна й залишок — того
     * магазину, від імені якого зараз продають: у сусідній точці вони інші.
     */
    public static function search(): never
    {
        Auth::requireCap('orders.create');
        $q = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) json_response(['items' => []]);

        $storeId = (int)($_GET['store_id'] ?? 0);
        $allowed = array_map(fn($s) => (int)$s['id'], self::createStores());
        if (!in_array($storeId, $allowed, true)) $storeId = 0;

        $like = '%' . $q . '%';
        $products = DB::all(
            'SELECT * FROM products WHERE active = 1 AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
             ORDER BY name LIMIT 15', [$like, $like, $like]);

        $items = [];
        foreach ($products as $p) {
            $variants = Catalog::variants((int)$p['id']);
            if ($variants) {
                foreach ($variants as $v) $items[] = self::searchRow($p, $v, $storeId ?: null);
            } else {
                $items[] = self::searchRow($p, null, $storeId ?: null);
            }
        }
        json_response(['items' => array_slice($items, 0, 30)]);
    }

    private static function searchRow(array $p, ?array $v, ?int $storeId): array
    {
        [$price] = Catalog::price($p, $v, $storeId);
        $stock = Catalog::stockByStore((int)$p['id'], $v ? (int)$v['id'] : null);
        return [
            'product_id' => (int)$p['id'],
            'variant_id' => $v ? (int)$v['id'] : 0,
            'title' => (string)$p['name'],
            'variant_name' => $v ? (string)$v['name'] : '',
            'price' => $price,
            'price_label' => price_fmt($price),
            // Залишок саме тієї точки, від імені якої продають; без вибраної —
            // сума по мережі, щоб було видно бодай «є десь».
            'stock' => $storeId ? (int)($stock[$storeId] ?? 0) : array_sum($stock),
            'made_to_order' => !empty($p['made_to_order']),
        ];
    }
    /**
     * Одна сторінка і для головного замовлення, і для підзамовлення: завжди видно
     * замовлення цілком, а керування зʼявляється лише там, де є права.
     */
    public static function view(int $id): never
    {
        $order = OrderFlow::order($id);
        if (!$order || !self::canSee($order)) redirect('/admin/orders');
        if (is_post()) self::handle($order);

        $parent = OrderFlow::head($order);
        $children = OrderFlow::children((int)$parent['id']);

        $items = []; $stock = []; $manage = [];
        // Накладна створюється на кожну частину окремо, тож і форма своя в
        // кожної: вага, післяплата й опис у різних магазинів різні
        $shipments = Shipments::forParent((int)$parent['id']);
        $shipForm = []; $shipGaps = []; $shipSender = [];
        foreach ($children as $c) {
            $rows = OrderFlow::items((int)$c['id']);
            $items[(int)$c['id']] = $rows;
            $manage[(int)$c['id']] = self::canManage($c);
            if (!isset($shipments[(int)$c['id']]) && (string)$parent['delivery'] === 'np') {
                $shipForm[(int)$c['id']] = Shipments::defaults($c, $parent);
                $shipGaps[(int)$c['id']] = Shipments::missing($c, $parent);
                // Окремо від решти причин: відправника заповнюють у налаштуваннях,
                // а не в картці, і сказати про це треба інакше — див. вигляд
                $shipSender[(int)$c['id']] = Shipments::senderReady($c['store_id'] ? (int)$c['store_id'] : null);
            }
            foreach ($rows as $it) {
                if (!$it['product_id']) continue;
                $stock[(int)$it['id']] = Catalog::stockByStore((int)$it['product_id'], $it['variant_id'] ? (int)$it['variant_id'] : null);
            }
        }

        // нотатки живуть у тій самій стрічці подій, але показуємо їх окремо:
        // серед статусів вони губляться, а читають їх перед роботою
        $all = OrderFlow::events((int)$parent['id']);
        $notes = array_values(array_filter($all, fn($e) => $e['type'] === 'note'));
        $events = array_values(array_filter($all, fn($e) => $e['type'] !== 'note'));

        View::show('admin/orders/view', [
            'order' => $order,          // те, що відкрили
            'parent' => $parent,        // головне замовлення
            'children' => $children,    // усі частини магазинів
            'focus' => $order['parent_id'] ? (int)$order['id'] : null,
            'items' => $items,
            'item_stock' => $stock,
            'can_manage' => $manage,
            'assignees' => self::assignees($children),
            'stores' => Catalog::stores(),
            'events' => $events,
            'notes' => $notes,
            'can_note' => Auth::can('orders.note'),
            'can_assign' => Auth::can('orders.assign'),
            'shipments' => $shipments,
            'ship_form' => $shipForm,
            'ship_gaps' => $shipGaps,
            'ship_sender_ready' => $shipSender,
            'can_ship' => Auth::can('orders.ship'),
            // Доставка належить замовленню цілком, тож правити її може той, хто
            // веде хоч одну його частину: везти доведеться йому, і саме він
            // телефонує покупцю уточнити. Продавець, який дивиться чуже
            // замовлення через orders.view_all, форми не побачить — інакше вона
            // показувалась би лише щоб відмовити на збереженні.
            'can_edit_delivery' => Auth::can('orders.ship') && in_array(true, $manage, true),
            'ship_payers' => Shipments::PAYERS,
            'ship_payments' => Shipments::PAYMENTS,
            'np_enabled' => NovaPoshta::enabled(),
            'statuses' => self::STATUSES,
            'can_manage_parent' => Auth::can('orders.manage'),
            'page_title' => 'Замовлення ' . $order['number'] . ' — адмінка',
        ], 'layouts/admin');
    }

    /** Хто взяв частини в роботу: [id підзамовлення => ['name','at','is_me']] */
    private static function assignees(array $children): array
    {
        $ids = [];
        foreach ($children as $c) if ($c['assigned_user_id']) $ids[] = (int)$c['assigned_user_id'];
        if (!$ids) return [];
        $names = [];
        foreach (DB::all('SELECT id, name FROM users WHERE id IN (' . implode(',', array_unique($ids)) . ')') as $u) {
            $names[(int)$u['id']] = $u['name'];
        }
        $out = [];
        foreach ($children as $c) {
            $uid = (int)($c['assigned_user_id'] ?? 0);
            if (!$uid) continue;
            $out[(int)$c['id']] = [
                'name' => $names[$uid] ?? '—',
                'at' => $c['assigned_at'],
                'is_me' => $uid === (int)Auth::id(),
            ];
        }
        return $out;
    }

    /** POST зі сторінки замовлення: статус, передача позиції, робота над частиною, нотатка */
    private static function handle(array $order): void
    {
        $parent = OrderFlow::head($order);
        $back = '/admin/orders/' . $order['id'];
        // діяти можна лише в межах відкритого замовлення
        $tree = [(int)$parent['id'] => $parent];
        foreach (OrderFlow::children((int)$parent['id']) as $c) $tree[(int)$c['id']] = $c;

        $action = $_POST['action'] ?? 'status';

        if ($action === 'note') {
            Auth::requireCap('orders.note');
            $text = trim((string)($_POST['note'] ?? ''));
            if ($text === '') { flash('error', 'Нотатка порожня.'); redirect($back); }
            if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);
            OrderFlow::log((int)$parent['id'], $order['parent_id'] ? (int)$order['id'] : null,
                'note', $text, Auth::id());
            flash('success', 'Нотатку додано.');
            redirect($back);
        }

        if ($action === 'claim' || $action === 'release') {
            Auth::requireCap('orders.assign');
            $target = $tree[(int)($_POST['order_id'] ?? 0)] ?? null;
            // мітка ставиться на частину магазину, а не на замовлення цілком
            if (!$target || !$target['parent_id'] || !self::canManage($target)) {
                flash('error', 'Немає прав брати цю частину в роботу.');
                redirect($back);
            }
            self::setAssignee($target, $action === 'claim');
            redirect($back);
        }

        // Адреса доставки належить замовленню цілком, а не частині: усі
        // магазини везуть в одне й те саме відділення. Тому окремо від
        // ship_*, які працюють з однією частиною.
        if ($action === 'np_address') {
            self::npAddress($order, $parent, $back);
        }

        // Накладні. Дії згруповані, бо перевірка прав у них спільна: накладну
        // веде той, хто веде саму частину (свій магазин), плюс окреме право
        // orders.ship — вона коштує грошей і створюється від імені магазину.
        if (str_starts_with((string)$action, 'ship_')) {
            self::shipment($action, $tree, $parent, $back);
        }

        if ($action === 'transfer') {
            $item = DB::row('SELECT * FROM order_items WHERE id = ?', [(int)($_POST['item_id'] ?? 0)]);
            $src = $item ? ($tree[(int)$item['order_id']] ?? null) : null;
            if (!$src || !self::canManage($src)) { flash('error', 'Немає прав передавати цю позицію.'); redirect($back); }
            try {
                flash('success', OrderFlow::transferItem((int)$item['id'], (int)($_POST['to_store_id'] ?? 0), Auth::id()));
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            // якщо дивилися підзамовлення, яке спорожніло й закрилось, — вертаємось на головне
            if (!OrderFlow::order((int)$order['id'])) $back = '/admin/orders/' . $parent['id'];
            redirect($back);
        }

        $target = $tree[(int)($_POST['order_id'] ?? 0)] ?? null;
        $new = $_POST['status'] ?? '';
        if (!$target || !self::canManage($target)) { flash('error', 'Немає прав змінювати цей статус.'); redirect($back); }
        if (OrderFlow::setStatus((int)$target['id'], $new, Auth::id())) {
            flash('success', ($target['parent_id'] ? 'Статус частини ' . $target['number'] : 'Статус замовлення')
                . ' оновлено: ' . OrderFlow::statusLabel($new));
        }
        redirect($back);
    }

    /**
     * Дообрати відділення з довідника НП.
     *
     * Потрібне для замовлень, у яких є назва, але немає Ref: старі (до цієї
     * інтеграції) та ті, де покупець вписав відділення руками, не торкнувшись
     * підказки. Без цього такі замовлення назавжди лишились би без накладної,
     * а продавцю лишалось би оформлювати їх у кабінеті НП і вписувати номер.
     *
     * Пишемо і в головне, і в частини: адреса успадковується при створенні, а
     * далі живе своєю копією в кожному підзамовленні (див. OrderFlow::INHERITED).
     */
    private static function npAddress(array $order, array $parent, string $back): never
    {
        Auth::requireCap('orders.ship');
        // Правити доставку може той, хто веде хоч одну частину цього замовлення:
        // везти доведеться йому, і саме він телефонує покупцю уточнити
        $mine = false;
        foreach (OrderFlow::children((int)$parent['id']) as $c) if (self::canManage($c)) { $mine = true; break; }
        if (!$mine) { flash('error', 'Немає прав правити доставку цього замовлення.'); redirect($back); }
        if (Shipments::forParent((int)$parent['id'])) {
            // Накладна вже виписана на стару адресу — мовчки перевести замовлення
            // на самовивіз означало б посилку, що їде в нікуди
            flash('error', 'Спершу відкріпіть накладну — доставку виписаної посилки так не змінюють.');
            redirect($back);
        }

        $delivery = (string)($_POST['delivery'] ?? '');
        if (!isset(OrderFlow::DELIVERY[$delivery])) $delivery = (string)$parent['delivery'];

        // Порожні поля всіх трьох способів: перемикаючись, лишати хвости
        // попереднього не можна — самовивіз із відділенням НП читається як
        // «то куди ж воно їде».
        $patch = [
            'delivery' => $delivery,
            'city' => null, 'city_ref' => null, 'np_type' => 'warehouse',
            'np_office' => null, 'np_office_ref' => null,
            'np_street' => null, 'np_street_ref' => null, 'np_house' => null, 'np_flat' => null,
            'address' => null,
        ];
        $storeId = null;

        if ($delivery === 'np') {
            $uuid = static fn($v) => preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', trim((string)$v))
                ? trim((string)$v) : null;
            $type = ($_POST['np_type'] ?? 'warehouse') === 'courier' ? 'courier' : 'warehouse';
            $city = trim((string)($_POST['np_city'] ?? ''));
            $cityRef = $uuid($_POST['city_ref'] ?? '');
            if ($city === '') { flash('error', 'Вкажіть місто доставки.'); redirect($back); }
            // Ref не вимагаємо: без ключа API підказок немає взагалі, і забороняти
            // тоді правити адресу означало б замкнене коло. Накладну за такою
            // адресою не створити — про це скаже сам блок відправлення.
            $patch['city'] = $city;
            $patch['city_ref'] = $cityRef;
            $patch['np_type'] = $type;
            if ($type === 'courier') {
                $street = trim((string)($_POST['np_street'] ?? ''));
                $house = trim((string)($_POST['np_house'] ?? ''));
                if ($street === '' || $house === '') {
                    flash('error', 'Для курʼєра потрібні вулиця й номер будинку.');
                    redirect($back);
                }
                $patch['np_street'] = $street;
                $patch['np_street_ref'] = $uuid($_POST['np_street_ref'] ?? '');
                $patch['np_house'] = $house;
                $patch['np_flat'] = trim((string)($_POST['np_flat'] ?? '')) ?: null;
            } else {
                $office = trim((string)($_POST['np_office'] ?? ''));
                if ($office === '') { flash('error', 'Вкажіть відділення або поштомат.'); redirect($back); }
                $patch['np_office'] = $office;
                $patch['np_office_ref'] = $uuid($_POST['np_office_ref'] ?? '');
            }
        } elseif ($delivery === 'pickup') {
            // store_id у головному — це точка видачі, а не виконавець. Приймаємо
            // лише чинну активну: неіснуючий id залишив би замовлення без адреси.
            $sid = (int)($_POST['pickup_store_id'] ?? 0);
            if (!$sid || !DB::row('SELECT id FROM stores WHERE id = ? AND active = 1', [$sid])) {
                flash('error', 'Оберіть магазин, з якого покупець забере замовлення.');
                redirect($back);
            }
            $storeId = $sid;
        } else {
            $address = trim((string)($_POST['address'] ?? ''));
            if ($address === '') { flash('error', 'Опишіть, як саме доставляєте.'); redirect($back); }
            $patch['address'] = mb_substr($address, 0, 200);
        }

        // Адреса однакова в усіх частинах — вона успадкована й лежить копією в
        // кожній. А от store_id у частині означає зовсім інше (магазин-виконавець),
        // тож точку видачі ставимо лише головному: інакше самовивіз перекинув би
        // усі позиції в одну точку разом із залишками.
        DB::update('orders', $patch, 'id = ? OR parent_id = ?', [(int)$parent['id'], (int)$parent['id']]);
        // Точку видачі ставимо (або знімаємо) лише головному: у частині те саме
        // поле означає магазин-виконавця, і затерти його — це втратити, хто
        // взагалі везе замовлення. Перехід на пошту точку видачі прибирає:
        // забирати вже нема звідки.
        DB::update('orders', ['store_id' => $storeId], 'id = ?', [(int)$parent['id']]);

        $where = $delivery === 'pickup'
            ? (string)(DB::val('SELECT name FROM stores WHERE id = ?', [$storeId]) ?? '')
            : OrderFlow::deliveryAddress($patch);
        OrderFlow::log((int)$parent['id'], null, 'shipment',
            'доставку змінено: ' . OrderFlow::deliveryLabel($delivery) . ($where !== '' ? ', ' . $where : ''),
            Auth::id());
        flash('success', 'Доставку збережено: ' . OrderFlow::deliveryLabel($delivery)
            . ($where !== '' ? ' — ' . $where : ''));
        redirect($back);
    }

    /**
     * Накладні Нової Пошти з картки замовлення.
     *
     * Усі дії стосуються ОДНІЄЇ частини — тієї, чий магазин веде цей продавець.
     * Тому перевірка одна на всіх: частина має бути з відкритого замовлення
     * (tree) і в межах прав людини (canManage). Право orders.ship понад те:
     * дивитись і вести замовлення можна й без нього.
     */
    private static function shipment(string $action, array $tree, array $parent, string $back): never
    {
        Auth::requireCap('orders.ship');
        $child = $tree[(int)($_POST['order_id'] ?? 0)] ?? null;
        if (!$child || !$child['parent_id'] || !self::canManage($child)) {
            flash('error', 'Немає прав керувати відправленням цієї частини.');
            redirect($back);
        }
        $shipment = Shipments::forOrder((int)$child['id']);

        if ($action === 'ship_create') {
            // Кожна накладна — це справжня посилка й гроші. Ліміт тут не від
            // ботів, а від подвійного натискання й від нетерплячого F5.
            RateLimit::guard('np_ttn', 60, 3600);
            $r = Shipments::create($child, $parent, (array)($_POST['ship'] ?? []), Auth::id());
            flash($r['ok'] ? 'success' : 'error', $r['ok']
                ? 'Накладну створено: ' . $r['shipment']['number']
                : 'Накладну не створено. ' . $r['error']);
            redirect($back);
        }

        if ($action === 'ship_attach') {
            $r = Shipments::attach($child, $parent, (string)($_POST['ttn'] ?? ''), Auth::id());
            flash($r['ok'] ? 'success' : 'error', $r['ok']
                ? 'Накладну ' . $r['shipment']['number'] . ' прикріплено.'
                : $r['error']);
            redirect($back);
        }

        if (!$shipment) { flash('error', 'Накладної для цієї частини немає.'); redirect($back); }

        if ($action === 'ship_refresh') {
            RateLimit::guard('np_track', 120, 3600);
            $changed = Shipments::refresh([$shipment], Auth::id());
            $fresh = Shipments::forOrder((int)$child['id']);
            flash('success', $changed
                ? 'Статус оновлено: ' . Shipments::statusLabel($fresh ?: $shipment)
                : 'Нова Пошта відповіла те саме: ' . Shipments::statusLabel($fresh ?: $shipment));
            redirect($back);
        }

        if ($action === 'ship_remove') {
            $r = Shipments::remove($shipment, Auth::id());
            flash($r['note'] !== '' ? 'error' : 'success',
                'Накладну ' . $shipment['number'] . ' відкріплено.' . ($r['note'] !== '' ? ' ' . $r['note'] : ''));
            redirect($back);
        }

        flash('error', 'Невідома дія з накладною.');
        redirect($back);
    }

    /**
     * Мітка «в роботі» — без замка: інший продавець може перебрати частину на себе.
     * Замок тут зробив би більше шкоди, ніж користі: людина забула зняти — і частина
     * висить, доки хтось не покличе адміна.
     */
    private static function setAssignee(array $target, bool $claim): void
    {
        $prev = $target['assigned_user_id']
            ? DB::val('SELECT name FROM users WHERE id = ?', [(int)$target['assigned_user_id']])
            : null;
        DB::update('orders', [
            'assigned_user_id' => $claim ? Auth::id() : null,
            'assigned_at' => $claim ? now() : null,
        ], 'id = ?', [(int)$target['id']]);

        $who = Auth::user()['name'] ?? '';
        $msg = $claim
            ? $target['number'] . ': узяв(ла) в роботу ' . $who
                . ($prev && (int)$target['assigned_user_id'] !== (int)Auth::id() ? ' (раніше — ' . $prev . ')' : '')
            : $target['number'] . ': знято з роботи' . ($prev ? ' (' . $prev . ')' : '');
        OrderFlow::log((int)$target['parent_id'], (int)$target['id'], 'assign', $msg, Auth::id());
        flash('success', $claim ? 'Взято в роботу.' : 'Знято з роботи.');
    }
}
