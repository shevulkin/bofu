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

    /**
     * Скільки цієї позиції можна покласти в кошик. null — без обмежень:
     * «виготовимо під замовлення» від залишку не залежить.
     *
     * Рахуємо тим самим OrderFlow::sellable(), що вирішує на оформленні, —
     * інакше кошик приймав би те, що потім відхиляє підтвердження замовлення.
     */
    public static function limit(int $productId, ?int $variantId): ?int
    {
        $p = DB::row('SELECT made_to_order FROM products WHERE id = ? AND active = 1', [$productId]);
        if (!$p) return 0;
        if (!empty($p['made_to_order'])) return null;
        return min(self::MAX_QTY, OrderFlow::sellable($productId, $variantId));
    }

    /**
     * Кладе позицію в кошик і повертає, скільки саме поклали: 0 означає
     * «нічого немає», менше за $qty — «стільки й лишилось». Це відповідь
     * покупцеві, тож приймати рішення про повідомлення має він, не кошик.
     */
    public static function add(int $productId, ?int $variantId = null, int $qty = 1): int
    {
        $variantId = self::resolveVariant($productId, $variantId);
        $key = self::key($productId, $variantId);
        $cart = self::items();
        $was = (int)($cart[$key]['qty'] ?? 0);
        $want = min(self::MAX_QTY, $was + max(1, $qty));

        // Порожній склад — не привід тримати позицію в кошику: інакше людина
        // заповнить усю форму й дізнається про відмову на останньому кліку.
        $limit = self::limit($productId, $variantId);
        if ($limit !== null) $want = min($want, $limit);
        if ($want <= $was) return 0;

        if ($was) $cart[$key]['qty'] = $want;
        else $cart[$key] = ['product_id' => $productId, 'variant_id' => $variantId, 'qty' => $want];
        $_SESSION['cart'] = $cart;
        return $want - $was;
    }

    public static function setQty(string $key, int $qty): void
    {
        if (!isset($_SESSION['cart'][$key])) return;
        $item = $_SESSION['cart'][$key];
        $limit = self::limit((int)$item['product_id'], isset($item['variant_id']) ? (int)$item['variant_id'] : null);
        $qty = min(self::MAX_QTY, $qty);
        if ($limit !== null) $qty = min($qty, $limit);
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
                // наявність саме цього варіанта по магазинах: [store_id => qty]
                'stock' => Catalog::stockByStore((int)$p['id'], $v ? (int)$v['id'] : null),
            ];
        }
        return $rows;
    }

    /**
     * Підсумки. Знижку рахуємо по кожній позиції окремо, а не від загальної суми:
     * покупець бачить перераховану ціну кожного товару, і ці числа мають скластися
     * рівно в «до сплати» — інакше на копійках форма не сходиться сама з собою.
     */
    public static function total(?int $storeId = null, ?array $promoCode = null): array
    {
        $subtotal = 0.0; $discount = 0.0;
        foreach (self::detailed($storeId) as $r) {
            $sum = (float)($r['sum'] ?? 0);
            $subtotal += $sum;
            // знижка позиції враховує ту, що на ній уже є: код може не
            // сумуватися з акцією або мати стелю сумарного відсотка
            $discount += Promo::cut($sum, $promoCode, Promo::ownPercent($r));
        }
        return ['subtotal' => $subtotal, 'discount' => $discount, 'total' => max(0, $subtotal - $discount)];
    }
}
