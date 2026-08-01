<?php
declare(strict_types=1);

/**
 * Обмеження частоти звернень — проти ботів, перебору й спаму замовленнями.
 * Рахуємо у БД, а не в сесії: бот просто не надсилає куки.
 */
class RateLimit
{
    /**
     * Зафіксувати спробу. Повертає false, якщо ліміт вичерпано.
     * $ident за замовчуванням — IP; для кодів на телефон логічніше передати сам номер.
     */
    public static function hit(string $action, int $limit, int $windowSec, ?string $ident = null): bool
    {
        $ident = $ident ?? self::ip();
        $since = date('Y-m-d H:i:s', time() - $windowSec);
        try {
            $n = (int)DB::val('SELECT COUNT(*) FROM rate_hits WHERE action = ? AND ident = ? AND created_at > ?',
                [$action, $ident, $since]);
            if ($n >= $limit) return false;
            DB::insert('rate_hits', ['action' => $action, 'ident' => $ident, 'created_at' => now()]);
        } catch (Throwable $e) {
            // не блокуємо покупця, якщо таблиця ще не створена
            return true;
        }
        self::gc();
        return true;
    }

    /** Ліміт вичерпано — коротка відповідь без деталей */
    public static function reject(bool $json = false): never
    {
        http_response_code(429);
        if ($json) json_response(['ok' => false, 'error' => 'Забагато запитів. Спробуйте за кілька хвилин.'], 429);
        header('Content-Type: text/html; charset=utf-8');
        exit('Забагато запитів. Спробуйте, будь ласка, за кілька хвилин.');
    }

    /** hit() + reject() одним викликом */
    public static function guard(string $action, int $limit, int $windowSec, ?string $ident = null, bool $json = false): void
    {
        if (!self::hit($action, $limit, $windowSec, $ident)) self::reject($json);
    }

    /**
     * IP клієнта. Заголовкам проксі віримо лише коли це ввімкнено в конфігу:
     * інакше X-Forwarded-For підробляється і обхід ліміту стає тривіальним.
     */
    public static function ip(): string
    {
        if (cfg('trust_proxy')) {
            $fwd = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($fwd !== '') {
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
            }
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** Прибирання старих записів — зрідка, щоб не робити зайвих запитів */
    private static function gc(): void
    {
        if (random_int(1, 200) !== 1) return;
        try { DB::delete('rate_hits', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]); }
        catch (Throwable $e) { /* не критично */ }
    }
}
