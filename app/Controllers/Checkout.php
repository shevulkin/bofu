<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Cart, Csrf, Auth, Catalog, Notify, Settings, AuthTokens, Newsletter, RateLimit;

class Checkout
{
    public static function form(): never
    {
        if (!Cart::items()) redirect('/cart');
        $rows = Cart::detailed();
        $stores = Catalog::stores();
        // чого бракує в кожному магазині для самовивозу (з урахуванням варіанта)
        $missing = [];
        foreach ($stores as $s) {
            $sid = (int)$s['id'];
            foreach ($rows as $r) {
                if ((int)($r['stock'][$sid] ?? 0) < $r['qty']) {
                    $missing[$sid][] = $r['product']['name'] . ($r['variant'] ? ' — ' . $r['variant']['name'] : '');
                }
            }
        }
        // Дані залогіненого покупця підставляємо самі: email приходить з Google,
        // телефон уже перевірений гейтом у App::run()
        $u = Auth::user();
        $email = $u && !str_ends_with((string)$u['email'], '.local') ? (string)$u['email'] : '';

        View::show('cart/checkout', [
            'rows' => $rows,
            'totals' => Cart::total(null, self::promo()),
            'stores' => $stores, 'missing' => $missing,
            'promo' => self::promo(),
            'np_enabled' => Settings::get('np_api_key') !== null && Settings::get('np_api_key') !== '',
            'pre' => ['name' => $u['name'] ?? '', 'phone' => $u['phone'] ?? '', 'email' => $email],
            'subscribed' => Newsletter::isSubscribed($email ?: null),
            'page_title' => 'Оформлення замовлення — ' . cfg('app_name'),
        ]);
    }

    private static function promo(): ?array
    {
        $code = trim($_POST['promo_code'] ?? $_SESSION['promo_code'] ?? '');
        if ($code === '') return null;
        $row = DB::row('SELECT * FROM promo_codes WHERE code = ? AND active = 1', [strtoupper($code)]);
        if ($row && ($row['expires_at'] === null || $row['expires_at'] === '' || $row['expires_at'] >= date('Y-m-d'))) {
            $_SESSION['promo_code'] = strtoupper($code);
            return $row;
        }
        return null;
    }

