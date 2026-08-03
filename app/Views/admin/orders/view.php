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
          <tr>
            <th data-help-title="Колонка «Товар»"
                data-help="Що саме замовили, з варіантом після крапки — «Мед липовий · 0.5 л».

Нижче сірим — скільки цього товару зараз лежить у кожному магазині мережі. Це поточний залишок, а не той, що був на момент замовлення.

Дивіться на цей рядок перед тим, як передавати позицію: він показує, у кого товар реально є.">Товар</th>
            <th data-help-title="Колонка «Ціна»"
                data-help="Ціна за одиницю, зафіксована в момент покупки.

Вона більше не змінюється ніколи: ні від нових акцій, ні від зміни ціни в картці товару, ні від передачі позиції в інший магазин. Покупець платить рівно те, що бачив у кошику.">Ціна</th>
            <th data-help-title="Колонка «К-сть»"
                data-help="Скільки одиниць замовили.

Якщо на складі менше, ніж тут написано, — замовлення все одно прийняте: сайт дозволяє замовляти понад залишок. Тоді узгодьте строки з покупцем.">К-сть</th>
            <th data-help-title="Колонка «Сума»" data-help="Ціна × кількість для цього рядка, без урахування знижки на все замовлення. Знижка розподіляється нижче, у підсумку частини.">Сума</th>
            <?php if ($mine): ?>
              <th data-help-title="Колонка «Передати»"
                  data-help="Передати позицію іншому магазину — коли товару у вас немає, а в сусідів є.

Оберіть магазин зі списку й натисніть стрілку. Позиція переїде в його частину замовлення (вона створиться, якщо ще не існує), залишок повернеться вам і спишеться в нього.

Ціна для покупця не змінюється — міняється лише те, хто виконує.

Якщо після передачі у вашій частині не лишиться жодної позиції, вона закриється сама.">Передати</th>
            <?php endif; ?></tr>
          <?php foreach ($items[$cid] as $it): ?>
            <tr>
              <td><?= e($it['title']) ?><?= $it['variant_name'] ? ' · ' . e($it['variant_name']) : '' ?>
                <?php
                  // Продано понад залишок: показуємо просто в рядку позиції, бо
                  // саме тут поруч стоїть кнопка «Передати» — тобто відповідь
                  $lack = $it['stock_taken'] !== null ? (int)$it['qty'] - (int)$it['stock_taken'] : 0;
                ?>
                <?php if ($lack > 0): ?>
                  <div style="color:var(--danger2);font-size:12.5px;margin-top:3px"
                       data-help-title="Не вистачає на складі"
                       data-help="Позицію замовили в більшій кількості, ніж було в цьому магазині. Сайт таке дозволяє — вважається, що виробник доробить.

Числа означають: скільки штук вдалося зняти зі складу і скільки замовили.

Два виходи, обидва тут же: передати позицію магазину, де товар є (стовпець «Передати» праворуч — під рядком видно залишки по всіх точках), або довиробити її й далі виконувати самому.

Позначка не зникає сама: вона показує, що було в момент покупки. Після передачі в іншу точку вона перерахується.">
                    ⚠️ Не вистачає: є <?= (int)$it['stock_taken'] ?> з <?= (int)$it['qty'] ?>
                  </div>
                <?php endif; ?>
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
            <?php if ((float)$c['discount'] > 0): ?>
              (знижка −<?= e(price_fmt($c['discount'])) ?><?= $c['promo_code'] ? ' за кодом ' . e($c['promo_code']) : '' ?>)
            <?php endif; ?>
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
                <button class="btn btn-line btn-xs" type="submit"
                        data-help-title="Взяти в роботу"
                        data-help="Позначає, що цією частиною займаєтесь саме ви. Ваше імʼя зʼявляється тут і в загальному списку замовлень.

Навіщо: щоб двоє не дзвонили тому самому покупцю й не пакували те саме замовлення двічі.

«Звільнити» — відпустити її назад, якщо передумали чи йдете зі зміни.

«Перебрати на себе» — забрати в колеги, коли він не встигає. Його імʼя заміниться вашим, попереднє лишиться в історії.

Це лише позначка: доступу до замовлення вона не змінює, статусів не чіпає й покупцю не показується."><?= $who
                  ? ($who['is_me'] ? 'Звільнити' : 'Перебрати на себе')
                  : 'Взяти в роботу' ?></button>
              </form>
            <?php endif; ?>
            <?php if ($mine): ?>
              <form method="post" action="<?= e(url('/admin/orders/' . $order['id'])) ?>" style="display:flex;gap:8px;align-items:center">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_id" value="<?= $cid ?>">
                <select name="status" data-help-title="Статус вашої частини"
                        data-help="Стан саме цієї частини замовлення — тієї, яку виконує ваш магазин.

