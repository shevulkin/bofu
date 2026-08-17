<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Settings, Catalog, RateLimit, Fiscal, Sheet, VchasnoGoods;

/**
 * Каса «Вчасно»: те, що стосується самої каси, а не окремого замовлення.
 *
 * Зміна, звіти й готівка в скриньці — це стан торгової точки; чеки живуть у
 * картках замовлень, і дублювати їх тут ні до чого. Виняток один: чеки, які
 * не пробились, — їх шукають не по замовленнях, а «покажіть усе, що впало».
 *
 * Товари — окрема сторінка. Каталог звіряють раз на кілька місяців, а зміну
 * відкривають щоранку, і змішувати ці дві роботи в одному екрані означало б
 * щоразу прокручувати чуже.
 */
class Kasa
{
    /** Куди складаємо вивантаження з їхнього кабінету, поки з ним працюють */
    private const UPLOAD_DIR = BOFU_ROOT . '/storage/vchasno';

    /**
     * Каси, до яких у нас є доступ.
     *
     * Каса належить точці, але точка може працювати й на спільній: тоді запис
     * один на всіх. Показуємо саме каси, а не магазини, — Z-звіт закриває
     * зміну каси, і людині треба бачити, скільки їх насправді.
     *
     * @return array<int, array{id:string, store_id:?int, name:string}>
     */
    private static function cases(): array
    {
        $out = [];
        foreach (DB::all("SELECT id, name FROM stores
                          WHERE vchasno_token IS NOT NULL AND vchasno_token <> '' AND active = 1
                          ORDER BY sort, id") as $s) {
            $out[] = ['id' => (string)(int)$s['id'], 'store_id' => (int)$s['id'], 'name' => (string)$s['name']];
        }
        if (trim((string)Settings::get('vchasno_token', '')) !== '') {
            $out[] = ['id' => 'main', 'store_id' => null,
                      'name' => $out ? 'Спільна каса (решта точок)' : 'Каса магазину'];
        }
        return $out;
    }

    private static function currentCase(array $cases): ?array
    {
        $want = (string)($_POST['case'] ?? $_GET['case'] ?? '');
        foreach ($cases as $c) if ($c['id'] === $want) return $c;
        return $cases[0] ?? null;
    }

    public static function index(): never
    {
        Auth::requireCap('fiscal.manage');
        $cases = self::cases();
        $case = self::currentCase($cases);

        if (is_post() && $case) self::action($case);

        // Статус питаємо на кожне відкриття: зміна могла закритись сама
        // (Z-звіт із іншої програми, доба скінчилась), і показувати
        // збережений колись стан гірше, ніж не показувати нічого.
        $status = $case ? \Vchasno::status($case['store_id']) : null;

        View::show('admin/vchasno/index', [
            'cases' => $cases,
            'case' => $case,
            'status' => $status,
            'info' => $status && $status['ok'] ? (array)($status['data']['info'] ?? []) : [],
            // Чеки, які не пробились: тут їх шукають, коли «щось пішло не так»,
            // а не гортаючи замовлення по одному
            'broken' => DB::all("SELECT f.*, o.number FROM fiscal_receipts f
                                 LEFT JOIN orders o ON o.id = f.order_id
                                 WHERE f.status <> 'done' ORDER BY f.id DESC LIMIT 30"),
            'recent' => DB::all("SELECT f.*, o.number FROM fiscal_receipts f
                                 LEFT JOIN orders o ON o.id = f.order_id
                                 WHERE f.status = 'done' ORDER BY f.id DESC LIMIT 20"),
            'page_title' => 'Вчасно.Каса — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Дії над касою.
     *
     * Кожна з них — справжня фіскальна операція, тому ліміт спільний і тісний:
     * він тут не від ботів, а від подвійного натискання. Z-звіт двічі поспіль
     * не страшний (друга спроба нічого не закриє), а от два службові внесення
     * розійдуться з готівкою у скриньці.
     */
    private static function action(array $case): void
    {
        $action = (string)($_POST['_action'] ?? '');
        $cashier = (string)(Auth::user()['name'] ?? '');
        $back = '/admin/vchasno?case=' . rawurlencode($case['id']);
        RateLimit::guard('vchasno_kasa', 60, 3600);

        $say = static function (array $r, string $ok) use ($back): never {
            flash($r['ok'] ? 'success' : 'error', $r['ok'] ? $ok : 'Каса відмовила: ' . $r['error']);
            redirect($back);
        };

        if ($action === 'shift_open') {
            $say(\Vchasno::openShift($cashier, $case['store_id']), 'Зміну відкрито.');
        }
        if ($action === 'x_report') {
            $r = \Vchasno::xReport($cashier, $case['store_id']);
            $say($r, 'X-звіт сформовано — його видно в кабінеті каси.');
        }
        if ($action === 'z_report') {
            $r = \Vchasno::zReport($cashier, $case['store_id']);
            $say($r, 'Z-звіт сформовано, зміну закрито.');
        }
        if ($action === 'cash_in' || $action === 'cash_out') {
            $sum = (float)str_replace(',', '.', (string)($_POST['sum'] ?? ''));
            if ($sum <= 0) { flash('error', 'Вкажіть суму більшу за нуль.'); redirect($back); }
            $comment = (string)($_POST['comment'] ?? '');
            $r = $action === 'cash_in'
                ? \Vchasno::cashIn($sum, $comment, $case['store_id'])
                : \Vchasno::cashOut($sum, $comment, $case['store_id']);
            $say($r, ($action === 'cash_in' ? 'Внесення' : 'Видачу') . ' проведено: ' . price_fmt($sum) . '.');
        }
        if ($action === 'retry_all') {
            $done = 0; $left = 0;
            foreach (Fiscal::due(20) as $r) {
                $res = Fiscal::retry($r, Auth::id());
                $res['ok'] ? $done++ : $left++;
            }
            flash($left ? 'error' : 'success',
                $done || $left
                    ? "Перепитано: пройшло — $done, досі без відповіді — $left."
                    : 'Непевних чеків немає — перепитувати нічого.');
            redirect($back);
        }
    }

    // ──────────────────────────────────────────────────────────────── товари

    public static function goods(): never
    {
        Auth::requireCap('fiscal.manage');
        $storeId = (int)($_POST['store_id'] ?? $_GET['store'] ?? 0) ?: null;

        if (is_post()) {
            $action = (string)($_POST['_action'] ?? '');
            if ($action === 'export') self::exportGoods($storeId);
            if ($action === 'upload') self::uploadGoods($storeId);
            if ($action === 'apply') self::applyGoods($storeId);
            if ($action === 'forget') {
                self::forgetUpload();
                flash('success', 'Файл прибрано.');
                redirect('/admin/vchasno/goods');
            }
        }

        // Звірку рахуємо з файлу щоразу, а не тримаємо в сесії: каталог у нас
        // міняється між відкриттями сторінки, і показувати вчорашню звірку як
        // сьогоднішню — це рівно той випадок, коли краще не показувати нічого.
        $report = null; $parsed = null;
        $file = self::uploadPath();
        if ($file !== null) {
            $parsed = VchasnoGoods::parse($file);
            if ($parsed['error'] === '') $report = VchasnoGoods::compare($parsed['goods'], $storeId);
        }

        View::show('admin/vchasno/goods', [
            'stores' => Catalog::stores(),
            'store_id' => $storeId,
            'has_file' => $file !== null,
            'file_name' => (string)($_SESSION['vchasno_goods_name'] ?? ''),
            'parsed' => $parsed,
            'report' => $report,
            'tax_groups' => \Vchasno::TAX_GROUPS,
            'page_title' => 'Вчасно.Каса: товари — адмінка',
        ], 'layouts/admin');
    }

    /** Наш каталог у файл, який приймає їхній імпорт */
    private static function exportGoods(?int $storeId): never
    {
        $rows = VchasnoGoods::export($storeId);
        $csv = (string)($_POST['format'] ?? '') === 'csv';
        $body = $csv ? Sheet::writeCsv($rows) : Sheet::writeXlsx($rows);
        $name = 'bofu-tovary-' . date('Y-m-d') . ($csv ? '.csv' : '.xlsx');

        header('Content-Type: ' . ($csv ? 'text/csv; charset=utf-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }

    /**
     * Прийняти вивантаження з їхнього кабінету.
     *
     * Файл кладемо в storage і памʼятаємо в сесії, а не тримаємо в ній самі
     * товари: каталог на кілька тисяч позицій роздув би сесію, яку читає
     * кожен запит сайту. Живе він до кінця роботи з ним — кнопка «прибрати»
     * поруч.
     */
    private static function uploadGoods(?int $storeId): never
    {
        $f = $_FILES['file'] ?? null;
        $back = '/admin/vchasno/goods' . ($storeId ? '?store=' . $storeId : '');
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Файл не завантажився. Оберіть вивантаження з кабінету «Вчасно.Каси» (xlsx або csv).');
            redirect($back);
        }
        if ((int)$f['size'] > 8 * 1024 * 1024) {
            flash('error', 'Файл завеликий — більше 8 МБ. Це точно вивантаження товарів?');
            redirect($back);
        }
        if (!is_dir(self::UPLOAD_DIR)) @mkdir(self::UPLOAD_DIR, 0775, true);
        self::forgetUpload();

        $path = self::UPLOAD_DIR . '/goods-' . bin2hex(random_bytes(8));
        if (!@move_uploaded_file($f['tmp_name'], $path)) {
            flash('error', 'Не вдалося зберегти файл — перевірте права на теку storage.');
            redirect($back);
        }
        $_SESSION['vchasno_goods_file'] = $path;
        $_SESSION['vchasno_goods_name'] = mb_substr((string)($f['name'] ?? 'файл'), 0, 100);

        $parsed = VchasnoGoods::parse($path);
        if ($parsed['error'] !== '') {
            self::forgetUpload();
            flash('error', $parsed['error']);
        } else {
            flash('success', 'Прочитано позицій: ' . count($parsed['goods']) . '.');
        }
        redirect($back);
    }

    /** Перенести до себе те, чого в нас немає */
    private static function applyGoods(?int $storeId): never
    {
        $back = '/admin/vchasno/goods' . ($storeId ? '?store=' . $storeId : '');
        $file = self::uploadPath();
        if ($file === null) { flash('error', 'Файл загубився — завантажте його ще раз.'); redirect($back); }

        $parsed = VchasnoGoods::parse($file);
        if ($parsed['error'] !== '') { flash('error', $parsed['error']); redirect($back); }

        $report = VchasnoGoods::compare($parsed['goods'], $storeId);
        $r = VchasnoGoods::apply($report['rows']);
        flash($r['filled'] ? 'success' : 'error',
            $r['filled']
                ? 'Заповнено порожніх полів у позиціях: ' . $r['filled'] . '.'
                  . ($r['notes'] ? ' Не чіпали: ' . implode('; ', array_slice($r['notes'], 0, 5))
                     . (count($r['notes']) > 5 ? ' та інші' : '') . '.' : '')
                : 'Нічого переносити: усе, що збіглося, у нас уже заповнене.');
        redirect($back);
    }

    private static function uploadPath(): ?string
    {
        $p = (string)($_SESSION['vchasno_goods_file'] ?? '');
        return $p !== '' && is_file($p) ? $p : null;
    }

    private static function forgetUpload(): void
    {
        $p = (string)($_SESSION['vchasno_goods_file'] ?? '');
        if ($p !== '' && is_file($p)) @unlink($p);
        unset($_SESSION['vchasno_goods_file'], $_SESSION['vchasno_goods_name']);
    }
}
