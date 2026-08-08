<?php
declare(strict_types=1);
define('BOFU_ROOT', dirname(__DIR__));
require BOFU_ROOT . '/app/Core/bootstrap.php';

$cmd = $argv[1] ?? 'help';
switch ($cmd) {
    case 'migrate':
        Schema::createAll();
        echo "Міграції: таблиці створено/оновлено\n";
        break;
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
