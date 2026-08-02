<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Auth, Csrf, AuthTokens, Telegram, Viber, Newsletter, Notify;

class Profile
{
    public static function index(): never
    {
        if (!Auth::check()) redirect('/');
        $u = Auth::user();

        if (is_post()) {
            Csrf::verify();
            // сповіщення зберігаються окремою формою — телефон тут не чіпаємо
            if (($_POST['_action'] ?? '') === 'notify') {
                Notify::savePrefs($u, (array)($_POST['n'] ?? []));
                flash('success', 'Налаштування сповіщень збережено');
                redirect('/profile');
            }
            $phone = AuthTokens::normPhone($_POST['phone'] ?? '');
            if (!$phone) {
                flash('error', 'Вкажіть коректний український номер телефону (напр. 067 123 45 67)');
            } else {
                $busy = DB::row('SELECT id FROM users WHERE phone = ? AND id != ?', [$phone, $u['id']]);
                if ($busy) flash('error', 'Цей номер вже привʼязано до іншого акаунта');
                else {
                    $name = trim($_POST['name'] ?? $u['name']) ?: $u['name'];
                    DB::update('users', ['phone' => $phone, 'name' => $name], 'id = ?', [$u['id']]);
                    $email = Newsletter::normEmail((string)$u['email']);
                    if ($email) Newsletter::apply(!empty($_POST['newsletter']), $email, $name, (int)$u['id'], 'profile');
                    flash('success', 'Профіль збережено');
                }
            }
            redirect('/profile');
        }

        $fresh = DB::row('SELECT * FROM users WHERE id = ?', [$u['id']]);
        View::show('account/profile', [
            'u' => $fresh,
            'mail_email' => Newsletter::normEmail((string)$fresh['email']),
            'subscribed' => Newsletter::isSubscribed(Newsletter::normEmail((string)$fresh['email'])),
            'tg_ready' => Telegram::configured(),
            'tg_bot' => Telegram::configured() ? Telegram::username() : '',
            'viber_ready' => Viber::configured(),
            'viber_uri' => Viber::configured() ? Viber::uri() : '',
            'notify_options' => Notify::optionsFor($fresh),
            'notify_events' => Notify::EVENTS,
            'notify_channels' => Notify::CHANNELS,
            'page_title' => 'Мій профіль — ' . cfg('app_name'),
        ]);
    }

    /** Видає токен і посилання на бота для підключення Telegram */
    public static function tgLink(): never
    {
        if (!Auth::check() || !Telegram::configured()) json_response(['ok' => false], 400);
        $t = AuthTokens::create('tg_link', Auth::id());
        json_response(['ok' => true, 'url' => 'https://t.me/' . Telegram::username() . '?start=' . $t['token']]);
    }

    /** Поллінг: чи привʼязався Telegram (обробляє getUpdates) */
    public static function tgCheck(): never
    {
        if (!Auth::check()) json_response(['ok' => false], 400);
        Telegram::processUpdates();
        $u = DB::row('SELECT tg_chat_id FROM users WHERE id = ?', [Auth::id()]);
        json_response(['ok' => true, 'linked' => !empty($u['tg_chat_id'])]);
    }

    public static function viberLink(): never
    {
        if (!Auth::check() || !Viber::configured()) json_response(['ok' => false], 400);
        $t = AuthTokens::create('viber_link', Auth::id());
        $uri = Viber::uri();
        json_response(['ok' => true, 'url' => 'viber://pa?chatURI=' . rawurlencode($uri) . '&context=' . $t['token']]);
    }

    public static function viberCheck(): never
    {
        if (!Auth::check()) json_response(['ok' => false], 400);
        $u = DB::row('SELECT viber_id FROM users WHERE id = ?', [Auth::id()]);
        json_response(['ok' => true, 'linked' => !empty($u['viber_id'])]);
    }
}
