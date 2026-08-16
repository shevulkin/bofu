<?php
declare(strict_types=1);

/**
 * Перевірка налаштувань інтеграцій — «а воно взагалі працює?».
 *
 * Причина існування: історія з Viber. Токен був правильний, поле заповнене,
 * а кнопка входу не показувалась — бо запит до API падав, і ніде про це не
 * писалось. Налаштування, яке мовчки не працює, гірше за незаповнене:
 * незаповнене видно.
 *
 * Два правила, від яких не відступаємо:
 *
 * 1. Перевіряємо ТЕ, ЩО ВПИСАНО У ФОРМУ, не зберігаючи. Інакше «перевірити»
 *    ставало б «зберегти», і помилковий токен лишався б у базі.
 * 2. Тільки читальні виклики. Жодного set_webhook (перепише бота, який може
 *    обслуговувати інший сайт) і жодних тестових листів чи повідомлень —
 *    вони йдуть живим людям.
 *
 * Кожна перевірка чесно каже, чого вона НЕ доводить.
 */
class IntegrationCheck
{
    /** @return array<int, array{name:string, state:string, text:string, note?:string}> */
    public static function run(array $v): array
    {
        return array_values(array_filter([
            self::telegram(trim((string)($v['telegram_bot_token'] ?? ''))),
            self::viber(trim((string)($v['viber_bot_token'] ?? ''))),
            self::novaPoshta(trim((string)($v['np_api_key'] ?? ''))),
            self::npSender(trim((string)($v['np_api_key'] ?? ''))),
            self::google($v),
            self::email(trim((string)($v['mail_from'] ?? ''))),
            self::botSite(trim((string)($v['bot_site_url'] ?? ''))),
            self::push(),
        ]));
    }

    private static function row(string $name, string $state, string $text, ?string $note = null): array
    {
        $r = ['name' => $name, 'state' => $state, 'text' => $text];
        if ($note !== null) $r['note'] = $note;
        return $r;
    }

    private static function telegram(string $token): array
    {
        if ($token === '') return self::row('Telegram', 'off', 'Токен не вказано — вхід і сповіщення через Telegram вимкнені');
        Telegram::useToken($token);
        try {
            $me = Telegram::api('getMe');
        } finally {
            Telegram::useToken(null);
        }
        $r = $me['result'] ?? null;
        if (!$r) {
            return self::row('Telegram', 'bad',
                'Telegram не прийняв токен: ' . ($me['description'] ?? 'немає відповіді'),
                'Перевірте, що це токен від @BotFather і що сервер має доступ до api.telegram.org');
        }
        return self::row('Telegram', 'ok',
            'Бот @' . ($r['username'] ?? '?') . ' («' . ($r['first_name'] ?? '') . '») відповідає');
    }

    private static function viber(string $token): array
    {
        if ($token === '') return self::row('Viber', 'off', 'Токен не вказано — вхід і сповіщення через Viber вимкнені');
        Viber::useToken($token);
        try {
            $info = Viber::api('get_account_info', []);
        } finally {
            Viber::useToken(null);
        }
        if (($info['status'] ?? -1) !== 0) {
            return self::row('Viber', 'bad',
                'Viber не прийняв токен: ' . ($info['status_message'] ?? 'немає відповіді'),
                'Токен беруть у кабінеті Viber-бота, розділ Edit Info');
        }
        $uri = (string)($info['uri'] ?? '');
        $hook = (string)($info['webhook'] ?? '');
        $mine = rtrim(BotAuth::siteUrl(), '/') . base_url('/api/viber/webhook');
        $text = 'Бот «' . ($info['name'] ?? '?') . '», адреса ' . ($uri !== '' ? 'viber://pa?chatURI=' . $uri : 'невідома');

        // Найважливіше в цій перевірці: у бота лише ОДИН webhook. Якщо він указує
        // на інший сайт — цей бот зараз працює там, і збереження токена тут його
        // забере. Краще, щоб адмін дізнався про це заздалегідь, а не з наслідків.
        if ($hook === '') {
            return self::row('Viber', 'warn', $text, 'Webhook не встановлено — бот поки нічого нам не надсилає. Збережіть налаштування, щоб зареєструвати його.');
        }
        if (BotAuth::siteUrl() !== '' && rtrim($hook, '/') !== rtrim($mine, '/')) {
            return self::row('Viber', 'warn', $text,
                'Webhook бота вказує на ' . $hook . ' — тобто зараз він обслуговує інший сайт. '
                . 'Збереження токена тут перепише адресу, і там бот перестане працювати. '
                . 'Якщо той сайт потрібен — заведіть окремого бота.');
        }
        return self::row('Viber', 'ok', $text . ', webhook наш');
    }

