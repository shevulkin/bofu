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
        'order_customer' => 'Покупцю: рух його замовлення',
        'order_shipment' => 'Покупцю: накладна й рух посилки',
        'user_new'       => 'Новий користувач',
        'stock_low'      => 'Закінчується товар',
        'stock_wanted'   => 'Просять повідомити про наявність',
        'stock_back'     => 'Товар знову в наявності',
    ];

    /**
     * Типове правило події: [кому, чи вмикати одразу]. Одне джерело і для
     * першої установки (Seeder), і для міграцій — інакше нова подія працює
     * лише на оновлених базах або лише на нових, і різницю помічають нескоро.
     *
     * 'customer' — не група, а адресат: подія стосується однієї конкретної
     * людини й іде через Notify::toCustomer()/toUser(). Такі події адмін не
     * може перевести на себе — див. isCustomerEvent().
     */
    public const DEFAULT_RULES = [
        'order_new'      => ['admins_sellers', true],
        'order_status'   => ['admins_sellers', false],
        'order_customer' => ['customer', true],
        'order_shipment' => ['customer', true],
        'user_new'       => ['admins_sellers', false],
        'stock_low'      => ['admins_sellers', false],
        'stock_wanted'   => ['sellers', true],
        'stock_back'     => ['customer', true],
    ];

    /** Подія адресована покупцю, а не персоналу: одержувача міняти нема сенсу */
    public static function isCustomerEvent(string $event): bool
    {
        return (self::DEFAULT_RULES[$event][0] ?? '') === 'customer';
    }

    public const DEFAULT_TEMPLATES = [
        // Порядок рядків — це порядок питань, які ставить собі продавець: чи моє
        // це замовлення (магазин), що саме замовили, на скільки, як і куди
        // везти, кому дзвонити. Телефон окремим рядком, з його початку і перед
        // імʼям: у месенджері він стає посиланням «подзвонити», у яке треба
        // влучити пальцем, а внизу повідомлення до нього найлегше дотягнутись.
        // Приліплений комою до імені, він то ховався в кінці рядка, то
        // переносився.
        // {shortage} стоїть одразу під складом замовлення й порожній, коли все є:
        // рядок зникне сам (interpolate). Це єдиний блок, що вимагає дії, тож
        // він має трапитись на очі раніше за суму й адресу.
        'order_new'    => "🛒 Нове замовлення {number}\nМагазин: {store}\n{items}\n{shortage}\nСума: {total} грн\n"
                        . "Доставка: {delivery}\n{address}\n{phone}\nКлієнт: {name}",
        'order_status' => "📦 Замовлення {number}: статус змінено на «{status}»",
        // Покупцю. Він не знає слова «підзамовлення» й не має його вчити: коли
        // рухається лише частина, {part} називає магазин і перелічує саме її
        // товари, а коли ціле замовлення — обидва рядки порожні й зникають.
        'order_customer' => "📦 Замовлення {number} — {status}\n{part}\n{items}\nСума: {total} грн",
        // Посилка. Номер накладної тут головне — саме його переписують у трекінг
        // і називають у відділенні, тож він стоїть окремим рядком і першим після
        // заголовка. {estimated}, {cod} і {part} порожніють самі, коли нема чого
        // сказати: рядок із самих підстановок зникає (interpolate).
        'order_shipment' => "🚚 Замовлення {number}\n{part}\nНакладна: {ttn}\n{status}\n{estimated}\n{cod}\n{url}",
        'user_new'     => "👤 Новий користувач: {name} ({email})",
        'stock_low'    => "⚠️ Товар «{product}» закінчується: залишилось {qty} шт. ({store})",
        // Це не побажання клієнта, а сигнал попиту: {waiting} відповідає на
        // питання «чи варто заради цього ставати за станок»
        'stock_wanted' => "🔔 Просять повідомити про наявність: «{product}»\n"
                        . "Уже чекають: {waiting}\nЗапит із точки: {store}",
        // {url} приходить уже з підписом або порожній: на локальній машині
        // абсолютної адреси немає, і рядок має зникнути, а не світити «Замовити:»
        'stock_back'   => "✅ «{product}» знову в наявності!\n{where}\n{url}",
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
                // особистий вибір людини може лише прибрати зайве з того, що дозволив адмін
                if (!self::wants((int)$user['id'], $event, (string)$rule['channel'])) continue;
                try { self::send($rule['channel'], $user, $text, $vars); }
                catch (Throwable $e) { self::log("send fail {$rule['channel']} u{$user['id']}: " . $e->getMessage()); }
            }
        }
    }

    /**
     * Те саме, але одній конкретній людині: подія стосується її особисто
     * («ваш товар зʼявився»), тож розкладка по групах отримувачів тут ні до чого.
     * Шаблони, глобальні перемикачі й особисті галки в кабінеті — усе як завжди.
     */
    public static function toUser(int $userId, string $event, array $vars): void
    {
        if (!Settings::bool('notify_all_enabled', true)) return;
        $user = DB::row('SELECT * FROM users WHERE id = ? AND active = 1', [$userId]);
        if (!$user) return;
        foreach (DB::all('SELECT * FROM notification_rules WHERE event = ? AND enabled = 1', [$event]) as $rule) {
            $channel = (string)$rule['channel'];
            if (!self::channelEnabled($channel)) continue;
            if (!self::wants($userId, $event, $channel)) continue;
            $tpl = $rule['template'] ?: (self::DEFAULT_TEMPLATES[$event] ?? '');
            try { self::send($channel, $user, self::interpolate($tpl, $vars), $vars); }
            catch (Throwable $e) { self::log("send fail $channel u$userId: " . $e->getMessage()); }
        }
    }

    /**
     * Покупцю про його замовлення.
     *
     * Зареєстрованому — усіма каналами, які він лишив собі в кабінеті. Гостю —
     * лише на пошту, вказану в замовленні: акаунта, а отже й вибору каналів,
     * у нього немає, і нічого, крім цієї адреси, ми про нього не знаємо.
     * Пошти теж немає — замовлення лишається тільки на телефоні, і це
     * нормальний шлях: подзвонять.
     */
    public static function toCustomer(?int $userId, ?string $email, string $event, array $vars): void
    {
        if ($userId) { self::toUser($userId, $event, $vars); return; }
        if (!$email || !Settings::bool('notify_all_enabled', true)) return;
        if (!self::channelEnabled('email')) return;
        foreach (DB::all('SELECT * FROM notification_rules WHERE event = ? AND channel = ? AND enabled = 1',
                 [$event, 'email']) as $rule) {
            $tpl = $rule['template'] ?: (self::DEFAULT_TEMPLATES[$event] ?? '');
            try { self::email(['email' => $email], self::interpolate($tpl, $vars), $vars); }
            catch (Throwable $e) { self::log("send fail email guest ($event): " . $e->getMessage()); }
        }
    }

    // ── Особисті налаштування ───────────────────────────────────────────────

    public const CHANNELS = [
        'telegram' => 'Telegram',
        'viber'    => 'Viber',
        'email'    => 'Email',
        'push'     => 'Сповіщення в браузері',
    ];

    /** Чи глобально увімкнений канал (перемикач адміна) */
    public static function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            'telegram' => Settings::bool('notify_telegram_enabled', true),
            'viber'    => Settings::bool('notify_viber_enabled', true),
            'email'    => Settings::bool('notify_email_enabled', true),
            'push'     => Settings::bool('notify_push_enabled', true),
            default    => false,
        };
    }

    /** Особисті налаштування користувача: ['подія|канал' => bool] */
    private static array $prefsCache = [];

    public static function prefs(int $userId): array
    {
        if (isset(self::$prefsCache[$userId])) return self::$prefsCache[$userId];
        $out = [];
        foreach (DB::all('SELECT event, channel, enabled FROM user_notify_prefs WHERE user_id = ?', [$userId]) as $r) {
            $out[$r['event'] . '|' . $r['channel']] = (bool)(int)$r['enabled'];
        }
        return self::$prefsCache[$userId] = $out;
    }

    public static function forgetPrefs(?int $userId = null): void
    {
        if ($userId === null) self::$prefsCache = [];
        else unset(self::$prefsCache[$userId]);
    }

    /**
     * Подія в рядку налаштувань, коли вибір стосується каналу цілком.
     * Людина обирає СПОСІБ отримання, а не перелік подій: які саме події
     * розсилати — рішення адміністратора, і дублювати його в кабінеті
     * означало б давати покупцю налаштування, яких він не просив.
     */
    public const ANY_EVENT = '*';

    /**
     * Чи хоче людина отримувати сповіщення цим каналом. Відсутність рядка =
     * хоче: інакше ввімкнення нового каналу адміном мовчки нікому б не дійшло.
     */
    public static function wantsChannel(int $userId, string $channel): bool
    {
        return self::prefs($userId)[self::ANY_EVENT . '|' . $channel] ?? true;
    }

    /**
     * Чи хоче людина цю подію цим каналом. Подія лишається в сигнатурі, бо
     * саме парами (подія, канал) розсилка й ходить, — але вирішує тепер лише
     * канал. Старі рядки з конкретними подіями не читаються: saveChannels()
     * прибирає їх при першому ж збереженні.
     */
    public static function wants(int $userId, string $event, string $channel): bool
    {
        return self::wantsChannel($userId, $channel);
    }

    /** Чи входить користувач у групу отримувачів правила */
    public static function inGroup(int $userId, string $mode): bool
    {
        // 'customer' — не група, а сам адресат: подія стосується конкретної
        // людини (їй чекався товар), і розсилати її «всім покупцям» безглуздо.
        // Тут відповідаємо true, щоб подія показалась у кабінеті кожному, хто
        // може її отримати; кому саме слати, вирішує toUser().
        if ($mode === 'customer') return true;
        $roles = Auth::roles($userId);
        $isAdmin = in_array(Roles::ADMIN, $roles, true);
        $isSeller = in_array(Roles::SELLER, $roles, true);
        return match ($mode) {
            'admins'         => $isAdmin,
            'sellers'        => $isSeller,
            'admins_sellers' => $isAdmin || $isSeller,
            default          => false,
        };
    }

    /**
     * Що саме ця людина може отримувати — для сторінки профілю.
     * Показуємо лише реально можливі пари: подія увімкнена адміном, канал
     * увімкнений глобально і людина входить у групу отримувачів. Немає сенсу
     * пропонувати вимкнути те, що й так ніколи не прийде.
     * Повертає [подія => [канал => ['on' => bool, 'ready' => bool, 'hint' => string]]]
     */
    public static function optionsFor(array $user): array
    {
        if (!Settings::bool('notify_all_enabled', true)) return [];
        $uid = (int)$user['id'];
        $out = [];
        foreach (DB::all('SELECT * FROM notification_rules WHERE enabled = 1') as $rule) {
            $event = (string)$rule['event'];
            $channel = (string)$rule['channel'];
            if (!isset(self::EVENTS[$event], self::CHANNELS[$channel])) continue;
            if (!self::channelEnabled($channel)) continue;
            if (!self::canSubscribe($uid, $channel)) continue;
            if (!self::inGroup($uid, (string)$rule['recipients'])) continue;
            [$ready, $hint] = self::readiness($user, $channel);
            $out[$event][$channel] = [
                'on' => self::wants($uid, $event, $channel),
                'ready' => $ready,
                'hint' => $hint,
            ];
        }
        return $out;
    }

    /**
     * Чи може ця людина взагалі підключити канал.
     *
     * Push — лише персоналу, і це не забаганка: підписатися можна тільки
     * кнопкою в адмінпанелі, а Api::pushSubscribe() відповідає стороннім 403.
     * Пропонувати галку тому, хто фізично не має де підписатись, гірше за
     * відсутність каналу: людина ставить її й чекає сповіщень, яких не буде.
     * Покупцю ті самі події приходять у Telegram і на пошту.
     *
     * Якщо колись зʼявиться підписка з кабінету — знімати обмеження треба
     * тут і в Api::pushSubscribe() разом, інакше знову розʼїдеться.
     */
    private static function canSubscribe(int $userId, string $channel): bool
    {
        // роль беремо ту, що людина МАЄ, а не обрану робочу — з тієї ж причини,
        // що й у inGroup(): адмін, який дивиться очима покупця, не перестає
        // отримувати сповіщення й не має втрачати свої налаштування
        return $channel !== 'push' || self::inGroup($userId, 'admins_sellers');
    }

    /** Чи налаштований канал у конкретної людини */
    private static function readiness(array $user, string $channel): array
    {
        return match ($channel) {
            'telegram' => [!empty($user['tg_chat_id']), 'Підключіть Telegram нижче'],
            'viber'    => [!empty($user['viber_id']), 'Підключіть Viber нижче'],
            'email'    => [!empty($user['email']), 'В акаунті немає пошти'],
            'push'     => [
                (bool)DB::val('SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1', [(int)$user['id']]),
                'Натисніть «Увімкнути пуші» в адмінпанелі — з того браузера, де хочете їх бачити',
            ],
            default    => [false, ''],
        };
    }

    /**
     * Способи отримання, доступні цій людині, — те, що показуємо в кабінеті.
     * Канал потрапляє сюди, якщо ним справді може щось прийти: увімкнений
     * глобально і хоч одне ввімкнене правило адресоване цій людині.
     *
     * Які саме події прийдуть — не її вибір, тож перелічуємо їх лише як
     * пояснення («Нове замовлення, Товар знову в наявності»): інакше з двох
     * галок «Email» і «Telegram» не видно, про що взагалі йдеться.
     *
     * @return array<string,array{on:bool,ready:bool,hint:string,events:string[]}>
     */
    public static function channelsFor(array $user): array
    {
        $uid = (int)$user['id'];
        $out = [];
        foreach (self::optionsFor($user) as $event => $channels) {
            foreach (array_keys($channels) as $channel) {
                if (!isset($out[$channel])) {
                    [$ready, $hint] = self::readiness($user, $channel);
                    $out[$channel] = [
                        'on' => self::wantsChannel($uid, $channel),
                        'ready' => $ready, 'hint' => $hint, 'events' => [],
                    ];
                }
                $out[$channel]['events'][] = self::EVENTS[$event] ?? $event;
            }
        }
        // порядок як у CHANNELS — щоб не стрибав від того, які правила ввімкнені
        return array_replace(array_intersect_key(self::CHANNELS, $out), $out);
    }

    /**
     * Зберігає вибір способів. Приймаємо лише канали, які людині справді
     * доступні, — інакше формою можна було б записати собі згоду на те, що
     * адмін вимкнув. Рядки старого формату (вибір по кожній події) заразом
     * прибираються: інакше подія, вимкнена колись, глушила б увімкнений канал.
     */
    public static function saveChannels(array $user, array $checked): void
    {
        $uid = (int)$user['id'];
        $allowed = self::channelsFor($user);   // рахуємо ДО видалення, поки кеш ще чинний
        DB::delete('user_notify_prefs', 'user_id = ?', [$uid]);
        self::forgetPrefs($uid);
        foreach (array_keys($allowed) as $channel) {
            if (empty($checked[$channel])) {
                DB::insert('user_notify_prefs', [
                    'user_id' => $uid, 'event' => self::ANY_EVENT, 'channel' => $channel, 'enabled' => 0,
                ]);
            }
        }
    }

    public static function interpolate(string $tpl, array $vars): string
    {
        $out = [];
        foreach (explode("\n", $tpl) as $line) {
            $filled = $line;
            foreach ($vars as $k => $v) $filled = str_replace('{' . $k . '}', (string)$v, $filled);
            // Рядок, що складався з самих підстановок і спорожнів, викидаємо:
            // інакше в самовивозі замість адреси лишався б порожній рядок, а в
            // замовленні без телефону — діра посеред повідомлення. Рядок із
            // власним підписом («Доставка: ») лишається — там видно, чого бракує.
            if (trim($filled) === '' && trim($line) !== '') continue;
            $out[] = $filled;
        }
        return implode("\n", $out);
    }

    /** Одержувачі: адміни та/або продавці магазину */
    private static function recipients(string $mode, ?int $storeId): array
    {
        // Ролі беремо з user_roles — тобто ті, що людина МАЄ. Обрана робоча роль тут
        // ні до чого: адмін, який зараз дивиться очима покупця, має й далі
        // отримувати сповіщення про замовлення.
        //
        // Режиму 'customer' тут навмисно немає, і додавати його НЕ треба: такі
        // події адресні («ваш товар зʼявився»), їх шле Notify::toUser() одній
        // людині. Гілка «всі покупці» перетворила б це на масову розсилку по
        // всій базі. inGroup() відповідає для 'customer' true лише для того,
        // щоб подія зʼявилась у кабінеті — на добір отримувачів це не впливає.
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
