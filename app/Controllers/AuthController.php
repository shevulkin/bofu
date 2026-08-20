<?php
declare(strict_types=1);

namespace Controllers;

use DB, Auth, GoogleAuth, Csrf, Notify, AuthTokens, AuthLog, Telegram, Viber;

class AuthController
{
    public static function google(): never
    {
        if (!GoogleAuth::configured()) {
            flash('error', 'Вхід через Google ще не налаштовано. Скористайтесь демо-входом або зверніться до адміністратора.');
            redirect('/');
        }
        redirect(GoogleAuth::authUrl());
    }

    public static function googleCallback(): never
    {
        $profile = GoogleAuth::configured() ? GoogleAuth::handleCallback() : null;
        if (!$profile) { flash('error', 'Не вдалося увійти через Google.'); redirect('/'); }
        $isNew = !DB::row('SELECT 1 FROM users WHERE google_id = ? OR email = ?', [$profile['sub'], $profile['email']]);
        Auth::loginWithGoogle($profile);
        if ($isNew) Notify::fire('user_new', ['name' => $profile['name'] ?? '', 'email' => $profile['email']]);
        flash('success', 'Вітаємо, ' . ($profile['name'] ?? $profile['email']) . '!');
        redirect('/');
    }

    /** Демо-вхід (лише коли demo_login=true у config) */
    public static function demo(): never
    {
        Csrf::verify();
        if (!cfg('demo_login')) redirect('/');
        $role = $_POST['role'] ?? 'customer';
        if (!in_array($role, ['admin', 'seller', 'customer'], true)) $role = 'customer';
        $user = DB::row('SELECT * FROM users WHERE role = ? ORDER BY id LIMIT 1', [$role]);
        if ($user) { Auth::login((int)$user['id']); flash('success', 'Демо-вхід: ' . $user['name']); }
        redirect($role === 'customer' ? '/' : '/admin');
    }

    /** Крок 1 входу через Telegram: токен + посилання на бота */
    public static function tgStart(): never
    {
        if (!Telegram::configured()) json_response(['ok' => false, 'error' => 'Telegram-бот ще не налаштований'], 400);
        $t = AuthTokens::create('tg_login');
        $_SESSION['login_token'] = $t['token'];
        json_response(['ok' => true, 'url' => 'https://t.me/' . Telegram::username() . '?start=' . $t['token']]);
    }

    /** Крок 2: сторінка полить, чи підтверджено вхід у боті */
    public static function tgStatus(): never
    {
        $token = $_SESSION['login_token'] ?? '';
        if (!$token) json_response(['ok' => false], 400);
        Telegram::processUpdates();
        self::loginIfConfirmed($token, 'tg_login');
    }

    public static function viberStart(): never
    {
        if (!Viber::configured()) json_response(['ok' => false, 'error' => 'Viber-бот ще не налаштований'], 400);
        $t = AuthTokens::create('viber_login');
        $_SESSION['login_token'] = $t['token'];
        json_response(['ok' => true, 'url' => 'viber://pa?chatURI=' . rawurlencode(Viber::uri()) . '&context=' . $t['token']]);
    }

    public static function viberStatus(): never
    {
        $token = $_SESSION['login_token'] ?? '';
        if (!$token) json_response(['ok' => false], 400);
        self::loginIfConfirmed($token, 'viber_login');
    }

    private static function loginIfConfirmed(string $token, string $purpose): never
    {
        $row = DB::row('SELECT * FROM auth_tokens WHERE token = ? AND purpose = ? AND expires_at > ?',
            [$token, $purpose, now()]);
        if ($row && $row['confirmed_user_id']) {
            Auth::login((int)$row['confirmed_user_id']);
            unset($_SESSION['login_token']);
            json_response(['ok' => true, 'logged_in' => true]);
        }
        json_response(['ok' => true, 'logged_in' => false]);
    }

