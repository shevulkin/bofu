<div class="admin-head">
  <h1 class="h-serif">Масове редагування</h1>
  <span class="dim">Змінюйте будь-які значення і натисніть «Зберегти все»</span>
</div>
<form method="post" action="<?= e(url('/admin/products/bulk')) ?>">
  <?= Csrf::field() ?>
  <p class="dim" style="margin:0 0 12px">
    У товарів з варіантами ціна й залишок задаються окремим рядком для кожного варіанта — саме з них береться наявність у магазині.
  </p>
  <table class="tbl">
    <tr>
      <th>Товар</th><th class="w-price">Базова ціна</th>
      <?php foreach ($stores as $s): ?>
        <th class="w-price">Ціна · <?= e($s['city'] ?: $s['name']) ?></th>
        <th class="w-stock">Залишок · <?= e($s['city'] ?: $s['name']) ?></th>
      <?php endforeach; ?>
      <th>Активний</th>
    </tr>
    <?php foreach ($products as $p): $pid = (int)$p['id']; $vs = $variants[$pid] ?? []; ?>
      <tr>
        <td>
          <input type="text" name="p[<?= $pid ?>][name]" value="<?= e($p['name']) ?>" style="min-width:220px">
          <div class="dim"><?= e($p['cat_name'] ?? '') ?><?= $vs ? ' · варіантів: ' . count($vs) : '' ?></div>
        </td>
        <td><input type="number" step="0.01" name="p[<?= $pid ?>][base_price]" value="<?= e($p['base_price']) ?>" placeholder="За запитом"></td>
        <?php foreach ($stores as $s): $sid = (int)$s['id']; ?>
          <td><input type="number" step="0.01" name="p[<?= $pid ?>][store_price][<?= $sid ?>]"
                     value="<?= e($prices[$pid][$sid] ?? '') ?>" placeholder="базова"></td>
          <td>
            <?php if ($vs): $sum = 0; foreach ($vs as $v) $sum += (int)($vstocks[(int)$v['id']][$sid] ?? 0); ?>
              <span class="dim" title="Сума по варіантах — редагуйте в рядках нижче">Σ <?= $sum ?></span>
            <?php else: ?>
              <input type="number" name="p[<?= $pid ?>][stock][<?= $sid ?>]" value="<?= e($stocks[$pid][$sid] ?? '') ?>">
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td style="text-align:center">
          <input type="hidden" name="p[<?= $pid ?>][active]" value="0">
          <input type="checkbox" name="p[<?= $pid ?>][active]" value="1" <?= $p['active'] ? 'checked' : '' ?>>
        </td>
      </tr>
      <?php foreach ($vs as $v): $vid = (int)$v['id']; ?>
        <tr class="variant-sub">
          <td style="padding-left:26px" class="muted">↳ <?= e($v['name']) ?><?= $v['sku'] ? ' · ' . e($v['sku']) : '' ?></td>
          <td class="dim" style="text-align:center"><?= $v['price'] !== null && $v['price'] !== '' ? e(price_fmt($v['price'])) : '—' ?></td>
          <?php foreach ($stores as $s): $sid = (int)$s['id']; ?>
            <td><input type="number" step="0.01" name="p[<?= $pid ?>][vprice][<?= $vid ?>][<?= $sid ?>]"
                       value="<?= e($vprices[$vid][$sid] ?? '') ?>" placeholder="базова"></td>
            <td><input type="number" name="p[<?= $pid ?>][vstock][<?= $vid ?>][<?= $sid ?>]"
                       value="<?= e($vstocks[$vid][$sid] ?? '') ?>"></td>
          <?php endforeach; ?>
          <td></td>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </table>
  <div style="position:sticky;bottom:0;background:var(--bg);padding:14px 0;border-top:1px solid var(--line);margin-top:2px">
    <button class="btn btn-gold" type="submit">💾 Зберегти все</button>
  </div>
</form>
