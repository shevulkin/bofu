<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog;

class Users
{
    public static function index(): never
    {
        Auth::requireAdmin();
        if (is_post()) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $user = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($user) {
                $role = $_POST['role'] ?? $user['role'];
                if (!in_array($role, ['admin', 'seller', 'editor', 'customer'], true)) $role = $user['role'];
                if ((int)$user['id'] === (int)Auth::id() && $role !== 'admin') {
                    flash('error', 'Не можна зняти адмін-права із самого себе');
                } else {
                    DB::update('users', [
                        'role' => $role, 'active' => isset($_POST['active']) ? 1 : 0,
                        'tg_chat_id' => trim($_POST['tg_chat_id'] ?? '') ?: null,
                    ], 'id = ?', [$uid]);
                    DB::delete('seller_stores', 'user_id = ?', [$uid]);
                    if ($role === 'seller') {
                        foreach ((array)($_POST['stores'] ?? []) as $sid) {
                            DB::insert('seller_stores', ['user_id' => $uid, 'store_id' => (int)$sid]);
                        }
                    }
                    flash('success', 'Користувача оновлено');
                }
            }
            redirect('/admin/users');
        }
        $users = DB::all('SELECT * FROM users ORDER BY id');
        $sellerStores = [];
        foreach (DB::all('SELECT * FROM seller_stores') as $r) $sellerStores[$r['user_id']][] = (int)$r['store_id'];
        View::show('admin/users', [
            'users' => $users, 'stores' => Catalog::stores(), 'seller_stores' => $sellerStores,
            'page_title' => 'Користувачі та ролі — адмінка',
        ], 'layouts/admin');
    }
}
