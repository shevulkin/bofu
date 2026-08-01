<div class="admin-head">
  <h1 class="h-serif">Замовлення <?= e($order['number']) ?></h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/orders')) ?>">← До списку</a>
</div>
<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:22px" data-rg="1">
  <div class="admin-card">
    <h2 class="h-serif">Склад замовлення</h2>
    <table class="tbl">
      <tr><th>Товар</th><th>Ціна</th><th>К-сть</th><th>Сума</th></tr>
      <?php foreach ($order_items as $it): ?>
        <tr>
          <td><?= e($it['title']) ?><?= $it['variant_name'] ? ' · ' . e($it['variant_name']) : '' ?>
            <?php $st = $item_stock[(int)$it['id']] ?? null; if ($st !== null): ?>
              <div class="dim">Зараз на складі:
                <?php $bits = [];
                  foreach ($stores as $s) $bits[] = e($s['city'] ?: $s['name']) . ' — ' . (int)($st[(int)$s['id']] ?? 0);
                  echo implode(' · ', $bits); ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= e(price_fmt($it['price'])) ?></td>
          <td><?= (int)$it['qty'] ?></td>
          <td><b><?= e(price_fmt($it['sum'])) ?></b></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <div class="totals" style="margin-top:18px">
      <div class="row"><span class="muted">Товари:</span><span><?= e(price_fmt($order['subtotal'])) ?></span></div>
      <?php if ((float)$order['discount'] > 0): ?>
        <div class="row"><span class="muted">Знижка<?= $order['promo_code'] ? ' (' . e($order['promo_code']) . ')' : '' ?>:</span><span>−<?= e(price_fmt($order['discount'])) ?></span></div>
      <?php endif; ?>
      <div class="row grand"><span>Разом:</span><span><?= e(price_fmt($order['total'])) ?></span></div>
    </div>
  </div>
  <div>
    <div class="admin-card">
      <h2 class="h-serif">Клієнт і доставка</h2>
      <p><b><?= e($order['name']) ?></b><br>
      <a href="tel:<?= e($order['phone']) ?>"><?= e($order['phone']) ?></a>
      <?php if ($order['email']): ?><br><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a><?php endif; ?></p>
      <p class="muted" style="margin-top:12px">
        <?= e(['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'][$order['delivery']] ?? $order['delivery']) ?>
        <?= $order['city'] ? '<br>Місто: ' . e($order['city']) : '' ?>
        <?= $order['np_office'] ? '<br>Відділення: ' . e($order['np_office']) : '' ?>
        <?= $order['address'] ? '<br>Адреса: ' . e($order['address']) : '' ?>
        <?php if ($store): ?><br>Магазин: <?= e($store['name'] . ($store['city'] ? ', ' . $store['city'] : '')) ?><?php endif; ?>
      </p>
      <?php if ($order['comment']): ?><p style="margin-top:12px" class="dim">Коментар: <?= e($order['comment']) ?></p><?php endif; ?>
    </div>
    <div class="admin-card">
      <h2 class="h-serif">Статус</h2>
      <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <select name="status">
            <?php foreach ($statuses as $key => $label): ?>
              <option value="<?= $key ?>" <?= $order['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-gold btn-sm" type="submit">Оновити статус</button>
      </form>
      <p class="dim" style="margin-top:12px">Зміна статусу надішле сповіщення згідно з налаштуваннями.</p>
    </div>
  </div>
</div>
