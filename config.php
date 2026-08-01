<?php
/**
 * Базова конфігурація Beekeeper of Ukraine.
 * НЕ редагуйте цей файл — створіть config.local.php і перевизначте потрібні ключі.
 */
$config = [
    'app_name'   => 'Beekeeper of Ukraine',
    'env'        => 'production',      // production | dev
    'debug'      => false,
    'base_url'   => null,              // null = автовизначення (працює і на /bofu/, і в корені домену)
    // База даних: mysql (Docker/XAMPP/HostIQ) або sqlite (тести)
    'db' => [
        'driver'   => 'mysql',
        'host'     => '127.0.0.1',
        'port'     => 3307,            // MySQL у Docker (щоб не конфліктувати з XAMPP:3306)
        'database' => 'bofu',
        'username' => 'bofu',
        'password' => 'bofu_secret',
        'charset'  => 'utf8mb4',
        'sqlite_path' => __DIR__ . '/storage/database.sqlite',
    ],
    // Google OAuth (заповнюється в налаштуваннях адмінки або тут)
    'google' => [ 'client_id' => '', 'client_secret' => '' ],
    // Демо-вхід: дає адмін-права одним кліком без пароля, тому за замовчуванням вимкнений.
    // Вмикати ЛИШЕ локально через config.local.php: ['demo_login' => true]
    'demo_login' => false,
    // Увімкніть, лише якщо сайт стоїть за Cloudflare/балансувальником: тоді IP клієнта
    // беремо з X-Forwarded-For. Без проксі це дало б змогу обходити ліміти підробкою заголовка.
    'trust_proxy' => false,
    'uploads_dir' => __DIR__ . '/assets/uploads',
    'session_name' => 'bofu_sid',
];
if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    $config = array_replace_recursive($config, $local);
}
return $config;
