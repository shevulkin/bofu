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

    public static function send(string $viberId, string $text, ?array $keyboard = null): void
    {
        $p = [
            'receiver' => $viberId, 'type' => 'text', 'text' => $text,
            'sender' => ['name' => mb_substr(cfg('app_name'), 0, 28)],
        ];
        if ($keyboard !== null) $p['keyboard'] = $keyboard;
        self::api('send_message', $p);
    }

    /** Кнопка «поділитися номером» — у Viber це окремий тип дії */
    private static function askPhone(string $viberId): void
    {
        self::send($viberId, BotAuth::text('bot_ask_phone'), [
            'Type' => 'keyboard', 'DefaultHeight' => false,
            'Buttons' => [[
                'ActionType' => 'share-phone', 'ActionBody' => 'phone',
                'Text' => BotAuth::text('bot_ask_phone_btn'), 'TextSize' => 'regular',
            ]],
        ]);
    }

    /** Підсумок: текст плюс кнопка-посилання назад на сайт */
    private static function sayDone(string $viberId, string $name, string $phone): void
    {
        $url = BotAuth::siteUrl();
        $kb = $url === '' ? null : [
            'Type' => 'keyboard', 'DefaultHeight' => false,
            'Buttons' => [[
                'ActionType' => 'open-url', 'ActionBody' => $url,
                'Text' => BotAuth::text('bot_done_btn'), 'TextSize' => 'regular',
            ]],
        ];
        self::send($viberId, BotAuth::text('bot_done', ['name' => $name, 'phone' => $phone]), $kb);
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

    /** Обробка події webhook: привʼязка viber_id за context-токеном або отриманий контакт */
    public static function handleEvent(array $ev): void
    {
        $type = $ev['event'] ?? '';
        $viberId = $ev['user']['id'] ?? $ev['sender']['id'] ?? null;
        $name = $ev['user']['name'] ?? $ev['sender']['name'] ?? '';
        if (!$viberId) return;

        // Контакт приходить окремим повідомленням, уже без context — тому
        // незавершений вхід шукаємо за самим viber_id.
        if ($type === 'message' && ($ev['message']['type'] ?? '') === 'contact') {
            self::onContact((string)$viberId, $ev['message']['contact'] ?? [], (string)$name);
            return;
        }

        $token = trim((string)($ev['context'] ?? ($type === 'message' ? ($ev['message']['text'] ?? '') : '')));
        if ($token === '') return;

        $row = DB::row('SELECT * FROM auth_tokens WHERE token = ? AND used = 0 AND expires_at > ?', [$token, now()]);
        if (!$row) return;

        if ($row['purpose'] === 'viber_link' && $row['user_id']) {
            // акаунт уже є, номер у ньому теж — питати вдруге немає за що
            DB::update('users', ['viber_id' => $viberId], 'id = ?', [$row['user_id']]);
            DB::update('auth_tokens', ['used' => 1, 'chat_id' => $viberId], 'id = ?', [$row['id']]);
            self::send($viberId, BotAuth::text('bot_linked', ['messenger' => 'Viber']));
        } elseif ($row['purpose'] === 'viber_login') {
            DB::update('auth_tokens', ['chat_id' => $viberId], 'id = ?', [$row['id']]);
            $known = DB::row('SELECT * FROM users WHERE viber_id = ? AND active = 1', [$viberId]);
            $phone = AuthTokens::normPhoneAny((string)($known['phone'] ?? ''));
            if ($known && $phone) self::confirm((int)$row['id'], (int)$known['id'], (string)$viberId, (string)$known['name'], $phone);
            else self::askPhone((string)$viberId);
        }
    }

    /**
     * Відповідь на кнопку «поділитися номером».
     *
     * Перевірки «свій контакт», як у Telegram, тут не треба: share-phone віддає
     * номер самого співрозмовника, переслати чужий цією дією неможливо.
     */
    private static function onContact(string $viberId, array $contact, string $name): void
    {
        $row = BotAuth::pendingLogin('viber_login', $viberId);
        if (!$row) return;

        $phone = AuthTokens::normPhoneAny((string)($contact['phone_number'] ?? ''));
        if (!$phone) { self::send($viberId, BotAuth::text('bot_bad_phone')); self::askPhone($viberId); return; }

        $uid = BotAuth::resolveUser('viber_id', $viberId, $phone, $name);
        if (!$uid) { self::send($viberId, BotAuth::text('bot_expired')); return; }
        self::confirm((int)$row['id'], $uid, $viberId, $name, $phone);
    }

    private static function confirm(int $tokenId, int $uid, string $viberId, string $name, string $phone): void
    {
        DB::update('auth_tokens',
            ['used' => 1, 'chat_id' => $viberId, 'confirmed_user_id' => $uid, 'phone' => $phone],
            'id = ?', [$tokenId]);
        self::sayDone($viberId, $name, $phone);
    }
}
