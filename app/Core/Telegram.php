<?php
declare(strict_types=1);

/** Telegram Bot API: надсилання, авто-визначення chat_id через getUpdates (працює без webhook) */
class Telegram
{
    public static function token(): string
    { return (string)Settings::get('telegram_bot_token', ''); }

    public static function configured(): bool
    { return self::token() !== ''; }

    public static function api(string $method, array $params = []): array
    {
        if (!self::configured()) return [];
        $ch = curl_init('https://api.telegram.org/bot' . self::token() . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        return is_array($json) ? $json : [];
    }

    public static function send(string $chatId, string $text): void
    { self::api('sendMessage', ['chat_id' => $chatId, 'text' => $text]); }

    /** Ім'я бота (кешується) — для посилань t.me/<bot>?start=... */
    public static function username(): string
    {
        $u = Settings::get('tg_bot_username', '');
        if ($u) return (string)$u;
        $me = self::api('getMe');
        $u = $me['result']['username'] ?? '';
        if ($u) Settings::set('tg_bot_username', $u);
        return (string)$u;
    }

    /**
     * Обробити нові повідомлення бота: шукаємо "/start <token>" і привʼязуємо chat_id
     * до токенів (підключення профілю або вхід). Викликається при поллінгу зі сторінок.
     */
    public static function processUpdates(): void
    {
        if (!self::configured()) return;
        $offset = (int)Settings::get('tg_updates_offset', '0');
        $resp = self::api('getUpdates', ['offset' => $offset + 1, 'timeout' => 0, 'allowed_updates' => '["message"]']);
        foreach (($resp['result'] ?? []) as $upd) {
            $offset = max($offset, (int)$upd['update_id']);
            $msg = $upd['message'] ?? null;
            if (!$msg) continue;
            $text = trim($msg['text'] ?? '');
            $chatId = (string)($msg['chat']['id'] ?? '');
            if (!$chatId || !preg_match('~^/start[ =_]?(.+)$~', $text, $m)) continue;
            $token = trim($m[1]);
            $row = DB::row("SELECT * FROM auth_tokens WHERE token = ? AND used = 0 AND expires_at > ?", [$token, now()]);
            if (!$row) { self::send($chatId, 'Посилання застаріло. Спробуйте ще раз із сайту.'); continue; }

            if ($row['purpose'] === 'tg_link' && $row['user_id']) {
                DB::update('users', ['tg_chat_id' => $chatId], 'id = ?', [$row['user_id']]);
                DB::update('auth_tokens', ['used' => 1, 'chat_id' => $chatId], 'id = ?', [$row['id']]);
                self::send($chatId, '✅ Telegram підключено до вашого акаунта Beekeeper of Ukraine. Сюди приходитимуть сповіщення та коди входу.');
            } elseif ($row['purpose'] === 'tg_login') {
                $user = DB::row('SELECT * FROM users WHERE tg_chat_id = ? AND active = 1', [$chatId]);
                if (!$user) {
                    $name = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? '')) ?: ('Telegram ' . $chatId);
                    $uid = DB::insert('users', [
                        'email' => 'tg' . $chatId . '@telegram.local', 'name' => $name,
                        'role' => 'customer', 'active' => 1, 'tg_chat_id' => $chatId, 'created_at' => now(),
                    ]);
                    Notify::fire('user_new', ['name' => $name, 'email' => 'Telegram']);
                } else {
                    $uid = (int)$user['id'];
                }
                DB::update('auth_tokens', ['used' => 1, 'chat_id' => $chatId, 'confirmed_user_id' => $uid], 'id = ?', [$row['id']]);
                self::send($chatId, '✅ Вхід підтверджено. Поверніться на сайт — ви вже увійшли.');
            }
        }
        Settings::set('tg_updates_offset', (string)$offset);
    }
}
