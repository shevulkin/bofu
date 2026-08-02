<?php
declare(strict_types=1);

/**
 * Портативна схема БД: генерує SQL для MySQL та SQLite з одного опису.
 * Типи: id, int, num(10,2), str(255), text, bool, ts
 */
class Schema
{
    public const VERSION = 15;

    /** Оновлення існуючої бази до поточної версії без втрати даних */
    public static function upgrade(): void
    {
        $ver = (int)(Settings::get('schema_version', '1'));
        if ($ver >= self::VERSION) return;
        if ($ver < 2) {
            self::addColumn('users', 'viber_id', 'str null');
            self::addColumn('users', 'phone', 'str null');
            // нові таблиці створяться через createAll (IF NOT EXISTS)
            self::createAll();
            // демо-користувачам телефони, щоб гейт не блокував
            // (|| — конкатенація лише в SQLite, у MySQL це логічне «або»)
            $cat = DB::driver() === 'sqlite' ? "'+38067000000' || id" : "CONCAT('+38067000000', id)";
            DB::query("UPDATE users SET phone = $cat WHERE email LIKE '%@bofu.local' AND (phone IS NULL OR phone = '')");
            // правила сповіщень для каналу viber
            foreach (Notify::EVENTS as $event => $label) {
                $exists = DB::row('SELECT id FROM notification_rules WHERE event = ? AND channel = ?', [$event, 'viber']);
                if (!$exists) {
                    DB::insert('notification_rules', [
                        'event' => $event, 'channel' => 'viber', 'enabled' => 0,
                        'recipients' => 'admins_sellers', 'template' => Notify::DEFAULT_TEMPLATES[$event] ?? '',
                    ]);
                }
            }
        }
        if ($ver < 3) {
            // словник характеристик + звʼязки варіантів
            self::createAll();
            self::addColumn('product_attrs', 'attribute_id', 'int null');
            self::addColumn('product_attrs', 'value_id', 'int null');
            Attrs::backfill();
        }
        if ($ver < 4) {
            self::addColumn('products', 'low_stock_threshold', 'int null');
        }
        if ($ver < 5) {
            // наявність товарів з варіантами тепер рахується по варіантах у кожному магазині
            self::moveStockToVariants();
        }
        if ($ver < 6) {
            // згода на розсилку: окрема таблиця, бо підписатись може й гість без акаунта
            self::createAll();
        }
        if ($ver < 7) {
            // наслідок бага з `||` вище: у MySQL телефони демо-акаунтів записались як '1'.
            // Чистимо — гейт у App::run() змусить вказати справжній номер.
            self::fixBrokenPhones();
        }
        if ($ver < 8) {
            // сторінка «замовлення прийнято» більше не адресується номером,
            // який можна перебрати, — тільки випадковим токеном
            self::addColumn('orders', 'token', 'str null');
            self::createAll(); // rate_hits
            foreach (DB::all("SELECT id FROM orders WHERE token IS NULL OR token = ''") as $o) {
                DB::update('orders', ['token' => bin2hex(random_bytes(16))], 'id = ?', [$o['id']]);
            }
        }
        if ($ver < 9) {
            // після чистки в ver<7 демо-акаунти лишились без телефону й упирались у гейт —
            // повертаємо те, що мала зробити зламана міграція ver<2
            foreach (DB::all("SELECT id FROM users WHERE email LIKE '%@bofu.local' AND (phone IS NULL OR phone = '')") as $u) {
                DB::update('users', ['phone' => '+3806700000' . str_pad((string)$u['id'], 2, '0', STR_PAD_LEFT)], 'id = ?', [$u['id']]);
            }
        }
        if ($ver < 10) {
            // замовлення розділяється на підзамовлення по магазинах
            self::addColumn('orders', 'parent_id', 'int null');
            self::addColumn('orders', 'seq', 'int default 0');
            self::createAll(); // order_events
            self::splitLegacyOrders();
        }
        if ($ver < 11) {
            // ролі переїжджають в окрему таблицю: одна людина може мати їх кілька
            self::createAll(); // user_roles
            self::moveRolesToUserRoles();
        }
        if ($ver < 12) {
            // одна людина може працювати і як адмін, і як продавець точки —
            // без ролі запис «хто змінив статус» став неоднозначним
            self::addColumn('order_events', 'role', 'str null');
        }
        if ($ver < 13) {
            // хто взяв підзамовлення в роботу — мітка без замка, лише щоб було видно,
            // що воно не лежить без нагляду і що двоє не роблять те саме
            self::addColumn('orders', 'assigned_user_id', 'int null');
            self::addColumn('orders', 'assigned_at', 'str null');
        }
        if ($ver < 14) {
            // особисті налаштування сповіщень; порожньо = усе, що дозволив адмін
            self::createAll(); // user_notify_prefs
        }
        if ($ver < 15) {
            // Прив'язка до точки тепер живе лише разом із роллю продавця
            // (Controllers\Admin\Users::saveStores). Прибираємо рядки, що лишились
            // від тих, хто роль уже втратив: інакше правило діяло б тільки для
            // нових змін, а старі «вічні» доступи тихо пережили б його.
            DB::query('DELETE FROM seller_stores WHERE user_id NOT IN
                       (SELECT user_id FROM user_roles WHERE role = ?)', [Roles::SELLER]);
        }
        Settings::set('schema_version', (string)self::VERSION);
    }

    /**
     * Роль зі стовпця users.role стає рядком у user_roles. Стовпець лишаємо на місці,
     * але більше не читаємо — приберемо окремо, коли переконаємось, що на нього
     * ніхто не посилається. Покупця не переносимо: це базовий стан, а не право.
     */
    private static function moveRolesToUserRoles(): void
    {
        foreach (DB::all('SELECT id, role FROM users') as $u) {
            $role = (string)($u['role'] ?? '');
            if ($role === '' || $role === Roles::CUSTOMER || !Roles::exists($role)) continue;
            $has = DB::row('SELECT id FROM user_roles WHERE user_id = ? AND role = ?', [$u['id'], $role]);
            if (!$has) DB::insert('user_roles', ['user_id' => (int)$u['id'], 'role' => $role, 'created_at' => now()]);
        }
    }

    /**
     * Старі замовлення переводимо в нову модель: кожне дістає одне підзамовлення,
     * куди переїжджають його позиції. Магазин — той, що вже вказаний у замовленні
     * (самовивіз), інакше магазин за замовчуванням. Так у коді лишається один шлях:
     * позиції завжди лежать у підзамовленні.
     */
    private static function splitLegacyOrders(): void
    {
        // позиції, що лежать просто в замовленні, — ознака старої моделі (і захист від повторного запуску)
        $rows = DB::all(
            'SELECT * FROM orders o WHERE o.parent_id IS NULL
             AND EXISTS (SELECT 1 FROM order_items i WHERE i.order_id = o.id)');
        foreach ($rows as $o) {
            $parentId = (int)$o['id'];
            $storeId = $o['store_id'] ? (int)$o['store_id'] : OrderFlow::defaultStoreId();
            DB::tx(function () use ($o, $parentId, $storeId) {
                $data = ['parent_id' => $parentId, 'seq' => 1,
                    'number' => $o['number'] . '/1', 'token' => null,
                    'store_id' => $storeId ?: null, 'status' => $o['status'],
                    'subtotal' => $o['subtotal'], 'discount' => $o['discount'], 'total' => $o['total'],
                    'created_at' => $o['created_at'] ?: now()];
                foreach (['user_id', 'name', 'phone', 'email', 'delivery', 'city', 'np_office',
                          'address', 'comment', 'promo_code'] as $f) $data[$f] = $o[$f] ?? null;
                $childId = DB::insert('orders', $data);
                DB::update('order_items', ['order_id' => $childId], 'order_id = ?', [$parentId]);
                OrderFlow::log($parentId, $childId, 'created',
                    'Замовлення переведено в модель підзамовлень при оновленні бази.');
            });
        }
    }

    /**
     * Залишки товарів, що мають варіанти, переносимо з рядка «без варіанта»
     * на перший активний варіант того самого магазину — щоб не втратити кількість.
     */
    private static function moveStockToVariants(): void
    {
        $rows = DB::all(
            'SELECT ss.* FROM store_stock ss
             WHERE ss.variant_id IS NULL
               AND EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = ss.product_id AND v.active = 1)');
        foreach ($rows as $r) {
            $pid = (int)$r['product_id']; $sid = (int)$r['store_id']; $qty = (int)$r['qty'];
            $vid = (int)(DB::val('SELECT id FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort, id LIMIT 1', [$pid]) ?? 0);
            if ($vid && $qty > 0) {
                $exists = DB::row('SELECT id, qty FROM store_stock WHERE product_id = ? AND store_id = ? AND variant_id = ?', [$pid, $sid, $vid]);
                if ($exists) DB::update('store_stock', ['qty' => (int)$exists['qty'] + $qty], 'id = ?', [$exists['id']]);
                else DB::insert('store_stock', ['product_id' => $pid, 'store_id' => $sid, 'variant_id' => $vid, 'qty' => $qty]);
            }
            DB::delete('store_stock', 'id = ?', [(int)$r['id']]);
        }
    }

    /** Телефони, які не проходять нормалізацію, робимо порожніми — краще перепитати, ніж мати сміття */
    private static function fixBrokenPhones(): void
    {
        foreach (DB::all("SELECT id, phone FROM users WHERE phone IS NOT NULL AND phone != ''") as $u) {
            $norm = AuthTokens::normPhoneAny((string)$u['phone']);
            if ($norm === null) DB::update('users', ['phone' => null], 'id = ?', [$u['id']]);
            elseif ($norm !== $u['phone']) DB::update('users', ['phone' => $norm], 'id = ?', [$u['id']]);
        }
    }

    private static function addColumn(string $table, string $col, string $spec): void
    {
        try { DB::val("SELECT $col FROM $table LIMIT 1"); return; } catch (Throwable $e) {}
        $driver = DB::driver();
        $sql = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . self::colSql($col, $spec, $driver);
        try { DB::pdo()->exec($sql); } catch (Throwable $e) { /* лог */ }
    }

    public static function tables(): array
    {
        return [
            'users' => [
                'id' => 'id', 'google_id' => 'str null unique', 'email' => 'str unique',
                'name' => 'str', 'avatar' => 'str null',
                'role' => "str default 'customer'", // admin|seller|editor|customer
                'active' => 'bool default 1', 'tg_chat_id' => 'str null',
                'viber_id' => 'str null', 'phone' => 'str null',
                'created_at' => 'ts',
            ],
            'stores' => [
                'id' => 'id', 'name' => 'str', 'slug' => 'str unique', 'city' => 'str null',
                'address' => 'str null', 'phone' => 'str null', 'active' => 'bool default 1', 'sort' => 'int default 0',
            ],
            'seller_stores' => [ 'user_id' => 'int', 'store_id' => 'int' ],
            // Ролі окремо від users: одна людина може мати кілька. Призначення магазинів
            // (seller_stores) з роллю не пов'язане — це різні речі.
            'user_roles' => [
                'id' => 'id', 'user_id' => 'int', 'role' => 'str', 'created_at' => 'ts',
            ],
            'categories' => [
                'id' => 'id', 'name' => 'str', 'slug' => 'str unique',
                'type' => "str default 'product'", 'sort' => 'int default 0', 'active' => 'bool default 1',
            ],
            'products' => [
                'id' => 'id', 'category_id' => 'int', 'name' => 'str', 'slug' => 'str unique',
                'sku' => 'str null', 'short_desc' => 'text null', 'description' => 'text null',
                'base_price' => 'num null', // null => "За запитом"
                'old_price' => 'num null',
                'type' => "str default 'product'", // product|service|video|course
                'unit' => 'str null',
                'active' => 'bool default 1', 'featured' => 'bool default 0',
                'made_to_order' => 'bool default 1', // виробник: можна замовити без наявності
                'low_stock_threshold' => 'int null', // ≤ цього — показуємо "закінчується" замість числа
                'image' => 'str null',
                'created_at' => 'ts', 'updated_at' => 'ts',
            ],
            'product_variants' => [
                'id' => 'id', 'product_id' => 'int', 'name' => 'str',
                'price' => 'num null', 'sku' => 'str null', 'sort' => 'int default 0', 'active' => 'bool default 1',
            ],
            'product_images' => [
                'id' => 'id', 'product_id' => 'int', 'path' => 'str',
                'width' => 'int default 0', 'height' => 'int default 0', 'bytes' => 'int default 0', 'sort' => 'int default 0',
            ],
            'product_attrs' => [
                'id' => 'id', 'product_id' => 'int', 'name' => 'str', 'value' => 'str', 'filterable' => 'bool default 0', 'sort' => 'int default 0',
                'attribute_id' => 'int null', 'value_id' => 'int null',
            ],
            // Словник характеристик: спільний для всіх товарів
            'attributes' => [
                'id' => 'id', 'name' => 'str', 'slug' => 'str unique', 'unit' => 'str null',
                'type' => "str default 'select'", // select|text|number|color
                'filterable' => 'bool default 1', 'sort' => 'int default 0', 'active' => 'bool default 1',
            ],
            'attribute_values' => [
                'id' => 'id', 'attribute_id' => 'int', 'value' => 'str', 'color' => 'str null', 'sort' => 'int default 0',
            ],
            // Для яких категорій пропонувати характеристику (немає рядків = для всіх)
            'attribute_categories' => [
                'id' => 'id', 'attribute_id' => 'int', 'category_id' => 'int',
            ],
            // Варіант = набір значень характеристик (Розмір: M + Колір: Червоний)
            'variant_options' => [
                'id' => 'id', 'variant_id' => 'int', 'attribute_id' => 'int', 'value_id' => 'int null', 'value' => 'str',
            ],
            'store_prices' => [
                'id' => 'id', 'product_id' => 'int', 'variant_id' => 'int null', 'store_id' => 'int', 'price' => 'num',
            ],
            'store_stock' => [
                'id' => 'id', 'product_id' => 'int', 'variant_id' => 'int null', 'store_id' => 'int', 'qty' => 'int default 0',
            ],
            'promotions' => [
                'id' => 'id', 'title' => 'str', 'percent' => 'num',
                'store_id' => 'int null', 'category_id' => 'int null', 'product_id' => 'int null',
                'starts_at' => 'str null', 'ends_at' => 'str null', 'active' => 'bool default 1',
            ],
            'promo_codes' => [
                'id' => 'id', 'code' => 'str unique', 'percent' => 'num', 'active' => 'bool default 1', 'expires_at' => 'str null',
            ],
            // Головне замовлення: parent_id IS NULL. Підзамовлення магазину: parent_id = головне,
            // store_id = магазин-виконавець, seq = його порядковий номер у замовленні.
            // Позиції (order_items) лежать лише в підзамовленнях — див. OrderFlow.
            'orders' => [
                'id' => 'id', 'number' => 'str unique', 'token' => 'str null', 'user_id' => 'int null',
                'parent_id' => 'int null', 'seq' => 'int default 0',
                'name' => 'str', 'phone' => 'str', 'email' => 'str null',
                'delivery' => 'str', 'city' => 'str null', 'np_office' => 'str null',
                'address' => 'str null', 'comment' => 'text null',
                'store_id' => 'int null',
                'status' => "str default 'new'", // new|processing|shipped|done|canceled
                // хто взяв підзамовлення в роботу (мітка, не блокування)
                'assigned_user_id' => 'int null', 'assigned_at' => 'str null',
                'promo_code' => 'str null',
                'subtotal' => 'num default 0', 'discount' => 'num default 0', 'total' => 'num default 0',
                'created_at' => 'ts',
            ],
            'order_items' => [
                'id' => 'id', 'order_id' => 'int', 'product_id' => 'int null', 'variant_id' => 'int null',
                'title' => 'str', 'variant_name' => 'str null', 'price' => 'num', 'qty' => 'int', 'sum' => 'num',
            ],
            // Історія замовлення: розділення, зміни статусів, передачі позицій між магазинами.
            // parent_id — завжди головне замовлення, щоб уся стрічка читалась одним запитом.
            'order_events' => [
                'id' => 'id', 'parent_id' => 'int', 'order_id' => 'int null', 'user_id' => 'int null',
                'type' => 'str', // created|status|transfer|note
                'role' => 'str null', // роль, у якій діяли
                'message' => 'text null', 'created_at' => 'ts',
            ],
            'diplomas' => [
                'id' => 'id', 'number' => 'str unique', 'student' => 'str', 'course' => 'str null',
                'issued_at' => 'str null', 'active' => 'bool default 1',
            ],
            'posts' => [
                'id' => 'id', 'user_id' => 'int null', 'title' => 'str', 'slug' => 'str unique',
                'excerpt' => 'text null', 'body' => 'text null', 'image' => 'str null',
                'published' => 'bool default 0', 'created_at' => 'ts',
            ],
            'settings' => [ 'key' => 'str primary', 'value' => 'text null' ],
            'content_blocks' => [
                'key' => 'str primary', 'title' => 'str null', 'body' => 'text null', 'image' => 'str null',
            ],
            // Особистий вибір користувача: які події й якими каналами він хоче отримувати.
            // Рядок зʼявляється лише коли людина щось ВИМКНУЛА — відсутність рядка означає
            // згоду. Так налаштування адміна лишається стелею, а це — фільтром під нею.
            'user_notify_prefs' => [
                'id' => 'id', 'user_id' => 'int', 'event' => 'str', 'channel' => 'str',
                'enabled' => 'bool default 1',
            ],
            'notification_rules' => [
                'id' => 'id', 'event' => 'str', 'channel' => 'str', // telegram|push|email
                'enabled' => 'bool default 1',
                'recipients' => "str default 'admins'", // admins|sellers|admins_sellers
                'template' => 'text null',
            ],
            'push_subscriptions' => [
                'id' => 'id', 'user_id' => 'int', 'endpoint' => 'text', 'p256dh' => 'str', 'auth' => 'str', 'created_at' => 'ts',
            ],
            'auth_tokens' => [
                'id' => 'id', 'user_id' => 'int null',
                'purpose' => 'str', // tg_link|tg_login|viber_link|viber_login|phone_code
                'token' => 'str unique', 'code' => 'str null', 'phone' => 'str null',
                'chat_id' => 'str null', 'confirmed_user_id' => 'int null',
                'expires_at' => 'str', 'used' => 'bool default 0', 'created_at' => 'ts',
            ],
            // Підписники розсилки: email гостя або зареєстрованого користувача.
            // token — для відписки одним кліком з листа, без входу на сайт.
            'subscribers' => [
                'id' => 'id', 'email' => 'str unique', 'name' => 'str null', 'user_id' => 'int null',
                'source' => "str default 'checkout'", // checkout|profile
                'active' => 'bool default 1', 'token' => 'str unique',
                'created_at' => 'ts', 'unsubscribed_at' => 'str null',
            ],
            // Лічильник звернень для обмеження частоти (боти, перебір, спам замовленнями)
            'rate_hits' => [
                'id' => 'id', 'action' => 'str', 'ident' => 'str', 'created_at' => 'ts',
            ],
            'migrations_log' => [ 'id' => 'id', 'name' => 'str', 'ran_at' => 'ts' ],
        ];
    }

    public static function createAll(): void
    {
        $driver = DB::driver();
        foreach (self::tables() as $table => $cols) {
            $sql = self::createSql($table, $cols, $driver);
            DB::pdo()->exec($sql);
        }
        // індекси
        $idx = [
            'products' => ['category_id', 'active', 'featured'],
            'product_variants' => ['product_id'],
            'product_images' => ['product_id'],
            'product_attrs' => ['product_id', 'attribute_id'],
            'attribute_values' => ['attribute_id'],
            'attribute_categories' => ['attribute_id', 'category_id'],
            'variant_options' => ['variant_id', 'attribute_id'],
            'store_prices' => ['product_id', 'store_id', 'variant_id'],
            'store_stock' => ['product_id', 'store_id', 'variant_id'],
            'orders' => ['status', 'store_id', 'user_id', 'token', 'parent_id'],
            'order_events' => ['parent_id'],
            'rate_hits' => ['action', 'ident', 'created_at'],
            'subscribers' => ['token'],
            'order_items' => ['order_id'],
            'seller_stores' => ['user_id', 'store_id'],
            'user_roles' => ['user_id', 'role'],
            'user_notify_prefs' => ['user_id'],
        ];
        foreach ($idx as $table => $columns) {
            foreach ($columns as $col) {
                $name = "idx_{$table}_{$col}";
                try { DB::pdo()->exec("CREATE INDEX $name ON $table ($col)"); }
                catch (Throwable $e) { /* вже існує */ }
            }
        }
    }

    private static function createSql(string $table, array $cols, string $driver): string
    {
        $defs = [];
        foreach ($cols as $name => $spec) {
            $defs[] = self::colSql($name, $spec, $driver);
        }
        $q = $driver === 'sqlite' ? '"' : '`';
        $sql = "CREATE TABLE IF NOT EXISTS {$q}{$table}{$q} (" . implode(', ', $defs) . ")";
        if ($driver === 'mysql') $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        return $sql;
    }

    private static function colSql(string $name, string $spec, string $driver): string
    {
        $q = $driver === 'sqlite' ? '"' : '`';
        $parts = preg_split('/\s+/', trim($spec));
        $type = array_shift($parts);
        $rest = implode(' ', $parts);
        $null = str_contains($rest, 'null') && !str_contains($rest, 'not null');
        $unique = str_contains($rest, 'unique');
        $primary = str_contains($rest, 'primary');
        $default = null;
        if (preg_match("/default\s+('([^']*)'|\S+)/", $rest, $m)) $default = $m[1];

        if ($type === 'id') {
            return $driver === 'sqlite'
                ? "{$q}{$name}{$q} INTEGER PRIMARY KEY AUTOINCREMENT"
                : "{$q}{$name}{$q} BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
        }
        $sqlType = match (true) {
            $type === 'int' => $driver === 'sqlite' ? 'INTEGER' : 'BIGINT',
            $type === 'num' => $driver === 'sqlite' ? 'NUMERIC' : 'DECIMAL(12,2)',
            $type === 'str' => $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(255)',
            $type === 'text' => $driver === 'sqlite' ? 'TEXT' : 'MEDIUMTEXT',
            $type === 'bool' => $driver === 'sqlite' ? 'INTEGER' : 'TINYINT(1)',
            $type === 'ts' => $driver === 'sqlite' ? 'TEXT' : 'DATETIME',
            default => 'TEXT',
        };
        $sql = "{$q}{$name}{$q} $sqlType";
        if ($primary) $sql .= ' PRIMARY KEY';
        elseif (!$null) $sql .= ' NOT NULL';
        if ($default !== null) $sql .= " DEFAULT $default";
        if ($unique) $sql .= ' UNIQUE';
        return $sql;
    }
}
