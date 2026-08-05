// Сканування штрихкодів камерою — спільне вікно для каси й для екрана кодів.
//
// Два шляхи до одного результату. На Android у браузері є вбудований
// BarcodeDetector — швидкий і апаратний, беремо його. Де його немає (а на
// Windows його немає ніде) — читаємо кадр самі, barcode.js. Той, хто сканує,
// різниці не бачить.
//
// Розмітку вікна будуємо тут, а не в шаблонах: сторінок, яким воно потрібне,
// уже дві, і копія розмітки в кожній розійшлася б із першою ж правкою.
window.BofuScan = (function () {
  'use strict';

  var box = null, video, msgEl, stream = null, raf = null, detector = null;
  var busy = false, frame = 0, handler = null, lastCode = '', lastAt = 0;
  var canvas = document.createElement('canvas');
  var ctx = canvas.getContext('2d', { willReadFrequently: true });

  function build() {
    if (box) return;
    box = document.createElement('div');
    box.className = 'pos-cam';
    box.hidden = true;
    box.innerHTML =
      '<div class="pos-cam-inner">' +
      '<video playsinline muted></video>' +
      '<div class="pos-cam-aim" aria-hidden="true"></div>' +
      '<p class="pos-cam-msg"></p>' +
      '<button type="button" class="btn btn-gold btn-sm">Готово</button>' +
      '</div>';
    document.body.appendChild(box);
    video = box.querySelector('video');
    msgEl = box.querySelector('.pos-cam-msg');
    box.querySelector('button').addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && box && !box.hidden) close();
    });
  }

  function say(text) { if (msgEl) msgEl.textContent = text; }

  var api = { say: say, close: close };

  /**
   * Увімкнути камеру. onCode(code, api) викликається на кожен упізнаний код —
   * далі вирішує сама сторінка: каса лишає вікно відкритим і сканує наступний
   * товар, екран кодів закриває його одразу.
   */
  function open(onCode, opts) {
    opts = opts || {};
    var fail = opts.onError || function (m) { window.alert(m); };

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      fail('Цей браузер не дає доступу до камери. Скористайтесь USB-сканером або введіть код руками.');
      return;
    }
    // Камеру браузер дає лише на https (localhost вважається безпечним). З
    // телефона по локальній http-адресі вона не увімкнеться — і сказати про це
    // треба зараз, а не показати порожнє вікно.
    if (!window.isSecureContext) {
      fail('Камера доступна лише за адресою https:// (або на localhost). Зараз сторінка відкрита без https.');
      return;
    }

    build();
    handler = onCode;
    lastCode = '';
    box.hidden = false;
    say('Вмикаємо камеру…');

    navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false
    }).then(function (s) {
      stream = s;
      video.srcObject = s;
      video.play();
      if ('BarcodeDetector' in window) {
        try {
          detector = new window.BarcodeDetector({
            formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128']
          });
        } catch (e) { detector = null; }
      }
      say(opts.title || 'Наведіть камеру на штрихкод');
      raf = requestAnimationFrame(tick);
    }).catch(function (e) {
      box.hidden = true;
      var why = e && e.name === 'NotAllowedError' ? 'ви заборонили доступ до камери в браузері'
        : (e && e.name === 'NotFoundError' ? 'камери не знайдено' : (e && e.message) || 'невідома причина');
      fail('Не вдалося увімкнути камеру: ' + why);
    });
  }

  function close() {
    if (raf) cancelAnimationFrame(raf);
    raf = null;
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null; detector = null; busy = false; handler = null;
    if (box) box.hidden = true;
  }

  /** Код упізнано: та сама етикетка в кадрі щомиті, тож повтори притримуємо */
  function hit(code) {
    var now = Date.now();
    if (code === lastCode && now - lastAt < 1800) return;
    lastCode = code; lastAt = now;
    if (navigator.vibrate) navigator.vibrate(60);   // на телефоні — єдине відчутне «пікнуло»
    if (handler) handler(code, api);
  }

  function tick() {
    raf = requestAnimationFrame(tick);
    if (!video.videoWidth) return;

    // Беремо не весь кадр, а смугу під рамкою прицілу: там етикетка, а решта
    // кадру — це стіл і руки, на яких декодер лише марнує час
    var sw = Math.floor(video.videoWidth * 0.92);
    var sh = Math.floor(video.videoHeight * 0.42);
    var sx = Math.floor((video.videoWidth - sw) / 2);
    var sy = Math.floor((video.videoHeight - sh) / 2);
    var scale = Math.min(1, 900 / sw);
    canvas.width = Math.floor(sw * scale);
    canvas.height = Math.floor(sh * scale);
    ctx.drawImage(video, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

    if (detector) {
      if (busy) return;
      busy = true;
      detector.detect(canvas).then(function (res) {
        busy = false;
        if (res && res.length && res[0].rawValue) hit(res[0].rawValue);
      }).catch(function () { busy = false; });
      return;
    }
    // Свій декодер важчий за апаратний, тож не на кожному кадрі: 20 спроб на
    // секунду людині нічого не додають, а ноутбук гудітиме
    if (++frame % 3 !== 0 || !window.BofuBarcode) return;
    var code = window.BofuBarcode.decode(ctx.getImageData(0, 0, canvas.width, canvas.height));
    if (code) hit(code);
  }

  return { open: open, close: close, say: say };
})();
