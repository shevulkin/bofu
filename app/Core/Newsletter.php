<?php
declare(strict_types=1);

/**
 * Підписка на розсилку. Email — завжди необовʼязковий: підписка існує лише там,
 * де людина сама поставила галку. Відписка — за токеном із листа, без входу на сайт.
 */
class Newsletter
{
    public static function normEmail(string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        // технічні адреси акаунтів із месенджерів — не поштові скриньки
        if (str_ends_with($email, '.local')) return null;
        return $email;
    }

    public static function find(string $email): ?array
    {
        $email = self::normEmail($email);
        return $email ? DB::row('SELECT * FROM subscribers WHERE email = ?', [$email]) : null;
    }

    public static function isSubscribed(?string $email): bool
    {
        if (!$email) return false;
        $row = self::find($email);
        return $row !== null && (int)$row['active'] === 1;
    }

    /** Підписати (або повторно активувати) адресу. Повертає false, якщо email некоректний. */
    public static function subscribe(string $email, ?string $name = null, ?int $userId = null, string $source = 'checkout'): bool
    {
        $email = self::normEmail($email);
        if (!$email) return false;
        $row = DB::row('SELECT * FROM subscribers WHERE email = ?', [$email]);
        if ($row) {
            DB::update('subscribers', [
                'active' => 1, 'unsubscribed_at' => null,
                'name' => $name ?: $row['name'],
                'user_id' => $userId ?? $row['user_id'],
            ], 'id = ?', [$row['id']]);
        } else {
            DB::insert('subscribers', [
                'email' => $email, 'name' => $name ?: null, 'user_id' => $userId,
                'source' => $source, 'active' => 1, 'token' => bin2hex(random_bytes(16)),
                'created_at' => now(),
            ]);
        }
        return true;
    }

    /** Зняти підписку за адресою (з профілю). Мовчки нічого не робить, якщо підписки немає. */
    public static function unsubscribe(string $email): void
    {
        $row = self::find($email);
        if ($row) self::deactivate($row);
    }

    /** Зняти підписку за токеном із листа. Повертає адресу, якщо токен валідний. */
    public static function unsubscribeByToken(string $token): ?string
    {
        $row = DB::row('SELECT * FROM subscribers WHERE token = ?', [$token]);
        if (!$row) return null;
        self::deactivate($row);
        return $row['email'];
    }

    private static function deactivate(array $row): void
    {
        if ((int)$row['active'] === 1) {
            DB::update('subscribers', ['active' => 0, 'unsubscribed_at' => now()], 'id = ?', [$row['id']]);
        }
    }

    /** Персональне посилання на відписку — вставляти в кожен лист розсилки */
    public static function unsubscribeUrl(string $email): ?string
    {
        $row = self::find($email);
        return $row ? url('/unsubscribe/' . $row['token']) : null;
    }

    /** Синхронізувати підписку з галкою у формі */
    public static function apply(bool $wanted, string $email, ?string $name = null, ?int $userId = null, string $source = 'checkout'): void
    {
        if ($wanted) self::subscribe($email, $name, $userId, $source);
        else self::unsubscribe($email);
    }
}
