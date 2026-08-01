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

    /** Сумарний залишок по всіх магазинах чи конкретному */
    public static function stock(int $productId, ?int $variantId = null, ?int $storeId = null): int
    {
        $sql = 'SELECT COALESCE(SUM(qty),0) FROM store_stock WHERE product_id = ?';
        $params = [$productId];
        if ($variantId !== null) { $sql .= ' AND variant_id = ?'; $params[] = $variantId; }
        if ($storeId !== null) { $sql .= ' AND store_id = ?'; $params[] = $storeId; }
        return (int)DB::val($sql, $params);
    }

    /** Залишки товару одним запитом: [store_id][variant_id|0] = qty */
    public static function stockMap(int $productId): array
    {
        $out = [];
        foreach (DB::all('SELECT store_id, variant_id, SUM(qty) AS qty FROM store_stock WHERE product_id = ? GROUP BY store_id, variant_id', [$productId]) as $r) {
            $out[(int)$r['store_id']][(int)($r['variant_id'] ?? 0)] = (int)$r['qty'];
        }
        return $out;
    }

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
            $where[] = 'EXISTS (SELECT 1 FROM store_stock ss WHERE ss.product_id = p.id AND ss.store_id = ? AND ss.qty > 0)';
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
