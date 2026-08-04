<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog;

/**
 * Довідник брендів: чий товар. Один бренд позначений «наш» — саме він дає
 * право написати покупцю «ми виробник», решта отримує нейтральний текст.
 */
class Brands
{
    public static function index(): never
    {
        Auth::requireCap('catalog.manage');
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add') self::add();
            if ($action === 'save') self::save();
            if ($action === 'delete') self::delete((int)($_POST['id'] ?? 0));
            redirect('/admin/brands');
        }
        View::show('admin/brands', [
            'brands' => Catalog::brands(),
            // скільки товарів на кожному бренді: без цього не видно, що можна прибрати
            'counts' => self::counts(),
            'own_default' => Catalog::ownBrandName(),
            'page_title' => 'Бренди — адмінка',
        ], 'layouts/admin');
    }

    /** @return array<int,int> [brand_id => скільки товарів] */
    private static function counts(): array
    {
        $out = [];
        foreach (DB::all('SELECT brand_id, COUNT(*) AS n FROM product_brands GROUP BY brand_id') as $r) {
            $out[(int)$r['brand_id']] = (int)$r['n'];
        }
        return $out;
    }

    private static function add(): void
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { flash('error', 'Вкажіть назву бренду'); return; }
        if (DB::row('SELECT id FROM brands WHERE name = ?', [$name])) {
            flash('error', 'Такий бренд уже є'); return;
        }
        $id = DB::insert('brands', [
            'name' => $name,
            'slug' => self::freeSlug($name),
            'own' => !empty($_POST['own']) ? 1 : 0,
            'active' => 1,
            'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM brands'),
        ]);
        if (!empty($_POST['own'])) self::keepSingleOwn($id);
        flash('success', 'Бренд додано');
    }

    private static function save(): void
    {
        foreach ((array)($_POST['brand'] ?? []) as $id => $b) {
            $id = (int)$id;
            $name = trim($b['name'] ?? '');
            if ($name === '') continue;   // порожня назва — це не перейменування, а промах
            $busy = DB::row('SELECT id FROM brands WHERE name = ? AND id <> ?', [$name, $id]);
            if ($busy) { flash('error', 'Бренд «' . $name . '» уже є — назви не повторюються'); continue; }
            DB::update('brands', [
                'name' => $name,
                // slug завжди перераховуємо з назви: інакше перейменований бренд
                // лишається під чужою адресою, а звільнена назва дістає при
                // створенні slug із хвостиком. Для незмінної назви freeSlug()
                // повертає той самий рядок, тож зайвих правок не буде.
                'slug' => self::freeSlug($name, $id),
                'own' => !empty($b['own']) ? 1 : 0,
                'active' => !empty($b['active']) ? 1 : 0,
            ], 'id = ?', [$id]);
            if (!empty($b['own'])) self::keepSingleOwn($id);
        }
        flash('success', 'Збережено');
    }

    /**
     * Видаляємо лише те, на чому не висять товари: інакше позиції мовчки
     * лишились би без бренду, а сайт — без відповіді, чий це товар.
     */
    private static function delete(int $id): void
    {
        if (!$id) return;
        $used = (int)DB::val('SELECT COUNT(*) FROM product_brands WHERE brand_id = ?', [$id]);
        if ($used) {
            flash('error', 'Бренд не видалено: на ньому ' . $used . ' товар(ів). '
                . 'Спершу перепідпишіть їх або зніміть галку «Активний».');
            return;
        }
        DB::delete('brands', 'id = ?', [$id]);
        flash('success', 'Бренд видалено');
    }

    /** «Наш» може бути лише один — інакше незрозуміло, від чийого імені казати «ми виробник» */
    private static function keepSingleOwn(int $keepId): void
    {
        DB::query('UPDATE brands SET own = 0 WHERE id <> ?', [$keepId]);
    }

    /** $exceptId — власний рядок при перейменуванні, інакше він займе слаг сам у себе */
    private static function freeSlug(string $name, int $exceptId = 0): string
    {
        $base = slugify($name) ?: 'brand';
        $slug = $base; $i = 1;
        while (DB::row('SELECT id FROM brands WHERE slug = ? AND id <> ?', [$slug, $exceptId])) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
