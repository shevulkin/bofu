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
      // Ціна за 100 г належить фасовці: банка 0,5 і банка 1,5 коштують різне
      // й важать різне. Без ваги рядок ховається — ділити нема на що.
      var priceUnit = document.getElementById('priceUnit');
      if (priceUnit) {
        priceUnit.textContent = v.per_100g || '';
        priceUnit.hidden = !v.per_100g;
      }
      // Вага в характеристиках — теж від фасовки
      var specWeight = document.getElementById('specWeight');
      var specWeightRow = document.getElementById('specWeightRow');
      if (specWeight) specWeight.textContent = v.weight_fmt || '';
      if (specWeightRow) specWeightRow.style.display = v.weight_fmt ? '' : 'none';
      if (note) {
        var bits = [];
        if (v.sku) bits.push('Артикул: ' + v.sku);
        var t = window.BOFU_LOW_STOCK_THRESHOLD;
        // текст «під замовлення» приходить із сервера: для чужого бренду він
        // інший, і дублювати тут правило означало б розʼїхатися з ним
        bits.push(v.qty > 0 ? (t !== null && v.qty <= t ? 'Закінчується' : 'У наявності')
          : (window.BOFU_MADE_TO_ORDER
              ? 'Немає на складі — ' + (window.BOFU_MTO_NOTE || 'під замовлення')
              : 'Немає в наявності'));
        note.textContent = bits.join(' · ');
      }
      updateAvailability(v);
      updateAddButton(v);
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

    /**
     * Кнопка «До кошика» для варіанта, якого немає. Сервер відмовить у будь-якому
     * разі — тут ми лише не даємо людині дізнатися про це вже на оформленні.
     * «Виготовимо під замовлення» не обмежуємо: такий товар роблять під клієнта.
     */
    function updateAddButton(v) {
      var btn = document.getElementById('addToCart');
      if (!btn) return;
      var blocked = v.qty <= 0 && !window.BOFU_MADE_TO_ORDER;
      btn.disabled = blocked;
      btn.textContent = blocked ? 'Немає в наявності' : 'До кошика';
      var qtyInput = document.querySelector('.qty-box input[name=qty]');
      if (qtyInput) {
        if (window.BOFU_MADE_TO_ORDER || v.qty <= 0) qtyInput.removeAttribute('max');
        else {
          qtyInput.max = v.qty;
          if (+qtyInput.value > v.qty) qtyInput.value = v.qty;
        }
      }
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
      // «Виготовимо під замовлення» стоїть над переліком точок, а не в ньому:
      // це відповідь на питання «то він буде?», і читатись вона має першою,
      // а не після двох рядків «немає».
      var mto = document.getElementById('madeToOrderLead');
      if (mto) mto.style.display = any ? 'none' : '';
      // «Повідомити, коли зʼявиться» має сенс лише для варіанта, якого немає;
      // тримаємо в прихованому полі саме обраний, інакше в чергу очікувань
      // потрапив би той, що стояв на сторінці при завантаженні
      var watch = document.getElementById('watchBox');
      if (watch) watch.style.display = any ? 'none' : '';
      var wv = document.getElementById('watchVariant');
      if (wv) wv.value = v.id;
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
  function showCartToast(name, note) {
    if (!cartToast) return;
    var textEl = cartToast.querySelector('.cart-toast-text');
    // note приходить, коли поклали не всю замовлену кількість — тоді про це
    // й кажемо: «додано в кошик» приховало б, що штук менше, ніж просили
    if (textEl) textEl.textContent = note || ((name ? '«' + name + '»' : 'Товар') + ' додано в кошик');
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
  /* ── Телефон ────────────────────────────────────────────────────────────────
     Дзеркало AuthTokens::normPhone / normPhoneAny. Сервер лишається джерелом
     істини — тут ми лише кажемо людині заздалегідь те, що він скаже потім.
     Якщо правило на сервері зміниться, міняти треба й тут: розʼїзд цих двох
     місць виглядає для людини як «кнопка не працює».                          */
  function phoneDigits(v) { return String(v || '').replace(/\D/g, ''); }

  function normPhoneUA(v) {
    var d = phoneDigits(v);
    if (d.length === 12 && d.indexOf('380') === 0) return '+' + d;
    if (d.length === 10 && d.charAt(0) === '0') return '+38' + d;
    // нуль попереду означає обрізаний десятизначний, а не номер без коду —
    // див. пояснення в AuthTokens::normPhone()
    if (d.length === 9 && d.charAt(0) !== '0') return '+380' + d;
    return null;
  }
  function normPhoneAny(v) {
    var ua = normPhoneUA(v);
    if (ua) return ua;
    var d = phoneDigits(v);
    if (/^\s*\+/.test(String(v || '')) && d.length >= 10 && d.length <= 15) return '+' + d;
    return null;
  }
  /** +380671234567 → +380 67 123 45 67. Чужі коди лишаємо як є: розбивка в них інша */
  function prettyPhone(norm) {
    var m = /^\+380(\d{2})(\d{3})(\d{2})(\d{2})$/.exec(norm || '');
    return m ? '+380 ' + m[1] + ' ' + m[2] + ' ' + m[3] + ' ' + m[4] : norm;
  }

  /**
   * Чому саме не підходить. «Неправильний номер» не каже, що робити далі, —
   * а людина здебільшого помилилась в одній цифрі або не поставила плюс.
   */
  function phoneProblem(raw) {
    var d = phoneDigits(raw);
    var plus = /^\s*\+/.test(raw);
    var foreign = plus && d.indexOf('380') !== 0;

    if (d.length === 0) return 'Введіть номер цифрами';
    if (foreign) {
      if (d.length < 10) return 'Замало цифр для міжнародного номера: ' + d.length + ' — треба щонайменше 10';
      return 'Забагато цифр: ' + d.length + ' — у міжнародному щонайбільше 15';
    }
    // Багато цифр без плюса — майже завжди закордонний номер, набраний як удома
    if (!plus && d.length > 12) return 'Схоже на іноземний номер — поставте + і код країни: +' + d;
    if (d.length === 10 && d.charAt(0) !== '0') return 'Український номер починається з нуля: 0' + d.slice(0, 2) + ' …';
    if (d.length < 10) return 'Замало цифр: ' + d.length + ' з 10. Приклад: 067 123 45 67';
    return 'Забагато цифр: ' + d.length + '. В українському номері їх 10: 067 123 45 67';
  }

  Array.prototype.forEach.call(document.querySelectorAll('input[type=tel]'), function (inp) {
    // Правило одне на всі форми — checkout, профіль, картка користувача, вхід
    // за номером. Різні правила в різних місцях означали б, що номер, з яким
    // людина замовляла, раптом не годиться для входу.
    var norm = normPhoneAny;
    if (!inp.getAttribute('inputmode')) inp.setAttribute('inputmode', 'tel');
    if (!inp.getAttribute('autocomplete')) inp.setAttribute('autocomplete', 'tel');

    var hint = document.createElement('div');
    hint.className = 'field-hint';
    inp.insertAdjacentElement('afterend', hint);

    function say(state, text) {
      hint.className = 'field-hint' + (state ? ' is-' + state : '');
      hint.textContent = text;
      inp.classList.toggle('is-bad', state === 'bad');
    }
    /** @param strict показувати помилку (після blur і на сабміті), а не лише поки набирають */
    function check(strict) {
      var raw = inp.value.trim();
      if (raw === '') {
        say('', 'Напр. 067 123 45 67. Іноземний — з кодом країни, через +');
        return !inp.required;
      }
      var ok = norm(raw);
      if (ok) { say('ok', 'Приймемо як ' + ok); return true; }
      say(strict ? 'bad' : '', strict ? phoneProblem(raw) : 'Дописуйте — поки що номер неповний');
      return false;
    }

    check(false);
    inp.addEventListener('input', function () { check(false); });
    inp.addEventListener('blur', function () {
      var ok = norm(inp.value.trim());
      if (ok) inp.value = prettyPhone(ok);   // показуємо формат, а не просто приймаємо
      check(true);
    });

    // Сабміт зупиняємо самі: людина має побачити, ЩО не так, ще до перезавантаження
    // сторінки — інакше вона повертається на форму з втраченими полями.
    if (inp.form) {
      inp.form.addEventListener('submit', function (e) {
        if (check(true)) return;
        e.preventDefault();
        inp.focus();
        inp.scrollIntoView({ block: 'center', behavior: 'smooth' });
      });
    }
  });

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
          showCartToast(form.dataset.productName, res.note);
          // Кошик змінився без перезавантаження сторінки. Смужка продажу (каса)
          // висить на цій самій сторінці й має оновити свій підсумок — інакше
          // продавець дивиться на суму, якої вже немає. Подія, а не прямий
          // виклик: вітрина нічого не знає про касу й знати не мусить.
          document.dispatchEvent(new CustomEvent('bofu:cart', { detail: { count: res.count } }));
        } else if (res && res.error) {
          showCartToast(null, res.error);
        } else if (res && res.redirect) {
          // сервер каже, що бракує вибору — ведемо туди, де його роблять
          location.href = res.redirect;
        } else {
          form.submit();
        }
      })
      .catch(function () { form.submit(); })
      .finally(function () { if (btn) btn.disabled = false; });
  });
})();
