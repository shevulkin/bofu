<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Attrs, Auth, Csrf, StockWatch, JsonLd;

class Shop
{
    public static function index(): never
    {
        $cats = Catalog::categories();
        $catSlug = $_GET['cat'] ?? '';
        $current = null;
        foreach ($cats as $c) if ($c['slug'] === $catSlug) $current = $c;
        // Розділ обраного підрозділу — для крихт і для панелі, яка має
        // відкритись саме на тій гілці, де людина зараз стоїть
        $parentCat = Catalog::parentCategory($current);

        $filters = [
            'category_id' => $current['id'] ?? null,
            'q' => trim($_GET['q'] ?? ''),
            'min' => $_GET['min'] ?? '',
            'max' => $_GET['max'] ?? '',
            'store_id' => (int)($_GET['store'] ?? 0) ?: null,
            'sort' => $_GET['sort'] ?? '',
            'attr' => (array)($_GET['attr'] ?? []),
            // і один slug із посилання, і галки в панелі фільтрів
            'brand' => array_values(array_filter(array_map(
                fn($v) => trim((string)$v), (array)($_GET['brand'] ?? [])), fn($v) => $v !== '')),
        ];
        $products = Catalog::search($filters);
        // бренди списку — одним запитом, інакше кожна картка ходила б у базу сама
        Catalog::preloadBrands($products);

        // назва бренду для заголовка, коли обрано рівно один: людина прийшла
        // сюди з картки товару й має бачити, де опинилась
        $brand = count($filters['brand']) === 1
            ? DB::row('SELECT * FROM brands WHERE slug = ? OR id = ?',
                [$filters['brand'][0], (int)$filters['brand'][0]])
            : null;

        // Обрані товари інших категорій (як у дизайні).
        //
        // Виключаємо не лише поточну категорію, а й усе, що вже стоїть вище на
        // цій самій сторінці. Без цього на «Всіх товарах» (де категорія не
        // обрана й фільтрувати не було чим) блок «Вас може зацікавити»
        // показував ті самі чотири позиції з мітками «ХІТ», які покупець
        // щойно проминув, — цілий екран без жодної нової позиції.
        $shownIds = array_map(fn($r) => (int)$r['id'], $products);
        $skip = $shownIds ? ' AND id NOT IN (' . implode(',', $shownIds) . ')' : '';
        // «Інші категорії» — це вся гілка, а не сама категорія: підрозділ того
        // самого розділу для покупця не «інше», він щойно звідти
        $branch = $current ? Catalog::branchIds((int)$current['id']) : [];
        $other = DB::all('SELECT * FROM products WHERE active = 1 AND featured = 1' .
            ($branch ? ' AND category_id NOT IN (' . implode(',', $branch) . ')' : '') . $skip . ' ORDER BY id LIMIT 4');
        Catalog::preloadBrands($other);

        View::show('shop/index', [
            'categories' => $cats,
            'cat_tree' => Catalog::categoryTree($cats),
            'current_cat' => $current,
            'parent_cat' => $parentCat,
            'products' => $products,
            'other_products' => $other,
            'filters' => $filters,
            'brand' => $brand,
            'stores' => Catalog::stores(),
            'attr_options' => Catalog::filterableAttrs($current['id'] ?? null),
            'brand_options' => Catalog::filterableBrands($current['id'] ?? null),
            'page_title' => ($current ? $current['name'] . ' — ' : '') . 'Магазин — ' . cfg('app_name'),
            // Свій опис на категорію. Порожній падав у загальний seo_description,
            // і Google бачив десяток сторінок з однаковим описом — для нього це
            // дублікати, які він показує в видачі неохоче.
            'meta_description' => $current
                ? mb_substr($current['name'], 0, 1) . mb_strtolower(mb_substr($current['name'], 1))
                    . ' з власної пасіки: ' . count($products) . ' '
                    . plural(count($products), 'позиція', 'позиції', 'позицій')
                    . ' у наявності, доставка Новою Поштою та самовивіз із магазинів.'
                : 'Мед, продукти бджільництва, свічки, інструмент і костюми пасічника з власної пасіки. Доставка Новою Поштою по всій Україні.',
            'jsonld' => [JsonLd::breadcrumbs(array_values(array_filter([
                ['Головна', '/'],
                ['Магазин', $current ? '/shop' : null],
                // підрозділ веде крихти через свій розділ — тим самим шляхом,
                // яким людина сюди дійшла панеллю каталогу
                $parentCat ? [$parentCat['name'], '/shop?cat=' . $parentCat['slug']] : null,
                $current ? [$current['name'], null] : null,
            ])))],
        ]);
    }

