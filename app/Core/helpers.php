<?php
declare(strict_types=1);

function cfg(string $key, $default = null) {
    $parts = explode('.', $key);
    $val = $GLOBALS['bofu_config'];
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) return $default;
        $val = $val[$p];
    }
    return $val;
}

/** Екранування для HTML */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * JSON для вставки всередину <script>. Екранує < > & ' " у \uXXXX, тому назва товару
 * на кшталт «</script><script>…» не може розірвати тег і виконатись як код.
 * Для JS і для JSON-LD результат лишається валідним JSON.
 */
function json_js($data): string {
    return (string)json_encode($data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/** Базовий URL застосунку (авто: піддиректорія XAMPP чи корінь домену) */
function base_url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $base = cfg('base_url');
        if (!$base) {
            $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $base = $dir === '' ? '' : $dir;
        }
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function url(string $path = ''): string { return base_url($path); }

/**
 * Повна адреса сторінки чи файлу — зі схемою й доменом.
 *
 * Потрібна там, де відносний шлях не працює: canonical, og:image, sitemap і
 * розмітка для пошуковиків. Google приймає в JSON-LD лише абсолютні адреси —
 * відносні він мовчки ігнорує, і саме тому це окремий хелпер, а не збирання
 * рядка на місці в кожному шаблоні.
 */
function abs_url(string $path = ''): string {
    // вже абсолютна (завантажене фото з чужого домену) — лишаємо як є
    if (preg_match('~^https?://~', $path)) return $path;
    return site_origin() . base_url($path);
}

/**
 * Повна адреса файлу з assets/.
 *
 * Окремо від abs_url(): asset() вже додав базовий шлях, і пропускати результат
 * ще раз через abs_url() означало б /bofu/bofu/assets/… — саме такі адреси
 * Google і не може завантажити, мовчки викидаючи фото з картки товару.
 */
function asset_abs(string $path): string {
    if (preg_match('~^https?://~', $path)) return $path;
    return site_origin() . asset($path);
}

/** Схема й домен без шляху: https://bofu.ua */
function site_origin(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/**
 * Повна адреса сторінки, яку зараз відкрито, без параметрів запиту.
 *
 * Окремо від abs_url(): REQUEST_URI вже містить базовий шлях, і пропускати
 * його через base_url() означало б отримати /bofu/bofu/product/… — саме так
 * canonical і починає вказувати на неіснуючу сторінку.
 */
function current_url(): string {
    return site_origin() . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
}
function asset(string $path): string { return base_url('assets/' . ltrim($path, '/')); }

/** Посилання на статичний файл з версією (mtime) для скидання кешу браузера після змін */
function asset_v(string $path): string {
    $abs = BOFU_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($abs) ? filemtime($abs) : time();
    return asset($path) . '?v=' . $v;
}

/**
 * Куди повертати після форми. Приймаємо лише свій відносний шлях: значення з POST
 * інакше відправило б покупця на чужий домен (і виглядало б як наше посилання).
 */
function safe_back($path, string $fallback = '/'): string {
    $path = (string)($path ?? '');
    if ($path === '' || $path[0] !== '/') return $fallback;
    if (str_starts_with($path, '//') || str_starts_with($path, '/\\')) return $fallback; // протокол-відносний URL
    if (strpbrk($path, "\r\n") !== false) return $fallback;
    return $path;
}

function redirect(string $path): never {
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : base_url($path)));
    exit;
}

function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) { $_SESSION['flash'][$key] = $msg; return null; }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function price_fmt($amount): string {
    if ($amount === null || $amount === '' ) return 'За запитом';
    $n = (float)$amount;
    if ($n <= 0) return 'За запитом';
    $s = number_format($n, ((int)round($n * 100) % 100) ? 2 : 0, ',', ' ');
    return $s . ' грн';
}

/**
 * Число для поля вводу: «120.00» → «120», «120.50» → «120.5», порожнє → порожнє.
 *
 * Ціни в базі десяткові, тож MySQL повертає їх із двома знаками завжди. У формі
 * це перетворювало кожну введену людиною цілу ціну на «120,00»: зайвий шум у
 * колонці чисел, а на довгих цінах ще й обрізане поле («14900,0…»).
 * Зберігається все як було — міняється лише те, що показано.
 */
