<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, Settings, QtyDiscounts;

class Promos
{
    public static function index(): never
    {
        Auth::requireCap('promos.manage');
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add_code' && trim($_POST['code'] ?? '') !== '') {
                // порожнє поле ліміту = без обмежень, тому саме null, а не 0
                $limit = static fn(string $k): ?int => (int)($_POST[$k] ?? 0) > 0 ? (int)$_POST[$k] : null;
                $cap = trim((string)($_POST['max_total_percent'] ?? ''));
                DB::insert('promo_codes', [
                    'code' => mb_strtoupper(trim($_POST['code'])), 'percent' => (float)($_POST['percent'] ?? 0),
                    'active' => 1, 'expires_at' => trim($_POST['expires_at'] ?? '') ?: null,
                    'max_uses' => $limit('max_uses'), 'per_user_limit' => $limit('per_user_limit'),
                    'stackable' => isset($_POST['stackable']) ? 1 : 0,
                    'max_total_percent' => $cap !== '' ? (float)$cap : null,
                ]);
                flash('success', 'Промокод додано');
            }
            if ($action === 'del_code') { DB::delete('promo_codes', 'id = ?', [(int)$_POST['id']]); flash('success', 'Промокод видалено'); }
            if ($action === 'add_promo' && trim($_POST['title'] ?? '') !== '') {
                DB::insert('promotions', [
                    'title' => trim($_POST['title']), 'percent' => (float)($_POST['percent'] ?? 0),
                    'store_id' => (int)($_POST['store_id'] ?? 0) ?: null,
                    'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                    'product_id' => (int)($_POST['product_id'] ?? 0) ?: null,
                    'starts_at' => trim($_POST['starts_at'] ?? '') ?: null,
                    'ends_at' => trim($_POST['ends_at'] ?? '') ?: null,
                    'active' => 1,
                ]);
                flash('success', 'Акцію створено');
            }
            if ($action === 'toggle_promo') {
                DB::query('UPDATE promotions SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]);
            }
            if ($action === 'del_promo') { DB::delete('promotions', 'id = ?', [(int)$_POST['id']]); flash('success', 'Акцію видалено'); }
            if ($action === 'qty_tiers') {
                // Стеля живе поруч зі шкалою навмисно: саме вона вирішує, скільки
                // з набраних відсотків покупець побачить у кошику насправді.
                $cap = trim((string)($_POST['max_discount_default'] ?? ''));
                if ($cap !== '') {
                    Settings::set('max_discount_default',
                        (string)max(0.0, min(100.0, (float)str_replace(',', '.', $cap))));
                }
                $cat = (int)($_POST['tier_category_id'] ?? 0);
                $errors = QtyDiscounts::save(null, $cat ?: null, (array)($_POST['tier'] ?? []));
                foreach ($errors as $err) flash('error', $err);
                if (!$errors) flash('success', 'Оптову шкалу збережено');
                redirect('/admin/promos' . ($cat ? '?tier_cat=' . $cat : ''));
            }
            if ($action === 'sale_banner') {
                Settings::set('sale_banner_active', isset($_POST['sale_active']) ? '1' : '0');
                Settings::set('sale_banner_text', trim($_POST['sale_text'] ?? ''));
                Settings::set('sale_banner_percent', trim($_POST['sale_percent'] ?? ''));
                flash('success', 'Банер оновлено');
            }
            redirect('/admin/promos');
        }
        $tierCat = (int)($_GET['tier_cat'] ?? 0);
        View::show('admin/promos', [
            // разом із фактичною кількістю використань — без неї ліміт у списку
            // нічого не означає: незрозуміло, скільки від нього лишилось
            'codes' => DB::all('SELECT c.*, (SELECT COUNT(*) FROM promo_uses u WHERE u.promo_id = c.id) used
                                FROM promo_codes c ORDER BY c.id DESC'),
            'promos' => DB::all('SELECT pr.*, s.name AS store_name, c.name AS cat_name, p.name AS product_name
                                 FROM promotions pr
                                 LEFT JOIN stores s ON s.id = pr.store_id
                                 LEFT JOIN categories c ON c.id = pr.category_id
                                 LEFT JOIN products p ON p.id = pr.product_id ORDER BY pr.id DESC'),
            'stores' => Catalog::stores(), 'categories' => Catalog::categories(),
            'products' => DB::all('SELECT id, name FROM products WHERE active = 1 ORDER BY name'),
            // Ярус, який зараз редагують: 0 — загальна шкала магазину
            'tier_cat' => $tierCat,
            'tier_rows' => QtyDiscounts::level(null, $tierCat ?: null),
            // Огляд усіх заповнених ярусів: без нього незрозуміло, чи шкала,
            // яку ви щойно ввели, взагалі до когось дійде — її може перебивати
            // ярус нижче, а їх на сторінці не видно.
            'tier_map' => self::tierMap(),
            'page_title' => 'Акції та промокоди — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Які яруси шкали заповнені: загальна, розділи (з назвами) і скільки товарів
     * мають власну. Товари поіменно не перелічуємо — їх бувають сотні, а питання,
     * на яке має відповісти список, одне: «чи є щось нижче за мій ярус».
     *
     * @return array{global:array,categories:array,products:int}
     */
    private static function tierMap(): array
    {
        $cats = [];
        $rows = DB::all('SELECT q.*, c.name AS cat_name FROM qty_discounts q
                         JOIN categories c ON c.id = q.category_id
                         WHERE q.product_id IS NULL AND q.category_id IS NOT NULL
                         ORDER BY c.sort, c.name, q.min_qty');
        foreach ($rows as $r) {
            $cid = (int)$r['category_id'];
            $cats[$cid]['name'] ??= (string)$r['cat_name'];
            $cats[$cid]['tiers'][] = $r;
        }
        return [
            'global' => QtyDiscounts::level(null, null),
            'categories' => $cats,
            'products' => (int)DB::val('SELECT COUNT(DISTINCT product_id) FROM qty_discounts WHERE product_id IS NOT NULL'),
        ];
    }
}
