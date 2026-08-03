<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Cart, Csrf, Auth, Catalog, OrderFlow, Settings, AuthTokens, Newsletter, RateLimit, Addresses;

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

        // Адреса підставляється, отримувач — ні: див. коментар у Addresses
        $addresses = Addresses::forUser(Auth::id());

        View::show('cart/checkout', [
            'rows' => $rows,
            'totals' => Cart::total(null, self::promo()),
            'stores' => $stores, 'missing' => $missing,
            'promo' => self::promo(),
            'addresses' => $addresses,
            'sel' => $addresses[0] ?? null,   // основна (Addresses::forUser сортує її першою)
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

        // Замовлення завжди розкладається на підзамовлення по магазинах-виконавцях:
        // покупець бачить одне замовлення, кожен продавець — свою частину (див. OrderFlow).
        $placed = OrderFlow::place([
            'number' => $number, 'token' => $token, 'user_id' => Auth::id(),
            'name' => $name, 'phone' => $phone, 'email' => $email,
            'delivery' => $delivery,
            // np_city, а не city: назва «city» вмикала автопідстановку адрес Chrome
            'city' => trim($_POST['np_city'] ?? '') ?: null,
            'np_office' => trim($_POST['np_office'] ?? '') ?: null,
            'address' => trim($_POST['address'] ?? '') ?: null,
            'comment' => trim($_POST['comment'] ?? '') ?: null,
            'store_id' => $storeId,
            'status' => 'new', 'promo_code' => $promo['code'] ?? null,
            'subtotal' => $totals['subtotal'], 'discount' => $totals['discount'], 'total' => $totals['total'],
            'created_at' => now(),
        ], Cart::detailed($storeId), $storeId);

        // Адресу зберігаємо лише за явною галкою і лише залогіненим: гостю
        // її нікуди прив'язати. Самовивіз не зберігаємо — це адреса магазину.
        if (Auth::id() && $delivery !== 'pickup') {
            $addrId = (int)($_POST['address_id'] ?? 0);
            if (!empty($_POST['save_address'])) {
                // id не передаємо навмисно: правка збереженої адреси — дія кабінету.
                // Тут зміна відділення дає нову адресу, а незмінена просто не
                // дублюється (dedupe в Addresses::save) і піднімається в списку.
                Addresses::save(Auth::id(), [
                    'delivery' => $delivery,
                    'city' => $_POST['np_city'] ?? '',
                    'city_ref' => $_POST['city_ref'] ?? '',
                    'np_office' => $_POST['np_office'] ?? '',
                    'address' => $_POST['address'] ?? '',
                ]);
            } elseif ($addrId) {
                Addresses::touch(Auth::id(), $addrId);
            }
        }

        // Розсилка — лише за явною галкою і лише коли вказано email
        if ($email) {
            Newsletter::apply(!empty($_POST['newsletter']), $email, $name, Auth::id(), 'checkout');
        }

        // Сповіщення йде окремо на кожен магазин — це його завдання, а не все замовлення
        foreach ($placed['children'] as $child) OrderFlow::notifyNew($child);

        Cart::clear();
        unset($_SESSION['promo_code']);
        redirect('/order/success/' . $token);
    }

    /** Підтвердження замовлення — лише за токеном із редіректу, номер для цього не годиться */
    public static function success(string $token): never
    {
        $order = DB::row('SELECT * FROM orders WHERE token = ? AND parent_id IS NULL', [$token]);
        if (!$order) { flash('error', 'Замовлення не знайдено.'); redirect('/'); }
        $children = OrderFlow::children((int)$order['id']);
        View::show('cart/success', [
            'order' => $order,
            'children' => $children,
            'items' => self::itemsByOrder($children),
            'page_title' => 'Замовлення прийнято — ' . cfg('app_name'),
        ]);
    }

    /** Кабінет покупця: одне замовлення = одна картка, всередині — частини магазинів */
    public static function myOrders(): never
    {
        if (!Auth::check()) { flash('error', 'Увійдіть, щоб бачити свої замовлення.'); redirect('/'); }
        $orders = DB::all('SELECT * FROM orders WHERE user_id = ? AND parent_id IS NULL ORDER BY id DESC', [Auth::id()]);
        $children = []; $items = [];
        foreach ($orders as $o) {
            $kids = OrderFlow::children((int)$o['id']);
            $children[$o['id']] = $kids;
            $items += self::itemsByOrder($kids);
        }
        View::show('account/orders', [
            'orders' => $orders, 'children' => $children, 'items' => $items,
            'page_title' => 'Мої замовлення — ' . cfg('app_name'),
        ]);
    }

    /** @return array<int,array> позиції, згруповані за id підзамовлення */
    private static function itemsByOrder(array $orders): array
    {
        $out = [];
        foreach ($orders as $o) $out[(int)$o['id']] = OrderFlow::items((int)$o['id']);
        return $out;
    }
}
