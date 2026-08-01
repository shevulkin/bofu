// Beekeeper of Ukraine — вітрина
(function () {
  var modal = document.getElementById('authModal');
  var loginBtn = document.getElementById('loginBtn');
  if (loginBtn && modal) {
    loginBtn.addEventListener('click', function (e) { e.preventDefault(); modal.classList.add('open'); });
    var close = document.getElementById('authClose');
    if (close) close.addEventListener('click', function () { modal.classList.remove('open'); });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('open'); });
  }
  var mb = document.getElementById('mobileBtn'), mm = document.getElementById('mobileMenu');
  if (mb && mm) mb.addEventListener('click', function () { mm.classList.toggle('open'); });

  var toTop = document.getElementById('toTop');
  if (toTop) {
    window.addEventListener('scroll', function () {
      toTop.classList.toggle('show', window.scrollY > 500);
    }, { passive: true });
    toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
  }

  // анімація лічильника випускників
  var counter = document.querySelector('[data-count-to]');
  if (counter) {
    var target = parseInt(counter.getAttribute('data-count-to'), 10) || 0;
    var start = null, dur = 1400;
    function tick(t) {
      if (!start) start = t;
      var p = Math.min(1, (t - start) / dur);
      counter.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('uk-UA');
      if (p < 1) requestAnimationFrame(tick);
    }
    var seen = false;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && !seen) { seen = true; requestAnimationFrame(tick); io.disconnect(); }
      });
    });
    io.observe(counter);
  }

  // додавання в кошик без перезавантаження сторінки
  var cartToast = document.getElementById('cartToast');
  var cartToastTimer = null;
  function showCartToast(name) {
    if (!cartToast) return;
    var textEl = cartToast.querySelector('.cart-toast-text');
    if (textEl) textEl.textContent = (name ? '«' + name + '»' : 'Товар') + ' додано в кошик';
    cartToast.classList.add('show');
    clearTimeout(cartToastTimer);
    cartToastTimer = setTimeout(function () { cartToast.classList.remove('show'); }, 6000);
  }
  if (cartToast) {
    ['cartToastClose', 'cartToastContinue'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b) b.addEventListener('click', function () {
        cartToast.classList.remove('show');
        clearTimeout(cartToastTimer);
      });
    });
  }
  function updateCartBadge(count) {
    var link = document.querySelector('.cart-link');
    if (!link) return;
    var badge = link.querySelector('.cart-badge');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'cart-badge';
        link.appendChild(badge);
      }
      badge.textContent = count;
    } else if (badge) {
      badge.remove();
    }
  }
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches || !form.matches('.add-cart-form')) return;
    e.preventDefault();
    var btn = form.querySelector('button[type=submit]');
    var data = new FormData(form);
    data.set('ajax', '1');
    if (btn) btn.disabled = true;
    fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.ok) {
          updateCartBadge(res.count);
          showCartToast(form.dataset.productName);
        } else {
          form.submit();
        }
      })
      .catch(function () { form.submit(); })
      .finally(function () { if (btn) btn.disabled = false; });
  });
})();
