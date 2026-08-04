<?php
declare(strict_types=1);

/** Логіка каталогу: ціни, акції, наявність, фільтри */
class Catalog
{
    /** Активні акції (кешовано на запит) */
    public static function activePromotions(): array
    {
        static $promos = null;
        if ($promos === null) {
            $today = date('Y-m-d');
            $promos = DB::all(
                "SELECT * FROM promotions WHERE active = 1
                 AND (starts_at IS NULL OR starts_at = '' OR starts_at <= ?)
                 AND (ends_at IS NULL OR ends_at = '' OR ends_at >= ?)", [$today, $today]);
        }
        return $promos;
    }

    /** Найбільша знижка (%) для товару в контексті магазину (null = загальний сайт) */
    public static function promoPercent(array $product, ?int $storeId = null): float
    {
        $best = 0.0;
        foreach (self::activePromotions() as $p) {
            if ($p['product_id'] && (int)$p['product_id'] !== (int)$product['id']) continue;
            if ($p['category_id'] && (int)$p['category_id'] !== (int)$product['category_id']) continue;
            if ($p['store_id'] && $storeId !== null && (int)$p['store_id'] !== $storeId) continue;
            if ($p['store_id'] && $storeId === null) continue; // акція конкретного магазину не діє на сайт загалом
            $best = max($best, (float)$p['percent']);
        }
        return $best;
    }

    /**
     * Чим банер обманює покупця — або null, якщо обіцянка збігається з цінами.
     *
     * Відсоток у банері нічого не рахує: у layouts/main.php він просто
     * дописується в текст смужки. Тому «−15%» угорі сайту й реальні ціни легко
     * розходяться — акцію забули створити, звузили до однієї категорії або вона
     * просто скінчилась, а смужка й далі обіцяє знижку.
     *
     * Дивимось рівно те, що бачить звичайний відвідувач каталогу: акції,
     * прив'язані до магазину, у загальний каталог не потрапляють (promoPercent),
     * тож обіцянку банера вони не виконують.
     *
     * $promos дозволяє передати готовий список замість запиту в базу — інакше
     * кеш activePromotions() не дав би перевірити більше одного розкладу за запуск.
     */
    public static function bannerWarning(?array $promos = null): ?string
    {
        if (!Settings::bool('sale_banner_active')) return null;

        $pct = (float)Settings::get('sale_banner_percent', '0');
        // Порожнє поле Settings::get віддає як '0', і банер малює «−0%» —
        // це та сама обіцянка ні про що, лише помітити її ще важче
        if ($pct <= 0) return 'Банер увімкнено, але відсоток не заданий — покупець бачить «−0%».';

        $whole = 0.0;  // акція на весь каталог
        $part  = 0.0;  // лише на категорію чи окремий товар
        foreach ($promos ?? self::activePromotions() as $p) {
            if ($p['store_id']) continue;
            $v = (float)$p['percent'];
            if ($p['category_id'] || $p['product_id']) $part = max($part, $v);
            else $whole = max($whole, $v);
        }
        if ($whole >= $pct) return null;

        $say = static fn(float $v): string => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        if ($part >= $pct) {
            return 'Банер обіцяє −' . $say($pct) . '% на всьому сайті, але така знижка діє лише на частину каталогу.';
        }
        $best = max($whole, $part);
        if ($best <= 0) {
            return 'Банер обіцяє −' . $say($pct) . '%, але жодної акції зараз немає — покупець бачить оголошення й повну ціну.';
        }
        return 'Банер обіцяє −' . $say($pct) . '%, а найбільша знижка зараз — ' . $say($best) . '%.';
    }

    /** Базова ціна з урахуванням перевизначення по магазину та варіанта */
    public static function rawPrice(array $product, ?array $variant = null, ?int $storeId = null): ?float
    {
        // 1) перевизначення по магазину (для варіанта чи товару)
        if ($storeId !== null) {
            $vid = $variant['id'] ?? null;
            $row = $vid
                ? DB::row('SELECT price FROM store_prices WHERE product_id = ? AND store_id = ? AND variant_id = ?', [$product['id'], $storeId, $vid])
                : DB::row('SELECT price FROM store_prices WHERE product_id = ? AND store_id = ? AND variant_id IS NULL', [$product['id'], $storeId]);
            if ($row && $row['price'] !== null) return (float)$row['price'];
        }
        // 2) ціна варіанта
        if ($variant && $variant['price'] !== null && $variant['price'] !== '') return (float)$variant['price'];
        // 3) базова ціна товару
        return ($product['base_price'] !== null && $product['base_price'] !== '') ? (float)$product['base_price'] : null;
    }

    /** Кінцева ціна з урахуванням акцій. Повертає [ціна, стара_ціна|null] */
    public static function price(array $product, ?array $variant = null, ?int $storeId = null): array
    {
        $raw = self::rawPrice($product, $variant, $storeId);
        if ($raw === null) return [null, null];
        $pct = self::promoPercent($product, $storeId);
        if ($pct > 0) return [round($raw * (1 - $pct / 100), 2), $raw];
        $old = ($product['old_price'] !== null && $product['old_price'] !== '' && (float)$product['old_price'] > $raw)
            ? (float)$product['old_price'] : null;
        return [$raw, $old];
    }

    /** Довідник брендів: усі або лише активні */
    public static function brands(bool $activeOnly = false): array
    {
        return DB::all('SELECT * FROM brands' . ($activeOnly ? ' WHERE active = 1' : '')
            . ' ORDER BY own DESC, sort, name');
    }

    /** Бренд товару як рядок довідника (кешовано на запит) */
    public static function brand(array $product): ?array
    {
        static $cache = [];
        $id = (int)($product['brand_id'] ?? 0);
        if (!$id) return null;
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = DB::row('SELECT * FROM brands WHERE id = ?', [$id]) ?: null;
        }
        return $cache[$id];
    }

