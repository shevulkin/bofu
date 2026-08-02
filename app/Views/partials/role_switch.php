<?php
/**
 * Перемикач режиму перегляду. Показуємо лише тим, кому є що вдавати,
 * тобто персоналу — покупець цього блоку не бачить взагалі.
 */
$rsRoles = Auth::simulatableRoles();
if (!$rsRoles) return;

$rsActive  = Auth::actingAs();
$rsBack    = request_path();
$rsAllowed = Auth::authorityStoreIds();
$rsStores  = array_values(array_filter(
    DB::all('SELECT id, name FROM stores ORDER BY id'),
    fn($s) => in_array((int)$s['id'], $rsAllowed, true)
));
$rsStoreId = Auth::actingStoreId();
?>
<div class="role-switch<?= $rsActive !== null ? ' is-acting' : '' ?>">
  <?php if ($rsActive !== null): ?>
    <span class="role-switch-now">
      Режим перегляду: <b><?= e(Roles::label($rsActive)) ?></b>
      <?php if ($rsActive === Roles::SELLER && $rsStoreId):
        $rsName = '';
        foreach ($rsStores as $s) if ((int)$s['id'] === $rsStoreId) $rsName = $s['name'];
        if ($rsName !== ''): ?> · <?= e($rsName) ?><?php endif;
      endif; ?>
    </span>
    <form method="post" action="<?= e(url('/role/reset')) ?>"><?= Csrf::field() ?>
      <input type="hidden" name="back" value="<?= e($rsBack) ?>">
      <button class="btn btn-line btn-xs" type="submit">Повернути свої права</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('/role/switch')) ?>"><?= Csrf::field() ?>
      <input type="hidden" name="back" value="<?= e($rsBack) ?>">
      <label class="dim" for="rsRole">Дивитись як</label>
      <select name="role" id="rsRole">
        <?php foreach ($rsRoles as $r): ?>
          <option value="<?= e($r) ?>"><?= e(Roles::label($r)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (count($rsStores) > 1): ?>
        <select name="store_id" title="Точка для режиму продавця">
          <?php foreach ($rsStores as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <button class="btn btn-line btn-xs" type="submit">Перемкнути</button>
    </form>
  <?php endif; ?>
</div>
