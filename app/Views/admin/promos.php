<div class="admin-head"><h1 class="h-serif">Акції та промокоди</h1></div>

<div class="admin-card">
  <h2 class="h-serif">Акційний банер сайту</h2>
  <form method="post" action="<?= e(url('/admin/promos')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="sale_banner">
    <label class="toggle"><input type="checkbox" name="sale_active" <?= Settings::bool('sale_banner_active') ? 'checked' : '' ?>><span class="tr"></span> Показувати банер</label>
    <div style="flex:1;min-width:220px"><label>Текст</label><input type="text" name="sale_text" value="<?= e(Settings::get('sale_banner_text', '')) ?>"></div>
    <div style="width:110px"><label>Знижка, %</label><input type="number" name="sale_percent" value="<?= e(Settings::get('sale_banner_percent', '')) ?>"></div>
    <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
  </form>
</div>

<div class="admin-card">
  <h2 class="h-serif">Акції (знижки на товари)</h2>
  <form method="post" action="<?= e(url('/admin/promos')) ?>" style="display:grid;grid-template-columns:2fr 90px 1fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:end" data-rg="1">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="add_promo">
    <div><label>Назва акції</label><input type="text" name="title" required placeholder="Напр. Медовий тиждень"></div>
    <div><label>%</label><input type="number" step="0.5" name="percent" required></div>
    <div><label>Магазин</label><select name="store_id"><option value="">Всі</option><?php foreach ($stores as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['city'] ?: $s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Категорія</label><select name="category_id"><option value="">Всі</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Товар</label><select name="product_id"><option value="">Всі</option><?php foreach ($products as $pp): ?><option value="<?= (int)$pp['id'] ?>"><?= e(mb_substr($pp['name'], 0, 30)) ?></option><?php endforeach; ?></select></div>
    <div><label>З дати</label><input type="date" name="starts_at"></div>
    <div><label>По дату</label><input type="date" name="ends_at"></div>
    <button class="btn btn-gold btn-sm" type="submit">+ Створити</button>
  </form>
  <?php if ($promos): ?>
  <table class="tbl" style="margin-top:18px">
    <tr><th>Акція</th><th>%</th><th>Область дії</th><th>Період</th><th>Стан</th><th></th></tr>
    <?php foreach ($promos as $pr): ?>
      <tr>
        <td><b><?= e($pr['title']) ?></b></td>
        <td>−<?= e($pr['percent']) ?>%</td>
        <td class="muted">
          <?= $pr['store_name'] ? 'Магазин: ' . e($pr['store_name']) : 'Всі магазини' ?>
          <?= $pr['cat_name'] ? ' · ' . e($pr['cat_name']) : '' ?>
          <?= $pr['product_name'] ? ' · ' . e($pr['product_name']) : '' ?>
        </td>
        <td class="dim"><?= e($pr['starts_at'] ?: '…') ?> — <?= e($pr['ends_at'] ?: '…') ?></td>
        <td><?= $pr['active'] ? '<span class="status-pill st-processing">Активна</span>' : '<span class="status-pill st-canceled">Вимкнена</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" action="<?= e(url('/admin/promos')) ?>" style="display:inline"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="toggle_promo"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
            <button class="btn btn-line btn-xs"><?= $pr['active'] ? 'Вимкнути' : 'Увімкнути' ?></button>
          </form>
          <form method="post" action="<?= e(url('/admin/promos')) ?>" style="display:inline"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="del_promo"><input type="hidden" name="id" value="<?= (int)$pr['id'] ?>">
            <button class="btn btn-danger btn-xs" onclick="return confirm('Видалити акцію?')">✕</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2 class="h-serif">Промокоди</h2>
  <form method="post" action="<?= e(url('/admin/promos')) ?>" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="add_code">
    <div><label>Код</label><input type="text" name="code" required placeholder="MED10" style="text-transform:uppercase"></div>
    <div style="width:110px"><label>Знижка, %</label><input type="number" step="0.5" name="percent" required></div>
    <div><label>Діє до</label><input type="date" name="expires_at"></div>
    <div style="width:150px"><label>Всього використань</label>
      <input type="number" min="1" name="max_uses" placeholder="без обмежень"></div>
    <div style="width:150px"><label>На одну людину</label>
      <input type="number" min="1" name="per_user_limit" placeholder="без обмежень"></div>
    <div style="width:170px"><label>Стеля знижки, %</label>
      <input type="number" step="0.5" min="0" name="max_total_percent" placeholder="без стелі"></div>
    <label class="toggle" style="margin-bottom:10px">
      <input type="checkbox" name="stackable" checked><span class="tr"></span> Сумується з акціями</label>
    <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
  </form>
  <p class="dim" style="margin:12px 0 0;font-size:13px">
    Порожнє поле — без обмежень. <b>Всього 1</b> — код для однієї людини: хто перший скористався, для решти він уже не діє.
    <b>На одну людину 1</b> — код для всіх, але кожен використає його один раз.
    Обидва порожні — кодом можна користуватись при кожній покупці.
    <br>
    <b>Сумується з акціями</b> вимкнено — код не діє на товари, які вже продаються зі знижкою.
    <b>Стеля</b> обмежує сумарну знижку на товар: акція 20% + код 15% зі стелею 25% дадуть 25%, а не 35%.
  </p>
  <table class="tbl" style="margin-top:18px">
    <tr><th>Код</th><th class="num">Знижка</th><th>Діє до</th><th>Обмеження</th><th class="num">Використано</th><th></th></tr>
    <?php foreach ($codes as $c): $max = (int)$c['max_uses']; $per = (int)$c['per_user_limit']; $used = (int)($c['used'] ?? 0); ?>
      <tr>
        <td><b><?= e($c['code']) ?></b></td>
        <td class="num">−<?= e($c['percent']) ?>%</td>
        <td class="dim"><?= e($c['expires_at'] ?: 'безстроково') ?></td>
        <td class="muted">
          <?= $max > 0 ? ($max === 1 ? 'одноразовий' : 'всього ' . $max) : 'без обмежень' ?>
          <?= $per > 0 ? ' · ' . ($per === 1 ? 'по разу на людину' : 'по ' . $per . ' на людину') : '' ?>
          <div class="dim" style="font-size:12px">
            <?= Promo::stacks($c) ? 'сумується з акціями' : 'не сумується з акціями' ?>
            <?= $c['max_total_percent'] !== null && $c['max_total_percent'] !== ''
                  ? ' · стеля ' . rtrim(rtrim(number_format((float)$c['max_total_percent'], 2, ',', ''), '0'), ',') . '%'
                  : '' ?>
          </div>
        </td>
        <td class="num<?= $max > 0 && $used >= $max ? ' dim' : '' ?>">
          <?= $used ?><?= $max > 0 ? ' з ' . $max : '' ?>
          <?= $max > 0 && $used >= $max ? '<div class="dim" style="font-size:12px">вичерпано</div>' : '' ?>
        </td>
        <td>
          <form method="post" action="<?= e(url('/admin/promos')) ?>"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="del_code"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-danger btn-xs" onclick="return confirm('Видалити промокод?')">✕</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
