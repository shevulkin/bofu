<?php
/** @var array|null $p @var array $variants @var array $variant_options @var array $attrs @var array $dict */
$isNew = $p === null;
$canStore = fn(int $sid): bool => in_array($sid, Auth::storeIds(), true);
// Картку товару веде адмін, продавець — лише ціни й залишки своїх магазинів.
// Форма повторює те, що перевіряє Products::save(): показувати поля, які все одно
// не збережуться, — гірше, ніж не показувати їх зовсім.
$canEdit = Auth::can('products.manage');
$roEdit = $canEdit ? '' : 'disabled';
?>
<div class="admin-head">
  <?php /* Мініатюра біля назви: блок фото лежить у самому низу (він поза формою,
           бо кожна дія з фото — окрема форма), тож без неї до кінця сторінки
           незрозуміло, який саме товар редагуєш. */ ?>
  <div class="prod-title">
    <?php if (!$isNew && $images): ?>
      <a class="prod-thumb" href="#photos" title="Перейти до фотографій">
        <img src="<?= e(asset(Images::displayThumb($images[0]['path']))) ?>" alt="">
        <?php if (count($images) > 1): ?><span class="prod-thumb-n"><?= count($images) ?></span><?php endif; ?>
      </a>
    <?php elseif (!$isNew): ?>
      <a class="prod-thumb is-empty" href="#photos" title="Фото ще немає — додати">🍯</a>
    <?php endif; ?>
    <h1 class="h-serif"><?= $isNew ? 'Новий товар' : e($p['name']) ?></h1>
  </div>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products')) ?>">← До списку</a>
</div>

<?php if (!$canEdit): ?>
  <div class="admin-card" style="border-color:var(--gold)">
    Картку товару (назву, опис, базові ціни, варіанти, фото) редагує адміністратор.
    Вам доступні <b>ціни та залишки ваших магазинів</b> — нижче на цій сторінці.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url($isNew ? '/admin/products/new' : '/admin/products/' . $p['id'])) ?>" id="productForm">
  <?= Csrf::field() ?>
  <?php /* Enter у будь-якому полі має зберігати товар, а не запускати генератор варіантів */ ?>
  <button type="submit" class="submit-default" tabindex="-1" aria-hidden="true"></button>
  <div class="admin-card">
    <h2 class="h-serif">Основне</h2>
    <div class="form-grid">
      <div class="field" data-help-title="Назва товару"
           data-help="Те, що покупець бачить у каталозі, на сторінці товару, у кошику й у листі про замовлення. Єдине обовʼязкове поле.

Пишіть так, як людина шукала б це у пошуку: «Мед липовий» краще за «Липа н/ф 2024».

Обʼєм, вагу чи колір у назву краще не вписувати — для цього є Варіанти нижче: тоді це буде один товар з вибором, а не пʼять окремих карток.">
        <label>Назва *</label><input type="text" name="name" required value="<?= e($p['name'] ?? '') ?>" <?= $roEdit ?>></div>
      <div class="field" data-help-title="Категорія"
           data-help="Розділ каталогу, у якому покупець знайде товар.

Впливає більше, ніж здається: від категорії залежить, чи спрацює акція, задана на категорію, і які характеристики зʼявляться у списку нижче (він підлаштовується під обрану категорію).

