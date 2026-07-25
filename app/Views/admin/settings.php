<div class="admin-head"><h1 class="h-serif">Налаштування</h1></div>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <?= Csrf::field() ?>
  <div class="admin-card">
    <h2 class="h-serif">Канали сповіщень</h2>
    <p class="dim" style="margin-bottom:16px">Головний вимикач вимикає все одразу. Тексти повідомлень і події — в розділі «Сповіщення».</p>
    <div style="display:flex;flex-direction:column;gap:14px">
      <?php foreach ($toggles as $key => $label): ?>
        <label class="toggle">
          <input type="checkbox" name="toggle[<?= e($key) ?>]" <?= Settings::bool($key, true) ? 'checked' : '' ?>>
          <span class="tr"></span> <?= e($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="admin-card">
    <h2 class="h-serif">Інтеграції та SEO</h2>
    <div class="form-grid">
      <?php foreach ($text_keys as $key => $label): ?>
        <div class="field">
          <label><?= e($label) ?></label>
          <input type="<?= str_contains($key, 'secret') || str_contains($key, 'token') || str_contains($key, 'key') ? 'password' : 'text' ?>"
                 name="text[<?= e($key) ?>]" value="<?= e(Settings::get($key, '')) ?>" autocomplete="off">
        </div>
      <?php endforeach; ?>
    </div>
    <p class="dim">Google OAuth: створіть ключі в Google Cloud Console → OAuth 2.0 Client ID, redirect URI: <code><?= e(GoogleAuth::redirectUri()) ?></code></p>
    <p class="dim">Telegram: створіть бота через @BotFather, вставте токен. Продавці/адміни отримують Chat ID, написавши боту /start (ID показує, напр., @userinfobot).</p>
    <p class="dim">Нова Пошта: безкоштовний API-ключ у особистому кабінеті novaposhta.ua → Налаштування → Безпека.</p>
    <p class="dim">Web Push: ключі згенеровано автоматично. Пуші на телефоні запрацюють після переносу на HTTPS-домен.</p>
  </div>
  <button class="btn btn-gold" type="submit">💾 Зберегти налаштування</button>
</form>
