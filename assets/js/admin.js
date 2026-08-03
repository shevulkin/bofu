// Адмінка: PWA + Web Push
(function () {
  var base = (window.BOFU && BOFU.base) || '/';

  // Service worker (PWA)
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(base.replace(/\/$/, '') + '/sw.js').catch(function(){});
  }

  // Кнопка встановлення PWA
  var deferred = null, btn = document.getElementById('pwaInstall');
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    if (btn) btn.style.display = 'block';
  });
  if (btn) btn.addEventListener('click', function () {
    if (deferred) { deferred.prompt(); deferred = null; btn.style.display = 'none'; }
  });

  // Web Push підписка (працює лише на HTTPS або localhost)
  var pushBtn = document.getElementById('pushEnable');
  if (pushBtn && 'PushManager' in window && (window.BOFU && BOFU.vapid)) {
    pushBtn.style.display = 'block';
    pushBtn.addEventListener('click', function () {
      Notification.requestPermission().then(function (perm) {
        if (perm !== 'granted') return;
        navigator.serviceWorker.ready.then(function (reg) {
          return reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64(BOFU.vapid)
          });
        }).then(function (sub) {
          return fetch(base.replace(/\/$/, '') + '/api/push/subscribe', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': (window.BOFU && BOFU.csrf) || ''
            },
            body: JSON.stringify(sub)
          });
        }).then(function () {
          pushBtn.textContent = '🔔 Пуші увімкнено';
        }).catch(function (e) { console.warn('push', e); });
      });
    });
  }
  // Налаштування: перевірка інтеграцій тим, що зараз у полях (нічого не зберігає)
  var checkBtn = document.getElementById('checkBtn');
  if (checkBtn) {
    var box = document.getElementById('checkResult');
    var icons = { ok: '✅', warn: '⚠️', bad: '❌', off: '—' };
    checkBtn.addEventListener('click', function () {
      var form = checkBtn.closest('form');
      var data = new FormData();
      // лише поля інтеграцій: перевірка не має відношення до перемикачів і текстів
      Array.prototype.forEach.call(form.querySelectorAll('[name^="text["]'), function (i) {
        data.append(i.name, i.value);
      });
      checkBtn.disabled = true;
      box.hidden = false;
      box.innerHTML = '<div class="dim">Питаємо…</div>';
      fetch(base.replace(/\/$/, '') + '/admin/settings/check', {
        method: 'POST', body: data,
        headers: { 'X-CSRF-Token': (window.BOFU && BOFU.csrf) || '' }
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res || !res.rows) throw new Error('bad response');
          box.innerHTML = '';
          res.rows.forEach(function (row) {
            var el = document.createElement('div');
            el.className = 'check-row is-' + row.state;
            var note = row.note ? '<div class="check-note">' + esc(row.note) + '</div>' : '';
            el.innerHTML = '<span class="check-icon">' + (icons[row.state] || '') + '</span>'
              + '<div><b>' + esc(row.name) + '</b> — ' + esc(row.text) + note + '</div>';
            box.appendChild(el);
          });
        })
        .catch(function () { box.innerHTML = '<div class="check-row is-bad">Не вдалося виконати перевірку. Спробуйте ще раз.</div>'; })
        .finally(function () { checkBtn.disabled = false; });
    });
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  // Налаштування: головний вимикач сповіщень гасить канальні. Стан галок не
  // чіпаємо — вимкнули головний, увімкнули назад, і все на місці (сервер їх
  // теж не переписує, поки головний вимкнений).
  var master = document.querySelector('[data-master]');
  if (master) {
    var group = document.querySelector('[data-group]');
    var note = group && group.querySelector('.toggle-group-note');
    var noChan = document.querySelector('[data-nochan]');
    var kids = document.querySelectorAll('[data-child]');
    function sync() {
      if (group) group.classList.toggle('is-off', !master.checked);
      if (note) note.hidden = master.checked;
      var any = false;
      Array.prototype.forEach.call(kids, function (c) {
        c.disabled = !master.checked;
        if (c.checked) any = true;
      });
      // головний увімкнено, а каналів жодного — виглядає налаштованим і мовчить
      if (noChan) noChan.hidden = !(master.checked && !any);
    }
    master.addEventListener('change', sync);
    Array.prototype.forEach.call(kids, function (c) { c.addEventListener('change', sync); });
  }

  // Користувачі: магазини існують лише разом із роллю продавця (Users::saveStores).
  // Знімають галку — селектор гасне й вибір скидається одразу, щоб було видно,
  // що доступ відкликається, а не «просто не збережеться».
  Array.prototype.forEach.call(document.querySelectorAll('[data-seller-role]'), function (role) {
    var form = role.form;
    if (!form) return;
    var boxes = form.querySelectorAll('[data-seller-stores]');
    var hint = form.querySelector('[data-seller-hint]');
    if (!boxes.length) return;
    role.addEventListener('change', function () {
      // міняємо ТЕКСТ, а не видимість: зникома підказка міняла висоту блоку,
      // і від кліку по ролі вся картка стрибала
      if (hint) hint.textContent = role.checked ? hint.dataset.on : hint.dataset.off;
      Array.prototype.forEach.call(boxes, function (b) {
        b.disabled = !role.checked;
        if (!role.checked) b.checked = false;
      });
    });
  });

  // Панель збереження: показує, що є незбережені зміни, і не дає піти мовчки.
  // Втратити півгодини правок у таблиці на сорок рядків — найдорожча помилка
  // в адмінці, і робиться вона одним кліком по пункту меню.
  Array.prototype.forEach.call(document.querySelectorAll('.admin-save'), function (bar) {
    var form = bar.closest('form');
    if (!form) return;
    var note = bar.querySelector('.admin-save-note');
    var saved = note ? note.textContent : '';
    var dirty = false;

    function mark() {
      if (dirty) return;
      dirty = true;
      bar.classList.add('is-dirty');
      if (note) note.textContent = 'Є незбережені зміни';
    }
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });

    // Видалення позначають галкою, а виконується воно аж при збереженні —
    // тому перепитуємо саме тут і називаємо поіменно, що зникне.
    form.addEventListener('submit', function (e) {
      var del = form.querySelectorAll('.js-del:checked');
      if (!del.length) return;
      var names = Array.prototype.map.call(del, function (box) {
        var row = box.closest('tr'), field = row && row.querySelector('input[type=text]');
        return field && field.value ? field.value : (row ? row.cells[0].textContent.trim() : '');
      }).filter(Boolean);
      var text = 'Буде видалено назавжди (' + del.length + '):\n· ' + names.join('\n· ') + '\n\nПродовжити?';
      if (!window.confirm(text)) { e.preventDefault(); dirty = true; }
    });
    if (note && saved === '') note.textContent = 'Усі зміни збережено';
  });

  function urlB64(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4);
    var b64 = (s + pad).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(b64), arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
})();