    public static function brandName(array $product): string
    {
        return (string)(self::brand($product)['name'] ?? '');
    }

    /**
     * Назва бренду самого магазину — потрібна лише там, де рядок довідника ще
     * не заведений: перша установка й міграція старого текстового поля.
     * Далі «наше чи ні» вирішує прапорець own у самому бренді.
     */
    public static function ownBrandName(): string
    {
        $set = trim((string)Settings::get('brand_own', ''));
        return $set !== '' ? $set : (string)cfg('app_name');
    }

    /** Чи це наш власний товар — саме за брендом, а не за категорією чи наявністю */
    public static function isOwnBrand(array $product): bool
    {
        return !empty(self::brand($product)['own']);
    }

    /**
     * Напис для товару, якого немає на складі, але який можна замовити.
     *
     * «Ми виробник» — твердження про походження товару, і казати його про
     * чужий воскопрес чи пошив у сторонній майстерні не можна. Для решти
     * важливо інше: товар усе одно буде, просто доведеться зачекати.
     */
    public static function madeToOrderNote(array $product): string
    {
        return self::isOwnBrand($product)
            ? 'Виготовимо під замовлення — ми виробник 🍯'
            : 'Виготовляється на замовлення — привеземо для вас';
    }

    /** Те саме коротко — там, де напис доклеюється до іншого рядка */
    public static function madeToOrderShort(array $product): string
    {
        return self::isOwnBrand($product)
            ? 'виготовимо під замовлення'
            : 'виготовляється на замовлення';
    }

    /** Чи має товар активні варіанти (кешовано на запит) */
    public static function hasVariants(int $productId): bool
    {
        static $cache = [];
        return $cache[$productId] ??= (bool)DB::val(
            'SELECT 1 FROM product_variants WHERE product_id = ? AND active = 1 LIMIT 1', [$productId]);
    }

    /**
     * Залишок по всіх магазинах чи конкретному.
     * Якщо в товару є варіанти — залишок беремо з них (сума по активних),
     * рядки без варіанта для такого товару ігноруємо.
     */
    public static function stock(int $productId, ?int $variantId = null, ?int $storeId = null): int
    {
        $byStore = self::stockByStore($productId, $variantId);
        return $storeId !== null ? ($byStore[$storeId] ?? 0) : array_sum($byStore);
    }