    /** Вхід за телефоном: надіслати код у месенджер */
    public static function phoneStart(): never
    {
        Csrf::verify();
        // normPhoneAny, а не normPhone: у базі номер уже лежить у E.164, і код
        // летить у Telegram чи Viber — країна тут ні на що не впливає. З вузьким
        // правилом закордонний покупець, який замовляв із номером +49, не міг
        // увійти власним номером: сайт його приймав, а вхід — ні.
        $phone = AuthTokens::normPhoneAny($_POST['phone'] ?? '');
        if (!$phone) json_response(['ok' => false, 'error' => 'Некоректний номер']);
        $user = DB::row('SELECT * FROM users WHERE phone = ? AND active = 1', [$phone]);
        if (!$user || (empty($user['tg_chat_id']) && empty($user['viber_id']))) {
            json_response(['ok' => false, 'error' => 'Цей номер не привʼязаний до акаунта з месенджером. Увійдіть через Google або Telegram.']);
        }
        $c = AuthTokens::createPhoneCode($phone);
        if ($c === false) json_response(['ok' => false, 'error' => 'Забагато спроб. Спробуйте за годину.']);
        $text = 'Код входу на сайт Beekeeper of Ukraine: ' . $c['code'] . ' (діє 5 хвилин)';
        $via = '';
        if (!empty($user['tg_chat_id']) && Telegram::configured()) { Telegram::send((string)$user['tg_chat_id'], $text); $via = 'Telegram'; }
        elseif (!empty($user['viber_id']) && Viber::configured()) { Viber::send($user['viber_id'], $text); $via = 'Viber'; }
        if (!$via) json_response(['ok' => false, 'error' => 'Канали надсилання недоступні. Зверніться до адміністратора.']);
        $_SESSION['phone_login'] = ['token' => $c['token'], 'phone' => $phone, 'tries' => 0];
        json_response(['ok' => true, 'via' => $via]);
    }

    public static function phoneVerify(): never
    {
        Csrf::verify();
        $st = $_SESSION['phone_login'] ?? null;
        $code = trim($_POST['code'] ?? '');
        if (!$st || !$code) json_response(['ok' => false, 'error' => 'Сесія входу застаріла']);
        if ($st['tries'] >= 5) { unset($_SESSION['phone_login']); json_response(['ok' => false, 'error' => 'Забагато спроб']); }
        $_SESSION['phone_login']['tries']++;
        $row = DB::row("SELECT * FROM auth_tokens WHERE token = ? AND purpose = 'phone_code' AND used = 0 AND expires_at > ?", [$st['token'], now()]);
        if (!$row || !hash_equals($row['code'], $code)) {
            // Номер у журналі маскуємо: серія невдалих спроб має бути видною,
            // а сам журнал не має перетворюватись на список телефонів покупців
            AuthLog::write(null, 'login_failed', 'невірний код для номера ' . self::maskPhone((string)$st['phone']));
            json_response(['ok' => false, 'error' => 'Невірний код']);
        }
        $user = DB::row('SELECT * FROM users WHERE phone = ? AND active = 1', [$row['phone']]);
        if (!$user) json_response(['ok' => false, 'error' => 'Акаунт не знайдено']);
        DB::update('auth_tokens', ['used' => 1], 'id = ?', [$row['id']]);
        unset($_SESSION['phone_login']);
        Auth::login((int)$user['id']);
        json_response(['ok' => true, 'logged_in' => true]);
    }

    /** +380671234567 → +38067…4567: досить, щоб упізнати свій номер, і замало, щоб зібрати чужі */
    private static function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        return $len <= 8 ? '…' : substr($phone, 0, 6) . '…' . substr($phone, -4);
    }

    public static function logout(): never
    {
        Csrf::verify();
        // Пишемо ДО logout(): після нього сесії вже немає, і хто саме вийшов —
        // теж невідомо
        AuthLog::write(Auth::id(), 'logout');
        Auth::logout();
        redirect('/');
    }
}
