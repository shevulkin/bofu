<div class="admin-head"><h1 class="h-serif">Панель</h1></div>
<div class="stat-grid">
  <div class="stat"><b><?= (int)$stats['orders_new'] ?></b><span>нових замовлень</span></div>
  <div class="stat"><b><?= (int)$stats['orders_total'] ?></b><span>всього замовлень</span></div>
  <div class="stat"><b><?= (int)$stats['products'] ?></b><span>активних товарів</span></div>
  <div class="stat"><b><?= e(number_format($stats['revenue'], 0, ',', ' ')) ?> ₴</b><span>оборот</span></div>
</div>
<div class="admin-card">
  <h2 class="h-serif">Останні замовлення</h2>
  <?php if (!$recent): ?><p class="muted">Поки що порожньо.</p><?php else: ?>
  <table class="tbl">
    <tr><th>Номер</th><th>Клієнт</th><th>Сума</th><th>Статус</th><th>Дата</th><th></th></tr>
    <?php $st = ['new'=>'Нове','processing'=>'Обробляється','shipped'=>'В дорозі','done'=>'Доставлено','canceled'=>'Скасовано']; ?>
    <?php foreach ($recent as $o): ?>
      <tr>
        <td><b><?= e($o['number']) ?></b></td>
        <td><?= e($o['name']) ?></td>
        <td><?= e(price_fmt($o['total'])) ?></td>
        <td><span class="status-pill st-<?= e($o['status']) ?>"><?= e($st[$o['status']] ?? $o['status']) ?></span></td>
        <td class="dim"><?= e(date('d.m.Y H:i', strtotime($o['created_at']))) ?></td>
        <td><a class="btn btn-line btn-xs" href="<?= e(url('/admin/orders/' . $o['id'])) ?>">Відкрити</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
