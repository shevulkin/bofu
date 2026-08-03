<?php
  // Обрана за замовчуванням збережена адреса підставляється просто в поля —
  // так вона працює й без JS, і покупцеві нічого не треба натискати
  $selDelivery = $sel['delivery'] ?? 'np';
  $selCity = $sel['city'] ?? '';
  $selRef = $sel['city_ref'] ?? '';
  $selOffice = $sel['np_office'] ?? '';
  $selAddress = $sel['address'] ?? '';
  $canSave = !empty($auth_user);
?>
<section class="section" style="padding-top:44px">
  <div class="container co-wrap">
    <div class="kicker">Оформлення</div>
    <h2>Замовлення</h2>
    <form method="post" action="<?= e(url('/checkout/submit')) ?>" class="co-grid" id="checkoutForm">
      <?= Csrf::field() ?>
      <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden">
        <label>Не заповнюйте це поле<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div>
      <div class="co-step">
        <h3 class="co-step-h"><span class="co-num">1</span>Доставка</h3>

        <?php if ($addresses): ?>
          <div class="field">
            <label>Мої адреси</label>
            <div class="variants" id="addrChips">
              <?php foreach ($addresses as $a): ?>
                <label class="chip<?= $sel && (int)$sel['id'] === (int)$a['id'] ? ' active' : '' ?>">
                  <input type="radio" name="address_id" value="<?= (int)$a['id'] ?>" hidden
                         <?= $sel && (int)$sel['id'] === (int)$a['id'] ? 'checked' : '' ?>
                         data-delivery="<?= e($a['delivery']) ?>" data-city="<?= e($a['city']) ?>"
                         data-ref="<?= e($a['city_ref']) ?>" data-office="<?= e($a['np_office']) ?>"
                         data-address="<?= e($a['address']) ?>">
                  <?= e(Addresses::title($a)) ?>
                </label>
              <?php endforeach; ?>
              <label class="chip"><input type="radio" name="address_id" value="" hidden>+ Інша адреса</label>
            </div>
            <p class="dim" style="margin:8px 0 0;font-size:13px">Адреси зберігаються без отримувача — його вказуєте щоразу.
              Керувати списком: <a href="<?= e(url('/profile')) ?>">у профілі</a>.</p>
          </div>
        <?php endif; ?>

        <div class="field">
          <label>Спосіб доставки</label>
          <div class="variants" id="deliveryChips">
            <label class="chip<?= $selDelivery === 'np' ? ' active' : '' ?>"><input type="radio" name="delivery" value="np"<?= $selDelivery === 'np' ? ' checked' : '' ?> hidden>Нова Пошта</label>
            <label class="chip"><input type="radio" name="delivery" value="pickup" hidden>Самовивіз з магазину</label>
            <label class="chip<?= $selDelivery === 'other' ? ' active' : '' ?>"><input type="radio" name="delivery" value="other"<?= $selDelivery === 'other' ? ' checked' : '' ?> hidden>Інше (узгодимо)</label>
          </div>
        </div>

        <div id="npFields"<?= $selDelivery === 'np' ? '' : ' style="display:none"' ?>>
          <div class="form-grid">
            <?php /* autocomplete="new-password" — єдине, що глушить автопідстановку адрес
                      у Chrome: "off" він для адресних полів свідомо ігнорує і накриває наш
                      список своїм. Ім'я поля теж не "city" — інакше евристика впізнає його
                      за назвою. data-* — те саме для менеджерів паролів. */ ?>
            <div class="field"><label>Місто</label><input type="text" name="np_city" id="npCity" value="<?= e($selCity) ?>" placeholder="Почніть вводити місто…" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
            <div class="field"><label>Відділення / поштомат</label><input type="text" name="np_office" id="npOffice" value="<?= e($selOffice) ?>" placeholder="Номер, вулиця або «поштомат»" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
          </div>
          <input type="hidden" name="city_ref" id="npCityRef" value="<?= e($selRef) ?>">
        </div>

        <div id="pickupFields" style="display:none">
          <div class="field"><label>Магазин</label>
            <select name="store_id" id="pickupStore">
              <option value="">— оберіть магазин —</option>
              <?php foreach ($stores as $s): $sid = (int)$s['id']; $miss = $missing[$sid] ?? []; ?>
                <option value="<?= $sid ?>" data-missing="<?= e(implode(', ', $miss)) ?>">
                  <?= e($s['name'] . ($s['city'] ? ', ' . $s['city'] : '') . ($s['address'] ? ', ' . $s['address'] : '')) ?>
                  <?= $miss ? ' — немає частини позицій' : ' — все є в наявності' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="dim" id="pickupNote" style="margin:8px 0 0"></p>
          </div>
        </div>

        <div id="otherFields"<?= $selDelivery === 'other' ? '' : ' style="display:none"' ?>>
          <div class="field"><label>Адреса / побажання</label><input type="text" name="address" id="otherAddress" value="<?= e($selAddress) ?>" placeholder="Опишіть, як вам зручно отримати"></div>
        </div>

        <?php if ($canSave): ?>
          <label class="checkbox" id="saveAddrRow" style="margin-bottom:18px<?= $selDelivery === 'pickup' ? ';display:none' : '' ?>">
            <input type="checkbox" name="save_address" value="1" checked>
            <span>Запамʼятати цю адресу — наступного разу не доведеться вводити</span>
          </label>
        <?php endif; ?>
      </div><!-- /крок 1 -->

      <div class="co-step">
        <h3 class="co-step-h"><span class="co-num">2</span>Отримувач</h3>
        <p class="co-note">Нова Пошта видає посилку лише тому, чиї імʼя й телефон вказані в накладній.
          Якщо забираєте самі — поставте галку, і дані підставляться з профілю.</p>

        <?php if ($pre['name'] !== '' || $pre['phone'] !== ''): ?>
          <label class="checkbox" id="meRow" style="margin-bottom:16px">
            <input type="checkbox" id="meRecipient">
            <span>Я отримувач</span>
          </label>
        <?php endif; ?>

        <div class="form-grid">
          <div class="field"><label>Отримувач *</label><input type="text" name="name" id="ordName" value="" required placeholder="Ім'я та прізвище"></div>
          <div class="field"><label>Телефон отримувача *</label><input type="tel" name="phone" id="ordPhone" value="" required placeholder="+380 __ ___ ____"></div>
        </div>
        <div class="field"><label>Email <span class="dim">(необовʼязково)</span></label><input type="email" name="email" id="orderEmail" value="<?= e($pre['email']) ?>" placeholder="надішлемо підтвердження замовлення"></div>

        <label class="checkbox" id="newsletterRow" style="align-items:flex-start;margin-bottom:18px;<?= $pre['email'] === '' ? 'display:none' : '' ?>">
          <input type="checkbox" name="newsletter" value="1" style="margin-top:3px"<?= $subscribed ? ' checked' : '' ?>>
          <span>Хочу отримувати новини та акції на цей email. Відписатись можна будь-коли — посиланням у листі або в профілі.</span>
        </label>
      </div><!-- /крок 2 -->

      <div class="co-step">
        <h3 class="co-step-h"><span class="co-num">3</span>Побажання</h3>
        <div class="field"><label>Коментар до замовлення</label>
          <textarea name="comment" rows="3" placeholder="Необовʼязково: зручний час дзвінка, побажання до пакування"></textarea></div>
      </div>
      </div><!-- /ліва колонка -->

      <aside class="co-sum">
        <h3 class="co-sum-h">Ваше замовлення</h3>
        <div class="co-items">
          <?php foreach ($rows as $r): $cut = Promo::cut((float)($r['sum'] ?? 0), $promo); ?>
            <div class="co-item" data-key="<?= e($r['key']) ?>">
              <img src="<?= e(asset($r['photo'])) ?>" alt="" loading="lazy">
              <div style="min-width:0">
                <div class="co-item-name"><?= e($r['product']['name']) ?></div>
                <div class="dim">
                  <?= $r['variant'] ? e($r['variant']['name']) . ' · ' : '' ?><?= (int)$r['qty'] ?> × <?= e(price_fmt($r['price'])) ?>
                </div>
              </div>
              <div class="co-item-sum">
                <?php if ($cut > 0): ?><s class="co-old"><?= e(price_fmt($r['sum'])) ?></s><?php endif; ?>
                <span class="co-now"><?= e(price_fmt(($r['sum'] ?? 0) - $cut)) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="field" style="margin-bottom:16px">
          <label>Промокод</label>
          <div class="co-promo">
            <input type="text" name="promo_code" id="promoInput" value="<?= e($promo['code'] ?? '') ?>" autocomplete="off" spellcheck="false">
            <button class="btn btn-line" type="button" id="promoBtn"><?= $promo ? 'Прибрати' : 'Застосувати' ?></button>
          </div>
          <p class="field-hint<?= $promo ? ' is-ok' : '' ?>" id="promoHint">
            <?= $promo ? e('✓ ' . Promo::label($promo) . ' — мінус ' . price_fmt($totals['discount'])) : '' ?>
          </p>
        </div>

        <div class="totals">
          <div class="row"><span class="muted">Товари:</span><span id="sumSubtotal"><?= e(price_fmt($totals['subtotal'])) ?></span></div>
          <div class="row" id="sumDiscountRow"<?= $totals['discount'] > 0 ? '' : ' style="display:none"' ?>>
            <span class="muted" id="sumDiscountLabel"><?= $promo ? e(Promo::label($promo)) : 'Знижка' ?>:</span>
            <span id="sumDiscount">−<?= e($totals['discount'] > 0 ? price_fmt($totals['discount']) : '0 грн') ?></span>
          </div>
          <div class="row grand"><span>До сплати:</span><span id="sumTotal"><?= e(price_fmt($totals['total'])) ?></span></div>
        </div>

        <p class="dim" style="margin:16px 0 18px;font-size:13px">Оплата при отриманні або за домовленістю —
          продавець зв'яжеться з вами для підтвердження.</p>
        <button class="btn btn-gold co-submit" type="submit" style="width:100%">Підтвердити замовлення</button>
        <p class="dim" style="margin:14px 0 0;font-size:12.5px">
          <a href="<?= e(url('/cart')) ?>">← Повернутись до кошика</a></p>
      </aside>

      <!-- телефон: сума й кнопка завжди під рукою, без гортання через усю форму -->
      <div class="co-bar">
        <div class="co-bar-total"><span>До сплати</span><b id="barTotal"><?= e(price_fmt($totals['total'])) ?></b></div>
        <button class="btn btn-gold" type="submit">Підтвердити</button>
      </div>
    </form>
  </div>
