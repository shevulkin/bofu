<?php
declare(strict_types=1);

/**
 * Вхід і реєстрація поштою: одноразовий код у листі.
 *
 * Навіщо взагалі. До цього акаунт можна було завести лише через Google або
 * Telegram. Людина, у якої є телефон, але немає ані Gmail, ані Telegram, могла
 * купити (гість оформлює замовлення без входу), але не могла мати кабінет:
 * ні історії замовлень, ні збережених адрес, ні «повідомити про наявність».
 * Пошта є практично в кожного, і вона не належить одній компанії — ukr.net,
 * meta.ua, робоча скринька годяться однаково.
 *
 * Чому КОД, а не посилання з листа. Пошту сайт надсилає звичайним mail()
 * (Notify::email), і без SPF/DKIM на домені частина листів осідає в теці
 * «Спам». Код цифрами звідти можна переписати руками, і вхід відбудеться;
 * посилання ж поштові фільтри ще й переписують на свої «безпечні» адреси.
 * Понад те, код нікуди не веде: людину не привчають відкривати посилання з
 * листа «від магазину» — саме на цій звичці й тримається фішинг.
 *
 * З якої адреси. Якщо в налаштуваннях задано mail_from_auth, лист із кодом іде
 * саме з неї (типово login@домен), а не із загальної скриньки магазину. Причина
 * не косметична: репутація адреси спільна на всі її листи, і скарга «це спам»
 * на лист про акцію вимкнула б доставку того єдиного листа, без якого в акаунт
 * не увійти. Поле необовʼязкове — порожнє означає «як було, однією адресою».
 *
 * Чому вхід і реєстрація — це одна дія. Окрема «реєстрація» тут не додає
 * нічого: акаунт створює саме володіння скринькою, а воно доводиться тим
 * самим кодом. Заразом зникає розрізнення «такої пошти немає» / «є» — форма
 * відповідає однаково, і перебором чужих адрес нічого не дізнатись.
 *
 * Чого цей вхід НЕ доводить — номера телефону. Пошта каже, що скринька її, і
 * рівно стільки. Тому phone_verified_at тут не проставляється ніколи, а номер
 * у профілі лишається просто контактом (див. Customers::resolve).
 */
class EmailAuth
{
    /** Скільки живе код і скільки їх можна попросити на одну адресу за годину */
    private const TTL_MIN = 15;
    private const PER_HOUR = 3;
    private const MAX_TRIES = 5;

    /**
     * Адреса, на яку справді можна написати.
     *
     * `.local` відсіюється не для краси: акаунти з месенджерів і покупці, яких
     * завів продавець, мають технічні адреси (`…@telegram.local`,
     * `…@offline.local`). Це не скриньки, лист туди не піде — і, головне, такий
     * акаунт має лишатись недосяжним для входу ззовні. Те саме правило й у
     * Newsletter::normEmail, тому воно тут не вигадується заново.
     */
    public static function normEmail(string $email): ?string
    {
        return Newsletter::normEmail($email);
    }

