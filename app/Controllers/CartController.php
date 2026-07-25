<?php
declare(strict_types=1);

namespace Controllers;

use View, Cart, Csrf, DB;

class CartController
{
    public static function index(): never
    {
        View::show('cart/index', [
            'rows' => Cart::detailed(),
            'totals' => Cart::total(),
            'page_title' => 'Кошик — ' . cfg('app_name'),
        ]);
    }

    public static function add(): never
    {
        Csrf::verify();
        $pid = (int)($_POST['product_id'] ?? 0);
        $vid = (int)($_POST['variant_id'] ?? 0) ?: null;
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $p = DB::row('SELECT id FROM products WHERE id = ? AND active = 1', [$pid]);
        if ($p) Cart::add($pid, $vid, $qty);
        if (($_POST['ajax'] ?? '') === '1') json_response(['ok' => true, 'count' => Cart::count()]);
        flash('success', 'Додано до кошика');
        redirect($_POST['back'] ?? '/cart');
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
