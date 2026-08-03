<?php
/**
 * Промокоди й перерахунок сум.  Запуск: php bin/cli.php test
 *
 * Головне, що доводимо: знижка, яку покупець побачив у формі, дорівнює тій,
 * що потрапляє в замовлення. Тому знижка рахується по позиціях, а не від
 * загальної суми: інакше сума перерахованих товарів і «до сплати» різнились
 * би на копійки, і форма суперечила б сама собі.
 *
 * Тест створює власний товар і власний код, а наприкінці прибирає обидва.
 */
declare(strict_types=1);

final class PromoTest
{
    private int $pass = 0;
    private int $fail = 0;
    private int $productId = 0;
    private array $codes = [];

    public function run(): int
    {
        $this->setUp();
        try {
            $this->testValidation();
            $this->testSession();
            $this->testTotals();
            $this->testRounding();
        } finally {
            $this->tearDown();
        }
        echo "\n" . ($this->fail === 0
            ? "УСЕ ДОБРЕ: {$this->pass} перевірок\n"
            : "ПРОВАЛЕНО: {$this->fail} з " . ($this->pass + $this->fail) . "\n");
        return $this->fail === 0 ? 0 : 1;
    }

    private function setUp(): void
    {
        $cat = (int)(DB::val('SELECT id FROM categories ORDER BY id LIMIT 1') ?? 0);
        $this->productId = DB::insert('products', [
            'category_id' => $cat, 'name' => 'Тестовий мед', 'slug' => 'test-promo-' . bin2hex(random_bytes(3)),
            'base_price' => 100, 'active' => 1, 'made_to_order' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->mkCode('TESTPROMO10', 10);
        $this->mkCode('TESTOFF', 20, ['active' => 0]);
        $this->mkCode('TESTOLD', 25, ['expires_at' => date('Y-m-d', strtotime('-1 day'))]);
        $this->mkCode('TESTTODAY', 30, ['expires_at' => date('Y-m-d')]);
        $_SESSION = [];
    }

    private function mkCode(string $code, float $percent, array $extra = []): void
    {
        DB::delete('promo_codes', 'code = ?', [$code]);
        $this->codes[] = $code;
        DB::insert('promo_codes', array_merge(
            ['code' => $code, 'percent' => $percent, 'active' => 1, 'expires_at' => null], $extra));
    }

    private function tearDown(): void
    {
        foreach ($this->codes as $c) DB::delete('promo_codes', 'code = ?', [$c]);
        if ($this->productId) DB::delete('products', 'id = ?', [$this->productId]);
        $_SESSION = [];
    }

    private function ok(string $what, bool $cond): void
    {
        if ($cond) { $this->pass++; echo "  ok   $what\n"; }
        else { $this->fail++; echo "  FAIL $what\n"; }
    }

    private function group(string $name): void { echo "\n== $name ==\n"; }

    private function testValidation(): void
    {
        $this->group('який код узагалі діє');
        $this->ok('робочий код знайдено', (Promo::find('TESTPROMO10')['percent'] ?? null) !== null);
        $this->ok('регістр не має значення', (Promo::find('testpromo10')['code'] ?? '') === 'TESTPROMO10');
        $this->ok('пробіли з боків не заважають', Promo::find('  TESTPROMO10  ') !== null);
        $this->ok('порожній рядок — не код', Promo::find('') === null);
        $this->ok('null — не код', Promo::find(null) === null);
        $this->ok('вигаданого коду немає', Promo::find('НЕМАЄТАКОГО') === null);
        $this->ok('вимкнений код не діє', Promo::find('TESTOFF') === null);
        $this->ok('протермінований не діє', Promo::find('TESTOLD') === null);
        // останній день має працювати: інакше знижка «до 31 числа» зникає 31-го
        $this->ok('останній день дії ще працює', Promo::find('TESTTODAY') !== null);
    }

    private function testSession(): void
    {
        $this->group('що памʼятає сесія');
        $_SESSION = [];
        Promo::apply('TESTPROMO10');
        $this->ok('робочий код запамʼятався', ($_SESSION['promo_code'] ?? '') === 'TESTPROMO10');
        $this->ok('без POST діє збережений', (Promo::current()['code'] ?? '') === 'TESTPROMO10');

        // Поле у формі головніше за памʼять: покупець платить за те, що бачить.
        $this->ok('хибний код із форми скасовує збережений', Promo::current('ХИБНИЙ') === null);
        $this->ok('і забувається', !isset($_SESSION['promo_code']));

        Promo::apply('TESTPROMO10');
        $this->ok('порожнє поле теж скасовує', Promo::current('') === null);
        $this->ok('памʼять очищено', !isset($_SESSION['promo_code']));
    }

    private function testTotals(): void
    {
        $this->group('перерахунок сум');
        $_SESSION['cart'] = [$this->productId . ':0' => ['product_id' => $this->productId, 'variant_id' => null, 'qty' => 3]];
        $promo = Promo::find('TESTPROMO10');

        $plain = Cart::total(null, null);
        $this->ok('без коду знижки немає', $plain['discount'] === 0.0 && $plain['total'] === 300.0);

        $t = Cart::total(null, $promo);
        $this->ok('знижка 10% від 300 = 30', $t['discount'] === 30.0);
        $this->ok('до сплати 270', $t['total'] === 270.0);
        $this->ok('сума товарів не змінилась', $t['subtotal'] === 300.0);

        $this->ok('знижка на позицію рахується так само', Promo::cut(300.0, $promo) === 30.0);
        $this->ok('без коду позиція не дешевшає', Promo::cut(300.0, null) === 0.0);
        $this->ok('підпис показує і код, і відсоток', Promo::label($promo) === 'Знижка (TESTPROMO10 −10%)');
    }

    /**
     * Копійки. Три позиції по 33.33 з 10% дають 3 × 3.33 = 9.99, а не round(9.999) = 10.
     * Саме перше число покупець побачить у картці замовлення, тож воно й має бути
     * в підсумку — інакше рядки не складаються в «до сплати».
     */
    private function testRounding(): void
    {
        $this->group('копійки сходяться');
        DB::update('products', ['base_price' => 33.33], 'id = ?', [$this->productId]);
        $_SESSION['cart'] = [$this->productId . ':0' => ['product_id' => $this->productId, 'variant_id' => null, 'qty' => 1]];
        $promo = Promo::find('TESTPROMO10');

        $rows = Cart::detailed();
        $t = Cart::total(null, $promo);
        $perItem = 0.0;
        foreach ($rows as $r) $perItem += (float)$r['sum'] - Promo::cut((float)$r['sum'], $promo);
        $this->ok('сума перерахованих позицій = до сплати', abs($perItem - $t['total']) < 0.0001);
        $this->ok('знижка з позиції та сама', $t['discount'] === 3.33);
    }
}

return (new PromoTest())->run();
