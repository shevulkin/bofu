<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <div class="kicker">Кабінет</div>
    <h2>Мої замовлення</h2>
    <?php if (!$orders): ?>
      <p class="muted" style="padding:36px 0">Замовлень поки немає. <a href="<?= e(url('/shop')) ?>">До магазину →</a></p>
    <?php else: ?>
      <?php $labels = ['new' => 'Нове', 'processing' => 'Обробляється', 'shipped' => 'В дорозі', 'done' => 'Доставлено', 'canceled' => 'Скасовано']; ?>
      <div style="display:flex;flex-direction:column;gap:14px;margin-top:28px">
        <?php foreach ($orders as $o): ?>
          <details class="card" style="padding:0">
            <summary style="display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px 22px;cursor:pointer;flex-wrap:wrap">
              <b style="font-family:var(--serif);font-size:19px"><?= e($o['number']) ?></b>
              <span class="dim"><?= e(date('d.m.Y', strtotime($o['created_at']))) ?></span>
              <span class="chip" style="cursor:default"><?= e($labels[$o['status']] ?? $o['status']) ?></span>
              <b class="price"><?= e(price_fmt($o['total'])) ?></b>
            </summary>
            <div style="padding:0 22px 20px">
              <?php foreach ($items[$o['id']] as $it): ?>
                <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--bg3);font-size:14px">
                  <span><?= e($it['title']) ?><?= $it['variant_name'] ? ' · ' . e($it['variant_name']) : '' ?> × <?= (int)$it['qty'] ?></span>
                  <span><?= e(price_fmt($it['sum'])) ?></span>
                </div>
              <?php endforeach; ?>
              <p class="dim" style="margin-top:12px">
                Доставка: <?= e(['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'][$o['delivery']] ?? $o['delivery']) ?>
                <?= $o['city'] ? ' · ' . e($o['city']) : '' ?><?= $o['np_office'] ? ' · ' . e($o['np_office']) : '' ?>
              </p>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
