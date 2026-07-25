<div class="admin-head"><h1 class="h-serif">Користувачі та ролі</h1></div>
<p class="dim" style="margin-bottom:18px">Нові користувачі з'являються після входу через Google. Тут ви призначаєте ролі та магазини для продавців. Telegram Chat ID потрібен для сповіщень у Telegram (бот повідомить його командою /start).</p>
<?php $roles = ['admin' => 'Адміністратор', 'seller' => 'Продавець', 'editor' => 'Автор постів', 'customer' => 'Покупець']; ?>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($users as $u): ?>
    <form class="admin-card" method="post" action="<?= e(url('/admin/users')) ?>" style="display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr auto;gap:14px;align-items:end;margin-bottom:0" data-rg="1">
      <?= Csrf::field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
      <div>
        <b><?= e($u['name']) ?></b>
        <div class="dim"><?= e($u['email']) ?></div>
      </div>
      <div><label>Роль</label>
        <select name="role">
          <?php foreach ($roles as $r => $lbl): ?>
            <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Магазини (для продавця)</label>
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
