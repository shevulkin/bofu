<?php
/**
 * Вхід поштою за одноразовим кодом і те, чим доведено номер телефону.
 * Запуск: php bin/cli.php test
 *
 * Тут перевіряється не «чи прийшов лист» — пошту ми не контролюємо, — а два
 * правила, ціна помилки в яких висока:
 *
 *   1) Кодом входить лише той, у кого справді є ця скринька. Тому: чужий код
 *      не пускає, використаний код не спрацьовує двічі, а технічні адреси
 *      акаунтів (@offline.local, @telegram.local) не приймаються взагалі —
 *      інакше запис, який продавець завів на покупця, ставав би доступним
 *      кожному, хто вгадає його вигадану адресу.
 *
 *   2) Вписаний руками номер НЕ робить акаунт власником номера. Саме на цьому
 *      трималась дірка, через яку прибрали вхід через Viber: замовлення, які
 *      продавець оформлює по телефону, знаходять покупця за номером, і
 *      «вписав чужий номер» означало б «бачу чужі покупки».
 *
 * Набір створює власні записи й прибирає їх за собою.
 */
declare(strict_types=1);

final class EmailAuthTest
{
    private int $pass = 0;
    private int $fail = 0;
    private array $users = [];
    private array $orders = [];
    private array $emails = [];

