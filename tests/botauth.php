<?php
/**
 * Вхід через месенджер: як номер телефону перетворюється на акаунт.
 * Запуск: php bin/cli.php test
 *
 * Найважливіше тут — злиття за номером. Покупець міг замовляти без реєстрації,
 * потім увійти через Telegram; якщо шукати спершу за chat_id, він отримає другий
 * акаунт, а замовлення лишаться в першому. Тому порядок пошуку «спершу телефон»
 * — це не деталь реалізації, а вимога, і вона тут зафіксована.
 *
 * Тест створює власні записи й прибирає їх за собою.
 */
declare(strict_types=1);

final class BotAuthTest
{
    private int $pass = 0;
    private int $fail = 0;
    private array $made = [];
    private array $savedSettings = [];

    public function run(): int
    {
        try {
            $this->testMergeByPhone();
            $this->testFindByMessengerId();
            $this->testCreatesNew();
            $this->testInactiveGetsNothing();
            $this->testTexts();
        } finally {
            $this->tearDown();
        }
        echo "\n" . ($this->fail === 0
            ? "УСЕ ДОБРЕ: {$this->pass} перевірок\n"
            : "ПРОВАЛЕНО: {$this->fail} з " . ($this->pass + $this->fail) . "\n");
        return $this->fail === 0 ? 0 : 1;
    }

    private function ok(string $what, bool $cond): void
    {
        if ($cond) { $this->pass++; echo "  ok   $what\n"; }
        else { $this->fail++; echo "  FAIL $what\n"; }
    }

    private function group(string $name): void { echo "\n== $name ==\n"; }

    private function mkUser(array $data): int
    {
        $id = DB::insert('users', array_merge([
            'email' => 'botauth-' . bin2hex(random_bytes(4)) . '@bofu.test',
            'name' => 'Тест', 'active' => 1, 'created_at' => now(),
        ], $data));
        $this->made[] = $id;
        return $id;
    }

    private function tearDown(): void
    {
        foreach ($this->made as $id) DB::delete('users', 'id = ?', [$id]);
        foreach ($this->savedSettings as $k => $v) Settings::set($k, (string)$v);
    }

    /** Замовляв за номером, потім прийшов у бота — це та сама людина */
    private function testMergeByPhone(): void
    {
        $this->group('номер склеює акаунти');
        $phone = '+380670000911';
        $old = $this->mkUser(['phone' => $phone, 'name' => 'Ганна Коваль']);

        $got = BotAuth::resolveUser('tg_chat_id', 'tg-911', $phone, 'Ганна з Telegram');
        $this->ok('увійшли в наявний акаунт, а не створили другий', $got === $old);

        $row = DB::row('SELECT * FROM users WHERE id = ?', [$old]);
        $this->ok('chat_id привʼязався до нього', (string)$row['tg_chat_id'] === 'tg-911');
        $this->ok('імʼя з профілю не затерте ніком з месенджера', $row['name'] === 'Ганна Коваль');
        $this->ok('другого акаунта не зʼявилось',
            (int)DB::val('SELECT COUNT(*) FROM users WHERE phone = ?', [$phone]) === 1);
    }

    /** Номер змінився (людина ввела інший), але месенджер той самий */
    private function testFindByMessengerId(): void
    {
        $this->group('пошук за id месенджера');
        $u = $this->mkUser(['viber_id' => 'vb-77', 'phone' => null]);
        $got = BotAuth::resolveUser('viber_id', 'vb-77', '+380670000922', 'Хтось');
        $this->ok('знайшли за viber_id', $got === $u);
        $this->ok('номер записався в акаунт',
            DB::val('SELECT phone FROM users WHERE id = ?', [$u]) === '+380670000922');
    }

    private function testCreatesNew(): void
    {
        $this->group('нової людини ще немає');
        $before = (int)DB::val('SELECT COUNT(*) FROM users');
        $id = BotAuth::resolveUser('tg_chat_id', 'tg-new-1', '+380670000933', 'Новий Покупець');
        $this->made[] = $id;
        $row = DB::row('SELECT * FROM users WHERE id = ?', [$id]);
        $this->ok('акаунт створено', $id > 0 && (int)DB::val('SELECT COUNT(*) FROM users') === $before + 1);
        $this->ok('одразу з номером — без нього гейт не пустив би далі профілю',
            $row['phone'] === '+380670000933');
        $this->ok('імʼя взяте з месенджера', $row['name'] === 'Новий Покупець');
        $this->ok('роль — покупець', $row['role'] === 'customer');
    }

    /** Вимкнений акаунт не має входити навіть із правильним номером */
    private function testInactiveGetsNothing(): void
    {
        $this->group('вимкнений акаунт');
        $phone = '+380670000944';
        $this->mkUser(['phone' => $phone, 'active' => 0]);
        $this->ok('входу не дано', BotAuth::resolveUser('tg_chat_id', 'tg-944', $phone, 'Хтось') === 0);
    }

    private function testTexts(): void
    {
        $this->group('тексти бота');
        $key = 'bot_done';
        $this->savedSettings[$key] = (string)Settings::get($key, '');

        Settings::set($key, '');
        $this->ok('порожнє налаштування = типовий текст',
            BotAuth::text($key) === str_replace(
                ['{name}', '{site_name}'], ['', (string)cfg('app_name')], BotAuth::TEXTS[$key][0]));

        Settings::set($key, 'Вітаю, {name}! Ваш номер {phone}. Сайт: {site_name}');
        $this->ok('підстановки працюють',
            BotAuth::text($key, ['name' => 'Оля', 'phone' => '+380670000955'])
            === 'Вітаю, Оля! Ваш номер +380670000955. Сайт: ' . cfg('app_name'));

        Settings::set($key, 'Текст без підстановок');
        $this->ok('текст без плейсхолдерів лишається собою',
            BotAuth::text($key, ['name' => 'Оля']) === 'Текст без підстановок');
    }
}

return (new BotAuthTest())->run();
