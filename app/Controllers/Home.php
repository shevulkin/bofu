<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Settings, Csrf, JsonLd;

class Home
{
    public static function index(): never
    {
        $featured = DB::all('SELECT * FROM products WHERE active = 1 AND featured = 1 ORDER BY id LIMIT 6');
        Catalog::preloadBrands($featured);   // бренди карток — одним запитом, а не по одному
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        $faq = json_decode(Content::get('faq', 'body', '[]'), true) ?: [];
        View::show('home/index', [
            // Лише верхній рівень: головна показує, з чого складається магазин,
            // а не весь його зміст. Розгортати гілку є де — у самому каталозі.
            'categories' => Catalog::rootCategories(),
            'featured' => $featured,
            'gallery_preview' => array_slice($gallery, 0, 3),
            'faq' => $faq,
            'page_title' => Settings::get('seo_title', cfg('app_name')),
            'meta_description' => Settings::get('seo_description', ''),
            // Питання й відповіді розміткою — ті самі, що показані на сторінці.
            // Google вимагає саме такої відповідності: розмітка, якої немає у
            // видимому тексті, вважається порушенням.
            'jsonld' => $faq ? [JsonLd::faq($faq)] : [],
        ]);
    }

    public static function about(): never
    {
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        View::show('home/about', [
            'gallery' => $gallery,
            'page_title' => 'Про мене — ' . cfg('app_name'),
        ]);
    }

    public static function courses(): never
    {
        // Свій набір питань, а не спільний із головною: сюди приходять по
        // навчання, і питання про повернення меду тут лише збиває.
        $faq = json_decode(Content::get('faq_course', 'body', '[]'), true) ?: [];
        View::show('home/courses', [
            'faq' => $faq,
            'page_title' => 'Курси бджільництва — ' . cfg('app_name'),
            'meta_description' => 'Авторський курс промислового бджільництва на технологіях США: практика на діючій пасіці, диплом із перевіркою справжності.',
            'jsonld' => array_values(array_filter([
                JsonLd::course(Content::title('course_1'), Content::get('course_1')),
                $faq ? JsonLd::faq($faq) : null,
                JsonLd::breadcrumbs([['Головна', '/'], ['Курси', null]]),
            ])),
        ]);
    }

    public static function gallery(): never
    {
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        View::show('home/gallery', ['gallery' => $gallery, 'page_title' => 'Галерея — ' . cfg('app_name')]);
    }

    public static function social(): never
    {
        View::show('home/social', ['page_title' => 'Соцмережі — ' . cfg('app_name')]);
    }

    /**
     * Наші партнери.
     *
     * Лише активні: знята галка означає «взяли паузу», і показувати таке
     * партнерство покупцю не можна — він піде за посиланням і не зрозуміє,
     * чому там тиша.
     */
    public static function partners(): never
    {
        View::show('home/partners', [
            'partners' => DB::all('SELECT * FROM partners WHERE active = 1 ORDER BY sort, name'),
            'page_title' => 'Наші партнери — ' . cfg('app_name'),
        ]);
    }

    /**
     * Де нас знайти: точки продажу на карті й списком.
     *
     * Лише активні (Catalog::stores) — закрита точка на карті означала б, що
     * людина приїде до зачинених дверей. Список іде разом із картою, а не
     * замість неї: адресу переписують у месенджер і диктують таксисту, а з
     * карти цього не зробиш.
     */
    public static function stores(): never
    {
        $stores = Catalog::stores();
        View::show('home/stores', [
            'stores' => $stores,
            // Карти на сторінці немає — лишились посилання «прокласти маршрут»
            // у кожній картці. Координати потрібні саме для них.
            'map_points' => \Geo::points($stores),
            'page_title' => 'Де нас знайти — ' . cfg('app_name'),
            'meta_description' => 'Адреси, телефони й графік роботи наших магазинів продуктів бджільництва. Самовивіз замовлень із будь-якої точки.',
            // Кожна точка окремою сутністю: адреса, телефон, графік і
            // координати — рівно те, з чого Google будує картку компанії.
            'jsonld' => array_merge(JsonLd::stores($stores), [
                JsonLd::breadcrumbs([['Головна', '/'], ['Де нас знайти', null]]),
            ]),
        ]);
    }

