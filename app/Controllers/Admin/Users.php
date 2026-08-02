<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Catalog, Roles, AuthTokens, Telegram, Viber, RateLimit;

class Users
{
    public static function index(): never
    {
        Auth::requireCap('users.manage');
        if (is_post()) {
            $uid = (int)($_POST['user_id'] ?? 0);
            $user = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($user) {
                $roles = array_values(array_intersect(
                    array_map('strval', (array)($_POST['roles'] ?? [])), Roles::assignable()));
                // інакше можна лишити систему без жодного адміністратора
                [$phone, $phoneErr] = self::readPhone($uid, $_POST['phone'] ?? null);
                if ((int)$user['id'] === (int)Auth::id() && !in_array(Roles::ADMIN, $roles, true)) {
                    flash('error', 'Не можна зняти адмін-права із самого себе');
                } elseif ($phoneErr !== null) {
                    // ролі й точки теж не чіпаємо: половина збереження гірша за жодну —
                    // людина бачить помилку і думає, що не збереглось нічого
                    flash('error', $phoneErr);
                } else {
                    $upd = [
                        'active' => isset($_POST['active']) ? 1 : 0,
                        'tg_chat_id' => trim($_POST['tg_chat_id'] ?? '') ?: null,
                    ];
                    // телефон пишемо, лише якщо поле було у формі
                    if (array_key_exists('phone', $_POST)) $upd['phone'] = $phone;
                    DB::update('users', $upd, 'id = ?', [$uid]);
                    $had = self::storeCount($uid);
                    self::saveRoles($uid, $roles);
                    self::saveStores($uid, (array)($_POST['stores'] ?? []), $roles);
                    Auth::forgetRoles($uid);
                    flash('success', $had > 0 && !in_array(Roles::SELLER, $roles, true)
                        ? 'Користувача оновлено. Роль продавця знято — доступ до магазинів відкликано, при поверненні ролі точки треба призначити наново.'
                        : 'Користувача оновлено');
                }
            }
            redirect('/admin/users');
        }
        $userRoles = [];
        foreach (DB::all('SELECT user_id, role FROM user_roles') as $r) {
            $userRoles[(int)$r['user_id']][] = (string)$r['role'];
        }
        $sellerStores = [];
        foreach (DB::all('SELECT * FROM seller_stores') as $r) $sellerStores[$r['user_id']][] = (int)$r['store_id'];
        View::show('admin/users', [
            'users' => DB::all('SELECT * FROM users ORDER BY id'),
            'stores' => Catalog::stores(),
            'seller_stores' => $sellerStores,
            'user_roles' => $userRoles,
            'assignable' => Roles::assignable(),
            'page_title' => 'Користувачі та ролі — адмінка',
        ], 'layouts/admin');
    }

    /**
     * Ролі зберігаємо різницею, а не «стерти й записати»: так ролі, яких немає
     * у формі (наприклад ще не задіяний editor), не зникнуть мовчки.
     */
    private static function saveRoles(int $uid, array $roles): void
    {
        $managed = Roles::assignable();
        $current = array_map(fn($r) => (string)$r['role'],
            DB::all('SELECT role FROM user_roles WHERE user_id = ?', [$uid]));
        foreach (array_diff(array_intersect($current, $managed), $roles) as $r) {
            DB::delete('user_roles', 'user_id = ? AND role = ?', [$uid, $r]);
        }
        foreach (array_diff($roles, $current) as $r) {
            DB::insert('user_roles', ['user_id' => $uid, 'role' => $r, 'created_at' => now()]);
        }
    }

    /**
     * Телефон із форми: [нормалізований або null, помилка або null].
     *
     * Правило те саме, що в checkout і профілі (normPhoneAny) — сюди номер вписує
     * адмін за людину, і розбіжність означала б, що адмін може завести номер,
     * який сама людина потім не збереже.
     */
    private static function readPhone(int $uid, $raw): array
    {
        if ($raw === null) return [null, null];              // поля не було у формі
        $t = trim((string)$raw);
        if ($t === '') return [null, null];                  // очистили — гейт попросить свій
        $phone = AuthTokens::normPhoneAny($t);
        if (!$phone) {
            return [null, 'Номер «' . $t . '» некоректний. Український — 067 123 45 67, іноземний — з кодом країни через +'];
        }
        // телефон = логін для входу за номером, тож він має бути в одного власника
        if (DB::row('SELECT id FROM users WHERE phone = ? AND id != ?', [$phone, $uid])) {
            return [null, 'Номер ' . $phone . ' вже привʼязаний до іншого акаунта'];
        }
        return [$phone, null];
    }

    /**
     * Написати людині від імені бота — туди, де вона вже привʼязана.
     * Каналів «про запас» тут немає: пишемо лише в підключений бот і лише тому,
     * хто його підключив, бо інакше повідомлення тихо зникне.
     */
    public static function message(): never
    {
        Auth::requireCap('users.manage');
        $uid = (int)($_POST['user_id'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $channel = (string)($_POST['channel'] ?? '');
        $user = DB::row('SELECT * FROM users WHERE id = ?', [$uid]);

        if (!$user || $text === '') {
            flash('error', 'Повідомлення порожнє — нічого не надіслано');
            redirect('/admin/users');
        }
        RateLimit::guard('admin_msg', 60, 3600);
        $text = mb_substr($text, 0, 2000);

        if ($channel === 'telegram' && Telegram::configured() && !empty($user['tg_chat_id'])) {
            Telegram::send((string)$user['tg_chat_id'], $text);
            flash('success', 'Надіслано в Telegram: ' . $user['name']);
        } elseif ($channel === 'viber' && Viber::configured() && !empty($user['viber_id'])) {
            Viber::send((string)$user['viber_id'], $text);
            flash('success', 'Надіслано у Viber: ' . $user['name']);
        } else {
            flash('error', 'Цей канал недоступний: бот не налаштований або людина його не підключила');
        }
        redirect('/admin/users');
    }

    /** Скільки точок закріплено за людиною зараз — щоб знати, чи було що відкликати */
    private static function storeCount(int $uid): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM seller_stores WHERE user_id = ?', [$uid]);
    }

    /**
     * Призначення точок живе лише разом із роллю продавця. Знімають роль — людина
     * зникає з усіх магазинів; повернення ролі доступу не відновлює, точки треба
     * призначити наново.
     *
     * Так звільнений продавець не лишається тихо прив'язаним до точки: інакше
     * рядок у seller_stores пережив би звільнення, і роль, повернена через рік
     * зовсім для іншого магазину, мовчки відкрила б йому старі залишки й
     * замовлення. Право має закінчуватись разом із підставою, а не чекати,
     * поки хтось згадає почистити прив'язки.
     *
     * Єдиний вхід для зміни прив'язок — тому public: правило перевіряється тестом
     * (tests/roles.php), а не лише очима на формі.
     */
    public static function saveStores(int $uid, array $ids, array $roles): void
    {
        DB::delete('seller_stores', 'user_id = ?', [$uid]);
        if (!in_array(Roles::SELLER, $roles, true)) return;   // немає ролі — немає й точок
        $valid = array_map(fn($r) => (int)$r['id'], DB::all('SELECT id FROM stores'));
        $want = array_values(array_unique(array_intersect(array_map('intval', $ids), $valid)));
        foreach ($want as $sid) {
            DB::insert('seller_stores', ['user_id' => $uid, 'store_id' => $sid]);
        }
    }
}
