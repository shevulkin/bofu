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
}

return (new NotifyTest())->run();
