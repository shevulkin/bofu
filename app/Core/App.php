<?php
declare(strict_types=1);

class App
{
    public static function run(): void
    {
        if (!self::dbReady()) return;
        Auth::start();
        $path = request_path();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Спільні дані для всіх шаблонів
        View::share('auth_user', Auth::user());
        View::share('cart_count', Cart::count());

        // Обовʼязковий телефон: доки не вказаний — лише профіль
        $u = Auth::user();
        if ($u && empty($u['phone'])) {
            $p = rtrim(request_path(), '/') ?: '/';
            $allowed = ['/profile', '/profile/tg/link', '/profile/tg/check', '/profile/viber/link', '/profile/viber/check', '/logout', '/sw.js', '/manifest.webmanifest'];
            if (!in_array($p, $allowed, true)) {
                flash('error', 'Вкажіть, будь ласка, номер телефону, щоб продовжити.');
                redirect('/profile');
            }
        }

        try {
            self::route($method, rtrim($path, '/') ?: '/');
        } catch (Throwable $e) {
            @file_put_contents(BOFU_ROOT . '/storage/logs/app-error.log',
                '[' . now() . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
            if (cfg('debug')) throw $e;
            http_response_code(500);
            echo View::render('errors/500', [], 'layouts/main');
        }
    }

    /** Перший запуск: створює таблиці й демо-дані автоматично */
    private static function dbReady(): bool
    {
        try {
            DB::val('SELECT COUNT(*) FROM settings');
            Schema::upgrade();
            return true;
        } catch (Throwable $e) {
            try {
                Schema::createAll();
                Seeder::run();
                DB::val('SELECT COUNT(*) FROM settings');
                return true;
            } catch (Throwable $e2) {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html lang="uk"><body style="font-family:sans-serif;background:#141110;color:#f6ecd9;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center">'
                   . '<div><h1 style="color:#f0b429">База даних недоступна</h1>'
                   . '<p>Запустіть базу даних командою:</p>'
                   . '<p><code style="background:#241d15;padding:8px 14px;border-radius:4px">docker compose up -d</code></p>'
                   . '<p style="color:#8a7a5c;font-size:13px">у папці проєкту (C:\\xampp\\htdocs\\bofu), зачекайте ~20 секунд і оновіть сторінку.<br>'
                   . htmlspecialchars($e2->getMessage()) . '</p></div></body></html>';
                return false;
            }
        }
    }

    private static function route(string $method, string $path): void
    {
        // --- статичні/системні ---
        if ($path === '/robots.txt') { Controllers\Seo::robots(); }
        if ($path === '/sitemap.xml') { Controllers\Seo::sitemap(); }
        if ($path === '/manifest.webmanifest') { Controllers\Seo::manifest(); }
        if ($path === '/sw.js') { Controllers\Seo::serviceWorker(); }

        // --- аутентифікація ---
        if ($path === '/auth/google') { Controllers\AuthController::google(); }
        if ($path === '/auth/google/callback') { Controllers\AuthController::googleCallback(); }
        if ($path === '/auth/demo' && $method === 'POST') { Controllers\AuthController::demo(); }
        if ($path === '/auth/tg/start') { Controllers\AuthController::tgStart(); }
        if ($path === '/auth/tg/status') { Controllers\AuthController::tgStatus(); }
        if ($path === '/auth/viber/start') { Controllers\AuthController::viberStart(); }
        if ($path === '/auth/viber/status') { Controllers\AuthController::viberStatus(); }
        if ($path === '/auth/phone/start' && $method === 'POST') { Controllers\AuthController::phoneStart(); }
        if ($path === '/auth/phone/verify' && $method === 'POST') { Controllers\AuthController::phoneVerify(); }
        if ($path === '/profile') { Controllers\Profile::index(); }
        if ($path === '/profile/tg/link') { Controllers\Profile::tgLink(); }
        if ($path === '/profile/tg/check') { Controllers\Profile::tgCheck(); }
        if ($path === '/profile/viber/link') { Controllers\Profile::viberLink(); }
        if ($path === '/profile/viber/check') { Controllers\Profile::viberCheck(); }
        if ($path === '/logout' && $method === 'POST') { Controllers\AuthController::logout(); }

        // --- вітрина ---
        if ($path === '/') { Controllers\Home::index(); }
        if ($path === '/shop') { Controllers\Shop::index(); }
        if (preg_match('~^/product/([a-z0-9-]+)$~', $path, $m)) { Controllers\Shop::product($m[1]); }
        if ($path === '/about') { Controllers\Home::about(); }
        if ($path === '/courses') { Controllers\Home::courses(); }
        if ($path === '/gallery') { Controllers\Home::gallery(); }
        if ($path === '/social') { Controllers\Home::social(); }
        if ($path === '/diploma') { Controllers\Home::diploma(); }
        if ($path === '/diploma/check' && $method === 'POST') { Controllers\Home::diplomaCheck(); }

        // --- кошик і замовлення ---
        if ($path === '/cart') { Controllers\CartController::index(); }
        if ($path === '/cart/add' && $method === 'POST') { Controllers\CartController::add(); }
        if ($path === '/cart/update' && $method === 'POST') { Controllers\CartController::update(); }
        if ($path === '/checkout') { Controllers\Checkout::form(); }
        if ($path === '/checkout' . '/submit' && $method === 'POST') { Controllers\Checkout::submit(); }
        if (preg_match('~^/order/success/([A-Z0-9-]+)$~', $path, $m)) { Controllers\Checkout::success($m[1]); }
        if ($path === '/orders') { Controllers\Checkout::myOrders(); }

        // --- API ---
        if ($path === '/api/np/cities') { Controllers\Api::npCities(); }
        if ($path === '/api/np/warehouses') { Controllers\Api::npWarehouses(); }
        if ($path === '/api/push/subscribe' && $method === 'POST') { Controllers\Api::pushSubscribe(); }
        if ($path === '/api/viber/webhook' && $method === 'POST') { Controllers\Api::viberWebhook(); }

        // --- адмінка ---
        if (str_starts_with($path, '/admin')) { self::admin($method, $path); }

        http_response_code(404);
        echo View::render('errors/404');
        exit;
    }

    private static function admin(string $method, string $path): void
    {
        Auth::requireStaff();
        $A = 'Controllers\\Admin\\';
        $post = $method === 'POST';
        if ($post) Csrf::verify();

        $routes = [
            '/admin'                    => [$A.'Dashboard', 'index'],
            '/admin/products'           => [$A.'Products', 'index'],
            '/admin/products/new'       => [$A.'Products', 'create'],
            '/admin/products/bulk'      => [$A.'Products', 'bulk'],
            '/admin/categories'         => [$A.'Categories', 'index'],
            '/admin/stores'             => [$A.'Stores', 'index'],
            '/admin/orders'             => [$A.'Orders', 'index'],
            '/admin/promos'             => [$A.'Promos', 'index'],
            '/admin/diplomas'           => [$A.'Diplomas', 'index'],
            '/admin/users'              => [$A.'Users', 'index'],
            '/admin/content'            => [$A.'ContentAdmin', 'index'],
            '/admin/media'              => [$A.'Media', 'index'],
            '/admin/settings'           => [$A.'SettingsAdmin', 'index'],
            '/admin/notifications'      => [$A.'Notifications', 'index'],
        ];
        if (isset($routes[$path])) {
            [$cls, $fn] = $routes[$path];
            $cls::$fn();
        }
        if (preg_match('~^/admin/products/(\d+)$~', $path, $m)) { $cls = $A.'Products'; $cls::edit((int)$m[1]); }
        if (preg_match('~^/admin/orders/(\d+)$~', $path, $m)) { $cls = $A.'Orders'; $cls::view((int)$m[1]); }

        http_response_code(404);
        echo View::render('errors/404');
        exit;
    }
}
