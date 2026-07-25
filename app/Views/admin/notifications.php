<div class="admin-head"><h1 class="h-serif">Сповіщення: події та тексти</h1></div>
<p class="dim" style="margin-bottom:18px">Для кожної події можна увімкнути канали, вибрати одержувачів і змінити текст повідомлення. Підстановки в фігурних дужках замінюються реальними даними.</p>
<form method="post" action="<?= e(url('/admin/notifications')) ?>">
  <?= Csrf::field() ?>
  <?php foreach ($by_event as $event => $rules): ?>
    <div class="admin-card">
      <h2 class="h-serif"><?= e($event_labels[$event] ?? $event) ?></h2>
      <p class="dim" style="margin-bottom:14px">Доступні підстановки: <code><?= e($vars_hint[$event] ?? '') ?></code></p>
      <table class="tbl">
        <tr><th style="width:110px">Канал</th><th style="width:90px">Увімкнено</th><th style="width:200px">Кому</th><th>Текст повідомлення</th></tr>
        <?php foreach ($rules as $r): ?>
          <tr>
            <td><b><?= e($channel_labels[$r['channel']] ?? $r['channel']) ?></b></td>
            <td style="text-align:center">
              <label class="toggle"><input type="checkbox" name="rule[<?= (int)$r['id'] ?>][enabled]" <?= $r['enabled'] ? 'checked' : '' ?>><span class="tr"></span></label>
            </td>
            <td>
              <select name="rule[<?= (int)$r['id'] ?>][recipients]">
                <option value="admins" <?= $r['recipients'] === 'admins' ? 'selected' : '' ?>>Лише адміни</option>
                <option value="sellers" <?= $r['recipients'] === 'sellers' ? 'selected' : '' ?>>Продавці магазину</option>
                <option value="admins_sellers" <?= $r['recipients'] === 'admins_sellers' ? 'selected' : '' ?>>Адміни + продавці</option>
              </select>
            </td>
            <td><textarea name="rule[<?= (int)$r['id'] ?>][template]" rows="2" style="min-width:260px"><?= e($r['template']) ?></textarea></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-gold" type="submit">💾 Зберегти правила</button>
</form>