    public static function submit(): never
    {
        Csrf::verify();
        if (!Cart::items()) redirect('/cart');
        // приманка для ботів: поле сховане стилями, людина його не заповнить
        if (trim($_POST['website'] ?? '') !== '') { flash('error', 'Не вдалося оформити замовлення.'); redirect('/checkout'); }
        RateLimit::guard('checkout', 15, 3600);

        $name = trim($_POST['name'] ?? '');
        // Замовлення без робочого телефону неможливе: номер нормалізуємо до +380XXXXXXXXX
        // (або міжнародного E.164) і зберігаємо вже у цьому вигляді
        $phone = AuthTokens::normPhoneAny($_POST['phone'] ?? '');
        $emailRaw = trim($_POST['email'] ?? '');
        $email = $emailRaw === '' ? null : Newsletter::normEmail($emailRaw);
        $delivery = $_POST['delivery'] ?? 'np';
        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Вкажіть ім\'я отримувача';
        if (!$phone) $errors[] = 'Вкажіть коректний номер телефону — без нього ми не зможемо підтвердити замовлення';
        if ($emailRaw !== '' && !$email) $errors[] = 'Email виглядає некоректним — виправте або залиште поле порожнім';
        if (!in_array($delivery, ['np', 'pickup', 'other'], true)) $delivery = 'other';

        // Магазин впливає на ціну (store_prices + акції магазину), тож приймаємо лише
        // існуючий активний і лише для самовивозу — інакше id підбирається руками в POST
        $storeId = null;
        if ($delivery === 'pickup') {
            $sid = (int)($_POST['store_id'] ?? 0);
            if ($sid && DB::row('SELECT id FROM stores WHERE id = ? AND active = 1', [$sid])) $storeId = $sid;
            if (!$storeId) $errors[] = 'Оберіть магазин для самовивозу';
        }

        if ($errors) {
            flash('error', implode('. ', $errors));
            redirect('/checkout');
        }

        $promo = self::promo();
        $totals = Cart::total($storeId, $promo);
        $number = 'BOFU-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        // Адреса сторінки підтвердження: номер короткий і передбачуваний, тому
        // посилання йде за окремим випадковим токеном
        $token = bin2hex(random_bytes(16));

        $orderId = DB::tx(function () use ($number, $token, $name, $phone, $email, $delivery, $storeId, $totals, $promo) {
            $orderId = DB::insert('orders', [
                'number' => $number, 'token' => $token, 'user_id' => Auth::id(),
                'name' => $name, 'phone' => $phone, 'email' => $email,
                'delivery' => $delivery,
                'city' => trim($_POST['city'] ?? '') ?: null,
                'np_office' => trim($_POST['np_office'] ?? '') ?: null,
                'address' => trim($_POST['address'] ?? '') ?: null,
                'comment' => trim($_POST['comment'] ?? '') ?: null,
                'store_id' => $storeId,
                'status' => 'new', 'promo_code' => $promo['code'] ?? null,
                'subtotal' => $totals['subtotal'], 'discount' => $totals['discount'], 'total' => $totals['total'],
                'created_at' => now(),
            ]);
            foreach (Cart::detailed($storeId) as $r) {
                DB::insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $r['product']['id'], 'variant_id' => $r['variant']['id'] ?? null,
                    'title' => $r['product']['name'], 'variant_name' => $r['variant']['name'] ?? null,
                    'price' => $r['price'] ?? 0, 'qty' => $r['qty'], 'sum' => $r['sum'] ?? 0,
                ]);
                // списання залишків саме того варіанта, що замовили:
                // з обраного магазину або з того, де його найбільше
                $pid = (int)$r['product']['id'];
                $vid = isset($r['variant']['id']) ? (int)$r['variant']['id'] : null;
                // товар з варіантами без вибраного варіанта (старий кошик) — списувати нема з чого
                if ($vid === null && Catalog::hasVariants($pid)) continue;
                $byStore = Catalog::stockByStore($pid, $vid);
                $sid = $storeId;
                if (!$sid) {
                    arsort($byStore);
                    foreach ($byStore as $candidate => $qty) { if ($qty > 0) { $sid = $candidate; break; } }
                }
                if ($sid) {
                    $fn = DB::driver() === 'sqlite' ? 'MAX' : 'GREATEST';
                    $cond = $vid === null ? 'variant_id IS NULL' : 'variant_id = ?';
                    $params = [$r['qty'], $pid, $sid];
                    if ($vid !== null) $params[] = $vid;
                    DB::query("UPDATE store_stock SET qty = $fn(0, qty - ?) WHERE product_id = ? AND store_id = ? AND $cond", $params);
                }
            }
            return $orderId;
        });

        // Розсилка — лише за явною галкою і лише коли вказано email
        if ($email) {
            Newsletter::apply(!empty($_POST['newsletter']), $email, $name, Auth::id(), 'checkout');
        }

        $storeName = $storeId ? (DB::val('SELECT name FROM stores WHERE id = ?', [$storeId]) ?? '—') : 'Онлайн';
        Notify::fire('order_new', [
            'number' => $number, 'name' => $name, 'phone' => $phone,
            'delivery' => ['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'][$delivery],
            'total' => number_format($totals['total'], 2, '.', ' '),
            'store' => $storeName,
        ], $storeId);

        Cart::clear();
        unset($_SESSION['promo_code']);
        redirect('/order/success/' . $token);
    }

    /** Підтвердження замовлення — лише за токеном із редіректу, номер для цього не годиться */
    public static function success(string $token): never
    {
        $order = DB::row('SELECT * FROM orders WHERE token = ?', [$token]);
        if (!$order) { flash('error', 'Замовлення не знайдено.'); redirect('/'); }
        View::show('cart/success', ['order' => $order, 'page_title' => 'Замовлення прийнято — ' . cfg('app_name')]);
    }

    public static function myOrders(): never
    {
        if (!Auth::check()) { flash('error', 'Увійдіть, щоб бачити свої замовлення.'); redirect('/'); }
        $orders = DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [Auth::id()]);
        $items = [];
        foreach ($orders as $o) $items[$o['id']] = DB::all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
        View::show('account/orders', ['orders' => $orders, 'items' => $items, 'page_title' => 'Мої замовлення — ' . cfg('app_name')]);
    }
}
