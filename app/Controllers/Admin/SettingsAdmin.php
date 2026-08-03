<?php
declare(strict_types=1);

namespace Controllers\Admin;

use View, Auth, Settings, WebPush, BotAuth, IntegrationCheck, RateLimit, Catalog, OrderFlow;

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
        'bot_site_url' => 'Адреса сайту для кнопки в боті (напр. https://bofu.ua)',
    ];

    private const TOGGLES = [
        'notify_all_enabled' => 'Усі сповіщення (головний вимикач)',
        'notify_telegram_enabled' => 'Канал Telegram',
        'notify_viber_enabled' => 'Канал Viber',
        'notify_email_enabled' => 'Канал Email',
        'notify_push_enabled' => 'Канал Push',
    ];

    /**
     * Перевірка інтеграцій без збереження: беремо те, що зараз у формі.
     * Ліміт — бо кожен виклик ходить у три чужі API, і кнопку легко затиснути.
     */
    public static function check(): never
    {
        Auth::requireCap('settings.manage');
        RateLimit::guard('settings_check', 30, 3600);
        json_response(['ok' => true, 'rows' => IntegrationCheck::run((array)($_POST['text'] ?? []))]);
    }

    public static function index(): never
    {
        Auth::requireCap('settings.manage');
        if (is_post()) {
            // Головний вимикач — гейт над канальними (Notify::fire), тож у формі
            // вони неактивні, поки він вимкнений. Неактивне поле в POST не
            // приходить, тому за вимкненого головного канальні НЕ чіпаємо:
            // інакше одне вимкнення мовчки стирало б усі налаштування каналів,
            // і після повернення головного все прийшлося б розставляти заново.
            $master = isset($_POST['toggle']['notify_all_enabled']);
            Settings::set('notify_all_enabled', $master ? '1' : '0');
            if ($master) {
                foreach (self::TOGGLES as $key => $label) {
                    if ($key === 'notify_all_enabled') continue;
                    Settings::set($key, isset($_POST['toggle'][$key]) ? '1' : '0');
                }
            }
            // окремо від TOGGLES: там усе типово увімкнене, а індексація має бути дозволена за замовчуванням
            Settings::set('seo_noindex', isset($_POST['seo_noindex']) ? '1' : '0');
            // Магазин, якому дістаються позиції, яких немає ніде. Приймаємо лише
            // чинну активну точку: неіснуючий id мовчки відкотив би нас до
            // «першої активної», і власник вважав би, що вибір діє.
            $store = (int)($_POST['default_store_id'] ?? 0);
            $valid = $store && in_array($store, OrderFlow::activeStoreIds(), true);
            Settings::set('default_store_id', $valid ? (string)$store : '');
            $oldViber = Settings::get('viber_bot_token', '');
            foreach (self::TEXT_KEYS as $key => $label) {
                if (isset($_POST['text'][$key])) Settings::set($key, trim($_POST['text'][$key]));
            }
            // Тексти бота: порожнє поле = повернути типовий текст, а не показати
            // людині порожнє повідомлення. Тому зберігаємо '' і підставляємо
            // замовчування при читанні (BotAuth::text).
            foreach (array_keys(BotAuth::TEXTS) as $key) {
                if (isset($_POST['bot'][$key])) Settings::set($key, trim($_POST['bot'][$key]));
            }
            Settings::set('yt_cache_time', '0'); // оновити кеш YouTube після зміни налаштувань
            // Viber: при зміні токена реєструємо webhook (працює лише на публічному HTTPS)
            $newViber = Settings::get('viber_bot_token', '');
            if ($newViber && $newViber !== $oldViber) {
                // Токен змінився — бот може бути вже інший, тож кеш URI викидаємо
                // й питаємо заново. Без URI кнопка входу через Viber не показується,
                // і мовчазна невдача тут виглядала б як «вхід кудись подівся».
                \Viber::forgetUri();
                $uri = \Viber::uri();
                if ($uri === '') flash('error', 'Viber: не вдалося отримати адресу бота — перевірте токен. Кнопка входу через Viber поки не показуватиметься.');

                $res = \Viber::setWebhook();
                if (($res['status'] ?? -1) === 0) flash('success', 'Viber webhook зареєстровано' . ($uri ? ', бот: ' . $uri : ''));
                else flash('error', 'Viber: webhook не зареєстровано (локально це нормально — запрацює на хостингу). ' . ($res['status_message'] ?? ''));
            }
            flash('success', 'Налаштування збережено');
            redirect('/admin/settings');
        }
        [$vapidPub] = WebPush::ensureKeys();
        View::show('admin/settings', [
            'toggles' => self::TOGGLES, 'text_keys' => self::TEXT_KEYS,
            'stores' => Catalog::stores(),
            'default_store_id' => OrderFlow::defaultStoreId(),
            'default_store_set' => Settings::get('default_store_id', '') !== '',
            'bot_texts' => BotAuth::TEXTS, 'bot_site' => BotAuth::siteUrl(),
            'vapid_public' => $vapidPub,
            'page_title' => 'Налаштування — адмінка',
        ], 'layouts/admin');
    }
}