function num_val($v): string {
    if ($v === null || $v === '' || !is_numeric($v)) return $v === null ? '' : (string)$v;
    $s = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

/**
 * Узгодження іменника з числом: 1 курс, 2 курси, 5 курсів.
 *
 * Правило одне на весь сайт. Було воно й раніше — усередині оформлення, для
 * «2 товари»; лічильники на головній його не знали й писали «1 авторських
 * курсів» у найпомітнішому рядку сторінки. Тепер форму запитують тут, і
 * розійтись цим двом місцям більше нема як.
 *
 * Одинадцять–чотирнадцять — виняток, який ламає наївну перевірку останньої
 * цифри: 11 закінчується на 1, але це «одинадцять курсів», а не «курс».
 */
function plural(int $n, string $one, string $few, string $many): string {
    $n = abs($n);
    if ($n % 100 >= 11 && $n % 100 <= 14) return $many;
    return match ($n % 10) {
        1 => $one,
        2, 3, 4 => $few,
        default => $many,
    };
}

/**
 * Назва категорії у випадному списку: підрозділ із відступом — «— Липовий».
 *
 * Списки категорій в адмінці пласкі, а каталог тепер має два рівні. Без
 * відступу «Липовий» і «Мед» стоять поруч як рівні розділи, і товар легко
 * покласти на поверх вище, ніж збирались.
 */
function cat_label(array $c): string {
    return str_repeat('— ', (int)($c['depth'] ?? 0)) . (string)$c['name'];
}

/** Те саме, але разом із самим числом: «3 товари» */
function plural_n(int $n, string $one, string $few, string $many): string {
    return $n . ' ' . plural($n, $one, $few, $many);
}

/**
 * Вага товару людською мовою: 0.35 → «350 г», 1.5 → «1,5 кг».
 *
 * У базі вага завжди в кілограмах (так її просить накладна Нової Пошти), але
 * покупець меду міряє грамами: «0,35 кг» у характеристиках читається як
 * технічне поле для доставки, а «350 г» — як вага банки.
 */
function weight_fmt($kg): string {
    $kg = (float)($kg ?? 0);
    if ($kg <= 0) return '';
    if ($kg < 1) {
        // Дробову частину показуємо лише коли вона є: «350 г», але «12,5 г».
        // Зрізати нулі рядком тут не можна — «200» перетворилось би на «2».
        $g = $kg * 1000;
        return number_format($g, ((int)round($g * 10) % 10) ? 1 : 0, ',', ' ') . ' г';
    }
    // 2 кг, 1,5 кг, 3,25 кг — знаків рівно стільки, скільки значущих
    $cents = (int)round($kg * 100) % 100;
    return number_format($kg, $cents === 0 ? 0 : ($cents % 10 === 0 ? 1 : 2), ',', ' ') . ' кг';
}

/**
 * Ціна за 100 г — рядок, який робить дорогий товар зрозумілим.
 *
 * «600 грн» за мед ні про що не каже, поки невідомо, скільки його. Порівняння
 * з полицею супермаркету покупець проводить усе одно — краще дати йому цифру
 * самим, ніж лишити здогадуватись, що банка маленька.
 *
 * Показуємо лише там, де це чесно: вага відома, ціна відома, і товар важить
 * достатньо, щоб «за 100 г» не виглядало як ділення заради ділення.
 */
function price_per_100g($price, $kg): string {
    $price = (float)($price ?? 0);
    $kg = (float)($kg ?? 0);
    if ($price <= 0 || $kg <= 0 || $kg < 0.05) return '';
    return price_fmt(round($price / ($kg * 10), 2)) . ' / 100 г';
}

/** Як price_fmt(), але для товарів "під замовлення" без ціни показує це замість "За запитом" */
function price_label($amount, bool $madeToOrder): string {
    $fmt = price_fmt($amount);
    return ($fmt === 'За запитом' && $madeToOrder) ? 'Під замовлення' : $fmt;
}

function slugify(string $text): string {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'h','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ie','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'i','й'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'iu','я'=>'ia'];
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    return trim(preg_replace('~-+~', '-', $text), '-') ?: 'item';
}

function now(): string { return date('Y-m-d H:i:s'); }

function request_path(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $base = base_url('');
    $base = rtrim($base, '/');
    if ($base && str_starts_with($uri, $base)) $uri = substr($uri, strlen($base));
    if (str_starts_with($uri, '/index.php')) $uri = substr($uri, strlen('/index.php'));
    return '/' . ltrim($uri, '/');
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/**
 * Позначка редагованої зони в шаблоні вітрини. Пишеться прямо в тег:
 * `<h1<?= edit_mark('hero_title', 'title') ?>>`. Поза режимом редагування
 * не додає нічого. Подробиці — EditMode::mark().
 */
function edit_mark(string $key, ?string $field = null): string
{
    return EditMode::mark($key, $field);
}

function json_response($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
