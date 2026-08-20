<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Auth, Csrf, AuthTokens, Telegram, Viber, Newsletter, Notify, Addresses, Settings, FiscalProvider;

class Profile
{
    /**
     * Своя каса продавця.
     *
     * Порожній маршрут означає «як у моїй точці» — і це нормальний стан для
     * більшості: власну касу заводить той, у кого Device Manager стоїть на
     * власному ПК чи телефоні зі своїм ключем.
     */
    private static function kasa(array $u): never
    {
        if (!Auth::can('orders.fiscal')) { flash('error', 'Немає прав пробивати чеки.'); redirect('/profile'); }
        $route = trim((string)($_POST['fiscal_route'] ?? ''));
        $url = trim((string)($_POST['dm_url'] ?? ''));
        DB::update('users', [
            'fiscal_route' => isset(FiscalProvider::ROUTES[$route]) ? $route : null,
            // Лише localhost: Device Manager стоїть на цьому ж комп'ютері й
            // слухає тільки його (див. FiscalProvider::normalizeDmUrl)
            'dm_url' => FiscalProvider::normalizeDmUrl($url),
            'dm_device' => mb_substr(trim((string)($_POST['dm_device'] ?? '')), 0, 100) ?: null,
        ], 'id = ?', [(int)$u['id']]);
        flash('success', $route === '' ? 'Працюєте касою своєї точки.' : 'Налаштування каси збережено.');
        redirect('/profile');
    }

    public static function index(): never
    {
        if (!Auth::check()) redirect('/');
        $u = Auth::user();

        if (is_post()) {
            Csrf::verify();
            // сповіщення зберігаються окремою формою — телефон тут не чіпаємо
            if (($_POST['_action'] ?? '') === 'notify') {
                Notify::saveChannels($u, (array)($_POST['ch'] ?? []));
                flash('success', 'Налаштування сповіщень збережено');
                redirect('/profile');
            }
            if (str_starts_with((string)($_POST['_action'] ?? ''), 'address_')) {
                self::address((string)$_POST['_action']);
            }
            // «Моя каса» — особисте налаштування продавця, як і канали
            // сповіщень: Device Manager стоїть на ЙОГО ПК чи телефоні, з його
            // ключем, і ніхто інший про це не знає краще за нього.
            if (($_POST['_action'] ?? '') === 'kasa') {
                self::kasa($u);
            }
            // normPhoneAny, а не normPhone: закордонний покупець оформлює замовлення
            // з номером +49… (Checkout), і гейт у App.php такий номер пропускає —
            // а профіль його не приймав. Виходило, що людина не могла зберегти
            // власний, уже записаний у неї номер.
            $phone = AuthTokens::normPhoneAny($_POST['phone'] ?? '');
            if (!$phone) {
                flash('error', 'Вкажіть коректний номер телефону — український як 067 123 45 67 або міжнародний із кодом країни через +');
            } else {
                $busy = DB::row('SELECT id FROM users WHERE phone = ? AND id != ?', [$phone, $u['id']]);
                if ($busy) flash('error', 'Цей номер вже привʼязано до іншого акаунта');
                else {
                    $name = trim($_POST['name'] ?? $u['name']) ?: $u['name'];
                    DB::update('users', ['phone' => $phone, 'name' => $name], 'id = ?', [$u['id']]);
                    $email = Newsletter::normEmail((string)$u['email']);
                    if ($email) Newsletter::apply(!empty($_POST['newsletter']), $email, $name, (int)$u['id'], 'profile');
                    flash('success', 'Профіль збережено');
                }
            }
            redirect('/profile');
        }

        $fresh = DB::row('SELECT * FROM users WHERE id = ?', [$u['id']]);
        View::show('account/profile', [
            'u' => $fresh,
            // Блок «моя каса» бачить лише той, хто взагалі пробиває чеки, і
            // лише там, де є куди їх пробивати: покупцю ці слова ні про що
            'can_fiscal' => Auth::can('orders.fiscal') && FiscalProvider::anyConfigured(),
            'fiscal_routes' => FiscalProvider::ROUTES,
            'fiscal_default' => FiscalProvider::DM_DEFAULT_URL,
            'addresses' => Addresses::forUser((int)$u['id']),
            'addr_limit' => Addresses::LIMIT,
            'np_enabled' => (string)Settings::get('np_api_key', '') !== '',
            'mail_email' => Newsletter::normEmail((string)$fresh['email']),
            'subscribed' => Newsletter::isSubscribed(Newsletter::normEmail((string)$fresh['email'])),
            'tg_ready' => Telegram::configured(),
            'tg_bot' => Telegram::configured() ? Telegram::username() : '',
            'viber_ready' => Viber::configured(),
            'viber_uri' => Viber::configured() ? Viber::uri() : '',
            'notify_options' => Notify::channelsFor($fresh),
            'notify_channels' => Notify::CHANNELS,
            'page_title' => 'Мій профіль — ' . cfg('app_name'),
        ]);
    }

