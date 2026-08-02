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

    public static function send(string $chatId, string $text, ?array $markup = null): void
    {
        $p = ['chat_id' => $chatId, 'text' => $text];
        if ($markup !== null) $p['reply_markup'] = json_encode($markup, JSON_UNESCAPED_UNICODE);
        self::api('sendMessage', $p);
    }

    /** Клавіатура з єдиною кнопкою «поділитися номером» */
    private static function askPhone(string $chatId): void
    {
        self::send($chatId, BotAuth::text('bot_ask_phone'), [
            'keyboard' => [[['text' => BotAuth::text('bot_ask_phone_btn'), 'request_contact' => true]]],
            'resize_keyboard' => true, 'one_time_keyboard' => true,
        ]);
    }

    /** Підсумкове повідомлення: прибираємо клавіатуру й даємо кнопку-посилання на сайт */
    private static function sayDone(string $chatId, string $name, string $phone): void
    {
        self::send($chatId, BotAuth::text('bot_done', ['name' => $name, 'phone' => $phone]),
            ['remove_keyboard' => true]);
        $url = BotAuth::siteUrl();
        // Кнопка-посилання окремим повідомленням: inline-клавіатура й remove_keyboard
        // в одному reply_markup не поєднуються — це різні типи розмітки.
        if ($url !== '') {
            self::send($chatId, '👇', ['inline_keyboard' => [[
                ['text' => BotAuth::text('bot_done_btn'), 'url' => $url],
            ]]]);
        }
    }

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
            $chatId = (string)($msg['chat']['id'] ?? '');
            if (!$chatId) continue;
            $name = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));

            // Контакт приходить окремим повідомленням — це відповідь на нашу кнопку
            if (isset($msg['contact'])) { self::onContact($chatId, $msg, $name); continue; }

            $text = trim($msg['text'] ?? '');
            if (!preg_match('~^/start[ =_]?(.+)$~', $text, $m)) continue;
            $token = trim($m[1]);
            $row = DB::row("SELECT * FROM auth_tokens WHERE token = ? AND used = 0 AND expires_at > ?", [$token, now()]);
            if (!$row) { self::send($chatId, BotAuth::text('bot_expired')); continue; }

            if ($row['purpose'] === 'tg_link' && $row['user_id']) {
                // Підключення до наявного акаунта: номер там уже є (без нього на сайт
                // не пускає гейт), тож питати його вдруге немає за що.
                DB::update('users', ['tg_chat_id' => $chatId], 'id = ?', [$row['user_id']]);
                DB::update('auth_tokens', ['used' => 1, 'chat_id' => $chatId], 'id = ?', [$row['id']]);
                self::send($chatId, BotAuth::text('bot_linked', ['messenger' => 'Telegram']));
            } elseif ($row['purpose'] === 'tg_login') {
                // Токен лишається невикористаним: вхід завершить контакт, а не /start.
                DB::update('auth_tokens', ['chat_id' => $chatId], 'id = ?', [$row['id']]);
                $known = DB::row('SELECT * FROM users WHERE tg_chat_id = ? AND active = 1', [$chatId]);
                $phone = AuthTokens::normPhoneAny((string)($known['phone'] ?? ''));
                if ($known && $phone) {
                    // Номер уже підтверджено раніше — вдруге не турбуємо
                    self::confirm((int)$row['id'], (int)$known['id'], $chatId, (string)$known['name'], $phone);
                } else {
                    self::askPhone($chatId);
                }
            }
        }
        Settings::set('tg_updates_offset', (string)$offset);
    }

    /**
     * Людина натиснула «поділитися номером».
     *
     * Ключова перевірка — contact.user_id проти from.id: у Telegram можна
     * переслати ЧУЖИЙ контакт із адресної книги, і без цієї перевірки нею
     * можна було б увійти в чужий акаунт, знаючи лише номер.
     */
    private static function onContact(string $chatId, array $msg, string $name): void
    {
        $row = BotAuth::pendingLogin('tg_login', $chatId);
        if (!$row) return;                       // контакт без початого входу — ігноруємо

        $contact = $msg['contact'] ?? [];
        $ownerId = (string)($contact['user_id'] ?? '');
        $fromId = (string)($msg['from']['id'] ?? '');
        if ($ownerId === '' || $fromId === '' || $ownerId !== $fromId) {
            self::send($chatId, BotAuth::text('bot_foreign_contact'));
            self::askPhone($chatId);
            return;
        }
        $phone = AuthTokens::normPhoneAny((string)($contact['phone_number'] ?? ''));
        if (!$phone) { self::send($chatId, BotAuth::text('bot_bad_phone')); self::askPhone($chatId); return; }

        $uid = BotAuth::resolveUser('tg_chat_id', $chatId, $phone, $name);
        if (!$uid) { self::send($chatId, BotAuth::text('bot_expired')); return; }  // акаунт вимкнено
        self::confirm((int)$row['id'], $uid, $chatId, $name, $phone);
    }

    /** Позначити токен підтвердженим — сторінка входу побачить це своїм опитуванням */
    private static function confirm(int $tokenId, int $uid, string $chatId, string $name, string $phone): void
    {
        DB::update('auth_tokens',
            ['used' => 1, 'chat_id' => $chatId, 'confirmed_user_id' => $uid, 'phone' => $phone],
            'id = ?', [$tokenId]);
        self::sayDone($chatId, $name, $phone);
    }
}
