<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content;

class Shop
{
    public static function index(): never
    {
        $cats = Catalog::categories();
        $catSlug = $_GET['cat'] ?? '';
        $current = null;
        foreach ($cats as $c) if ($c['slug'] === $catSlug) $current = $c;

        $filters = [
            'category_id' => $current['id'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
            'min' => $_GET['min'] ?? '',
            'max' => $_GET['max'] ?? '',
            'store_id' => (int)($_GET['store'] ?? 0) ?: null,
            'sort' => $_GET['sort'] ?? '',
            'attr' => (array)($_GET['attr'] ?? []),
        ];
        $products = Catalog::search($filters);

        // Обрані товари інших категорій (як у дизайні)
        $other = DB::all('SELECT * FROM products WHERE active = 1 AND featured = 1' .
            ($current ? ' AND category_id != ' . (int)$current['id'] : '') . ' ORDER BY id LIMIT 4');

        View::show('shop/index', [
            'categories' => $cats,
            'current_cat' => $current,
            'products' => $products,
            'other_products' => $other,
            'filters' => $filters,
            'stores' => Catalog::stores(),
            'attr_options' => Catalog::filterableAttrs($current['id'] ?? null),
            'page_title' => ($current ? $current['name'] . ' — ' : '') . 'Магазин — ' . cfg('app_name'),
        ]);
    }

    public static function product(string $slug): never
    {
        $p = DB::row('SELECT * FROM products WHERE slug = ? AND active = 1', [$slug]);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        $cat = DB::row('SELECT * FROM categories WHERE id = ?', [$p['category_id']]);
        $variants = Catalog::variants((int)$p['id']);
        $attrs = Catalog::attrs((int)$p['id']);
        $images = Catalog::images((int)$p['id']);
        $stores = Catalog::stores();

        // наявність по магазинах
        $availability = [];
        foreach ($stores as $s) {
            $availability[] = [
                'store' => $s,
                'qty' => Catalog::stock((int)$p['id'], null, (int)$s['id']),
                'price' => Catalog::price($p, null, (int)$s['id'])[0],
            ];
        }
        [$price, $old] = Catalog::price($p);
        $related = DB::all('SELECT * FROM products WHERE active = 1 AND category_id = ? AND id != ? LIMIT 4', [$p['category_id'], $p['id']]);

        View::show('shop/product', [
            'p' => $p, 'cat' => $cat, 'variants' => $variants, 'attrs' => $attrs,
            'images' => $images, 'availability' => $availability,
            'price' => $price, 'old_price' => $old, 'related' => $related,
            'page_title' => $p['name'] . ' — ' . cfg('app_name'),
            'meta_description' => $p['short_desc'] ?? '',
            'jsonld_product' => true,
        ]);
    }
}
