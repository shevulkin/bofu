<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Attrs;

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

        // варіанти як комбінації характеристик (розмір, колір…)
        $variantOptions = Attrs::variantOptionsFor((int)$p['id']);
        $axes = Catalog::variantAxes($variants, $variantOptions);
        $stockMap = Catalog::stockMap((int)$p['id']);

        // наявність по магазинах: сумарна і окремо по кожному варіанту
        $availability = [];
        foreach ($stores as $s) {
            $sid = (int)$s['id'];
            $availability[] = [
                'store' => $s,
                'qty' => Catalog::stock((int)$p['id'], null, $sid),
                'price' => Catalog::price($p, null, $sid)[0],
                'by_variant' => $stockMap[$sid] ?? [],
            ];
        }
        [$price, $old] = Catalog::price($p);

        // дані для миттєвого перерахунку ціни й наявності при виборі варіанта
        $variantData = [];
        foreach ($variants as $v) {
            $vid = (int)$v['id'];
            [$vp, $vo] = Catalog::price($p, $v);
            $opts = [];
            foreach ($variantOptions[$vid] ?? [] as $o) $opts[(int)$o['attribute_id']] = $o['value'];
            $qty = 0;
            foreach ($stockMap as $byVariant) $qty += (int)($byVariant[$vid] ?? 0);
            $variantData[] = [
                'id' => $vid, 'name' => $v['name'], 'sku' => $v['sku'] ?? '',
                'price' => $vp, 'price_fmt' => $vp !== null ? price_fmt($vp) : 'Ціна за запитом',
                'old_fmt' => $vo !== null ? price_fmt($vo) : '',
                'qty' => $qty, 'opts' => $opts,
            ];
        }

        $related = DB::all('SELECT * FROM products WHERE active = 1 AND category_id = ? AND id != ? LIMIT 4', [$p['category_id'], $p['id']]);

        View::show('shop/product', [
            'p' => $p, 'cat' => $cat, 'variants' => $variants, 'attrs' => $attrs,
            'variant_axes' => $axes, 'variant_data' => $variantData,
            'images' => $images, 'availability' => $availability,
            'price' => $price, 'old_price' => $old, 'related' => $related,
            'page_title' => $p['name'] . ' — ' . cfg('app_name'),
            'meta_description' => $p['short_desc'] ?? '',
            'jsonld_product' => true,
        ]);
    }
}
