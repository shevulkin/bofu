<?php
/**
 * Особисті налаштування сповіщень.  Запуск: php bin/cli.php test
 *
 * Головне, що доводимо: вибір користувача — це фільтр ПІД налаштуванням адміна.
 * Він може лише прибрати зайве й ніколи не здатен увімкнути канал чи подію,
 * які адміністратор закрив.
 *
 * Тест підміняє глобальні перемикачі й правила, а наприкінці повертає їх
 * точно як було, тож на робочі налаштування не впливає.
 */
declare(strict_types=1);

final class NotifyTest
{
    private const KEYS = ['notify_all_enabled', 'notify_telegram_enabled',
        'notify_email_enabled', 'notify_push_enabled', 'notify_viber_enabled'];

    private int $pass = 0;
    private int $fail = 0;
    private array $settings = [];
    private array $rules = [];
    private int $admin = 0;
    private int $customer = 0;

    public function run(): int
    {
        $this->setUp();
        try {
            $this->testDefaults();
            $this->testUserCanTurnOff();
            $this->testUserCannotTurnOnWhatAdminClosed();
            $this->testAdminSwitchesWin();
            $this->testGroups();
            $this->testOrderMessage();
        } finally {
            $this->tearDown();
        }
        echo "\n" . ($this->fail === 0
            ? "УСЕ ДОБРЕ: {$this->pass} перевірок\n"
            : "ПРОВАЛЕНО: {$this->fail} з " . ($this->pass + $this->fail) . "\n");
        return $this->fail === 0 ? 0 : 1;
    }

    private function setUp(): void
    {
        foreach (self::KEYS as $k) $this->settings[$k] = Settings::get($k, null);
        $this->rules = DB::all('SELECT id, enabled FROM notification_rules');
        foreach (self::KEYS as $k) Settings::set($k, '1');

        // лишаємо ввімкненим рівно одне правило: order_new через telegram
        DB::query('UPDATE notification_rules SET enabled = 0');
        $row = DB::row("SELECT id FROM notification_rules WHERE event = 'order_new' AND channel = 'telegram'");
        if ($row) DB::update('notification_rules', ['enabled' => 1, 'recipients' => 'admins_sellers'], 'id = ?', [$row['id']]);

        $this->admin = DB::insert('users', ['email' => 'notify-admin@bofu.test', 'name' => 'Тест адмін',
            'active' => 1, 'tg_chat_id' => '123', 'created_at' => now()]);
        DB::insert('user_roles', ['user_id' => $this->admin, 'role' => Roles::ADMIN, 'created_at' => now()]);
        $this->customer = DB::insert('users', ['email' => 'notify-buyer@bofu.test', 'name' => 'Тест покупець',
            'active' => 1, 'created_at' => now()]);
        Auth::forgetRoles();
        Notify::forgetPrefs();
    }

    private function tearDown(): void
    {
        foreach ($this->settings as $k => $v) {
            if ($v === null) DB::delete('settings', '`key` = ?', [$k]);
            else Settings::set($k, $v);
        }
        foreach ($this->rules as $r) {
            DB::update('notification_rules', ['enabled' => (int)$r['enabled']], 'id = ?', [$r['id']]);
        }
        foreach ([$this->admin, $this->customer] as $id) {
            DB::delete('user_notify_prefs', 'user_id = ?', [$id]);
            DB::delete('user_roles', 'user_id = ?', [$id]);
            DB::delete('users', 'id = ?', [$id]);
        }
        Auth::forgetRoles();
        Notify::forgetPrefs();
    }

    private function ok(string $what, bool $cond): void
    {
        if ($cond) { $this->pass++; echo "  ok   $what\n"; }
        else { $this->fail++; echo "  FAIL $what\n"; }
    }

    private function group(string $name): void { echo "\n== $name ==\n"; }

    private function user(int $id): array { return DB::row('SELECT * FROM users WHERE id = ?', [$id]); }

    // ── Перевірки ───────────────────────────────────────────────────────────

