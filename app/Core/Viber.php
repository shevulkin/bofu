<?php
declare(strict_types=1);

/** Viber Bot API: сповіщення + підключення/вхід через webhook (працює на публічному HTTPS) */
class Viber
{
    public static function token(): string
    { return (string)Settings::get('viber_bot_token', ''); }

    public static function configured(): bool
    { return self::token() !== ''; }

    public static function api(string $method, array $data): array
    {
        if (!self::configured()) return [];
        $ch = curl_init('https://chatapi.viber.com/pa/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Viber-Auth-Token: ' . self::token()],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        return is_array($json) ? $json : [];
    }

    public static function send(string $viberId, string $text): void
    {
        self::api('send_message', [
            'receiver' => $viberId, 'type' => 'text', 'text' => $text,
            'sender' => ['name' => mb_substr(cfg('app_name'), 0, 28)],
        ]);
    }

    /** URI бота для deep-link viber://pa?chatURI=...&context=token */
    public static function uri(): string
    {
        $u = Settings::get('viber_bot_uri', '');
        if ($u) return (string)$u;
        $info = self::api('get_account_info', []);
        $u = $info['uri'] ?? '';
        if ($u) Settings::set('viber_bot_uri', $u);
        return (string)$u;
    }

    /** Виклик після збереження токена: реєструє webhook */
    public static function setWebhook(): array
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . base_url('/api/viber/webhook');
        return self::api('set_webhook', ['url' => $url, 'event_types' => ['conversation_started', 'subscribed', 'message'], 'send_name' => true]);
    }

    public static function verifySignature(string $body, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $body, self::token()), $signature);
    }

    /** Обробка події webhook: привʼязка viber_id за context-токеном */
    public static function handleEvent(array $ev): void
    {
        $type = $ev['event'] ?? '';
        $viberId = $ev['user']['id'] ?? $ev['sender']['id'] ?? null;
        $name = $ev['user']['name'] ?? $ev['sender']['name'] ?? '';
        $token = trim((string)($ev['context'] ?? ($type === 'message' ? ($ev['message']['text'] ?? '') : '')));
        if (!$viberId || $token === '') return;

        $row = DB::row('SELECT * FROM auth_tokens WHERE token = ? AND used = 0 AND expires_at > ?', [$token, now()]);
        if (!$row) return;

        if ($row['purpose'] === 'viber_link' && $row['user_id']) {
            DB::update('users', ['viber_id' => $viberId], 'id = ?', [$row['user_id']]);
            DB::update('auth_tokens', ['used' => 1, 'chat_id' => $viberId], 'id = ?', [$row['id']]);
            self::send($viberId, '✅ Viber підключено до вашого акаунта Beekeeper of Ukraine.');
        } elseif ($row['purpose'] === 'viber_login') {
            $user = DB::row('SELECT * FROM users WHERE viber_id = ? AND active = 1', [$viberId]);
            if (!$user) {
                $uid = DB::insert('users', [
                    'email' => 'viber-' . substr(md5($viberId), 0, 10) . '@viber.local',
                    'name' => $name ?: 'Viber користувач', 'role' => 'customer', 'active' => 1,
                    'viber_id' => $viberId, 'created_at' => now(),
                ]);
                Notify::fire('user_new', ['name' => $name, 'email' => 'Viber']);
            } else {
                $uid = (int)$user['id'];
            }
            DB::update('auth_tokens', ['used' => 1, 'chat_id' => $viberId, 'confirmed_user_id' => $uid], 'id = ?', [$row['id']]);
            self::send($viberId, '✅ Вхід підтверджено. Поверніться на сайт.');
        }
    }
}
