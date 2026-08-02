<?php
declare(strict_types=1);

/**
 * Автентифікація та права.
 *
 * Права — обʼєднання прав усіх ролей користувача (user_roles). Понад це є
 * необовʼязковий вибір робочої ролі: людина може діяти як інша роль, щоб перевірити,
 * як виглядає система її очима. Такий режим завжди лише ЗВУЖУЄ права
 * (Roles::narrow — перетин), тож підробка сесії не здатна нічого видати,
 * максимум — урізати права самому собі.
 */
class Auth
{
    /** Ролі за id користувача — у межах одного запиту вони не змінюються самі по собі */
    private static array $rolesCache = [];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessDir = BOFU_ROOT . '/storage/sessions';
            if (!is_dir($sessDir)) @mkdir($sessDir, 0775, true);
            if (is_writable($sessDir)) session_save_path($sessDir);
            session_name(cfg('session_name', 'bofu_sid'));
            // не приймати ідентифікатор сесії, якого ми не видавали (фіксація сесії)
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                // на HTTPS-хостингу кука не має витікати у відкритий http
                'secure' => $https,
                'path' => base_url('/'),
            ]);
            session_start();
        }
    }

    public static function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) return null;
        static $cache = null;
        if ($cache && (int)$cache['id'] === (int)$id) return $cache;
        $cache = DB::row('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
        return $cache;
    }

    public static function id(): ?int { $u = self::user(); return $u ? (int)$u['id'] : null; }
    public static function check(): bool { return self::user() !== null; }

    // ── Ролі ────────────────────────────────────────────────────────────────

    /** Ролі користувача: рядки з user_roles плюс «покупець», який є в кожного, хто увійшов */
    public static function roles(?int $userId = null): array
    {
        $uid = $userId ?? self::id();
        if (!$uid) return [];
        if (isset(self::$rolesCache[$uid])) return self::$rolesCache[$uid];
        $rows = DB::all('SELECT role FROM user_roles WHERE user_id = ?', [$uid]);
        $roles = [];
        foreach ($rows as $r) {
            $role = (string)$r['role'];
            if (Roles::exists($role) && $role !== Roles::CUSTOMER) $roles[$role] = true;
        }
        $roles[Roles::CUSTOMER] = true;
        // впорядковуємо за старшинством з реєстру, щоб інтерфейс був передбачуваним
        return self::$rolesCache[$uid] = array_values(array_filter(Roles::all(), fn($r) => isset($roles[$r])));
    }

    /** Скинути кеш ролей — після зміни ролей у межах того самого запиту */
    public static function forgetRoles(?int $userId = null): void
    {
        if ($userId === null) self::$rolesCache = [];
        else unset(self::$rolesCache[$userId]);
    }

    /** Ролі, що дають доступ до кабінету (усе, крім покупця) */
    public static function staffRoles(?int $userId = null): array
    {
        return array_values(array_diff(self::roles($userId), [Roles::CUSTOMER]));
    }

    /** Повні права користувача — без урахування режиму перегляду */
    public static function ownCaps(): array
    {
        return Roles::capsOf(self::roles());
    }

    /** Чинні права: повні, звужені роллю, яку зараз вдають */
    public static function caps(): array
    {
        $own = self::ownCaps();
        $act = self::actingAs();
        return $act === null ? $own : Roles::narrow($own, Roles::caps($act));
    }

    public static function can(string $cap): bool { return Roles::allows(self::caps(), $cap); }

    /**
     * Роль для показу в інтерфейсі. Для перевірки прав НЕ використовувати —
     * для цього є can(): роль одна, а прав може бути кілька.
     */
    public static function role(): string
    {
        $act = self::actingAs();
        if ($act !== null) return $act;
        return self::staffRoles()[0] ?? Roles::CUSTOMER;
    }

    /** Чи має людина хоч якісь права в кабінеті (з урахуванням режиму перегляду) */
    public static function isStaff(): bool { return self::caps() !== []; }

    /** Повні права. Лишається для сумісності; у нових місцях — can() з конкретним правом */
    public static function isAdmin(): bool { return in_array('*', self::caps(), true); }

    // ── Робоча роль (симуляція) ─────────────────────────────────────────────

    /** Роль, яку зараз вдають, або null — діємо на повних правах */
    public static function actingAs(): ?string
    {
        $r = $_SESSION['act_as'] ?? null;
        return (is_string($r) && Roles::exists($r)) ? $r : null;
    }

    /** Магазин, обраний для режиму продавця (щоб бачити те саме, що бачить він) */
    public static function actingStoreId(): ?int
    {
        $s = $_SESSION['act_store'] ?? null;
        return $s ? (int)$s : null;
    }

    /**
     * Вдавати можна будь-яку роль, чиї права є підмножиною власних.
     * Звідси само собою, без жодних винятків у коді: адмін (усі права) перевіряє
     * будь-яку роль; «провалитись» у покупця може кожен зі стафу, бо порожня
     * множина вкладена в будь-яку; а продавець не вдасть редактора.
     * Власні ролі теж проходять — їхні права за побудовою входять в обʼєднання.
     */
    public static function canSimulate(string $role): bool
    {
        if (!Roles::exists($role) || !self::check()) return false;
        if (self::staffRoles() === []) return false;   // покупцеві нема що вдавати
        return Roles::covers(self::ownCaps(), $role);
    }

    /**
     * Ролі для перемикача. Пропонуємо лише ті, що справді щось міняють:
     * власна роль нічого не звужує, а роль без прав (поки такий editor) у списку
     * лише збиває з пантелику. Тому — робочі ролі плюс покупець, і тільки ті,
     * від яких набір прав дійсно зменшується.
     */
    public static function simulatableRoles(): array
    {
        if (self::staffRoles() === []) return [];
        $own = self::ownCaps();
        sort($own);
        $offer = array_merge(Roles::assignable(), [Roles::CUSTOMER]);
        return array_values(array_filter(Roles::all(), function ($r) use ($offer, $own) {
            if (!in_array($r, $offer, true) || !self::canSimulate($r)) return false;
            $narrowed = Roles::narrow($own, Roles::caps($r));
            sort($narrowed);
            return $narrowed !== $own;
        }));
    }

    public static function simulate(string $role, ?int $storeId = null): bool
    {
        if (!self::canSimulate($role)) return false;
        // магазин для режиму продавця приймаємо лише з тих, до яких доступ уже є,
        // інакше перемикач став би способом зазирнути в чужу точку
        if ($role === Roles::SELLER && $storeId !== null && !in_array($storeId, self::authorityStoreIds(), true)) {
            return false;
        }
        $_SESSION['act_as'] = $role;
        $_SESSION['act_store'] = $role === Roles::SELLER ? $storeId : null;
        return true;
    }

    public static function stopSimulating(): void
    {
        unset($_SESSION['act_as'], $_SESSION['act_store']);
    }

    // ── Магазини ────────────────────────────────────────────────────────────

    /**
     * Магазини, доступні користувачу. Призначення (seller_stores) не пов'язане з роллю:
     * це окрема прив'язка людини до точки. Роль лише вирішує, чи діє обмеження взагалі.
     */
    /**
     * Магазини, доступні за СПРАВЖНІМИ правами. Обрану роль тут не враховуємо
     * навмисно: він обирає, на яку з доступних точок дивимось, але не може ні
     * відкрити чужу, ні відібрати власну.
     */
    public static function authorityStoreIds(): array
    {
        if (!self::check()) return [];
        if (Roles::allows(self::ownCaps(), 'stores.all')) {
            return array_map(fn($r) => (int)$r['id'], DB::all('SELECT id FROM stores'));
        }
        return self::assignedStoreIds();
    }

    public static function storeIds(): array
    {
        $authority = self::authorityStoreIds();
        if (self::actingAs() !== Roles::SELLER) return $authority;
        $sid = self::actingStoreId();
        // обраний магазин має бути серед доступних, інакше перемикач став би
        // способом зазирнути в чужу точку через підміну сесії
        if ($sid !== null) return in_array($sid, $authority, true) ? [$sid] : [];
        // магазин не обрано: показуємо власні призначення. Інтерфейс завжди пропонує
        // вибір, тож сюди потрапляє хіба що стара сесія.
        return self::assignedStoreIds();
    }

    /** Магазини, до яких людину призначено, — незалежно від ролі й режиму перегляду */
    public static function assignedStoreIds(?int $userId = null): array
    {
        $uid = $userId ?? self::id();
        if (!$uid) return [];
        return array_map(fn($r) => (int)$r['store_id'],
            DB::all('SELECT store_id FROM seller_stores WHERE user_id = ?', [$uid]));
    }

    // ── Вхід і вихід ────────────────────────────────────────────────────────

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        self::stopSimulating();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    // ── Гейти ───────────────────────────────────────────────────────────────

    public static function requireStaff(): void
    {
        if (!self::isStaff()) { flash('error', 'Потрібен вхід із правами персоналу.'); redirect('/'); }
    }

    /**
     * Гейт «потрібні повні права». Лишається для наявних контролерів; у міру того,
     * як кожен дістане своє конкретне право, ці виклики заміняються на requireCap().
     */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) self::deny();
    }

    /**
     * Гейт на конкретне право. Ставиться і на маршрут, і всередині кожної дії,
     * що змінює дані: приховати кнопку — не перевірка.
     */
    public static function requireCap(string $cap): void
    {
        if (!self::can($cap)) self::deny($cap);
    }

    /**
     * Відмова з поясненням. Якщо права насправді є і бракує їх лише через режим
     * перегляду — кажемо саме це: інакше людина вирішить, що режим зламаний,
     * і просто перестане ним користуватись.
     */
    private static function deny(?string $cap = null): void
    {
        $act = self::actingAs();
        $hasReally = $cap === null ? in_array('*', self::ownCaps(), true) : Roles::allows(self::ownCaps(), $cap);
        if ($act !== null && $hasReally) {
            flash('error', 'У ролі «' . Roles::label($act) . '» це недоступно. '
                . 'Поверніться до своїх прав перемикачем угорі.');
        } else {
            flash('error', 'Недостатньо прав для цієї дії.');
        }
        // на ту саму сторінку не кидаємо — інакше відмова зациклиться редіректом
        $home = (self::isStaff() && request_path() !== '/admin') ? '/admin' : '/';
        redirect($home);
    }

    /** Знайти або створити користувача Google */
    public static function loginWithGoogle(array $profile): void
    {
        $user = DB::row('SELECT * FROM users WHERE google_id = ? OR email = ?', [$profile['sub'], $profile['email']]);
        if ($user) {
            DB::update('users', [
                'google_id' => $profile['sub'],
                'name' => $profile['name'] ?? $user['name'],
                'avatar' => $profile['picture'] ?? $user['avatar'],
            ], 'id = ?', [$user['id']]);
            $id = (int)$user['id'];
        } else {
            $id = DB::insert('users', [
                'google_id' => $profile['sub'], 'email' => $profile['email'],
                'name' => $profile['name'] ?? $profile['email'], 'avatar' => $profile['picture'] ?? null,
                'role' => Roles::CUSTOMER, 'active' => 1, 'created_at' => now(),
            ]);
        }
        self::login($id);
    }
}
