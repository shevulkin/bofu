<div class="admin-head"><h1 class="h-serif">Категорії</h1></div>
<form class="admin-card" method="post" action="<?= e(url('/admin/categories')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div style="flex:1;min-width:200px"><label>Назва нової категорії</label><input type="text" name="name" required></div>
  <div><label>Тип</label>
    <select name="type"><option value="product">Товари</option><option value="service">Послуги</option><option value="video">Відео</option><option value="course">Курси</option></select>
  </div>
  <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
</form>
<form method="post" action="<?= e(url('/admin/categories')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <table class="tbl">
    <tr><th>Назва</th><th>Тип</th><th>Товарів</th><th style="width:90px">Порядок</th><th>Активна</th><th>Видалити</th></tr>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><input type="text" name="cat[<?= (int)$c['id'] ?>][name]" value="<?= e($c['name']) ?>"></td>
        <td class="muted"><?= e(['product'=>'Товари','service'=>'Послуги','video'=>'Відео','course'=>'Курси'][$c['type']] ?? $c['type']) ?></td>
        <td class="muted"><?= (int)$c['cnt'] ?></td>
        <td><input type="number" name="cat[<?= (int)$c['id'] ?>][sort]" value="<?= (int)$c['sort'] ?>"></td>
        <td style="text-align:center"><input type="checkbox" name="cat[<?= (int)$c['id'] ?>][active]" <?= $c['active'] ? 'checked' : '' ?>></td>
        <td style="text-align:center"><input type="checkbox" name="cat[<?= (int)$c['id'] ?>][_delete]" <?= $c['cnt'] > 0 ? 'disabled title="У категорії є товари"' : '' ?>></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <button class="btn btn-gold" style="margin-top:16px" type="submit">💾 Зберегти</button>
</form>
