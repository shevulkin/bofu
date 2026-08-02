<div class="modal-back" id="authModal">
  <div class="modal">
    <h3>Вхід на сайт</h3>
    <div class="stack">
      <?php if (GoogleAuth::configured()): ?>
        <a class="btn btn-gold" href="<?= e(url('/auth/google')) ?>">Увійти через Google</a>
      <?php endif; ?>
      <?php if (Telegram::configured()): ?>
        <button class="btn btn-line" id="tgLoginBtn" type="button">Увійти через Telegram</button>
      <?php endif; ?>
      <?php if (Viber::configured() && Viber::uri()): ?>
        <button class="btn btn-line" id="viberLoginBtn" type="button">Увійти через Viber</button>
      <?php endif; ?>
      <?php if (Telegram::configured() || Viber::configured()): ?>
        <button class="btn btn-line" id="phoneLoginBtn" type="button">Увійти за номером телефону</button>
      <?php endif; ?>
    </div>
    <div id="loginHint" class="dim" style="margin-top:12px;display:none"></div>

    <div id="phoneLoginBox" style="display:none;margin-top:14px">
      <?php /* data-phone="ua" — вхід за номером приймає лише український
               (AuthController::phoneStart через normPhone), тож і підказка
               має бути про український, а не пускати +49 і потім відмовляти */ ?>
      <div class="field"><label>Номер телефону</label><input type="tel" id="phoneInput" data-phone="ua" placeholder="067 123 45 67"></div>
      <div class="field" id="codeField" style="display:none"><label>Код з месенджера</label><input type="text" id="codeInput" placeholder="123456" inputmode="numeric"></div>
      <button class="btn btn-gold btn-sm" id="phoneSendBtn" type="button">Отримати код</button>
      <button class="btn btn-gold btn-sm" id="codeVerifyBtn" type="button" style="display:none">Увійти</button>
      <p class="dim" style="margin-top:8px">Код прийде у ваш Telegram або Viber, привʼязаний до акаунта.</p>
    </div>

    <?php if (cfg('demo_login')): ?>
      <p class="dim" style="margin-top:14px">Демо-режим: оберіть, під яким акаунтом увійти.</p>
      <div class="stack">
        <?php foreach (['customer' => 'Увійти як покупець', 'seller' => 'Увійти як продавець', 'admin' => 'Увійти як адміністратор'] as $role => $label): ?>
        <form method="post" action="<?= e(url('/auth/demo')) ?>"><?= Csrf::field() ?>
          <input type="hidden" name="role" value="<?= $role ?>">
          <button class="btn btn-line" style="width:100%" type="submit"><?= $label ?></button>
        </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="stack"><button class="btn btn-line btn-sm" id="authClose" type="button">Скасувати</button></div>
  </div>
</div>
<script>
(function(){
  var base = '<?= e(url('/')) ?>'.replace(/\/$/, '');
  var csrf = '<?= e(Csrf::token()) ?>';
  var hint = document.getElementById('loginHint');
  function show(msg){ if(hint){hint.style.display='block';hint.textContent=msg;} }
  function pollStatus(url){
    var n = 0;
    var t = setInterval(function(){
      if (++n > 60) { clearInterval(t); show('Час вийшов. Спробуйте ще раз.'); return; }
      fetch(base + url).then(r=>r.json()).then(function(d){
        if (d.logged_in) { clearInterval(t); location.reload(); }
      });
    }, 2500);
  }
  function startLogin(startUrl, statusUrl, hintMsg){
    fetch(base + startUrl).then(r=>r.json()).then(function(d){
      if (!d.ok) { show(d.error || 'Недоступно'); return; }
      window.open(d.url, '_blank');
      show(hintMsg);
      pollStatus(statusUrl);
    });
  }
  var tg = document.getElementById('tgLoginBtn');
  if (tg) tg.addEventListener('click', function(){ startLogin('/auth/tg/start', '/auth/tg/status', 'Натисніть Start у Telegram-боті — сайт увійде автоматично…'); });
  var vb = document.getElementById('viberLoginBtn');
  if (vb) vb.addEventListener('click', function(){ startLogin('/auth/viber/start', '/auth/viber/status', 'Підтвердіть у Viber — сайт увійде автоматично…'); });

  var pb = document.getElementById('phoneLoginBtn');
  if (pb) pb.addEventListener('click', function(){
    var box = document.getElementById('phoneLoginBox');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
  });
  var sendBtn = document.getElementById('phoneSendBtn');
  if (sendBtn) sendBtn.addEventListener('click', function(){
    var fd = new FormData();
    fd.append('_csrf', csrf); fd.append('phone', document.getElementById('phoneInput').value);
    fetch(base + '/auth/phone/start', {method:'POST', body: fd}).then(r=>r.json()).then(function(d){
      if (!d.ok) { show(d.error || 'Помилка'); return; }
      show('Код надіслано у ' + d.via + '. Введіть його нижче.');
      document.getElementById('codeField').style.display = 'block';
      document.getElementById('codeVerifyBtn').style.display = 'inline-flex';
      sendBtn.style.display = 'none';
    });
  });
  var verBtn = document.getElementById('codeVerifyBtn');
  if (verBtn) verBtn.addEventListener('click', function(){
    var fd = new FormData();
    fd.append('_csrf', csrf); fd.append('code', document.getElementById('codeInput').value);
    fetch(base + '/auth/phone/verify', {method:'POST', body: fd}).then(r=>r.json()).then(function(d){
      if (d.logged_in) location.reload(); else show(d.error || 'Невірний код');
    });
  });
})();
</script>
