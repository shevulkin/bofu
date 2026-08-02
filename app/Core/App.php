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

        // Сайт закрито від пошуковиків: заголовок діє й там, де немає HTML (sitemap, JSON, файли)
        if (Settings::bool('seo_noindex')) header('X-Robots-Tag: noindex, nofollow');

        // Спільні дані для всіх шаблонів
        View::share('auth_user', Auth::user());
        View::share('cart_count', Cart::count());

        // Обовʼязковий телефон: доки не вказаний коректний номер — лише профіль.
        // Перевіряємо саме валідність, а не «непорожність», інакше сміття на кшталт '1' пройде гейт.
        $u = Auth::user();
        if ($u && AuthTokens::normPhoneAny((string)($u['phone'] ?? '')) === null) {
            $p = rtrim(request_path(), '/') ?: '/';
            $allowed = ['/profile', '/profile/tg/link', '/profile/tg/check', '/profile/viber/link', '/profile/viber/check', '/logout', '/sw.js', '/manifest.webmanifest'];
            // відписка від розсилки має працювати завжди — це вимога закону про згоду
            if (!in_array($p, $allowed, true) && !str_starts_with($p, '/unsubscribe/')) {
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
                   // текст помилки PDO містить хост і користувача БД — показуємо лише в debug,
                   // решті йде в лог
                   . (cfg('debug') ? htmlspecialchars($e2->getMessage()) : 'Деталі — у storage/logs/app-error.log')
                   . '</p></div></body></html>';
                @file_put_contents(BOFU_ROOT . '/storage/logs/app-error.log',
                    '[' . date('Y-m-d H:i:s') . '] DB: ' . $e2->getMessage() . "\n", FILE_APPEND);
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
        // створення токенів входу — найдешевша для бота дія, тому з лімітом
        if ($path === '/auth/tg/start') { RateLimit::guard('login_start', 20, 3600, null, true); Controllers\AuthController::tgStart(); }
        if ($path === '/auth/tg/status') { Controllers\AuthController::tgStatus(); }
        if ($path === '/auth/viber/start') { RateLimit::guard('login_start', 20, 3600, null, true); Controllers\AuthController::viberStart(); }
        if ($path === '/auth/viber/status') { Controllers\AuthController::viberStatus(); }
        if ($path === '/auth/phone/start' && $method === 'POST') { RateLimit::guard('phone_start', 10, 3600, null, true); Controllers\AuthController::phoneStart(); }
        if ($path === '/auth/phone/verify' && $method === 'POST') { RateLimit::guard('phone_verify', 20, 3600, null, true); Controllers\AuthController::phoneVerify(); }
        if ($path === '/profile') { Controllers\Profile::index(); }
        if ($path === '/profile/tg/link') { Controllers\Profile::tgLink(); }
        if ($path === '/profile/tg/check') { Controllers\Profile::tgCheck(); }
        if ($path === '/profile/viber/link') { Controllers\Profile::viberLink(); }
        if ($path === '/profile/viber/check') { Controllers\Profile::viberCheck(); }
        if ($path === '/logout' && $method === 'POST') { Controllers\AuthController::logout(); }
        // режим перегляду — поза адмінкою: у режимі покупця стафних прав немає,
        // і під гейтом адмінки з нього не було б виходу
        if ($path === '/role/switch' && $method === 'POST') { Controllers\RoleController::change(); }
        if ($path === '/role/reset' && $method === 'POST') { Controllers\RoleController::reset(); }
        if ($path === '/role/store' && $method === 'POST') { Controllers\RoleController::store(); }

        // --- вітрина ---
        if ($path === '/') { Controllers\Home::index(); }
        if ($path === '/shop') { Controllers\Shop::index(); }
        if (preg_match('~^/product/([a-z0-9-]+)$~', $path, $m)) { Controllers\Shop::product($m[1]); }
        if ($path === '/about') { Controllers\Home::about(); }
        if ($path === '/courses') { Controllers\Home::courses(); }
        if ($path === '/gallery') { Controllers\Home::gallery(); }
        if ($path === '/social') { Controllers\Home::social(); }
        if ($path === '/diploma') { Controllers\Home::diploma(); }
        if ($path === '/diploma/check' && $method === 'POST') { RateLimit::guard('diploma', 40, 3600); Controllers\Home::diplomaCheck(); }

        // --- кошик і замовлення ---
        if ($path === '/cart') { Controllers\CartController::index(); }
        if ($path === '/cart/add' && $method === 'POST') { Controllers\CartController::add(); }
        if ($path === '/cart/update' && $method === 'POST') { Controllers\CartController::update(); }
        // сторінка оформлення лише читає; POST сюди приймати нема потреби, а без нього
        // стороння сторінка не може записати покупцеві промокод у сесію
        if ($path === '/checkout' && $method !== 'POST') { Controllers\Checkout::form(); }
        if ($path === '/checkout' . '/submit' && $method === 'POST') { Controllers\Checkout::submit(); }
        if (preg_match('~^/order/success/([a-f0-9]{32})$~', $path, $m)) { Controllers\Checkout::success($m[1]); }
        if ($path === '/orders') { Controllers\Checkout::myOrders(); }

        // --- розсилка ---
        if (preg_match('~^/unsubscribe/([a-f0-9]{32})$~', $path, $m)) { Controllers\NewsletterController::unsubscribe($m[1]); }

        // --- API ---
        // проксі до Нової Пошти витрачає наш API-ключ — не даємо викачувати довідник
        if ($path === '/api/np/cities') { RateLimit::guard('np', 300, 3600, null, true); Controllers\Api::npCities(); }
        if ($path === '/api/np/warehouses') { RateLimit::guard('np', 300, 3600, null, true); Controllers\Api::npWarehouses(); }
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

        // Третій елемент — право, потрібне для маршруту. Правило навмисно суворе:
        // маршрут без права доступний лише тим, хто має всі права. Так забутий
        // запис закриває доступ, а не відкриває його тихо.
        // 'staff' — виняток для панелі: вона нічого не показує понад те, на що
        // права перевіряються всередині, і має бути доступна будь-якому персоналу.
        $routes = [
            '/admin'                    => [$A.'Dashboard', 'index', 'staff'],
            '/admin/products'           => [$A.'Products', 'index', 'products.view'],
            '/admin/products/new'       => [$A.'Products', 'create', 'products.manage'],
            '/admin/products/bulk'      => [$A.'Products', 'bulk', 'products.view'],
            '/admin/categories'         => [$A.'Categories', 'index', 'catalog.manage'],
            '/admin/attributes'         => [$A.'Attributes', 'index', 'catalog.manage'],
            '/admin/stores'             => [$A.'Stores', 'index', 'stores.manage'],
            '/admin/orders'             => [$A.'Orders', 'index', 'orders.view'],
            '/admin/promos'             => [$A.'Promos', 'index', 'promos.manage'],
            '/admin/diplomas'           => [$A.'Diplomas', 'index', 'diplomas.manage'],
            '/admin/users'              => [$A.'Users', 'index', 'users.manage'],
            '/admin/users/message'      => [$A.'Users', 'message', 'users.manage'],
            '/admin/subscribers'        => [$A.'Subscribers', 'index', 'subscribers.manage'],
            '/admin/content'            => [$A.'ContentAdmin', 'index', 'content.manage'],
            '/admin/media'              => [$A.'Media', 'index', 'media.manage'],
            '/admin/settings'           => [$A.'SettingsAdmin', 'index', 'settings.manage'],
            '/admin/settings/check'     => [$A.'SettingsAdmin', 'check', 'settings.manage'],
            '/admin/notifications'      => [$A.'Notifications', 'index', 'notifications.manage'],
        ];
        if (isset($routes[$path])) {
            [$cls, $fn] = $routes[$path];
            self::gate($routes[$path][2] ?? null);
            $cls::$fn();
        }
        if (preg_match('~^/admin/products/(\d+)$~', $path, $m)) {
            self::gate('products.view'); $cls = $A.'Products'; $cls::edit((int)$m[1]);
        }
        if (preg_match('~^/admin/orders/(\d+)$~', $path, $m)) {
            self::gate('orders.view'); $cls = $A.'Orders'; $cls::view((int)$m[1]);
        }

        http_response_code(404);
        echo View::render('errors/404');
        exit;
    }

    /**
     * Гейт маршруту адмінки. Це перший із двох рубежів: другий стоїть усередині
     * кожної дії, що змінює дані, бо один маршрут обслуговує і показ, і POST
     * з різними діями, а приховати кнопку — не перевірка.
     */
    private static function gate(?string $cap): void
    {
        if ($cap === 'staff') return;          // достатньо requireStaff() вище
        Auth::requireCap($cap ?? '*');         // право не оголошене — лише повні права
    }
}
