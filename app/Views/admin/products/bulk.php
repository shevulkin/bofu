<div class="admin-head">
  <h1 class="h-serif">Масове редагування</h1>
  <span class="dim">Змінюйте будь-які значення і натисніть «Зберегти все»</span>
</div>
<form method="post" action="<?= e(url('/admin/products/bulk')) ?>">
  <?= Csrf::field() ?>
  <table class="tbl">
    <tr>
      <th>Товар</th><th class="w-price">Базова ціна</th>
      <?php foreach ($stores as $s): ?>
        <th class="w-price">Ціна · <?= e($s['city'] ?: $s['name']) ?></th>
        <th class="w-stock">Залишок · <?= e($s['city'] ?: $s['name']) ?></th>
      <?php endforeach; ?>
      <th>Активний</th>
    </tr>
    <?php foreach ($products as $p): ?>
      <tr>
        <td>
          <input type="text" name="p[<?= (int)$p['id'] ?>][name]" value="<?= e($p['name']) ?>" style="min-width:220px">
          <div class="dim"><?= e($p['cat_name'] ?? '') ?></div>
        </td>
        <td><input type="number" step="0.01" name="p[<?= (int)$p['id'] ?>][base_price]" value="<?= e($p['base_price']) ?>" placeholder="За запитом"></td>
        <?php foreach ($stores as $s): ?>
          <td><input type="number" step="0.01" name="p[<?= (int)$p['id'] ?>][store_price][<?= (int)$s['id'] ?>]"
                     value="<?= e($prices[$p['id']][$s['id']] ?? '') ?>" placeholder="базова"></td>
          <td><input type="number" name="p[<?= (int)$p['id'] ?>][stock][<?= (int)$s['id'] ?>]"
                     value="<?= e($stocks[$p['id']][$s['id']] ?? '') ?>"></td>
        <?php endforeach; ?>
        <td style="text-align:center">
          <input type="hidden" name="p[<?= (int)$p['id'] ?>][active]" value="0">
          <input type="checkbox" name="p[<?= (int)$p['id'] ?>][active]" value="1" <?= $p['active'] ? 'checked' : '' ?>>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div style="position:sticky;bottom:0;background:var(--bg);padding:14px 0;border-top:1px solid var(--line);margin-top:2px">
    <button class="btn btn-gold" type="submit">💾 Зберегти все</button>
  </div>
</form>
