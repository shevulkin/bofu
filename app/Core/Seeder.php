<?php
declare(strict_types=1);

/** Початкові дані (з дизайну Beekeeper of Ukraine) */
class Seeder
{
    public static function run(): void
    {
        if (DB::val('SELECT COUNT(*) FROM categories') > 0) { echo "Seed: дані вже є, пропускаю\n"; return; }

        // --- магазини ---
        $store1 = DB::insert('stores', ['name' => 'Магазин №1', 'slug' => 'store-1', 'city' => 'Львів', 'address' => 'вул. Прикладна, 1', 'phone' => '+380 67 000 0001', 'active' => 1, 'sort' => 1]);
        $store2 = DB::insert('stores', ['name' => 'Магазин №2', 'slug' => 'store-2', 'city' => 'Київ', 'address' => 'вул. Прикладна, 2', 'phone' => '+380 67 000 0002', 'active' => 1, 'sort' => 2]);

        // --- користувачі (демо) ---
        $admin = DB::insert('users', ['email' => 'admin@bofu.local', 'name' => 'Назарій Білько', 'role' => 'admin', 'active' => 1, 'phone' => '+380670000000', 'created_at' => now()]);
        $seller = DB::insert('users', ['email' => 'seller@bofu.local', 'name' => 'Олег Продавець', 'role' => 'seller', 'active' => 1, 'phone' => '+380670000001', 'created_at' => now()]);
        $customer = DB::insert('users', ['email' => 'customer@bofu.local', 'name' => 'Ганна Коваль', 'role' => 'customer', 'active' => 1, 'phone' => '+380670000002', 'created_at' => now()]);
        DB::insert('seller_stores', ['user_id' => $seller, 'store_id' => $store1]);

        // --- категорії (з дизайну) ---
        $cats = [
            ['honey', 'Мед і смаколики', 'product'], ['health', 'Для здоров\'я', 'product'],
            ['meds', 'Ліки для бджіл', 'product'], ['beauty', 'Краса і догляд', 'product'],
            ['candles', 'Свічки', 'product'], ['tools', 'Інструменти', 'product'],
            ['films', 'Фільми', 'video'], ['queens', 'Бджолині матки', 'product'],
            ['services', 'Послуги', 'service'],
        ];
        $catIds = [];
        foreach ($cats as $i => [$slug, $name, $type]) {
            $catIds[$slug] = DB::insert('categories', ['name' => $name, 'slug' => $slug, 'type' => $type, 'sort' => $i, 'active' => 1]);
        }

        // --- товари (з дизайну) ---
        $products = [
            ['honey', 'Мед різнотрав\'я, 350г', 'Класичний мед з різнотрав\'я власної пасіки.', 180, 34, 'img/honey-jar.webp', 1, [['Вага','350 г'],['Походження','Власна пасіка, Україна'],['Термін зберігання','24 місяці'],['Умови зберігання','Сухе, темне місце, до +20°C']], []],
            ['honey', 'Мед соняшниковий, 350г', 'Ніжний, швидко кристалізується.', 160, 28, 'img/honey-jar.webp', 0, [['Вага','350 г'],['Походження','Власна пасіка, Україна'],['Термін зберігання','24 місяці'],['Особливість','Швидка природна кристалізація']], []],
            ['honey', 'Набір "Пасічник", 3х350г', 'Три види меду в подарунковій коробці.', 480, 12, 'img/honey-jar.webp', 0, [['Вміст','3 банки по 350 г'],['Походження','Власна пасіка, Україна'],['Упаковка','Подарункова коробка'],['Термін зберігання','24 місяці']], []],
            ['health', 'Прополіс, 30мл', 'Настоянка прополісу на спирту.', 120, 40, null, 0, [['Об\'єм','30 мл'],['Концентрація','20%'],['Форма','Спиртова настоянка'],['Термін зберігання','36 місяців']], []],
            ['health', 'Пилок квітковий, 200г', 'Джерело білка та мікроелементів.', 150, 22, null, 0, [['Вага','200 г'],['Форма','Гранули'],['Походження','Власна пасіка, Україна'],['Термін зберігання','12 місяців']], []],
            ['meds', 'Підкормка для бджіл', 'Профілактика та підтримка сім\'ї взимку.', 210, 18, null, 1, [['Призначення','Профілактика, підтримка взимку'],['Форма','Сироп/паста'],['Застосування','За інструкцією виробника']], []],
            ['meds', 'Засіб від кліща Варроа', 'Ветеринарний препарат для обробки вуликів.', 270, 15, null, 0, [['Тип','Ветеринарний препарат'],['Призначення','Обробка від кліща Варроа'],['Застосування','За інструкцією виробника']], []],
            ['beauty', 'Мило з прополісом', 'Натуральний догляд для шкіри.', 95, 30, null, 0, [['Склад','Прополіс, натуральні олії'],['Вага','100 г'],['Тип шкіри','Всі типи']], []],
            ['beauty', 'Бальзам для губ з воском', 'На основі бджолиного воску.', 70, 26, null, 0, [['Склад','Бджолиний віск, олії'],['Об\'єм','10 мл']], []],
            ['candles', 'Свічка з вощини, мала', 'Ручна робота, натуральний віск.', 110, 20, null, 1, [['Матеріал','Натуральна вощина'],['Виготовлення','Ручна робота'],['Час горіння','~4 години']], [['Натуральний віск', null], ['Медовий відтінок', 125], ['Молочний', null]]],
            ['candles', 'Свічка з вощини, велика', 'Довге горіння, медовий аромат.', 190, 16, null, 0, [['Матеріал','Натуральна вощина'],['Виготовлення','Ручна робота'],['Час горіння','~9 годин']], [['Натуральний віск', null], ['Медовий відтінок', 210]]],
            ['tools', 'Димар пасічника', 'Класичний інструмент для роботи з вуликом.', 340, 9, null, 0, [['Матеріал','Нержавіюча сталь'],['Призначення','Робота з вуликом']], []],
            ['tools', 'Стамеска пасічника', 'Для роботи з рамками та корпусами.', 90, 24, null, 0, [['Матеріал','Нержавіюча сталь'],['Довжина','20 см']], []],
            ['films', 'Фільм "Шлях пасічника"', 'Авторський документальний фільм про пасіку.', 99, 999, null, 0, [['Формат','Цифрове відео'],['Мова','Українська'],['Автор','Назарій Білько']], []],
            ['queens', 'Плідна бджоломатка', 'Перевірена, засіяна, готова до підсадки у сім\'ю.', 900, 7, null, 0, [['Стан','Плідна (засіяна)'],['Походження','Власна пасіка'],['Доставка','У кліточці, з супроводом']], []],
            ['queens', 'Неплідна бджоломатка', 'Молода матка до початку яйцекладки.', 450, 11, null, 0, [['Стан','Неплідна'],['Походження','Власна пасіка'],['Доставка','У кліточці, з супроводом']], []],
            ['services', 'Стекування меду', 'Професійна відкачка та стекування меду з рамок.', null, 999, null, 0, [['Формат','Виїзна послуга'],['Термін','За домовленістю'],['Оплата','Індивідуально']], []],
        ];
        foreach ($products as [$cat, $name, $desc, $price, $stock, $img, $featured, $specs, $variants]) {
            $type = $cat === 'films' ? 'video' : ($cat === 'services' ? 'service' : 'product');
            $pid = DB::insert('products', [
                'category_id' => $catIds[$cat], 'name' => $name, 'slug' => slugify($name),
                'short_desc' => $desc, 'description' => $desc,
                'base_price' => $price, 'type' => $type, 'active' => 1, 'featured' => $featured,
                'made_to_order' => 1, 'image' => $img,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($specs as $i => [$aname, $avalue]) {
                DB::insert('product_attrs', ['product_id' => $pid, 'name' => $aname, 'value' => $avalue, 'filterable' => in_array($aname, ['Вага', 'Походження', 'Матеріал', 'Форма'], true) ? 1 : 0, 'sort' => $i]);
            }
            $variantIds = [null];
            foreach ($variants as $i => [$vname, $vprice]) {
                $variantIds[] = DB::insert('product_variants', ['product_id' => $pid, 'name' => $vname, 'price' => $vprice, 'sort' => $i, 'active' => 1]);
            }
            // залишки: розділити між магазинами
            $half = intdiv($stock, 2);
            DB::insert('store_stock', ['product_id' => $pid, 'variant_id' => null, 'store_id' => $store1, 'qty' => $stock - $half]);
            DB::insert('store_stock', ['product_id' => $pid, 'variant_id' => null, 'store_id' => $store2, 'qty' => $half]);
        }

        // --- словник характеристик зі щойно засіяних товарів ---
        Attrs::backfill();

        // варіанти свічок описуємо характеристикою, щоб покупець обирав відтінок чіпами
        $shade = Attrs::ensure('Відтінок');
        if ($shade) {
            Attrs::setCategories((int)$shade['id'], [$catIds['candles']]);
            $rows = DB::all('SELECT pv.id, pv.name FROM product_variants pv
                             JOIN products p ON p.id = pv.product_id WHERE p.category_id = ?', [$catIds['candles']]);
            foreach ($rows as $v) {
                $val = Attrs::ensureValue((int)$shade['id'], $v['name']);
                if ($val) DB::insert('variant_options', [
                    'variant_id' => (int)$v['id'], 'attribute_id' => (int)$shade['id'],
                    'value_id' => (int)$val['id'], 'value' => $val['value'],
                ]);
            }
        }

        // --- промокоди (з дизайну) ---
        DB::insert('promo_codes', ['code' => 'MED10', 'percent' => 10, 'active' => 1]);
        DB::insert('promo_codes', ['code' => 'BDJILY15', 'percent' => 15, 'active' => 1]);

        // --- дипломи (демо) ---
        DB::insert('diplomas', ['number' => 'BOFU-2024-001', 'student' => 'Іван Петренко', 'course' => 'Промислове бджільництво', 'issued_at' => '2024-09-15', 'active' => 1]);
        DB::insert('diplomas', ['number' => 'BOFU-2025-014', 'student' => 'Марія Шевчук', 'course' => 'Промислове бджільництво', 'issued_at' => '2025-06-20', 'active' => 1]);

        // --- контент (тексти з дизайну) ---
        $blocks = [
            ['hero_badge', 'Аспірант · Бджоляр · Блогер · Підприємець', null],
            ['hero_title', 'Назарій Білько — Beekeeper of Ukraine', null],
            ['hero_text', null, 'Власник і засновник мережі магазинів продуктів бджільництва «Beekeeper of Ukraine». Навчаю бджільництва, веду власну пасіку та випускаю мед під власним брендом.'],
            ['hero_points', null, "153+ випускників курсів\nВерифіковані дипломи\nЗроблено в Україні"],
            ['counter_graduates', '153', null],
            ['counter_courses', '1', null],
            ['counter_founded', '2022', null],
            ['about_teaser_title', 'Бджоляр, блогер, підприємець', null],
            ['about_teaser', null, 'З тринадцяти років вивчаю світ бджіл — від першого наставника-дідуся до профільної освіти на кафедрі бджільництва та практики в Україні й за кордоном. Сьогодні керую мережею магазинів «Beekeeper of Ukraine» й навчаю цієї справи інших.'],
            ['about_full', 'Бджоляр, блогер, підприємець', 'З тринадцяти років я розпочав вивчати світ бджіл — моїм першим наставником став дідусь Атаманчук Микола Євгенович, який прищепив мені любов до бджіл, бджільництва і природи.'],
            ['why_1', 'Понад 10 років на пасіці', 'Навчання з 13 років, профільна освіта на кафедрі бджільництва та практика в Україні й за кордоном.'],
            ['why_2', 'Власний бізнес, а не лише курс', 'Веду діючу мережу магазинів продуктів бджільництва — вчу на реальному, робочому досвіді.'],
            ['why_3', 'Диплом із перевіркою справжності', 'Кожен випускник отримує диплом, який можна перевірити на сайті за номером.'],
            ['course_1', 'Промислове бджільництво на основі технологій США', 'Промислова технологія лише здається складною — покажу, як вона працює насправді.'],
            ['course_1_link', 'https://docs.google.com/forms/d/1nbegwmC7BHYPzcdMcd9vwU0si-ZA7fdyIWhHZ8uwGoA/prefill', null],
            ['faq', null, json_encode([
                ['Кому підходить курс?', 'І новачкам без досвіду, і тим, хто вже має пасіку та хоче перейти на промислові технології.'],
                ['Як проходить навчання?', 'Після заявки через форму запису ми узгоджуємо формат і дати — курс включає практичну частину.'],
                ['Як перевірити диплом?', 'Введіть номер диплому у розділі «Диплом» — система звірить його з базою випускників.'],
                ['Чи можна повернути товар з магазину?', 'Так, зв\'яжіться з нами через соцмережі — питання обміну й повернення вирішуємо індивідуально.'],
            ], JSON_UNESCAPED_UNICODE)],
            ['social_instagram', 'https://instagram.com/', null],
            ['social_youtube', 'https://youtube.com/', null],
            ['social_tiktok', 'https://tiktok.com/', null],
            ['gallery', null, json_encode([
                ['Бджільництво', 'img/gallery-1.webp'], ['Чикаго', 'img/gallery-2.webp'],
                ['Вдалий медозбір', 'img/gallery-3.webp'], ['Подорож Америкою', 'img/gallery-4.webp'],
                ['Спікер у школі аграрія', 'img/gallery-5.webp'],
            ], JSON_UNESCAPED_UNICODE)],
            ['final_cta_title', 'Готові розпочати?', null],
            ['final_cta_text', null, 'Запишіться на курс або оберіть продукти з власної пасіки вже сьогодні.'],
        ];
        foreach ($blocks as [$key, $title, $body]) {
            DB::insert('content_blocks', ['key' => $key, 'title' => $title, 'body' => $body, 'image' => null]);
        }

        // --- налаштування за замовчуванням ---
        $settings = [
            'notify_all_enabled' => '1', 'notify_telegram_enabled' => '1',
            'notify_email_enabled' => '1', 'notify_push_enabled' => '1',
            'sale_banner_active' => '0', 'sale_banner_text' => 'Сезонна знижка на мед!', 'sale_banner_percent' => '15',
            'telegram_bot_token' => '', 'np_api_key' => '', 'mail_from' => '',
            'google_client_id' => '', 'google_client_secret' => '',
            'seo_title' => 'Beekeeper of Ukraine — мед, продукти бджільництва, курси',
            'seo_description' => 'Мед з власної пасіки, продукти бджільництва, авторські курси та перевірка дипломів. Виробник Назарій Білько.',
        ];
        foreach ($settings as $k => $v) DB::insert('settings', ['key' => $k, 'value' => $v]);

        DB::insert('settings', ['key' => 'schema_version', 'value' => (string)Schema::VERSION]);
        DB::insert('settings', ['key' => 'notify_viber_enabled', 'value' => '1']);
        DB::insert('settings', ['key' => 'viber_bot_token', 'value' => '']);

        // --- правила нотифікацій ---
        foreach (Notify::EVENTS as $event => $label) {
            foreach (['telegram', 'viber', 'email', 'push'] as $channel) {
                DB::insert('notification_rules', [
                    'event' => $event, 'channel' => $channel, 'enabled' => $event === 'order_new' ? 1 : 0,
                    'recipients' => 'admins_sellers', 'template' => Notify::DEFAULT_TEMPLATES[$event] ?? '',
                ]);
            }
        }
        echo "Seed: готово\n";
    }
}
