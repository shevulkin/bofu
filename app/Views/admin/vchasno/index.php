<div class="admin-head"><h1 class="h-serif">Вчасно.Каса</h1></div>

<p class="card-lead" style="margin:-14px 0 22px">
  Стан ПРРО, зміна та звіти. Самі чеки живуть у картках замовлень — тут лише те,
  що стосується каси цілком. Зміну <b>не обовʼязково відкривати руками</b>:
  якщо вона закрита, каса відкриє її сама разом із першим чеком. А от закривати
  її треба — Z-звіт хоча б раз на добу вимагає закон.
</p>

<?php if (!$cases): ?>
  <div class="admin-card dim">
    Жодної каси не налаштовано. Токен беруть у кабінеті
    <a href="https://kasa.vchasno.ua/" target="_blank" rel="noopener">kasa.vchasno.ua</a>
    (Дії з касою → Налаштування каси → Токен) і вписують у
    <a href="<?= e(url('/admin/settings')) ?>">Налаштуваннях</a>,
    а якщо в кожної точки своя каса — у <a href="<?= e(url('/admin/stores')) ?>">картках магазинів</a>.
  </div>
<?php else: ?>

<?php if (count($cases) > 1): ?>
  <div class="admin-card" style="margin-bottom:18px">
    <div class="dim" style="font-size:13px;margin-bottom:8px">Каса</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($cases as $c): ?>
        <a class="btn <?= $c['id'] === $case['id'] ? 'btn-gold' : 'btn-line' ?> btn-sm"
           href="<?= e(url('/admin/vchasno?case=' . rawurlencode($c['id']))) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h2 class="h-serif"><?= e($case['name']) ?></h2>
  <?php if (!$status || !$status['ok']): ?>
    <p class="dim" style="margin:8px 0 0">
      Каса не відповіла: <?= e($status['error'] ?? 'немає токена') ?>.
      Перевірити токен можна в <a href="<?= e(url('/admin/settings')) ?>">Налаштуваннях</a> кнопкою «Перевірити інтеграції».
    </p>
  <?php else: ?>
    <?php
      $shift = (int)($info['shift_status'] ?? -1);
      $open = $shift === Vchasno::SHIFT_OPEN;
      $test = isset($info['isFis']) && (int)$info['isFis'] === 0;
    ?>
    <div style="display:flex;gap:24px;flex-wrap:wrap;margin-top:6px">
      <div>
        <div class="dim" style="font-size:12.5px">Фіскальний номер каси</div>
        <b><?= e((string)($info['fisid'] ?? '—')) ?></b>
      </div>
      <div>
        <div class="dim" style="font-size:12.5px">ЄДРПОУ</div>
        <b><?= e((string)($info['edrpou'] ?? '—')) ?></b>
      </div>
      <div>
        <div class="dim" style="font-size:12.5px">Зміна</div>
        <b><?= $open ? 'відкрита' : ($shift === Vchasno::SHIFT_CLOSED ? 'закрита' : 'ще не відкривалась') ?></b>
        <?php if ($open && ($info['shift_dt'] ?? '') !== ''): ?>
          <span class="dim">з <?= e((string)$info['shift_dt']) ?></span>
        <?php endif; ?>
      </div>
      <div>
        <div class="dim" style="font-size:12.5px">Готівка в скриньці</div>
        <b><?= e(price_fmt((float)($info['safe'] ?? 0))) ?></b>
      </div>
      <div>
        <div class="dim" style="font-size:12.5px">Звʼязок із ДПС</div>
        <b><?= (int)($info['online_status'] ?? 0) === 1 ? 'онлайн' : 'офлайн' ?></b>
      </div>
    </div>

    <?php if ($test): ?>
      <p class="dim" style="margin:14px 0 0;color:var(--warn,#f0b429)">
        Це <b>тестова каса</b>: чеки з неї не мають юридичної сили й у ДПС не потрапляють.
        Так перевіряють інтеграцію — але перед відкриттям магазину підставте токен фіскальної каси.
      </p>
    <?php endif; ?>

    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
      <?= Csrf::field() ?>
      <input type="hidden" name="case" value="<?= e($case['id']) ?>">
      <?php if (!$open): ?>
        <button class="btn btn-line btn-sm" type="submit" name="_action" value="shift_open"
                data-help-title="Відкрити зміну"
                data-help="Потрібне рідко: якщо зміна закрита, каса відкриє її сама разом із першим чеком.

Ця кнопка знадобиться, коли треба зробити службове внесення до першого продажу — внести розмінну готівку на початку дня.">▶ Відкрити зміну</button>
      <?php endif; ?>
      <button class="btn btn-line btn-sm" type="submit" name="_action" value="x_report"
              data-help-title="X-звіт"
              data-help="Проміжний звіт: скільки набігло від початку зміни. Нічого не закриває, робити його можна скільки завгодно разів.

