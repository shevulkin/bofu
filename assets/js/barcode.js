// Читання штрихкодів EAN-13 / EAN-8 з кадру камери.
//
// Навіщо своє. Браузерний BarcodeDetector є лише на Android і ChromeOS — на
// Windows його немає взагалі, а саме там стоїть каса. Тягнути бібліотеку в
// проєкт, який принципово живе без залежностей і має працювати на шаред-хостингу
// копіюванням теки, не варто заради одного алгоритму на дві сотні рядків.
//
// Що робимо: беремо кілька горизонтальних смуг кадру, перетворюємо кожну на
// послідовність чорних і білих смужок і пробуємо прочитати з них цифри. Штрихкод
// на етикетці однаковий по всій висоті, тож достатньо, щоб бодай одна смуга
// перетнула його чисто — решта хай не читається.
//
// Помилково прочитаний код гірший за непрочитаний: у чек тихо ляже чужий товар.
// Тому наприкінці перевіряємо контрольну цифру — саме для цього вона в коді й є.
window.BofuBarcode = (function () {
  'use strict';

  // Ширини чотирьох смужок кожної цифри (разом завжди 7 модулів).
  // Набір L — ліві цифри, G — той самий набір задом наперед, R — як L.
  var L = [[3,2,1,1],[2,2,2,1],[2,1,2,2],[1,4,1,1],[1,1,3,2],
           [1,2,3,1],[1,1,1,4],[1,3,1,2],[1,2,1,3],[3,1,1,2]];
  var G = L.map(function (p) { return p.slice().reverse(); });

  // Перша цифра EAN-13 не намальована окремо — вона закодована тим, якими
  // наборами (L чи G) записані шість лівих цифр
  var FIRST = ['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG',
               'LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];

  /**
   * Наскільки ці чотири смужки схожі на еталон. Порівнюємо в частках модуля, а
   * не в пікселях: камера тримає етикетку то ближче, то далі, і абсолютні
   * ширини не значать нічого.
   *
   * @return число: менше — краще; 1e9 — не схоже зовсім
   */
  function variance(widths, pattern) {
    var total = 0, patTotal = 0, i;
    for (i = 0; i < 4; i++) { total += widths[i]; patTotal += pattern[i]; }
    if (total < patTotal) return 1e9;          // смужки вужчі за один модуль
    var unit = total / patTotal;
    // 0.7 модуля на смужку — стільки ж дозволяє собі ZXing. З піввідхиленням
    // (0.5) код із дробовою шириною модуля — 2.5 пікселя на смужку — не читався
    // взагалі: смужки виходили то по 2, то по 3 пікселі, і жоден еталон не
    // підходив, хоча код на екрані був бездоганний.
    var max = unit * 0.7;
    var sum = 0;
    for (i = 0; i < 4; i++) {
      var diff = Math.abs(widths[i] - pattern[i] * unit);
      if (diff > max) return 1e9;
      sum += diff;
    }
    return sum / unit;
  }

  /** Одна цифра з чотирьох смужок: яка саме й яким набором записана */
  function digitAt(runs, at, sets) {
    var widths = [runs[at], runs[at + 1], runs[at + 2], runs[at + 3]];
    var best = 1e9, bestDigit = -1, bestSet = '';
    sets.forEach(function (s) {
      var table = s === 'G' ? G : L;
      for (var d = 0; d < 10; d++) {
        var v = variance(widths, table[d]);
        if (v < best) { best = v; bestDigit = d; bestSet = s; }
      }
    });
    return best < 0.6 ? { digit: bestDigit, set: bestSet } : null;
  }

  /** Три однакові смужки — це роздільник (початок, середина, кінець) */
  function guard(runs, at, count, unit) {
    for (var i = 0; i < count; i++) {
      var w = runs[at + i];
      if (w === undefined || w < unit * 0.4 || w > unit * 1.8) return false;
    }
    return true;
  }

  /** Контрольна цифра: саме вона відрізняє прочитаний код від вигаданого */
  function checksum(digits) {
    var sum = 0;
    for (var i = 0; i < digits.length - 1; i++) {
      // вага 3 стоїть на других позиціях, рахуючи з кінця (перед контрольною)
      var weight = ((digits.length - 2 - i) % 2 === 0) ? 3 : 1;
      sum += digits[i] * weight;
    }
    return (10 - (sum % 10)) % 10 === digits[digits.length - 1];
  }

  /** EAN-13, починаючи зі смужки at (вона має бути чорною) */
  function readEan13(runs, at) {
    var unit = (runs[at] + runs[at + 1] + runs[at + 2]) / 3;
    if (!guard(runs, at, 3, unit)) return null;
    bump('guard');

    var digits = [], parity = '', i, d;
    for (i = 0; i < 6; i++) {
      d = digitAt(runs, at + 3 + i * 4, ['L', 'G']);
      if (!d) { if (i >= 3) bump('half'); return null; }
      digits.push(d.digit);
      parity += d.set;
    }
    bump('left');
    var center = at + 3 + 24;
    if (!guard(runs, center, 5, unit)) return null;
    for (i = 0; i < 6; i++) {
      d = digitAt(runs, center + 5 + i * 4, ['L']);   // праві цифри — тільки L-ширини
      if (!d) return null;
      digits.push(d.digit);
    }
    if (!guard(runs, center + 5 + 24, 3, unit)) return null;

    var first = FIRST.indexOf(parity);
    if (first < 0) return null;
    digits.unshift(first);
    bump('read');
    if (checksum(digits)) return digits.join('');
    bump('csum');           // прочитали все, але контрольна цифра не зійшлась
    return null;
  }

  /** EAN-8 — той самий устрій, але по чотири цифри й без гри наборами */
  function readEan8(runs, at) {
    var unit = (runs[at] + runs[at + 1] + runs[at + 2]) / 3;
    if (!guard(runs, at, 3, unit)) return null;

    var digits = [], i, d;
    for (i = 0; i < 4; i++) {
      d = digitAt(runs, at + 3 + i * 4, ['L']);
      if (!d) return null;
      digits.push(d.digit);
    }
    var center = at + 3 + 16;
    if (!guard(runs, center, 5, unit)) return null;
    for (i = 0; i < 4; i++) {
      d = digitAt(runs, center + 5 + i * 4, ['L']);
      if (!d) return null;
      digits.push(d.digit);
    }
    if (!guard(runs, center + 5 + 16, 3, unit)) return null;
    return checksum(digits) ? digits.join('') : null;
  }

  /**
   * Смуга пікселів → послідовність ширин чорних і білих смужок.
   *
   * Лінія може йти під нахилом: етикетку тримають у руці, і рівно вона не
   * лягає майже ніколи. Горизонтальна лінія на перекошеному коді входить у
   * смужки зверху, а виходить уже в надрукованих цифрах — і читати нема чого.
   * Тому смугу ведемо з нахилом slope (зсув по y на кожен піксель по x).
   *
   * Поріг рахуємо для кожної смуги окремо: освітлення на етикетці нерівне, і
   * один поріг на весь кадр з'їдав би її край.
   */
  // Буфери на модуль, а не на виклик: ліній за кадр — під дві сотні, і
  // алокація масиву на кожну з них коштувала б більше, ніж саме читання.
  var rowBuf = null, preBuf = null, minBuf = null, maxBuf = null;

  /**
   * Лічильники того, де саме читання зупинилось.
   *
   * «Не читається» — найгірша відповідь: вона однакова і для темряви, і для
   * чужого коду, і для однієї збитої цифри. Тому декодер рахує, скільки ліній
   * дало смужки, скільки з них знайшли роздільник, скільки дочитались до кінця
   * і скільки завалили контрольну цифру. З цими числами видно, що чинити:
   * світло, відстань чи сам код.
   */
  var stats = null;
  function bump(key) { if (stats) stats[key] = (stats[key] || 0) + 1; }

  function runsOfLine(data, width, height, yCenter, slope) {
    if (!rowBuf || rowBuf.length < width) {
      rowBuf = new Uint8Array(width);
      preBuf = new Float64Array(width + 1);
    }
    var row = rowBuf, n = 0, min = 255, max = 0, i, v;
    for (i = 0; i < width; i++) {
      var y = Math.round(yCenter + slope * (i - width / 2));
      if (y < 0 || y >= height) continue;       // лінія вийшла за кадр — цей шматок пропускаємо
      // зелений канал замість чесної яскравості: він і є більшість яскравості,
      // а множень на кадр стає втричі менше
      v = data[(y * width + i) * 4 + 1];
      row[n++] = v;
      if (v < min) min = v;
      if (v > max) max = v;
    }
    if (n < 60 || max - min < 40) return null;  // закоротка смуга або без контрасту

    /**
     * Поріг — середина між найтемнішим і найсвітлішим ПОРУЧ, а не одне число
     * на всю смугу.
     *
     * Одного порогу вистачає лише на рівно освітленому папері. У житті смуга
     * йде через яскраву коробку, тінь від руки й темний стіл — і половина коду
     * опиняється по «неправильний» бік.
     *
     * Чому саме середина між min і max, а не середнє: середнє зміщене туди, де
     * більше пікселів, а на етикетці білого завжди більше за чорне. Поріг
     * повзе до білого, і кожна чорна смужка міряється вужчою, ніж вона є, — на
     * різкому кадрі це ще проходить, а на трохи розмитому цифри вже не
     * складаються. Саме через це «код видно, а не читається».
     *
     * Рахуємо блоками по win пікселів і беремо сусідні блоки — це той самий
     * ковзний min/max, але за один прохід.
     */
    var win = Math.max(8, Math.round(n / 32));
    var blocks = Math.ceil(n / win);
    if (!minBuf || minBuf.length < blocks) {
      minBuf = new Uint8Array(blocks + 2);
      maxBuf = new Uint8Array(blocks + 2);
    }
    var bi, from, to;
    for (bi = 0; bi < blocks; bi++) {
      from = bi * win; to = Math.min(n, from + win);
      var lo = 255, hi = 0;
      for (i = from; i < to; i++) { v = row[i]; if (v < lo) lo = v; if (v > hi) hi = v; }
      minBuf[bi] = lo; maxBuf[bi] = hi;
    }

    var runs = [], count = 0, black = false, first = true;
    for (i = 0; i < n; i++) {
      bi = (i / win) | 0;
      var a = bi > 0 ? bi - 1 : 0, b2 = bi + 1 < blocks ? bi + 1 : blocks - 1;
      var lo2 = minBuf[a], hi2 = maxBuf[a], k;
      for (k = a + 1; k <= b2; k++) {
        if (minBuf[k] < lo2) lo2 = minBuf[k];
        if (maxBuf[k] > hi2) hi2 = maxBuf[k];
      }
      // Рівна ділянка без перепаду — це поле, а не смужки: інакше шум паперу
      // розпадався б на сотні «смужок» і збивав відлік
      var isBlack = (hi2 - lo2) < 24 ? false : row[i] < (lo2 + hi2) / 2;
      if (first) { black = isBlack; first = false; count = 1; continue; }
      if (isBlack === black) { count++; continue; }
      runs.push(count);
      count = 1;
      black = isBlack;
    }
    runs.push(count);
    // Перша смужка має бути чорною: з білого поля відлік починати нема сенсу
    if (!black || runs.length % 2 === 0) { /* нічого: чергування перевіряємо нижче */ }
    if (!(row[0] < (minBuf[0] + maxBuf[0]) / 2 && (maxBuf[0] - minBuf[0]) >= 24)) runs.shift();
    return runs.length < 30 ? null : runs;
  }

  /** Спроба прочитати код із однієї смуги, в обидва боки */
  function fromRuns(runs) {
    // Етикетку часто підносять догори дриґом, тож пробуємо й задом наперед.
    // Але розворот міняє й чергування кольорів: у прямому масиві чорні смужки
    // стоять на парних місцях, а в перевернутому — лише якщо смужок непарна
    // кількість. Інакше першу (білу) треба прибрати, бо весь відлік нижче
    // спирається на «парний індекс = чорна».
    var rev = runs.slice().reverse();
    if (runs.length % 2 === 0) rev.shift();
    var tries = [runs, rev];
    for (var t = 0; t < tries.length; t++) {
      var r = tries[t];
      // Кожна друга смужка чорна; починати можна лише з чорної.
      // 42 — найкоротший можливий код (EAN-8): менше смужок попереду означає,
      // що читати вже нічого. З більшим порогом EAN-8 не читався взагалі.
      for (var at = 0; at + 42 < r.length; at += 2) {
        var code = readEan13(r, at) || readEan8(r, at);
        if (code) return code;
      }
    }
    return null;
  }

  /**
   * Нахили, під якими пробуємо вести лінію.
   *
   * Рівно етикетку в руці не тримає ніхто, а горизонтальна лінія на
   * перекошеному коді входить у смужки зверху й виходить уже в надрукованих
   * цифрах. Без нахилів код на коробці в руці не читається взагалі.
   *
   * Крок навмисно дрібний. З рідким (п'ять значень) робочий діапазон між ними
   * провалювався: 12° читалось, а 7° — ні, і виглядало це як «камера не бачить».
   * Заміряно на tests/barcode.html: смуга, у якій код читається, вужча за 0.05.
   *
   * Порядок — від найменшого: рівно піднесений код має читатися з першої спроби.
   */
  var SLOPES = (function () {
    var out = [0];
    for (var s = 0.03; s <= 0.281; s += 0.03) out.push(+s.toFixed(2), -s.toFixed(2));
    return out;
  })();

  // Нахил, під яким пощастило минулого разу. Руку між двома товарами тримають
  // приблизно однаково, тож наступний код майже напевно піде тим самим кутом —
  // і знайдеться з першої спроби, а не з пʼятої.
  var luckySlope = 0;

  return {
    /**
     * Прочитати штрихкод із кадру.
     *
     * Пробуємо кілька смуг під кількома нахилами: одна впаде на блік, згин чи
     * пальці, друга — на цифри під кодом, а третя пройде чисто. Виходимо на
     * першому ж коді, що зійшовся з контрольною цифрою.
     *
     * @param {ImageData} img
     * @return {?string} код або null
     */
    decode: function (img, statsOut) {
      stats = statsOut || null;
      var rows = 14;
      var order = [luckySlope].concat(SLOPES.filter(function (s) { return s !== luckySlope; }));
      try {
        for (var s = 0; s < order.length; s++) {
          for (var i = 1; i <= rows; i++) {
            var y = img.height * i / (rows + 1);
            bump('lines');
            var runs = runsOfLine(img.data, img.width, img.height, y, order[s]);
            if (!runs) continue;
            bump('runs');
            var code = fromRuns(runs);
            if (code) { luckySlope = order[s]; return code; }
          }
        }
        return null;
      } finally { stats = null; }
    },

    // Внутрішня кухня, відкрита навмисно: tests/barcode.html міряє нею, під
    // якими нахилами код читається, а під якими ні. Без цього «не читається»
    // лишається здогадкою — а здогадками такі речі не лагодяться.
    _line: runsOfLine,
    _read: fromRuns,
    _slopes: SLOPES
  };
})();
