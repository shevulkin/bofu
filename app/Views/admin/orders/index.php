<?php $q = fn(array $over = []) => url('/admin/orders?' . http_build_query(array_merge(
      ['status' => $status], $sees_all ? ['scope' => $scope] : [], $over))); ?>
<div class="admin-head"><h1 class="h-serif"><?= $is_seller_view ? ($scope === 'all' ? 'Замовлення мережі' : 'Мої замовлення') : 'Замовлення' ?></h1></div>
<?php if ($is_seller_view): ?>
  <p class="dim" style="margin:-8px 0 14px">
    <?= $scope === 'all'
      ? 'Замовлення всіх точок. Чужі частини — лише для перегляду; щоб узятись за таку, передайте позицію собі.'
      : 'Тут — лише ваші частини замовлень. Відкрийте будь-яку, щоб побачити замовлення цілком.' ?>
  </p>
<?php endif; ?>
<?php if ($sees_all): ?>
  <div class="cat-chips" style="margin-bottom:8px">
    <a class="chip <?= $scope === 'mine' ? 'active' : '' ?>" href="<?= e($q(['scope' => 'mine'])) ?>">Мої точки</a>
    <a class="chip <?= $scope === 'all' ? 'active' : '' ?>" href="<?= e($q(['scope' => 'all'])) ?>">Уся мережа</a>
  </div>
<?php endif; ?>
<div class="cat-chips">
  <a class="chip <?= $status === 'all' ? 'active' : '' ?>" href="<?= e($q(['status' => 'all'])) ?>">Усі</a>
  <?php foreach ($statuses as $key => $label): ?>
    <a class="chip <?= $status === $key ? 'active' : '' ?>" href="<?= e($q(['status' => $key])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$orders): ?><p class="muted">Замовлень немає.</p><?php else: ?>
<table class="tbl">
  <tr><th>Номер</th><th>Клієнт</th><th>Товари</th><th><?= $is_seller_view ? 'Замовлення' : 'Магазини' ?></th>
      <th class="num">Сума</th><th>Статус</th><th>Дата</th><th></th></tr>
  <?php foreach ($orders as $o): $id = (int)$o['id'];
        $foreign = $is_seller_view && $o['store_id'] && !in_array((int)$o['store_id'], $my_store_ids, true); ?>
    <tr<?= $foreign ? ' class="row-foreign"' : '' ?>>
      <td><b><?= e($o['number']) ?></b>
        <?php if ($o['store_name']): ?><div class="dim"><?= e($o['store_name']) ?><?= $foreign ? ' · чужа точка' : '' ?></div><?php endif; ?>
        <?php if (!empty($o['assigned_name'])): ?>
          <div class="dim" style="white-space:nowrap">у роботі: <?= e($o['assigned_name']) ?></div>
        <?php endif; ?></td>
      <td><?= e($o['name']) ?><div class="dim"><?= e($o['phone']) ?></div></td>
      <td class="muted" style="max-width:240px;white-space:normal">
        <?php $names = array_map(fn($i) => $i['title'] . ' × ' . $i['qty'], $items[$id] ?? []); echo e(implode(', ', $names)); ?>
      </td>
      <td style="max-width:230px;white-space:normal">
        <?php if ($o['parent_id']): ?>
          <a href="<?= e(url('/admin/orders/' . $o['parent_id'])) ?>"><?= e($o['parent_number']) ?></a>
        <?php elseif (count($children[$id] ?? []) <= 1): ?>
          <span class="dim"><?= e($children[$id][0]['store_name'] ?? '—') ?></span>
        <?php else: ?>
          <?php foreach ($children[$id] as $c): ?>
            <div class="dim" style="white-space:nowrap"><?= e($c['store_name'] ?: 'Не призначено') ?>
              <span class="status-pill st-<?= e($c['status']) ?>"><?= e($statuses[$c['status']] ?? $c['status']) ?></span></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </td>
      <td class="num"><b><?= e(price_fmt($o['total'])) ?></b></td>
      <td><span class="status-pill st-<?= e($o['status']) ?>"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td>
      <?php
        // «Сьогодні 09:40» відповідає на питання, яке продавець ставить насправді
        // («це свіже чи вчорашнє?»), а 03.08.2026 змушує це вираховувати
        $ts = strtotime($o['created_at']);
        $day = date('Y-m-d', $ts);
        $when = $day === date('Y-m-d') ? 'сьогодні ' . date('H:i', $ts)
              : ($day === date('Y-m-d', strtotime('-1 day')) ? 'вчора ' . date('H:i', $ts)
              : date('d.m.Y H:i', $ts));
      ?>
      <td class="dim" style="white-space:nowrap"<?= $day === date('Y-m-d') ? ' title="Сьогоднішнє замовлення"' : '' ?>><?= e($when) ?></td>
      <td><a class="btn btn-line btn-xs" href="<?= e(url('/admin/orders/' . $id)) ?>">Відкрити</a></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
