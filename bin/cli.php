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
        $code = 0;
        foreach (glob(BOFU_ROOT . '/tests/*.php') ?: [] as $file) {
            echo "\n### " . basename($file) . "\n";
            $code = max($code, (int)require $file);
        }
        exit($code);
    default:
        echo "Використання: php bin/cli.php [migrate|seed|fresh|test]\n";
}
