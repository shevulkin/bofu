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
