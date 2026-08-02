<?php
declare(strict_types=1);

namespace Controllers\Admin;

use View, Auth, Settings, WebPush;

class SettingsAdmin
{
    private const TEXT_KEYS = [
        'seo_title' => 'SEO: заголовок сайту',
        'seo_description' => 'SEO: опис сайту',
        'telegram_bot_token' => 'Telegram: токен бота',
        'viber_bot_token' => 'Viber: токен бота',
        'np_api_key' => 'Нова Пошта: API-ключ',
        'youtube_channel' => 'YouTube: канал (@handle, посилання або UC-ID)',
        'mail_from' => 'Email відправника',
        'google_client_id' => 'Google OAuth: Client ID',
        'google_client_secret' => 'Google OAuth: Client Secret',
    ];

    private const TOGGLES = [
        'notify_all_enabled' => 'Усі сповіщення (головний вимикач)',
        'notify_telegram_enabled' => 'Канал Telegram',
        'notify_viber_enabled' => 'Канал Viber',
        'notify_email_enabled' => 'Канал Email',
        'notify_push_enabled' => 'Канал Push',
    ];

    public static function index(): never
    {
        Auth::requireCap('settings.manage');
        if (is_post()) {
            foreach (self::TOGGLES as $key => $label) {
                Settings::set($key, isset($_POST['toggle'][$key]) ? '1' : '0');
            }
            // окремо від TOGGLES: там усе типово увімкнене, а індексація має бути дозволена за замовчуванням
            Settings::set('seo_noindex', isset($_POST['seo_noindex']) ? '1' : '0');
            $oldViber = Settings::get('viber_bot_token', '');
            foreach (self::TEXT_KEYS as $key => $label) {
                if (isset($_POST['text'][$key])) Settings::set($key, trim($_POST['text'][$key]));
            }
            Settings::set('yt_cache_time', '0'); // оновити кеш YouTube після зміни налаштувань
            // Viber: при зміні токена реєструємо webhook (працює лише на публічному HTTPS)
            $newViber = Settings::get('viber_bot_token', '');
            if ($newViber && $newViber !== $oldViber) {
                $res = \Viber::setWebhook();
                if (($res['status'] ?? -1) === 0) flash('success', 'Viber webhook зареєстровано');
                else flash('error', 'Viber: webhook не зареєстровано (локально це нормально — запрацює на хостингу). ' . ($res['status_message'] ?? ''));
            }
            flash('success', 'Налаштування збережено');
            redirect('/admin/settings');
        }
        [$vapidPub] = WebPush::ensureKeys();
        View::show('admin/settings', [
            'toggles' => self::TOGGLES, 'text_keys' => self::TEXT_KEYS,
            'vapid_public' => $vapidPub,
            'page_title' => 'Налаштування — адмінка',
        ], 'layouts/admin');
    }
}
