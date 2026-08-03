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

    /** Перевірити код і запамʼятати робочий у сесії (хибний — забути) */
    public static function apply(?string $code): ?array
    {
        $row = self::find($code);
        if ($row) $_SESSION['promo_code'] = (string)$row['code'];
        else unset($_SESSION['promo_code']);
        return $row;
    }

    /**
     * Код, що діє зараз. Якщо форма щось прислала — головне вона (у полі
     * покупець бачить те, за що погоджується платити). Без POST показуємо
     * збережений у сесії.
     */
    public static function current(?string $posted = null): ?array
    {
        if ($posted !== null) return self::apply($posted);
        return self::find($_SESSION['promo_code'] ?? null);
    }

    public static function forget(): void { unset($_SESSION['promo_code']); }

    /** Знижка на суму позиції — округлення тут одне на весь код (див. Cart::total) */
    public static function cut(float $sum, ?array $promo): float
    {
        if (!$promo) return 0.0;
        return round($sum * (float)$promo['percent'] / 100, 2);
    }

    /** «Знижка (MED10 −10%)» — підпис для рядка підсумків */
    public static function label(array $promo): string
    {
        $pct = rtrim(rtrim(number_format((float)$promo['percent'], 2, ',', ''), '0'), ',');
        return 'Знижка (' . $promo['code'] . ' −' . $pct . '%)';
    }
}
