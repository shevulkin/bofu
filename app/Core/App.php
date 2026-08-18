<?php
declare(strict_types=1);

class App
{
    public static function run(): void
    {
        // Найпершими: заголовки мають піти навіть із тією відповіддю, яка
        // впаде помилкою або редіректом. Усе, що нижче, вже може щось віддати.
        Security::headers();
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
                // Пошуковик має зрозуміти, що це тимчасово, і не викидати сторінки з індексу
                header('Retry-After: 300');

                /*
                 * Дві різні сторінки для двох різних людей.
                 *
                 * Розробнику потрібна причина й команда. Покупцю — ні: він не
                 * запускатиме docker, а слова «C:\xampp\htdocs\bofu» на чужому
                 * сайті лише розкажуть стороннім, як влаштована наша машина.
                 * Тому підказки видно рівно там, де debug увімкнений, тобто
                 * локально, — а на бойовому сервері лишається одна фраза без
                 * жодної технічної деталі.
                 */
                $body = cfg('debug')
                    ? '<h1 style="color:#f0b429">База даних недоступна</h1>'
                      . '<p>Запустіть базу даних командою:</p>'
                      . '<p><code style="background:#241d15;padding:8px 14px;border-radius:4px">docker compose up -d</code></p>'
                      . '<p style="color:#9a8a6b;font-size:13px">у папці проєкту (' . htmlspecialchars(BOFU_ROOT) . '),'
                      . ' зачекайте ~20 секунд і оновіть сторінку.<br>'
                      // текст помилки PDO містить хост і користувача БД — лише в debug
                      . htmlspecialchars($e2->getMessage()) . '</p>'
                    : '<h1 style="color:#f0b429">Сайт тимчасово недоступний</h1>'
                      . '<p style="color:#9a8a6b">Ми вже про це знаємо. Спробуйте, будь ласка, за кілька хвилин.</p>';

                echo '<!DOCTYPE html><html lang="uk"><head><meta charset="utf-8">'
                   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                   . '<title>Сайт тимчасово недоступний</title></head>'
                   . '<body style="font-family:sans-serif;background:#141110;color:#f6ecd9;display:flex;'
                   . 'align-items:center;justify-content:center;min-height:100vh;text-align:center;margin:0">'
                   . '<div style="padding:20px">' . $body . '</div></body></html>';
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

        // каса — теж поза адмінкою: чек набирають, ходячи вітриною, і смужка
        // продажу має працювати саме там, де продавець зараз стоїть
        if ($path === '/pos/state') { Controllers\PosController::state(); }
        if ($path === '/pos/off' && $method === 'POST') { Controllers\PosController::off(); }

        // режим редагування — теж поза адмінкою: правлять блоки на самій вітрині,
        // і гейт /admin закрив би збереження саме там, де воно потрібне
        if ($path === '/edit/on' && $method === 'POST') { Controllers\EditController::on(); }
        if ($path === '/edit/off' && $method === 'POST') { Controllers\EditController::off(); }
        if ($path === '/edit/blocks' && $method === 'GET') { Controllers\EditController::blocks(); }
        if ($path === '/edit/block' && $method === 'GET') { Controllers\EditController::block(); }
        if ($path === '/edit/save' && $method === 'POST') { Controllers\EditController::save(); }

        // --- вітрина ---
        if ($path === '/') { Controllers\Home::index(); }
        if ($path === '/shop') { Controllers\Shop::index(); }
        // Розділ каталогу окремою адресою: /shop/med замість /shop?cat=med.
        // Один сегмент вистачає й для підрозділів — slug категорії унікальний.
        // Старі адреси з ?cat= сюди ж і переїжджають, редиректом (Shop::index).
        if (preg_match('~^/shop/([a-z0-9-]+)$~', $path, $m)) { Controllers\Shop::index($m[1]); }
        if (preg_match('~^/product/([a-z0-9-]+)$~', $path, $m)) { Controllers\Shop::product($m[1]); }
        if ($path === '/about') { Controllers\Home::about(); }
        if ($path === '/courses') { Controllers\Home::courses(); }
        if ($path === '/gallery') { Controllers\Home::gallery(); }
        if ($path === '/social') { Controllers\Home::social(); }
        if ($path === '/stores') { Controllers\Home::stores(); }
        if ($path === '/partners') { Controllers\Home::partners(); }
        // Правові сторінки. Умови продажу — частина товару, а не додаток до
        // нього: без них покупець не знає, з ким має справу, а закон вимагає
        // цієї інформації на сайті.
        if (preg_match('~^/(delivery|payment|returns|privacy|offer)$~', $path, $m)) { Controllers\Home::legal($m[1]); }
        if ($path === '/diploma') { Controllers\Home::diploma(); }
        if ($path === '/diploma/check' && $method === 'POST') { RateLimit::guard('diploma', 40, 3600); Controllers\Home::diplomaCheck(); }

        // --- кошик і замовлення ---
        if ($path === '/cart') { Controllers\CartController::index(); }
        if ($path === '/cart/add' && $method === 'POST') { Controllers\CartController::add(); }
        // Набір кладеться однією дією: розкладати його на три кліки означало б
        // питати покупця про те, що вже вирішено складом набору
        if ($path === '/cart/add-bundle' && $method === 'POST') { Controllers\CartController::addBundle(); }
        if ($path === '/cart/update' && $method === 'POST') { Controllers\CartController::update(); }
        // сторінка оформлення лише читає; POST сюди приймати нема потреби, а без нього
        // стороння сторінка не може записати покупцеві промокод у сесію
        // У режимі продажу кошик — це чек покупця, а не власна покупка продавця.
        // Звичайне оформлення тут створило б замовлення на самого продавця, ще
        // й повз касу: без анонімності, без «віддано», без позначки «звідки».
        // Тому обидва входи в оформлення ведуть на касу.
        if (str_starts_with($path, '/checkout') && Pos::active()) redirect('/admin/orders/new');
        if ($path === '/checkout' && $method !== 'POST') { Controllers\Checkout::form(); }
        if ($path === '/checkout' . '/submit' && $method === 'POST') { Controllers\Checkout::submit(); }
        // промокод — коротка комбінація літер, тобто його можна підбирати; з лімітом
        if ($path === '/checkout/promo' && $method === 'POST') { RateLimit::guard('promo', 40, 3600, null, true); Controllers\Checkout::applyPromo(); }
        if (preg_match('~^/order/success/([a-f0-9]{32})$~', $path, $m)) { Controllers\Checkout::success($m[1]); }
        if ($path === '/orders') { Controllers\Checkout::myOrders(); }
        // «повідомте, коли зʼявиться» — з лімітом: кнопка відкрита будь-кому,
        // хто увійшов, а запити дешеві й ідуть у чергу, яку читає продавець
        if ($path === '/stock/watch' && $method === 'POST') {
            RateLimit::guard('stock_watch', 30, 3600);
            Controllers\Shop::watch();
        }

        // --- розсилка ---
        if (preg_match('~^/unsubscribe/([a-f0-9]{32})$~', $path, $m)) { Controllers\NewsletterController::unsubscribe($m[1]); }

        // --- API ---
        // проксі до Нової Пошти витрачає наш API-ключ — не даємо викачувати довідник
        if ($path === '/api/np/cities') { RateLimit::guard('np', 300, 3600, null, true); Controllers\Api::npCities(); }
        if ($path === '/api/np/warehouses') { RateLimit::guard('np', 300, 3600, null, true); Controllers\Api::npWarehouses(); }
        if ($path === '/api/np/streets') { RateLimit::guard('np', 300, 3600, null, true); Controllers\Api::npStreets(); }
        // Довідник відправників — вміст особистого кабінету НП, тож лише персоналу
        // й лише POST: право перевіряється всередині разом із CSRF
        if ($path === '/api/np/senders' && $method === 'POST') { Controllers\Api::npSenders(); }
        // Агент каси: єдине вікно між сайтом і ПРРО, у якого ключ лежить у
        // магазині. Без сесії й без CSRF — агент не браузер; його впізнають за
        // токеном точки. Ліміт усередині: агент стукає раз на пару секунд, і
        // це нормально, а от підбір токена має впертись у стелю.
        if ($path === '/api/fiscal/pull' && $method === 'POST') { Controllers\Api::fiscalPull(); }
        if ($path === '/api/fiscal/push' && $method === 'POST') { Controllers\Api::fiscalPush(); }
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
            '/admin/stock-requests'     => [$A.'StockRequests', 'index', 'products.view'],
            '/admin/categories'         => [$A.'Categories', 'index', 'catalog.manage'],
            '/admin/attributes'         => [$A.'Attributes', 'index', 'catalog.manage'],
            '/admin/brands'             => [$A.'Brands', 'index', 'catalog.manage'],
            '/admin/partners'           => [$A.'Partners', 'index', 'content.manage'],
            '/admin/stores'             => [$A.'Stores', 'index', 'stores.manage'],
            // Власники точок: коли мережа — це більше ніж один платник податків
            '/admin/owners'             => [$A.'OwnersAdmin', 'index', 'stores.manage'],
            '/admin/orders'             => [$A.'Orders', 'index', 'orders.view'],
            '/admin/orders/new'         => [$A.'Orders', 'pos', 'orders.create'],
            '/admin/orders/search'      => [$A.'Orders', 'search', 'orders.create'],
            // Плитка однієї категорії: фільтр у касі не має відкривати сторінку
            // заново — разом з нею скидався крок і набране в полях
            '/admin/orders/tiles'       => [$A.'Orders', 'tiles', 'orders.create'],
            // Маршрут «каса на цьому пристрої»: до Device Manager на машині
            // продавця може достукатись лише його ж вкладка, тож завдання їй
            // видає сайт, а результат вона приносить назад.
            '/admin/fiscal/next'        => [$A.'Orders', 'fiscalNext', 'orders.fiscal'],
            '/admin/fiscal/done'        => [$A.'Orders', 'fiscalDone', 'orders.fiscal'],
            // Пробний запит до каси на пристрої: нічого не проводить, лише
            // показує, чи браузер узагалі пускає сторінку на localhost
            '/admin/fiscal/probe'       => [$A.'Orders', 'fiscalProbe', 'orders.fiscal'],
            '/admin/products/codes'     => [$A.'Products', 'codes', 'products.manage'],
            // Каса «Вчасно»: зміна, звіти, звірка товарів. Чеки окремих
            // замовлень тут не живуть — вони в картках замовлень.
            '/admin/vchasno'            => [$A.'Kasa', 'index', 'fiscal.manage'],
            '/admin/vchasno/goods'      => [$A.'Kasa', 'goods', 'fiscal.manage'],
            '/admin/promos'             => [$A.'Promos', 'index', 'promos.manage'],
            // Набори «разом дешевше»: свій екран, бо в набору є склад —
            // список товарів, який не вміщається рядком серед акцій
            '/admin/bundles'            => [$A.'BundlesAdmin', 'index', 'promos.manage'],
            // Оптові шкали: своя сторінка, бо в них є питання, якого немає в
            // акцій, — котра шкала справді діє після перебивання ярусів
            '/admin/wholesale'          => [$A.'Wholesale', 'index', 'promos.manage'],
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
        // Рахунок і видаткова накладна: окрема сторінка без адмінського обрамлення,
        // щоб її можна було просто надрукувати або зберегти в PDF браузером
        if (preg_match('~^/admin/orders/(\d+)/invoice$~', $path, $m)) {
            self::gate('orders.view'); $cls = $A.'Orders'; $cls::invoice((int)$m[1]);
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
