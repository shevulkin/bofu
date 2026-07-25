<?php
declare(strict_types=1);

class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessDir = BOFU_ROOT . '/storage/sessions';
            if (!is_dir($sessDir)) @mkdir($sessDir, 0775, true);
            if (is_writable($sessDir)) session_save_path($sessDir);
            session_name(cfg('session_name', 'bofu_sid'));
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'path' => base_url('/'),
            ]);
            session_start();
        }
    }

    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) return null;
        static $cache = null;
        if ($cache && (int)$cache['id'] === (int)$id) return $cache;
        $cache = DB::row('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
        return $cache;
    }

    public static function id(): ?int { $u = self::user(); return $u ? (int)$u['id'] : null; }
    public static function check(): bool { return self::user() !== null; }
    public static function role(): ?string { $u = self::user(); return $u['role'] ?? null; }
    public static function isAdmin(): bool { return self::role() === 'admin'; }
    public static function isSeller(): bool { return self::role() === 'seller'; }
    public static function isStaff(): bool { return in_array(self::role(), ['admin', 'seller'], true); }

    /** Магазини, доступні продавцю (адмін — усі) */
    public static function storeIds(): array
    {
        if (self::isAdmin()) {
            return array_map(fn($r) => (int)$r['id'], DB::all('SELECT id FROM stores'));
        }
        if (self::isSeller()) {
            return array_map(fn($r) => (int)$r['store_id'],
                DB::all('SELECT store_id FROM seller_stores WHERE user_id = ?', [self::id()]));
        }
        return [];
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function requireStaff(): void
    {
        if (!self::isStaff()) { flash('error', 'Потрібен вхід із правами персоналу.'); redirect('/'); }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) { flash('error', 'Доступ лише для адміністратора.'); redirect('/admin'); }
    }

    /** Знайти або створити користувача Google */
    public static function loginWithGoogle(array $profile): void
    {
        $user = DB::row('SELECT * FROM users WHERE google_id = ? OR email = ?', [$profile['sub'], $profile['email']]);
        if ($user) {
            DB::update('users', [
                'google_id' => $profile['sub'],
                'name' => $profile['name'] ?? $user['name'],
                'avatar' => $profile['picture'] ?? $user['avatar'],
            ], 'id = ?', [$user['id']]);
            $id = (int)$user['id'];
        } else {
            $id = DB::insert('users', [
                'google_id' => $profile['sub'], 'email' => $profile['email'],
                'name' => $profile['name'] ?? $profile['email'], 'avatar' => $profile['picture'] ?? null,
                'role' => 'customer', 'active' => 1, 'created_at' => now(),
            ]);
        }
        self::login($id);
    }
}
