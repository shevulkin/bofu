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
            foreach ($f['attr'] as $name => $value) {
                if ($value === '') continue;
                $where[] = 'EXISTS (SELECT 1 FROM product_attrs pa WHERE pa.product_id = p.id AND pa.name = ? AND pa.value = ?)';
                $params[] = $name; $params[] = $value;
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
        return DB::all('SELECT * FROM product_attrs WHERE product_id = ? ORDER BY sort, id', [$productId]);
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
        return $img['path'] ?? 'img/honey-jar.png';
    }

    /** Значення фільтрованих атрибутів для категорії */
    public static function filterableAttrs(?int $categoryId): array
    {
        $sql = 'SELECT pa.name, pa.value FROM product_attrs pa
                JOIN products p ON p.id = pa.product_id AND p.active = 1
                WHERE pa.filterable = 1';
        $params = [];
        if ($categoryId) { $sql .= ' AND p.category_id = ?'; $params[] = $categoryId; }
        $sql .= ' GROUP BY pa.name, pa.value ORDER BY pa.name, pa.value';
        $out = [];
        foreach (DB::all($sql, $params) as $r) $out[$r['name']][] = $r['value'];
        return $out;
    }
}
