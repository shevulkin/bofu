<?php
declare(strict_types=1);

namespace Controllers;

use View, Cart, Csrf, DB, Catalog;

class CartController
{
    public static function index(): never
    {
        $rows = Cart::detailed();
        // Порожній кошик — це не помилка, а розвилка: людина або ще нічого не
        // обрала, або передумала. Порожній екран із одним посиланням лишає її
        // наодинці з цим рішенням; кілька позицій дають привід лишитись.
        // Запитуємо їх лише коли кошик справді порожній.
        $suggest = [];
        if (!$rows) {
            $suggest = DB::all('SELECT * FROM products WHERE active = 1 AND featured = 1 ORDER BY id LIMIT 4');
            Catalog::preloadBrands($suggest);
        }
        View::show('cart/index', [
            'rows' => $rows,
            'suggest' => $suggest,
            'totals' => Cart::total(),
            'stores' => Catalog::stores(),
            'page_title' => 'Кошик — ' . cfg('app_name'),
        ]);
    }

    public static function add(): never
    {
        Csrf::verify();
        $pid = (int)($_POST['product_id'] ?? 0);
        $vid = (int)($_POST['variant_id'] ?? 0) ?: null;
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $p = DB::row('SELECT id, slug FROM products WHERE id = ? AND active = 1', [$pid]);

        // Коли варіант є з чого обрати — обирає покупець. Мовчки підставити перший
        // означає продати не той розмір, тож відправляємо на картку товару.
        if ($p && !self::variantChosen($pid, $vid)) {
            $to = '/product/' . $p['slug'];
            if (($_POST['ajax'] ?? '') === '1') json_response(['ok' => false, 'redirect' => url($to)]);
            flash('error', 'Оберіть варіант товару.');
            redirect($to);
        }

        $added = $p ? Cart::add($pid, $vid, $qty) : 0;
        $ajax = ($_POST['ajax'] ?? '') === '1';

        // Поклали менше, ніж просили, — це не тиха дрібниця: покупець розраховував
        // на свою кількість і має дізнатися про стелю тут, а не на оформленні.
        if ($added <= 0) {
            // «Немає в наявності» і «все наявне вже у вас у кошику» — різні речі,
            // і друге не привід шукати товар деінде
            $limit = $p ? Cart::limit($pid, $vid) : 0;
            $msg = $limit
                ? 'У кошику вже вся наявна кількість — ' . $limit . ' шт.'
                : 'Цього товару зараз немає в наявності.';
            if ($ajax) json_response(['ok' => false, 'error' => $msg]);
            flash('error', $msg);
            redirect(safe_back($_POST['back'] ?? null, '/cart'));
        }
        $note = $added < $qty
            ? 'Додали ' . $added . ' шт. — це все, що є в наявності.'
            : null;

        if ($ajax) json_response(['ok' => true, 'count' => Cart::count(), 'note' => $note]);
        flash('success', $note ?? 'Додано до кошика');
        redirect(safe_back($_POST['back'] ?? null, '/cart'));
    }

    /** Варіант обовʼязковий лише там, де вибір справді є: один варіант — це не вибір */
    private static function variantChosen(int $productId, ?int $variantId): bool
    {
        $variants = Catalog::variants($productId);
        if (count($variants) < 2) return true;
        foreach ($variants as $v) if ((int)$v['id'] === $variantId) return true;
        return false;
    }

    public static function update(): never
    {
        Csrf::verify();
        $action = $_POST['action'] ?? '';
        $key = $_POST['key'] ?? '';
        if ($action === 'remove') Cart::remove($key);
        elseif ($action === 'inc') Cart::setQty($key, (int)($_POST['qty'] ?? 1) + 1);
        elseif ($action === 'dec') Cart::setQty($key, (int)($_POST['qty'] ?? 1) - 1);
        elseif ($action === 'set') Cart::setQty($key, (int)($_POST['qty'] ?? 1));
        redirect('/cart');
    }
}
