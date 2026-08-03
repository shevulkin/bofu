<?php
declare(strict_types=1);

/**
 * Промокоди. Одне місце перевірки на всіх: і на кнопці «Застосувати», і при
 * підтвердженні замовлення. Інакше форма показувала б одну знижку, а в
 * замовлення потрапляла б інша.
 *
 * Головне правило: діє рівно той код, що зараз у полі. Сесія лише памʼятає
 * його між сторінками, але сама по собі знижки не дає.
 */
class Promo
{
    /**
     * Значення з форми. Кодом є лише рядок: `promo_code[]=A&promo_code[]=B` —
     * спроба протягнути два коди одним запитом, і масив тут стає порожнечею,
     * а не «Array». Діяти може рівно один код, бо знижка рахується з одного.
     */
    public static function fromInput($value): string
    {
        return is_string($value) ? $value : '';
    }

    /** Код із бази, якщо він існує, увімкнений і не протермінований */
    public static function find(?string $code): ?array
    {
        $code = mb_strtoupper(trim((string)$code));
        if ($code === '') return null;
        $row = DB::row('SELECT * FROM promo_codes WHERE code = ? AND active = 1', [$code]);
        if (!$row) return null;
        $exp = trim((string)($row['expires_at'] ?? ''));
        if ($exp !== '' && $exp < date('Y-m-d')) return null;
        return $row;
    }

    /**
     * Чи можна скористатися кодом саме цій людині зараз.
     *
     * Ліміти два й вони незалежні: max_uses — скільки разів кодом скористаються
     * всі разом (1 = код для однієї людини), per_user_limit — скільки разів та
     * сама людина (1 = кожному по разу, порожньо = хоч при кожній покупці).
     *
     * @return array{0:?array,1:string} код і порожній рядок — або null і причина
     */
    public static function check(?string $code, ?int $userId = null, ?string $phone = null): array
    {
        $row = self::find($code);
        if (!$row) return [null, 'Такий промокод не діє — перевірте написання або строк дії'];

        $max = (int)($row['max_uses'] ?? 0);
        if ($max > 0 && self::usedTotal((int)$row['id']) >= $max) {
            return [null, $max === 1
                ? 'Цей код одноразовий і вже використаний'
                : 'Ліміт використань цього коду вичерпано'];
        }
        // Гостя без номера порахувати нічим — тоді ліміт «на людину» перевіримо
        // при підтвердженні замовлення, коли номер уже введено.
        $per = (int)($row['per_user_limit'] ?? 0);
        if ($per > 0 && ($userId || $phone) && self::usedBy((int)$row['id'], $userId, $phone) >= $per) {
            return [null, $per === 1
                ? 'Цим кодом ви вже скористались — він діє один раз на людину'
                : 'Ви вже використали цей код максимальну кількість разів'];
        }
        return [$row, ''];
    }

