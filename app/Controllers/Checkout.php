<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Cart, Csrf, Auth, Catalog, OrderFlow, Settings, AuthTokens, Newsletter, RateLimit, Addresses, Promo;

class Checkout
{
    public static function form(): never
    {
        if (!Cart::items()) redirect('/cart');
        $rows = Cart::detailed();
        $stores = Catalog::stores();
        // Чого бракує в кожному магазині для самовивозу (з урахуванням варіанта).
        // Разом із тим, де цю позицію можна забрати натомість: людині, що вже
        // обрала самовивіз, сусідня точка майже завжди краща за очікування.
        $missing = [];
        foreach ($stores as $s) {
            $sid = (int)$s['id'];
            foreach ($rows as $r) {
                if ((int)($r['stock'][$sid] ?? 0) >= $r['qty']) continue;
                $title = $r['product']['name'] . ($r['variant'] ? ' — ' . $r['variant']['name'] : '');
                $alt = [];
                foreach ($stores as $o) {
                    $oid = (int)$o['id'];
                    if ($oid !== $sid && (int)($r['stock'][$oid] ?? 0) >= $r['qty']) {
                        $alt[] = $o['city'] ?: $o['name'];
                    }
                }
                $missing[$sid][] = $alt
                    ? $title . (count($alt) > 1 ? ' — є в інших точках: ' : ' — є в іншій точці: ') . implode(', ', $alt)
                    : $title . ' — немає в жодній точці';
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
        return Promo::current(
            isset($_POST['promo_code']) ? Promo::fromInput($_POST['promo_code']) : null,
            Auth::id(), self::buyerPhone());
    }

    /**
     * Кого вважаємо «людиною» для ліміту «один раз на людину»: залогіненого —
     * за акаунтом, гостя — за номером із форми (у профілі номер уже перевірений).
     * Номер отримувача теж годиться: іншого сталого імені гостя ми не знаємо.
     */
    private static function buyerPhone(): ?string
    {
        $u = Auth::user();
        if ($u && !empty($u['phone'])) return (string)$u['phone'];
        return AuthTokens::normPhoneAny($_POST['phone'] ?? '');
    }

    /**
     * Кнопка «Застосувати» біля промокоду. Відповідає перерахованими сумами, а не
     * перезавантаженням сторінки: інакше все вже введене в форму (отримувач,
     * адреса) зникло б заради перевірки коду.
     */
    public static function applyPromo(): never
    {
        Csrf::verify();
        $posted = Promo::fromInput($_POST['promo_code'] ?? '');
        [$promo, $error] = Promo::apply($posted, Auth::id(), self::buyerPhone());
        $rows = Cart::detailed();
        $totals = Cart::total(null, $promo);

        $items = [];
        foreach ($rows as $r) {
            $sum = (float)($r['sum'] ?? 0);
            $cut = Promo::cut($sum, $promo, Promo::ownPercent($r));
            $items[$r['key']] = [
                'sum' => price_fmt($sum - $cut),
                'old' => $cut > 0 ? price_fmt($sum) : null,
            ];
        }
        json_response([
            'ok' => $promo !== null,
            'empty' => trim($posted) === '',
            'code' => $promo['code'] ?? '',
            'label' => $promo ? Promo::label($promo) : '',
            // причина відмови приходить з сервера: «вже використаний» і
            // «такого коду немає» — різні речі, і людина має бачити яка саме
            'error' => $error,
            // Код робочий, але ліг не на все або не повністю: інакше «ви
            // заощадили 0 грн» чи менша за очікувану сума виглядали б як
            // поломка, а не як умова коду.
            'note' => Promo::note($promo, $rows),
            // нульову знижку price_fmt показав би як «За запитом» — це про ціну, не про суму
            'discount' => $totals['discount'] > 0 ? price_fmt($totals['discount']) : '0 грн',
            'subtotal' => price_fmt($totals['subtotal']),
            'total' => price_fmt($totals['total']),
            'items' => $items,
        ]);
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

        // Останнє слово за сервером: код міг вичерпатись, поки людина заповнювала
        // форму. Мовчки прибрати знижку не можна — вона підтверджує суму, яку
        // бачить, тож повертаємо на форму з поясненням і вже без коду.
        $postedCode = Promo::fromInput($_POST['promo_code'] ?? '');
        [$promo, $promoError] = Promo::apply($postedCode, Auth::id(), $phone);
        if ($promoError !== '' && trim($postedCode) !== '') {
            flash('error', $promoError . '. Перевірте суму й підтвердіть замовлення ще раз.');
            redirect('/checkout');
        }
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

        // Використання записуємо лише разом із замовленням — ліміти рахуються
        // по фактах покупок, а не по спробах ввести код у формі
        if ($promo) Promo::recordUse($promo, (int)$placed['id'], Auth::id(), $phone);

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
