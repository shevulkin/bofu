<?php
declare(strict_types=1);

namespace Controllers;

use DB, Auth, Csrf, Settings, WebPush, Viber;

class Api
{
    /** Пошук міст Нової Пошти (потрібен API-ключ у налаштуваннях) */
    public static function npCities(): never
    {
        $q = trim($_GET['q'] ?? '');
        $key = Settings::get('np_api_key');
        if (!$key || mb_strlen($q) < 2) json_response(['items' => []]);
        $resp = self::np($key, 'Address', 'searchSettlements', ['CityName' => $q, 'Limit' => '10']);
        $items = [];
        foreach (($resp['data'][0]['Addresses'] ?? []) as $a) {
            $items[] = ['ref' => $a['DeliveryCity'] ?? '', 'label' => $a['Present'] ?? ''];
        }
        json_response(['items' => $items]);
    }

    public static function npWarehouses(): never
    {
        $cityRef = trim($_GET['city'] ?? '');
        $key = Settings::get('np_api_key');
        if (!$key || !$cityRef) json_response(['items' => []]);
        $resp = self::np($key, 'Address', 'getWarehouses', ['CityRef' => $cityRef, 'Limit' => '80']);
        $items = [];
        foreach (($resp['data'] ?? []) as $w) $items[] = $w['Description'] ?? '';
        json_response(['items' => $items]);
    }

    private static function np(string $key, string $model, string $method, array $props): array
    {
        $ch = curl_init('https://api.novaposhta.ua/v2.0/json/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'apiKey' => $key, 'modelName' => $model, 'calledMethod' => $method, 'methodProperties' => $props,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $json = json_decode((string)$resp, true);
        return is_array($json) ? $json : [];
    }

    /** Webhook від Viber (реєструється автоматично при збереженні токена) */
    public static function viberWebhook(): never
    {
        $body = file_get_contents('php://input') ?: '';
        $sig = $_SERVER['HTTP_X_VIBER_CONTENT_SIGNATURE'] ?? '';
        if (!Viber::configured() || !$sig || !Viber::verifySignature($body, $sig)) {
            json_response(['status' => 0]); // webhook-перевірка Viber теж приходить сюди
        }
        $ev = json_decode($body, true) ?: [];
        try { Viber::handleEvent($ev); } catch (\Throwable $e) { \Notify::log('viber: ' . $e->getMessage()); }
        json_response(['status' => 0]);
    }

    public static function pushSubscribe(): never
    {
        if (!Auth::isStaff()) json_response(['ok' => false], 403);
        // Без CSRF стороння сторінка могла б простим POST (text/plain) підписати свій
        // endpoint на сповіщення адміна — а в них номер, ім'я, телефон і сума замовлення.
        Csrf::verify();
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            json_response(['ok' => false], 422);
        }
        $exists = DB::row('SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?', [Auth::id(), $data['endpoint']]);
        if (!$exists) {
            DB::insert('push_subscriptions', [
                'user_id' => Auth::id(), 'endpoint' => $data['endpoint'],
                'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'], 'created_at' => now(),
            ]);
        }
        json_response(['ok' => true]);
    }
}
