<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <div class="kicker">Оформлення</div>
    <h2>Дані для доставки</h2>
    <form method="post" action="<?= e(url('/checkout/submit')) ?>" style="margin-top:30px" id="checkoutForm">
      <?= Csrf::field() ?>
      <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden">
        <label>Не заповнюйте це поле<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>
      <div class="form-grid">
        <div class="field"><label>Отримувач *</label><input type="text" name="name" value="<?= e($pre['name']) ?>" required placeholder="Ім'я та прізвище"></div>
        <div class="field"><label>Телефон *</label><input type="tel" name="phone" value="<?= e($pre['phone']) ?>" required placeholder="+380 __ ___ ____"></div>
        <div class="field"><label>Email <span class="dim">(необовʼязково)</span></label><input type="email" name="email" id="orderEmail" value="<?= e($pre['email']) ?>" placeholder="для підтвердження замовлення"></div>
      </div>

      <label class="checkbox" id="newsletterRow" style="align-items:flex-start;margin-bottom:18px;<?= $pre['email'] === '' ? 'display:none' : '' ?>">
        <input type="checkbox" name="newsletter" value="1" style="margin-top:3px"<?= $subscribed ? ' checked' : '' ?>>
        <span>Хочу отримувати новини та акції на цей email. Відписатись можна будь-коли — посиланням у листі або в профілі.</span>
      </label>

      <div class="field">
        <label>Спосіб доставки</label>
        <div class="variants" id="deliveryChips">
          <label class="chip active"><input type="radio" name="delivery" value="np" checked hidden>Нова Пошта</label>
          <label class="chip"><input type="radio" name="delivery" value="pickup" hidden>Самовивіз з магазину</label>
          <label class="chip"><input type="radio" name="delivery" value="other" hidden>Інше (узгодимо)</label>
        </div>
      </div>

      <div id="npFields">
        <div class="form-grid">
          <div class="field"><label>Місто</label><input type="text" name="city" id="npCity" placeholder="Почніть вводити місто…" autocomplete="off" list="npCityList"><datalist id="npCityList"></datalist></div>
          <div class="field"><label>Відділення / поштомат</label><input type="text" name="np_office" id="npOffice" placeholder="Номер, вулиця або «поштомат»" list="npOfficeList" autocomplete="off"><datalist id="npOfficeList"></datalist></div>
        </div>
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

      <div id="otherFields" style="display:none">
        <div class="field"><label>Адреса / побажання</label><input type="text" name="address" placeholder="Опишіть, як вам зручно отримати"></div>
      </div>

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
<script>
(function(){
  var chips = document.querySelectorAll('#deliveryChips .chip');
  var np = document.getElementById('npFields'), pk = document.getElementById('pickupFields'), ot = document.getElementById('otherFields');
  chips.forEach(function(ch){
    ch.addEventListener('click', function(){
      chips.forEach(function(c){c.classList.remove('active')});
      ch.classList.add('active');
      var v = ch.querySelector('input').value;
      np.style.display = v === 'np' ? '' : 'none';
      pk.style.display = v === 'pickup' ? '' : 'none';
      ot.style.display = v === 'other' ? '' : 'none';
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
  // Автопідказки Нової Пошти (працюють за наявності API-ключа)
  var enabled = <?= $np_enabled ? 'true' : 'false' ?>;
  if (enabled) {
    var cityInput = document.getElementById('npCity'), cityList = document.getElementById('npCityList');
    var offInput = document.getElementById('npOffice'), offList = document.getElementById('npOfficeList');
    var cityUrl = '<?= e(url('/api/np/cities')) ?>', whUrl = '<?= e(url('/api/np/warehouses')) ?>';
    // ref міст накопичуємо, а не перечитуємо зі списку підказок: після вибору
    // міста НП на його ж повну назву відповідає іншим рядком («м. Кривий Ріг,
    // Дніпропетровська обл.» → «…, Криворізький р-н, …»), збіг не знаходився,
    // ref лишався порожнім — і відділення не завантажувались ніколи
    var refByLabel = {}, cityRef = '', tCity, tOff;

    function fillList(list, values) {
      list.innerHTML = '';
      values.forEach(function(v){ var o = document.createElement('option'); o.value = v; list.appendChild(o); });
    }
    function pickCity() {
      var ref = refByLabel[cityInput.value.trim()] || '';
      if (ref === cityRef) return;
      cityRef = ref;
      offInput.value = '';           // відділення старого міста в новому не існує
      fillList(offList, []);
      if (cityRef) loadOffices('');
    }
    function loadOffices(q) {
      var ref = cityRef;
      fetch(whUrl + '?city=' + encodeURIComponent(ref) + '&q=' + encodeURIComponent(q))
        .then(function(r){return r.json()}).then(function(d){
          if (ref !== cityRef) return; // місто вже змінили — відповідь застаріла
          fillList(offList, d.items);
        }).catch(function(){});
    }

    cityInput.addEventListener('input', function(){
      clearTimeout(tCity);
      var q = cityInput.value.trim();
      if (refByLabel[q]) { pickCity(); return; }
      tCity = setTimeout(function(){
        fetch(cityUrl + '?q=' + encodeURIComponent(q))
          .then(function(r){return r.json()}).then(function(d){
            d.items.forEach(function(it){ refByLabel[it.label] = it.ref; });
            fillList(cityList, d.items.map(function(it){ return it.label }));
            pickCity();
          }).catch(function(){});
      }, 300);
    });
    cityInput.addEventListener('change', pickCity);

    // великі міста мають тисячі точок, тому список звужує сама НП за введеним
    // текстом: «12», «Хрещатик» чи «поштомат» однаково працюють
    offInput.addEventListener('input', function(){
      if (!cityRef) return;
      clearTimeout(tOff);
      var q = offInput.value.trim();
      tOff = setTimeout(function(){ loadOffices(q); }, 300);
    });
    offInput.addEventListener('focus', function(){
      if (cityRef && !offList.children.length) loadOffices(offInput.value.trim());
    });
  }
})();
</script>