    /** Скільки разів кодом скористались усі разом */
    public static function usedTotal(int $promoId): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM promo_uses WHERE promo_id = ?', [$promoId]);
    }

    /**
     * Скільки разів кодом скористалась ця людина. Залогінену впізнаємо за
     * акаунтом, гостя — за номером: іншого сталого імені в нас немає.
     */
    public static function usedBy(int $promoId, ?int $userId, ?string $phone): int
    {
        $where = []; $args = [$promoId];
        if ($userId) { $where[] = 'user_id = ?'; $args[] = $userId; }
        if ($phone) { $where[] = 'phone = ?'; $args[] = $phone; }
        if (!$where) return 0;
        return (int)DB::val('SELECT COUNT(*) FROM promo_uses WHERE promo_id = ? AND (' . implode(' OR ', $where) . ')', $args);
    }

    /** Записати факт використання — рівно один рядок на замовлення */
    public static function recordUse(array $promo, ?int $orderId, ?int $userId, ?string $phone): void
    {
        DB::insert('promo_uses', [
            'promo_id' => (int)$promo['id'], 'code' => (string)$promo['code'],
            'order_id' => $orderId, 'user_id' => $userId, 'phone' => $phone,
            'created_at' => now(),
        ]);
    }

    /**
     * Перевірити код і запамʼятати робочий у сесії (хибний або вичерпаний — забути).
     * @return array{0:?array,1:string} код і порожній рядок — або null і причина
     */
    public static function apply(?string $code, ?int $userId = null, ?string $phone = null): array
    {
        [$row, $err] = self::check($code, $userId, $phone);
        if ($row) $_SESSION['promo_code'] = (string)$row['code'];
        else unset($_SESSION['promo_code']);
        return [$row, $err];
    }

    /**
     * Код, що діє зараз. Якщо форма щось прислала — головне вона (у полі
     * покупець бачить те, за що погоджується платити). Без POST показуємо
     * збережений у сесії — але так само перевіреним: код міг вичерпатись,
     * поки сторінка лежала відкритою.
     */
    public static function current(?string $posted = null, ?int $userId = null, ?string $phone = null): ?array
    {
        if ($posted !== null) return self::apply($posted, $userId, $phone)[0];
        return self::check($_SESSION['promo_code'] ?? null, $userId, $phone)[0];
    }

    public static function forget(): void { unset($_SESSION['promo_code']); }

    /**
     * Знижка на суму позиції. $ownPct — знижка, яка на цій позиції вже є
     * (акція магазину чи стара ціна); від неї залежить, чи додасться код і
     * наскільки. Округлення тут одне на весь код — див. Cart::total.
     */
    public static function cut(float $sum, ?array $promo, float $ownPct = 0.0): float
    {
        if (!$promo || $sum <= 0) return 0.0;
        $pct = self::extraPercent($promo, $ownPct);
        return $pct > 0 ? round($sum * $pct / 100, 2) : 0.0;
    }

    /**
     * Скільки відсотків код реально додасть до позиції, яка вже має знижку $ownPct.
     * Не сумується — 0 на будь-якій зниженій ціні; є стеля — код обрізається
     * рівно до неї (акція 20% + код 15% зі стелею 25% дають 5, а не 15).
     */
    public static function extraPercent(array $promo, float $ownPct = 0.0): float
    {
        $pct = (float)$promo['percent'];
        if ($ownPct > 0 && !self::stacks($promo)) return 0.0;
        $cap = $promo['max_total_percent'] ?? null;
        if ($cap !== null && $cap !== '') {
            $left = (float)$cap - $ownPct;
            $pct = min($pct, max(0.0, $left));
        }
        return $pct;
    }

    /** Чи складається код із наявними знижками (за замовчуванням — так) */
    public static function stacks(array $promo): bool
    {
        return !array_key_exists('stackable', $promo) || $promo['stackable'] === null || (int)$promo['stackable'] === 1;
    }

    /** Знижка, яка вже є на позиції: скільки відсотків від старої ціни зняли */
    public static function ownPercent(array $row): float
    {
        $old = (float)($row['old'] ?? 0);
        $price = (float)($row['price'] ?? 0);
        if ($old <= 0 || $price <= 0 || $old <= $price) return 0.0;
        return round(($old - $price) / $old * 100, 2);
    }

    /**
     * Чому знижка вийшла меншою за відсоток коду (або її немає зовсім).
     * Порожній рядок — коли код ліг на все й повністю, і пояснювати нічого.
     */
    public static function note(?array $promo, array $rows): string
    {
        if (!$promo) return '';
        $skipped = 0; $applied = 0; $capped = 0;
        foreach ($rows as $r) {
            $sum = (float)($r['sum'] ?? 0);
            if ($sum <= 0) continue;
            $eff = self::extraPercent($promo, self::ownPercent($r));
            if ($eff <= 0) { $skipped++; continue; }
            $applied++;
            if ($eff < (float)$promo['percent'] - 0.001) $capped++;
        }
        if ($skipped && !$applied) return 'Код не діє на товари, які вже продаються зі знижкою';
        if ($skipped) return 'На товари, що вже зі знижкою, код не поширюється';
        if ($capped) {
            $cap = rtrim(rtrim(number_format((float)$promo['max_total_percent'], 2, ',', ''), '0'), ',');
            return 'Сумарна знижка на товар обмежена ' . $cap . '%, тож на акційні позиції код додав менше';
        }
        return '';
    }

    /** «Знижка (MED10 −10%)» — підпис для рядка підсумків */
    public static function label(array $promo): string
    {
        $pct = rtrim(rtrim(number_format((float)$promo['percent'], 2, ',', ''), '0'), ',');
        return 'Знижка (' . $promo['code'] . ' −' . $pct . '%)';
    }
}
