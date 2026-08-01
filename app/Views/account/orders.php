<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <div class="kicker">Кабінет</div>
    <h2>Мої замовлення</h2>
    <?php if (!$orders): ?>
      <p class="muted" style="padding:36px 0">Замовлень поки немає. <a href="<?= e(url('/shop')) ?>">До магазину →</a></p>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:14px;margin-top:28px">
        <?php foreach ($orders as $o): $parts = $children[$o['id']] ?? []; $split = count($parts) > 1; ?>
          <details class="card" style="padding:0">
            <summary style="display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px 22px;cursor:pointer;flex-wrap:wrap">
              <b style="font-family:var(--serif);font-size:19px"><?= e($o['number']) ?></b>
              <span class="dim"><?= e(date('d.m.Y', strtotime($o['created_at']))) ?></span>
              <span class="chip" style="cursor:default"><?= e(OrderFlow::statusLabel($o['status'])) ?></span>
              <b class="price"><?= e(price_fmt($o['total'])) ?></b>
            </summary>
            <div style="padding:0 22px 20px">
              <?php if ($split): ?>
                <p class="dim" style="margin:0 0 14px">Замовлення виконують <?= count($parts) ?> магазини — кожен відправляє свою частину, тому статуси можуть відрізнятися.</p>
              <?php endif; ?>

              <?php foreach ($parts as $c): ?>
                <div style="<?= $split ? 'border:1px solid var(--bg3);border-radius:8px;padding:12px 14px;margin-bottom:12px' : '' ?>">
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;<?= $split ? 'margin-bottom:8px' : 'margin-bottom:4px' ?>">
                    <span class="dim">Виконує: <b><?= e($c['store_name'] ?: 'Уточнюємо') ?></b><?= $c['store_city'] ? ', ' . e($c['store_city']) : '' ?></span>
                    <?php if ($split): ?>
                      <span class="chip" style="cursor:default;font-size:11.5px"><?= e(OrderFlow::statusLabel($c['status'])) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php foreach ($items[(int)$c['id']] ?? [] as $it): ?>
                    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--bg3);font-size:14px">
                      <span><?= e($it['title']) ?><?= $it['variant_name'] ? ' · ' . e($it['variant_name']) : '' ?> × <?= (int)$it['qty'] ?></span>
                      <span><?= e(price_fmt($it['sum'])) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>

              <p class="dim" style="margin-top:12px">
                Доставка: <?= e(OrderFlow::deliveryLabel($o['delivery'])) ?>
                <?= $o['city'] ? ' · ' . e($o['city']) : '' ?><?= $o['np_office'] ? ' · ' . e($o['np_office']) : '' ?>
              </p>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