    private function testDefaults(): void
    {
        $this->group('за замовчуванням людина отримує все дозволене');
        $this->ok('порожні налаштування = згода', Notify::wants($this->admin, 'order_new', 'telegram'));
        $opts = Notify::optionsFor($this->user($this->admin));
        $this->ok('пропонується рівно ввімкнене адміном правило',
            array_keys($opts) === ['order_new'] && array_keys($opts['order_new']) === ['telegram']);
        $this->ok('канал позначено готовим — chat_id заданий', $opts['order_new']['telegram']['ready']);
    }

    private function testUserCanTurnOff(): void
    {
        $this->group('людина може вимкнути те, що їй не потрібне');
        Notify::savePrefs($this->user($this->admin), []);   // жодної галки
        Notify::forgetPrefs();
        $this->ok('вимкнене більше не хоче', !Notify::wants($this->admin, 'order_new', 'telegram'));
        $this->ok('у БД рядок саме про цю пару',
            (int)DB::val("SELECT COUNT(*) FROM user_notify_prefs WHERE user_id = ? AND event = 'order_new' AND channel = 'telegram'", [$this->admin]) === 1);

        Notify::savePrefs($this->user($this->admin), ['order_new' => ['telegram' => '1']]);
        Notify::forgetPrefs();
        $this->ok('повернути назад можна', Notify::wants($this->admin, 'order_new', 'telegram'));
        $this->ok('зайвих рядків не лишається',
            (int)DB::val('SELECT COUNT(*) FROM user_notify_prefs WHERE user_id = ?', [$this->admin]) === 0);
    }

    private function testUserCannotTurnOnWhatAdminClosed(): void
    {
        $this->group('вибір не може вийти за межі дозволеного адміном');
        // підроблена форма: просимо подію й канал, які адмін вимкнув
        Notify::savePrefs($this->user($this->admin), [
            'order_new' => ['telegram' => '1', 'email' => '1'],
            'stock_low' => ['telegram' => '1'],
        ]);
        Notify::forgetPrefs();
        $this->ok('закрита адміном подія не потрапила в налаштування',
            (int)DB::val("SELECT COUNT(*) FROM user_notify_prefs WHERE event = 'stock_low'") === 0);
        $opts = Notify::optionsFor($this->user($this->admin));
        $this->ok('і не зʼявляється у виборі', !isset($opts['stock_low']));
        $this->ok('канал, вимкнений у правилі, теж не зʼявляється', !isset($opts['order_new']['email']));
    }

    private function testAdminSwitchesWin(): void
    {
        $this->group('перемикачі адміна головніші за вибір людини');
        Settings::set('notify_telegram_enabled', '0');
        $opts = Notify::optionsFor($this->user($this->admin));
        $this->ok('вимкнений канал зникає з вибору', !isset($opts['order_new']['telegram']));
        Settings::set('notify_telegram_enabled', '1');

        Settings::set('notify_all_enabled', '0');
        $this->ok('головний перемикач прибирає все', Notify::optionsFor($this->user($this->admin)) === []);
        Settings::set('notify_all_enabled', '1');
    }

    private function testGroups(): void
    {
        $this->group('групи отримувачів');
        $this->ok('адмін входить у admins_sellers', Notify::inGroup($this->admin, 'admins_sellers'));
        $this->ok('покупець не входить', !Notify::inGroup($this->customer, 'admins_sellers'));
        $this->ok('покупцю нема чого налаштовувати', Notify::optionsFor($this->user($this->customer)) === []);
    }

