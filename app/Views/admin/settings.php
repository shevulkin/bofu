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
    <h2 class="h-serif">Видимість для пошукових систем</h2>
    <label class="toggle">
      <input type="checkbox" name="seo_noindex" <?= Settings::bool('seo_noindex') ? 'checked' : '' ?>>
      <span class="tr"></span> Закрити сайт від пошукових систем
    </label>
    <p class="dim" style="margin:14px 0 0">Поки увімкнено: у <code>robots.txt</code> стоїть <code>Disallow: /</code>,
      на кожній сторінці — <code>noindex, nofollow</code>, карта сайту порожня. Зручно на час налаштування й тестів.
      <b style="color:var(--gold)">Не забудьте вимкнути перед запуском</b> — інакше сайт не потрапить у Google.</p>
    <p class="dim" style="margin:8px 0 0">Це не захист: сторінки лишаються доступними всім, хто знає адресу.
      Пошуковики просто не додають їх у видачу, а вже проіндексовані зникають протягом кількох днів.</p>
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
  <div class="admin-card">
    <h2 class="h-serif">Тексти бота при вході</h2>
    <p class="dim" style="margin-bottom:16px">
      Вхід через Telegram чи Viber завершується лише після того, як людина поділиться номером телефону —
      без нього ми не змогли б підтвердити замовлення, а покупець із замовленнями на цей номер
      отримав би другий акаунт. Це те, що бот пише на кожному кроці.
      <br>Порожнє поле повертає типовий текст. У фігурних дужках — підстановки.
    </p>
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php foreach ($bot_texts as $key => [$default, $hint]): ?>
        <div class="field" style="margin:0">
          <label><?= e($hint) ?></label>
          <textarea name="bot[<?= e($key) ?>]" rows="2" placeholder="<?= e($default) ?>"><?= e(Settings::get($key, '')) ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="dim" style="margin-top:14px">
      Кнопка «назад на сайт» веде на
      <?php if ($bot_site !== ''): ?><code><?= e($bot_site) ?></code><?php else: ?>
        <b style="color:var(--gold)">нікуди — заповніть «Адреса сайту для кнопки в боті» вище</b><?php endif; ?>.
      Локально адреса визначається сама, але Viber стукає у webhook власним запитом, тож на бойовому
      сервері поле краще заповнити явно.
    </p>
  </div>
  <button class="btn btn-gold" type="submit">💾 Зберегти налаштування</button>
</form>
