<section class="section" style="text-align:center;padding-top:96px">
  <div class="container narrow">
    <div style="font-size:56px;margin-bottom:18px">🍯</div>
    <h2>Замовлення прийнято!</h2>
    <p class="lead" style="margin:18px auto 8px">Номер вашого замовлення:</p>
    <p style="font-family:var(--serif);font-size:34px;color:var(--gold);font-weight:700"><?= e($order['number']) ?></p>
    <p class="muted" style="margin:16px auto 24px">Ми зв'яжемося з вами найближчим часом для підтвердження.<br>Сума до сплати: <b><?= e(price_fmt($order['total'])) ?></b></p>
    <?php if (count($children) > 1): ?>
      <div class="card" style="max-width:520px;margin:0 auto 30px;padding:18px 22px;text-align:left">
        <p class="dim" style="margin:0 0 12px">Товари є в різних магазинах, тож замовлення виконають <?= count($children) ?> продавці — кожен відправить свою частину. Стежити за ними можна в кабінеті одним замовленням.</p>
        <?php foreach ($children as $c): ?>
          <div style="padding:8px 0;border-top:1px solid var(--bg3)">
            <b><?= e($c['store_name'] ?: 'Уточнюємо магазин') ?></b>
            <span class="dim"> — <?= e(price_fmt($c['total'])) ?></span>
            <?php foreach ($items[(int)$c['id']] ?? [] as $it): ?>
              <div class="dim" style="font-size:13px"><?= e($it['title']) ?><?= $it['variant_name'] ? ' · ' . e($it['variant_name']) : '' ?> × <?= (int)$it['qty'] ?></div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-gold" href="<?= e(url('/shop')) ?>">Продовжити покупки</a>
      <a class="btn btn-line" href="<?= e(url('/')) ?>">На головну</a>
    </div>
  </div>
</section>