    /**
     * Створити код і надіслати його листом.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function sendCode(string $rawEmail): array
    {
        $email = self::normEmail($rawEmail);
        if ($email === null) return ['ok' => false, 'error' => 'Вкажіть, будь ласка, коректну адресу пошти'];

        $recent = (int)DB::val(
            "SELECT COUNT(*) FROM auth_tokens WHERE purpose = 'email_code' AND email = ? AND created_at > ?",
            [$email, date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= self::PER_HOUR) {
            return ['ok' => false, 'error' => 'На цю адресу вже надіслано кілька кодів. Спробуйте, будь ласка, за годину.'];
        }

        // Акаунт вимкнений — коду не шлемо, але й не кажемо про це: інакше форма
        // перетворюється на спосіб перевіряти чужі адреси на наявність у базі.
        // Людина побачить звичайне «код надіслано» і не дочекається листа; той,
        // хто дійсно вимкнув акаунт, знає, кому написати.
        $user = DB::row('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && !$user['active']) return ['ok' => true];

        $code = (string)random_int(100000, 999999);
        $t = AuthTokens::create('email_code', null, ['email' => $email, 'code' => $code], self::TTL_MIN);

        $text = "Ваш код для входу на сайт " . cfg('app_name') . ": " . $code
            . "\n\nКод діє " . self::TTL_MIN . " хвилин і потрібен один раз."
            . "\nЯкщо ви не заходили на сайт — просто не вводьте його, більше нічого робити не треба.";
        Notify::email(['email' => $email], $text, ['code' => $code], 'auth_code');

        /*
         * У режимі розробки код лягає ще й у лог.
         *
         * На локальній машині пошти немає взагалі: mail() тихо повертає false,
         * і єдиний спосіб увійти — лізти в таблицю auth_tokens. Це дрібниця,
         * але саме такі дрібниці роблять «підняти проєкт у себе» вправою на
         * витримку.
         *
         * Умова — cfg('debug'), той самий прапорець, яким на бойовому сервері
         * вимкнено показ помилок (bootstrap.php); на production він вимкнений
         * ProdCheck перевіряє це окремо. Тобто в бойовий лог коди не потраплять.
         */
        if (cfg('debug')) error_log('EmailAuth: код для ' . $email . ' — ' . $code);

        return ['ok' => true, 'token' => $t['token']];
    }

    /**
     * Звірити код і повернути id акаунта — знайденого або щойно створеного.
     *
     * @return array{ok:bool,error?:string,user_id?:int}
     */
    public static function verify(string $token, string $code): array
    {
        $code = preg_replace('/\D/', '', trim($code)) ?? '';
        $row = DB::row("SELECT * FROM auth_tokens WHERE token = ? AND purpose = 'email_code' AND used = 0 AND expires_at > ?",
            [$token, now()]);
        if (!$row || $code === '') return ['ok' => false, 'error' => 'Код застарів. Попросіть, будь ласка, новий.'];
        if (!hash_equals((string)$row['code'], $code)) {
            AuthLog::write(null, 'login_failed', 'невірний код для пошти ' . self::maskEmail((string)$row['email']));
            return ['ok' => false, 'error' => 'Невірний код'];
        }

        DB::update('auth_tokens', ['used' => 1], 'id = ?', [(int)$row['id']]);
        $email = (string)$row['email'];
        $user = DB::row('SELECT * FROM users WHERE email = ?', [$email]);

        if ($user) {
            if (!$user['active']) return ['ok' => false, 'error' => 'Цей акаунт вимкнено. Зверніться, будь ласка, до магазину.'];
            // Скринька щойно довела, що вона її: позначку ставимо й тим, хто
            // заводився через Google чи прийшов сюди вперше після оновлення.
            if (empty($user['email_verified_at'])) {
                DB::update('users', ['email_verified_at' => now()], 'id = ?', [(int)$user['id']]);
            }
            return ['ok' => true, 'user_id' => (int)$user['id']];
        }

        // Імені ще не знаємо: питати його в формі входу зайве, людина впише
        // його в профілі (а без телефону далі профілю все одно не пустять).
        $uid = DB::insert('users', [
            'email' => $email, 'name' => 'Покупець',
            'role' => Roles::CUSTOMER, 'active' => 1,
            'email_verified_at' => now(), 'created_at' => now(),
        ]);
        Notify::fire('user_new', ['name' => '', 'email' => $email]);
        return ['ok' => true, 'user_id' => $uid];
    }

    /** i.vasylenko@ukr.net → i.v…o@ukr.net: досить, щоб упізнати свою, і замало, щоб зібрати чужі */
    public static function maskEmail(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) return '…';
        $name = substr($email, 0, $at);
        $rest = substr($email, $at);
        if (mb_strlen($name) <= 3) return mb_substr($name, 0, 1) . '…' . $rest;
        return mb_substr($name, 0, 3) . '…' . mb_substr($name, -1) . $rest;
    }
}
