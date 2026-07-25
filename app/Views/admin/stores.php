<div class="admin-head"><h1 class="h-serif">Магазини</h1></div>
<form class="admin-card" method="post" action="<?= e(url('/admin/stores')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div style="flex:1;min-width:160px"><label>Назва</label><input type="text" name="name" required></div>
  <div><label>Місто</label><input type="text" name="city"></div>
  <div><label>Адреса</label><input type="text" name="address"></div>
  <div><label>Телефон</label><input type="text" name="phone"></div>
  <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
</form>
<form method="post" action="<?= e(url('/admin/stores')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <table class="tbl">
    <tr><th>Назва</th><th>Місто</th><th>Адреса</th><th>Телефон</th><th>Активний</th></tr>
    <?php foreach ($stores as $s): ?>
      <tr>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][name]" value="<?= e($s['name']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][city]" value="<?= e($s['city']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][address]" value="<?= e($s['address']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][phone]" value="<?= e($s['phone']) ?>"></td>
        <td style="text-align:center"><input type="checkbox" name="store[<?= (int)$s['id'] ?>][active]" <?= $s['active'] ? 'checked' : '' ?>></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <button class="btn btn-gold" style="margin-top:16px" type="submit">💾 Зберегти</button>
</form>
