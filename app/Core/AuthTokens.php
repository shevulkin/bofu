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
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlMin * 60),
            'used' => 0, 'created_at' => now(),
        ], $extra));
        return ['id' => $id, 'token' => $token];
    }

    public static function find(string $token, string $purpose): ?array
    {
        return DB::row('SELECT * FROM auth_tokens WHERE token = ? AND purpose = ? AND expires_at > ?', [$token, $purpose, now()]);
    }

    /** Нормалізація телефону до +380XXXXXXXXX */
    public static function normPhone(string $phone): ?string
    {
        $d = preg_replace('/\D/', '', $phone);
        if (strlen($d) === 12 && str_starts_with($d, '380')) return '+' . $d;
        if (strlen($d) === 10 && str_starts_with($d, '0')) return '+38' . $d;
        if (strlen($d) === 9) return '+380' . $d;
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
        if (str_starts_with(trim($phone), '+') && strlen($d) >= 10 && strlen($d) <= 15) return '+' . $d;
        return null;
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