Сам звіт зʼявиться в кабінеті «Вчасно.Каси».">📄 X-звіт</button>
      <?php if ($open): ?>
        <button class="btn btn-line btn-sm" type="submit" name="_action" value="z_report"
                onclick="return confirm('Закрити зміну Z-звітом? Після цього чеки цієї зміни вже не змінити.')"
                data-help-title="Z-звіт — закриття зміни"
                data-help="Підсумковий звіт за зміну. Закон вимагає закривати зміну щонайменше раз на 24 години — інакше каса перестане приймати чеки.

Після Z-звіту зміна закривається, а наступний чек відкриє нову.

Це можна поставити в cron: php bin/cli.php vchasno:z">🔒 Z-звіт (закрити зміну)</button>
      <?php endif; ?>
    </form>

    <details style="margin-top:16px">
      <summary class="dim" style="cursor:pointer;font-size:13px">Службове внесення та видача готівки</summary>
      <p class="dim" style="margin:8px 0;font-size:12.5px">
        Внесення — розмінна готівка на початку дня, видача — інкасація. Це фіскальні
        операції: вони друкуються чеком і змінюють суму готівки в скриньці.
      </p>
      <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
        <?= Csrf::field() ?>
        <input type="hidden" name="case" value="<?= e($case['id']) ?>">
        <div>
          <label class="dim" style="font-size:12px">Сума</label>
          <input type="text" name="sum" inputmode="decimal" style="max-width:140px" placeholder="500">
        </div>
        <div style="flex:1;min-width:200px">
          <label class="dim" style="font-size:12px">Коментар</label>
          <input type="text" name="comment" maxlength="120" placeholder="розмінна монета">
        </div>
        <button class="btn btn-line btn-sm" type="submit" name="_action" value="cash_in">↓ Внести</button>
        <button class="btn btn-line btn-sm" type="submit" name="_action" value="cash_out">↑ Видати</button>
      </form>
    </details>
  <?php endif; ?>
</div>

<?php if ($broken): ?>
  <div class="admin-card" style="margin-top:18px">
    <h2 class="h-serif">Чеки, які не пройшли</h2>
    <p class="dim" style="margin:-6px 0 14px">
      Продаж без чека — це продаж повз ДПС, тож вони зібрані тут, а не лише в картках замовлень.
      «Відповіді ще немає» означає, що каса змовчала: такий чек міг і пробитись, тому повторний
      запит іде з тією самою міткою й другого чека з нього не вийде.
    </p>
    <table class="tbl">
      <tr><th>Замовлення</th><th>Що</th><th class="num">Сума</th><th>Коли</th><th>Причина</th></tr>
      <?php foreach ($broken as $r): ?>
        <tr>
          <td><a href="<?= e(url('/admin/orders/' . (int)$r['parent_id'])) ?>"><?= e((string)($r['number'] ?? '—')) ?></a></td>
          <td><?= $r['type'] === 'return' ? 'повернення' : 'продаж' ?></td>
          <td class="num"><?= e(price_fmt((float)$r['sum'])) ?></td>
          <td class="dim"><?= e(date('d.m H:i', strtotime((string)$r['created_at']))) ?></td>
          <td class="dim"><?= e(Fiscal::statusLabel($r)) ?><?= $r['error'] ? ' — ' . e((string)$r['error']) : '' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($case): ?>
      <form method="post" style="margin-top:12px">
        <?= Csrf::field() ?>
        <input type="hidden" name="case" value="<?= e($case['id']) ?>">
        <button class="btn btn-line btn-sm" type="submit" name="_action" value="retry_all">
          ↻ Перепитати касу про непевні</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($recent): ?>
  <div class="admin-card" style="margin-top:18px">
    <h2 class="h-serif">Останні чеки</h2>
    <table class="tbl">
      <tr><th>Чек</th><th>Замовлення</th><th>Що</th><th class="num">Сума</th><th>Коли</th></tr>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td>
            <?php if ($r['qr']): ?>
              <a href="<?= e((string)$r['qr']) ?>" target="_blank" rel="noopener"><?= e((string)$r['fiscal_number']) ?></a>
            <?php else: ?><?= e((string)$r['fiscal_number']) ?><?php endif; ?>
            <?php if (!empty($r['is_test'])): ?><span class="dim"> · тестова</span><?php endif; ?>
          </td>
          <td><a href="<?= e(url('/admin/orders/' . (int)$r['parent_id'])) ?>"><?= e((string)($r['number'] ?? '—')) ?></a></td>
          <td><?= $r['type'] === 'return' ? 'повернення' : 'продаж' ?></td>
          <td class="num"><?= e(price_fmt((float)$r['sum'])) ?></td>
          <td class="dim"><?= e(date('d.m H:i', strtotime((string)$r['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>

<?php endif; ?>

<div class="admin-card" style="margin-top:18px">
  <h2 class="h-serif">Товари</h2>
  <p class="dim" style="margin:-6px 0 12px">
    Звірити наш каталог із каталогом кабінету — щоб у їхніх звітах наш товар був одним рядком,
    а не двома половинками під різними назвами.
  </p>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/vchasno/goods')) ?>">Звірка товарів →</a>
</div>
