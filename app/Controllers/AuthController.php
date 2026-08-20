<?php
declare(strict_types=1);

namespace Controllers;

use DB, Auth, GoogleAuth, Csrf, Notify, AuthTokens, AuthLog, EmailAuth, Telegram, Viber;

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

    /**
     * Вхід поштою, крок 1: надіслати одноразовий код.
     *
     * Відповідь однакова і для наявної адреси, і для незнайомої: цей вхід
     * заразом і реєстрація, тож розрізняти нема чого — а відмінність у
     * відповідях перетворила б форму на спосіб перевіряти чужі адреси.
     */
    public static function emailStart(): never
    {
        Csrf::verify();
        $res = EmailAuth::sendCode($_POST['email'] ?? '');
        if (!$res['ok']) json_response(['ok' => false, 'error' => $res['error'] ?? 'Помилка']);
        // Токен живе в сесії, а не в формі: інакше його видно в розмітці, і
        // код можна було б звіряти з чужим листом у тому ж браузері.
        if (!empty($res['token'])) $_SESSION['email_login'] = ['token' => $res['token'], 'tries' => 0];
        json_response(['ok' => true]);
    }

    /** Вхід поштою, крок 2: звірити код */
    public static function emailVerify(): never
    {
        Csrf::verify();
        $st = $_SESSION['email_login'] ?? null;
        if (!$st) json_response(['ok' => false, 'error' => 'Сесія входу застаріла. Попросіть, будь ласка, новий код.']);
        if ($st['tries'] >= 5) { unset($_SESSION['email_login']); json_response(['ok' => false, 'error' => 'Забагато спроб']); }
        $_SESSION['email_login']['tries']++;

        $res = EmailAuth::verify((string)$st['token'], (string)($_POST['code'] ?? ''));
        if (!$res['ok']) json_response(['ok' => false, 'error' => $res['error'] ?? 'Невірний код']);
        unset($_SESSION['email_login']);
        Auth::login((int)$res['user_id']);
        json_response(['ok' => true, 'logged_in' => true]);
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
        /*
         * Код можна надіслати лише в чат, який уже підтверджений.
         *
         * Це не наша вигадка й не те, що можна «докрутити»: ані Telegram, ані
         * Viber не дають боту написати за номером телефону. Бот відповідає лише
         * тому, хто перший написав йому сам. Тому «зареєструватися за номером,
         * підтвердивши в месенджері» працює тільки в один бік — від месенджера
         * до сайту, і саме так зроблено вхід через Telegram: людина натискає в
         * боті «поділитися номером», а Telegram сам засвідчує, що номер її
         * (contact.user_id проти from.id).
         *
         * Тому тут не «помилка», а вказівка на робочий шлях. Telegram названий
         * першим навмисно: він і створює акаунт, і робить це з підтвердженим
         * номером — тобто рівно те, чого людина хотіла, натиснувши «увійти за
         * номером».
         */
        if (!$user) {
            json_response(['ok' => false, 'error' =>
                'На цей номер ще немає акаунта. Створіть його через Telegram — бот попросить '
                . 'поділитися номером, і акаунт зʼявиться вже з підтвердженим номером. '
                . 'Або увійдіть за поштою: код прийде листом.']);
        }
        if (empty($user['tg_chat_id']) && empty($user['viber_id'])) {
            json_response(['ok' => false, 'error' =>
                'До цього акаунта не підключений жоден месенджер, тож надіслати код нікуди. '
                . 'Увійдіть через Google або Telegram, а тоді підключіть месенджер у профілі.']);
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
