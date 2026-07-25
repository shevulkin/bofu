<div class="admin-head"><h1 class="h-serif">Дипломи випускників</h1></div>
<form class="admin-card" method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div><label>Номер</label><input type="text" name="number" required placeholder="BOFU-2026-001"></div>
  <div style="flex:1;min-width:180px"><label>Випускник</label><input type="text" name="student" required></div>
  <div><label>Курс</label><input type="text" name="course" placeholder="Промислове бджільництво"></div>
  <div><label>Дата видачі</label><input type="date" name="issued_at"></div>
  <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
</form>
<table class="tbl">
  <tr><th>Номер</th><th>Випускник</th><th>Курс</th><th>Видано</th><th>Стан</th><th></th></tr>
  <?php foreach ($diplomas as $d): ?>
    <tr>
      <td><b><?= e($d['number']) ?></b></td>
      <td><?= e($d['student']) ?></td>
      <td class="muted"><?= e($d['course'] ?: '—') ?></td>
      <td class="dim"><?= e($d['issued_at'] ?: '—') ?></td>
      <td><?= $d['active'] ? '<span class="status-pill st-processing">Дійсний</span>' : '<span class="status-pill st-canceled">Анульований</span>' ?></td>
      <td style="white-space:nowrap">
        <form method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:inline"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-line btn-xs"><?= $d['active'] ? 'Анулювати' : 'Відновити' ?></button>
        </form>
        <form method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:inline"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-danger btn-xs" onclick="return confirm('Видалити запис?')">✕</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
