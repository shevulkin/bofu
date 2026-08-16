<?php
  // Обрана за замовчуванням збережена адреса підставляється просто в поля —
  // так вона працює й без JS, і покупцеві нічого не треба натискати
  $selDelivery = $sel['delivery'] ?? 'np';
  $selCity = $sel['city'] ?? '';
  $selRef = $sel['city_ref'] ?? '';
  $selOffice = $sel['np_office'] ?? '';
  $selOfficeRef = $sel['np_office_ref'] ?? '';
  $selType = ($sel['np_type'] ?? 'warehouse') === 'courier' ? 'courier' : 'warehouse';
  $selStreet = $sel['np_street'] ?? '';
  $selStreetRef = $sel['np_street_ref'] ?? '';
  $selHouse = $sel['np_house'] ?? '';
  $selFlat = $sel['np_flat'] ?? '';
  $selAddress = $sel['address'] ?? '';
  $canSave = !empty($auth_user);
  // «2 товари» читається як конкретна річ, «Ваше замовлення» — як абстракція
  $units = array_sum(array_map(fn($r) => (int)$r['qty'], $rows));
  $goods = $units . ' ' . ($units % 10 === 1 && $units % 100 !== 11 ? 'товар'
        : (in_array($units % 10, [2, 3, 4], true) && !in_array($units % 100, [12, 13, 14], true) ? 'товари' : 'товарів'));
  $saved = fn(array $t) => 'ви заощадили ' . price_fmt($t['discount']);
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
                         data-office-ref="<?= e($a['np_office_ref'] ?? '') ?>"
                         data-type="<?= e($a['np_type'] ?? 'warehouse') ?>"
                         data-street="<?= e($a['np_street'] ?? '') ?>" data-street-ref="<?= e($a['np_street_ref'] ?? '') ?>"
                         data-house="<?= e($a['np_house'] ?? '') ?>" data-flat="<?= e($a['np_flat'] ?? '') ?>"
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
          <?php /* Куди саме везти. Це не «спосіб доставки» (він вище), а вибір
                   усередині Нової Пошти: у відділення чи курʼєром додому. Різниця
                   для нас істотна — курʼєрська накладна вимагає вулиці з довідника,
                   а не просто адреси рядком. */ ?>
          <div class="field">
            <div class="variants" id="npTypeChips">
              <label class="chip<?= $selType === 'warehouse' ? ' active' : '' ?>"><input type="radio" name="np_type" value="warehouse"<?= $selType === 'warehouse' ? ' checked' : '' ?> hidden>У відділення або поштомат</label>
              <label class="chip<?= $selType === 'courier' ? ' active' : '' ?>"><input type="radio" name="np_type" value="courier"<?= $selType === 'courier' ? ' checked' : '' ?> hidden>Курʼєром на адресу</label>
            </div>
          </div>
          <div class="form-grid">
            <?php /* autocomplete="new-password" — єдине, що глушить автопідстановку адрес
                      у Chrome: "off" він для адресних полів свідомо ігнорує і накриває наш
                      список своїм. Ім'я поля теж не "city" — інакше евристика впізнає його
                      за назвою. data-* — те саме для менеджерів паролів. */ ?>
            <div class="field"><label>Місто</label><input type="text" name="np_city" id="npCity" value="<?= e($selCity) ?>" placeholder="Почніть вводити місто…" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
            <div class="field" id="npOfficeField"><label>Відділення / поштомат</label><input type="text" name="np_office" id="npOffice" value="<?= e($selOffice) ?>" placeholder="Номер, вулиця або «поштомат»" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
          </div>
          <div class="form-grid" id="npCourierFields"<?= $selType === 'courier' ? '' : ' style="display:none"' ?>>
            <div class="field" style="grid-column:1/-1"><label>Вулиця</label><input type="text" name="np_street" id="npStreet" value="<?= e($selStreet) ?>" placeholder="Почніть вводити назву вулиці…" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false"></div>
            <div class="field"><label>Будинок</label><input type="text" name="np_house" id="npHouse" value="<?= e($selHouse) ?>" placeholder="12А" maxlength="20"></div>
            <div class="field"><label>Квартира <span class="dim">(якщо є)</span></label><input type="text" name="np_flat" id="npFlat" value="<?= e($selFlat) ?>" placeholder="45" maxlength="20"></div>
          </div>
          <?php /* Ref-и з довідника НП: людина бачить назви, а накладну потім
                   створюють саме за цими посиланнями */ ?>
          <input type="hidden" name="city_ref" id="npCityRef" value="<?= e($selRef) ?>">
          <input type="hidden" name="np_office_ref" id="npOfficeRef" value="<?= e($selOfficeRef) ?>">
          <input type="hidden" name="np_street_ref" id="npStreetRef" value="<?= e($selStreetRef) ?>">
        </div>

        <div id="pickupFields" style="display:none">
          <div class="field"><label>Магазин</label>
            <select name="store_id" id="pickupStore">
              <option value="">— оберіть магазин —</option>
              <?php foreach ($stores as $s): $sid = (int)$s['id']; $miss = $missing[$sid] ?? []; ?>
                <option value="<?= $sid ?>" data-missing="<?= e(implode("\n", $miss)) ?>">
                  <?= e($s['name'] . ($s['city'] ? ', ' . $s['city'] : '') . ($s['address'] ? ', ' . $s['address'] : '')) ?>
                  <?= $miss ? ' — немає частини позицій' : ' — все є в наявності' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="dim" id="pickupNote" style="margin:8px 0 0;white-space:pre-line"></p>
          </div>
          <?php /* Карта відповідає на те, чого список назв не каже: яка точка
                   ближча. Клік по мітці обирає магазин, вибір у списку — веде
                   карту до нього, тож обидва шляхи ведуть до одного поля.
                   Немає ключа чи координат — блок просто не виводиться, і
                   оформлення працює далі як працювало. */ ?>
          <?php if ($map_key && $map_points): ?>
            <div class="field">
              <div class="store-map" id="pickupMap"></div>
              <p class="dim" style="margin:8px 0 0;font-size:12.5px">Клацніть на мітку, щоб обрати цю точку.</p>
            </div>
          <?php endif; ?>
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
        <h3 class="co-step-h"><span class="co-num">3</span>Побажання <span class="dim" style="font-size:14px;font-family:var(--sans)">— необовʼязково</span></h3>
        <div class="field"><label>Коментар до замовлення</label>
          <textarea name="comment" rows="3" placeholder="Необовʼязково: зручний час дзвінка, побажання до пакування"></textarea></div>
      </div>
      </div><!-- /ліва колонка -->

      <aside class="co-sum">
       <details id="coFold" open>
        <summary class="co-sum-h">Ваше замовлення <span class="dim" style="font-size:14px">· <?= e($goods) ?></span>
          <b class="co-fold-total" id="foldTotal"><?= e(price_fmt($totals['total'])) ?></b></summary>
        <div class="co-items">
          <?php foreach ($rows as $r): $cut = Promo::cut((float)($r['sum'] ?? 0), $promo, Promo::ownPercent($r)); ?>
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

        <p style="margin:0 0 14px"<?= $promo ? ' hidden' : '' ?> id="promoToggleRow">
          <button type="button" class="co-promo-link" id="promoToggle">Маю промокод</button>
        </p>
        <div class="field" id="promoBox" style="margin-bottom:16px<?= $promo ? '' : ';display:none' ?>">
          <label>Промокод</label>
          <div class="co-promo">
            <input type="text" name="promo_code" id="promoInput" value="<?= e($promo['code'] ?? '') ?>" autocomplete="off" spellcheck="false">
            <button class="btn btn-line" type="button" id="promoBtn"><?= $promo ? 'Прибрати' : 'Застосувати' ?></button>
          </div>
          <?php $promoNote = Promo::note($promo, $rows); ?>
          <p class="field-hint<?= $promo ? ' is-ok' : '' ?>" id="promoHint">
            <?= $promo ? e('✓ Код ' . $promo['code'] . ' діє — ' . $saved($totals)
                           . ($promoNote !== '' ? '. ' . $promoNote : '')) : '' ?>
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

        <ul class="co-trust">
          <li>Оплата при отриманні або за домовленістю</li>
          <li>Продавець зателефонує, щоб підтвердити замовлення</li>
          <li>Нічого не спишеться зараз — це не оплата</li>
        </ul>
        <button class="btn btn-gold co-submit" type="submit" style="width:100%">Підтвердити замовлення</button>
        <p class="dim" style="margin:14px 0 0;font-size:12.5px">
          <a href="<?= e(url('/cart')) ?>">← Повернутись до кошика</a></p>
       </details>
      </aside>

      <!-- телефон: сума й кнопка завжди під рукою, без гортання через усю форму -->
      <div class="co-bar">
        <div class="co-bar-total"><span><?= e($goods) ?> · до сплати</span><b id="barTotal"><?= e(price_fmt($totals['total'])) ?></b></div>
        <button class="btn btn-gold" type="submit">Підтвердити</button>
      </div>
    </form>
  </div>
