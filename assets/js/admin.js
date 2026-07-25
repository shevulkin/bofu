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
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sub)
          });
        }).then(function () {
          pushBtn.textContent = '🔔 Пуші увімкнено';
        }).catch(function (e) { console.warn('push', e); });
      });
    });
  }
  function urlB64(s) {
    var pad = '='.repeat((4 - s.length % 4) % 4);
    var b64 = (s + pad).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(b64), arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
})();
