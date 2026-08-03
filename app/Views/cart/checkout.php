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
<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <div class="kicker">Оформлення</div>
    <h2>Дані для доставки</h2>
    <form method="post" action="<?= e(url('/checkout/submit')) ?>" style="margin-top:30px" id="checkoutForm">
      <?= Csrf::field() ?>
      <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden">
        <label>Не заповнюйте це поле<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <?php if ($pre['name'] !== '' || $pre['phone'] !== ''): ?>
        <!-- Отримувача не підставляємо мовчки: посилку часто відправляють іншій
             людині, а Нова Пошта видає її лише тому, чиї дані в накладній -->
        <label class="checkbox" id="meRow" style="margin-bottom:14px">
          <input type="checkbox" id="meRecipient">
          <span>Я отримувач — підставити моє ім'я і телефон</span>
        </label>
      <?php endif; ?>

      <div class="form-grid">
        <div class="field"><label>Отримувач *</label><input type="text" name="name" id="ordName" value="" required placeholder="Ім'я та прізвище"></div>
        <div class="field"><label>Телефон отримувача *</label><input type="tel" name="phone" id="ordPhone" value="" required placeholder="+380 __ ___ ____"></div>
        <div class="field"><label>Email <span class="dim">(необовʼязково)</span></label><input type="email" name="email" id="orderEmail" value="<?= e($pre['email']) ?>" placeholder="для підтвердження замовлення"></div>
      </div>

      <label class="checkbox" id="newsletterRow" style="align-items:flex-start;margin-bottom:18px;<?= $pre['email'] === '' ? 'display:none' : '' ?>">
        <input type="checkbox" name="newsletter" value="1" style="margin-top:3px"<?= $subscribed ? ' checked' : '' ?>>
        <span>Хочу отримувати новини та акції на цей email. Відписатись можна будь-коли — посиланням у листі або в профілі.</span>
      </label>

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
          <p class="dim" style="margin:8px 0 0">Адреси зберігаються без отримувача — його вказуєте щоразу.
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
          <div class="field"><label>Місто</label><input type="text" name="city" id="npCity" value="<?= e($selCity) ?>" placeholder="Почніть вводити місто…" autocomplete="off"></div>
          <div class="field"><label>Відділення / поштомат</label><input type="text" name="np_office" id="npOffice" value="<?= e($selOffice) ?>" placeholder="Номер, вулиця або «поштомат»" autocomplete="off"></div>
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

      <div class="field"><label>Коментар</label><textarea name="comment" rows="3" placeholder="Необов'язково"></textarea></div>

      <div class="form-grid">
        <div class="field"><label>Промокод</label><input type="text" name="promo_code" value="<?= e($promo['code'] ?? '') ?>" placeholder="Напр. MED10"></div>
      </div>

      <div class="totals" style="max-width:100%;margin:10px 0 26px">
        <div class="row"><span class="muted">Товари:</span><span><?= e(price_fmt($totals['subtotal'])) ?></span></div>
        <?php if ($totals['discount'] > 0): ?>
          <div class="row"><span class="muted">Знижка (<?= e($promo['code']) ?>):</span><span>−<?= e(price_fmt($totals['discount'])) ?></span></div>
        <?php endif; ?>
        <div class="row grand"><span>До сплати:</span><span><?= e(price_fmt($totals['total'])) ?></span></div>
      </div>
      <p class="dim" style="margin-bottom:18px">Оплата при отриманні або за домовленістю — продавець зв'яжеться з вами для підтвердження.</p>
      <button class="btn btn-gold" type="submit" style="width:100%">Підтвердити замовлення</button>
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