    /**
     * Адреси доставки в кабінеті: зберегти (нову або правку), зробити основною,
     * видалити. Права не перевіряємо тут — Addresses працює лише з рядками
     * власника, тож чужий id у POST нічого не дає.
     */
    private static function address(string $action): never
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'address_delete') {
            Addresses::remove(Auth::id(), $id);
            flash('success', 'Адресу видалено');
        } elseif ($action === 'address_default') {
            Addresses::setDefault(Auth::id(), $id);
            flash('success', 'Основну адресу змінено');
        } else {
            $saved = Addresses::save(Auth::id(), [
                'delivery' => $_POST['delivery'] ?? 'np',
                'label' => $_POST['label'] ?? '',
                'city' => $_POST['np_city'] ?? '',   // див. коментар у формі: назва не «city» через Chrome
                'city_ref' => $_POST['city_ref'] ?? '',
                'np_office' => $_POST['np_office'] ?? '',
                'np_office_ref' => $_POST['np_office_ref'] ?? '',
                'np_type' => $_POST['np_type'] ?? 'warehouse',
                'np_street' => $_POST['np_street'] ?? '',
                'np_street_ref' => $_POST['np_street_ref'] ?? '',
                'np_house' => $_POST['np_house'] ?? '',
                'np_flat' => $_POST['np_flat'] ?? '',
                'address' => $_POST['address'] ?? '',
            ], $id);
            if ($saved) flash('success', 'Адресу збережено');
            elseif (count(Addresses::forUser(Auth::id())) >= Addresses::LIMIT) {
                flash('error', 'Збережено вже ' . Addresses::LIMIT . ' адрес — видаліть непотрібні');
            } else flash('error', 'Заповніть адресу: для Нової Пошти — місто, для іншої доставки — саму адресу');
        }
        redirect('/profile');
    }

    /** Видає токен і посилання на бота для підключення Telegram */
    public static function tgLink(): never
    {
        if (!Auth::check() || !Telegram::configured()) json_response(['ok' => false], 400);
        $t = AuthTokens::create('tg_link', Auth::id());
        json_response(['ok' => true, 'url' => 'https://t.me/' . Telegram::username() . '?start=' . $t['token']]);
    }

    /** Поллінг: чи привʼязався Telegram (обробляє getUpdates) */
    public static function tgCheck(): never
    {
        if (!Auth::check()) json_response(['ok' => false], 400);
        Telegram::processUpdates();
        $u = DB::row('SELECT tg_chat_id FROM users WHERE id = ?', [Auth::id()]);
        json_response(['ok' => true, 'linked' => !empty($u['tg_chat_id'])]);
    }

    public static function viberLink(): never
    {
        if (!Auth::check() || !Viber::configured()) json_response(['ok' => false], 400);
        $t = AuthTokens::create('viber_link', Auth::id());
        $uri = Viber::uri();
        json_response(['ok' => true, 'url' => 'viber://pa?chatURI=' . rawurlencode($uri) . '&context=' . $t['token']]);
    }

    public static function viberCheck(): never
    {
        if (!Auth::check()) json_response(['ok' => false], 400);
        $u = DB::row('SELECT viber_id FROM users WHERE id = ?', [Auth::id()]);
        json_response(['ok' => true, 'linked' => !empty($u['viber_id'])]);
    }
}
