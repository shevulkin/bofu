// Каса: набір чека без перезавантажень.
//
// Усі рішення лишаються на сервері — ціни, залишки, ліміти й знижки рахує він
// (Pos, Cart, Promo). Тут тільки те, без чого продаж був би незручним: відправити
// дію, перемалювати чек і показати відповідь. Другої копії ціноутворення в
// браузері немає навмисно: вона неминуче розійшлася б із серверною.
(function () {
  var form = document.getElementById('posForm');
  if (!form || !window.POS) return;

  var linesEl = document.getElementById('posLines');
  var emptyEl = document.getElementById('posEmpty');
  var totalEl = document.getElementById('posTotal');
  var msgEl = document.getElementById('posMsg');
  var scanEl = document.getElementById('posScan');
  var camBtn = document.getElementById('posCam');
  var phoneEl = document.getElementById('posPhone');
  var nameEl = document.getElementById('posName');
  var notes = form.querySelectorAll('[data-pos-note]');
  var storeSel = form.querySelector('[data-pos-store]') || document.querySelector('[data-pos-store]');

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function say(text, bad) {
    if (!msgEl) return;
    msgEl.textContent = text || '';
    msgEl.style.color = bad ? 'var(--danger2)' : 'var(--gold2)';
  }

  /** Відправити дію каси й перемалювати чек */
  function act(action, extra) {
    var data = new FormData(form);
    data.set('_action', action);
    Object.keys(extra || {}).forEach(function (k) { data.set(k, extra[k]); });
    return fetch(POS.postUrl, {
      method: 'POST', body: data,
      headers: { 'X-CSRF-Token': POS.csrf, 'X-Requested-With': 'fetch' }
    }).then(function (r) { return r.json(); }).then(function (d) {
      render(d);
      return d;
    }).catch(function () { say('Не вдалося звʼязатися з сервером — спробуйте ще раз', true); });
  }

  function render(d) {
    if (!d || !d.lines) return;
    linesEl.innerHTML = d.lines.map(function (l) {
      return '<tr><td>' + esc(l.title) +
        (l.variant_name ? '<div class="dim">' + esc(l.variant_name) + '</div>' : '') + '</td>' +
        '<td class="num" style="white-space:nowrap">' +
        '<button type="button" class="pos-qty-btn" data-pos-qty="' + esc(l.key) + '" data-to="' + (l.qty - 1) + '">−</button>' +
        '<b>' + l.qty + '</b>' +
        '<button type="button" class="pos-qty-btn" data-pos-qty="' + esc(l.key) + '" data-to="' + (l.qty + 1) + '">+</button></td>' +
        '<td class="num">' + esc(l.sum_label) + '</td>' +
        '<td><button type="button" class="btn btn-line btn-xs" data-pos-qty="' + esc(l.key) + '" data-to="0">×</button></td></tr>';
    }).join('');
    if (emptyEl) emptyEl.hidden = d.lines.length > 0;
    if (totalEl) totalEl.textContent = d.total_label;
    if (d.error) say(d.error, true);
    else if (d.added) say(d.added + ' — додано');

    // Покупець приходить у кожній відповіді — і рядок стану, і сам номер:
    // сервер повертає його нормалізованим, тож продавець бачить рівно те, що
    // запишеться в замовлення. Помилковий номер поле не переписує: людині
    // треба виправляти те, що вона набрала, а не вгадувати, що з нього вийшло.
    if (!d.error && d.phone !== undefined && phoneEl && document.activeElement !== phoneEl) {
      phoneEl.value = d.phone;
    }
    if (!d.error && d.name && nameEl && nameEl.value.trim() === '') nameEl.value = d.name;
    if (phoneEl) phoneEl.classList.toggle('is-bad', !!d.error);
    if (d.customer_note !== undefined) {
      var state = d.error ? 'bad' : d.customer_state;
      Array.prototype.forEach.call(notes, function (el) {
        el.className = 'pos-who is-' + state;
        var icon = el.querySelector('[data-pos-icon]');
        var text = el.querySelector('[data-pos-text]');
        if (icon) icon.textContent = d.error ? '!' : d.customer_icon;
        if (text) text.textContent = d.error ? d.error : d.customer_note;
      });
    }
  }

  // ── плитка й чек ────────────────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    var tile = e.target.closest('[data-pos-add]');
    if (tile) {
      act('add', { product_id: tile.getAttribute('data-product'), variant_id: tile.getAttribute('data-variant') });
      // Курсор повертається у поле сканера: продавець із сканером у руках не
      // має щоразу клікати в нього мишею після тапу по плитці.
      if (scanEl) scanEl.focus();
      return;
    }
    var qty = e.target.closest('[data-pos-qty]');
    if (qty) act('qty', { key: qty.getAttribute('data-pos-qty'), qty: qty.getAttribute('data-to') });
  });

  // ── сканер і пошук ──────────────────────────────────────────────────────
  // Одне поле на дві дії. Сканер набирає код за мить і тисне Enter — це скан.
  // Людина друкує повільно й Enter не тисне — це пошук.
  if (scanEl) {
    var box = document.createElement('div');
    box.className = 'np-drop';
    scanEl.parentNode.appendChild(box);
    var found = [], timer;

    function close() { box.classList.remove('is-open'); }
    function note(text) {
      box.innerHTML = '<div class="np-note">' + esc(text) + '</div>';
      box.classList.add('is-open');
    }
    function show(items) {
      found = items;
      if (!items.length) { note('Нічого не знайдено'); return; }
      box.innerHTML = '';
      items.forEach(function (it, i) {
        var d = document.createElement('div');
        var stock = it.stock > 0 ? it.stock + ' шт.' : (it.made_to_order ? 'під замовлення' : 'немає');
        d.innerHTML = '<span>' + esc(it.title) +
          (it.variant_name ? ' <span class="dim">· ' + esc(it.variant_name) + '</span>' : '') + '</span>' +
          '<span class="dim" style="float:right">' + esc(it.price_label) + ' · ' + esc(stock) + '</span>';
        // mousedown, а не click: клік приходить уже після blur, коли список закрито
        d.addEventListener('mousedown', function (ev) {
          ev.preventDefault();
          act('add', { product_id: found[i].product_id, variant_id: found[i].variant_id });
          scanEl.value = ''; close(); scanEl.focus();
        });
        box.appendChild(d);
      });
      box.classList.add('is-open');
    }

    scanEl.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Enter') return;
      e.preventDefault();               // Enter у касі не оформлює замовлення
      clearTimeout(timer);
      close();
      var code = scanEl.value.trim();
      if (code === '') return;
      scanEl.value = '';
      act('scan', { code: code });
    });

    scanEl.addEventListener('input', function () {
      clearTimeout(timer);
      var q = scanEl.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        fetch(POS.searchUrl + '?q=' + encodeURIComponent(q) +
              '&store_id=' + encodeURIComponent(storeSel ? storeSel.value : ''))
          .then(function (r) { return r.json(); })
          .then(function (d) { show(d.items || []); })
          .catch(function () { note('Не вдалося виконати пошук'); });
      }, 250);
    });
    scanEl.addEventListener('blur', function () { setTimeout(close, 150); });
  }

  // ── камера ──────────────────────────────────────────────────────────────
  // Саме вікно камери спільне з екраном кодів (scan.js): каса лишає його
  // відкритим і сканує товар за товаром, поки продавець не натисне «Готово».
  if (camBtn) camBtn.addEventListener("click", function () {
    if (!window.BofuScan) { say("Модуль сканування не завантажився — оновіть сторінку", true); return; }
    window.BofuScan.open(function (code, ui) {
      ui.say("Код " + code + " — шукаємо…");
      act("scan", { code: code }).then(function (d) {
        if (!d) return;
        // Код прочитано (високий «пік» уже прозвучав), але товару за ним немає —
        // це друга новина, і вона має звучати інакше, інакше продавець піде
        // далі, впевнений, що позиція в чеку.
        if (d.error) ui.beep(false);
        ui.say(d.error ? d.error : (d.added ? d.added + " — додано" : "Готово"));
      });
    }, {
      onError: function (m) { say(m, true); },
      onManual: function () { if (scanEl) { scanEl.focus(); say("Наберіть код цифрами з-під смужок"); } }
    });
  });

  // ── покупець ────────────────────────────────────────────────────────────
  // Номер перевіряє сервер — той самий AuthTokens::normPhoneAny, що вирішує
  // при оформленні й при вході на сайт. Другої, javascript-ної перевірки тут
  // немає навмисно: вона неминуче розійшлася б із серверною, і продавець
  // побачив би зелене поле там, де замовлення потім не збережеться.
  var lastLookup = null;

  function lookup(force) {
    var value = phoneEl ? phoneEl.value.trim() : '';
    if (!force && value === lastLookup) return;   // те саме двічі не питаємо
    lastLookup = value;
    act('customer', {});
  }

  var findBtn = form.querySelector('[data-pos-find]');
  if (findBtn) findBtn.addEventListener('click', function () { lookup(true); });

  // Перевіряємо, щойно людина пішла з поля: чекати кнопки «Знайти» означало б,
  // що криву цифру знайдуть аж на оформленні — коли покупець уже пішов.
  if (phoneEl) {
    phoneEl.addEventListener('blur', function () { lookup(false); });
    // Почали правити — червона рамка вже не про те, що зараз у полі
    phoneEl.addEventListener('input', function () { phoneEl.classList.remove('is-bad'); });
    phoneEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); lookup(true); }
    });
  }
  if (nameEl) nameEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); lookup(true); }
  });

  // ── магазин ─────────────────────────────────────────────────────────────
  // У точки свій цінник, своя акція і свій склад, тож зміна магазину
  // перемальовує весь екран — і плитку, і чек.
  if (storeSel) {
    storeSel.addEventListener('change', function () {
      if (form.requestSubmit) form.requestSubmit();
      else form.submit();
    });
  }

  // ── що показувати ───────────────────────────────────────────────────────
  var deliveryBox = form.querySelector('[data-pos-delivery]');
  var handedBox = form.querySelector('[data-pos-handed]');
  var npBox = form.querySelector('[data-pos-np]');
  var addrBox = form.querySelector('[data-pos-addr]');

  function pick(sel, def) {
    var on = form.querySelector(sel + ':checked');
    return on ? on.value : def;
  }

  function sync() {
    var offline = pick('[data-pos-source]', 'offline') === 'offline';
    // Продаж на місці — це завжди видача в точці: доставляти нікуди, товар
    // уже тут. Тому вибір способу доставки ховаємо, а не лишаємо як пастку.
    if (offline) {
      var pickup = form.querySelector('[data-pos-dlv][value="pickup"]');
      if (pickup) pickup.checked = true;
    }
    if (deliveryBox) deliveryBox.hidden = offline;
    var dlv = pick('[data-pos-dlv]', 'pickup');
    if (npBox) npBox.hidden = dlv !== 'np';
    if (addrBox) addrBox.hidden = dlv !== 'other';
    if (handedBox) handedBox.hidden = dlv !== 'pickup';
  }

  Array.prototype.forEach.call(form.querySelectorAll('[data-pos-source],[data-pos-dlv]'), function (el) {
    el.addEventListener('change', sync);
  });

  // «Товар віддано» йде за способом продажу, і саме при його зміні: у точці
  // товар у руках майже завжди, а телефоном його ще везти. Забута галка
  // закрила б замовлення, якого ніхто не виконував.
  Array.prototype.forEach.call(form.querySelectorAll('[data-pos-source]'), function (el) {
    el.addEventListener('change', function () {
      var box = handedBox && handedBox.querySelector('input[type=checkbox]');
      if (box) box.checked = el.value === 'offline';
    });
  });

  sync();

  if (window.npAutocomplete) {
    window.npAutocomplete({ city: 'npCity', office: 'npOffice', ref: 'npCityRef',
                            officeRef: 'npOfficeRef', street: 'npStreet', streetRef: 'npStreetRef' });
  }

  // Відділення чи курʼєр: показуємо тільки потрібні поля. Різниця не косметична —
  // курʼєрська накладна вимагає вулиці з довідника, а не адреси рядком.
  var npType = document.getElementById('posNpType');
  if (npType) {
    var syncNpType = function () {
      var courier = npType.value === 'courier';
      document.querySelectorAll('[data-pos-np-wh]').forEach(function (el) { el.hidden = courier; });
      document.querySelectorAll('[data-pos-np-courier]').forEach(function (el) { el.hidden = !courier; });
    };
    npType.addEventListener('change', syncNpType);
    syncNpType();
  }
})();
