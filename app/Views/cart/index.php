<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <div class="kicker">Кошик</div>
    <h2>Ваше замовлення</h2>
    <?php if (!$rows): ?>
      <p class="muted" style="padding:36px 0">Кошик порожній. <a href="<?= e(url('/shop')) ?>">Перейти до магазину →</a></p>
    <?php else: ?>
      <table class="cart-table" style="margin-top:30px">
        <tr><th>Товар</th><th></th><th>Ціна</th><th>К-сть</th><th>Сума</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="width:76px"><img class="cart-thumb" src="<?= e(asset($r['photo'])) ?>" alt=""></td>
            <td>
              <b><?= e($r['product']['name']) ?></b>
              <?php if ($r['variant']): ?><div class="dim"><?= e($r['variant']['name']) ?></div><?php endif; ?>
              <?php
                // наявність саме цієї позиції (варіанта) по магазинах
                $where = [];
                foreach ($stores as $s) {
                    $q = (int)($r['stock'][(int)$s['id']] ?? 0);
                    if ($q >= $r['qty']) $where[] = $s['city'] ?: $s['name'];
                }
              ?>
              <?php if ($where): ?>
                <div class="dim">Є в наявності: <?= e(implode(', ', $where)) ?></div>
              <?php elseif ($r['product']['made_to_order']): ?>
                <div class="dim">Немає в магазинах — виготовимо під замовлення</div>
              <?php else: ?>
                <div class="dim" style="color:var(--danger2)">Немає в наявності</div>
              <?php endif; ?>
            </td>
            <td><?= e(price_fmt($r['price'])) ?></td>
            <td>
              <form method="post" action="<?= e(url('/cart/update')) ?>" style="display:inline-flex;gap:6px;align-items:center">
                <?= Csrf::field() ?>
                <input type="hidden" name="key" value="<?= e($r['key']) ?>">
                <input type="hidden" name="qty" value="<?= (int)$r['qty'] ?>">
                <button class="btn btn-line btn-xs" name="action" value="dec">−</button>
                <b><?= (int)$r['qty'] ?></b>
                <button class="btn btn-line btn-xs" name="action" value="inc">+</button>
              </form>
            </td>
            <td><b><?= e(price_fmt($r['sum'])) ?></b></td>
            <td>
              <form method="post" action="<?= e(url('/cart/update')) ?>"><?= Csrf::field() ?>
                <input type="hidden" name="key" value="<?= e($r['key']) ?>">
                <button class="btn btn-danger btn-xs" name="action" value="remove" title="Прибрати">✕</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <div class="totals">
        <div class="row grand"><span>Разом:</span><span><?= e(price_fmt($totals['total'])) ?></span></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:14px;margin-top:26px">
        <a class="btn btn-line" href="<?= e(url('/shop')) ?>">Продовжити покупки</a>
        <a class="btn btn-gold" href="<?= e(url('/checkout')) ?>">Оформити замовлення</a>
      </div>
    <?php endif; ?>
  </div>
</section>
