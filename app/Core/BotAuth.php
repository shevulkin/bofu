<?php
declare(strict_types=1);

/**
 * Спільна частина входу через месенджер: тексти, посилання на сайт і те,
 * як номер телефону перетворюється на акаунт.
 *
 * Чому взагалі просимо номер. Раніше бот створював акаунт одразу після /start,
 * і людина потрапляла на сайт без телефону — а він обовʼязковий (гейт у App::run
 * не пускає далі профілю). Виходило: увійшов, і одразу впираєшся у форму. Гірше,
 * що той самий покупець, який колись замовляв за номером, отримував ДРУГИЙ акаунт:
 * замовлення лишались у старому.
 *
 * Тепер номер — частина входу, а не наслідок. Він же й склеює акаунти:
 * знайшли за номером — заходимо в наявний, ні — створюємо новий уже з номером.
 *
 * Telegram і Viber відрізняються лише способом попросити контакт, тому все,
 * що не стосується конкретного API, живе тут.
 */
class BotAuth
{
    /** Тексти бота. Адмін править їх у Налаштуваннях; тут — розумні значення за замовчуванням. */
    public const TEXTS = [
        'bot_ask_phone' => [
            'Підтвердіть, що це ви: поділіться номером телефону кнопкою нижче. Без номера ми не зможемо повідомити про замовлення.',
            'Прохання поділитися номером',
        ],
        'bot_ask_phone_btn' => ['📱 Поділитися номером', 'Напис на кнопці «поділитися номером»'],
        'bot_done' => [
            "✅ Готово, {name}! Ви увійшли на сайт {site_name}.\nМожна повертатися — усе вже працює.",
            'Успішний вхід (доступні {name}, {phone}, {site_name})',
        ],
        'bot_done_btn' => ['Повернутись на сайт', 'Напис на кнопці-посиланні після входу'],
        'bot_bad_phone' => [
            'Не вдалося прочитати номер. Спробуйте ще раз кнопкою нижче — надішліть саме свій контакт.',
            'Номер не розпізнано',
        ],
        'bot_foreign_contact' => [
            'Це чужий контакт. Щоб увійти, поділіться власним номером.',
            'Надіслано чужий контакт',
        ],
        'bot_expired' => [
            'Посилання застаріло. Поверніться на сайт і почніть вхід заново.',
            'Посилання застаріло',
        ],
        'bot_linked' => [
            '✅ {messenger} підключено до вашого акаунта {site_name}. Сюди приходитимуть сповіщення та коди входу.',
            'Месенджер підключено до наявного акаунта (доступний {messenger})',
        ],
    ];

    /**
     * Текст із налаштувань або типовий, з підставленими {плейсхолдерами}.
     *
     * Весь набір плейсхолдерів відомий заздалегідь і завжди підставляється —
     * навіть порожнім. Інакше адмін, який вписав {phone} у текст, де номера
     * немає, показав би покупцю дослівне «{phone}».
     */
    public static function text(string $key, array $vars = []): string
    {
        $t = trim((string)Settings::get($key, ''));
        if ($t === '') $t = self::TEXTS[$key][0] ?? '';
        $vars += [
            'name' => '', 'phone' => '', 'messenger' => '',
            'site_name' => (string)cfg('app_name'), 'site' => self::siteUrl(),
        ];
        foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
        return $t;
    }

    /**
     * Адреса сайту для кнопки в боті.
     *
     * Обчислити з поточного запиту можна не завжди: Viber стукає у webhook сам,
     * а Telegram опитується з будь-якої сторінки. Тому адміну дано поле — воно
     * головніше. Автовизначення лишається як зручність для локальної роботи.
     */
    public static function siteUrl(): string
    {
        $set = trim((string)Settings::get('bot_site_url', ''));
        if ($set !== '') return rtrim($set, '/');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') return '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return rtrim($scheme . '://' . $host . base_url(''), '/');
    }

    /**
     * Знайти або створити акаунт за номером і привʼязкою до месенджера.
     *
     * Порядок пошуку важливий: спершу за номером, і лише потім за id месенджера.
     * Номер — це те, чим людина себе називає всюди (замовлення, вхід за кодом),
     * тож саме він склеює її записи. Якби шукали спершу за chat_id, покупець із
     * замовленнями на +380… отримав би окремий акаунт лише тому, що вперше
     * прийшов через бота.
     *
     * @param string $idField 'tg_chat_id' | 'viber_id'
     * @return int id акаунта
     */
    public static function resolveUser(string $idField, string $extId, string $phone, string $name): int
    {
        if (!in_array($idField, ['tg_chat_id', 'viber_id'], true)) {
            throw new InvalidArgumentException('Невідоме поле месенджера: ' . $idField);
        }
        $user = DB::row('SELECT * FROM users WHERE phone = ? ORDER BY id LIMIT 1', [$phone])
             ?: DB::row("SELECT * FROM users WHERE $idField = ? ORDER BY id LIMIT 1", [$extId]);

        if ($user) {
            $upd = [$idField => $extId, 'phone' => $phone];
            // Імʼя з месенджера не затирає вже введене: людина могла назватись
            // у профілі як їй треба, а в Telegram у неї нік із емодзі.
            if (trim((string)$user['name']) === '' && $name !== '') $upd['name'] = $name;
            if (!$user['active']) return 0;               // вимкнений акаунт входу не дає
            DB::update('users', $upd, 'id = ?', [(int)$user['id']]);
            return (int)$user['id'];
        }

        $suffix = $idField === 'tg_chat_id' ? '@telegram.local' : '@viber.local';
        $uid = DB::insert('users', [
            'email' => substr(md5($extId), 0, 12) . $suffix,
            'name' => $name !== '' ? $name : 'Покупець',
            'role' => 'customer', 'active' => 1,
            $idField => $extId, 'phone' => $phone, 'created_at' => now(),
        ]);
        Notify::fire('user_new', ['name' => $name, 'email' => $idField === 'tg_chat_id' ? 'Telegram' : 'Viber']);
        return $uid;
    }

    /** Незавершений вхід цього чату: /start уже був, номера ще немає */
    public static function pendingLogin(string $purpose, string $chatId): ?array
    {
        return DB::row(
            'SELECT * FROM auth_tokens WHERE purpose = ? AND chat_id = ? AND used = 0 AND expires_at > ?
             ORDER BY id DESC LIMIT 1', [$purpose, $chatId, now()]);
    }
}
