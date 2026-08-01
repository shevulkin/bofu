<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Notify, Catalog;

class Orders
{
    public const STATUSES = [
        'new' => 'Нове', 'processing' => 'Обробляється', 'shipped' => 'В дорозі',
        'done' => 'Доставлено', 'canceled' => 'Скасовано',
    ];

    private static function scopeWhere(): array
    {
        if (Auth::isAdmin()) return ['1=1', []];
        $ids = Auth::storeIds();
        if (!$ids) return ['1=0', []];
        $in = implode(',', array_map('intval', $ids));
        return ["(store_id IN ($in) OR store_id IS NULL)", []];
    }

    public static function index(): never
    {
        [$scope, $params] = self::scopeWhere();
        $status = $_GET['status'] ?? 'all';
        $where = $scope;
        if ($status !== 'all' && isset(self::STATUSES[$status])) { $where .= ' AND status = ?'; $params[] = $status; }
        $orders = DB::all("SELECT o.*, s.name AS store_name FROM orders o LEFT JOIN stores s ON s.id = o.store_id WHERE $where ORDER BY o.id DESC", $params);
        $items = [];
        foreach ($orders as $o) $items[$o['id']] = DB::all('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]);
        View::show('admin/orders/index', [
            'orders' => $orders, 'items' => $items, 'status' => $status, 'statuses' => self::STATUSES,
            'page_title' => 'Замовлення — адмінка',
        ], 'layouts/admin');
    }

    public static function view(int $id): never
    {
        [$scope, $params] = self::scopeWhere();
        $order = DB::row("SELECT * FROM orders WHERE id = ? AND $scope", array_merge([$id], $params));
        if (!$order) redirect('/admin/orders');

        if (is_post()) {
            $new = $_POST['status'] ?? '';
            if (isset(self::STATUSES[$new]) && $new !== $order['status']) {
                DB::update('orders', ['status' => $new], 'id = ?', [$id]);
                Notify::fire('order_status', ['number' => $order['number'], 'status' => self::STATUSES[$new]], $order['store_id'] ? (int)$order['store_id'] : null);
                flash('success', 'Статус оновлено: ' . self::STATUSES[$new]);
            }
            $comment = trim($_POST['admin_comment'] ?? '');
            redirect('/admin/orders/' . $id);
        }

        $items = DB::all('SELECT * FROM order_items WHERE order_id = ?', [$id]);
        // поточні залишки по магазинах для кожної позиції (саме того варіанта)
        $itemStock = [];
        foreach ($items as $it) {
            if (!$it['product_id']) continue;
            $itemStock[(int)$it['id']] = Catalog::stockByStore((int)$it['product_id'], $it['variant_id'] ? (int)$it['variant_id'] : null);
        }

        View::show('admin/orders/view', [
            'order' => $order,
            'order_items' => $items,
            'item_stock' => $itemStock,
            'stores' => Catalog::stores(),
            'statuses' => self::STATUSES,
            'store' => $order['store_id'] ? DB::row('SELECT * FROM stores WHERE id = ?', [$order['store_id']]) : null,
            'page_title' => 'Замовлення ' . $order['number'] . ' — адмінка',
        ], 'layouts/admin');
    }
}
