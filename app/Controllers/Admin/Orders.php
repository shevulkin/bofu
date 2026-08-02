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
        // продавець бачить картину по мережі, але правити зможе лише свої точки —
        // це вирішує canManage, а не видимість
        if (Auth::can('orders.view_all')) return true;
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
        // за замовчуванням кабінет заточений під свої точки; «всі» — коли треба
        // подивитись картину по мережі або забрати позицію собі
        $seesAll = $mine !== null && Auth::can('orders.view_all');
        $scope = ($_GET['scope'] ?? 'mine') === 'all' && $seesAll ? 'all' : 'mine';
        $params = [];
        if ($mine === null)       $where = 'o.parent_id IS NULL';      // адмін — замовлення цілком
        elseif ($scope === 'all') $where = 'o.parent_id IS NOT NULL';  // уся мережа, чуже — лише читання
        elseif (!$mine)           $where = '1=0';
        else                      $where = 'o.parent_id IS NOT NULL AND o.store_id IN (' . implode(',', array_map('intval', $mine)) . ')';
        if ($status !== 'all' && isset(self::STATUSES[$status])) { $where .= ' AND o.status = ?'; $params[] = $status; }

        $orders = DB::all(
            "SELECT o.*, s.name AS store_name, p.number AS parent_number, au.name AS assigned_name
             FROM orders o
             LEFT JOIN stores s ON s.id = o.store_id
             LEFT JOIN orders p ON p.id = o.parent_id
             LEFT JOIN users au ON au.id = o.assigned_user_id
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
            'my_store_ids' => $mine ?? [],
            'sees_all' => $seesAll, 'scope' => $scope,
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

        // нотатки живуть у тій самій стрічці подій, але показуємо їх окремо:
        // серед статусів вони губляться, а читають їх перед роботою
        $all = OrderFlow::events((int)$parent['id']);
        $notes = array_values(array_filter($all, fn($e) => $e['type'] === 'note'));
        $events = array_values(array_filter($all, fn($e) => $e['type'] !== 'note'));

        View::show('admin/orders/view', [
            'order' => $order,          // те, що відкрили
            'parent' => $parent,        // головне замовлення
            'children' => $children,    // усі частини магазинів
            'focus' => $order['parent_id'] ? (int)$order['id'] : null,
            'items' => $items,
            'item_stock' => $stock,
            'can_manage' => $manage,
            'assignees' => self::assignees($children),
            'stores' => Catalog::stores(),
            'events' => $events,
            'notes' => $notes,
            'can_note' => Auth::can('orders.note'),
            'can_assign' => Auth::can('orders.assign'),
            'statuses' => self::STATUSES,
            'can_manage_parent' => Auth::isAdmin(),
            'page_title' => 'Замовлення ' . $order['number'] . ' — адмінка',
        ], 'layouts/admin');
    }

    /** Хто взяв частини в роботу: [id підзамовлення => ['name','at','is_me']] */
    private static function assignees(array $children): array
    {
        $ids = [];
        foreach ($children as $c) if ($c['assigned_user_id']) $ids[] = (int)$c['assigned_user_id'];
        if (!$ids) return [];
        $names = [];
        foreach (DB::all('SELECT id, name FROM users WHERE id IN (' . implode(',', array_unique($ids)) . ')') as $u) {
            $names[(int)$u['id']] = $u['name'];
        }
        $out = [];
        foreach ($children as $c) {
            $uid = (int)($c['assigned_user_id'] ?? 0);
            if (!$uid) continue;
            $out[(int)$c['id']] = [
                'name' => $names[$uid] ?? '—',
                'at' => $c['assigned_at'],
                'is_me' => $uid === (int)Auth::id(),
            ];
        }
        return $out;
    }

    /** POST зі сторінки замовлення: статус, передача позиції, робота над частиною, нотатка */
    private static function handle(array $order): void
    {
        $parent = OrderFlow::head($order);
        $back = '/admin/orders/' . $order['id'];
        // діяти можна лише в межах відкритого замовлення
        $tree = [(int)$parent['id'] => $parent];
        foreach (OrderFlow::children((int)$parent['id']) as $c) $tree[(int)$c['id']] = $c;

        $action = $_POST['action'] ?? 'status';

        if ($action === 'note') {
            Auth::requireCap('orders.note');
            $text = trim((string)($_POST['note'] ?? ''));
            if ($text === '') { flash('error', 'Нотатка порожня.'); redirect($back); }
            if (mb_strlen($text) > 2000) $text = mb_substr($text, 0, 2000);
            OrderFlow::log((int)$parent['id'], $order['parent_id'] ? (int)$order['id'] : null,
                'note', $text, Auth::id());
            flash('success', 'Нотатку додано.');
            redirect($back);
        }

        if ($action === 'claim' || $action === 'release') {
            Auth::requireCap('orders.assign');
            $target = $tree[(int)($_POST['order_id'] ?? 0)] ?? null;
            // мітка ставиться на частину магазину, а не на замовлення цілком
            if (!$target || !$target['parent_id'] || !self::canManage($target)) {
                flash('error', 'Немає прав брати цю частину в роботу.');
                redirect($back);
            }
            self::setAssignee($target, $action === 'claim');
            redirect($back);
        }

        if ($action === 'transfer') {
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

    /**
     * Мітка «в роботі» — без замка: інший продавець може перебрати частину на себе.
     * Замок тут зробив би більше шкоди, ніж користі: людина забула зняти — і частина
     * висить, доки хтось не покличе адміна.
     */
    private static function setAssignee(array $target, bool $claim): void
    {
        $prev = $target['assigned_user_id']
            ? DB::val('SELECT name FROM users WHERE id = ?', [(int)$target['assigned_user_id']])
            : null;
        DB::update('orders', [
            'assigned_user_id' => $claim ? Auth::id() : null,
            'assigned_at' => $claim ? now() : null,
        ], 'id = ?', [(int)$target['id']]);

        $who = Auth::user()['name'] ?? '';
        $msg = $claim
            ? $target['number'] . ': узяв(ла) в роботу ' . $who
                . ($prev && (int)$target['assigned_user_id'] !== (int)Auth::id() ? ' (раніше — ' . $prev . ')' : '')
            : $target['number'] . ': знято з роботи' . ($prev ? ' (' . $prev . ')' : '');
        OrderFlow::log((int)$target['parent_id'], (int)$target['id'], 'assign', $msg, Auth::id());
        flash('success', $claim ? 'Взято в роботу.' : 'Знято з роботи.');
    }
}