Порядок роботи: Нове → Обробляється (взяли, пакуєте) → В дорозі (віддали перевізнику) → Доставлено.

«Скасовано» — коли замовлення не відбудеться. Скасовану частину не можна повернути в роботу, тож не тисніть навмання.

Обравши значення, натисніть «Оновити» — до цього нічого не збережеться.">
                  <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $c['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-gold btn-sm" type="submit"
                        data-help-title="Кнопка «Оновити»"
                        data-help="Зберігає обраний статус вашої частини.

Одразу після цього покупець отримує сповіщення про зміну — тож ставте «В дорозі» тоді, коли посилка справді поїхала, а не наперед.

Статус усього замовлення перерахується сам: він дорівнює найменш просунутій частині. Поки хоч один магазин не закрив свою, «Доставлено» покупець не побачить.">Оновити</button>
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
          <?php
            // Відсоток рахуємо з самого замовлення, а не з довідника кодів: код
            // могли відтоді змінити або видалити, а в замовленні має лишатись те,
            // на чому зійшлися з покупцем.
            $pct = (float)$parent['subtotal'] > 0
                 ? round((float)$parent['discount'] / (float)$parent['subtotal'] * 100, 1) : 0;
          ?>
          <div class="row">
            <span class="muted"><?= $parent['promo_code']
              ? 'Промокод ' . e($parent['promo_code']) . ($pct > 0 ? ' (−' . e(rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',')) . '%)' : '')
              : 'Знижка' ?>:</span>
            <span>−<?= e(price_fmt($parent['discount'])) ?></span>
          </div>
        <?php elseif ($parent['promo_code']): ?>
          <div class="row"><span class="muted">Промокод <?= e($parent['promo_code']) ?>:</span>
            <span class="dim">не дав знижки</span></div>
        <?php endif; ?>
        <div class="row grand"><span>Разом за замовленням:</span><span><?= e(price_fmt($parent['total'])) ?></span></div>
      </div>
      <p class="dim" style="margin-top:10px">Передача позиції між магазинами не змінює ціну для покупця — лише те, хто її виконує.</p>
    </div>
  </div>

  <div>
    <div class="admin-card">
      <h2 class="h-serif" data-help-title="Клієнт і доставка"
          data-help="Усе, що потрібно, щоб зв'язатися з покупцем і відправити замовлення.

Телефон та email клікабельні: натисніть — і відкриється дзвінок або лист.

Нижче — спосіб доставки й адреса. «Самовивіз» означає, що покупець забере сам, тож адреси там немає.

Коментар покупця показується окремим рядком унизу. Читайте його завжди: саме там пишуть «зателефонуйте після 18:00» чи «домофон не працює».

Ці дані однакові для всього замовлення й не змінюються від передачі позицій між магазинами.">
        Клієнт і доставка</h2>
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
          <button class="btn btn-line btn-sm" type="submit"
                  data-help-title="Проставити всім частинам"
                  data-help="Ставить обраний статус одразу всім магазинам, що виконують це замовлення.

Головне застосування — скасувати замовлення цілком одним рухом, не заходячи в кожну частину окремо.

Обережно: покупець отримає сповіщення по кожній частині.

Уже скасовані частини не воскресають — якщо магазин скасував свою, вона такою й лишиться.

Для звичайної роботи користуйтеся статусом своєї частини: спільний статус порахується сам.">Проставити всім частинам</button>
        </form>
      <?php endif; ?>
      <p class="dim" style="margin-top:12px">Статус замовлення рахується сам: він дорівнює найменш просунутій частині, тож «Доставлено» зʼявиться, коли всі магазини закриють свої.</p>
    </div>

    <?php if ($notes || $can_note): ?>
      <div class="admin-card">
        <h2 class="h-serif" data-help-title="Нотатки"
            data-help="Внутрішні записи для персоналу. Покупець їх НЕ бачить — ні в кабінеті, ні в листі, ні в боті.

Сюди пишуть те, що має знати наступна зміна: «клієнт просив зателефонувати після 18:00», «домовились відправити в понеділок», «просив не класти чек у посилку».

Кожен запис підписується імʼям і роллю автора та часом — видно, хто й коли це написав.

Нотатку не можна відредагувати чи видалити, тож формулюйте одразу як слід.">
          Нотатки</h2>
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
        <h2 class="h-serif" data-help-title="Історія"
            data-help="Автоматичний журнал усього, що сталося з замовленням: створення, зміни статусів, передачі позицій між магазинами, нотатки.

Кожен запис підписаний імʼям і роллю того, хто діяв, та часом. Записи без імені зробила система сама — наприклад, перерахунок спільного статусу.

Найновіше — зверху. Історію не можна змінити чи видалити: саме тому вона й корисна, коли треба зʼясувати, хто що зробив.">
          Історія</h2>
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
