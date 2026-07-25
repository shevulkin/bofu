<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth;

class Categories
{
    public static function index(): never
    {
        Auth::requireAdmin();
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
                $name = trim($_POST['name']);
                $slug = slugify($name);
                $i = 1; $base = $slug;
                while (DB::row('SELECT 1 FROM categories WHERE slug = ?', [$slug])) $slug = $base . '-' . (++$i);
                DB::insert('categories', [
                    'name' => $name, 'slug' => $slug,
                    'type' => in_array($_POST['type'] ?? '', ['product','service','video','course'], true) ? $_POST['type'] : 'product',
                    'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM categories'), 'active' => 1,
                ]);
                flash('success', 'Категорію додано');
            }
            if ($action === 'save') {
                foreach ((array)($_POST['cat'] ?? []) as $id => $c) {
                    if (!empty($c['_delete'])) {
                        $cnt = (int)DB::val('SELECT COUNT(*) FROM products WHERE category_id = ?', [(int)$id]);
                        if ($cnt === 0) DB::delete('categories', 'id = ?', [(int)$id]);
                        else flash('error', 'Категорію не видалено: у ній є товари');
                        continue;
                    }
                    DB::update('categories', [
                        'name' => trim($c['name'] ?? ''), 'sort' => (int)($c['sort'] ?? 0),
                        'active' => !empty($c['active']) ? 1 : 0,
                    ], 'id = ?', [(int)$id]);
                }
                flash('success', 'Збережено');
            }
            redirect('/admin/categories');
        }
        $cats = DB::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt FROM categories c ORDER BY c.sort, c.id');
        View::show('admin/categories', ['cats' => $cats, 'page_title' => 'Категорії — адмінка'], 'layouts/admin');
    }
}
