<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, Images, Attrs, StockWatch;

class Products
{
    public static function index(): never
    {
        $q = trim($_GET['q'] ?? '');
        $cat = (int)($_GET['cat'] ?? 0);
        $brand = (int)($_GET['brand'] ?? 0);   // з довідника брендів — «показати ці товари»
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
        if ($brand) {
            $where[] = 'EXISTS (SELECT 1 FROM product_brands pb WHERE pb.product_id = p.id AND pb.brand_id = ?)';
            $params[] = $brand;
        }
        $products = DB::all(
            'SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY p.id DESC', $params);
        $variantCount = [];
        foreach (DB::all('SELECT product_id, COUNT(*) AS c FROM product_variants WHERE active = 1 GROUP BY product_id') as $r) {
            $variantCount[(int)$r['product_id']] = (int)$r['c'];
        }
        View::show('admin/products/index', [
            'products' => $products, 'categories' => Catalog::categories(),
            'stores' => Catalog::stores(), 'stocks' => Catalog::stockTotals(), 'variant_count' => $variantCount,
            'q' => $q, 'cat' => $cat,
            'brand' => Catalog::brand($brand),
            'page_title' => 'Товари — адмінка',
        ], 'layouts/admin');
    }

    /** Масове редагування: назва/ціна/залишки по магазинах у таблиці (варіанти — окремими рядками) */
    public static function bulk(): never
    {
        $stores = Catalog::stores();
        if (is_post()) {
            // Картка товару (назва, базова ціна, видимість) — спільна для всього сайту,
            // тож потребує products.manage. Продавцю лишаються ціни й залишки його точок.
            $canCard = Auth::can('products.manage');
            $rows = $_POST['p'] ?? [];
            foreach ($rows as $id => $data) {
                $id = (int)$id;
                $upd = [];
                if ($canCard) {
                    if (isset($data['name']) && trim($data['name']) !== '') $upd['name'] = trim($data['name']);
                    if (array_key_exists('base_price', $data)) $upd['base_price'] = $data['base_price'] === '' ? null : (float)$data['base_price'];
                    if (isset($data['active'])) $upd['active'] = (int)(bool)$data['active'];
                }
                if ($upd) { $upd['updated_at'] = now(); DB::update('products', $upd, 'id = ?', [$id]); }

                $variantIds = array_map('intval', array_column(
                    DB::all('SELECT id FROM product_variants WHERE product_id = ? AND active = 1', [$id]), 'id'));

                // Власна ціна варіанта — така сама частина картки товару, як і
                // базова ціна, тож і право те саме. Порожнє поле означає «діє
                // базова ціна товару», а не нуль. WHERE по product_id не дає
                // підробленою формою переставити ціну чужому варіанту.
                if ($canCard) {
                    foreach ((array)($data['vbase'] ?? []) as $vid => $val) {
                        $vid = (int)$vid;
                        if (!in_array($vid, $variantIds, true)) continue;
                        DB::update('product_variants',
                            ['price' => ($val === '' ? null : (float)$val)],
                            'id = ? AND product_id = ?', [$vid, $id]);
                    }
                }
                // ціни та залишки товару без варіанта (залишок — лише коли варіантів немає)
                self::syncStore($id, null, (array)($data['store_price'] ?? []), $variantIds ? [] : (array)($data['stock'] ?? []));
                // ціни та залишки по кожному варіанту
                foreach ($variantIds as $vid) {
                    self::syncStore($id, $vid, (array)($data['vprice'][$vid] ?? []), (array)($data['vstock'][$vid] ?? []));
                }
            }
            flash('success', 'Зміни збережено');
            // Фільтр переживає збереження: інакше після кожного «Зберегти все»
            // людину викидало б у повний список і добірку треба було б робити
            // заново — а саме заради неї сюди й заходять.
            redirect('/admin/products/bulk' . self::bulkQuery());
        }

        [$where, $params] = self::bulkFilter();
        $products = DB::all(
            'SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id = p.category_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY c.sort, p.name', $params);
        $variants = [];
        foreach (DB::all('SELECT * FROM product_variants WHERE active = 1 ORDER BY sort, id') as $v) {
            $variants[(int)$v['product_id']][] = $v;
        }
        $prices = []; $stocks = []; $vprices = []; $vstocks = [];
        foreach (DB::all('SELECT * FROM store_prices') as $r) {
            if ($r['variant_id'] === null) $prices[(int)$r['product_id']][(int)$r['store_id']] = $r['price'];
            else $vprices[(int)$r['variant_id']][(int)$r['store_id']] = $r['price'];
        }
        foreach (DB::all('SELECT * FROM store_stock') as $r) {
            if ($r['variant_id'] === null) $stocks[(int)$r['product_id']][(int)$r['store_id']] = $r['qty'];
            else $vstocks[(int)$r['variant_id']][(int)$r['store_id']] = $r['qty'];
        }
        View::show('admin/products/bulk', [
            'products' => $products, 'stores' => $stores, 'variants' => $variants,
            'prices' => $prices, 'stocks' => $stocks, 'vprices' => $vprices, 'vstocks' => $vstocks,
            'categories' => Catalog::categories(), 'brands' => Catalog::brands(),
            'f' => self::bulkInput(), 'query' => self::bulkQuery(),
            'page_title' => 'Масове редагування — адмінка',
        ], 'layouts/admin');
    }