</section>
<?php if ($np_enabled) echo View::partial('partials/np_autocomplete'); ?>
<script>
(function(){
  var np = document.getElementById('npFields'), pk = document.getElementById('pickupFields'), ot = document.getElementById('otherFields');
  var saveRow = document.getElementById('saveAddrRow');
  var nameInput = document.getElementById('ordName'), phoneInput = document.getElementById('ordPhone');
  var me = <?= json_js(['name' => $pre['name'], 'phone' => $pre['phone']]) ?>;
  var npWidget = window.npAutocomplete
    ? window.npAutocomplete({city: 'npCity', office: 'npOffice', ref: 'npCityRef'}) : null;

  function showFor(v){
    np.style.display = v === 'np' ? '' : 'none';
    pk.style.display = v === 'pickup' ? '' : 'none';
    ot.style.display = v === 'other' ? '' : 'none';
    // самовивіз зберігати нічого: адреса магазину і так у довіднику
    if (saveRow) saveRow.style.display = v === 'pickup' ? 'none' : '';
  }
  // «Я отримувач»: одні й ті самі поля, просто заповнені з профілю
  var meBox = document.getElementById('meRecipient');
  function applyMe(){
    if (meBox.checked) {
      nameInput.value = me.name; phoneInput.value = me.phone;
      nameInput.readOnly = phoneInput.readOnly = true;
    } else {
      nameInput.value = phoneInput.value = '';
      nameInput.readOnly = phoneInput.readOnly = false;
      nameInput.focus();
    }
  }
  if (meBox) meBox.addEventListener('change', applyMe);

  var chips = document.querySelectorAll('#deliveryChips .chip');
  chips.forEach(function(ch){
    ch.addEventListener('click', function(){
      chips.forEach(function(c){c.classList.remove('active')});
      ch.classList.add('active');
      var v = ch.querySelector('input').value;
      showFor(v);
      // забираєте самі — отримувач очевидний, тож підставляємо себе одразу
      if (v !== 'np' && meBox && !meBox.checked && !nameInput.value && !phoneInput.value) {
        meBox.checked = true; applyMe();
      }
    });
  });

  // вибір збереженої адреси
  document.querySelectorAll('#addrChips .chip').forEach(function(ch){
    ch.addEventListener('click', function(){
      document.querySelectorAll('#addrChips .chip').forEach(function(c){c.classList.remove('active')});
      ch.classList.add('active');
      var i = ch.querySelector('input'), d = i.dataset;
      if (!i.value) {                           // «Інша адреса» — звільняємо поля під нову
        if (npWidget) npWidget.apply('', '', '');
        else { document.getElementById('npCity').value = ''; document.getElementById('npOffice').value = ''; }
        document.getElementById('npCityRef').value = '';
        document.getElementById('otherAddress').value = '';
        return;
      }
      var delivery = d.delivery === 'other' ? 'other' : 'np';
      chips.forEach(function(c){
        var on = c.querySelector('input').value === delivery;
        c.classList.toggle('active', on);
        c.querySelector('input').checked = on;
      });
      showFor(delivery);
      if (npWidget) npWidget.apply(d.city, d.ref, d.office);
      else { document.getElementById('npCity').value = d.city || ''; document.getElementById('npOffice').value = d.office || ''; }
      document.getElementById('npCityRef').value = d.ref || '';
      document.getElementById('otherAddress').value = d.address || '';
    });
  });

  // Промокод: перевіряємо окремим запитом і одразу показуємо, що саме дала
  // знижка. Без цього людина дізнавалась про долю коду вже після підтвердження
  // замовлення — тобто коли міняти щось пізно.
  var promoInput = document.getElementById('promoInput'), promoBtn = document.getElementById('promoBtn');
  var promoHint = document.getElementById('promoHint');
  function applyPromo(code){
    promoBtn.disabled = true;
    promoHint.className = 'field-hint';
    promoHint.textContent = 'Перевіряємо…';
    var body = new URLSearchParams({_csrf: '<?= e(Csrf::token()) ?>', promo_code: code});
    fetch('<?= e(url('/checkout/promo')) ?>', {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function(r){ return r.json() }).then(function(d){
        promoBtn.disabled = false;
        promoBtn.textContent = d.ok ? 'Прибрати' : 'Застосувати';
        promoHint.className = 'field-hint' + (d.ok ? ' is-ok' : (d.empty ? '' : ' is-bad'));
        promoHint.textContent = d.ok ? '✓ ' + d.label + ' — мінус ' + d.discount
          : (d.empty ? '' : '✕ Такий промокод не діє — перевірте написання або строк дії');
        // ціни позицій: стара лишається закресленою, щоб знижку було видно
        document.querySelectorAll('.co-item').forEach(function(el){
          var it = d.items[el.dataset.key];
          if (!it) return;
          var old = el.querySelector('.co-old'), now = el.querySelector('.co-now');
          now.textContent = it.sum;
          if (it.old) {
            if (!old) { old = document.createElement('s'); old.className = 'co-old'; now.parentNode.insertBefore(old, now); }
            old.textContent = it.old;
          } else if (old) old.remove();
        });
        document.getElementById('sumSubtotal').textContent = d.subtotal;
        document.getElementById('sumTotal').textContent = d.total;
        var bar = document.getElementById('barTotal');
        if (bar) bar.textContent = d.total;
        document.getElementById('sumDiscountRow').style.display = d.ok ? '' : 'none';
        document.getElementById('sumDiscountLabel').textContent = (d.label || 'Знижка') + ':';
        document.getElementById('sumDiscount').textContent = '−' + d.discount;
      }).catch(function(){
        promoBtn.disabled = false;
        promoHint.className = 'field-hint is-bad';
        promoHint.textContent = 'Не вдалося перевірити код — спробуйте ще раз';
      });
  }
  if (promoBtn) {
    promoBtn.addEventListener('click', function(){
      // «Прибрати» — це та сама перевірка з порожнім кодом
      if (promoBtn.textContent === 'Прибрати') promoInput.value = '';
      applyPromo(promoInput.value.trim());
    });
    promoInput.addEventListener('keydown', function(e){
      if (e.key === 'Enter') { e.preventDefault(); applyPromo(promoInput.value.trim()); }
    });
    // правка коду руками скасовує попередній результат: показані суми більше
    // не відповідають тому, що в полі
    promoInput.addEventListener('input', function(){
      promoBtn.textContent = 'Застосувати';
      promoHint.className = 'field-hint';
      promoHint.textContent = 'Натисніть «Застосувати», щоб перевірити код';
    });
  }

  // галка розсилки має сенс лише коли вказано email
  var em = document.getElementById('orderEmail'), nlRow = document.getElementById('newsletterRow');
  if (em && nlRow) {
    em.addEventListener('input', function(){
      nlRow.style.display = em.value.trim() ? '' : 'none';
      if (!em.value.trim()) nlRow.querySelector('input').checked = false;
    });
  }
  // самовивіз: показуємо, чого бракує в обраному магазині
  var store = document.getElementById('pickupStore'), note = document.getElementById('pickupNote');
  if (store && note) {
    store.addEventListener('change', function () {
      var opt = store.options[store.selectedIndex];
      var miss = opt ? (opt.dataset.missing || '') : '';
      note.textContent = miss ? 'У цьому магазині зараз немає: ' + miss + '. Замовлення приймемо — узгодимо строки.' : '';
    });
  }
})();
</script>
