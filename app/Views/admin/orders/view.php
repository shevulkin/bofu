<?php $total_parts = count($children); ?>
<div class="admin-head">
  <div>
    <h1 class="h-serif" style="margin:0">Замовлення <?= e($parent['number']) ?></h1>
    <?php if ($focus): ?>
      <?php $focus_store = ''; foreach ($children as $c) if ((int)$c['id'] === $focus) $focus_store = $c['store_name'] ?: 'Без магазину'; ?>
      <div class="dim" style="margin-top:4px">Ваша частина: <?= e($order['number']) ?> — <?= e($focus_store) ?><?= $total_parts > 1 ? ' (усього частин: ' . (int)$total_parts . ')' : '' ?></div>
    <?php elseif ($total_parts > 1): ?>
      <div class="dim" style="margin-top:4px">Розділено між магазинами: <?= (int)$total_parts ?></div>
    <?php endif; ?>
  </div>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/orders')) ?>">← До списку</a>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:22px" data-rg="1">
  <div>
    <?php foreach ($children as $c): $cid = (int)$c['id']; $mine = !empty($can_manage[$cid]); ?>
      <div class="admin-card" style="<?= $focus === $cid ? 'border-color:var(--gold)' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <h2 class="h-serif" style="margin:0">
            <?= e($c['store_name'] ?: 'Магазин не призначено') ?>
            <?php if ($c['store_city']): ?><span class="dim" style="font-size:14px"><?= e($c['store_city']) ?></span><?php endif; ?>
          </h2>
          <div style="display:flex;align-items:center;gap:10px">
            <span class="dim"><?= e($c['number']) ?><?= $total_parts > 1 ? ' · частина ' . (int)$c['seq'] : '' ?></span>
            <span class="status-pill st-<?= e($c['status']) ?>"><?= e($statuses[$c['status']] ?? $c['status']) ?></span>
          </div>
        </div>

        <table class="tbl" style="margin-top:14px">
          <tr><th>Товар</th><th>Ціна</th><th>К-сть</th><th>Сума</th><?php if ($mine): ?><th>Передати</th><?php endif; ?></tr>
          <?php foreach ($items[$cid] as $it): ?>
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
              <?php if ($mine): ?>
                <td>
                  <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>" style="display:flex;gap:6px;align-items:center">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="transfer">
                    <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                    <select name="to_store_id" style="min-width:130px">
                      <?php foreach ($stores as $s): if ((int)$s['id'] === (int)$c['store_id']) continue; ?>
                        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-line btn-xs" type="submit">→</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </table>

        <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-top:14px;flex-wrap:wrap">
          <div class="dim">Частина магазину: <b><?= e(price_fmt($c['total'])) ?></b>
            <?php if ((float)$c['discount'] > 0): ?> (знижка −<?= e(price_fmt($c['discount'])) ?>)<?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php $who = $assignees[$cid] ?? null; ?>
            <?php if ($who): ?>
              <span class="dim" style="white-space:nowrap">у роботі: <b><?= e($who['name']) ?></b><?php
                if ($who['at']): ?> · <?= e(date('d.m H:i', strtotime($who['at']))) ?><?php endif; ?></span>
            <?php endif; ?>
            <?php if ($mine && $can_assign): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>" style="display:inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="<?= $who && $who['is_me'] ? 'release' : 'claim' ?>">
                <input type="hidden" name="order_id" value="<?= $cid ?>">
                <button class="btn btn-line btn-xs" type="submit"><?= $who
                  ? ($who['is_me'] ? 'Звільнити' : 'Перебрати на себе')
                  : 'Взяти в роботу' ?></button>
              </form>
            <?php endif; ?>
            <?php if ($mine): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>" style="display:flex;gap:8px;align-items:center">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_id" value="<?= $cid ?>">
                <select name="status">
                  <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $c['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-gold btn-sm" type="submit">Оновити</button>
              </form>
            <?php else: ?>
              <span class="dim">Цю частину веде інший магазин</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="admin-card">
      <div class="totals">
        <div class="row"><span class="muted">Товари:</span><span><?= e(price_fmt($parent['subtotal'])) ?></span></div>
        <?php if ((float)$parent['discount'] > 0): ?>
          <div class="row"><span class="muted">Знижка<?= $parent['promo_code'] ? ' (' . e($parent['promo_code']) . ')' : '' ?>:</span><span>−<?= e(price_fmt($parent['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="row grand"><span>Разом за замовленням:</span><span><?= e(price_fmt($parent['total'])) ?></span></div>
      </div>
      <p class="dim" style="margin-top:10px">Передача позиції між магазинами не змінює ціну для покупця — лише те, хто її виконує.</p>
    </div>
  </div>

  <div>
    <div class="admin-card">
      <h2 class="h-serif">Клієнт і доставка</h2>
      <p><b><?= e($parent['name']) ?></b><br>
      <a href="tel:<?= e($parent['phone']) ?>"><?= e($parent['phone']) ?></a>
      <?php if ($parent['email']): ?><br><a href="mailto:<?= e($parent['email']) ?>"><?= e($parent['email']) ?></a><?php endif; ?></p>
      <p class="muted" style="margin-top:12px">
        <?= e(OrderFlow::deliveryLabel($parent['delivery'])) ?>
        <?= $parent['city'] ? '<br>Місто: ' . e($parent['city']) : '' ?>
        <?= $parent['np_office'] ? '<br>Відділення: ' . e($parent['np_office']) : '' ?>
        <?= $parent['address'] ? '<br>Адреса: ' . e($parent['address']) : '' ?>
      </p>
      <?php if ($parent['comment']): ?><p style="margin-top:12px" class="dim">Коментар: <?= e($parent['comment']) ?></p><?php endif; ?>
    </div>

    <div class="admin-card">
      <h2 class="h-serif">Статус замовлення</h2>
      <p style="margin-bottom:10px"><span class="status-pill st-<?= e($parent['status']) ?>"><?= e($statuses[$parent['status']] ?? $parent['status']) ?></span></p>
      <?php if ($can_manage_parent): ?>
        <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="order_id" value="<?= (int)$parent['id'] ?>">
          <div class="field">
            <select name="status">
              <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= $key ?>" <?= $parent['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-line btn-sm" type="submit">Проставити всім частинам</button>
        </form>
      <?php endif; ?>
      <p class="dim" style="margin-top:12px">Статус замовлення рахується сам: він дорівнює найменш просунутій частині, тож «Доставлено» зʼявиться, коли всі магазини закриють свої.</p>
    </div>

    <?php if ($notes || $can_note): ?>
      <div class="admin-card">
        <h2 class="h-serif">Нотатки</h2>
        <p class="dim" style="margin-bottom:10px">Внутрішні записи для персоналу — покупець їх не бачить.</p>
        <?php foreach ($notes as $n): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--bg3);font-size:13.5px">
            <div style="white-space:pre-wrap"><?= e($n['message']) ?></div>
            <div class="dim"><?= e(date('d.m.Y H:i', strtotime($n['created_at']))) ?><?= $n['user_name'] ? ' · ' . e($n['user_name']) : '' ?><?php
              if (!empty($n['role'])): ?> · <?= e(Roles::label($n['role'])) ?><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$notes): ?><p class="muted" style="margin-bottom:10px">Поки порожньо.</p><?php endif; ?>
        <?php if ($can_note): ?>
          <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>" style="margin-top:12px">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="note">
            <div class="field">
              <textarea name="note" rows="2" maxlength="2000" placeholder="Наприклад: клієнт просив зателефонувати після 18:00"></textarea>
            </div>
            <button class="btn btn-line btn-sm" type="submit">Додати нотатку</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($events): ?>
      <div class="admin-card">
        <h2 class="h-serif">Історія</h2>
        <?php foreach ($events as $ev): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--bg3);font-size:13.5px">
            <?= e($ev['message']) ?>
            <div class="dim"><?= e(date('d.m.Y H:i', strtotime($ev['created_at']))) ?><?= $ev['user_name'] ? ' · ' . e($ev['user_name']) : '' ?><?php
              /* роль показуємо поруч з іменем: у старих записів її немає */
              if (!empty($ev['role'])): ?> · <?= e(Roles::label($ev['role'])) ?><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
