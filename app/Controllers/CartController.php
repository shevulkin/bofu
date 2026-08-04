<?php
declare(strict_types=1);

namespace Controllers;

use View, Cart, Csrf, DB, Catalog;

class CartController
{
    public static function index(): never
    {
        View::show('cart/index', [
            'rows' => Cart::detailed(),
            'totals' => Cart::total(),
            'stores' => Catalog::stores(),
            'page_title' => 'Кошик — ' . cfg('app_name'),
        ]);
    }

    public static function add(): never
    {
        Csrf::verify();
        $pid = (int)($_POST['product_id'] ?? 0);
        $vid = (int)($_POST['variant_id'] ?? 0) ?: null;
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $p = DB::row('SELECT id, slug FROM products WHERE id = ? AND active = 1', [$pid]);

        // Коли варіант є з чого обрати — обирає покупець. Мовчки підставити перший
        // означає продати не той розмір, тож відправляємо на картку товару.
        if ($p && !self::variantChosen($pid, $vid)) {
            $to = '/product/' . $p['slug'];
            if (($_POST['ajax'] ?? '') === '1') json_response(['ok' => false, 'redirect' => url($to)]);
            flash('error', 'Оберіть варіант товару.');
            redirect($to);
        }

        if ($p) Cart::add($pid, $vid, $qty);
        if (($_POST['ajax'] ?? '') === '1') json_response(['ok' => true, 'count' => Cart::count()]);
        flash('success', 'Додано до кошика');
        redirect(safe_back($_POST['back'] ?? null, '/cart'));
    }

    /** Варіант обовʼязковий лише там, де вибір справді є: один варіант — це не вибір */
    private static function variantChosen(int $productId, ?int $variantId): bool
    {
        $variants = Catalog::variants($productId);
        if (count($variants) < 2) return true;
        foreach ($variants as $v) if ((int)$v['id'] === $variantId) return true;
        return false;
    }

    public static function update(): never
    {
        Csrf::verify();
        $action = $_POST['action'] ?? '';
        $key = $_POST['key'] ?? '';
        if ($action === 'remove') Cart::remove($key);
        elseif ($action === 'inc') Cart::setQty($key, (int)($_POST['qty'] ?? 1) + 1);
        elseif ($action === 'dec') Cart::setQty($key, (int)($_POST['qty'] ?? 1) - 1);
        elseif ($action === 'set') Cart::setQty($key, (int)($_POST['qty'] ?? 1));
        redirect('/cart');
    }
}