Якщо категорія неправильна, товар просто не знайдуть у потрібному розділі.">
        <label>Категорія</label>
        <select name="category_id" id="catSelect" <?= $roEdit ?>>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($p['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" data-help-title="Артикул (SKU)"
           data-help="Ваш внутрішній код товару для обліку: «MED-LIP-05».

У каталозі покупцю не показується, але за ним працює пошук в адмінці — зручно, коли назви схожі й треба знайти рівно те, що на етикетці.

Поле необовʼязкове. Якщо ведете склад у таблиці чи 1С, ставте тут той самий код, що й там.">
        <label>Артикул</label><input type="text" name="sku" value="<?= e($p['sku'] ?? '') ?>" <?= $roEdit ?>></div>
      <div class="field" data-help-title="Штрихкод"
           data-help="Код із етикетки — той, що під смужками (EAN-13, зазвичай 13 цифр). Саме його читає сканер на касі.

Це не те саме, що артикул: артикул придумуєте ви для обліку, штрихкод друкує виробник. Тому й поля два — інакше вони рано чи пізно перетруть одне одного.

Найпростіший спосіб заповнити: станьте курсором у поле й піднесіть сканер до етикетки — він сам «надрукує» код.

У товару з фасовками код належить фасовці, а не товару: заповнюйте його в рядках варіантів нижче.">
        <label>Штрихкод</label><input type="text" name="barcode" value="<?= e($p['barcode'] ?? '') ?>" <?= $roEdit ?>
               inputmode="numeric" autocomplete="off"></div>
      <?php
      $prodBrands = $p ? Catalog::brandsOf($p) : [];
      $chosenBrands = array_map(fn($b) => (int)$b['id'], $prodBrands);
      // неактивний бренд лишається у списку, поки він призначений цьому товару:
      // інакше вибір мовчки злетів би при найближчому збереженні
      $brandList = Catalog::brands(true);
      foreach ($prodBrands as $b) {
          if (!$b['active']) $brandList[] = $b;
      }
      ?>
      <div class="field" style="min-width:260px" data-help-title="Бренди (чий товар)"
           data-help="Хто виробник цієї позиції. Список ведеться в розділі «Каталог → Бренди».

Брендів може бути кілька — це і є спільне виробництво. Позначте свій і партнерів: товар знайдеться в пошуку за кожним із них, а покупець побачить «Виготовляємо разом із «Медоїжка»».

Лише свій бренд — «Виготовимо під замовлення, ми виробник». Лише чужий або жодного — «Виготовляється на замовлення, привеземо для вас».

Це твердження про походження товару, тому вгадувати його сайт не буде: порожньо означає «не наше».

Бренди також показуються покупцю в характеристиках і йдуть у розмітку для Google.">
        <label>Бренди</label>
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php foreach ($brandList as $b): ?>
            <label class="checkbox" style="margin:0">
              <input type="checkbox" name="brand_ids[]" value="<?= (int)$b['id'] ?>"
                     <?= in_array((int)$b['id'], $chosenBrands, true) ? 'checked' : '' ?> <?= $roEdit ?>>
              <span><?= e($b['name']) ?><?php if ($b['own']): ?> <span class="dim">— наш</span><?php endif; ?><?php
                if (!$b['active']): ?> <span class="dim">— неактивний</span><?php endif; ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$brandList): ?>
            <span class="dim">Список порожній — <a href="<?= e(url('/admin/brands')) ?>">додайте бренди</a>.</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="field" data-help-title="Тип"
           data-help="Що це за позиція: звичайний Товар, Послуга, Відео чи Курс.

Для меду й усього, що можна покласти в коробку, лишайте «Товар» — це варіант за замовчуванням.

Решта типів потрібні для нематеріальних позицій, які не возять і не рахують на складі.">
        <label>Тип</label>
        <select name="type" <?= $roEdit ?>>
          <?php foreach (['product' => 'Товар', 'service' => 'Послуга', 'video' => 'Відео', 'course' => 'Курс'] as $t => $lbl): ?>
            <option value="<?= $t ?>" <?= ($p['type'] ?? 'product') === $t ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" data-help-title="Базова ціна"
           data-help="Ціна за замовчуванням, у гривнях.

Її можуть перебити, у такому порядку: окрема ціна магазину (таблиця нижче), потім ціна варіанта, а вже на результат накладається акція.

Порожнє поле означає «За запитом»: покупець побачить не число, а пропозицію звʼязатися. Використовуйте свідомо — товар без ціни купують значно рідше.">
        <label>Базова ціна, грн (порожньо = «За запитом»)</label><input type="number" step="0.01" name="base_price" value="<?= e(num_val($p['base_price'] ?? '')) ?>" <?= $roEdit ?>></div>
      <div class="field" data-help-title="Стара ціна"
           data-help="Закреслена ціна поруч із поточною — щоб показати вигоду.

Показується тільки якщо вона більша за поточну; інакше сайт її просто проігнорує.

Увага: товар зі старою ціною вважається «вже зі знижкою». Промокод із вимкненим «Сумується з акціями» на нього не подіє, а стеля знижки рахуватиме цю різницю. Не ставте сюди вигадану ціну «для краси» — вона впливає на реальні розрахунки.

Якщо знижка тимчасова, правильніше створити Акцію: вона сама закреслить стару ціну й сама закінчиться в потрібний день.">
        <label>Стара ціна (закреслена)</label><input type="number" step="0.01" name="old_price" value="<?= e(num_val($p['old_price'] ?? '')) ?>" <?= $roEdit ?>></div>
    </div>
    <div class="field" data-help-title="Короткий опис"
         data-help="Один рядок, який показується під назвою в каталозі — там, де покупець швидко переглядає багато товарів.

Скажіть головне: «Зібраний у липні на Полтавщині, 0.5 л».

Довгий текст тут обріжеться — для нього є «Повний опис» нижче.">
      <label>Короткий опис</label><input type="text" name="short_desc" value="<?= e($p['short_desc'] ?? '') ?>" <?= $roEdit ?>></div>
    <div class="field" data-help-title="Повний опис"
         data-help="Розгорнутий текст на сторінці товару: походження, смак, як зберігати, чим корисний.

Переноси рядків зберігаються, тож можна писати абзацами. Розмітка не підтримується — це звичайний текст.

Технічні дані (вага, обʼєм, регіон) краще виносити в Характеристики нижче: за ними працюють фільтри в каталозі, а за текстом опису — ні.">
      <label>Повний опис</label><textarea name="description" rows="5" <?= $roEdit ?>><?= e($p['description'] ?? '') ?></textarea></div>
    <div style="display:flex;gap:26px;flex-wrap:wrap">
      <label class="checkbox" data-help-title="Активний"
             data-help="Чи показувати товар на сайті просто зараз.

Знята галка — товар зникає з каталогу й пошуку, купити його неможливо. При цьому нічого не втрачається: фото, опис, ціни й залишки лишаються, галку можна повернути будь-коли.

Так правильно ховати сезонні позиції й те, що тимчасово не продаєте, — замість видаляти картку.">
        <input type="checkbox" name="active" <?= ($p['active'] ?? 1) ? 'checked' : '' ?> <?= $roEdit ?>> Активний (видно на сайті)</label>
      <label class="checkbox" data-help-title="Рекомендований"
             data-help="Піднімає товар угору каталогу й позначає його як «Хіт».

Ставте небагатьом позиціям. Якщо позначити половину каталогу, позначка перестане щось означати, а сортування — допомагати.">
        <input type="checkbox" name="featured" <?= ($p['featured'] ?? 0) ? 'checked' : '' ?> <?= $roEdit ?>> Рекомендований (на головній)</label>
      <label class="checkbox" data-help-title="Виготовляємо під замовлення"
             data-help="Змінює те, що бачить покупець, коли товару немає на складі.

Галка стоїть — товар можна замовити в будь-якій кількості, навіть коли на складі порожньо. Замість «немає в наявності» покупець побачить, що позицію зроблять під замовлення. Текст залежить від поля «Бренд»: свій товар — «ми виробник», чужий — нейтральне «привеземо для вас».

Галка знята — продаємо лише те, що є: покласти в кошик більше, ніж лишилось у мережі магазинів, сайт не дасть, а на нулі кнопка стане «Немає в наявності».

Тобто це не лише напис, а й межа продажу. Знімайте галку там, де виготовити додатково неможливо.">
        <input type="checkbox" name="made_to_order" <?= ($p['made_to_order'] ?? 1) ? 'checked' : '' ?> <?= $roEdit ?>> Виготовляємо під замовлення</label>
    </div>
    <div class="field" style="max-width:360px" data-help-title="Поріг «закінчується»"
         data-help="З якої кількості показувати покупцю «закінчується» замість «в наявності».

Поставте 3 — і щойно в магазині лишиться 3 штуки або менше, поруч із цією точкою зʼявиться «закінчується». Це підштовхує не відкладати покупку.

Рахується окремо по кожному магазину, а не по всій мережі.

Порожньо — попередження не показується взагалі, буде просто «в наявності».">
      <label>Поріг «закінчується», шт.</label>
      <input type="number" min="0" step="1" name="low_stock_threshold" value="<?= e($p['low_stock_threshold'] ?? '') ?>" placeholder="порожньо — показувати просто «в наявності»" <?= $roEdit ?>>
    </div>
    <div class="field" data-help-title="Вага, кг"
         data-help="Скільки важить одна штука разом із тарою — це те, за чим Нова Пошта рахує доставку.

Проставте — і форма накладної сама порахує вагу посилки: вага × кількість по всіх позиціях. Продавцю лишиться звірити, а не зважувати кожне замовлення.

Порожньо — береться типова вага з Налаштувань. Це не помилка, просто накладна буде приблизною, і НП може перерахувати за фактом.

У товару з фасовками вагу вказують у самій фасовці: «мед узагалі» не важить нічого, важить банка на 0.5 чи на 1.5 кг.">
      <label>Вага, кг</label>
      <input type="text" name="weight" value="<?= e(num_val($p['weight'] ?? null)) ?>" placeholder="0.5 — для розрахунку накладної" <?= $roEdit ?>>
    </div>
  </div>

  <?php if (!$isNew): ?>
  <?php if ($canEdit): ?>
  <div class="admin-card">
    <h2 class="h-serif">Характеристики</h2>
    <p class="dim" style="margin:-8px 0 14px">
      Обирайте зі спільного словника — так значення однакові в усіх товарів і працюють фільтри.
      Список залежить від категорії товару.
      <?php if (Auth::can('catalog.manage')): ?><a href="<?= e(url('/admin/attributes')) ?>">Керувати словником →</a><?php endif; ?>
    </p>
    <div id="attrRows" class="row-list"></div>
    <button class="btn btn-line btn-sm" type="button" id="attrAdd" style="margin-top:12px">+ Додати характеристику</button>
  </div>

  <div class="admin-card">
    <h2 class="h-serif">Варіанти</h2>
    <p class="dim" style="margin:-8px 0 14px">Різні виконання того самого товару: розмір, колір, об'єм. Покупець обирає їх на сторінці товару.</p>

    <div class="row-list" id="variantRows">
      <?php foreach ($variants as $v): $vid = (int)$v['id']; $opts = $variant_options[$vid] ?? []; ?>
        <div class="grid-row variant-row" data-vid="<?= $vid ?>">
          <div class="gr-main">
            <?php if ($opts): ?>
              <div class="variant-tags">
                <?php foreach ($opts as $o): ?>
                  <span class="tag" title="<?= e($o['attr_name']) ?>">
                    <?php if (!empty($o['color'])): ?><i class="swatch" style="background:<?= e($o['color']) ?>"></i><?php endif; ?>
                    <?= e($o['attr_name']) ?>: <b><?= e($o['value']) ?></b>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <input type="text" name="variant[<?= $vid ?>][name]" value="<?= e($v['name']) ?>" placeholder="Назва варіанта">
            <?php endif; ?>
          </div>
          <input type="number" step="0.01" name="variant[<?= $vid ?>][price]" value="<?= e(num_val($v['price'])) ?>" placeholder="ціна" title="Порожньо = базова ціна товару">
          <input type="text" name="variant[<?= $vid ?>][sku]" value="<?= e($v['sku'] ?? '') ?>" placeholder="артикул">
          <?php /* Штрихкод належить фасовці: етикетку клеять на банку, а не на «мед узагалі» */ ?>
          <input type="text" name="variant[<?= $vid ?>][barcode]" value="<?= e($v['barcode'] ?? '') ?>"
                 placeholder="штрихкод" inputmode="numeric" autocomplete="off">
          <?php /* Вага теж належить фасовці — за нею рахується накладна */ ?>
          <input type="text" name="variant[<?= $vid ?>][weight]" value="<?= e(num_val($v['weight'] ?? null)) ?>"
                 placeholder="вага, кг" title="Вага однієї штуки — для розрахунку доставки">
          <label class="checkbox" title="Показувати покупцям"><input type="checkbox" name="variant[<?= $vid ?>][active]" <?= $v['active'] ? 'checked' : '' ?>> вкл.</label>
          <button class="btn btn-danger btn-xs row-del" type="button" title="Видалити варіант">✕</button>
          <input type="hidden" name="variant[<?= $vid ?>][_delete]" value="" disabled>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-line btn-sm" type="button" id="variantAdd" style="margin-top:12px">+ Додати варіант вручну</button>

    <div class="gen-box" id="genBox" style="margin-top:22px">
      <b>Згенерувати варіанти з характеристик</b>
      <p class="dim" style="margin:6px 0 12px">Відмітьте значення — створяться всі їхні комбінації (напр. 3 розміри × 2 кольори = 6 варіантів). Наявні комбінації не дублюються.</p>
      <div id="genAxes"></div>
      <button class="btn btn-gold btn-sm" type="submit" name="_action" value="gen_variants" style="margin-top:12px">⚙ Створити комбінації</button>
    </div>
  </div>
  <?php endif; ?>

  <?php $activeVariants = array_values(array_filter($variants, fn($v) => (int)$v['active'] === 1)); ?>
  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Ціни та залишки по магазинах"
        data-help="Таблиця, де для кожної точки задають свою ціну й свою кількість.

Ціна: порожньо — діє базова ціна товару. Заповнили — у цьому магазині діятиме саме вона. Так роблять, коли в різних містах різна собівартість.

Залишок: скільки штук фізично лежить у цій точці. Від нього залежить, який магазин отримає замовлення: система віддає його туди, де вистачає на всю кількість.

Чесні цифри тут важливіші, ніж здається: замовити понад залишок сайт дозволяє, і тоді розбиратися доведеться вам уже після покупки.">
      Ціни та залишки по магазинах</h2>
    <p class="dim" style="margin:-8px 0 14px">
      Порожня ціна = діє базова. Порожній залишок = немає в наявності (товар усе одно можна замовити, якщо ввімкнено «під замовлення»).
      <?php if ($activeVariants): ?><br>У товару є варіанти, тому наявність рахується <b>по варіантах у кожному магазині</b> — залишок «без варіанта» не враховується.<?php endif; ?>
    </p>
    <div style="overflow-x:auto">
      <table class="tbl matrix">
        <tr>
          <th data-help-title="Колонка «Магазин»"
              data-help="Точки вашої мережі. Кожен рядок — окремий склад зі своєю ціною й кількістю.

Якщо ви продавець, редагувати можна лише рядки своїх точок — чужі показані, але заблоковані.

Список береться з розділу «Магазини». Вимкнені там точки сюди не потрапляють.">Магазин</th>
          <th colspan="2" data-help-title="Товар без варіанта"
              data-help="Ціна й залишок для товару, у якого немає варіантів.

Якщо у товару є хоч один увімкнений варіант, ці два стовпці перестають враховуватись: наявність тоді рахується лише по варіантах. Цифри звідси не зникнуть, але на сайт не вплинуть.

Тобто заповнюйте цю пару тільки для простих товарів — тих, що продаються в одному виконанні.">Товар без варіанта</th>
          <?php foreach ($variants as $v): ?><th colspan="2"
              data-help-title="Варіант «<?= e($v['name']) ?>»"
              data-help="Ціна й залишок саме цього виконання товару в кожному магазині.

Позначка «(вимк.)» означає, що варіант вимкнений: покупець його не бачить і його залишок не враховується в наявності.

Кожен варіант рахується окремо: у точці може бути 10 банок 0.5 л і жодної 1 л."><?= e($v['name']) ?><?= (int)$v['active'] === 1 ? '' : ' (вимк.)' ?></th><?php endforeach; ?>
        </tr>
        <tr class="sub"><th></th>
          <th data-help-title="Ціна в магазині"
              data-help="Ціна саме в цій точці. Порожньо — діє базова ціна товару.

Заповнена тут ціна має пріоритет над базовою й над ціною варіанта. Акція накладається вже на результат: магазинна ціна 200 грн з акцією 15% дасть 170 грн.">ціна</th>
          <th data-help-title="Залишок, шт"
              data-help="Скільки штук зараз фізично в цій точці.

Від цієї цифри залежить розподіл замовлень: система шукає магазин, де вистачає на всю кількість, і лише потім — де є хоч щось.

Порожньо дорівнює нулю. Залишок зменшується сам, коли товар замовляють, і повертається, якщо позицію передали іншій точці.">шт</th>
          <?php foreach ($variants as $v): ?><th data-help-title="Ціна варіанта в магазині"
              data-help="Ціна цього варіанта саме в цій точці. Порожньо — діє ціна варіанта, а якщо й вона порожня, то базова ціна товару.">ціна</th><th
              data-help-title="Залишок варіанта, шт"
              data-help="Скільки штук цього варіанта зараз у цій точці. Саме ці числа складаються в наявність товару, коли у нього є варіанти.">шт</th><?php endforeach; ?>
        </tr>
        <?php foreach ($stores as $s): $sid = (int)$s['id']; $ro = $canStore($sid) ? '' : 'disabled title="Немає доступу до цього магазину"'; ?>
          <tr>
            <td><?= e($s['name']) ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></td>
            <td><input type="number" step="0.01" name="store_price[<?= $sid ?>]" value="<?= e(num_val($store_prices[$sid] ?? '')) ?>" placeholder="базова" <?= $ro ?>></td>
            <td>
              <?php if ($activeVariants): ?>
                <?php $sum = 0; foreach ($activeVariants as $v) $sum += (int)($variant_stock[(int)$v['id']][$sid] ?? 0); ?>
                <span class="dim" title="Сума по варіантах цього магазину — редагуйте в колонках варіантів">Σ <?= $sum ?></span>
              <?php else: ?>
                <input type="number" name="store_stock[<?= $sid ?>]" value="<?= e($store_stock[$sid] ?? '') ?>" <?= $ro ?>>
              <?php endif; ?>
            </td>
            <?php foreach ($variants as $v): $vid = (int)$v['id']; ?>
              <td><input type="number" step="0.01" name="vprice[<?= $vid ?>][<?= $sid ?>]" value="<?= e(num_val($variant_prices[$vid][$sid] ?? '')) ?>" placeholder="базова" <?= $ro ?>></td>
              <td><input type="number" name="vstock[<?= $vid ?>][<?= $sid ?>]" value="<?= e($variant_stock[$vid][$sid] ?? '') ?>" <?= $ro ?>></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php else: ?>
    <p class="dim">Характеристики, варіанти та ціни по магазинах зʼявляться одразу після створення товару.</p>
  <?php endif; ?>

  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    <span class="admin-save-note"></span>
    <?php if (!$isNew && Auth::can('products.manage')): ?>
      <button class="btn btn-danger" type="submit" name="_action" value="delete" style="margin-left:auto"
        onclick="return confirm('Видалити товар разом із фото та цінами?')">Видалити товар</button>
    <?php endif; ?>
  </div>
</form>

<?php if (!$isNew && $canEdit): ?>
<div class="admin-card" id="photos" style="margin-top:22px;scroll-margin-top:16px">
  <h2 class="h-serif">Фотографії</h2>
  <p class="dim" style="margin:0 0 14px">Перше фото — головне: воно у каталозі, кошику та при поширенні в соцмережах.
    Решта показуються мініатюрами на сторінці товару в цьому ж порядку.</p>
  <div class="img-grid">
    <?php $last = count($images) - 1; foreach ($images as $i => $img): $isMain = $i === 0; ?>
      <div class="img-cell<?= $isMain ? ' is-main' : '' ?>">
        <img src="<?= e(asset(Images::displayThumb($img['path']))) ?>" alt="">
        <?php if ($isMain): ?><span class="img-badge">Головне</span><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>" class="img-del"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete_image">
          <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
          <button class="btn btn-danger btn-xs" style="padding:3px 8px" title="Прибрати з товару" onclick="return confirm('Прибрати фото з товару?')">✕</button>
        </form>
        <div class="img-actions">
          <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>"><?= Csrf::field() ?>
            <input type="hidden" name="_action" value="move_image">
            <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
            <button class="btn btn-line btn-xs" name="dir" value="up" title="Раніше" <?= $i === 0 ? 'disabled' : '' ?>>←</button>
            <button class="btn btn-line btn-xs" name="dir" value="down" title="Пізніше" <?= $i === $last ? 'disabled' : '' ?>>→</button>
          </form>
          <?php if (!$isMain): ?>
            <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>"><?= Csrf::field() ?>
              <input type="hidden" name="_action" value="main_image">
              <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
              <button class="btn btn-line btn-xs" title="Зробити головним">★ Головне</button>
            </form>
          <?php endif; ?>
        </div>
        <span class="dim"><?= (int)$img['width'] ?>×<?= (int)$img['height'] ?> · <?= round($img['bytes'] / 1024) ?> КБ</span>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-gold btn-sm" type="button" onclick="MediaPicker.open(function(path){
      var f = document.getElementById('attachImageForm');
      f.querySelector('[name=media_path]').value = path; f.submit();
    })">📷 Додати фото (з сайту або з ПК)</button>
    <span class="dim">Фото автоматично стискається і адаптується; розмір показано під мініатюрою</span>
  </div>
  <form method="post" action="<?= e(url('/admin/products/' . $p['id'])) ?>" id="attachImageForm" style="display:none">
    <?= Csrf::field() ?>
    <input type="hidden" name="_action" value="attach_image">
    <input type="hidden" name="media_path" value="">
  </form>
</div>
<?= View::partial('partials/media_picker') ?>

<script>
window.BOFU_DICT = <?= json_js($dict) ?>;
window.BOFU_PRODUCT_ATTRS = <?= json_js(array_map(fn($a) => [
    'attribute_id' => (int)$a['attribute_id'],
    'value_id' => (int)$a['value_id'],
    'value' => $a['value'],
], array_values(array_filter($attrs, fn($a) => !empty($a['attribute_id']))))) ?>;
</script>
<script src="<?= e(asset_v('js/product-form.js')) ?>" defer></script>
<?php endif; ?>