    public function run(): int
    {
        try {
            $this->testNormEmail();
            $this->testSendCode();
            $this->testRateLimitPerAddress();
            $this->testWrongCode();
            $this->testCreatesAccount();
            $this->testSecondLoginSameAccount();
            $this->testCodeIsSingleUse();
            $this->testInactiveAccount();
            $this->testOwnsPhone();
            $this->testResolveRefusesUnprovenPhone();
            $this->testClaimOrders();
            $this->testMaskEmail();
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

    /** Своя адреса на кожен виклик: інакше стеля «три коди на годину» з'їла б набір */
    private function mail(): string
    {
        $e = 'mailauth-' . bin2hex(random_bytes(5)) . '@bofu.test';
        $this->emails[] = $e;
        return $e;
    }

    private function mkUser(array $data): int
    {
        $id = DB::insert('users', array_merge([
            'email' => $this->mail(), 'name' => 'Тест', 'active' => 1, 'created_at' => now(),
        ], $data));
        $this->users[] = $id;
        return $id;
    }

    private function mkOrder(?int $userId, string $phone): int
    {
        $id = DB::insert('orders', [
            'number' => 'TEST-' . bin2hex(random_bytes(4)), 'token' => bin2hex(random_bytes(8)),
            'user_id' => $userId, 'name' => 'Тест', 'phone' => $phone,
            'delivery' => 'pickup', 'status' => 'new', 'total' => 0, 'created_at' => now(),
        ]);
        $this->orders[] = $id;
        return $id;
    }

    /** Код із бази: у тесті ми на місці листоноші, який має право зазирнути в лист */
    private function codeOf(string $token): string
    {
        return (string)DB::val("SELECT code FROM auth_tokens WHERE token = ?", [$token]);
    }

    private function tearDown(): void
    {
        foreach ($this->orders as $id) DB::delete('orders', 'id = ?', [$id]);
        foreach ($this->users as $id) { DB::delete('user_roles', 'user_id = ?', [$id]); DB::delete('users', 'id = ?', [$id]); }
        foreach ($this->emails as $e) DB::delete('auth_tokens', 'email = ?', [$e]);
    }

    private function testNormEmail(): void
    {
        $this->group('яка адреса взагалі годиться');
        $this->ok('звичайна пошта приймається', EmailAuth::normEmail(' Ivan@Ukr.NET ') === 'ivan@ukr.net');
        $this->ok('сміття не приймається', EmailAuth::normEmail('ivan(at)ukr.net') === null);
        // Технічні адреси — не скриньки. Якби вони проходили, вхід у чужий
        // акаунт зводився б до вгадування рядка з md5 замість володіння поштою.
        $this->ok('@offline.local (покупець від продавця) не приймається',
            EmailAuth::normEmail('a1b2c3d4e5f6@offline.local') === null);
        $this->ok('@telegram.local не приймається', EmailAuth::normEmail('a1b2c3d4e5f6@telegram.local') === null);
    }

    private function testSendCode(): void
    {
        $this->group('код створюється й прив\'язується до адреси');
        $email = $this->mail();
        $res = EmailAuth::sendCode($email);
        $this->ok('код надіслано', !empty($res['ok']) && !empty($res['token']));
        $row = DB::row("SELECT * FROM auth_tokens WHERE token = ?", [$res['token']]);
        $this->ok('токен лежить із призначенням email_code', $row && $row['purpose'] === 'email_code');
        $this->ok('адреса збережена при токені', $row && $row['email'] === $email);
        $this->ok('код шестизначний', $row && preg_match('/^\d{6}$/', (string)$row['code']) === 1);

        $bad = EmailAuth::sendCode('не пошта');
        $this->ok('некоректна адреса — відмова з поясненням', empty($bad['ok']) && !empty($bad['error']));
    }

    private function testRateLimitPerAddress(): void
    {
        $this->group('стеля кодів на одну адресу');
        $email = $this->mail();
        for ($i = 0; $i < 3; $i++) EmailAuth::sendCode($email);
        $fourth = EmailAuth::sendCode($email);
        // Стеля саме на адресу, а не лише на IP: інакше зміна IP знімала б
        // обмеження з конкретної скриньки, і форма ставала б засобом її залити
        $this->ok('четвертий код за годину не надсилається', empty($fourth['ok']));
    }

    private function testWrongCode(): void
    {
        $this->group('чужий код не пускає');
        $email = $this->mail();
        $res = EmailAuth::sendCode($email);
        $real = $this->codeOf($res['token']);
        $wrong = $real === '111111' ? '222222' : '111111';

        $out = EmailAuth::verify($res['token'], $wrong);
        $this->ok('невірний код відхилено', empty($out['ok']));
        $this->ok('акаунт на цю адресу не з\'явився',
            (int)DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 0);
        $this->ok('токен не витрачено — правильний код ще спрацює',
            (int)DB::val('SELECT used FROM auth_tokens WHERE token = ?', [$res['token']]) === 0);

        $out2 = EmailAuth::verify('токена-з-таким-рядком-немає', $real);
        $this->ok('вигаданий токен не пускає', empty($out2['ok']));
    }

    private function testCreatesAccount(): void
    {
        $this->group('перший вхід = реєстрація');
        $email = $this->mail();
        $res = EmailAuth::sendCode($email);
        $out = EmailAuth::verify($res['token'], $this->codeOf($res['token']));
        $this->ok('код прийнято', !empty($out['ok']) && !empty($out['user_id']));
        $uid = (int)$out['user_id'];
        $this->users[] = $uid;

        $u = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
        $this->ok('акаунт створено з цією поштою', $u && $u['email'] === $email);
        $this->ok('роль — покупець', $u && $u['role'] === Roles::CUSTOMER);
        $this->ok('пошта позначена підтвердженою', $u && !empty($u['email_verified_at']));
        // Головне обмеження цього входу: пошта не каже нічого про телефон
        $this->ok('номер не з\'явився і не вважається підтвердженим',
            $u && empty($u['phone']) && empty($u['phone_verified_at']));
    }

    private function testSecondLoginSameAccount(): void
    {
        $this->group('другий вхід — той самий акаунт');
        $email = $this->mail();
        $first = EmailAuth::sendCode($email);
        $a = EmailAuth::verify($first['token'], $this->codeOf($first['token']));
        $this->users[] = (int)$a['user_id'];

        $second = EmailAuth::sendCode($email);
        $b = EmailAuth::verify($second['token'], $this->codeOf($second['token']));
        $this->ok('id той самий', (int)$a['user_id'] === (int)$b['user_id']);
        $this->ok('другого акаунта на ту саму пошту не з\'явилось',
            (int)DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) === 1);
    }

    private function testCodeIsSingleUse(): void
    {
        $this->group('код одноразовий');
        $email = $this->mail();
        $res = EmailAuth::sendCode($email);
        $code = $this->codeOf($res['token']);
        $ok = EmailAuth::verify($res['token'], $code);
        $this->users[] = (int)$ok['user_id'];
        $again = EmailAuth::verify($res['token'], $code);
        // Лист із кодом лишається в скриньці назавжди, і скриньку можуть
        // прочитати згодом — на чужому комп'ютері, зі старого телефону.
        // Тому код має вмирати першим використанням, а не строком.
        $this->ok('повторно той самий код не спрацьовує', empty($again['ok']));
    }

    private function testInactiveAccount(): void
    {
        $this->group('вимкнений акаунт');
        $email = $this->mail();
        $uid = $this->mkUser(['email' => $email, 'active' => 0]);
        $res = EmailAuth::sendCode($email);
        // Назовні відповідь звичайна — інакше форма стає способом перевіряти,
        // чи є така адреса в базі. Але коду не існує, і ввійти нічим.
        $this->ok('відповідь не видає, що акаунт вимкнено', !empty($res['ok']));
        $this->ok('коду при цьому не створено', empty($res['token'])
            && (int)DB::val("SELECT COUNT(*) FROM auth_tokens WHERE email = ?", [$email]) === 0);
        $this->ok('акаунт лишився вимкненим', (int)DB::val('SELECT active FROM users WHERE id = ?', [$uid]) === 0);
    }

    private function testOwnsPhone(): void
    {
        $this->group('хто вважається власником номера');

        $verified = DB::row('SELECT * FROM users WHERE id = ?',
            [$this->mkUser(['phone' => '+380670001101', 'phone_verified_at' => now(), 'tg_chat_id' => 'tg-own-1'])]);
        $this->ok('підтверджений номер — власник', Customers::ownsPhone($verified));

        // Запис продавця: технічна адреса й жодного каналу входу. Увійти в
        // нього не може ніхто, тож і привласнити його нема кому.
        $stub = DB::row('SELECT * FROM users WHERE id = ?',
            [$this->mkUser(['email' => bin2hex(random_bytes(6)) . '@offline.local', 'phone' => '+380670001102'])]);
        $this->ok('покупець, заведений продавцем, — власник', Customers::ownsPhone($stub));

        $typed = DB::row('SELECT * FROM users WHERE id = ?',
            [$this->mkUser(['phone' => '+380670001103', 'email_verified_at' => now()])]);
        $this->ok('вписаний руками номер (вхід поштою) — НЕ власник', !Customers::ownsPhone($typed));

        $google = DB::row('SELECT * FROM users WHERE id = ?',
            [$this->mkUser(['phone' => '+380670001104', 'google_id' => 'g-own-1', 'email_verified_at' => now()])]);
        $this->ok('вписаний руками номер (вхід Google) — НЕ власник', !Customers::ownsPhone($google));

        $tg = DB::row('SELECT * FROM users WHERE id = ?',
            [$this->mkUser(['phone' => '+380670001105', 'tg_chat_id' => 'tg-own-2'])]);
        $this->ok('Telegram без підтвердження номера — НЕ власник', !Customers::ownsPhone($tg));
    }

    private function testResolveRefusesUnprovenPhone(): void
    {
        $this->group('замовлення продавця не йде до непідтвердженого номера');

        $phone = '+380670001201';
        $impostor = $this->mkUser(['phone' => $phone, 'email_verified_at' => now(), 'name' => 'Хтось']);
        $got = Customers::resolve($phone, 'Покупець із трубки');
        $this->ok('покупця не причеплено — замовлення лишиться анонімним', $got === null);
        $this->ok('чужий акаунт не отримав нічого й не змінився',
            DB::val('SELECT name FROM users WHERE id = ?', [$impostor]) === 'Хтось');

        // Вільний номер працює як і працював: продавець заводить покупця
        $free = '+380670001202';
        $newId = Customers::resolve($free, 'Нова Покупчиня');
        $this->ok('на вільний номер акаунт створюється', is_int($newId) && $newId > 0);
        if ($newId) {
            $this->users[] = $newId;
            $row = DB::row('SELECT * FROM users WHERE id = ?', [$newId]);
            $this->ok('це запис продавця — власник свого номера', Customers::ownsPhone($row));
            $this->ok('повторний продаж іде в той самий запис', Customers::resolve($free, '') === $newId);
        }

        // Той, хто номер довів, отримує свої продажі
        $proven = '+380670001203';
        $owner = $this->mkUser(['phone' => $proven, 'phone_verified_at' => now(), 'tg_chat_id' => 'tg-own-3']);
        $this->ok('підтверджений номер отримує замовлення', Customers::resolve($proven, '') === $owner);
    }

    private function testClaimOrders(): void
    {
        $this->group('підтвердження номера забирає власні гостьові замовлення');
        $phone = '+380670001301';
        $mine = $this->mkOrder(null, $phone);                 // гість оформив без входу
        $someone = $this->mkOrder(null, '+380670001399');     // чуже гостьове
        $uid = $this->mkUser(['phone' => null]);
        $other = $this->mkUser(['phone' => null]);
        $taken = $this->mkOrder($other, $phone);              // той самий номер, але вже чиєсь

        $n = Customers::claimOrders($uid, $phone);
        $this->ok('причеплено рівно одне', $n === 1);
        $this->ok('гостьове замовлення на цей номер стало моїм',
            (int)DB::val('SELECT user_id FROM orders WHERE id = ?', [$mine]) === $uid);
        $this->ok('чуже гостьове не зачеплено',
            DB::val('SELECT user_id FROM orders WHERE id = ?', [$someone]) === null);
        // Найважливіше: те, що вже комусь належить, не перечіпляється НІКОЛИ —
        // інакше підтвердження номера ставало б способом відібрати замовлення
        $this->ok('замовлення, яке вже має власника, лишилось у нього',
            (int)DB::val('SELECT user_id FROM orders WHERE id = ?', [$taken]) === $other);
    }

    private function testMaskEmail(): void
    {
        $this->group('адреса в журналі замаскована');
        // Журнал невдалих спроб має показувати серію, а не перетворюватись на
        // список поштових адрес покупців
        $m = EmailAuth::maskEmail('ivanenko@ukr.net');
        $this->ok('домен видно, скриньку — ні', str_contains($m, '@ukr.net') && !str_contains($m, 'ivanenko'));
        $this->ok('коротка адреса теж прихована', !str_contains(EmailAuth::maskEmail('ai@i.ua'), 'ai@'));
    }
}

return (new EmailAuthTest())->run();
