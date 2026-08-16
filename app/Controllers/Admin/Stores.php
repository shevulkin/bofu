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
                    ], self::coords($s['coords'] ?? '', $bad), self::npSender($s)), 'id = ?', [(int)$id]);
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
        $stores = DB::all('SELECT * FROM stores ORDER BY sort, id');
        View::show('admin/stores', [
            'stores' => $stores,
            'maps_key' => Geo::key(),
            // Звідки починати, коли в точки координат ще немає. Сусідня точка —
            // майже завжди ближче до правди, ніж центр країни: філії відкривають
            // там, де вже працюють. Немає жодної — показуємо Україну цілком.
            'map_start' => self::mapStart($stores),
            'page_title' => 'Магазини — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Звідки точка відправляє посилки Новою Поштою.
     *
     * Місто й відділення приймаються лише разом і лише з довідника: відділення
     * без міста (чи навпаки) — це накладна в нікуди, а назва без Ref нічого не
     * варта для API. Половинчастий набір мовчки обнуляємо — тоді працює спільне
     * відділення з налаштувань, і це чесніше за «збережено», після якого
     * накладна не створюється.
     */
    private static function npSender(array $s): array
    {
        $cut = static fn($v, int $max) => mb_substr(trim((string)($v ?? '')), 0, $max);
        $cityRef = $cut($s['np_city_ref'] ?? '', 60);
        $whRef = $cut($s['np_warehouse_ref'] ?? '', 60);
        $full = $cityRef !== '' && $whRef !== '';
        return [
            'np_city' => $full ? ($cut($s['np_city'] ?? '', 160) ?: null) : null,
            'np_city_ref' => $full ? $cityRef : null,
            'np_warehouse' => $full ? ($cut($s['np_warehouse'] ?? '', 200) ?: null) : null,
            'np_warehouse_ref' => $full ? $whRef : null,
            'np_sender_phone' => $cut($s['np_sender_phone'] ?? '', 30) ?: null,
        ];
    }

    /** @return array{lat:float,lng:float,zoom:int} */
    private static function mapStart(array $stores): array
    {
        foreach ($stores as $s) {
            if (Geo::has($s)) return ['lat' => (float)$s['lat'], 'lng' => (float)$s['lng'], 'zoom' => 12];
        }
        return ['lat' => 49.0, 'lng' => 31.5, 'zoom' => 6];
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
