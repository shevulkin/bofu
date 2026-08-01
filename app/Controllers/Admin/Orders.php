<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, OrderFlow;

/**
 * Адмінка замовлень.
 * Адмін працює з головними замовленнями (і бачить усі частини), продавець — зі своїми
 * підзамовленнями, але з правом відкрити головне й побачити картину цілком.
 */
class Orders
{
    public const STATUSES = OrderFlow::STATUSES;

    /** Магазини поточного користувача; null = адмін (усі) */
    private static function myStores(): ?array
    {
        return Auth::isAdmin() ? null : Auth::storeIds();
    }

    /** Чи може користувач змінювати це підзамовлення */
    public static function canManage(array $order): bool
    {
        if (Auth::isAdmin()) return true;
        if (!$order['parent_id']) return false; // головне веде лише адмін
        return in_array((int)$order['store_id'], Auth::storeIds(), true);
    }

    /** Доступ до сторінки: своє підзамовлення або головне, у якому є своя частина */
    private static function canSee(array $order): bool
    {
        if (Auth::isAdmin()) return true;
        $ids = Auth::storeIds();
        if (!$ids) return false;
        if ($order['parent_id']) return in_array((int)$order['store_id'], $ids, true);
        $in = implode(',', array_map('intval', $ids));
        return (bool)DB::val("SELECT 1 FROM orders WHERE parent_id = ? AND store_id IN ($in) LIMIT 1", [$order['id']]);
    }

    public static function index(): never
    {
        $mine = self::myStores();
        $status = $_GET['status'] ?? 'all';
        $params = [];
        if ($mine === null)  $where = 'o.parent_id IS NULL';           // адмін — замовлення цілком
        elseif (!$mine)      $where = '1=0';
        else                 $where = 'o.parent_id IS NOT NULL AND o.store_id IN (' . implode(',', array_map('intval', $mine)) . ')';
        if ($status !== 'all' && isset(self::STATUSES[$status])) { $where .= ' AND o.status = ?'; $params[] = $status; }

        $orders = DB::all(
            "SELECT o.*, s.name AS store_name, p.number AS parent_number
             FROM orders o
             LEFT JOIN stores s ON s.id = o.store_id
             LEFT JOIN orders p ON p.id = o.parent_id
             WHERE $where ORDER BY o.id DESC", $params);

        $items = []; $children = [];
        foreach ($orders as $o) {
            $id = (int)$o['id'];
            $items[$id] = $o['parent_id'] ? OrderFlow::items($id) : OrderFlow::allItems($id);
            if (!$o['parent_id']) $children[$id] = OrderFlow::children($id);
        }

        View::show('admin/orders/index', [
            'orders' => $orders, 'items' => $items, 'children' => $children,
            'status' => $status, 'statuses' => self::STATUSES,
            'is_seller_view' => $mine !== null,
            'page_title' => 'Замовлення — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Одна сторінка і для головного замовлення, і для підзамовлення: завжди видно
     * замовлення цілком, а керування зʼявляється лише там, де є права.
     */
    public static function view(int $id): never
    {
        $order = OrderFlow::order($id);
        if (!$order || !self::canSee($order)) redirect('/admin/orders');
        if (is_post()) self::handle($order);

        $parent = OrderFlow::head($order);
        $children = OrderFlow::children((int)$parent['id']);

        $items = []; $stock = []; $manage = [];
        foreach ($children as $c) {
            $rows = OrderFlow::items((int)$c['id']);
            $items[(int)$c['id']] = $rows;
            $manage[(int)$c['id']] = self::canManage($c);
            foreach ($rows as $it) {
                if (!$it['product_id']) continue;
                $stock[(int)$it['id']] = Catalog::stockByStore((int)$it['product_id'], $it['variant_id'] ? (int)$it['variant_id'] : null);
            }
        }

        View::show('admin/orders/view', [
            'order' => $order,          // те, що відкрили
            'parent' => $parent,        // головне замовлення
            'children' => $children,    // усі частини магазинів
            'focus' => $order['parent_id'] ? (int)$order['id'] : null,
            'items' => $items,
            'item_stock' => $stock,
            'can_manage' => $manage,
            'stores' => Catalog::stores(),
            'events' => OrderFlow::events((int)$parent['id']),
            'statuses' => self::STATUSES,
            'can_manage_parent' => Auth::isAdmin(),
            'page_title' => 'Замовлення ' . $order['number'] . ' — адмінка',
        ], 'layouts/admin');
    }

    /** POST зі сторінки замовлення: статус частини (чи всього) або передача позиції */
    private static function handle(array $order): void
    {
        $parent = OrderFlow::head($order);
        $back = '/admin/orders/' . $order['id'];
        // діяти можна лише в межах відкритого замовлення
        $tree = [(int)$parent['id'] => $parent];
        foreach (OrderFlow::children((int)$parent['id']) as $c) $tree[(int)$c['id']] = $c;

        if (($_POST['action'] ?? 'status') === 'transfer') {
            $item = DB::row('SELECT * FROM order_items WHERE id = ?', [(int)($_POST['item_id'] ?? 0)]);
            $src = $item ? ($tree[(int)$item['order_id']] ?? null) : null;
            if (!$src || !self::canManage($src)) { flash('error', 'Немає прав передавати цю позицію.'); redirect($back); }
            try {
                flash('success', OrderFlow::transferItem((int)$item['id'], (int)($_POST['to_store_id'] ?? 0), Auth::id()));
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
            // якщо дивилися підзамовлення, яке спорожніло й закрилось, — вертаємось на головне
            if (!OrderFlow::order((int)$order['id'])) $back = '/admin/orders/' . $parent['id'];
            redirect($back);
        }

        $target = $tree[(int)($_POST['order_id'] ?? 0)] ?? null;
        $new = $_POST['status'] ?? '';
        if (!$target || !self::canManage($target)) { flash('error', 'Немає прав змінювати цей статус.'); redirect($back); }
        if (OrderFlow::setStatus((int)$target['id'], $new, Auth::id())) {
            flash('success', ($target['parent_id'] ? 'Статус частини ' . $target['number'] : 'Статус замовлення')
                . ' оновлено: ' . OrderFlow::statusLabel($new));
        }
        redirect($back);
    }
}
