<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Auth, Csrf, AuthTokens, Telegram, Viber;

class Profile
{
    public static function index(): never
    {
        if (!Auth::check()) redirect('/');
        $u = Auth::user();

        if (is_post()) {
            Csrf::verify();
            $phone = AuthTokens::normPhone($_POST['phone'] ?? '');
            if (!$phone) {
                flash('error', 'Вкажіть коректний український номер телефону (напр. 067 123 45 67)');
            } else {
                $busy = DB::row('SELECT id FROM users WHERE phone = ? AND id != ?', [$phone, $u['id']]);
                if ($busy) flash('error', 'Цей номер вже привʼязано до іншого акаунта');
                else {
                    DB::update('users', ['phone' => $phone, 'name' => trim($_POST['name'] ?? $u['name']) ?: $u['name']], 'id = ?', [$u['id']]);
                    flash('success', 'Профіль збережено');
                }
            }
            redirect('/profile');
        }

        View::show('account/profile', [
            'u' => DB::row('SELECT * FROM users WHERE id = ?', [$u['id']]),
            'tg_ready' => Telegram::configured(),
            'tg_bot' => Telegram::configured() ? Telegram::username() : '',
            'viber_ready' => Viber::configured(),
            'viber_uri' => Viber::configured() ? Viber::uri() : '',
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
