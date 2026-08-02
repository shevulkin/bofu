<?php
/** @var array $users @var array $stores @var array $seller_stores @var array $user_roles @var array $assignable */
$tgOn = Telegram::configured();
$viberOn = Viber::configured();
?>
<div class="admin-head"><h1 class="h-serif">Користувачі та ролі</h1></div>
<p class="dim" style="margin-bottom:18px">
  Нові користувачі зʼявляються після входу через Google, Telegram або Viber. Ролей може бути кілька —
  права підсумовуються. Магазини призначаються людині, а не ролі, і лише поки в неї є роль продавця:
  знімете роль — вона зникне з усіх точок, і при поверненні ролі їх треба буде обрати наново.
</p>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($users as $u): $uid = (int)$u['id'];
      $has = $user_roles[$uid] ?? [];
      $isSeller = in_array(Roles::SELLER, $has, true);
      // Написати можна лише туди, де є і бот, і привʼязка людини до нього
      $canTg = $tgOn && !empty($u['tg_chat_id']);
      $canViber = $viberOn && !empty($u['viber_id']);
  ?>
    <div class="admin-card user-card">
      <form class="user-grid" method="post" action="<?= e(url('/admin/users')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="user_id" value="<?= $uid ?>">

        <div>
          <b><?= e($u['name']) ?></b>
          <div class="dim"><?= e($u['email'] ?: 'без email') ?></div>
          <div class="dim" style="font-size:11.5px">у системі з <?= e(substr((string)$u['created_at'], 0, 10)) ?></div>
        </div>

        <div><label>Ролі</label>
          <?php foreach ($assignable as $r): ?>
            <label class="checkbox" style="display:block">
              <input type="checkbox" name="roles[]" value="<?= e($r) ?>" <?= in_array($r, $has, true) ? 'checked' : '' ?>
                     <?= $r === Roles::SELLER ? 'data-seller-role' : '' ?>>
              <?= e(Roles::label($r)) ?>
            </label>
          <?php endforeach; ?>
          <span class="dim" style="font-size:12px">Без жодної — звичайний покупець</span>
        </div>

        <?php /* Точки прив'язані до ролі продавця (Users::saveStores), а не до кожної
                 ролі окремо: доступ у людини один на всі її ролі.

                 Галки, а не <select multiple>: у нативному списку звичайний клік
                 скидає попередній вибір, а другу точку додають лише Ctrl+кліком.

                 Підказка під списком міняє ТЕКСТ, а не видимість: зникома підказка
                 змінювала висоту блоку, і від кліку по ролі вся картка стрибала. */ ?>
        <div><label>Магазини</label>
          <div class="store-picker">
            <?php foreach ($stores as $s): $sid = (int)$s['id']; ?>
              <label class="checkbox">
                <input type="checkbox" name="stores[]" value="<?= $sid ?>" data-seller-stores
                       <?= $isSeller && in_array($sid, $seller_stores[$uid] ?? [], true) ? 'checked' : '' ?>
                       <?= $isSeller ? '' : 'disabled' ?>>
                <?= e($s['name'] . ($s['city'] ? ' · ' . $s['city'] : '')) ?>
              </label>
            <?php endforeach; ?>
            <?php if (!$stores): ?><span class="dim">Магазинів ще немає</span><?php endif; ?>
          </div>
          <span class="dim chan-note" data-seller-hint
                data-on="Відмічені точки веде ця людина"
                data-off="Лише для продавців — адмін і так бачить усю мережу"
          ><?= $isSeller ? 'Відмічені точки веде ця людина' : 'Лише для продавців — адмін і так бачить усю мережу' ?></span>
        </div>

        <div><label>Звʼязок</label>
          <input type="tel" name="phone" value="<?= e($u['phone']) ?>" placeholder="067 123 45 67">
          <div class="chan-list">
            <?php if ($tgOn): ?>
              <div class="chan">
                <span class="chan-name">Telegram</span>
                <input type="text" name="tg_chat_id" value="<?= e($u['tg_chat_id']) ?>" placeholder="chat id">
                <span class="chan-state <?= $u['tg_chat_id'] ? 'is-on' : '' ?>"><?= $u['tg_chat_id'] ? '●' : '○' ?></span>
              </div>
            <?php endif; ?>
            <?php if ($viberOn): ?>
              <?php /* viber_id привʼязує сам бот за токеном (Viber::webhook) — руками
                       його не вписати, тому лише показуємо стан */ ?>
              <div class="chan">
                <span class="chan-name">Viber</span>
                <span class="chan-val dim"><?= $u['viber_id'] ? 'привʼязано' : 'не привʼязано' ?></span>
                <span class="chan-state <?= $u['viber_id'] ? 'is-on' : '' ?>"><?= $u['viber_id'] ? '●' : '○' ?></span>
              </div>
            <?php endif; ?>
            <?php if (!$tgOn && !$viberOn): ?>
              <span class="dim chan-note">Месенджери не налаштовані —
                <a href="<?= e(url('/admin/settings')) ?>">увімкнути</a></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="user-actions">
          <label class="checkbox"><input type="checkbox" name="active" <?= $u['active'] ? 'checked' : '' ?>> Активний</label>
          <button class="btn btn-gold btn-sm" type="submit">Зберегти</button>
        </div>
      </form>

      <?php /* Окрема форма, а не частина картки: форми не вкладаються одна в одну,
               та й надсилання повідомлення — інша дія, ніж збереження профілю. */ ?>
      <?php if ($canTg || $canViber): ?>
        <details class="msg-box">
          <summary>✉ Написати від бота</summary>
          <form method="post" action="<?= e(url('/admin/users/message')) ?>" class="msg-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="user_id" value="<?= $uid ?>">
            <?php if ($canTg && $canViber): ?>
              <select name="channel">
                <option value="telegram">Telegram</option>
                <option value="viber">Viber</option>
              </select>
            <?php else: ?>
              <input type="hidden" name="channel" value="<?= $canTg ? 'telegram' : 'viber' ?>">
              <span class="dim">у <?= $canTg ? 'Telegram' : 'Viber' ?></span>
            <?php endif; ?>
            <textarea name="text" rows="2" required placeholder="Текст повідомлення" maxlength="2000"></textarea>
            <button class="btn btn-line btn-sm" type="submit">Надіслати</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
