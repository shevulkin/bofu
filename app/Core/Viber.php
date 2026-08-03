<?php
declare(strict_types=1);

/** Viber Bot API: сповіщення + підключення/вхід через webhook (працює на публічному HTTPS) */
class Viber
{
    /** Підміна токена для перевірки налаштувань — див. Telegram::useToken() */
    private static ?string $override = null;
    public static function useToken(?string $token): void { self::$override = $token; }

    public static function token(): string
    { return self::$override ?? (string)Settings::get('viber_bot_token', ''); }

    public static function configured(): bool
    { return self::token() !== ''; }

    public static function api(string $method, array $data): array
    {
        if (!self::configured()) return [];
        // (object), а не сам масив: json_encode([]) дає "[]", і Viber відповідає
        // на це «400 Bad Request». Саме через це get_account_info(<порожньо>)
        // мовчки не працював, а разом із ним зникала кнопка входу через Viber.
        $ch = curl_init('https://chatapi.viber.com/pa/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode((object)$data, JSON_UNESCAPED_UNICODE),
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

    /**
     * «Це справді ви?» перед входом у вже привʼязаний акаунт — навіщо, див.
     * Telegram::askConfirm(). Viber не має окремих callback-ів: натиснута кнопка
     * приходить звичайним повідомленням, текст якого — її ActionBody.
     */
    private static function askConfirm(string $viberId, array $row): void
    {
        $token = (string)$row['token'];
        self::send($viberId, BotAuth::text('bot_confirm_login', BotAuth::loginFrom($row) + ['messenger' => 'Viber']), [
            'Type' => 'keyboard', 'DefaultHeight' => false,
            'Buttons' => [
                ['ActionType' => 'reply', 'ActionBody' => 'ok:' . $token, 'Columns' => 3,
                 'Text' => BotAuth::text('bot_confirm_btn'), 'TextSize' => 'regular'],
                ['ActionType' => 'reply', 'ActionBody' => 'no:' . $token, 'Columns' => 3,
                 'Text' => BotAuth::text('bot_decline_btn'), 'TextSize' => 'regular'],
            ],
        ]);
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

    /**
     * URI бота для deep-link viber://pa?chatURI=...&context=token.
     *
     * Питається один раз і кешується. Невдалу спробу теж запамʼятовуємо на
     * 10 хвилин: без цього кожен рендер сторінки з вікном входу йшов у мережу
     * і чекав таймауту, тобто одна помилка в налаштуваннях гальмувала весь сайт.
     */
    public static function uri(): string
    {
        $u = (string)Settings::get('viber_bot_uri', '');
        if ($u !== '') return $u;
        if (time() - (int)Settings::get('viber_uri_tried', '0') < 600) return '';

        Settings::set('viber_uri_tried', (string)time());
        $info = self::api('get_account_info', []);
        $u = (string)($info['uri'] ?? '');
        if ($u !== '') Settings::set('viber_bot_uri', $u);
        return $u;
    }

    /** Забути кеш URI — після зміни токена бот може бути вже інший */
    public static function forgetUri(): void
    {
        Settings::set('viber_bot_uri', '');
        Settings::set('viber_uri_tried', '0');
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

        // Відповідь на кнопки «це я» / «це не я» — теж звичайне повідомлення
        if (preg_match('~^(ok|no):(\w+)$~', $token, $m)) { self::onConfirm((string)$viberId, $m[1], $m[2]); return; }

        $row = DB::row('SELECT * FROM auth_tokens WHERE token = ? AND used = 0 AND expires_at > ?', [$token, now()]);
        if (!$row) return;

        if ($row['purpose'] === 'viber_link' && $row['user_id']) {
            // акаунт уже є, номер у ньому теж — питати вдруге немає за що
            DB::update('users', ['viber_id' => $viberId], 'id = ?', [$row['user_id']]);
            DB::update('auth_tokens', ['used' => 1, 'chat_id' => $viberId], 'id = ?', [$row['id']]);
            self::send($viberId, BotAuth::text('bot_linked', ['messenger' => 'Viber']));
        } elseif ($row['purpose'] === 'viber_login') {
            DB::update('auth_tokens', ['chat_id' => $viberId], 'id = ?', [$row['id']]);
            // привʼязаний id підтверджує вхід кнопкою, решта — надсилає контакт;
            // пояснення в BotAuth::linkedUser і Telegram::processUpdates()
            if (BotAuth::linkedUser('viber_id', (string)$viberId)) {
                self::askConfirm((string)$viberId, $row);
            } else {
                self::askPhone((string)$viberId);
            }
        }
    }

    /** Натиснуто «це я» / «це не я» — звірку токена з чатом див. Telegram::onCallback() */
    private static function onConfirm(string $viberId, string $answer, string $token): void
    {
        $row = DB::row('SELECT * FROM auth_tokens WHERE token = ? AND purpose = ? AND chat_id = ? AND used = 0 AND expires_at > ?',
            [$token, 'viber_login', $viberId, now()]);
        if (!$row) { self::send($viberId, BotAuth::text('bot_expired')); return; }

        if ($answer === 'no') {
            DB::update('auth_tokens', ['used' => 1], 'id = ?', [(int)$row['id']]);
            self::send($viberId, BotAuth::text('bot_declined'));
            return;
        }

        $known = BotAuth::linkedUser('viber_id', $viberId);
        if (!$known) { self::askPhone($viberId); return; }
        self::confirm((int)$row['id'], (int)$known['id'], $viberId,
            (string)$known['name'], (string)$known['phone']);
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
