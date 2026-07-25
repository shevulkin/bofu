<?php $isNew = $p === null; ?>
<div class="admin-head">
  <h1 class="h-serif"><?= $isNew ? 'Новий товар' : e($p['name']) ?></h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products')) ?>">← До списку</a>
</div>

<form method="post" action="<?= e(url($isNew ? '/admin/products/new' : '/admin/products/' . $p['id'])) ?>">
  <?= Csrf::field() ?>
  <div class="admin-card">
    <h2 class="h-serif">Основне</h2>
    <div class="form-grid">
      <div class="field"><label>Назва *</label><input type="text" name="name" required value="<?= e($p['name'] ?? '') ?>"></div>
      <div class="field"><label>Категорія</label>
        <select name="category_id">
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($p['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Артикул</label><input type="text" name="sku" value="<?= e($p['sku'] ?? '') ?>"></div>
      <div class="field"><label>Тип</label>
        <select name="type">
          <?php foreach (['product' => 'Товар', 'service' => 'Послуга', 'video' => 'Відео', 'course' => 'Курс'] as $t => $lbl): ?>
            <option value="<?= $t ?>" <?= ($p['type'] ?? 'product') === $t ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Базова ціна, грн (порожньо = «За запитом»)</label><input type="number" step="0.01" name="base_price" value="<?= e($p['base_price'] ?? '') ?>"></div>
      <div class="field"><label>Стара ціна (закреслена)</label><input type="number" step="0.01" name="old_price" value="<?= e($p['old_price'] ?? '') ?>"></div>
    </div>
    <div class="field"><label>Короткий опис</label><input type="text" name="short_desc" value="<?= e($p['short_desc'] ?? '') ?>"></div>
    <div class="field"><label>Повний опис</label><textarea name="description" rows="5"><?= e($p['description'] ?? '') ?></textarea></div>
    <div style="display:flex;gap:26px;flex-wrap:wrap">
      <label class="checkbox"><input type="checkbox" name="active" <?= ($p['active'] ?? 1) ? 'checked' : '' ?>> Активний (видно на сайті)</label>
      <label class="checkbox"><input type="checkbox" name="featured" <?= ($p['featured'] ?? 0) ? 'checked' : '' ?>> Рекомендований (на головній)</label>
      <label class="checkbox"><input type="checkbox" name="made_to_order" <?= ($p['made_to_order'] ?? 1) ? 'checked' : '' ?>> Виготовляємо під замовлення</label>
    </div>
  </div>

  <?php if (!$isNew): ?>
  <div class="admin-card">
    <h2 class="h-serif">Ціни та залишки по магазинах</h2>
    <table class="tbl">
      <tr><th>Магазин</th><th class="w-price">Ціна тут (порожньо = базова)</th><th class="w-stock">Залишок, шт</th></tr>
      <?php foreach ($stores as $s): ?>
        <tr>
          <td><?= e($s['name']) ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></td>
          <td><input type="number" step="0.01" name="store_price[<?= (int)$s['id'] ?>]" value="<?= e($store_prices[$s['id']] ?? '') ?>" placeholder="базова"></td>
          <td><input type="number" name="store_stock[<?= (int)$s['id'] ?>]" value="<?= e($store_stock[$s['id']] ?? '') ?>"></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="admin-card">
    <h2 class="h-serif">Варіанти (напр. відтінок, об'єм)</h2>
    <table class="tbl">
      <tr><th>Назва варіанта</th><th class="w-price">Ціна (порожньо = базова)</th><th></th></tr>
      <?php foreach ($variants as $v): ?>
        <tr>
          <td><input type="text" name="variant[<?= (int)$v['id'] ?>][name]" value="<?= e($v['name']) ?>"></td>
          <td><input type="number" step="0.01" name="variant[<?= (int)$v['id'] ?>][price]" value="<?= e($v['price']) ?>"></td>
          <td><label class="checkbox"><input type="checkbox" name="variant[<?= (int)$v['id'] ?>][_delete]" value="1"> видалити</label></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><input type="text" name="variant[new][name]" placeholder="+ новий варіант"></td>
        <td><input type="number" step="0.01" name="variant[new][price]"></td>
        <td></td>
      </tr>
    </table>
  </div>

  <div class="admin-card">
    <h2 class="h-serif">Атрибути / характеристики</h2>
    <table class="tbl">
      <tr><th>Назва</th><th>Значення</th><th>У фільтрах</th><th></th></tr>
      <?php foreach ($attrs as $a): ?>
        <tr>
          <td><input type="text" name="attr[<?= (int)$a['id'] ?>][name]" value="<?= e($a['name']) ?>"></td>
          <td><input type="text" name="attr[<?= (int)$a['id'] ?>][value]" value="<?= e($a['value']) ?>"></td>
          <td style="text-align:center"><input type="checkbox" name="attr[<?= (int)$a['id'] ?>][filterable]" <?= $a['filterable'] ? 'checked' : '' ?>></td>
          <td><label class="checkbox"><input type="checkbox" name="attr[<?= (int)$a['id'] ?>][_delete]" value="1"> видалити</label></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><input type="text" name="attr[new][name]" placeholder="+ нова характеристика"></td>
        <td><input type="text" name="attr[new][value]"></td>
        <td style="text-align:center"><input type="checkbox" name="attr[new][filterable]"></td>
        <td></td>
      </tr>
    </table>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;align-items:center">
    <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    <?php if (!$isNew && Auth::isAdmin()): ?>
      <button class="btn btn-danger" type="submit" name="_action" value="delete"
        onclick="return confirm('Видалити товар разом із фото та цінами?')">Видалити товар</button>
    <?php endif; ?>
  </div>
</form>

<?php if (!$isNew): ?>
<div class="admin-card" style="margin-top:22px">
  <h2 class="h-serif">Фотографії</h2>
  <div class="img-grid">
    <?php foreach ($images as $img): ?>
      <div class="img-cell" style="position:relative">
        <img src="<?= e(asset(Images::thumbPath($img['path']))) ?>" alt="">
        <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>" style="position:absolute;top:4px;right:4px"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete_image">
          <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
          <button class="btn btn-danger btn-xs" style="padding:3px 8px" title="Прибрати з товару" onclick="return confirm('Прибрати фото з товару?')">✕</button>
        </form>
        <span class="dim"><?= (int)$img['width'] ?>×<?= (int)$img['height'] ?> · <?= round($img['bytes'] / 1024) ?> КБ</span>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-gold btn-sm" type="button" onclick="MediaPicker.open(function(path){
      var f = document.getElementById('attachImageForm');
      f.querySelector('[name=media_path]').value = path; f.submit();
    })">📷 Додати фото (з сайту або з ПК)</button>
    <span class="dim">Фото автоматично стискається і адаптується; розмір показано під мініатюрою</span>
  </div>
  <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>" id="attachImageForm" style="display:none">
    <?= Csrf::field() ?>
    <input type="hidden" name="_action" value="attach_image">
    <input type="hidden" name="media_path" value="">
  </form>
</div>
<?= View::partial('partials/media_picker') ?>
<?php endif; ?>
