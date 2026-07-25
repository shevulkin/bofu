<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $base = BOFU_ROOT . '/app/';
    $map = ['Core\\' => 'Core/', 'Controllers\\' => 'Controllers/'];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) { require $file; return; }
        }
    }
    $file = $base . 'Core/' . $class . '.php';
    if (is_file($file)) require $file;
});

require BOFU_ROOT . '/app/Core/helpers.php';

$GLOBALS['bofu_config'] = require BOFU_ROOT . '/config.php';

if (cfg('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BOFU_ROOT . '/storage/logs/php-error.log');
}