    /**
     * Чи вага вже описана характеристикою товару.
     *
     * Колонка weight зʼявилась пізніше за характеристики, і в багатьох картках
     * вага живе в обох місцях одразу. Показувати треба щось одне — лишаємо те,
     * що вписав власник руками: у характеристиці він міг уточнити («350 г ± 5»),
     * а колонка зберігає лише число.
     */
    private static function weightIsAttr(array $attrs): bool
    {
        foreach ($attrs as $a) {
            $name = mb_strtolower(trim((string)($a['name'] ?? '')));
            // Обидва написання апострофа навмисно: у старих картках вага
            // заведена звичайною лапкою, у нових — правильним U+02BC.
            if (in_array($name, ['вага', 'маса', 'обʼєм', "об'єм", 'объем'], true)) return true;
        }
        return false;
    }

    public static function product(string $slug): never
    {
        $p = DB::row('SELECT * FROM products WHERE slug = ? AND active = 1', [$slug]);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        $cat = DB::row('SELECT * FROM categories WHERE id = ?', [$p['category_id']]);
        $catParent = Catalog::parentCategory($cat);   // «Мед» над «Липовим» — для крихт
        $variants = Catalog::variants((int)$p['id']);
        $attrs = Catalog::attrs((int)$p['id']);
        $images = Catalog::gallery($p); // головне фото першим, далі додаткові
        $stores = Catalog::stores();

        // варіанти як комбінації характеристик (розмір, колір…)
        $variantOptions = Attrs::variantOptionsFor((int)$p['id']);
        $axes = Catalog::variantAxes($variants, $variantOptions);
        $stockMap = Catalog::stockMap((int)$p['id']);

        // варіант, обраний за замовчуванням — з нього беремо ціну й наявність до першого кліку
        $first = $variants[0] ?? null;

        // наявність по магазинах: для обраного варіанта (або товару без варіантів)
        $availability = [];
        foreach ($stores as $s) {
            $sid = (int)$s['id'];
            $availability[] = [
                'store' => $s,
                'qty' => Catalog::stock((int)$p['id'], $first['id'] ?? null, $sid),
                'price' => Catalog::price($p, $first, $sid)[0],
                'by_variant' => $stockMap[$sid] ?? [],
            ];
        }
        [$price, $old] = Catalog::price($p, $first);

        // дані для миттєвого перерахунку ціни й наявності при виборі варіанта
        $variantData = [];
        foreach ($variants as $v) {
            $vid = (int)$v['id'];
            [$vp, $vo] = Catalog::price($p, $v);
            $opts = [];
            foreach ($variantOptions[$vid] ?? [] as $o) $opts[(int)$o['attribute_id']] = $o['value'];
            $qty = 0;
            $storePrices = [];
            foreach ($stores as $s) {
                $sid = (int)$s['id'];
                $qty += (int)($stockMap[$sid][$vid] ?? 0);
                $sp = Catalog::price($p, $v, $sid)[0];
                $storePrices[$sid] = ($sp !== null && $sp != $vp) ? price_fmt($sp) : '';
            }
            // Вага належить фасовці: банка 0,5 і банка 1,5 — це різна вага й
            // різна ціна за 100 г. Порожня у варіанта — беремо товарну.
            $vw = ($v['weight'] ?? null) !== null && $v['weight'] > 0 ? (float)$v['weight'] : (float)($p['weight'] ?? 0);
            $variantData[] = [
                'id' => $vid, 'name' => $v['name'], 'sku' => $v['sku'] ?? '',
                'price' => $vp, 'price_fmt' => $vp !== null ? price_fmt($vp) : 'Ціна за запитом',
                'old_fmt' => $vo !== null ? price_fmt($vo) : '',
                'qty' => $qty, 'opts' => $opts, 'store_price' => $storePrices,
                'weight_fmt' => weight_fmt($vw),
                'per_100g' => price_per_100g($vp, $vw),
            ];
        }

        $related = DB::all('SELECT * FROM products WHERE active = 1 AND category_id = ? AND id != ? LIMIT 4', [$p['category_id'], $p['id']]);
        Catalog::preloadBrands($related);

        View::show('shop/product', [
            'p' => $p, 'cat' => $cat, 'variants' => $variants, 'attrs' => $attrs,
            'variant_axes' => $axes, 'variant_data' => $variantData,
            'images' => $images, 'availability' => $availability,
            'price' => $price, 'old_price' => $old, 'related' => $related,
            // Вага й ціна за 100 г для стану «до вибору варіанта»; далі їх
            // переставляє JS разом із ціною — так само, як наявність.
            //
            // Рядок ваги не показуємо, якщо вагу вже описали характеристикою:
            // її заводили руками задовго до появи колонки, і два однакові рядки
            // «Вага 350 г» підряд читаються як помилка, а не як подробиця.
            'weight_fmt' => self::weightIsAttr($attrs) ? '' : weight_fmt($first['weight'] ?? $p['weight'] ?? null),
            'per_100g' => price_per_100g($price, ($first['weight'] ?? null) ?: ($p['weight'] ?? null)),
            'page_title' => $p['name'] . ' — ' . cfg('app_name'),
            'meta_description' => $p['short_desc'] ?? '',
            'jsonld_product' => true,
            'jsonld' => [
                JsonLd::product(
                    $p, $images, $price,
                    array_map(fn($n) => ['@type' => 'Brand', 'name' => $n], Catalog::brandNames($p)),
                    Catalog::stock((int)$p['id']) > 0 || !empty($p['made_to_order'])
                ),
                // Крихти повторюють шлях, яким людина сюди дійшла: головна →
                // категорія → товар. Google показує їх замість голої адреси.
                JsonLd::breadcrumbs(array_values(array_filter([
                    ['Головна', '/'],
                    ['Магазин', '/shop'],
                    $catParent ? [$catParent['name'], '/shop?cat=' . $catParent['slug']] : null,
                    $cat ? [$cat['name'], '/shop?cat=' . $cat['slug']] : null,
                    [$p['name'], null],
                ]))),
            ],
            // чи ця людина вже чекає обраний варіант — щоб не пропонувати вдруге
            'watching' => StockWatch::isWaiting((int)$p['id'], $first['id'] ?? null, Auth::id()),
        ]);
    }

