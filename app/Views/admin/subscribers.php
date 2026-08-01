<div class="admin-head">
  <h1 class="h-serif">Розсилка</h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/subscribers')) ?>?export=csv">⬇ Експорт CSV</a>
</div>
<div class="admin-card">
  <p class="dim" style="margin:0">Активних підписників: <b style="color:var(--gold)"><?= (int)$active_count ?></b>.
    Адреси потрапляють сюди лише тоді, коли покупець сам поставив галку згоди — при оформленні замовлення або в профілі.
    У кожен лист розсилки обовʼязково вставляйте персональне посилання на відписку (є у CSV).</p>
</div>
<table class="tbl">
  <tr><th>Email</th><th>Ім'я</th><th>Звідки</th><th>Дата згоди</th><th>Стан</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><b><?= e($r['email']) ?></b></td>
      <td><?= e($r['name'] ?: '—') ?></td>
      <td class="dim"><?= $r['source'] === 'profile' ? 'Профіль' : 'Оформлення' ?></td>
      <td class="dim"><?= e($r['created_at']) ?></td>
      <td>
        <?= (int)$r['active'] === 1
          ? '<span class="status-pill st-processing">Підписаний</span>'
          : '<span class="status-pill st-canceled">Відписаний ' . e((string)$r['unsubscribed_at']) . '</span>' ?>
      </td>
      <td style="white-space:nowrap">
        <?php if ((int)$r['active'] === 1): ?>
          <form method="post" action="<?= e(url('/admin/subscribers')) ?>" style="display:inline"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="unsubscribe"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-line btn-xs">Відписати</button>
          </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/admin/subscribers')) ?>" style="display:inline"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-danger btn-xs" onclick="return confirm('Видалити запис назавжди?')">✕</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="dim">Поки що ніхто не підписався.</td></tr><?php endif; ?>
</table>
