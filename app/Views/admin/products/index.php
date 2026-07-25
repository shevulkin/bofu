<div class="admin-head">
  <h1 class="h-serif">Товари</h1>
  <div style="display:flex;gap:10px">
    <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products/bulk')) ?>">Масове редагування</a>
    <a class="btn btn-gold btn-sm" href="<?= e(url('/admin/products/new')) ?>">+ Новий товар</a>
  </div>
</div>
<form class="admin-card" method="get" action="<?= e(url('/admin/products')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
  <div style="flex:1;min-width:200px"><label>Пошук</label><input type="text" name="q" value="<?= e($q) ?>" placeholder="Назва або артикул"></div>
  <div><label>Категорія</label>
    <select name="cat">
      <option value="">Всі</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-gold btn-sm" type="submit">Знайти</button>
</form>
<table class="tbl">
  <tr><th></th><th>Назва</th><th>Категорія</th><th>Ціна</th><th>Статус</th><th></th></tr>
  <?php foreach ($products as $p): ?>
    <tr>
      <td style="width:64px">
        <?php $ph = Catalog::photo($p); ?>
        <img class="img-thumb" src="<?= e(asset($ph)) ?>" alt="" loading="lazy">
      </td>
      <td><b><?= e($p['name']) ?></b><?php if ($p['sku']): ?><div class="dim"><?= e($p['sku']) ?></div><?php endif; ?></td>
      <td class="muted"><?= e($p['cat_name'] ?? '—') ?></td>
      <td><?= e(price_fmt($p['base_price'])) ?></td>
      <td><?= $p['active'] ? '<span class="status-pill st-processing">Активний</span>' : '<span class="status-pill st-canceled">Прихований</span>' ?>
          <?= $p['featured'] ? ' <span class="status-pill st-new">Хіт</span>' : '' ?></td>
      <td><a class="btn btn-line btn-xs" href="<?= e(url('/admin/products/' . $p['id'])) ?>">Редагувати</a></td>
    </tr>
  <?php endforeach; ?>
</table>
