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

  // вибір варіанта товару: ціна, наявність і артикул оновлюються одразу
  var picker = document.getElementById('variantPicker');
  if (picker && window.BOFU_VARIANTS && BOFU_VARIANTS.length) {
    var variants = BOFU_VARIANTS;
    var hidden = document.getElementById('variantId');
    var priceNow = document.getElementById('priceNow');
    var priceOld = document.getElementById('priceOld');
    var note = document.getElementById('variantNote');
    var axisRows = picker.querySelectorAll('[data-axis]');
    var selection = {};

    function findVariant() {
      for (var i = 0; i < variants.length; i++) {
        var ok = true;
        for (var axis in selection) {
          if (String(variants[i].opts[axis]) !== String(selection[axis])) { ok = false; break; }
        }
        if (ok) return variants[i];
      }
      return null;
    }

    // чи існує варіант, якщо в обраному наборі замінити одну вісь
    function combinationExists(axis, value) {
      for (var i = 0; i < variants.length; i++) {
        var v = variants[i], ok = String(v.opts[axis]) === String(value);
        for (var other in selection) {
          if (other === axis) continue;
          if (String(v.opts[other]) !== String(selection[other])) { ok = false; break; }
        }
        if (ok) return true;
      }
      return false;
    }

    function byId(id) {
      for (var i = 0; i < variants.length; i++) if (variants[i].id === +id) return variants[i];
      return null;
    }

    function apply(v) {
      if (!v) {
        if (note) note.textContent = 'Такої комбінації немає — оберіть іншу.';
        return;
      }
      if (hidden) hidden.value = v.id;
      if (priceNow) priceNow.textContent = v.price_fmt;
      if (priceOld) {
        priceOld.textContent = v.old_fmt || '';
        priceOld.hidden = !v.old_fmt;
      }
      if (note) {
        var bits = [];
        if (v.sku) bits.push('Артикул: ' + v.sku);
        var t = window.BOFU_LOW_STOCK_THRESHOLD;
        bits.push(v.qty > 0 ? (t !== null && v.qty <= t ? 'Закінчується' : 'У наявності')
          : (window.BOFU_MADE_TO_ORDER ? 'Немає на складі — виготовимо під замовлення' : 'Немає в наявності'));
        note.textContent = bits.join(' · ');
      }
      updateAvailability(v);
      // гасимо значення, які не поєднуються з поточним вибором
      picker.querySelectorAll('.opt').forEach(function (btn) {
        var axis = btn.dataset.axis;
        btn.classList.toggle('active', String(selection[axis]) === btn.dataset.value);
        btn.classList.toggle('off', !combinationExists(axis, btn.dataset.value));
      });
      picker.querySelectorAll('.opt-plain').forEach(function (btn) {
        btn.classList.toggle('active', +btn.dataset.variant === v.id);
      });
    }

    function updateAvailability(v) {
      var box = document.getElementById('availability');
      if (!box) return;
      var any = false;
      box.querySelectorAll('[data-store]').forEach(function (el) {
        var map = {};
        try { map = JSON.parse(el.dataset.stock || '{}'); } catch (e) {}
        var qty = +(map[v.id] || 0);
        any = any || qty > 0;
        var txt = el.querySelector('.av-text');
        if (txt) {
          var t = window.BOFU_LOW_STOCK_THRESHOLD;
          var stockTxt = qty > 0 ? (t !== null && qty <= t ? 'закінчується' : 'в наявності') : 'немає';
          // ціна цього магазину для обраного варіанта
          var sp = (v.store_price || {})[el.dataset.store] || '';
          txt.textContent = el.dataset.label + ': ' + stockTxt + (sp ? ' · ціна тут: ' + sp : '');
        }
        el.classList.toggle('yes', qty > 0);
        el.classList.toggle('no', qty <= 0);
      });
      var mto = document.getElementById('madeToOrder');
      if (mto) mto.style.display = any ? 'none' : '';
    }

    if (axisRows.length) {
      var first = byId(picker.dataset.first) || variants[0];
      for (var axis in first.opts) selection[axis] = first.opts[axis];
      picker.querySelectorAll('.opt').forEach(function (btn) {
        btn.addEventListener('click', function () {
          selection[btn.dataset.axis] = btn.dataset.value;
          var v = findVariant();
          // якщо такої комбінації немає — підбираємо найближчу з обраним значенням
          if (!v) {
            for (var i = 0; i < variants.length; i++) {
              if (String(variants[i].opts[btn.dataset.axis]) === btn.dataset.value) {
                selection = {};
                for (var a in variants[i].opts) selection[a] = variants[i].opts[a];
                v = variants[i];
                break;
              }
            }
          }
          apply(v);
        });
      });
      apply(first);
    } else {
      picker.querySelectorAll('.opt-plain').forEach(function (btn) {
        btn.addEventListener('click', function () { apply(byId(btn.dataset.variant)); });
      });
      apply(byId(picker.dataset.first) || variants[0]);
    }
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