    private static function novaPoshta(string $key): array
    {
        if ($key === '') return self::row('Нова Пошта', 'off', 'Ключа немає — у checkout не буде підказок міст і відділень');
        $ch = curl_init('https://api.novaposhta.ua/v2.0/json/');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'apiKey' => $key, 'modelName' => 'Address', 'calledMethod' => 'searchSettlements',
                'methodProperties' => ['CityName' => 'Київ', 'Limit' => '1'],
            ], JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
        ]);
        $resp = json_decode((string)curl_exec($ch), true);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_array($resp)) return self::row('Нова Пошта', 'bad', 'Немає відповіді від API' . ($err ? ': ' . $err : ''));
        if (empty($resp['success'])) {
            return self::row('Нова Пошта', 'bad',
                'Ключ відхилено: ' . implode('; ', (array)($resp['errors'] ?? ['без пояснення'])));
        }
        $found = $resp['data'][0]['Addresses'][0]['Present'] ?? '';
        return self::row('Нова Пошта', 'ok', 'Ключ робочий' . ($found !== '' ? ' — тестовий пошук знайшов «' . $found . '»' : ''));
    }

    /**
     * Чи можна створювати накладні. Робочий ключ цього ще не означає: ключ
     * відкриває довідники, а накладну підписує контрагент-відправник, якого
     * треба обрати окремо. Без цієї перевірки перша ж спроба створити ТТН
     * закінчилась би відмовою НП тоді, коли продавець стоїть із коробкою.
     *
     * Самих накладних не створюємо — вони справжні й коштують грошей. Тому
     * перевіряємо заповненість і кажемо прямо, чого це не доводить.
     */
    private static function npSender(string $key): array
    {
        if ($key === '') return [];   // без ключа нема про що говорити — скаже перевірка вище

        $gaps = [];
        if ((string)Settings::get('np_sender_ref', '') === '') $gaps[] = 'контрагент-відправник';
        if ((string)Settings::get('np_sender_contact_ref', '') === '') $gaps[] = 'контактна особа';
        if (NovaPoshta::phone((string)Settings::get('np_sender_phone', '')) === '') $gaps[] = 'телефон відправника';
        if ((string)Settings::get('np_sender_city_ref', '') === '') $gaps[] = 'місто відправлення';
        if ((string)Settings::get('np_sender_warehouse_ref', '') === '') $gaps[] = 'відділення відправлення';

        if ($gaps) {
            return self::row('НП: відправник', 'warn',
                'Накладні поки не створюються — не заповнено: ' . implode(', ', $gaps),
                'Підказки міст і відділень у checkout працюють і без цього. А от кнопка «Створити накладну» '
                . 'у замовленні скаже те саме, що й тут. Заповнюється в картці «Нова Пошта: відправник» нижче.');
        }
        return self::row('НП: відправник', 'ok',
            'Відправник заповнений: ' . (Settings::get('np_sender_name', '') ?: 'контрагент обрано')
            . ', ' . (Settings::get('np_sender_warehouse', '') ?: 'відділення обрано'),
            'Це перевірка заповненості, а не дійсності: чи прийме НП саме цього відправника, '
            . 'з’ясується на першій справжній накладній. Тестових ми не створюємо — вони коштують грошей.');
    }

    private static function google(array $v): array
    {
        $id = trim((string)($v['google_client_id'] ?? ''));
        $secret = trim((string)($v['google_client_secret'] ?? ''));
        if ($id === '' && $secret === '') return self::row('Google OAuth', 'off', 'Ключів немає — кнопки «Увійти через Google» не буде');
        if ($id === '' || $secret === '') return self::row('Google OAuth', 'bad', 'Заповнено лише одне з двох полів — потрібні обидва');
        if (!str_contains($id, '.apps.googleusercontent.com')) {
            return self::row('Google OAuth', 'warn', 'Client ID не схожий на гугловий',
                'Зазвичай він закінчується на .apps.googleusercontent.com');
        }
        return self::row('Google OAuth', 'ok', 'Ключі на місці',
            'Перевірити насправді можна лише входом: Google приймає ключі тільки в живому обміні. '
            . 'Redirect URI має точно збігатися з ' . GoogleAuth::redirectUri());
    }

    private static function email(string $from): array
    {
        if ($from === '') return self::row('Email', 'off', 'Адресу відправника не вказано — листи підуть від адреси хостингу');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) return self::row('Email', 'bad', '«' . $from . '» не схоже на адресу');
        return self::row('Email', 'ok', 'Відправник: ' . $from,
            'Що листи справді доходять, це не доводить — тестового листа не шлемо, бо він пішов би живій людині. '
            . 'Перевірте оформленням замовлення на власну адресу.');
    }

    private static function botSite(string $url): array
    {
        $eff = $url !== '' ? rtrim($url, '/') : BotAuth::siteUrl();
        if ($eff === '') return self::row('Адреса сайту для бота', 'bad', 'Не вказано й не визначилась — кнопка «повернутись на сайт» у боті нікуди не веде');
        if (!preg_match('~^https?://~', $eff)) return self::row('Адреса сайту для бота', 'bad', '«' . $eff . '» — адреса має починатися з http:// або https://');
        if ($url === '') {
            return self::row('Адреса сайту для бота', 'warn', 'Визначилась сама: ' . $eff,
                'Viber стукає у webhook власним запитом, тож на бойовому сервері краще вписати адресу явно');
        }
        return self::row('Адреса сайту для бота', 'ok', $eff);
    }

    private static function push(): array
    {
        [$pub] = WebPush::ensureKeys();
        if (!$pub) {
            return self::row('Web Push', 'bad', 'Ключі не згенерувалися',
                extension_loaded('openssl')
                    ? 'OpenSSL є, але не зміг створити ключ — зазвичай він не знаходить свій openssl.cnf. Вкажіть шлях у змінній OPENSSL_CONF (на Windows: C:\\xampp\\apache\\conf\\openssl.cnf) і перезапустіть Apache.'
                    : 'Розширення OpenSSL вимкнене в php.ini — увімкніть extension=openssl');
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
              || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');
        if (!$https) return self::row('Web Push', 'warn', 'Ключі є, але сайт відкрито не через HTTPS', 'Пуші в браузері працюють лише на HTTPS (або localhost)');
        return self::row('Web Push', 'ok', 'Ключі є, протокол підходить');
    }
}
