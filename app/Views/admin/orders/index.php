<div class="admin-head"><h1 class="h-serif">Замовлення</h1></div>
<div class="cat-chips">
  <a class="chip <?= $status === 'all' ? 'active' : '' ?>" href="<?= e(url('/admin/orders')) ?>">Усі</a>
  <?php foreach ($statuses as $key => $label): ?>
    <a class="chip <?= $status === $key ? 'active' : '' ?>" href="<?= e(url('/admin/orders?status=' . $key)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$orders): ?><p class="muted">Замовлень немає.</p><?php else: ?>
<table class="tbl">
  <tr><th>Номер</th><th>Клієнт</th><th>Товари</th><th>Доставка</th><th>Сума</th><th>Статус</th><th>Дата</th><th></th></tr>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><b><?= e($o['number']) ?></b><?php if ($o['store_name']): ?><div class="dim"><?= e($o['store_name']) ?></div><?php endif; ?></td>
      <td><?= e($o['name']) ?><div class="dim"><?= e($o['phone']) ?></div></td>
      <td class="muted" style="max-width:240px;white-space:normal">
        <?php $names = array_map(fn($i) => $i['title'] . ' × ' . $i['qty'], $items[$o['id']]); echo e(implode(', ', $names)); ?>
      </td>
      <td class="muted"><?= e(['np' => 'Нова Пошта', 'pickup' => 'Самовивіз', 'other' => 'Інше'][$o['delivery']] ?? $o['delivery']) ?></td>
      <td><b><?= e(price_fmt($o['total'])) ?></b></td>
      <td><span class="status-pill st-<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
      <td class="dim"><?= e(date('d.m.Y H:i', strtotime($o['created_at']))) ?></td>
      <td><a class="btn btn-line btn-xs" href="<?= e(url('/admin/orders/' . $o['id'])) ?>">Відкрити</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
