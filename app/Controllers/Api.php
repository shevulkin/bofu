<?php
declare(strict_types=1);

namespace Controllers;

use DB, Auth, Csrf, Settings, WebPush, Viber, NovaPoshta;

class Api
{
    /**
     * Довідники Нової Пошти для підказок у формах.
     *
     * Усі три відповідають однаково — [['ref','label']], — бо однаково й
     * використовуються: людина бачить label, а в приховане поле лягає ref.
     * Без ref накладну потім не створити: НП приймає посилання, а не назву,
     * і зіставити «Відділення №5» з довідником заднім числом не вийде —
     * таких у місті буває кілька, у різних районах.
     */
    public static function npCities(): never
    {
        $q = trim($_GET['q'] ?? '');
        if (!NovaPoshta::enabled() || mb_strlen($q) < 2) json_response(['items' => []]);
        json_response(['items' => NovaPoshta::settlements($q)]);
    }

    /** Відділення й поштомати обраного міста; ?q — фільтр за номером чи адресою */
    public static function npWarehouses(): never
    {
        $ref = trim($_GET['city'] ?? '');
        $q = trim($_GET['q'] ?? '');
        if (!NovaPoshta::enabled() || $ref === '') json_response(['items' => []]);
        json_response(['items' => NovaPoshta::warehouses($ref, $q)]);
    }

    /** Вулиці міста — для доставки курʼєром на адресу */
    public static function npStreets(): never
    {
        $ref = trim($_GET['city'] ?? '');
        $q = trim($_GET['q'] ?? '');
        if (!NovaPoshta::enabled() || $ref === '' || mb_strlen($q) < 2) json_response(['items' => []]);
        json_response(['items' => NovaPoshta::streets($ref, $q)]);
    }

    /**
     * Контрагенти-відправники кабінету НП і їхні контактні особи.
     *
     * Лише для тих, хто налаштовує сайт: це вміст чужого особистого кабінету,
     * і показувати його покупцям нема жодних підстав. Ключ беремо з форми,
     * якщо він там є, — інакше довідник неможливо було б підтягнути, поки
     * новий ключ ще не збережено.
     */
    public static function npSenders(): never
    {
        // Не requireCap(): той відповів би перенаправленням, а тут на іншому
        // кінці fetch — йому потрібен зрозумілий код, а не сторінка входу
        if (!Auth::can('settings.manage')) json_response(['ok' => false, 'error' => 'Немає прав', 'items' => []], 403);
        Csrf::verify();
        $key = trim((string)($_POST['key'] ?? '')) ?: NovaPoshta::key();
        if ($key === '') json_response(['ok' => false, 'error' => 'Спершу вкажіть API-ключ', 'items' => []]);

        $r = NovaPoshta::call('Counterparty', 'getCounterparties',
            ['CounterpartyProperty' => 'Sender', 'Page' => '1'], $key);
        if (!$r['ok']) json_response(['ok' => false, 'error' => $r['error'], 'items' => []]);

        $items = [];
        foreach ($r['data'] as $c) {
            if (empty($c['Ref'])) continue;
            $contacts = [];
            $cr = NovaPoshta::call('Counterparty', 'getCounterpartyContactPersons',
                ['Ref' => (string)$c['Ref'], 'Page' => '1'], $key);
            foreach ($cr['data'] as $p) {
                if (empty($p['Ref'])) continue;
                $contacts[] = [
                    'ref' => (string)$p['Ref'],
                    'label' => (string)($p['Description'] ?? ''),
                    'phone' => (string)($p['Phones'] ?? ''),
                ];
            }
            $items[] = [
                'ref' => (string)$c['Ref'],
                'label' => (string)($c['Description'] ?? $c['Ref']),
                'contacts' => $contacts,
            ];
        }
        json_response(['ok' => true, 'error' => '', 'items' => $items]);
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
