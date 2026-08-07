// Карта точок продажу.
//
// Єдине місце, де згадується Google. Сторінки дають лише розмітку з даними
// (window.BOFU_MAP) і чекають на BofuMap.render — тож зміна постачальника карт
// коштує цей файл, а не всі екрани, де карта показується.
//
// Ключ живе в налаштуваннях, а не в коді: він прив'язаний до домену й у різних
// установок різний. Немає ключа — карта просто не малюється, а список адрес із
// кнопками «маршрут» лишається на місці. Тому сторінка ніколи не залежить від
// того, чи вдалося завантажити чужий скрипт.
(function () {
  var api = null;          // проміс завантаження, спільний на всі карти сторінки

  /** Скрипт Google завантажуємо один раз і лише тоді, коли карта справді потрібна */
  function load(key) {
    if (api) return api;
    api = new Promise(function (resolve, reject) {
      if (window.google && window.google.maps) { resolve(window.google.maps); return; }
      var cb = 'bofuMapReady';
      window[cb] = function () { resolve(window.google.maps); };
      var s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(key) +
              '&callback=' + cb + '&language=uk&region=UA&loading=async';
      s.async = true;
      s.onerror = function () { reject(new Error('script')); };
      document.head.appendChild(s);
    });
    return api;
  }

  /**
   * Мітки в межах екрана.
   *
   * Одна точка — просто центр і сталий масштаб: fitBounds на єдиній мітці
   * наблизив би так, що видно асфальт і жодного орієнтира навколо.
   */
  function frame(maps, map, points) {
    if (points.length === 1) {
      map.setCenter({ lat: points[0].lat, lng: points[0].lng });
      map.setZoom(15);
      return;
    }
    var b = new maps.LatLngBounds();
    points.forEach(function (p) { b.extend({ lat: p.lat, lng: p.lng }); });
    map.fitBounds(b, 48);
  }

  function card(p) {
    var h = '<div class="map-pop"><b>' + esc(p.name) + '</b>';
    if (p.address) h += '<div>' + esc(p.address) + '</div>';
    if (p.phone) h += '<div><a href="tel:' + esc(p.phone.replace(/[^\d+]/g, '')) + '">' + esc(p.phone) + '</a></div>';
    if (p.route) h += '<div><a href="' + esc(p.route) + '" target="_blank" rel="noopener">Прокласти маршрут →</a></div>';
    return h + '</div>';
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  /**
   * @param {Element} host    куди малювати
   * @param {Object}  cfg     {key, points, onPick}
   * @returns {Object|null}   {select(id)} — щоб сторінка могла підсвітити точку
   */
  function render(host, cfg) {
    if (!host || !cfg.key || !cfg.points || !cfg.points.length) return null;

    var ctl = { select: function () {} };
    load(cfg.key).then(function (maps) {
      host.classList.add('is-live');
      var map = new maps.Map(host, {
        mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
        // Сторінку гортають пальцем через усю ширину екрана: карта, що ловить
        // цей жест, зупиняє прокрутку й людина застрягає на ній
        gestureHandling: 'cooperative'
      });
      var info = new maps.InfoWindow();
      var marks = {};

      cfg.points.forEach(function (p) {
        var m = new maps.Marker({ position: { lat: p.lat, lng: p.lng }, map: map, title: p.name });
        marks[p.id] = m;
        m.addListener('click', function () {
          info.setContent(card(p));
          info.open(map, m);
          if (cfg.onPick) cfg.onPick(p.id);
        });
      });
      frame(maps, map, cfg.points);

      ctl.select = function (id) {
        var m = marks[id];
        if (!m) { info.close(); return; }
        map.panTo(m.getPosition());
        if (map.getZoom() < 14) map.setZoom(15);
        var p = null;
        cfg.points.forEach(function (q) { if (q.id === id) p = q; });
        if (p) { info.setContent(card(p)); info.open(map, m); }
      };
      if (cfg.selected) ctl.select(cfg.selected);
    }).catch(function () {
      // Чужий скрипт не завантажився (немає мережі, ключ відкликано, блокувальник).
      // Карту прибираємо зовсім: порожній сірий прямокутник виглядає як поломка
      // сторінки, хоча адреси під ним цілі.
      host.hidden = true;
    });
    return ctl;
  }

  /**
   * Карта для вибору точки: клацнули — мітка стала, потягнули — переїхала.
   *
   * Пошуку адреси тут навмисно немає. Він живе в окремому API Google з окремою
   * оплатою, тобто коштував би грошей на кожному відкритті вікна — заради дії,
   * яку роблять раз на магазин за все життя. Замість нього карта відкривається
   * там, де вже стоїть мітка (або поруч із сусідньою точкою), а далі своє місто
   * людина впізнає з двох рухів.
   *
   * @param {Element} host
   * @param {Object}  cfg  {key, lat, lng, fallback:{lat,lng,zoom}, onMove(lat,lng)}
   * @returns {Object} {focus(lat,lng)} — щоб вікно могло відкритись на новому місці
   */
  function pick(host, cfg) {
    var ctl = { focus: function () {}, refresh: function () {} };
    if (!host || !cfg.key) return ctl;

    load(cfg.key).then(function (maps) {
      host.classList.add('is-live');
      var start = (cfg.lat != null && cfg.lng != null)
        ? { lat: cfg.lat, lng: cfg.lng }
        : { lat: cfg.fallback.lat, lng: cfg.fallback.lng };
      var map = new maps.Map(host, {
        center: start,
        zoom: (cfg.lat != null ? 17 : cfg.fallback.zoom),
        mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
        // Тут карта — головне на екрані, і жест по ній має рухати саме її
        gestureHandling: 'greedy'
      });
      // Мітка зʼявляється лише коли точка задана: порожній магазин не має
      // отримати мітку «десь у центрі України» просто від відкриття вікна
      var mark = null;
      function place(pos, tell) {
        if (!mark) {
          mark = new maps.Marker({ position: pos, map: map, draggable: true });
          mark.addListener('dragend', function () {
            var p = mark.getPosition();
            if (cfg.onMove) cfg.onMove(p.lat(), p.lng());
          });
        } else {
          mark.setPosition(pos);
        }
        if (tell && cfg.onMove) cfg.onMove(pos.lat, pos.lng);
      }
      if (cfg.lat != null && cfg.lng != null) place(start, false);

      map.addListener('click', function (e) {
        place({ lat: e.latLng.lat(), lng: e.latLng.lng() }, true);
      });

      ctl.focus = function (lat, lng) {
        if (lat == null || lng == null) return;
        map.setCenter({ lat: lat, lng: lng });
        map.setZoom(17);
        place({ lat: lat, lng: lng }, false);
      };
      // Вікно ховається через display:none, а прихований блок має нульовий
      // розмір. Без цього карта після другого відкриття лишалась би сірою:
      // вона памʼятає ті розміри, які бачила востаннє.
      ctl.refresh = function () {
        maps.event.trigger(map, 'resize');
        if (mark) map.setCenter(mark.getPosition());
      };
      ctl.refresh();
    }).catch(function () { host.hidden = true; });

    return ctl;
  }

  window.BofuMap = { render: render, pick: pick };
})();
