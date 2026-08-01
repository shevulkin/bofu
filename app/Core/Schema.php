<?php
declare(strict_types=1);

/**
 * Портативна схема БД: генерує SQL для MySQL та SQLite з одного опису.
 * Типи: id, int, num(10,2), str(255), text, bool, ts
 */
class Schema
{
    public const VERSION = 3;

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
            DB::query("UPDATE users SET phone = '+38067000000' || id WHERE email LIKE '%@bofu.local' AND (phone IS NULL OR phone = '')");
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
        Settings::set('schema_version', (string)self::VERSION);
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
            'orders' => [
                'id' => 'id', 'number' => 'str unique', 'user_id' => 'int null',
                'name' => 'str', 'phone' => 'str', 'email' => 'str null',
                'delivery' => 'str', 'city' => 'str null', 'np_office' => 'str null',
                'address' => 'str null', 'comment' => 'text null',
                'store_id' => 'int null',
                'status' => "str default 'new'", // new|processing|shipped|done|canceled
                'promo_code' => 'str null',
                'subtotal' => 'num default 0', 'discount' => 'num default 0', 'total' => 'num default 0',
                'created_at' => 'ts',
            ],
            'order_items' => [
                'id' => 'id', 'order_id' => 'int', 'product_id' => 'int null', 'variant_id' => 'int null',
                'title' => 'str', 'variant_name' => 'str null', 'price' => 'num', 'qty' => 'int', 'sum' => 'num',
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
            'store_prices' => ['product_id', 'store_id'],
            'store_stock' => ['product_id', 'store_id'],
            'orders' => ['status', 'store_id', 'user_id'],
            'order_items' => ['order_id'],
            'seller_stores' => ['user_id', 'store_id'],
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