    /**
     * Правові сторінки: доставка, оплата, повернення, приватність, оферта.
     *
     * Один метод на всі пʼять, бо відрізняються вони лише текстом, який власник
     * править в адмінці. Сторінки не декоративні: без них покупець не бачить,
     * з ким укладає договір, а закони «Про електронну комерцію» та «Про захист
     * персональних даних» прямо вимагають цієї інформації на сайті.
     *
     * Тексти беруться з блоків контенту; вбудовані значення з Seeder працюють
     * як каркас, поки власник не написав свої, — але реквізити не підставляємо
     * жодні, і сторінка про це чесно попереджає.
     */
    private const LEGAL_PAGES = [
        'delivery' => ['page_delivery', 'Доставка', 'Умови доставки: перевізники, строки, вартість і самовивіз із магазинів.'],
        'payment'  => ['page_payment',  'Оплата', 'Способи оплати: післяплата, картка в магазині, рахунок для ФОП і компаній.'],
        'returns'  => ['page_returns',  'Обмін і повернення', 'Умови обміну та повернення товару згідно із Законом «Про захист прав споживачів».'],
        'privacy'  => ['page_privacy',  'Політика конфіденційності', 'Які персональні дані ми збираємо, навіщо, кому передаємо і як їх видалити.'],
        'offer'    => ['page_offer',    'Публічна оферта', 'Договір купівлі-продажу, який покупець приймає, оформлюючи замовлення.'],
    ];

    /**
     * Сторінки з вбудованим текстом.
     *
     * Оферта й політика конфіденційності — документи стандартні: їхній зміст
     * диктують закон і GDPR, а не бізнес. Тому вони живуть у коді (LegalText)
     * і показуються самі собою, без жодного налаштування. Блок контенту при
     * цьому лишається старшим: щойно власник напише свій текст, показується
     * він. Порожній блок означає «беремо вбудований», а не «сторінка порожня».
     *
     * Доставка, оплата й повернення сюди не входять навмисно: там кожен рядок
     * залежить від конкретного бізнесу — перевізники, строки, пороги
     * безкоштовної доставки. Вбудований «правильний» текст для них був би
     * вигадкою про чужу роботу.
     */
    private const BUILT_IN = [
        'page_offer' => [\LegalText::class, 'offer'],
        'page_privacy' => [\LegalText::class, 'privacy'],
    ];

    public static function legal(string $slug): never
    {
        $def = self::LEGAL_PAGES[$slug] ?? null;
        if (!$def) { http_response_code(404); View::show('errors/404', ['page_title' => 'Сторінку не знайдено']); }
        [$key, $title, $description] = $def;
        $text = Content::get($key);
        if ($text === '' && isset(self::BUILT_IN[$key])) $text = (self::BUILT_IN[$key])();
        View::show('home/legal', [
            'block' => $key,
            'heading' => $title,
            'text' => $text,
            // Реквізити продавця друкуються під кожною правовою сторінкою: саме
            // там їх шукають, і саме там їх вимагає закон.
            'entity' => Content::title('legal_entity'),
            'entity_details' => Content::get('legal_entity'),
            'page_title' => $title . ' — ' . cfg('app_name'),
            'meta_description' => $description,
        ]);
    }

    public static function diploma(): never
    {
        View::show('home/diploma', [
            'result' => null,
            'page_title' => 'Перевірка диплому — ' . cfg('app_name'),
            'meta_description' => 'Перевірте справжність диплома випускника курсів бджільництва за його номером.',
        ]);
    }

    public static function diplomaCheck(): never
    {
        Csrf::verify();
        $number = trim($_POST['number'] ?? '');
        $found = $number !== ''
            ? DB::row('SELECT * FROM diplomas WHERE number = ? AND active = 1', [$number])
            : null;
        View::show('home/diploma', [
            'result' => ['ok' => (bool)$found, 'number' => $number, 'diploma' => $found],
            'page_title' => 'Перевірка диплому — ' . cfg('app_name'),
        ]);
    }
}
