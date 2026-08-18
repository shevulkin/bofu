<?php
declare(strict_types=1);

/** Одноразові токени: підключення месенджерів, вхід, коди за телефоном */
class AuthTokens
{
    public static function create(string $purpose, ?int $userId = null, array $extra = [], int $ttlMin = 15): array
    {
        $token = bin2hex(random_bytes(16));
        $id = DB::insert('auth_tokens', array_merge([
            'user_id' => $userId, 'purpose' => $purpose, 'token' => $token,
            'ip' => RateLimit::ip(), 'agent' => self::device($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlMin * 60),
            'used' => 0, 'created_at' => now(),
        ], $extra));
        return ['id' => $id, 'token' => $token];
    }

    /**
     * Браузер і система коротким рядком: «Chrome на Windows».
     *
     * Зберігаємо саме такий рядок, а не сирий User-Agent: його читатиме
     * покупець у боті, коли вирішуватиме, його це вхід чи чужий. З рядка на
     * двісті символів він не зрозуміє нічого, а нам більше й не треба.
     *
     * Порядок перевірок важливий: Edge і Opera теж пишуть про себе «Chrome»,
     * а Chrome — «Safari». Тому рідкісніше йде першим.
     */
    public static function device(string $ua): ?string
    {
        $ua = trim($ua);
        if ($ua === '') return null;
        $browser = '';
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'YaBrowser' => 'Yandex', 'Firefox' => 'Firefox',
                  'Chrome' => 'Chrome', 'Safari' => 'Safari'] as $needle => $name) {
            if (str_contains($ua, $needle)) { $browser = $name; break; }
        }
        $os = '';
        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows',
                  'Macintosh' => 'macOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (str_contains($ua, $needle)) { $os = $name; break; }
        }
        if ($browser === '' && $os === '') return mb_substr($ua, 0, 60);
        if ($browser === '') return $os;
        return $os === '' ? $browser : $browser . ' на ' . $os;
    }

    public static function find(string $token, string $purpose): ?array
    {
        return DB::row('SELECT * FROM auth_tokens WHERE token = ? AND purpose = ? AND expires_at > ?', [$token, $purpose, now()]);
    }

    /**
     * Український номер → +380XXXXXXXXX.
     *
     * Це половина правила, а не окремий режим: за межами normPhoneAny() і тестів
     * її ніхто не викликає, і не варто. Форми, вхід і гейт мають судити однаково,
     * інакше номер, з яким людина замовляла, раптом не годиться для входу.
     */
    public static function normPhone(string $phone): ?string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) === 12 && str_starts_with($d, '380')) return '+' . $d;
        if (strlen($d) === 10 && str_starts_with($d, '0')) return '+38' . $d;
        // Девʼять цифр — це номер без нуля й коду (671234567). Якщо нуль там є,
        // то це обрізаний десятизначний, а не «номер без коду»: з '067123456'
        // виходило '+380067123456' — синтаксично схоже на номер, і саме тому
        // небезпечне. Нова Пошта й Telegram такий не візьмуть, а до відправки
        // ніхто не помітить. Краще попросити ввести ще раз, ніж вгадувати.
        if (strlen($d) === 9 && !str_starts_with($d, '0')) return '+380' . $d;
        return null;
    }

    /**
     * Як normPhone(), але не відсікає закордонних покупців: приймає міжнародний
     * номер у форматі +<код країни><номер> (E.164, 10–15 цифр).
     */
    public static function normPhoneAny(string $phone): ?string
    {
        $ua = self::normPhone($phone);
        if ($ua) return $ua;
        $d = preg_replace('/\D/', '', $phone) ?? '';
        // Український номер має сталу довжину: 380 і рівно 9 цифр. Якщо їх
        // більше — це не «закордонний номер», а той самий український із зайвою
        // цифрою: коду країни 380X не існує ні в кого. Без цієї перевірки
        // «+38063463513278» проходило б як міжнародний, і замовлення поїхало б
        // на номер, якого немає, — а помітилось би це аж при дзвінку.
        if (str_starts_with($d, '380')) return null;
        if (str_starts_with(trim($phone), '+') && strlen($d) >= 10 && strlen($d) <= 15) return '+' . $d;
        return null;
    }

    /**
     * Чому номер не годиться — людськими словами.
     *
     * Одне «неправильний номер» на всі випадки змушує гадати, що саме не так,
     * а найчастіша помилка (зайва чи забута цифра) з нього не видно зовсім.
     *
     * @return ?string null — номер придатний
     */
    public static function phoneProblem(string $phone): ?string
    {
        $raw = trim($phone);
        if ($raw === '') return 'Номер порожній';
        if (self::normPhoneAny($raw) !== null) return null;

        $d = preg_replace('/\D/', '', $raw) ?? '';
        if (str_starts_with($d, '380') || str_starts_with($d, '0')) {
            $digits = str_starts_with($d, '380') ? strlen($d) - 3 : strlen($d) - 1;
            return 'Український номер — це код і 7 цифр, разом 9 після +380. Тут їх ' . max(0, $digits)
                . '. Приклад: 0671234567';
        }
        if ($d === '') return 'У номері немає жодної цифри';
        if (!str_starts_with($raw, '+')) return 'Закордонний номер пишіть із «+» і кодом країни, наприклад +49 151 2345678';
        return 'Номер має містити від 10 до 15 цифр — перевірте, чи не загубилась або не зайва цифра';
    }

    /** Створити код входу за телефоном; false якщо перевищено ліміт */
    public static function createPhoneCode(string $phone): array|false
    {
        $recent = (int)DB::val("SELECT COUNT(*) FROM auth_tokens WHERE purpose = 'phone_code' AND phone = ? AND created_at > ?",
            [$phone, date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= 3) return false;
        $code = (string)random_int(100000, 999999);
        $t = self::create('phone_code', null, ['phone' => $phone, 'code' => $code], 5);
        return ['token' => $t['token'], 'code' => $code];
    }

    public static function cleanup(): void
    {
        DB::delete('auth_tokens', 'expires_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}
