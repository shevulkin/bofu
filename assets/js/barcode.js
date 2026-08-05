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
    var max = unit / 2;                         // піввідхилення модуля на смужку
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

    var digits = [], parity = '', i, d;
    for (i = 0; i < 6; i++) {
      d = digitAt(runs, at + 3 + i * 4, ['L', 'G']);
      if (!d) return null;
      digits.push(d.digit);
      parity += d.set;
    }
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
    return checksum(digits) ? digits.join('') : null;
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
   * Поріг рахуємо для кожної смуги окремо: освітлення на етикетці нерівне, і
   * один поріг на весь кадр з'їдав би її край.
   */
  function runsOfRow(data, width, y) {
    var min = 255, max = 0, i, v, row = new Uint8Array(width);
    for (i = 0; i < width; i++) {
      var p = (y * width + i) * 4;
      // зелений канал замість чесної яскравості: він і є більшість яскравості,
      // а множень на кадр стає втричі менше
      v = data[p + 1];
      row[i] = v;
      if (v < min) min = v;
      if (v > max) max = v;
    }
    if (max - min < 40) return null;            // смуга без контрасту — не штрихкод
    var threshold = (min + max) / 2;

    var runs = [], count = 0, black = row[0] < threshold;
    // Перша смужка має бути чорною: з білого поля відлік починати нема сенсу
    for (i = 0; i < width; i++) {
      var isBlack = row[i] < threshold;
      if (isBlack === black) { count++; continue; }
      runs.push(count);
      count = 1;
      black = isBlack;
    }
    runs.push(count);
    if (!(row[0] < threshold)) runs.shift();     // прибираємо біле поле зліва
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

  return {
    /**
     * Прочитати штрихкод із кадру. Пробуємо кілька горизонтальних смуг: одна
     * може впасти на блік, згин чи пальці, а сусідня — ні.
     *
     * @param {ImageData} img
     * @return {?string} код або null
     */
    decode: function (img) {
      var rows = 24;
      for (var i = 1; i <= rows; i++) {
        var y = Math.floor(img.height * i / (rows + 1));
        var runs = runsOfRow(img.data, img.width, y);
        if (!runs) continue;
        var code = fromRuns(runs);
        if (code) return code;
      }
      return null;
    }
  };
})();
