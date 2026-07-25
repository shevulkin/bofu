<div class="admin-head">
  <h1 class="h-serif">Медіа-бібліотека</h1>
  <form method="post" action="<?= e(url('/admin/media')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="upload">
    <input type="file" name="image" accept="image/*" required>
    <button class="btn btn-gold btn-sm" type="submit">+ Завантажити з ПК</button>
  </form>
</div>
<p class="dim" style="margin-bottom:18px">Усі фото сайту. Хрестик видаляє фото звідусіль (товари, банери, галерея). Вбудовані фото дизайну видалити не можна.</p>
<div class="img-grid">
  <?php foreach ($items as $it): ?>
    <div class="img-cell" style="position:relative">
      <img src="<?= e(asset($it['thumb'])) ?>" alt="" loading="lazy">
      <?php if (!$it['builtin']): ?>
        <form method="post" action="<?= e(url('/admin/media')) ?>" style="position:absolute;top:4px;right:4px">
          <?= Csrf::field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="path" value="<?= e($it['path']) ?>">
          <button class="btn btn-danger btn-xs" style="padding:3px 8px" onclick="return confirm('Видалити фото з сайту? Воно зникне з товарів і банерів.')">✕</button>
        </form>
      <?php endif; ?>
      <span class="dim"><?= (int)$it['width'] ?>×<?= (int)$it['height'] ?> · <?= round($it['bytes']/1024) ?> КБ</span>
    </div>
  <?php endforeach; ?>
</div>
