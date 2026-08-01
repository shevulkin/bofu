<div class="admin-head">
  <h1 class="h-serif">Медіа-бібліотека</h1>
  <form method="post" action="<?= e(url('/admin/media')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="upload">
    <input type="file" name="image" accept="image/*" required>
    <button class="btn btn-gold btn-sm" type="submit">+ Завантажити з ПК</button>
  </form>
</div>
<p class="dim" style="margin-bottom:18px">Усі фото сайту. Хрестик видаляє фото звідусіль. Фото, прив'язані до товарів, банерів чи галереї, видалити не можна — спершу приберіть/замініть їх там (посилання під фото). Вбудовані фото дизайну видалити не можна.</p>
<div class="img-grid">
  <?php foreach ($items as $it): $uses = $it['usage'] ?? []; ?>
    <div class="img-cell" style="position:relative">
      <img src="<?= e(asset($it['thumb'])) ?>" alt="" loading="lazy">
      <?php if (!$it['builtin']): ?>
        <?php if ($uses): ?>
          <span class="badge" title="Фото використовується — видалення заблоковано" style="position:absolute;top:4px;right:4px">🔒 <?= count($uses) ?></span>
        <?php else: ?>
          <form method="post" action="<?= e(url('/admin/media')) ?>" style="position:absolute;top:4px;right:4px">
            <?= Csrf::field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="path" value="<?= e($it['path']) ?>">
            <button class="btn btn-danger btn-xs" style="padding:3px 8px" onclick="return confirm('Видалити фото з сайту?')">✕</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <span class="dim"><?= (int)$it['width'] ?>×<?= (int)$it['height'] ?> · <?= round($it['bytes']/1024) ?> КБ</span>
      <?php if ($uses): ?>
        <div class="dim" style="margin-top:4px;font-size:12px;line-height:1.5">
          <?php foreach ($uses as $u): ?>
            <div><a href="<?= e($u['url']) ?>">→ <?= e($u['label']) ?></a></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
