<?php
declare(strict_types=1);

class Cart
{
    /** Стеля на позицію: захист від сміттєвих значень у POST і від обнулення залишків ботом */
    public const MAX_QTY = 999;

    private static bool $normalized = false;

    public static function items(): array { self::normalize(); return $_SESSION['cart'] ?? []; }

    private static function key(int $productId, ?int $variantId): string
    { return $productId . ':' . ($variantId ?? 0); }

    /**
     * Варіант у рядку кошика обовʼязковий, коли він є в товару:
     * саме від нього залежать ціна й наявність по магазинах.
     */
    private static function resolveVariant(int $productId, ?int $variantId): ?int
    {
        if ($variantId !== null && DB::row('SELECT 1 FROM product_variants WHERE id = ? AND product_id = ? AND active = 1',
                [$variantId, $productId])) return $variantId;
        $first = DB::val('SELECT id FROM product_variants WHERE product_id = ? AND active = 1 ORDER BY sort, id LIMIT 1', [$productId]);
        return $first !== null ? (int)$first : null;
    }

    /** Старі рядки без варіанта (або з вимкненим) переводимо на актуальний варіант */
    private static function normalize(): void
    {
        if (self::$normalized) return;
        self::$normalized = true;
        $cart = $_SESSION['cart'] ?? [];
        if (!$cart) return;
        $out = []; $changed = false;
        foreach ($cart as $key => $item) {
            $pid = (int)$item['product_id'];
            $vid = self::resolveVariant($pid, isset($item['variant_id']) ? (int)$item['variant_id'] : null);
            if ($vid !== ($item['variant_id'] ?? null)) { $item['variant_id'] = $vid; $changed = true; }
            $k = self::key($pid, $vid);
            if ($k !== $key) $changed = true;
            if (isset($out[$k])) $out[$k]['qty'] += $item['qty'];
            else $out[$k] = $item;
        }
        if ($changed) $_SESSION['cart'] = $out;
    }

    public static function add(int $productId, ?int $variantId = null, int $qty = 1): void
    {
        $variantId = self::resolveVariant($productId, $variantId);
        $key = self::key($productId, $variantId);
        $cart = self::items();
        if (isset($cart[$key])) $cart[$key]['qty'] = min(self::MAX_QTY, $cart[$key]['qty'] + $qty);
        else $cart[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'qty' => min(self::MAX_QTY, max(1, $qty))];
        $_SESSION['cart'] = $cart;
    }

    public static function setQty(string $key, int $qty): void
    {
        if (!isset($_SESSION['cart'][$key])) return;
        if ($qty <= 0) unset($_SESSION['cart'][$key]);
        else $_SESSION['cart'][$key]['qty'] = min(self::MAX_QTY, $qty);
    }

    public static function remove(string $key): void { unset($_SESSION['cart'][$key]); }
    public static function clear(): void { unset($_SESSION['cart']); }

    public static function count(): int
    {
        return array_sum(array_map(fn($i) => $i['qty'], self::items()));
    }

    /** Розгорнуті рядки кошика з цінами */
    public static function detailed(?int $storeId = null): array
    {
        $rows = [];
        foreach (self::items() as $key => $item) {
            $p = DB::row('SELECT * FROM products WHERE id = ? AND active = 1', [$item['product_id']]);
            if (!$p) continue;
            $v = $item['variant_id'] ? DB::row('SELECT * FROM product_variants WHERE id = ?', [$item['variant_id']]) : null;
            [$price, $old] = Catalog::price($p, $v, $storeId);
            $rows[] = [
                'key' => $key, 'product' => $p, 'variant' => $v, 'qty' => $item['qty'],
                'price' => $price, 'old' => $old,
                'sum' => $price !== null ? $price * $item['qty'] : null,
                'photo' => Catalog::photo($p),
                // наявність саме цього варіанта по магазинах: [store_id => qty]
                'stock' => Catalog::stockByStore((int)$p['id'], $v ? (int)$v['id'] : null),
            ];
        }
        return $rows;
    }

    public static function total(?int $storeId = null, ?array $promoCode = null): array
    {
        $subtotal = 0.0;
        foreach (self::detailed($storeId) as $r) $subtotal += $r['sum'] ?? 0;
        $discount = 0.0;
        if ($promoCode) $discount = round($subtotal * (float)$promoCode['percent'] / 100, 2);
        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount)];
    }
}
