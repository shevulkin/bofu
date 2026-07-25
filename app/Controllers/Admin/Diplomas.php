<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth;

class Diplomas
{
    public static function index(): never
    {
        Auth::requireAdmin();
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'add' && trim($_POST['number'] ?? '') !== '') {
                try {
                    DB::insert('diplomas', [
                        'number' => strtoupper(trim($_POST['number'])), 'student' => trim($_POST['student'] ?? ''),
                        'course' => trim($_POST['course'] ?? '') ?: null, 'issued_at' => trim($_POST['issued_at'] ?? '') ?: null, 'active' => 1,
                    ]);
                    flash('success', 'Диплом додано');
                } catch (\Throwable $e) { flash('error', 'Такий номер вже існує'); }
            }
            if ($action === 'toggle') DB::query('UPDATE diplomas SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]);
            if ($action === 'delete') DB::delete('diplomas', 'id = ?', [(int)$_POST['id']]);
            redirect('/admin/diplomas');
        }
        View::show('admin/diplomas', [
            'diplomas' => DB::all('SELECT * FROM diplomas ORDER BY id DESC'),
            'page_title' => 'Дипломи — адмінка',
        ], 'layouts/admin');
    }
}
