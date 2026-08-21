<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Settings, Csrf, JsonLd, Courses;

class Home
{
    public static function index(): never
    {
        // Курси сюди не потрапляють: у них своя сторінка й свій спосіб подачі,
        // а в стрічці «хітів» поруч із медом вони лише збивають ціну з пантелику
        $featured = DB::all("SELECT * FROM products WHERE active = 1 AND featured = 1
                             AND type <> 'course' ORDER BY id LIMIT 6");
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
        /*
         * Курси беруться з каталогу, а не з текстового блоку.
         *
         * Досі сторінка показувала один незмінний абзац і кнопку на форму
         * стороннього сайту — тобто студент не бачив ані що саме купує, ані
         * скільки це коштує, а магазин не бачив покупки взагалі: гроші й заявка
         * жили деінде. Тепер курс — товар (див. Courses), тож ним керує та сама
         * картка в адмінці, що й банкою меду: назва, опис, фото, ціна.
         */
        $courses = Courses::all();
        Catalog::preloadBrands($courses);
        View::show('home/courses', [
            'faq' => $faq,
            'courses' => $courses,
            'page_title' => 'Курси бджільництва — ' . cfg('app_name'),
            'meta_description' => 'Авторський курс промислового бджільництва на технологіях США: практика на діючій пасіці, диплом із перевіркою справжності.',
            'jsonld' => array_values(array_filter([
                JsonLd::course(Content::title('course_1'), Content::get('course_1')),
                $faq ? JsonLd::faq($faq) : null,
                JsonLd::breadcrumbs([['Головна', '/'], ['Курси', null]]),
            ])),
        ]);
    }

    /**
     * Кабінет студента: куплені курси й отримані сертифікати.
     *
     * Одна сторінка на дві теми, а не дві окремі, бо в обох випадках питання те
     * саме — «що я маю з навчання». Розділяти їх на два пункти меню означало б
     * змусити людину гадати, у якому з них шукати.
     */
    public static function learning(): never
    {
        if (!\Auth::check()) { flash('error', 'Увійдіть, щоб бачити свої курси.'); redirect('/'); }
        $uid = (int)\Auth::id();
        View::show('account/learning', [
            'courses' => Courses::forUser($uid),
            'diplomas' => \Diplomas::forUser($uid),
            'page_title' => 'Моє навчання — ' . cfg('app_name'),
        ]);
    }

    /**
     * Сторінка одного курсу.
     *
     * Окремий маршрут і окремий шаблон, а не /product/{slug}: товарна сторінка
     * показує вагу, залишок на складі й «схожі товари». На курсі за 14 900 це
     * читається як недбалість — вага в навчання, склад у відео й добірка меду
     * під програмою.
     */
    public static function course(string $slug): never
    {
        $p = Courses::bySlug($slug);
        if (!$p) { http_response_code(404); View::show('errors/404'); }
        $uid = \Auth::id();
        View::show('home/course', [
            'prod' => $p,
            'facts' => Catalog::attrs((int)$p['id']),
            'photos' => Catalog::images((int)$p['id']),
            'owned' => Courses::owned($uid, (int)$p['id']),
            'open' => $uid !== null && Courses::isOpen((int)$uid, (int)$p['id']),
            // Скільки дипломів уже видано за цим курсом — доказ, а не обіцянка
            'graduates' => (int)DB::val('SELECT COUNT(*) FROM diplomas WHERE product_id = ? AND active = 1',
                [(int)$p['id']]),
            'faq' => json_decode(Content::get('faq_course', 'body', '[]'), true) ?: [],
            'page_title' => $p['name'] . ' — ' . cfg('app_name'),
            'meta_description' => (string)($p['short_desc'] ?? ''),
            'jsonld' => array_values(array_filter([
                JsonLd::course($p['name'], (string)($p['short_desc'] ?? '')),
                JsonLd::breadcrumbs([['Головна', '/'], ['Курси', '/courses'], [$p['name'], null]]),
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
            /*
             * Номер із посилання ЛИШЕ підставляється в поле — окремою змінною,
             * а не як «результат». Перевірку робить POST, і підсунути номер у
             * $result означало б показати вердикт («не знайдено») ще до того,
             * як щось перевіряли: у масиві немає ключа ok, і шаблон чесно вивів
             * би відмову на порожньому місці.
             */
            'prefill' => trim((string)($_GET['number'] ?? '')),
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
