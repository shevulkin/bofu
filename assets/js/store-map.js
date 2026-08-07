// Вибір координат магазину на карті.
//
// Пара чисел, скопійована з Google Maps, працює — але вимагає піти на інший
// сайт, знайти там своє місце, згадати про праву кнопку й повернутись. Тут те
// саме робиться на місці: відкрили карту, клацнули по своєму входу, закрили.
//
// Поле нікуди не дівається. Воно лишається і як спосіб вставити готову пару, і
// як єдине джерело правди: карта тільки пише в нього, а зберігає форма. Тому
// «Скасувати» справді скасовує — до збереження нічого не сталося.
(function () {
  var cfg = window.STORE_MAP;
  if (!cfg || !cfg.key || !window.BofuMap) return;

  var back = document.getElementById('storePicker');
  var host = document.getElementById('storePickerMap');
  var title = document.getElementById('storePickerTitle');
  var out = document.getElementById('storePickerVal');
  var apply = document.getElementById('storePickerApply');
  var clear = document.getElementById('storePickerClear');
  if (!back || !host) return;

  var target = null;      // поле, у яке повернемо координати
  var picked = null;      // {lat, lng} — обране в цьому вікні
  var mapCtl = null;

  function fmt(lat, lng) { return trim(lat) + ', ' + trim(lng); }
  function trim(n) { return String(Math.round(n * 1e7) / 1e7); }

  function show(lat, lng) {
    picked = (lat == null) ? null : { lat: lat, lng: lng };
    out.textContent = picked ? fmt(picked.lat, picked.lng) : 'Точку ще не обрано';
    apply.disabled = !picked;
  }

  /** Те, що вже стоїть у полі, — щоб карта відкрилась на ньому, а не з нуля */
  function current(input) {
    var m = String(input.value || '').match(/(-?\d+(?:\.\d+)?)[^\d-]+(-?\d+(?:\.\d+)?)/);
    if (!m) return null;
    var lat = parseFloat(m[1]), lng = parseFloat(m[2]);
    if (isNaN(lat) || isNaN(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
    return { lat: lat, lng: lng };
  }

  function close() { back.classList.remove('open'); target = null; }

  function open(input, name) {
    target = input;
    title.textContent = name ? 'Точка на карті: ' + name : 'Точка на карті';
    back.classList.add('open');

    var has = current(input);
    show(has ? has.lat : null, has ? has.lng : null);

    if (!mapCtl) {
      // Карту будуємо один раз на сторінку: кожне нове вікно інакше
      // перезавантажувало б плитки й витрачало квоту на те саме місце
      mapCtl = window.BofuMap.pick(host, {
        key: cfg.key,
        lat: has ? has.lat : null,
        lng: has ? has.lng : null,
        fallback: cfg.fallback,
        onMove: function (lat, lng) { show(lat, lng); }
      });
    } else {
      // Карта щойно була в прихованому блоці, тобто нульового розміру —
      // перш ніж вести її кудись, хай перевимірює себе
      mapCtl.refresh();
      if (has) mapCtl.focus(has.lat, has.lng);
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-store-pick]');
    if (btn) {
      e.preventDefault();
      var cell = btn.closest('td') || btn.parentNode;
      open(cell.querySelector('input[type=text]'), btn.getAttribute('data-store-pick'));
      return;
    }
    // Клік повз вікно закриває його: те саме, що «Скасувати»
    if (e.target === back) close();
  });

  apply.addEventListener('click', function () {
    if (target && picked) {
      target.value = fmt(picked.lat, picked.lng);
      // Сторінка стежить за незбереженими змінами — хай побачить і цю
      target.dispatchEvent(new Event('input', { bubbles: true }));
    }
    close();
  });

  clear.addEventListener('click', function () {
    if (target) { target.value = ''; target.dispatchEvent(new Event('input', { bubbles: true })); }
    close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && back.classList.contains('open')) close();
  });
})();