</section>
<?php if ($np_enabled) echo View::partial('partials/np_autocomplete'); ?>
<?php /* без defer: наш скрипт нижче звертається до BofuMap одразу */ ?>
<?php if ($map_key && $map_points): ?><script src="<?= e(asset_v('js/map.js')) ?>"></script><?php endif; ?>
<script>
(function(){
  var np = document.getElementById('npFields'), pk = document.getElementById('pickupFields'), ot = document.getElementById('otherFields');
  var saveRow = document.getElementById('saveAddrRow');
  var nameInput = document.getElementById('ordName'), phoneInput = document.getElementById('ordPhone');
  var me = <?= json_js(['name' => $pre['name'], 'phone' => $pre['phone']]) ?>;
  var npWidget = window.npAutocomplete
    ? window.npAutocomplete({city: 'npCity', office: 'npOffice', ref: 'npCityRef',
                             officeRef: 'npOfficeRef', street: 'npStreet', streetRef: 'npStreetRef'}) : null;

  // Відділення чи курʼєр: показуємо рівно ті поля, які потрібні обраному
  // способу. Приховане поле лишається заповненим — повернувшись до відділення,
  // людина побачить те, що вже обрала, а не порожнечу.
  var npTypeChips = document.querySelectorAll('#npTypeChips .chip');
  var courierFields = document.getElementById('npCourierFields');
  var officeField = document.getElementById('npOfficeField');
  function showNpType(v){
    if (courierFields) courierFields.style.display = v === 'courier' ? '' : 'none';
    if (officeField) officeField.style.display = v === 'courier' ? 'none' : '';
  }
  npTypeChips.forEach(function(ch){
    ch.addEventListener('click', function(){
      npTypeChips.forEach(function(c){ c.classList.remove('active'); c.querySelector('input').checked = false; });
      ch.classList.add('active');
      var i = ch.querySelector('input');
      i.checked = true;
      showNpType(i.value);
    });
  });
  function npType(){
    var on = document.querySelector('#npTypeChips input:checked');
    return on ? on.value : 'warehouse';
  }
  showNpType(npType());

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
        if (npWidget) npWidget.apply('', '', '', '', '', '');
        else { document.getElementById('npCity').value = ''; document.getElementById('npOffice').value = ''; }
        document.getElementById('npCityRef').value = '';
        document.getElementById('npOfficeRef').value = '';
        document.getElementById('npHouse').value = document.getElementById('npFlat').value = '';
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
      var type = d.type === 'courier' ? 'courier' : 'warehouse';
      npTypeChips.forEach(function(c){
        var on = c.querySelector('input').value === type;
        c.classList.toggle('active', on);
        c.querySelector('input').checked = on;
      });
      showNpType(type);
      if (npWidget) npWidget.apply(d.city, d.ref, d.office, d.officeRef, d.street, d.streetRef);
      else { document.getElementById('npCity').value = d.city || ''; document.getElementById('npOffice').value = d.office || ''; }
      document.getElementById('npCityRef').value = d.ref || '';
      document.getElementById('npOfficeRef').value = d.officeRef || '';
      document.getElementById('npHouse').value = d.house || '';
      document.getElementById('npFlat').value = d.flat || '';
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
    // телефон потрібен для коду «один раз на людину»: гостя ми впізнаємо лише
    // за номером, тож без нього ліміт перевірився б аж при підтвердженні
    var body = new URLSearchParams({_csrf: '<?= e(Csrf::token()) ?>', promo_code: code,
      phone: (phoneInput && phoneInput.value) || ''});
    fetch('<?= e(url('/checkout/promo')) ?>', {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function(r){ return r.json() }).then(function(d){
        promoBtn.disabled = false;
        promoBtn.textContent = d.ok ? 'Прибрати' : 'Застосувати';
        promoHint.className = 'field-hint' + (d.ok ? ' is-ok' : (d.empty ? '' : ' is-bad'));
        // «заощадили», а не «мінус»: те саме число, але як здобуток, а не як
        // бухгалтерська операція — і воно доречніше саме в мить рішення
        // причину пише сервер: «вже використаний» і «такого немає» — різні речі
        promoHint.textContent = d.ok
          ? '✓ Код ' + d.code + ' діє — ви заощадили ' + d.discount + (d.note ? '. ' + d.note : '')
          : (d.empty ? '' : '✕ ' + (d.error || 'Такий промокод не діє'));
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
        ['barTotal', 'foldTotal'].forEach(function(id){
          var el = document.getElementById(id);
          if (el) el.textContent = d.total;
        });
        document.getElementById('sumDiscountRow').style.display = d.ok ? '' : 'none';
        document.getElementById('sumDiscountLabel').textContent = (d.label || 'Знижка') + ':';
        document.getElementById('sumDiscount').textContent = '−' + d.discount;
      }).catch(function(){
        promoBtn.disabled = false;
        promoHint.className = 'field-hint is-bad';
        promoHint.textContent = 'Не вдалося перевірити код — спробуйте ще раз';
      });
  }
  // На телефоні картка згорнута: у розгорнутому стані вона відсувала першу
  // клітинку форми майже на екран. Атрибут open лишається в розмітці, тож без
  // JS усе видно як було.
  var fold = document.getElementById('coFold');
  if (fold) {
    var narrow = window.matchMedia('(max-width:1000px)');
    // стежимо за шириною, а не питаємо один раз: поворот телефона й зміна
    // розміру вікна мають повертати картку у відповідний стан
    var syncFold = function(){ narrow.matches ? fold.removeAttribute('open') : fold.setAttribute('open', ''); };
    syncFold();
    window.addEventListener('load', syncFold);
    narrow.addEventListener ? narrow.addEventListener('change', syncFold) : narrow.addListener(syncFold);
  }

  var promoToggle = document.getElementById('promoToggle');
  if (promoToggle) promoToggle.addEventListener('click', function(){
    document.getElementById('promoToggleRow').hidden = true;
    document.getElementById('promoBox').style.display = '';
    promoInput.focus();
  });
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

  // Пройдений крок позначаємо галкою: людині видно, скільки лишилось, і
  // незакритий крок помітний одразу, а не після відмови форми на кнопці.
  var steps = document.querySelectorAll('.co-step');
  function stepDone(i){
    var d = document.querySelector('#deliveryChips input:checked');
    var v = d ? d.value : 'np';
    if (i === 0) {
      if (v === 'np') {
        if (!document.getElementById('npCity').value.trim()) return false;
        return npType() === 'courier'
          ? !!(document.getElementById('npStreet').value.trim() && document.getElementById('npHouse').value.trim())
          : !!document.getElementById('npOffice').value.trim();
      }
      if (v === 'pickup') return !!(document.getElementById('pickupStore') || {}).value;
      return !!document.getElementById('otherAddress').value.trim();
    }
    if (i === 1) {
      return nameInput.value.trim().length >= 2 && phoneInput.value.replace(/\D/g, '').length >= 9;
    }
    return null;                       // третій крок необовʼязковий — його не «закривають»
  }
  function refreshSteps(){
    steps.forEach(function(st, i){
      var num = st.querySelector('.co-num'), done = stepDone(i);
      if (done === null) return;
      num.classList.toggle('is-done', done);
      num.textContent = done ? '✓' : String(i + 1);
    });
  }
  document.getElementById('checkoutForm').addEventListener('input', refreshSteps);
  document.getElementById('checkoutForm').addEventListener('click', function(){ setTimeout(refreshSteps, 0) });
  refreshSteps();

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
      // рядком на позицію: поруч із кожною видно, де її можна забрати натомість
      note.textContent = miss
        ? 'У цьому магазині зараз немає:\n' + miss + '\nЗамовлення приймемо й так — узгодимо строки.'
        : '';
    });
  }

  // Карта й список — два входи в те саме поле, тож ведуть одне одного: клік по
  // мітці обирає магазин у списку, вибір у списку веде карту до точки. Інакше
  // людина обрала б мітку й не зрозуміла, чи вибір узагалі зарахувався.
  var mapHost = document.getElementById('pickupMap');
  if (mapHost && store && window.BofuMap) {
    var ctl = window.BofuMap.render(mapHost, {
      key: <?= json_js($map_key) ?>,
      points: <?= json_js($map_points) ?>,
      onPick: function (id) {
        store.value = String(id);
        // change програмній зміні value браузер не шле — а примітку про
        // відсутні позиції має оновити саме він
        store.dispatchEvent(new Event('change'));
      }
    });
    if (ctl) store.addEventListener('change', function () { ctl.select(parseInt(store.value, 10) || 0); });
  }
})();
</script>
