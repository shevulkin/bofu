<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Cart, Csrf, Auth, Catalog, Notify, Settings;

class Checkout
{
    public static function form(): never
    {
        if (!Cart::items()) redirect('/cart');
        View::show('cart/checkout', [
            'rows' => Cart::detailed(),
            'totals' => Cart::total(null, self::promo()),
            'stores' => Catalog::stores(),
            'promo' => self::promo(),
            'np_enabled' => Settings::get('np_api_key') !== null && Settings::get('np_api_key') !== '',
            'page_title' => 'Оформлення замовлення — ' . cfg('app_name'),
        ]);
    }

    private static function promo(): ?array
    {
        $code = trim($_POST['promo_code'] ?? $_SESSION['promo_code'] ?? '');
        if ($code === '') return null;
        $row = DB::row('SELECT * FROM promo_codes WHERE code = ? AND active = 1', [strtoupper($code)]);
        if ($row && ($row['expires_at'] === null || $row['expires_at'] === '' || $row['expires_at'] >= date('Y-m-d'))) {
            $_SESSION['promo_code'] = strtoupper($code);
            return $row;
        }
        return null;
    }

    public static function submit(): never
    {
        Csrf::verify();
        if (!Cart::items()) redirect('/cart');

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $delivery = $_POST['delivery'] ?? 'np';
        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Вкажіть ім\'я отримувача';
        if (!preg_match('/\d{6,}/', preg_replace('/\D/', '', $phone) ?? '')) $errors[] = 'Вкажіть коректний телефон';
        if (!in_array($delivery, ['np', 'pickup', 'other'], true)) $delivery = 'other';
        $storeId = (int)($_POST['store_id'] ?? 0) ?: null;
        if ($delivery === 'pickup' && !$storeId) $errors[] = 'Оберіть магазин для самовивозу';

        if ($errors) {
            flash('error', implode('. ', $errors));
            redirect('/checkout');
        }

        $promo = self::promo();
        $totals = Cart::total($delivery === 'pickup' ? $storeId : null, $promo);
        $number = 'BOFU-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        $orderId = DB::tx(function () use ($number, $name, $phone, $delivery, $storeId, $totals, $promo) {
            $orderId = DB::insert('orders', [
                'number' => $number, 'user_id' => Auth::id(),
                'name' => $name, 'phone' => $phone, 'email' => trim($_POST['email'] ?? '') ?: null,
                'delivery' => $delivery,
                'city' => trim($_POST['city'] ?? '') ?: null,
                'np_office' => trim($_POST['np_office'] ?? '') ?: null,
                'address' => trim($_POST['address'] ?? '') ?: null,
                'comment' => trim($_POST['comment'] ?? '') ?: null,
                'store_id' => $storeId,
                'status' => 'new', 'promo_code' => $promo['code'] ?? null,
                'subtotal' => $totals['subtotal'], 'discount' => $totals['discount'], 'total' => $totals['total'],
                'created_at' => now(),
            ]);
            foreach (Cart::detailed($delivery === 'pickup' ? $storeId : null) as $r) {
                DB::insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => $r['product']['id'], 'variant_id' => $r['variant']['id'] ?? null,
                    'title' => $r['product']['name'], 'variant_name' => $r['variant']['name'] ?? null,
                    'price' => $r['price'] ?? 0, 'qty' => $r['qty'], 'sum' => $r['sum'] ?? 0,
                ]);
                // списання залишків з магазину (якщо відомий) або з першого, де є
                $sid = $storeId;
                if (!$sid) {
                    $row = DB::row('SELECT store_id FROM store_stock WHERE product_id = ? AND qty > 0 ORDER BY qty DESC LIMIT 1', [$r['product']['id']]);
                    $sid = $row['store_id'] ?? null;
                }
                if ($sid) {
                    $fn = DB::driver() === 'sqlite' ? 'MAX' : 'GREATEST';
                    DB::query("UPDATE store_stock SET qty = $fn(0, qty - ?) WHERE product_id = ? AND store_id = ? AND variant_id IS NULL",
                        [$r['qty'], $r['product']['id'], $sid]);
                }
            }
            return $orderId;
        });

        $storeName = $storeId ? (DB::val('SELECT name FROM stores WHERE id = ?', [$storeId]) ?? '—') : 'Онлайн';
        Notify::fire('order_new', [
            'number' => $number, 'name' => $name, 'phone' => $phone,
            'delivery' => ['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'][$delivery],
            'total' => number_format($totals['total'], 2, '.', ' '),
            'store' => $storeName,
        ], $storeId);

        Cart::clear();
        unset($_SESSION['promo_code']);
        redirect('/order/success/' . $number);
    }

    public static function success(string $number): never
    {
        $order = DB::row('SELECT * FROM orders WHERE number = ?', [$number]);
        if (!$order) redirect('/');
        View::show('cart/success', ['order' => $order, 'page_title' => 'Замовлення прийнято — ' . cfg('app_name')]);
    }

    public static function myOrders(): never
    {
        if (!Auth::check()) { flash('error', 'Увійдіть, щоб бачити свої замовлення.'); redirect('/'); }
        $orders = DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC', [Auth::id()]);
        $items = [];
        foreach ($orders as $o) $items[$o['id']] = DB::all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
        View::show('account/orders', ['orders' => $orders, 'items' => $items, 'page_title' => 'Мої замовлення — ' . cfg('app_name')]);
    }
}
