<section class="section" style="padding-top:48px">
  <div class="container narrow" style="max-width:640px">
    <div class="kicker">Кабінет</div>
    <h2>Мій профіль</h2>
    <?php if (empty($u['phone'])): ?>
      <div class="flash" style="padding:0;margin-top:16px"><div class="flash-error">Для користування сайтом потрібен номер телефону — вкажіть його нижче.</div></div>
    <?php endif; ?>

    <form class="admin-card" method="post" action="<?= e(url('/profile')) ?>" style="margin-top:22px">
      <?= Csrf::field() ?>
      <div class="field"><label>Ім'я та прізвище</label><input type="text" name="name" value="<?= e($u['name']) ?>" required></div>
      <div class="field"><label>Телефон *</label><input type="tel" name="phone" value="<?= e($u['phone']) ?>" placeholder="067 123 45 67" required></div>
      <div class="field"><label>Email</label><input type="text" value="<?= e($mail_email ?: '—') ?>" disabled>
        <?php if (!$mail_email): ?><p class="dim" style="margin:6px 0 0">Email підтягнеться автоматично, якщо увійти через Google.</p><?php endif; ?>
      </div>
      <?php if ($mail_email): ?>
        <label class="checkbox" style="align-items:flex-start;margin-bottom:18px">
          <input type="checkbox" name="newsletter" value="1" style="margin-top:3px"<?= $subscribed ? ' checked' : '' ?>>
          <span>Отримувати новини та акції на <?= e($mail_email) ?>. Зніміть галку, щоб відписатись.</span>
        </label>
      <?php endif; ?>
      <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    </form>

    <?php if ($notify_options): ?>
      <form class="admin-card" method="post" action="<?= e(url('/profile')) ?>">
        <?= Csrf::field() ?><input type="hidden" name="_action" value="notify">
        <h2 class="h-serif" style="font-size:20px">Сповіщення</h2>
        <p class="dim" style="margin-bottom:16px">Оберіть, що і куди вам надсилати. Показано лише те,
          що ввімкнув адміністратор — решту він вимкнув для всіх.</p>
        <?php foreach ($notify_options as $ev => $channels): ?>
          <div class="notify-row">
            <b><?= e($notify_events[$ev] ?? $ev) ?></b>
            <div class="notify-ch">
              <?php foreach ($channels as $ch => $st): ?>
                <label class="checkbox<?= $st['ready'] ? '' : ' is-off' ?>"
                       <?= $st['ready'] ? '' : 'title="' . e($st['hint']) . '"' ?>>
                  <input type="checkbox" name="n[<?= e($ev) ?>][<?= e($ch) ?>]" value="1"<?= $st['on'] ? ' checked' : '' ?>>
                  <span><?= e($notify_channels[$ch] ?? $ch) ?><?php
                    if (!$st['ready']): ?> <span class="dim">— <?= e($st['hint']) ?></span><?php endif; ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <button class="btn btn-gold" type="submit" style="margin-top:16px">💾 Зберегти сповіщення</button>
      </form>
    <?php endif; ?>

    <div class="admin-card">
      <h2 class="h-serif" style="font-size:20px">Месенджери для сповіщень і входу</h2>
      <p class="dim" style="margin-bottom:16px">Підключіть месенджер — сюди приходитимуть сповіщення про замовлення та коди входу за номером телефону.</p>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
          <b style="min-width:90px">Telegram</b>
          <?php if (!empty($u['tg_chat_id'])): ?>
            <span class="status-pill st-processing">✓ Підключено</span>
          <?php elseif ($tg_ready): ?>
            <button class="btn btn-line btn-sm" id="tgLinkBtn" type="button">Підключити Telegram</button>
            <span class="dim" id="tgLinkHint"></span>
          <?php else: ?>
            <span class="dim">бот ще не налаштований адміністратором</span>
          <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
          <b style="min-width:90px">Viber</b>
          <?php if (!empty($u['viber_id'])): ?>
            <span class="status-pill st-processing">✓ Підключено</span>
          <?php elseif ($viber_ready && $viber_uri): ?>
            <button class="btn btn-line btn-sm" id="viberLinkBtn" type="button">Підключити Viber</button>
            <span class="dim" id="viberLinkHint"></span>
          <?php else: ?>
            <span class="dim">запрацює після публікації сайту на хостингу</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <p><a href="<?= e(url('/orders')) ?>">→ Мої замовлення</a></p>
  </div>
</section>
<script>
(function(){
  var base = '<?= e(url('/')) ?>'.replace(/\/$/, '');
  function poller(checkUrl, hintEl){
    var n = 0;
    var t = setInterval(function(){
      if (++n > 40) { clearInterval(t); return; }
      fetch(base + checkUrl).then(r=>r.json()).then(function(d){
        if (d.linked) { clearInterval(t); location.reload(); }
      });
    }, 3000);
  }
  var tg = document.getElementById('tgLinkBtn');
  if (tg) tg.addEventListener('click', function(){
    fetch(base + '/profile/tg/link').then(r=>r.json()).then(function(d){
      if (!d.ok) return;
      window.open(d.url, '_blank');
      document.getElementById('tgLinkHint').textContent = 'Натисніть Start у боті — сторінка оновиться сама…';
      poller('/profile/tg/check', 'tgLinkHint');
    });
  });
  var vb = document.getElementById('viberLinkBtn');
  if (vb) vb.addEventListener('click', function(){
    fetch(base + '/profile/viber/link').then(r=>r.json()).then(function(d){
      if (!d.ok) return;
      location.href = d.url;
      document.getElementById('viberLinkHint').textContent = 'Підтвердіть у Viber — сторінка оновиться сама…';
      poller('/profile/viber/check', 'viberLinkHint');
    });
  });
})();
</script>
