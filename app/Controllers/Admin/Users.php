<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, Roles;

class Users
{
    public static function index(): never
    {
        Auth::requireCap('users.manage');
        if (is_post()) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $user = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($user) {
                $roles = array_values(array_intersect(
                    array_map('strval', (array)($_POST['roles'] ?? [])), Roles::assignable()));
                // інакше можна лишити систему без жодного адміністратора
                if ((int)$user['id'] === (int)Auth::id() && !in_array(Roles::ADMIN, $roles, true)) {
                    flash('error', 'Не можна зняти адмін-права із самого себе');
                } else {
                    DB::update('users', [
                        'active' => isset($_POST['active']) ? 1 : 0,
                        'tg_chat_id' => trim($_POST['tg_chat_id'] ?? '') ?: null,
                    ], 'id = ?', [$uid]);
                    self::saveRoles($uid, $roles);
                    self::saveStores($uid, (array)($_POST['stores'] ?? []));
                    Auth::forgetRoles($uid);
                    flash('success', 'Користувача оновлено');
                }
            }
            redirect('/admin/users');
        }
        $userRoles = [];
        foreach (DB::all('SELECT user_id, role FROM user_roles') as $r) {
            $userRoles[(int)$r['user_id']][] = (string)$r['role'];
        }
        $sellerStores = [];
        foreach (DB::all('SELECT * FROM seller_stores') as $r) $sellerStores[$r['user_id']][] = (int)$r['store_id'];
        View::show('admin/users', [
            'users' => DB::all('SELECT * FROM users ORDER BY id'),
            'stores' => Catalog::stores(),
            'seller_stores' => $sellerStores,
            'user_roles' => $userRoles,
            'assignable' => Roles::assignable(),
            'page_title' => 'Користувачі та ролі — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Ролі зберігаємо різницею, а не «стерти й записати»: так ролі, яких немає
     * у формі (наприклад ще не задіяний editor), не зникнуть мовчки.
     */
    private static function saveRoles(int $uid, array $roles): void
    {
        $managed = Roles::assignable();
        $current = array_map(fn($r) => (string)$r['role'],
            DB::all('SELECT role FROM user_roles WHERE user_id = ?', [$uid]));
        foreach (array_diff(array_intersect($current, $managed), $roles) as $r) {
            DB::delete('user_roles', 'user_id = ? AND role = ?', [$uid, $r]);
        }
        foreach (array_diff($roles, $current) as $r) {
            DB::insert('user_roles', ['user_id' => $uid, 'role' => $r, 'created_at' => now()]);
        }
    }

    /** Призначення точок не залежить від ролі — це окрема прив'язка людини до магазину */
    private static function saveStores(int $uid, array $ids): void
    {
        $valid = array_map(fn($r) => (int)$r['id'], DB::all('SELECT id FROM stores'));
        $want = array_values(array_unique(array_intersect(array_map('intval', $ids), $valid)));
        DB::delete('seller_stores', 'user_id = ?', [$uid]);
        foreach ($want as $sid) {
            DB::insert('seller_stores', ['user_id' => $uid, 'store_id' => $sid]);
        }
    }
}
