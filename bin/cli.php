<?php
declare(strict_types=1);
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$cmd = $argv[1] ?? 'help';
switch ($cmd) {
    case 'migrate':
        Schema::createAll();
        // upgrade() наприкінці ставить schema_version. Без нього перший же
        // запит із браузера вважав би базу за версію 1 і прогнав усі історичні
        // кроки поспіль — вони нешкідливі, але це зайвий шум у логах саме тоді,
        // коли дивишся, чи все гаразд із новим сервером.
        Schema::upgrade();
        echo "Міграції: таблиці створено/оновлено (схема " . Schema::VERSION . ")\n";
        break;
    case 'grant-admin':
        /*
         * Перший адміністратор на новому сервері.
         *
         * Вхід через Google створює людину покупцем (Auth::loginWithGoogle) —
         * і правильно робить: інакше будь-хто, хто натиснув «Увійти», ставав би
         * адміністратором. Але на порожній базі це замикає коло: щоб роздати
         * ролі, треба вже мати доступ до адмінки. Ця команда його розмикає —
         * рівно один раз, з консолі, де вона доступна лише власнику хостингу.
         *
         * Роль пишемо і в user_roles (звідки її читає Auth), і в users.role —
         * друге лишилось з часів, коли роль була одна, і його читає ще частина
         * старих запитів.
         */
        $email = trim((string)($argv[2] ?? ''));
        if ($email === '') {
            echo "Вкажіть пошту: php bin/cli.php grant-admin ваша@пошта\n";
            exit(1);
        }
        $u = DB::row('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$u) {
            // Фігурні дужки обовʼязкові: у назвах змінних PHP дозволені байти
            // 0x80–0xFF, тож «$email»» прочиталось би як змінна разом із лапкою
            echo "Користувача з поштою «{$email}» немає.\n"
               . "Спершу увійдіть на сайт через Google цією поштою — акаунт створиться сам,\n"
               . "після чого повторіть команду.\n";
            exit(1);
        }
        $uid = (int)$u['id'];
        if (!DB::row('SELECT id FROM user_roles WHERE user_id = ? AND role = ?', [$uid, Roles::ADMIN])) {
            DB::insert('user_roles', ['user_id' => $uid, 'role' => Roles::ADMIN, 'created_at' => now()]);
        }
        DB::update('users', ['role' => Roles::ADMIN, 'active' => 1], 'id = ?', [$uid]);
        echo "Готово: {$u['name']} <$email> тепер адміністратор.\n"
           . "Перезайдіть на сайт, якщо ви вже були залогінені.\n";
        exit(0);
    case 'prod-check':
        // Нічого не змінює: показує, що заважає пускати на сайт покупців.
        // Код виходу ненульовий, якщо є хоч одне «не можна» — щоб перевірку
        // можна було поставити останнім кроком розгортання.
        $rows = ProdCheck::run();
        $mark = [ProdCheck::OK => '  ✓', ProdCheck::WARN => '  !', ProdCheck::BAD => '  ✗'];
        // %-28s рахує байти, а кирилична літера важить два — колонка «поїхала б»
        // рівно на тих рядках, які тут усі
        $pad = fn(string $s, int $w) => $s . str_repeat(' ', max(1, $w - mb_strlen($s)));
        $bad = $warn = 0;
        foreach ($rows as $r) {
            if ($r['level'] === ProdCheck::BAD) $bad++;
            if ($r['level'] === ProdCheck::WARN) $warn++;
            echo $mark[$r['level']], ' ', $pad($r['title'], 26), $r['note'], "\n";
        }
        echo "\n" . str_repeat('─', 48) . "\n";
        echo $bad
            ? "НЕ ГОТОВО: критичних — $bad, попереджень — $warn\n"
            : ($warn ? "Можна запускати, але перегляньте попередження ($warn)\n" : "Готово до бойового сервера\n");
        exit($bad ? 1 : 0);
    case 'seed':
        Schema::createAll();
        Seeder::run();
        break;
    case 'fresh':
        $c = cfg('db');
        if (DB::driver() === 'sqlite' && is_file($c['sqlite_path'])) unlink($c['sqlite_path']);
        else {
            foreach (array_keys(Schema::tables()) as $t) {
                try { DB::pdo()->exec("DROP TABLE IF EXISTS `$t`"); } catch (Throwable $e) {}
            }
        }
        Schema::createAll();
        Seeder::run();
        echo "Fresh: базу перестворено\n";
        break;
    case 'test':
        Schema::upgrade();
        $code = 0; $files = 0;
        foreach (glob(BOFU_ROOT . '/tests/*.php') ?: [] as $file) {
            echo "\n### " . basename($file) . "\n";
            $code = max($code, (int)require $file);
            $files++;
        }
        // підсумок наприкінці: інакше видно лише результат останнього файлу
        echo "\n" . str_repeat('─', 48) . "\n"
            . ($code === 0 ? "ВСІ НАБОРИ ПРОЙДЕНО ($files)" : "Є ПРОВАЛЕНІ НАБОРИ — дивіться вище") . "\n";
        exit($code);
    default:
        echo "Використання: php bin/cli.php [migrate|seed|fresh|test|prod-check]\n";
}
