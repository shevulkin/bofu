<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Notify;

class Notifications
{
    public static function index(): never
    {
        Auth::requireCap('notifications.manage');
        if (is_post()) {
            foreach ((array)($_POST['rule'] ?? []) as $id => $r) {
                DB::update('notification_rules', [
                    'enabled' => !empty($r['enabled']) ? 1 : 0,
                    'recipients' => in_array($r['recipients'] ?? '', ['admins', 'sellers', 'admins_sellers'], true) ? $r['recipients'] : 'admins',
                    'template' => $r['template'] ?? '',
                ], 'id = ?', [(int)$id]);
            }
            flash('success', 'Правила сповіщень збережено');
            redirect('/admin/notifications');
        }
        $rules = DB::all('SELECT * FROM notification_rules ORDER BY event, channel');
        $byEvent = [];
        foreach ($rules as $r) $byEvent[$r['event']][] = $r;
        View::show('admin/notifications', [
            'by_event' => $byEvent, 'event_labels' => Notify::EVENTS,
            'channel_labels' => ['telegram' => 'Telegram', 'viber' => 'Viber', 'email' => 'Email', 'push' => 'Push'],
            'vars_hint' => [
                'order_new' => '{number} {name} {phone} {delivery} {address} {items} {shortage} {total} {store}',
                'order_status' => '{number} {status}',
                'user_new' => '{name} {email}',
                'stock_low' => '{product} {qty} {store}',
            ],
            'page_title' => 'Сповіщення — адмінка',
        ], 'layouts/admin');
    }
}
