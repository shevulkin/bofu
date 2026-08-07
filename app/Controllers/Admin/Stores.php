<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Geo;

class Stores
{
    public static function index(): never
    {
        Auth::requireCap('stores.manage');
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add' && trim($_POST['name'] ?? '') !== '') {
                $name = trim($_POST['name']);
                DB::insert('stores', array_merge([
                    'name' => $name, 'slug' => slugify($name) . '-' . random_int(10, 99),
                    'city' => trim($_POST['city'] ?? '') ?: null, 'address' => trim($_POST['address'] ?? '') ?: null,
                    'phone' => trim($_POST['phone'] ?? '') ?: null, 'active' => 1,
                    'sort' => (int)DB::val('SELECT COALESCE(MAX(sort),0)+1 FROM stores'),
                ], self::coords($_POST['coords'] ?? '', $bad)));
                flash('success', $bad ? 'Магазин додано, але координати не розібрались — впишіть їх у рядку нижче'
                                      : 'Магазин додано');
            }
            if ($action === 'save') {
                $badNames = [];
                foreach ((array)($_POST['store'] ?? []) as $id => $s) {
                    $name = trim($s['name'] ?? '');
                    DB::update('stores', array_merge([
                        'name' => $name, 'city' => trim($s['city'] ?? '') ?: null,
                        'address' => trim($s['address'] ?? '') ?: null, 'phone' => trim($s['phone'] ?? '') ?: null,
                        'active' => !empty($s['active']) ? 1 : 0,
                    ], self::coords($s['coords'] ?? '', $bad)), 'id = ?', [(int)$id]);
                    if ($bad) $badNames[] = $name;
                }
                // Про нерозібрані координати кажемо поіменно: збереглося все інше,
                // і мовчазне «Збережено» лишило б людину в упевненості, що мітка є
                flash($badNames ? 'error' : 'success', $badNames
                    ? 'Збережено, але координати не розібрались: ' . implode(', ', $badNames)
                      . '. Потрібна пара чисел «50.4501, 30.5234» або посилання з Google Maps.'
                    : 'Збережено');
            }
            redirect('/admin/stores');
        }
        View::show('admin/stores', [
            'stores' => DB::all('SELECT * FROM stores ORDER BY sort, id'),
            'maps_key' => Geo::key(),
            'page_title' => 'Магазини — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Поле координат у стовпці бази.
     *
     * Порожнє поле — це «мітки немає», і його треба вміти зняти, тому порожнеча
     * пише null, а не мовчки лишає старе. А от нерозбірливий текст не чіпає
     * нічого: людина описалась, і стерти через це правильні координати було б
     * найгіршою з можливих відповідей.
     */
    private static function coords(string $raw, ?bool &$bad = null): array
    {
        $raw = trim($raw);
        $bad = false;
        if ($raw === '') return ['lat' => null, 'lng' => null];
        $p = Geo::parse($raw);
        if ($p === null) { $bad = true; return []; }
        return ['lat' => $p['lat'], 'lng' => $p['lng']];
    }
}
