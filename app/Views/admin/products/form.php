<?php
/** @var array|null $p @var array $variants @var array $variant_options @var array $attrs @var array $dict */
$isNew = $p === null;
$canStore = fn(int $sid): bool => in_array($sid, Auth::storeIds(), true);
// Картку товару веде адмін, продавець — лише ціни й залишки своїх магазинів.
// Форма повторює те, що перевіряє Products::save(): показувати поля, які все одно
// не збережуться, — гірше, ніж не показувати їх зовсім.
$canEdit = Auth::can('products.manage');
$roEdit = $canEdit ? '' : 'disabled';
?>
<div class="admin-head">
  <h1 class="h-serif"><?= $isNew ? 'Новий товар' : e($p['name']) ?></h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products')) ?>">← До списку</a>
</div>

<?php if (!$canEdit): ?>
  <div class="admin-card" style="border-color:var(--gold)">
    Картку товару (назву, опис, базові ціни, варіанти, фото) редагує адміністратор.
    Вам доступні <b>ціни та залишки ваших магазинів</b> — нижче на цій сторінці.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/admin/products/new' : '/admin/products/' . $p['id'])) ?>" id="productForm">
  <?= Csrf::field() ?>
  <?php /* Enter у будь-якому полі має зберігати товар, а не запускати генератор варіантів */ ?>
  <button type="submit" class="submit-default" tabindex="-1" aria-hidden="true"></button>
  <div class="admin-card">
    <h2 class="h-serif">Основне</h2>
    <div class="form-grid">
      <div class="field"><label>Назва *</label><input type="text" name="name" required value="<?= e($p['name'] ?? '') ?>" <?= $roEdit ?>></div>
      <div class="field"><label>Категорія</label>
        <select name="category_id" id="catSelect" <?= $roEdit ?>>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($p['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Артикул</label><input type="text" name="sku" value="<?= e($p['sku'] ?? '') ?>" <?= $roEdit ?>></div>
      <div class="field"><label>Тип</label>
        <select name="type" <?= $roEdit ?>>
          <?php foreach (['product' => 'Товар', 'service' => 'Послуга', 'video' => 'Відео', 'course' => 'Курс'] as $t => $lbl): ?>
            <option value="<?= $t ?>" <?= ($p['type'] ?? 'product') === $t ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Базова ціна, грн (порожньо = «За запитом»)</label><input type="number" step="0.01" name="base_price" value="<?= e($p['base_price'] ?? '') ?>" <?= $roEdit ?>></div>
      <div class="field"><label>Стара ціна (закреслена)</label><input type="number" step="0.01" name="old_price" value="<?= e($p['old_price'] ?? '') ?>" <?= $roEdit ?>></div>
    </div>
    <div class="field"><label>Короткий опис</label><input type="text" name="short_desc" value="<?= e($p['short_desc'] ?? '') ?>" <?= $roEdit ?>></div>
    <div class="field"><label>Повний опис</label><textarea name="description" rows="5" <?= $roEdit ?>><?= e($p['description'] ?? '') ?></textarea></div>
    <div style="display:flex;gap:26px;flex-wrap:wrap">
      <label class="checkbox"><input type="checkbox" name="active" <?= ($p['active'] ?? 1) ? 'checked' : '' ?> <?= $roEdit ?>> Активний (видно на сайті)</label>
      <label class="checkbox"><input type="checkbox" name="featured" <?= ($p['featured'] ?? 0) ? 'checked' : '' ?> <?= $roEdit ?>> Рекомендований (на головній)</label>
      <label class="checkbox"><input type="checkbox" name="made_to_order" <?= ($p['made_to_order'] ?? 1) ? 'checked' : '' ?> <?= $roEdit ?>> Виготовляємо під замовлення</label>
    </div>
    <div class="field" style="max-width:360px">
      <label>Поріг «закінчується», шт.</label>
      <input type="number" min="0" step="1" name="low_stock_threshold" value="<?= e($p['low_stock_threshold'] ?? '') ?>" placeholder="порожньо — показувати просто «в наявності»" <?= $roEdit ?>>
    </div>
  </div>

  <?php if (!$isNew): ?>
  <?php if ($canEdit): ?>
  <div class="admin-card">
    <h2 class="h-serif">Характеристики</h2>
    <p class="dim" style="margin:-8px 0 14px">
      Обирайте зі спільного словника — так значення однакові в усіх товарів і працюють фільтри.
      Список залежить від категорії товару.
      <?php if (Auth::can('catalog.manage')): ?><a href="<?= e(url('/admin/attributes')) ?>">Керувати словником →</a><?php endif; ?>
    </p>
    <div id="attrRows" class="row-list"></div>
    <button class="btn btn-line btn-sm" type="button" id="attrAdd" style="margin-top:12px">+ Додати характеристику</button>
  </div>

  <div class="admin-card">
    <h2 class="h-serif">Варіанти</h2>
    <p class="dim" style="margin:-8px 0 14px">Різні виконання того самого товару: розмір, колір, об'єм. Покупець обирає їх на сторінці товару.</p>

    <div class="row-list" id="variantRows">
      <?php foreach ($variants as $v): $vid = (int)$v['id']; $opts = $variant_options[$vid] ?? []; ?>
        <div class="grid-row variant-row" data-vid="<?= $vid ?>">
          <div class="gr-main">
            <?php if ($opts): ?>
              <div class="variant-tags">
                <?php foreach ($opts as $o): ?>
                  <span class="tag" title="<?= e($o['attr_name']) ?>">
                    <?php if (!empty($o['color'])): ?><i class="swatch" style="background:<?= e($o['color']) ?>"></i><?php endif; ?>
                    <?= e($o['attr_name']) ?>: <b><?= e($o['value']) ?></b>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <input type="text" name="variant[<?= $vid ?>][name]" value="<?= e($v['name']) ?>" placeholder="Назва варіанта">
            <?php endif; ?>
          </div>
          <input type="number" step="0.01" name="variant[<?= $vid ?>][price]" value="<?= e($v['price']) ?>" placeholder="ціна" title="Порожньо = базова ціна товару">
          <input type="text" name="variant[<?= $vid ?>][sku]" value="<?= e($v['sku'] ?? '') ?>" placeholder="артикул">
          <label class="checkbox" title="Показувати покупцям"><input type="checkbox" name="variant[<?= $vid ?>][active]" <?= $v['active'] ? 'checked' : '' ?>> вкл.</label>
          <button class="btn btn-danger btn-xs row-del" type="button" title="Видалити варіант">✕</button>
          <input type="hidden" name="variant[<?= $vid ?>][_delete]" value="" disabled>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-line btn-sm" type="button" id="variantAdd" style="margin-top:12px">+ Додати варіант вручну</button>

    <div class="gen-box" id="genBox" style="margin-top:22px">
      <b>Згенерувати варіанти з характеристик</b>
      <p class="dim" style="margin:6px 0 12px">Відмітьте значення — створяться всі їхні комбінації (напр. 3 розміри × 2 кольори = 6 варіантів). Наявні комбінації не дублюються.</p>
      <div id="genAxes"></div>
      <button class="btn btn-gold btn-sm" type="submit" name="_action" value="gen_variants" style="margin-top:12px">⚙ Створити комбінації</button>
    </div>
  </div>
  <?php endif; ?>

  <?php $activeVariants = array_values(array_filter($variants, fn($v) => (int)$v['active'] === 1)); ?>
  <div class="admin-card">
    <h2 class="h-serif">Ціни та залишки по магазинах</h2>
    <p class="dim" style="margin:-8px 0 14px">
      Порожня ціна = діє базова. Порожній залишок = немає в наявності (товар усе одно можна замовити, якщо ввімкнено «під замовлення»).
      <?php if ($activeVariants): ?><br>У товару є варіанти, тому наявність рахується <b>по варіантах у кожному магазині</b> — залишок «без варіанта» не враховується.<?php endif; ?>
    </p>
    <div style="overflow-x:auto">
      <table class="tbl matrix">
        <tr>
          <th>Магазин</th>
          <th colspan="2">Товар без варіанта</th>
          <?php foreach ($variants as $v): ?><th colspan="2"><?= e($v['name']) ?><?= (int)$v['active'] === 1 ? '' : ' (вимк.)' ?></th><?php endforeach; ?>
        </tr>
        <tr class="sub"><th></th>
          <th>ціна</th><th>шт</th>
          <?php foreach ($variants as $v): ?><th>ціна</th><th>шт</th><?php endforeach; ?>
        </tr>
        <?php foreach ($stores as $s): $sid = (int)$s['id']; $ro = $canStore($sid) ? '' : 'disabled title="Немає доступу до цього магазину"'; ?>
          <tr>
            <td><?= e($s['name']) ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></td>
            <td><input type="number" step="0.01" name="store_price[<?= $sid ?>]" value="<?= e($store_prices[$sid] ?? '') ?>" placeholder="базова" <?= $ro ?>></td>
            <td>
              <?php if ($activeVariants): ?>
                <?php $sum = 0; foreach ($activeVariants as $v) $sum += (int)($variant_stock[(int)$v['id']][$sid] ?? 0); ?>
                <span class="dim" title="Сума по варіантах цього магазину — редагуйте в колонках варіантів">Σ <?= $sum ?></span>
              <?php else: ?>
                <input type="number" name="store_stock[<?= $sid ?>]" value="<?= e($store_stock[$sid] ?? '') ?>" <?= $ro ?>>
              <?php endif; ?>
            </td>
            <?php foreach ($variants as $v): $vid = (int)$v['id']; ?>
              <td><input type="number" step="0.01" name="vprice[<?= $vid ?>][<?= $sid ?>]" value="<?= e($variant_prices[$vid][$sid] ?? '') ?>" placeholder="базова" <?= $ro ?>></td>
              <td><input type="number" name="vstock[<?= $vid ?>][<?= $sid ?>]" value="<?= e($variant_stock[$vid][$sid] ?? '') ?>" <?= $ro ?>></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php else: ?>
    <p class="dim">Характеристики, варіанти та ціни по магазинах зʼявляться одразу після створення товару.</p>
  <?php endif; ?>

  <div style="display:flex;gap:12px;align-items:center">
    <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    <?php if (!$isNew && Auth::can('products.manage')): ?>
      <button class="btn btn-danger" type="submit" name="_action" value="delete"
        onclick="return confirm('Видалити товар разом із фото та цінами?')">Видалити товар</button>
    <?php endif; ?>
  </div>
</form>

<?php if (!$isNew && $canEdit): ?>
<div class="admin-card" style="margin-top:22px">
  <h2 class="h-serif">Фотографії</h2>
  <p class="dim" style="margin:0 0 14px">Перше фото — головне: воно у каталозі, кошику та при поширенні в соцмережах.
    Решта показуються мініатюрами на сторінці товару в цьому ж порядку.</p>
  <div class="img-grid">
    <?php $last = count($images) - 1; foreach ($images as $i => $img): $isMain = $i === 0; ?>
      <div class="img-cell<?= $isMain ? ' is-main' : '' ?>">
        <img src="<?= e(asset(Images::displayThumb($img['path']))) ?>" alt="">
        <?php if ($isMain): ?><span class="img-badge">Головне</span><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>" class="img-del"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete_image">
          <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
          <button class="btn btn-danger btn-xs" style="padding:3px 8px" title="Прибрати з товару" onclick="return confirm('Прибрати фото з товару?')">✕</button>
        </form>
        <div class="img-actions">
          <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="move_image">
            <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
            <button class="btn btn-line btn-xs" name="dir" value="up" title="Раніше" <?= $i === 0 ? 'disabled' : '' ?>>←</button>
            <button class="btn btn-line btn-xs" name="dir" value="down" title="Пізніше" <?= $i === $last ? 'disabled' : '' ?>>→</button>
          </form>
          <?php if (!$isMain): ?>
            <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>"><?= Csrf::field() ?>
              <input type="hidden" name="_action" value="main_image">
              <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
              <button class="btn btn-line btn-xs" title="Зробити головним">★ Головне</button>
            </form>
          <?php endif; ?>
        </div>
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

<script>
window.BOFU_DICT = <?= json_js($dict) ?>;
window.BOFU_PRODUCT_ATTRS = <?= json_js(array_map(fn($a) => [
    'attribute_id' => (int)$a['attribute_id'],
    'value_id' => (int)$a['value_id'],
    'value' => $a['value'],
], array_values(array_filter($attrs, fn($a) => !empty($a['attribute_id']))))) ?>;
</script>
<script src="<?= e(asset_v('js/product-form.js')) ?>" defer></script>
<?php endif; ?>