    /**
     * Повідомлення про замовлення. Адреса доставки — те, заради чого продавець
     * і відкриває сповіщення; а самовивіз адреси не має, і порожнього рядка
     * замість неї бути не повинно.
     */
    private function testOrderMessage(): void
    {
        $this->group('повідомлення про замовлення');
        $np = ['delivery' => 'np', 'city' => 'Київ', 'np_office' => 'Відділення №5', 'address' => null];
        $other = ['delivery' => 'other', 'city' => 'Ніжин', 'np_office' => null, 'address' => 'вул. Шевченка, 1'];
        $pickup = ['delivery' => 'pickup', 'city' => 'Київ', 'np_office' => 'Відділення №5', 'address' => null];

        $this->ok('Нова Пошта: місто й відділення',
            OrderFlow::deliveryAddress($np) === 'Київ, Відділення №5');
        $this->ok('інша доставка: місто й вулиця',
            OrderFlow::deliveryAddress($other) === 'Ніжин, вул. Шевченка, 1');
        $this->ok('самовивіз адреси не має', OrderFlow::deliveryAddress($pickup) === '');

        $vars = ['number' => 'BOFU-1', 'name' => 'Марія', 'phone' => '+380500000000',
                 'delivery' => 'Нова Пошта', 'address' => OrderFlow::deliveryAddress($np),
                 'items' => "• Мед липовий, 0.5 л × 2\n• Прополіс × 1",
                 'total' => '600.00', 'store' => 'Магазин №1'];
        $text = Notify::interpolate(Notify::DEFAULT_TEMPLATES['order_new'], $vars);
        $this->ok('адреса в повідомленні є', str_contains($text, 'Київ, Відділення №5'));
        $this->ok('видно, що замовили', str_contains($text, '• Мед липовий, 0.5 л × 2'));
        $this->ok('телефон окремим рядком', str_contains($text, "\n+380500000000"));
        $this->ok('телефон стоїть перед імʼям',
            strpos($text, '+380500000000') < strpos($text, 'Клієнт: Марія'));
        $this->ok('порожніх рядків немає', !str_contains($text, "\n\n"));

        $text = Notify::interpolate(Notify::DEFAULT_TEMPLATES['order_new'],
            ['address' => '', 'items' => ''] + $vars);
        $this->ok('без адреси й позицій рядки зникають, а не порожніють',
            !str_contains($text, "\n\n"));
        $this->ok('решта полів на місці', str_contains($text, 'Доставка: Нова Пошта')
            && str_contains($text, 'Клієнт: Марія'));

        $this->testItemsSummary();
    }

    /** Список позицій: варіант у назві, кількість завжди, довге замовлення обрізане */
    private function testItemsSummary(): void
    {
        $orderId = DB::insert('orders', [
            'number' => 'BOFU-TEST-' . bin2hex(random_bytes(3)), 'name' => 'Тест', 'phone' => '+380500000001',
            'delivery' => 'np', 'total' => 0, 'created_at' => now(),
        ]);
        try {
            DB::insert('order_items', ['order_id' => $orderId, 'title' => 'Мед липовий',
                'variant_name' => '0.5 л', 'price' => 300, 'qty' => 2, 'sum' => 600]);
            DB::insert('order_items', ['order_id' => $orderId, 'title' => 'Прополіс',
                'variant_name' => null, 'price' => 120, 'qty' => 1, 'sum' => 120]);
            $this->ok('варіант у назві, кількість у кожного рядка',
                OrderFlow::itemsSummary($orderId) === "• Мед липовий, 0.5 л × 2\n• Прополіс × 1");

            for ($i = 0; $i < 3; $i++) {
                DB::insert('order_items', ['order_id' => $orderId, 'title' => 'Свічка ' . $i,
                    'variant_name' => null, 'price' => 50, 'qty' => 1, 'sum' => 50]);
            }
            $short = OrderFlow::itemsSummary($orderId, 2);
            $this->ok('довге замовлення обрізане', substr_count($short, "\n") === 2);
            $this->ok('і сказано, скільки лишилось', str_contains($short, '…та ще 3 поз.'));
            $this->ok('порожнє замовлення дає порожній рядок', OrderFlow::itemsSummary(0) === '');
        } finally {
            DB::delete('order_items', 'order_id = ?', [$orderId]);
            DB::delete('orders', 'id = ?', [$orderId]);
        }
    }
}

return (new NotifyTest())->run();
