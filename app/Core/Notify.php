<?php
declare(strict_types=1);

/**
 * Нотифікації: Telegram / Email / Web Push.
 * Події та шаблони налаштовуються в адмінці (notification_rules).
 * Глобальні перемикачі: settings notify_telegram_enabled / notify_email_enabled / notify_push_enabled / notify_all_enabled
 */
class Notify
{
    public const EVENTS = [
        'order_new'      => 'Нове замовлення',
        'order_status'   => 'Зміна статусу замовлення',
        'user_new'       => 'Новий користувач',
        'stock_low'      => 'Закінчується товар',
    ];

    public const DEFAULT_TEMPLATES = [
        'order_new'    => "🛒 Нове замовлення {number}\nКлієнт: {name}, {phone}\nДоставка: {delivery}\nСума: {total} грн\nМагазин: {store}",
        'order_status' => "📦 Замовлення {number}: статус змінено на «{status}»",
        'user_new'     => "👤 Новий користувач: {name} ({email})",
        'stock_low'    => "⚠️ Товар «{product}» закінчується: залишилось {qty} шт. ({store})",
    ];

    /** Головна точка виклику: Notify::fire('order_new', ['number'=>..., ...], $storeId) */
    public static function fire(string $event, array $vars, ?int $storeId = null): void
    {
        if (!Settings::bool('notify_all_enabled', true)) return;
        $rules = DB::all('SELECT * FROM notification_rules WHERE event = ? AND enabled = 1', [$event]);
        foreach ($rules as $rule) {
            $channelOn = match ($rule['channel']) {
                'telegram' => Settings::bool('notify_telegram_enabled', true),
                'viber'    => Settings::bool('notify_viber_enabled', true),
                'email'    => Settings::bool('notify_email_enabled', true),
                'push'     => Settings::bool('notify_push_enabled', true),
                default    => false,
            };
            if (!$channelOn) continue;
            $tpl = $rule['template'] ?: (self::DEFAULT_TEMPLATES[$event] ?? '');
            $text = self::interpolate($tpl, $vars);
            $recipients = self::recipients($rule['recipients'], $storeId);
            foreach ($recipients as $user) {
                try { self::send($rule['channel'], $user, $text, $vars); }
                catch (Throwable $e) { self::log("send fail {$rule['channel']} u{$user['id']}: " . $e->getMessage()); }
            }
        }
    }

    public static function interpolate(string $tpl, array $vars): string
    {
        foreach ($vars as $k => $v) $tpl = str_replace('{' . $k . '}', (string)$v, $tpl);
        return $tpl;
    }

    /** Одержувачі: адміни та/або продавці магазину */
    private static function recipients(string $mode, ?int $storeId): array
    {
        // Ролі беремо з user_roles — тобто ті, що людина МАЄ. Обрана робоча роль тут
        // ні до чого: адмін, який зараз дивиться очима покупця, має й далі
        // отримувати сповіщення про замовлення.
        $users = [];
        if (in_array($mode, ['admins', 'admins_sellers'], true)) {
            $users = DB::all(
                "SELECT u.* FROM users u JOIN user_roles r ON r.user_id = u.id
                 WHERE r.role = ? AND u.active = 1", [Roles::ADMIN]);
        }
        if (in_array($mode, ['sellers', 'admins_sellers'], true)) {
            if ($storeId !== null) {
                $sellers = DB::all(
                    "SELECT u.* FROM users u
                     JOIN user_roles r ON r.user_id = u.id AND r.role = ?
                     JOIN seller_stores ss ON ss.user_id = u.id
                     WHERE ss.store_id = ? AND u.active = 1", [Roles::SELLER, $storeId]);
            } else {
                $sellers = DB::all(
                    "SELECT u.* FROM users u JOIN user_roles r ON r.user_id = u.id
                     WHERE r.role = ? AND u.active = 1", [Roles::SELLER]);
            }
            foreach ($sellers as $s) $users[] = $s;
        }
        // унікальні
        $seen = []; $out = [];
        foreach ($users as $u) { if (!isset($seen[$u['id']])) { $seen[$u['id']] = 1; $out[] = $u; } }
        return $out;
    }

    private static function send(string $channel, array $user, string $text, array $vars): void
    {
        switch ($channel) {
            case 'telegram': self::telegram($user, $text); break;
            case 'viber':    if (!empty($user['viber_id'])) Viber::send($user['viber_id'], $text); break;
            case 'email':    self::email($user, $text, $vars); break;
            case 'push':     self::push($user, $text); break;
        }
    }

    public static function telegram(array $user, string $text): void
    {
        $chat = $user['tg_chat_id'] ?? null;
        if (!$chat || !Telegram::configured()) return;
        Telegram::send((string)$chat, $text);
    }

    public static function email(array $user, string $text, array $vars): void
    {
        if (empty($user['email'])) return;
        $to = filter_var((string)$user['email'], FILTER_VALIDATE_EMAIL);
        if (!$to) return;
        // адреса відправника задається в адмінці — перенос рядка в ній дав би змогу
        // дописати власні заголовки листа
        $from = (string)Settings::get('mail_from', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $from = str_replace(["\r", "\n"], '', $from);
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $subject = '=?UTF-8?B?' . base64_encode(cfg('app_name') . ' — сповіщення') . '?=';
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n" .
                   "From: " . $from . "\r\n";
        @mail($to, $subject, $text, $headers);
    }

    public static function push(array $user, string $text): void
    {
        // Web Push (VAPID) — активується після деплою на HTTPS. Локально пропускаємо.
        $subs = DB::all('SELECT * FROM push_subscriptions WHERE user_id = ?', [$user['id']]);
        if (!$subs) return;
        WebPush::sendToAll($subs, ['title' => cfg('app_name'), 'body' => $text]);
    }

    private static function httpPost(string $url, array $data): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function log(string $msg): void
    {
        @file_put_contents(BOFU_ROOT . '/storage/logs/notify.log',
            '[' . now() . '] ' . $msg . "\n", FILE_APPEND);
    }
}
