<div class="modal-back" id="authModal">
  <div class="modal">
    <h3>Вхід на сайт</h3>
    <?php /* Навіщо входити — і, головне, що для покупки це не обовʼязково.
             Без цього рядка модалка читається як обовʼязкова реєстрація перед
             замовленням, і частина покупців закриває її разом із кошиком. */ ?>
    <p class="dim" style="margin:-4px 0 16px">Історія замовлень, збережені адреси й сповіщення
      про наявність. Щоб купити, входити не обовʼязково.</p>
    <div class="stack">
      <?php if (GoogleAuth::configured()): ?>
        <a class="btn btn-gold" href="<?= e(url('/auth/google')) ?>">Увійти через Google</a>
      <?php endif; ?>
      <?php if (Telegram::configured()): ?>
        <button class="btn btn-line" id="tgLoginBtn" type="button">Увійти через Telegram</button>
      <?php endif; ?>
      <?php /* Входу через Viber тут немає навмисно, і це не забута кнопка.
               Viber не має чим довести, що надісланий номер належить саме
               співрозмовнику: у контакті немає поля, з яким це можна звірити
               (у Telegram воно є — див. Telegram::onContact). Тому Viber
               лишається каналом сповіщень і доставки коду, а не способом
               увійти: код приходить у ВЖЕ підключений месенджер, тобто туди,
               куди сторонній не дістане. */ ?>
      <?php if (Telegram::configured() || Viber::configured()): ?>
        <button class="btn btn-line" id="phoneLoginBtn" type="button">Увійти за номером телефону</button>
      <?php endif; ?>
    </div>
    <div id="loginHint" class="dim" style="margin-top:12px;display:none"></div>

    <div id="phoneLoginBox" style="display:none;margin-top:14px">
      <div class="field"><label>Номер телефону</label><input type="tel" id="phoneInput" placeholder="067 123 45 67"></div>
      <div class="field" id="codeField" style="display:none"><label>Код з месенджера</label><input type="text" id="codeInput" placeholder="123456" inputmode="numeric"></div>
      <button class="btn btn-gold btn-sm" id="phoneSendBtn" type="button">Отримати код</button>
      <button class="btn btn-gold btn-sm" id="codeVerifyBtn" type="button" style="display:none">Увійти</button>
      <p class="dim" style="margin-top:8px">Код прийде у ваш Telegram або Viber, привʼязаний до акаунта.</p>
    </div>

    <?php /* Демо-входу тут більше немає. Він видавав адмін-права одним POST без
             пароля, а стримував його один прапорець у config.local.php — тобто
             випадково скопійований на сервер рядок віддавав магазин чужому, і
             ніщо про це не попереджало. Для локальної розробки те саме дає
             `php bin/cli.php grant-admin`, і воно не живе в бойовому коді. */ ?>
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
  if (tg) tg.addEventListener('click', function(){ startLogin('/auth/tg/start', '/auth/tg/status', 'У боті натисніть Start, а тоді «Поділитися номером» — сайт увійде автоматично…'); });

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
