<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth;

class Stores
{
    public static function index(): never
    {
        Auth::requireAdmin();
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
                $name = trim($_POST['name']);
                DB::insert('stores', [
                    'name' => $name, 'slug' => slugify($name) . '-' . random_int(10, 99),
                    'city' => trim($_POST['city'] ?? '') ?: null, 'address' => trim($_POST['address'] ?? '') ?: null,
                    'phone' => trim($_POST['phone'] ?? '') ?: null, 'active' => 1,
                    'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM stores'),
                ]);
                flash('success', 'Магазин додано');
            }
            if ($action === 'save') {
                foreach ((array)($_POST['store'] ?? []) as $id => $s) {
                    DB::update('stores', [
                        'name' => trim($s['name'] ?? ''), 'city' => trim($s['city'] ?? '') ?: null,
                        'address' => trim($s['address'] ?? '') ?: null, 'phone' => trim($s['phone'] ?? '') ?: null,
                        'active' => !empty($s['active']) ? 1 : 0,
                    ], 'id = ?', [(int)$id]);
                }
                flash('success', 'Збережено');
            }
            redirect('/admin/stores');
        }
        View::show('admin/stores', [
            'stores' => DB::all('SELECT * FROM stores ORDER BY sort, id'),
            'page_title' => 'Магазини — адмінка',
        ], 'layouts/admin');
    }
}
