<div class="admin-head"><h1 class="h-serif">Користувачі та ролі</h1></div>
<p class="dim" style="margin-bottom:18px">Нові користувачі з'являються після входу через Google. Ролей може бути кілька — права підсумовуються. Призначення магазинів не залежить від ролі. Telegram Chat ID потрібен для сповіщень у Telegram (бот повідомить його командою /start).</p>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($users as $u): ?>
    <form class="admin-card" method="post" action="<?= e(url('/admin/users')) ?>" style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr auto;gap:14px;align-items:end;margin-bottom:0" data-rg="1">
      <?= Csrf::field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
      <div>
        <b><?= e($u['name']) ?></b>
        <div class="dim"><?= e($u['email']) ?></div>
      </div>
      <?php $has = $user_roles[(int)$u['id']] ?? []; ?>
      <div><label>Ролі</label>
        <?php foreach ($assignable as $r): ?>
          <label class="checkbox" style="display:block">
            <input type="checkbox" name="roles[]" value="<?= e($r) ?>" <?= in_array($r, $has, true) ? 'checked' : '' ?>>
            <?= e(Roles::label($r)) ?>
          </label>
        <?php endforeach; ?>
        <span class="dim" style="font-size:12px">Без жодної — звичайний покупець</span>
      </div>
      <div><label>Магазини</label>
        <select name="stores[]" multiple size="2">
          <?php foreach ($stores as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $seller_stores[$u['id']] ?? [], true) ? 'selected' : '' ?>><?= e($s['name'] . ($s['city'] ? ' · ' . $s['city'] : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Telegram Chat ID</label><input type="text" name="tg_chat_id" value="<?= e($u['tg_chat_id']) ?>" placeholder="напр. 123456789"></div>
      <div style="display:flex;gap:12px;align-items:center">
        <label class="checkbox"><input type="checkbox" name="active" <?= $u['active'] ? 'checked' : '' ?>> Активний</label>
        <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>