    /**
     * «Повідомте, коли зʼявиться».
     *
     * Тільки для тих, хто увійшов: канали сповіщень людина обирає в кабінеті,
     * а в гостя кабінету немає. Гостю кажемо це прямо й відправляємо на вхід —
     * мовчазна відмова виглядала б як поламана кнопка.
     */
    public static function watch(): never
    {
        Csrf::verify();
        $back = safe_back($_POST['back'] ?? null, '/shop');
        if (!Auth::check()) {
            flash('error', 'Увійдіть, щоб ми могли вас сповістити — у кабінеті ви оберете, куди саме писати.');
            redirect($back);
        }
        $pid = (int)($_POST['product_id'] ?? 0);
        $vid = (int)($_POST['variant_id'] ?? 0) ?: null;
        $sid = (int)($_POST['store_id'] ?? 0) ?: null;

        $p = DB::row('SELECT id FROM products WHERE id = ? AND active = 1', [$pid]);
        if (!$p) { flash('error', 'Товар не знайдено.'); redirect($back); }
        // Варіант мусить належати цьому товару: інакше в чергу очікувань
        // потрапила б чужа позиція, і продавець виробляв би не те
        if ($vid !== null && !DB::row('SELECT 1 FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $pid])) {
            $vid = null;
        }
        if ($sid !== null && !DB::row('SELECT 1 FROM stores WHERE id = ? AND active = 1', [$sid])) {
            $sid = null;
        }

        flash('success', StockWatch::add($pid, $vid, $sid, (int)Auth::id())
            ? 'Добре — напишемо, щойно зʼявиться. Куди саме писати, можна змінити в кабінеті.'
            : 'Ви вже в черзі на цю позицію — повідомимо, щойно вона зʼявиться.');
        redirect($back);
    }
}
