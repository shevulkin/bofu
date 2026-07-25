<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, Images;

class Products
{
    public static function index(): never
    {
        $q = trim($_GET['q'] ?? '');
        $cat = (int)($_GET['cat'] ?? 0);
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
        $products = DB::all(
            'SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC', $params);
        View::show('admin/products/index', [
            'products' => $products, 'categories' => Catalog::categories(),
            'q' => $q, 'cat' => $cat,
            'page_title' => 'Товари — адмінка',
        ], 'layouts/admin');
    }

    /** Масове редагування: назва/ціна/залишки по магазинах у таблиці */
    public static function bulk(): never
    {
        $stores = Catalog::stores();
        if (is_post()) {
            $rows = $_POST['p'] ?? [];
            foreach ($rows as $id => $data) {
                $id = (int)$id;
                $upd = [];
                if (isset($data['name']) && trim($data['name']) !== '') $upd['name'] = trim($data['name']);
                if (array_key_exists('base_price', $data)) $upd['base_price'] = $data['base_price'] === '' ? null : (float)$data['base_price'];
                if (isset($data['active'])) $upd['active'] = (int)(bool)$data['active'];
                if ($upd) { $upd['updated_at'] = now(); DB::update('products', $upd, 'id = ?', [$id]); }
                // ціни по магазинах
                foreach ((array)($data['store_price'] ?? []) as $sid => $priceVal) {
                    $sid = (int)$sid;
                    if (!Auth::isAdmin() && !in_array($sid, Auth::storeIds(), true)) continue;
                    $exists = DB::row('SELECT id FROM store_prices WHERE product_id = ? AND store_id = ? AND variant_id IS NULL', [$id, $sid]);
                    if ($priceVal === '') { if ($exists) DB::delete('store_prices', 'id = ?', [$exists['id']]); }
                    elseif ($exists) DB::update('store_prices', ['price' => (float)$priceVal], 'id = ?', [$exists['id']]);
                    else DB::insert('store_prices', ['product_id' => $id, 'store_id' => $sid, 'variant_id' => null, 'price' => (float)$priceVal]);
                }
                // залишки
                foreach ((array)($data['stock'] ?? []) as $sid => $qty) {
                    $sid = (int)$sid;
                    if (!Auth::isAdmin() && !in_array($sid, Auth::storeIds(), true)) continue;
                    if ($qty === '') continue;
                    $exists = DB::row('SELECT id FROM store_stock WHERE product_id = ? AND store_id = ? AND variant_id IS NULL', [$id, $sid]);
                    if ($exists) DB::update('store_stock', ['qty' => (int)$qty], 'id = ?', [$exists['id']]);
                    else DB::insert('store_stock', ['product_id' => $id, 'store_id' => $sid, 'variant_id' => null, 'qty' => (int)$qty]);
                }
            }
            flash('success', 'Зміни збережено');
            redirect('/admin/products/bulk');
        }
        $products = DB::all('SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY c.sort, p.name');
        $prices = []; $stocks = [];
        foreach (DB::all('SELECT * FROM store_prices WHERE variant_id IS NULL') as $r) $prices[$r['product_id']][$r['store_id']] = $r['price'];
        foreach (DB::all('SELECT * FROM store_stock WHERE variant_id IS NULL') as $r) $stocks[$r['product_id']][$r['store_id']] = $r['qty'];
        View::show('admin/products/bulk', [
            'products' => $products, 'stores' => $stores, 'prices' => $prices, 'stocks' => $stocks,
            'page_title' => 'Масове редагування — адмінка',
        ], 'layouts/admin');
    }

