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
    <tr>
      <th style="width:34%">Назва</th><th>Тип</th><th class="num">Товарів</th>
      <th class="num w-sort">Порядок</th><th class="col-mid">Активна</th><th class="col-mid">Видалити</th>
    </tr>
    <?php foreach ($cats as $c): $busy = (int)$c['cnt'] > 0; ?>
      <tr>
        <td><input type="text" name="cat[<?= (int)$c['id'] ?>][name]" value="<?= e($c['name']) ?>"></td>
        <td class="muted"><?= e(['product'=>'Товари','service'=>'Послуги','video'=>'Відео','course'=>'Курси'][$c['type']] ?? $c['type']) ?></td>
        <td class="muted num"><?= (int)$c['cnt'] ?></td>
        <td class="num"><input type="number" name="cat[<?= (int)$c['id'] ?>][sort]" value="<?= (int)$c['sort'] ?>"></td>
        <td class="col-mid"><input type="checkbox" name="cat[<?= (int)$c['id'] ?>][active]" <?= $c['active'] ? 'checked' : '' ?>></td>
        <td class="col-mid col-del">
          <input type="checkbox" class="js-del" name="cat[<?= (int)$c['id'] ?>][_delete]"
                 <?= $busy ? 'disabled title="Спершу перенесіть або видаліть товари цієї категорії"' : '' ?>>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    <span class="admin-save-note"></span>
  </div>
</form>