    /** Залишки товару одним запитом: [store_id][variant_id|0] = qty (лише активні варіанти) */
    public static function stockMap(int $productId): array
    {
        $rows = DB::all(
            'SELECT ss.store_id, ss.variant_id, SUM(ss.qty) AS qty
             FROM store_stock ss
             LEFT JOIN product_variants pv ON pv.id = ss.variant_id
             WHERE ss.product_id = ? AND (ss.variant_id IS NULL OR pv.active = 1)
             GROUP BY ss.store_id, ss.variant_id', [$productId]);
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['store_id']][(int)($r['variant_id'] ?? 0)] = (int)$r['qty'];
        }
        return $out;
    }

    /**
     * Залишки по магазинах для конкретної позиції: [store_id => qty].
     * Варіант обовʼязковий для товарів з варіантами — інакше беремо рядки без варіанта.
     */
    public static function stockByStore(int $productId, ?int $variantId = null): array
    {
        $params = [$productId];
        if ($variantId !== null) {
            $sql = 'SELECT store_id, SUM(qty) AS qty FROM store_stock WHERE product_id = ? AND variant_id = ?';
            $params[] = $variantId;
        } elseif (self::hasVariants($productId)) {
            $sql = 'SELECT ss.store_id, SUM(ss.qty) AS qty FROM store_stock ss
                    JOIN product_variants pv ON pv.id = ss.variant_id AND pv.active = 1
                    WHERE ss.product_id = ?';
        } else {
            $sql = 'SELECT store_id, SUM(qty) AS qty FROM store_stock WHERE product_id = ? AND variant_id IS NULL';
        }
        $sql .= ' GROUP BY store_id';
        $out = [];
        foreach (DB::all($sql, $params) as $r) $out[(int)$r['store_id']] = (int)$r['qty'];
        return $out;
    }

    /**
     * Залишки всіх товарів по магазинах одним запитом: [product_id][store_id] = qty.
     * Для товарів з варіантами беруться рядки варіантів, для решти — рядки без варіанта.
     */
    public static function stockTotals(): array
    {
        $rows = DB::all(
            'SELECT ss.product_id, ss.store_id, SUM(ss.qty) AS qty
             FROM store_stock ss
             LEFT JOIN product_variants pv ON pv.id = ss.variant_id
             WHERE (ss.variant_id IS NULL OR pv.active = 1)
               AND (ss.variant_id IS NOT NULL
                    OR NOT EXISTS (SELECT 1 FROM product_variants v
                                   WHERE v.product_id = ss.product_id AND v.active = 1))
             GROUP BY ss.product_id, ss.store_id');
        $out = [];
        foreach ($rows as $r) $out[(int)$r['product_id']][(int)$r['store_id']] = (int)$r['qty'];
        return $out;
    }

    /** SQL-умова «є в наявності в магазині» з урахуванням варіантів (для p.id у зовнішньому запиті) */
    private const IN_STOCK_EXISTS =
        'EXISTS (SELECT 1 FROM store_stock ss
                 LEFT JOIN product_variants pv ON pv.id = ss.variant_id
                 WHERE ss.product_id = p.id AND ss.store_id = ? AND ss.qty > 0
                   AND (ss.variant_id IS NULL OR pv.active = 1)
                   AND (ss.variant_id IS NOT NULL
                        OR NOT EXISTS (SELECT 1 FROM product_variants v
                                       WHERE v.product_id = p.id AND v.active = 1)))';

    public static function categories(): array
    {
        return DB::all('SELECT * FROM categories WHERE active = 1 ORDER BY sort, id');
    }

    public static function stores(): array
    {
        return DB::all('SELECT * FROM stores WHERE active = 1 ORDER BY sort, id');
    }

    /** Пошук/фільтрація товарів */
    public static function search(array $f): array
    {
        $where = ['p.active = 1'];
        $params = [];
        if (!empty($f['category_id'])) { $where[] = 'p.category_id = ?'; $params[] = (int)$f['category_id']; }
        if (!empty($f['q'])) {
            $where[] = '(p.name LIKE ? OR p.short_desc LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (isset($f['min']) && $f['min'] !== '') { $where[] = 'p.base_price >= ?'; $params[] = (float)$f['min']; }
        if (isset($f['max']) && $f['max'] !== '') { $where[] = 'p.base_price <= ?'; $params[] = (float)$f['max']; }
        if (!empty($f['store_id'])) {
            $where[] = self::IN_STOCK_EXISTS;
            $params[] = (int)$f['store_id'];
        }
        if (!empty($f['attr']) && is_array($f['attr'])) {
            foreach ($f['attr'] as $slug => $values) {
                // приймаємо і один рядок (старі посилання), і масив значень
                $values = array_values(array_filter(array_map('strval', (array)$values), fn($v) => $v !== ''));
                if (!$values) continue;
                $in = implode(',', array_fill(0, count($values), '?'));
                // значення може бути як характеристикою товару, так і віссю варіанта
                // ключем може бути slug або назва — старі збережені посилання теж працюють
                $where[] = "(EXISTS (SELECT 1 FROM product_attrs pa JOIN attributes a ON a.id = pa.attribute_id
                                     WHERE pa.product_id = p.id AND (a.slug = ? OR a.name = ?) AND pa.value IN ($in))
                         OR EXISTS (SELECT 1 FROM variant_options vo
                                     JOIN product_variants pv ON pv.id = vo.variant_id AND pv.active = 1
                                     JOIN attributes a2 ON a2.id = vo.attribute_id
                                     WHERE pv.product_id = p.id AND (a2.slug = ? OR a2.name = ?) AND vo.value IN ($in)))";
                $params[] = (string)$slug; $params[] = (string)$slug;
                foreach ($values as $v) $params[] = $v;
                $params[] = (string)$slug; $params[] = (string)$slug;
                foreach ($values as $v) $params[] = $v;
            }
        }
        $order = match ($f['sort'] ?? '') {
            'price_asc' => 'p.base_price IS NULL, p.base_price ASC',
            'price_desc' => 'p.base_price IS NULL, p.base_price DESC',
            'new' => 'p.id DESC',
            default => 'p.featured DESC, p.id ASC',
        };
        $sql = 'SELECT p.* FROM products p WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
        return DB::all($sql, $params);
    }

    public static function variants(int $productId): array
    {
        return DB::all('SELECT * FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort, id', [$productId]);
    }

    public static function attrs(int $productId): array
    {
        return DB::all(
            'SELECT pa.*, a.slug AS attr_slug, a.unit, a.type AS attr_type, av.color
             FROM product_attrs pa
             LEFT JOIN attributes a ON a.id = pa.attribute_id
             LEFT JOIN attribute_values av ON av.id = pa.value_id
             WHERE pa.product_id = ? ORDER BY pa.sort, pa.id', [$productId]);
    }

    /**
     * Осі варіантів товару: [attribute_id => ['name'=>…, 'type'=>…, 'values'=>[value => ['value','color']]]].
     * Повертає порожньо, якщо варіанти не описані характеристиками (старі текстові варіанти).
     */
    public static function variantAxes(array $variants, array $optionsByVariant): array
    {
        $axes = [];
        foreach ($variants as $v) {
            $opts = $optionsByVariant[(int)$v['id']] ?? [];
            if (!$opts) return []; // хоч один варіант без характеристик — показуємо простим списком
            foreach ($opts as $o) {
                $aid = (int)$o['attribute_id'];
                $axes[$aid]['id'] = $aid;
                $axes[$aid]['name'] = $o['attr_name'];
                $axes[$aid]['type'] = $o['attr_type'];
                $axes[$aid]['values'][$o['value']] = ['value' => $o['value'], 'color' => $o['color'] ?? null];
            }
        }
        return $axes;
    }

    public static function images(int $productId): array
    {
        return DB::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$productId]);
    }

    /** Головне фото товару */
    public static function photo(array $product): string
    {
        if (!empty($product['image'])) return $product['image'];
        $img = DB::row('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id LIMIT 1', [$product['id']]);
        return $img['path'] ?? 'img/honey-jar.webp';
    }

    /**
     * Галерея товару: головне фото першим, далі додаткові в заданому порядку.
     * Без дублів; якщо фото немає взагалі — одна заглушка.
     */
    public static function gallery(array $product): array
    {
        $rows = self::images((int)$product['id']);
        $main = (string)($product['image'] ?? '');
        $out = []; $seen = [];

        if ($main !== '') {
            foreach ($rows as $r) if ($r['path'] === $main) { $out[] = $r; break; }
            // головне фото могли призначити поза списком (банер, галерея сайту)
            if (!$out) $out[] = ['id' => 0, 'path' => $main, 'width' => 0, 'height' => 0, 'bytes' => 0];
            $seen[$main] = true;
        }
        foreach ($rows as $r) {
            if (isset($seen[$r['path']])) continue;
            $seen[$r['path']] = true;
            $out[] = $r;
        }
        if (!$out) $out[] = ['id' => 0, 'path' => 'img/honey-jar.webp', 'width' => 0, 'height' => 0, 'bytes' => 0];
        return $out;
    }

    /**
     * Характеристики для фільтрів: лише ті, що позначені у словнику й реально
     * зустрічаються серед активних товарів категорії. Значення — з кількістю товарів.
     * [['id','name','slug','unit','type','values'=>[['value','color','count'], …]], …]
     */
    public static function filterableAttrs(?int $categoryId): array
    {
        $catSql = $categoryId ? ' AND p.category_id = ?' : '';
        $params = $categoryId ? [$categoryId] : [];

        // значення з характеристик товару
        $rows = DB::all(
            'SELECT a.id, a.name, a.slug, a.unit, a.type, a.sort, pa.value, av.color, COUNT(DISTINCT p.id) AS cnt
             FROM product_attrs pa
             JOIN attributes a ON a.id = pa.attribute_id AND a.filterable = 1 AND a.active = 1
             JOIN products p ON p.id = pa.product_id AND p.active = 1
             LEFT JOIN attribute_values av ON av.id = pa.value_id
             WHERE 1=1' . $catSql . '
             GROUP BY a.id, a.name, a.slug, a.unit, a.type, a.sort, pa.value, av.color', $params);

        // значення, що є осями варіантів (розмір, колір)
        $rows = array_merge($rows, DB::all(
            'SELECT a.id, a.name, a.slug, a.unit, a.type, a.sort, vo.value, av.color, COUNT(DISTINCT p.id) AS cnt
             FROM variant_options vo
             JOIN attributes a ON a.id = vo.attribute_id AND a.filterable = 1 AND a.active = 1
             JOIN product_variants pv ON pv.id = vo.variant_id AND pv.active = 1
             JOIN products p ON p.id = pv.product_id AND p.active = 1
             LEFT JOIN attribute_values av ON av.id = vo.value_id
             WHERE 1=1' . $catSql . '
             GROUP BY a.id, a.name, a.slug, a.unit, a.type, a.sort, vo.value, av.color', $params));

        $out = [];
        foreach ($rows as $r) {
            $slug = $r['slug'];
            $out[$slug] ??= ['id' => (int)$r['id'], 'name' => $r['name'], 'slug' => $slug,
                             'unit' => $r['unit'], 'type' => $r['type'], 'sort' => (int)$r['sort'], 'values' => []];
            $v = $r['value'];
            if (isset($out[$slug]['values'][$v])) $out[$slug]['values'][$v]['count'] += (int)$r['cnt'];
            else $out[$slug]['values'][$v] = ['value' => $v, 'color' => $r['color'], 'count' => (int)$r['cnt']];
        }
        uasort($out, fn($a, $b) => [$a['sort'], $a['name']] <=> [$b['sort'], $b['name']]);
        foreach ($out as &$a) {
            uasort($a['values'], fn($x, $y) => strnatcasecmp($x['value'], $y['value']));
            $a['values'] = array_values($a['values']);
        }
        return $out;
    }
}
