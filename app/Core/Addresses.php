<?php
declare(strict_types=1);

/**
 * Збережені адреси доставки покупця — щоб не вводити місто й відділення щоразу.
 *
 * Отримувача тут навмисно немає. Посилку часто відправляють різним людям, і
 * підставлений «за замовчуванням» отримувач — це чужа посилка на чужий телефон.
 * Тому ім'я й телефон отримувача покупець підтверджує в кожному замовленні
 * (Checkout), а адреса лишається спільною.
 */
class Addresses
{
    /** Більше в кабінеті тримати нема сенсу — список перестає бути швидким вибором */
    public const LIMIT = 10;

    /** @return array<int,array> основна першою, далі — нещодавно вживані */
    public static function forUser(?int $userId): array
    {
        if (!$userId) return [];
        return DB::all('SELECT * FROM user_addresses WHERE user_id = ?
                        ORDER BY is_default DESC, used_at DESC, id DESC', [$userId]);
    }

    /** Адреса покупця за id. Чужу не віддає: id приходить із форми, тобто підбирається руками. */
    public static function find(?int $userId, int $id): ?array
    {
        if (!$userId || $id <= 0) return null;
        return DB::row('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /**
     * Зберегти адресу (нову або правку наявної за $id).
     * Повертає id або null, якщо даних недостатньо чи вичерпано ліміт.
     */
    public static function save(?int $userId, array $in, int $id = 0): ?int
    {
        if (!$userId) return null;
        $row = self::normalize($in);
        if ($row === null) return null;

        $own = $id > 0 ? self::find($userId, $id) : null;
        if ($id > 0 && !$own) return null;             // чужий або неіснуючий id

        // Та сама адреса вдруге не створює другий рядок: покупець зберігає ту саму
        // пошту з кожним замовленням, і без цього список заріс би копіями.
        // COALESCE — бо NULL != NULL, і порівняння порожніх полів інакше не спрацює.
        $same = DB::row('SELECT id FROM user_addresses
                         WHERE user_id = ? AND delivery = ?
                           AND COALESCE(city, \'\') = ? AND COALESCE(np_office, \'\') = ?
                           AND COALESCE(address, \'\') = ? AND id <> ?',
            [$userId, $row['delivery'], (string)$row['city'], (string)$row['np_office'],
             (string)$row['address'], $id]);
        $target = $own ?: $same;

        if ($target) {
            // мітку не затираємо порожньою: збереження з checkout її не передає
            if ($row['label'] === null) unset($row['label']);
            DB::update('user_addresses', $row + ['used_at' => now()], 'id = ?', [$target['id']]);
            return (int)$target['id'];
        }
        if (count(self::forUser($userId)) >= self::LIMIT) return null;

        $first = !DB::row('SELECT id FROM user_addresses WHERE user_id = ?', [$userId]);
        return DB::insert('user_addresses', $row + [
            'user_id' => $userId,
            'is_default' => $first ? 1 : 0,   // перша адреса стає основною сама
            'used_at' => now(), 'created_at' => now(),
        ]);
    }

    public static function remove(?int $userId, int $id): void
    {
        $a = self::find($userId, $id);
        if (!$a) return;
        DB::delete('user_addresses', 'id = ?', [$id]);
        // основна не може зникнути безслідно — інакше checkout нічого не підставить
        if ((int)$a['is_default'] === 1) {
            $next = DB::row('SELECT id FROM user_addresses WHERE user_id = ? ORDER BY used_at DESC, id DESC', [$userId]);
            if ($next) self::setDefault($userId, (int)$next['id']);
        }
    }

    /** Основна адреса рівно одна — решту знімаємо в тій самій дії */
    public static function setDefault(?int $userId, int $id): void
    {
        if (!self::find($userId, $id)) return;
        DB::update('user_addresses', ['is_default' => 0], 'user_id = ?', [$userId]);
        DB::update('user_addresses', ['is_default' => 1], 'id = ?', [$id]);
    }

    /** Позначити використаною — щоб недавні адреси були вище в списку */
    public static function touch(?int $userId, int $id): void
    {
        if (!self::find($userId, $id)) return;
        DB::update('user_addresses', ['used_at' => now()], 'id = ?', [$id]);
    }

    /** Підпис для списку: власна мітка або сама адреса */
    public static function title(array $a): string
    {
        $label = trim((string)($a['label'] ?? ''));
        if ($label !== '') return $label;
        if (($a['delivery'] ?? 'np') === 'np') {
            return trim((string)$a['city'] . ($a['np_office'] ? ', ' . $a['np_office'] : ''), ' ,') ?: 'Адреса';
        }
        return trim((string)($a['address'] ?? '')) ?: 'Адреса';
    }

    /**
     * Дані з форми → рядок таблиці. null означає «зберігати нічого»:
     * порожня адреса в списку тільки заважає.
     */
    private static function normalize(array $in): ?array
    {
        $delivery = ($in['delivery'] ?? 'np') === 'other' ? 'other' : 'np';
        $cut = static fn($v, int $max) => mb_substr(trim((string)($v ?? '')), 0, $max);
        $city = $cut($in['city'] ?? '', 160);
        $office = $cut($in['np_office'] ?? '', 200);
        $address = $cut($in['address'] ?? '', 200);
        $label = $cut($in['label'] ?? '', 60);

        if ($delivery === 'np' && $city === '') return null;
        if ($delivery === 'other' && $address === '') return null;

        return [
            'delivery' => $delivery,
            'label' => $label === '' ? null : $label,
            'city' => $city === '' ? null : $city,
            // ref міста Нової Пошти: з ним підказки відділень працюють одразу
            'city_ref' => $cut($in['city_ref'] ?? '', 60) ?: null,
            'np_office' => $office === '' ? null : $office,
            'address' => $address === '' ? null : $address,
        ];
    }
}