    /** Значення фільтра як їх увів користувач — і для запиту, і для форми */
    private static function bulkInput(): array
    {
        return [
            'q' => trim((string)($_GET['q'] ?? '')),
            'cat' => (int)($_GET['cat'] ?? 0),
            'brand' => (int)($_GET['brand'] ?? 0),
            // «є що правити» — найчастіша причина сюди зайти
            'only' => in_array($_GET['only'] ?? '', ['zero', 'noprice', 'variants'], true) ? $_GET['only'] : '',
        ];
    }

    /** @return array{0:string[],1:array} умови та параметри для вибірки товарів */
    private static function bulkFilter(): array
    {
        $f = self::bulkInput();
        $where = ['1=1'];
        $params = [];
        if ($f['q'] !== '') {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $params[] = '%' . $f['q'] . '%';
            $params[] = '%' . $f['q'] . '%';
        }
        if ($f['cat']) { $where[] = 'p.category_id = ?'; $params[] = $f['cat']; }
        if ($f['brand']) {
            $where[] = 'EXISTS (SELECT 1 FROM product_brands pb WHERE pb.product_id = p.id AND pb.brand_id = ?)';
            $params[] = $f['brand'];
        }
        // Порожній склад рахуємо по всій мережі й з урахуванням варіантів —
        // саме так, як його бачить покупець.
        if ($f['only'] === 'zero') {
            $where[] = 'COALESCE((SELECT SUM(ss.qty) FROM store_stock ss WHERE ss.product_id = p.id), 0) <= 0';
        }
        if ($f['only'] === 'noprice') $where[] = '(p.base_price IS NULL OR p.base_price = 0)';
        if ($f['only'] === 'variants') {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.active = 1)';
        }
        return [$where, $params];
    }

    /** Рядок запиту поточного фільтра — щоб він пережив збереження */
    private static function bulkQuery(): string
    {
        $f = array_filter(self::bulkInput(), fn($v) => $v !== '' && $v !== 0);
        return $f ? '?' . http_build_query($f) : '';
    }

    public static function create(): never
    {
        Auth::requireCap('products.manage');
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
                'low_stock_threshold' => ($_POST['low_stock_threshold'] ?? '') === '' ? null : (int)$_POST['low_stock_threshold'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            self::syncBrands($id, (array)($_POST['brand_ids'] ?? []));
            flash('success', 'Товар створено — додайте фото, атрибути та ціни');
            redirect('/admin/products/' . $id);
        }
        View::show('admin/products/form', [
            'p' => null, 'categories' => Catalog::categories(), 'stores' => Catalog::stores(),
            'variants' => [], 'attrs' => [], 'images' => [], 'store_prices' => [], 'store_stock' => [],
            'variant_options' => [], 'variant_prices' => [], 'variant_stock' => [], 'dict' => Attrs::all(),
            'page_title' => 'Новий товар — адмінка',
        ], 'layouts/admin');
    }

    public static function edit(int $id): never
    {
        $p = DB::row('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) redirect('/admin/products');

        if (is_post()) { self::save($id, $p); }

        $store_prices = []; $store_stock = [];
        $variant_prices = []; $variant_stock = [];
        foreach (DB::all('SELECT * FROM store_prices WHERE product_id = ?', [$id]) as $r) {
            if ($r['variant_id'] === null) $store_prices[$r['store_id']] = $r['price'];
            else $variant_prices[(int)$r['variant_id']][(int)$r['store_id']] = $r['price'];
        }
        foreach (DB::all('SELECT * FROM store_stock WHERE product_id = ?', [$id]) as $r) {
            if ($r['variant_id'] === null) $store_stock[$r['store_id']] = $r['qty'];
            else $variant_stock[(int)$r['variant_id']][(int)$r['store_id']] = $r['qty'];
        }

        View::show('admin/products/form', [
            'p' => $p, 'categories' => Catalog::categories(), 'stores' => Catalog::stores(),
            'variants' => DB::all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort, id', [$id]),
            'variant_options' => Attrs::variantOptionsFor($id),
            'attrs' => DB::all('SELECT * FROM product_attrs WHERE product_id = ? ORDER BY sort, id', [$id]),
            'images' => DB::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]),
            'store_prices' => $store_prices, 'store_stock' => $store_stock,
            'variant_prices' => $variant_prices, 'variant_stock' => $variant_stock,
            'dict' => Attrs::all(),
            'page_title' => 'Товар: ' . $p['name'] . ' — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Хто що редагує в товарі.
     * Картка товару (назва, опис, ціни, варіанти, характеристики, фото) — спільна для
     * всього сайту, тож нею керує лише адмін. Продавцю лишаються ціни й залишки його
     * магазинів: саме їх він і веде щодня. Перевірка тут — джерело істини, форма лише
     * повторює її візуально.
     */
    private static function save(int $id, array $p): void
    {
        $action = $_POST['_action'] ?? 'save';
        $canCard = Auth::can('products.manage');

        // Дозволений список, а не заборонений: без products.manage проходить лише
        // звичайне збереження, і в ньому далі приймаються самі ціни та залишки.
        // Нова дія, додана сюди пізніше, за замовчуванням буде закрита — саме так
        // і треба: помилка має відмовляти, а не відкривати доступ.
        if (!$canCard && $action !== 'save') {
            flash('error', 'Змінювати картку товару може лише адміністратор.');
            redirect('/admin/products/' . $id);
        }

        if ($action === 'delete' && $canCard) {
            $paths = array_column(DB::all('SELECT path FROM product_images WHERE product_id = ?', [$id]), 'path');
            foreach (DB::all('SELECT id FROM product_variants WHERE product_id = ?', [$id]) as $v) {
                DB::delete('variant_options', 'variant_id = ?', [(int)$v['id']]);
            }
            foreach (['product_images','product_variants','product_attrs','product_brands','store_prices','store_stock'] as $t) DB::delete($t, 'product_id = ?', [$id]);
            DB::delete('products', 'id = ?', [$id]);
            // видаляти файл лише якщо фото не прикріплене ще до якогось іншого товару/банера/галереї
            foreach (array_unique($paths) as $path) if (!Media::usage($path)) Images::delete($path);
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
                self::syncImages($id);
                flash('success', 'Фото додано до товару');
            } else flash('error', 'Фото не знайдено');
            redirect('/admin/products/' . $id);
        }

        if ($action === 'upload_image') {
            $res = Images::saveUpload($_FILES['image'] ?? [], 'p' . $id);
            if ($res) {
                [$path, $w, $h, $bytes] = $res;
                DB::insert('product_images', ['product_id' => $id, 'path' => $path, 'width' => $w, 'height' => $h, 'bytes' => $bytes, 'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM product_images WHERE product_id = ?', [$id])]);
                self::syncImages($id);
                flash('success', 'Фото додано (' . $w . '×' . $h . ', ' . round($bytes/1024) . ' КБ)');
            } else flash('error', 'Не вдалося завантажити фото');
            redirect('/admin/products/' . $id);
        }

        if ($action === 'delete_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            $img = DB::row('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id]);
            if ($img) {
                DB::delete('product_images', 'id = ?', [$imgId]);
                self::syncImages($id);
                // видаляти файл лише якщо це фото не прикріплене ще десь (інший товар, банер, галерея)
                if (!Media::usage($img['path'])) Images::delete($img['path']);
                flash('success', 'Фото видалено');
            }
            redirect('/admin/products/' . $id);
        }

        // Головне фото — це перше в списку: робимо його головним, посунувши на початок
        if ($action === 'main_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            if (DB::row('SELECT 1 FROM product_images WHERE id = ? AND product_id = ?', [$imgId, $id])) {
                DB::update('product_images', ['sort' => -1], 'id = ?', [$imgId]);
                self::syncImages($id);
                flash('success', 'Головне фото змінено');
            }
            redirect('/admin/products/' . $id);
        }

        // Порядок додаткових фото: міняємо місцями із сусідом
        if ($action === 'move_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            $up = ($_POST['dir'] ?? 'up') === 'up';
            $ids = array_map('intval', array_column(
                DB::all('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort, id', [$id]), 'id'));
            $i = array_search($imgId, $ids, true);
            $j = $i === false ? false : $i + ($up ? -1 : 1);
            if ($i !== false && $j !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                foreach ($ids as $sort => $iid) DB::update('product_images', ['sort' => $sort], 'id = ?', [$iid]);
                self::syncImages($id);
                flash('success', $i === 0 || $j === 0 ? 'Порядок змінено — головним стало інше фото' : 'Порядок фото змінено');
            }
            redirect('/admin/products/' . $id);
        }

        // Генератор варіантів: усі комбінації обраних значень. Працює разом зі збереженням,
        // тому незбережені правки у формі не губляться.
        $generated = 0;
        if ($action === 'gen_variants') {
            $selection = [];
            foreach ((array)($_POST['gen'] ?? []) as $aid => $valueIds) {
                $ids = array_filter(array_map('intval', (array)$valueIds));
                if ($ids) $selection[(int)$aid] = $ids;
            }
            if ($selection) $generated = Attrs::generateVariants($id, $selection);
        }

        // Основне збереження (картка товару — лише з products.manage)
        if ($canCard) {
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
                'low_stock_threshold' => ($_POST['low_stock_threshold'] ?? '') === '' ? null : (int)$_POST['low_stock_threshold'],
                'updated_at' => now(),
            ], 'id = ?', [$id]);
            self::syncBrands($id, (array)($_POST['brand_ids'] ?? []));

            // Варіанти: назви з характеристик не чіпаємо — вони збираються автоматично
            $withOptions = Attrs::variantOptionsFor($id);
            $sort = 0;
            foreach ((array)($_POST['variant'] ?? []) as $vid => $v) {
                if (str_starts_with((string)$vid, 'new')) continue;
                $vid = (int)$vid;
                if (!empty($v['_delete'])) { self::deleteVariant($id, $vid); continue; }
                $name = trim($v['name'] ?? '');
                $auto = !empty($withOptions[$vid]);
                if (!$auto && $name === '') continue; // текстовий варіант без назви — не чіпаємо
                $upd = [
                    'price' => ($v['price'] ?? '') === '' ? null : (float)$v['price'],
                    'sku' => trim($v['sku'] ?? '') ?: null,
                    'active' => !empty($v['active']) ? 1 : 0,
                    'sort' => $sort++,
                ];
                if (!$auto) $upd['name'] = $name;
                DB::update('product_variants', $upd, 'id = ? AND product_id = ?', [$vid, $id]);
            }
            foreach ((array)($_POST['variant'] ?? []) as $vid => $v) {
                if (!str_starts_with((string)$vid, 'new')) continue;
                if (trim($v['name'] ?? '') === '') continue;
                DB::insert('product_variants', [
                    'product_id' => $id, 'name' => trim($v['name']),
                    'price' => ($v['price'] ?? '') === '' ? null : (float)$v['price'],
                    'sku' => trim($v['sku'] ?? '') ?: null,
                    'active' => !empty($v['active']) ? 1 : 0,
                    'sort' => $sort++,
                ]);
            }

            // Характеристики: словник + значення зі списку (рядки перезаписуються цілком)
            Attrs::saveProductAttrs($id, (array)($_POST['attr'] ?? []), (int)($_POST['category_id'] ?? $p['category_id']));
        }

        // Ціни та залишки: по товару загалом і окремо по кожному варіанту.
        // Якщо варіанти є — залишок товару без варіанта не приймаємо: наявність рахується з варіантів.
        $hasVariants = (bool)DB::val('SELECT 1 FROM product_variants WHERE product_id = ? AND active = 1 LIMIT 1', [$id]);
        self::syncStore($id, null, (array)($_POST['store_price'] ?? []), $hasVariants ? [] : (array)($_POST['store_stock'] ?? []));
        $variantIds = array_map('intval', array_column(DB::all('SELECT id FROM product_variants WHERE product_id = ?', [$id]), 'id'));
        foreach ((array)($_POST['vprice'] ?? []) as $vid => $prices) {
            if (!in_array((int)$vid, $variantIds, true)) continue;
            self::syncStore($id, (int)$vid, (array)$prices, (array)($_POST['vstock'][$vid] ?? []));
        }

        if ($action === 'gen_variants') {
            flash($generated ? 'success' : 'error', $generated
                ? 'Збережено. Створено варіантів: ' . $generated . ' — задайте їм ціни й залишки.'
                : 'Збережено, але нових комбінацій не додано: оберіть значення, яких ще немає.');
        } else {
            flash('success', 'Збережено');
        }
        redirect('/admin/products/' . $id);
    }

    /**
     * Наводить порядок у фото товару: sort = 0,1,2… без дірок,
     * а `products.image` (головне фото) завжди дорівнює першому в списку.
     */
    private static function syncImages(int $productId): void
    {
        $rows = DB::all('SELECT id, path, sort FROM product_images WHERE product_id = ? ORDER BY sort, id', [$productId]);
        foreach ($rows as $i => $r) {
            if ((int)$r['sort'] !== $i) DB::update('product_images', ['sort' => $i], 'id = ?', [(int)$r['id']]);
        }
        DB::update('products', ['image' => $rows[0]['path'] ?? null, 'updated_at' => now()], 'id = ?', [$productId]);
    }

    /** Видалення варіанта разом з його цінами, залишками та характеристиками */
    private static function deleteVariant(int $productId, int $variantId): void
    {
        DB::delete('variant_options', 'variant_id = ?', [$variantId]);
        DB::delete('store_prices', 'product_id = ? AND variant_id = ?', [$productId, $variantId]);
        DB::delete('store_stock', 'product_id = ? AND variant_id = ?', [$productId, $variantId]);
        DB::delete('product_variants', 'id = ? AND product_id = ?', [$variantId, $productId]);
    }

    /** Ціни/залишки по магазинах для товару ($variantId = null) або конкретного варіанта */
    private static function syncStore(int $productId, ?int $variantId, array $prices, array $stock): void
    {
        $cond = $variantId === null ? 'variant_id IS NULL' : 'variant_id = ?';
        $extra = $variantId === null ? [] : [$variantId];
        // Право на дію і межі, у яких вона дозволена, — різні речі, тому перевіряємо
        // обидві: чи взагалі можна правити ціни/залишки і чи належить точка тобі.
        // storeIds() для адміна повертає всі магазини, тож окремої гілки не треба.
        $canPrice = Auth::can('products.price');
        $canStock = Auth::can('products.stock');
        $mine = Auth::storeIds();

        foreach ($canPrice ? $prices : [] as $sid => $priceVal) {
            $sid = (int)$sid;
            if (!in_array($sid, $mine, true)) continue;
            $exists = DB::row("SELECT id FROM store_prices WHERE product_id = ? AND store_id = ? AND $cond",
                array_merge([$productId, $sid], $extra));
            if ($priceVal === '') { if ($exists) DB::delete('store_prices', 'id = ?', [$exists['id']]); }
            elseif ($exists) DB::update('store_prices', ['price' => (float)$priceVal], 'id = ?', [$exists['id']]);
            else DB::insert('store_prices', ['product_id' => $productId, 'store_id' => $sid, 'variant_id' => $variantId, 'price' => (float)$priceVal]);
        }
        foreach ($canStock ? $stock : [] as $sid => $qty) {
            $sid = (int)$sid;
            if (!in_array($sid, $mine, true)) continue;
            $exists = DB::row("SELECT id FROM store_stock WHERE product_id = ? AND store_id = ? AND $cond",
                array_merge([$productId, $sid], $extra));
            if ($qty === '') { if ($exists) DB::delete('store_stock', 'id = ?', [$exists['id']]); continue; }
            if ($exists) DB::update('store_stock', ['qty' => (int)$qty], 'id = ?', [$exists['id']]);
            else DB::insert('store_stock', ['product_id' => $productId, 'store_id' => $sid, 'variant_id' => $variantId, 'qty' => (int)$qty]);
        }
        // Товар міг щойно зʼявитись — тим, хто його чекав, час дізнатись.
        // Ставимо саме тут: це єдине місце, через яке проходить і збереження
        // картки, і масове редагування. StockWatch сам перевірить, чи є що
        // повідомляти, тож зайвого виклику не буде.
        if ($canStock) StockWatch::fulfil($productId, $variantId);
    }

    /**
     * Бренди товару: приймаємо лише ті id, що справді є в довіднику, —
     * інакше підробленою формою можна було б звʼязати товар із чим завгодно.
     */
    private static function syncBrands(int $productId, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        DB::delete('product_brands', 'product_id = ?', [$productId]);
        foreach ($ids as $bid) {
            if (!DB::row('SELECT id FROM brands WHERE id = ?', [$bid])) continue;
            DB::insert('product_brands', ['product_id' => $productId, 'brand_id' => $bid]);
        }
    }
}
