<?php /* Підказки міст, відділень і вулиць Нової Пошти. Підключається один раз на
        сторінці; поля прив'язуються викликом npAutocomplete({...}) — так віджет
        живе і в checkout, і в кабінеті, і в налаштуваннях, не роздвоюючись. */ ?>
<script>
window.npAutocomplete = function (opt) {
  var cityInput = document.getElementById(opt.city), offInput = document.getElementById(opt.office);
  var refInput = opt.ref ? document.getElementById(opt.ref) : null;
  var offRefInput = opt.officeRef ? document.getElementById(opt.officeRef) : null;
  var streetInput = opt.street ? document.getElementById(opt.street) : null;
  var streetRefInput = opt.streetRef ? document.getElementById(opt.streetRef) : null;
  if (!cityInput || !offInput) return null;
  var cityUrl = '<?= e(url('/api/np/cities')) ?>',
      whUrl = '<?= e(url('/api/np/warehouses')) ?>',
      stUrl = '<?= e(url('/api/np/streets')) ?>';
  var cityRef = (refInput && refInput.value) || '';

  /**
   * Власний список замість <datalist>: той відкривається на розсуд браузера і
   * щойно завантажені варіанти показує лише після наступного натискання —
   * зовні це виглядає так, ніби підказок немає взагалі.
   */
  function dropdown(input, onPick) {
    var wrap = input.parentNode;
    wrap.classList.add('np-wrap');
    var box = document.createElement('div');
    box.className = 'np-drop';
    wrap.appendChild(box);
    var items = [], active = -1;

    function close() { box.classList.remove('is-open'); active = -1; }
    function note(text) {
      box.innerHTML = '';
      var d = document.createElement('div');
      d.className = 'np-note'; d.textContent = text;
      box.appendChild(d);
      box.classList.add('is-open');
    }
    /** values — [{ref, label}]: підпис бачить людина, ref іде в приховане поле */
    function show(values) {
      items = values; active = -1; box.innerHTML = '';
      if (!values.length) { note('Нічого не знайдено'); return; }
      values.forEach(function (v, i) {
        var d = document.createElement('div');
        d.textContent = v.label;
        // mousedown, а не click: клік приходить уже після blur, коли список закрито
        d.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
        box.appendChild(d);
      });
      box.classList.add('is-open');
    }
    function pick(i) {
      if (i < 0 || i >= items.length) return;
      input.value = items[i].label;
      close();
      onPick(items[i]);
    }
    function move(step) {
      var els = box.querySelectorAll('div:not(.np-note)');
      if (!els.length) return;
      if (active >= 0) els[active].classList.remove('is-active');
      active = (active + step + els.length) % els.length;
      els[active].classList.add('is-active');
      els[active].scrollIntoView({block: 'nearest'});
    }
    input.addEventListener('keydown', function (e) {
      if (!box.classList.contains('is-open')) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(active); }
      else if (e.key === 'Escape') close();
    });
    input.addEventListener('blur', function () { setTimeout(close, 120); });
    return {show: show, note: note, close: close};
  }

  function setRef(ref) {
    cityRef = ref;
    if (refInput) refInput.value = ref;
  }
  /**
   * Ref відділення живе рівно доти, доки в полі стоїть обраний із довідника
   * рядок. Дописав людина букву — ref стирається: краще попросити обрати
   * зі списку ще раз, ніж створити накладну на сусіднє відділення.
   */
  function setOfficeRef(ref) { if (offRefInput) offRefInput.value = ref || ''; }
  function setStreetRef(ref) { if (streetRefInput) streetRefInput.value = ref || ''; }

  // ref міст тримаємо в мапі, а не перечитуємо повторним пошуком: на повну
  // назву НП відповідає іншим рядком («м. Кривий Ріг, Дніпропетровська обл.»
  // → «…, Криворізький р-н, …»), і місто ставало «не обраним»
  var cityRefByLabel = {}, tCity, tOff, tStreet;
  if (cityRef && cityInput.value) cityRefByLabel[cityInput.value.trim()] = cityRef;

  var cityDrop = dropdown(cityInput, function (item) {
    setRef(item.ref);
    offInput.value = '';               // відділення старого міста в новому не існує
    setOfficeRef('');
    if (streetInput) { streetInput.value = ''; setStreetRef(''); }
    loadOffices('', true);
    offInput.focus();
  });
  var offDrop = dropdown(offInput, function (item) { setOfficeRef(item.ref); });

  cityInput.addEventListener('input', function () {
    clearTimeout(tCity);
    setRef('');                        // місто ще не обрано зі списку
    setOfficeRef('');
    var q = cityInput.value.trim();
    if (q.length < 2) { cityDrop.note('Введіть щонайменше дві літери назви міста'); return; }
    cityDrop.note('Шукаємо…');
    tCity = setTimeout(function () {
      fetch(cityUrl + '?q=' + encodeURIComponent(q))
        .then(function (r) { return r.json() }).then(function (d) {
          d.items.forEach(function (it) { cityRefByLabel[it.label] = it.ref; });
          cityDrop.show(d.items);
        }).catch(function () { cityDrop.note('Не вдалося звʼязатися з Новою Поштою'); });
    }, 250);
  });
  cityInput.addEventListener('focus', function () {
    if (cityInput.value.trim().length < 2) cityDrop.note('Введіть щонайменше дві літери назви міста');
  });

  // великі міста мають тисячі точок (у Києві 7700+), тому список звужує сама
  // НП за введеним текстом: «12», «Хрещатик» чи «поштомат» однаково працюють
  function loadOffices(q, silent) {
    var ref = cityRef;
    if (!ref) { offDrop.note('Спершу оберіть місто зі списку'); return; }
    if (!silent) offDrop.note('Шукаємо…');
    fetch(whUrl + '?city=' + encodeURIComponent(ref) + '&q=' + encodeURIComponent(q))
      .then(function (r) { return r.json() }).then(function (d) {
        if (ref !== cityRef) return;   // місто вже змінили — відповідь застаріла
        offDrop.show(d.items);
      }).catch(function () { offDrop.note('Не вдалося звʼязатися з Новою Поштою'); });
  }
  offInput.addEventListener('input', function () {
    clearTimeout(tOff);
    setOfficeRef('');
    var q = offInput.value.trim();
    tOff = setTimeout(function () { loadOffices(q); }, 250);
  });
  offInput.addEventListener('focus', function () { loadOffices(offInput.value.trim()); });

  // Вулиця — лише для доставки курʼєром. Поля може й не бути: у налаштуваннях
  // відправника адреса не потрібна, там відправляють із відділення.
  var streetDrop = streetInput ? dropdown(streetInput, function (item) { setStreetRef(item.ref); }) : null;
  if (streetInput && streetDrop) {
    streetInput.addEventListener('input', function () {
      clearTimeout(tStreet);
      setStreetRef('');
      var q = streetInput.value.trim();
      if (!cityRef) { streetDrop.note('Спершу оберіть місто зі списку'); return; }
      if (q.length < 2) { streetDrop.note('Введіть щонайменше дві літери назви вулиці'); return; }
      streetDrop.note('Шукаємо…');
      tStreet = setTimeout(function () {
        fetch(stUrl + '?city=' + encodeURIComponent(cityRef) + '&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json() }).then(function (d) { streetDrop.show(d.items); })
          .catch(function () { streetDrop.note('Не вдалося звʼязатися з Новою Поштою'); });
      }, 250);
    });
  }

  return {
    /** Підставити збережену адресу: ref відомий, тож підказки працюють одразу */
    apply: function (city, ref, office, officeRef, street, streetRef) {
      cityInput.value = city || '';
      offInput.value = office || '';
      if (city && ref) cityRefByLabel[String(city).trim()] = ref;
      setRef(ref || '');
      setOfficeRef(officeRef || '');
      if (streetInput) { streetInput.value = street || ''; setStreetRef(streetRef || ''); }
      cityDrop.close(); offDrop.close();
      if (streetDrop) streetDrop.close();
    }
  };
};
</script>
