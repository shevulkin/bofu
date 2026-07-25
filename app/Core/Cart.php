<?php
declare(strict_types=1);

class Cart
{
    public static function items(): array { return $_SESSION['cart'] ?? []; }

    private static function key(int $productId, ?int $variantId): string
    { return $productId . ':' . ($variantId ?? 0); }

    public static function add(int $productId, ?int $variantId = null, int $qty = 1): void
    {
        $key = self::key($productId, $variantId);
        $cart = $_SESSION['cart'] ?? [];
        if (isset($cart[$key])) $cart[$key]['qty'] += $qty;
        else $cart[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'qty' => max(1, $qty)];
        $_SESSION['cart'] = $cart;
    }

    public static function setQty(string $key, int $qty): void
    {
        if (!isset($_SESSION['cart'][$key])) return;
        if ($qty <= 0) unset($_SESSION['cart'][$key]);
        else $_SESSION['cart'][$key]['qty'] = $qty;
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
