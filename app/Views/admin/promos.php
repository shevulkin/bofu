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
    <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
  </form>
  <table class="tbl" style="margin-top:18px">
    <tr><th>Код</th><th>Знижка</th><th>Діє до</th><th></th></tr>
    <?php foreach ($codes as $c): ?>
      <tr>
        <td><b><?= e($c['code']) ?></b></td>
        <td>−<?= e($c['percent']) ?>%</td>
        <td class="dim"><?= e($c['expires_at'] ?: 'безстроково') ?></td>
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
