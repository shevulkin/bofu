<?php
/**
 * Курс — товар, але з трьома відмінностями, і кожна тут перевіряється.
 *
 *  1. Його не буває «мало»: складу немає, тож ані кошик, ані оформлення не
 *     мають рахувати залишок. Інакше курс, у якого нуль на складі (а він там
 *     завжди нуль), не продався б жодного разу.
 *  2. Його нікуди не везти: замовлення з самих курсів отримує спосіб
 *     'digital', і адреси в нього немає.
 *  3. Змішаний кошик цифровим НЕ стає: поруч із курсом лежить банка меду, і
 *     її все одно треба везти.
 *
 * Запуск: php bin/cli.php test
 */
declare(strict_types=1);

final class CoursesTest
{
    private int $pass = 0;
    private int $fail = 0;
    private array $products = [];
    private array $cats = [];

    private function ok(string $what, bool $cond): void
    {
        if ($cond) { $this->pass++; echo "  ✓ $what\n"; }
        else { $this->fail++; echo "  ✗ $what\n"; }
    }

    private function group(string $n): void { echo "\n== $n ==\n"; }

    private function cat(string $type): int
    {
        $id = DB::insert('categories', ['name' => 'Тест ' . $type, 'type' => $type,
            'slug' => 'test-' . $type . '-' . bin2hex(random_bytes(4)), 'active' => 1]);
        $this->cats[] = $id;
        return $id;
    }

    /** Товар без жодного залишку й без «під замовлення» — найсуворіший випадок */
    private function product(string $type): array
    {
        $id = DB::insert('products', [
            'category_id' => $this->cat($type), 'name' => 'Тест ' . $type,
            'slug' => 'test-p-' . bin2hex(random_bytes(5)),
            'base_price' => 1000, 'type' => $type, 'active' => 1,
            'made_to_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->products[] = $id;
        return DB::row('SELECT * FROM products WHERE id = ?', [$id]);
    }

    /** Рядок кошика в тому вигляді, у якому його бачить Cart::detailed() */
    private function row(array $product, int $qty = 1): array
    {
        return ['product' => $product, 'variant' => null, 'qty' => $qty];
    }

    public function run(): int
    {
        $course = $this->product(Courses::TYPE);
        $honey  = $this->product('product');

        $this->group('що вважається курсом');
        $this->ok('товар із type=course — курс', Courses::isCourse($course));
        $this->ok('звичайний товар — ні', !Courses::isCourse($honey));
        $this->ok('порожнеча не ламає перевірку', !Courses::isCourse(null));

        $this->group('курсу не буває «мало»');
        // Обидва без залишків і без «під замовлення» — різниця лише в типі
        $this->ok('кошик не обмежує курс',
            Cart::limit((int)$course['id'], null) === null);
        $this->ok('а звичайний товар без залишку — обмежує нулем',
            Cart::limit((int)$honey['id'], null) === 0);
        $this->ok('оформлення не рахує курс браком',
            OrderFlow::unavailable([$this->row($course)]) === []);
        $this->ok('а звичайний товар — рахує',
            count(OrderFlow::unavailable([$this->row($honey)])) === 1);

        $this->group('доставка цифрового замовлення');
        $this->ok('самі курси — цифрове', Courses::cartIsDigital([$this->row($course)]));
        $this->ok('курс і мед разом — ні (мед треба везти)',
            !Courses::cartIsDigital([$this->row($course), $this->row($honey)]));
        $this->ok('самий лише мед — ні', !Courses::cartIsDigital([$this->row($honey)]));
        // Порожній кошик цифровим не рахуємо: інакше чекаут сховав би доставку
        // ще до того, як покупець щось поклав
        $this->ok('порожній кошик — не цифровий', !Courses::cartIsDigital([]));

        $this->ok('спосіб «digital» відомий системі',
            isset(OrderFlow::DELIVERY['digital']));
        $this->ok('адреси в цифрового замовлення немає',
            OrderFlow::deliveryAddress(['delivery' => 'digital', 'city' => 'Київ']) === '');

        $this->group('курси в замовленні знаходяться по всьому дереву');
        // Позиції лежать у ПІДзамовленні — саме там, де їх шукати найлегше забути
        $parent = DB::insert('orders', ['number' => 'TSTC-' . bin2hex(random_bytes(4)),
            'name' => 'Тест', 'phone' => '+380670000000', 'delivery' => 'digital',
            'status' => 'new', 'total' => 1000, 'created_at' => now()]);
        $child = DB::insert('orders', ['number' => 'TSTC-' . bin2hex(random_bytes(4)) . '/1',
            'parent_id' => $parent, 'name' => 'Тест', 'phone' => '+380670000000',
            'delivery' => 'digital', 'status' => 'new', 'total' => 1000, 'created_at' => now()]);
        DB::insert('order_items', ['order_id' => $child, 'product_id' => (int)$course['id'],
            'title' => $course['name'], 'price' => 1000, 'qty' => 1, 'sum' => 1000]);

        $this->ok('знаходиться за головним замовленням', count(Courses::inOrder($parent)) === 1);
        $this->ok('і за його частиною', count(Courses::inOrder($child)) === 1);

        DB::pdo()->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$child]);
        foreach ([$child, $parent] as $o) DB::pdo()->prepare('DELETE FROM orders WHERE id = ?')->execute([$o]);
        foreach ($this->products as $id) DB::pdo()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        foreach ($this->cats as $id) DB::pdo()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);

        echo $this->fail === 0
            ? "\nУСЕ ДОБРЕ: {$this->pass} перевірок\n"
            : "\nПРОВАЛЕНО: {$this->fail} із " . ($this->pass + $this->fail) . "\n";
        return $this->fail === 0 ? 0 : 1;
    }
}

return (new CoursesTest())->run();
