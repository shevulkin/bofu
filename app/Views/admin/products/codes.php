<?php
/**
 * Коди й штрихкоди. Екран заточений під одну дію: пройтись товарами й проставити
 * коди. Три способи, бо в житті трапляються всі три:
 *
 *   • на товарі є фабрична етикетка — USB-сканер або 📷 камера в рядку;
 *   • етикетки немає (своя банка меду) — кнопка «Придумати коди»;
 *   • код відомий із накладної — просто ввести руками.
 *
 * @var array $products @var array $variants @var array $dupes
 */
$dupSku = fn(?string $c) => $c !== null && in_array(mb_strtolower(trim((string)$c)), $dupes['sku'], true);
$dupBar = fn(?string $c) => $c !== null && in_array(mb_strtolower(trim((string)$c)), $dupes['barcode'], true);

/** Поле штрихкоду: саме поле, кнопка камери й малюнок коду для друку */
$barcodeCell = function (string $name, ?string $code, bool $dupe): string {
    $svg = $code ? Barcode::svg((string)$code) : '';
    return '<div class="code-cell">'
        . '<input type="text" name="' . e($name) . '" value="' . e($code ?? '') . '"'
        . ' inputmode="numeric" autocomplete="off" data-code-input'
        . ($dupe ? ' style="border-color:var(--danger2)" title="Такий штрихкод уже є в іншої позиції"' : '') . '>'
        . '<button type="button" class="btn btn-line btn-xs" data-code-scan'
        . ' title="Просканувати камерою">📷</button>'
        . ($svg ? '<div class="code-pic">' . $svg . '</div>' : '')
        . '</div>';
};
?>
<div class="admin-head"><h1 class="h-serif">Коди й штрихкоди</h1>
  <span class="dim">Станьте в поле й піднесіть сканер — або натисніть 📷 і наведіть камеру</span>
</div>

<div class="card-warn" style="margin-bottom:18px">
  <b>Артикул</b> — ваш внутрішній код для обліку, придумуєте ви.
  <b>Штрихкод</b> — те, що надруковано на етикетці й читає сканер на касі.
  У товару з фасовками коди належать <b>фасовці</b>: етикетку клеять на банку, а не на «мед узагалі».
  <br>Немає етикетки — натисніть <b>«Придумати коди»</b> внизу: створимо власні
  (з тих, які стандарт лишив для внутрішнього вжитку магазину), намалюємо, і їх можна буде роздрукувати й наклеїти.
</div>

<?php if ($dupes['sku'] || $dupes['barcode']): ?>
  <div class="flash" style="padding:0;margin:0 0 16px"><div class="flash-error">
    Є однакові коди — вони підсвічені нижче. Каса щоразу братиме той товар, який трапиться першим,
    і продавець цього не помітить: у чек тихо ляже не та позиція.
  </div></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/products/codes')) ?>">
  <?= Csrf::field() ?>
  <table class="tbl">
    <tr>
      <th>Товар</th>
      <th style="width:190px" data-help-title="Артикул"
          data-help="Ваш код для обліку: «MED-LIP-05». Пошук в адмінці й на касі знаходить товар і за ним.

Якщо ведете склад у таблиці чи 1С — ставте той самий код, що й там.">Артикул</th>
      <th data-help-title="Штрихкод"
          data-help="Код із етикетки (EAN-13 — 13 цифр). Саме його читає сканер: на касі продавець підносить сканер до банки, і позиція сама лягає в чек.

Три способи заповнити: USB-сканером (станьте в поле й піднесіть), камерою (кнопка 📷 у рядку) або кнопкою «Придумати коди» внизу — для товарів без фабричної етикетки.

Під полем показано сам код малюнком: сторінку можна роздрукувати (Ctrl+P), розрізати й наклеїти на товар.">Штрихкод</th>
    </tr>
    <?php foreach ($products as $p): $pid = (int)$p['id']; $vs = $variants[$pid] ?? []; ?>
      <tr class="row-product">
        <td>
          <a href="<?= e(url('/admin/products/' . $pid)) ?>"><?= e($p['name']) ?></a>
          <div class="dim"><?= e($p['cat_name'] ?? '') ?><?= $p['active'] ? '' : ' · вимкнений' ?></div>
        </td>
        <?php /* У товару з фасовками власні коди не мають сенсу: сканер має
                 попасти в конкретну банку, а не в назву товару. Тому там поля
                 закриті, а не просто порожні — інакше їх заповнять, і скан
                 мовчки додаватиме «якийсь варіант». */ ?>
        <?php if ($vs): ?>
          <td colspan="2" class="dim">коди — у фасовках нижче</td>
        <?php else: ?>
          <td><input type="text" name="p[<?= $pid ?>][sku]" value="<?= e($p['sku'] ?? '') ?>"
                     autocomplete="off" <?= $dupSku($p['sku']) ? 'style="border-color:var(--danger2)" title="Такий артикул уже є в іншого товару"' : '' ?>></td>
          <td><?= $barcodeCell("p[$pid][barcode]", $p['barcode'], $dupBar($p['barcode'])) ?></td>
        <?php endif; ?>
      </tr>
      <?php foreach ($vs as $v): $vid = (int)$v['id']; ?>
        <tr class="variant-sub">
          <td class="var-name muted"><?= e($v['name']) ?><?= $v['active'] ? '' : ' · вимкнений' ?></td>
          <td><input type="text" name="v[<?= $vid ?>][sku]" value="<?= e($v['sku'] ?? '') ?>"
                     autocomplete="off" <?= $dupSku($v['sku']) ? 'style="border-color:var(--danger2)" title="Такий артикул уже є в іншої позиції"' : '' ?>></td>
          <td><?= $barcodeCell("v[$vid][barcode]", $v['barcode'], $dupBar($v['barcode'])) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </table>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти коди</button>
    <?php /* Генерація йде тією ж кнопкою, що й збереження, — інакше введене
             руками зникло б від натискання «Придумати». Порядок на сервері:
             спершу зберегти набране, потім домалювати те, що лишилось порожнім. */ ?>
    <button class="btn btn-line" type="submit" name="_action" value="generate"
            data-help-title="Кнопка «Придумати коди»"
            data-help="Створює власні штрихкоди всім позиціям, у яких поле порожнє. Уже заповнені не чіпає — фабричний код із етикетки завжди головніший.

Коди беруться з діапазону, який стандарт лишив магазинам для внутрішнього вжитку, тож вони гарантовано не збігаються з жодним чужим товаром у світі.

Код виводиться з номера позиції, тому повторне натискання нічого не переставляє.

Далі їх треба надрукувати й наклеїти: малюнок кожного коду показаний просто тут, сторінка друкується через Ctrl+P.">✨ Придумати коди порожнім</button>
    <span class="admin-save-note"></span>
  </div>
</form>

<script src="<?= e(asset_v('js/barcode.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/scan.js')) ?>" defer></script>
<script>
// Камера в рядку: прочитаний код лягає в те поле, біля якого натиснули 📷,
// і вікно одразу закривається — тут сканують по одному, а не потоком.
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-code-scan]');
  if (!btn) return;
  var input = btn.parentNode.querySelector('[data-code-input]');
  if (!window.BofuScan || !input) return;
  window.BofuScan.open(function (code, ui) {
    input.value = code;
    input.classList.add('is-fresh');
    ui.close();
    input.focus();
  }, {
    title: 'Наведіть камеру на штрихкод товару',
    onError: function (m) { window.alert(m); },
    onManual: function () { input.focus(); }
  });
});
</script>