    public static function create(): never
    {
        if (is_post()) {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') { flash('error', 'Вкажіть назву'); redirect('/admin/products/new'); }
            $slug = slugify($name);
            $i = 1; $base = $slug;
            while (DB::row('SELECT 1 FROM products WHERE slug = ?', [$slug])) $slug = $base . '-' . (++$i);
            $id = DB::insert('products', [
                'category_id' => (int)($_POST['category_id'] ?? 0),
                'name' => $name, 'slug' => $slug,
                'sku' => trim($_POST['sku'] ?? '') ?: null,
                'short_desc' => trim($_POST['short_desc'] ?? '') ?: null,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'base_price' => $_POST['base_price'] === '' ? null : (float)$_POST['base_price'],
                'type' => in_array($_POST['type'] ?? '', ['product','service','video','course'], true) ? $_POST['type'] : 'product',
                'active' => isset($_POST['active']) ? 1 : 0,
                'featured' => isset($_POST['featured']) ? 1 : 0,
                'made_to_order' => isset($_POST['made_to_order']) ? 1 : 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            flash('success', 'Товар створено — додайте фото, атрибути та ціни');
            redirect('/admin/products/' . $id);
        }
        View::show('admin/products/form', [
            'p' => null, 'categories' => Catalog::categories(), 'stores' => Catalog::stores(),
            'variants' => [], 'attrs' => [], 'images' => [], 'store_prices' => [], 'store_stock' => [],
            'page_title' => 'Новий товар — адмінка',
        ], 'layouts/admin');
    }

    public static function edit(int $id): never
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) redirect('/admin/products');

        if (is_post()) { self::save($id, $p); }

        $store_prices = []; $store_stock = [];
        foreach (DB::all('SELECT * FROM store_prices WHERE product_id = ? AND variant_id IS NULL', [$id]) as $r) $store_prices[$r['store_id']] = $r['price'];
        foreach (DB::all('SELECT * FROM store_stock WHERE product_id = ? AND variant_id IS NULL', [$id]) as $r) $store_stock[$r['store_id']] = $r['qty'];

        View::show('admin/products/form', [
            'p' => $p, 'categories' => Catalog::categories(), 'stores' => Catalog::stores(),
            'variants' => DB::all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort, id', [$id]),
            'attrs' => DB::all('SELECT * FROM product_attrs WHERE product_id = ? ORDER BY sort, id', [$id]),
            'images' => DB::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]),
            'store_prices' => $store_prices, 'store_stock' => $store_stock,
            'page_title' => 'Товар: ' . $p['name'] . ' — адмінка',
        ], 'layouts/admin');
    }

    private static function save(int $id, array $p): void
    {
        $action = $_POST['_action'] ?? 'save';

        if ($action === 'delete' && Auth::isAdmin()) {
            foreach (DB::all('SELECT path FROM product_images WHERE product_id = ?', [$id]) as $img) Images::delete($img['path']);
            foreach (['product_images','product_variants','product_attrs','store_prices','store_stock'] as $t) DB::delete($t, 'product_id = ?', [$id]);
            DB::delete('products', 'id = ?', [$id]);
            flash('success', 'Товар видалено');
            redirect('/admin/products');
        }

        if ($action === 'attach_image') {
            $path = (string)($_POST['media_path'] ?? '');
            $abs = BOFU_ROOT . '/assets/' . $path;
            if ((str_starts_with($path, 'uploads/') || str_starts_with($path, 'img/')) && !str_contains($path, '..') && is_file($abs)) {
                $size = @getimagesize($abs);
                DB::insert('product_images', [
                    'product_id' => $id, 'path' => $path,
                    'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0, 'bytes' => filesize($abs) ?: 0,
                    'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM product_images WHERE product_id = ?', [$id]),
                ]);
                if (empty($p['image'])) DB::update('products', ['image' => $path], 'id = ?', [$id]);
                flash('success', 'Фото додано до товару');
            } else flash('error', 'Фото не знайдено');
            redirect('/admin/products/' . $id);
        }

        if ($action === 'upload_image') {
            $res = Images::saveUpload($_FILES['image'] ?? [], 'p' . $id);
            if ($res) {
                [$path, $w, $h, $bytes] = $res;
                DB::insert('product_images', ['product_id' => $id, 'path' => $path, 'width' => $w, 'height' => $h, 'bytes' => $bytes, 'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM product_images WHERE product_id = ?', [$id])]);
                if (empty($p['image'])) DB::update('products', ['image' => $path], 'id = ?', [$id]);
                flash('success', 'Фото додано (' . $w . '×' . $h . ', ' . round($bytes/1024) . ' КБ)');
            } else flash('error', 'Не вдалося завантажити фото');
            redirect('/admin/products/' . $id);
        }

        if ($action === 'delete_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            $img = DB::row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
            if ($img) {
                Images::delete($img['path']);
                DB::delete('product_images', 'id = ?', [$imgId]);
                if ($p['image'] === $img['path']) {
                    $next = DB::row('SELECT path FROM product_images WHERE product_id = ? ORDER BY sort, id LIMIT 1', [$id]);
                    DB::update('products', ['image' => $next['path'] ?? null], 'id = ?', [$id]);
                }
                flash('success', 'Фото видалено');
            }
            redirect('/admin/products/' . $id);
        }

        // Основне збереження
        DB::update('products', [
            'name' => trim($_POST['name'] ?? $p['name']),
            'category_id' => (int)($_POST['category_id'] ?? $p['category_id']),
            'sku' => trim($_POST['sku'] ?? '') ?: null,
            'short_desc' => trim($_POST['short_desc'] ?? '') ?: null,
            'description' => trim($_POST['description'] ?? '') ?: null,
            'base_price' => ($_POST['base_price'] ?? '') === '' ? null : (float)$_POST['base_price'],
            'old_price' => ($_POST['old_price'] ?? '') === '' ? null : (float)$_POST['old_price'],
            'type' => in_array($_POST['type'] ?? '', ['product','service','video','course'], true) ? $_POST['type'] : $p['type'],
            'active' => isset($_POST['active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
            'made_to_order' => isset($_POST['made_to_order']) ? 1 : 0,
            'updated_at' => now(),
        ], 'id = ?', [$id]);

        // Варіанти
        foreach ((array)($_POST['variant'] ?? []) as $vid => $v) {
            if ($vid === 'new') continue;
            if (!empty($v['_delete'])) { DB::delete('product_variants', 'id = ? AND product_id = ?', [(int)$vid, $id]); continue; }
            DB::update('product_variants', [
                'name' => trim($v['name'] ?? ''),
                'price' => ($v['price'] ?? '') === '' ? null : (float)$v['price'],
            ], 'id = ? AND product_id = ?', [(int)$vid, $id]);
        }
        $newV = $_POST['variant']['new'] ?? null;
        if ($newV && trim($newV['name'] ?? '') !== '') {
            DB::insert('product_variants', [
                'product_id' => $id, 'name' => trim($newV['name']),
                'price' => ($newV['price'] ?? '') === '' ? null : (float)$newV['price'],
                'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM product_variants WHERE product_id = ?', [$id]),
                'active' => 1,
            ]);
        }

        // Атрибути
        foreach ((array)($_POST['attr'] ?? []) as $aid => $a) {
            if ($aid === 'new') continue;
            if (!empty($a['_delete'])) { DB::delete('product_attrs', 'id = ? AND product_id = ?', [(int)$aid, $id]); continue; }
            DB::update('product_attrs', [
                'name' => trim($a['name'] ?? ''), 'value' => trim($a['value'] ?? ''),
                'filterable' => !empty($a['filterable']) ? 1 : 0,
            ], 'id = ? AND product_id = ?', [(int)$aid, $id]);
        }
        $newA = $_POST['attr']['new'] ?? null;
        if ($newA && trim($newA['name'] ?? '') !== '' && trim($newA['value'] ?? '') !== '') {
            DB::insert('product_attrs', [
                'product_id' => $id, 'name' => trim($newA['name']), 'value' => trim($newA['value']),
                'filterable' => !empty($newA['filterable']) ? 1 : 0,
                'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM product_attrs WHERE product_id = ?', [$id]),
            ]);
        }

        // Ціни та залишки по магазинах
        foreach ((array)($_POST['store_price'] ?? []) as $sid => $priceVal) {
            $sid = (int)$sid;
            if (!Auth::isAdmin() && !in_array($sid, Auth::storeIds(), true)) continue;
            $exists = DB::row('SELECT id FROM store_prices WHERE product_id = ? AND store_id = ? AND variant_id IS NULL', [$id, $sid]);
            if ($priceVal === '') { if ($exists) DB::delete('store_prices', 'id = ?', [$exists['id']]); }
            elseif ($exists) DB::update('store_prices', ['price' => (float)$priceVal], 'id = ?', [$exists['id']]);
            else DB::insert('store_prices', ['product_id' => $id, 'store_id' => $sid, 'variant_id' => null, 'price' => (float)$priceVal]);
        }
        foreach ((array)($_POST['store_stock'] ?? []) as $sid => $qty) {
            $sid = (int)$sid;
            if (!Auth::isAdmin() && !in_array($sid, Auth::storeIds(), true)) continue;
            if ($qty === '') continue;
            $exists = DB::row('SELECT id FROM store_stock WHERE product_id = ? AND store_id = ? AND variant_id IS NULL', [$id, $sid]);
            if ($exists) DB::update('store_stock', ['qty' => (int)$qty], 'id = ?', [$exists['id']]);
            else DB::insert('store_stock', ['product_id' => $id, 'store_id' => $sid, 'variant_id' => null, 'qty' => (int)$qty]);
        }

        flash('success', 'Збережено');
        redirect('/admin/products/' . $id);
    }
}
