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

    <div class="admin-card">
      <h2 class="h-serif" style="font-size:20px">Адреси доставки</h2>
      <p class="dim" style="margin-bottom:16px">Збережена адреса підставляється при оформленні — вводити щоразу не треба.
        Отримувача адреса не памʼятає навмисно: посилку видають лише тому, чиї імʼя й телефон у накладній,
        тож їх ви підтверджуєте в кожному замовленні.</p>

      <?php if (!$addresses): ?>
        <p class="dim">Поки що порожньо. Додайте першу — або поставте галку «Запамʼятати цю адресу» при оформленні.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px">
          <?php foreach ($addresses as $a): ?>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:10px">
              <div style="flex:1;min-width:220px">
                <b><?= e(Addresses::title($a)) ?></b>
                <?php if ((int)$a['is_default'] === 1): ?><span class="status-pill st-processing">основна</span><?php endif; ?>
                <div class="dim" style="font-size:13px">
                  <?= $a['delivery'] === 'np'
                        ? e(trim(($a['city'] ?? '') . ($a['np_office'] ? ', ' . $a['np_office'] : ''), ' ,'))
                        : e($a['address']) ?>
                </div>
              </div>
              <button class="btn btn-line btn-sm addr-edit" type="button"
                      data-id="<?= (int)$a['id'] ?>" data-label="<?= e($a['label']) ?>"
                      data-delivery="<?= e($a['delivery']) ?>" data-city="<?= e($a['city']) ?>"
                      data-ref="<?= e($a['city_ref']) ?>" data-office="<?= e($a['np_office']) ?>"
                      data-address="<?= e($a['address']) ?>">Змінити</button>
              <?php if ((int)$a['is_default'] !== 1): ?>
                <form method="post" action="<?= e(url('/profile')) ?>" style="display:inline">
                  <?= Csrf::field() ?><input type="hidden" name="_action" value="address_default">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn btn-line btn-sm" type="submit">Зробити основною</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e(url('/profile')) ?>" style="display:inline"
                    onsubmit="return confirm('Видалити цю адресу?')">
                <?= Csrf::field() ?><input type="hidden" name="_action" value="address_delete">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-line btn-sm" type="submit">Видалити</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/profile')) ?>" id="addrForm">
        <?= Csrf::field() ?><input type="hidden" name="_action" value="address_save">
        <input type="hidden" name="id" id="addrId" value="">
        <input type="hidden" name="delivery" id="addrDelivery" value="np">
        <input type="hidden" name="city_ref" id="npCityRef" value="">
        <h3 class="h-serif" style="font-size:17px;margin:0 0 12px" id="addrFormTitle">Нова адреса</h3>
        <div class="variants" id="addrKind" style="margin-bottom:14px">
          <label class="chip active" data-kind="np">Нова Пошта</label>
          <label class="chip" data-kind="other">Інша доставка</label>
        </div>
        <div class="form-grid">
          <div class="field"><label>Назва <span class="dim">(необовʼязково)</span></label>
            <input type="text" name="label" id="addrLabel" placeholder="Дім, Робота, Мамі"></div>
          <?php /* autocomplete="new-password" і назва не "city" — інакше Chrome накриває
                    наш список власною підстановкою адрес (див. checkout) */ ?>
          <div class="field addr-np"><label>Місто</label>
            <input type="text" name="np_city" id="npCity" placeholder="Почніть вводити місто…" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
          <div class="field addr-np"><label>Відділення / поштомат</label>
            <input type="text" name="np_office" id="npOffice" placeholder="Номер, вулиця або «поштомат»" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
        </div>
        <div class="field addr-other" style="display:none"><label>Адреса</label>
          <input type="text" name="address" id="addrAddress" placeholder="Місто, вулиця, будинок — як вам зручно отримати"></div>
        <button class="btn btn-gold" type="submit">💾 Зберегти адресу</button>
        <button class="btn btn-line" type="button" id="addrCancel" style="display:none">Скасувати</button>
        <p class="dim" style="margin:12px 0 0">Збережено <?= count($addresses) ?> з <?= (int)$addr_limit ?>.</p>
      </form>
    </div>

    <?php if ($notify_options): ?>
      <form class="admin-card" method="post" action="<?= e(url('/profile')) ?>">
        <?= Csrf::field() ?><input type="hidden" name="_action" value="notify">
        <h2 class="h-serif" style="font-size:20px">Сповіщення</h2>
        <p class="dim" style="margin-bottom:16px">Оберіть, куди вам надсилати. Показано лише те,
          що ввімкнув адміністратор — решту він вимкнув для всіх.</p>
        <?php foreach ($notify_options as $ch => $st): ?>
          <div class="notify-row">
            <label class="checkbox<?= $st['ready'] ? '' : ' is-off' ?>"
                   <?= $st['ready'] ? '' : 'title="' . e($st['hint']) . '"' ?>>
              <input type="checkbox" name="ch[<?= e($ch) ?>]" value="1"<?= $st['on'] ? ' checked' : '' ?>>
              <span><b><?= e($notify_channels[$ch] ?? $ch) ?></b><?php
                if (!$st['ready']): ?> <span class="dim">— <?= e($st['hint']) ?></span><?php endif; ?></span>
            </label>
            <?php /* що саме сюди приходитиме — не вибір людини, а пояснення */ ?>
            <div class="dim notify-what"><?= e(implode(', ', array_unique($st['events']))) ?></div>
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
<?php if ($np_enabled) echo View::partial('partials/np_autocomplete'); ?>
<script>
(function(){
  // --- адреси доставки ---
  var form = document.getElementById('addrForm');
  var npWidget = window.npAutocomplete
    ? window.npAutocomplete({city: 'npCity', office: 'npOffice', ref: 'npCityRef'}) : null;

  function setKind(kind){
    document.getElementById('addrDelivery').value = kind;
    document.querySelectorAll('#addrKind .chip').forEach(function(c){
      c.classList.toggle('active', c.dataset.kind === kind);
    });
    document.querySelectorAll('.addr-np').forEach(function(el){ el.style.display = kind === 'np' ? '' : 'none' });
    document.querySelectorAll('.addr-other').forEach(function(el){ el.style.display = kind === 'np' ? 'none' : '' });
  }
  document.querySelectorAll('#addrKind .chip').forEach(function(c){
    c.addEventListener('click', function(){ setKind(c.dataset.kind) });
  });

  function reset(){
    form.reset();
    document.getElementById('addrId').value = '';
    document.getElementById('npCityRef').value = '';
    document.getElementById('addrFormTitle').textContent = 'Нова адреса';
    document.getElementById('addrCancel').style.display = 'none';
    setKind('np');
  }
  var cancel = document.getElementById('addrCancel');
  if (cancel) cancel.addEventListener('click', reset);

  document.querySelectorAll('.addr-edit').forEach(function(btn){
    btn.addEventListener('click', function(){
      var d = btn.dataset;
      document.getElementById('addrId').value = d.id;
      document.getElementById('addrLabel').value = d.label || '';
      document.getElementById('addrAddress').value = d.address || '';
      setKind(d.delivery === 'other' ? 'other' : 'np');
      // ref підставляємо разом з містом — інакше підказки відділень довелося б
      // «розігрівати» повторним пошуком міста
      if (npWidget) npWidget.apply(d.city, d.ref, d.office);
      else {
        document.getElementById('npCity').value = d.city || '';
        document.getElementById('npOffice').value = d.office || '';
        document.getElementById('npCityRef').value = d.ref || '';
      }
      document.getElementById('addrFormTitle').textContent = 'Змінити адресу';
      document.getElementById('addrCancel').style.display = '';
      form.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
  });

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
