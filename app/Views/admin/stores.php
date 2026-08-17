<div class="admin-head"><h1 class="h-serif">Магазини</h1></div>
<?php /* Координати без ключа — робота, яка нікому не видно. Кажемо про це тут,
         а не в налаштуваннях: заповнюють координати саме на цьому екрані */ ?>
<?php if ($maps_key === '' && array_filter($stores, fn($s) => Geo::has($s))): ?>
  <div class="card-warn" style="margin-bottom:16px">
    Координати заповнені, але <b>ключ Google Maps не заданий</b> — карта покупцю не показується.
    Поки що замість неї він бачить адресу з кнопкою «прокласти маршрут»: це працює й без ключа.
    Ключ вписується в <a href="<?= e(url('/admin/settings')) ?>">Налаштуваннях</a>.
  </div>
<?php endif; ?>
<form class="admin-card" method="post" action="<?= e(url('/admin/stores')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div style="flex:1;min-width:160px" data-help-title="Назва магазину"
       data-help="Як точка називається у вас і на сайті: «Головний», «Філія на Подолі».

Покупець бачить цю назву при виборі самовивозу, а продавець — у списку замовлень. Робіть її такою, щоб точку можна було впізнати з одного погляду.

Єдине обовʼязкове поле при створенні — решту можна дозаповнити пізніше.">
    <label>Назва</label><input type="text" name="name" required></div>
  <div data-help-title="Місто"
       data-help="Місто, де стоїть точка.

Саме воно частіше за назву показується там, де мало місця: у виборі магазину, у розкладці залишків, у списку замовлень. Якщо місто задане, у таких місцях покажеться воно, а не назва.

Заповнюйте завжди, коли точок більше однієї, — інакше їх плутатимуть.">
    <label>Місто</label><input type="text" name="city"></div>
  <div data-help-title="Адреса"
       data-help="Вулиця й будинок. Покупець бачить її при виборі самовивозу — саме за нею він до вас приїде.

Пишіть так, як зручно шукати в картах: «вул. Медова, 12».">
    <label>Адреса</label><input type="text" name="address"></div>
  <div data-help-title="Телефон"
       data-help="Контактний номер точки для покупців.

Це номер магазину, а не ваш особистий: він показується на сайті. Сповіщення про замовлення на нього не надсилаються — вони налаштовуються окремо, у розділі «Сповіщення».">
    <label>Телефон</label><input type="text" name="phone"></div>
  <div style="min-width:200px" data-help-title="Координати"
       data-help="Точка на карті. Саме за нею магазин показується покупцю при самовивозі й на сторінці «Де нас знайти».

Звідки взяти: відкрийте Google Maps, знайдіть свій вхід, клацніть по ньому правою кнопкою — у меню перший рядок і буде парою чисел. Клацніть по ньому: воно скопіюється. Сюди можна вставити або цю пару, або просто посилання на місце — розберемо обидва.

Адресу це не замінює: адресу читає людина, координати потрібні карті. Порожнє поле — точка лишається в списку, але без мітки.">
    <label>Координати (необов'язково)</label><input type="text" name="coords" placeholder="50.4501, 30.5234">
    <?php if ($maps_key): ?>
      <button type="button" class="btn btn-line btn-xs" style="margin-top:6px" data-store-pick="нова точка">📍 обрати на карті</button>
    <?php endif; ?></div>
  <button class="btn btn-gold btn-sm" type="submit" data-help-title="Кнопка «Додати»"
          data-help="Створює нову точку одразу активною — вона відразу зʼявиться на сайті у виборі магазину.

Після створення точці треба задати залишки товарів (у картці товару або в масовому редагуванні) і призначити продавців у розділі «Користувачі». Без залишків вона буде порожньою вітриною.">+ Додати</button>
</form>
<form method="post" action="<?= e(url('/admin/stores')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <?php /* Заповнюється кнопкою «створити токен агента»: форма одна на всі
           точки, і сказати, про яку саме йдеться, більше нема як. */ ?>
  <input type="hidden" name="store_id" value="">
  <table class="tbl">
    <tr><th>Назва</th><th>Місто</th><th>Адреса</th><th>Телефон</th>
      <th style="width:220px" data-help-title="Колонка «Координати»"
          data-help="Мітка точки на карті — у самовивозі й на сторінці «Де нас знайти».

Вставляйте пару чисел «50.4501, 30.5234» або посилання з Google Maps: розберемо і те, і те. Кома як десятковий знак («50,4501, 30,5234») теж підійде — саме так копіює система з українською локаллю.

Поруч із заповненим полем зʼявляється «перевірити»: воно відкриває цю точку в Google Maps. Клацніть після заповнення — це єдиний спосіб побачити, що мітка стала на ваш вхід, а не на сусідній квартал.

Порожнє поле — точка лишається в списку самовивозу, але на карті її не буде.">Координати</th>
      <th class="col-mid" data-help-title="Колонка «Активний»"
          data-help="Чи працює точка на сайті просто зараз.

Знята галка означає: магазин зникає з вибору самовивозу, його залишки перестають враховуватись, і нові замовлення на нього не розподіляються.

Уже оформлені замовлення нікуди не діваються — їх усе одно треба виконати.

Це правильний спосіб тимчасово закрити точку (ремонт, відпустка): усі дані, ціни й залишки лишаються на місці, галку можна повернути будь-коли. Видалення магазинів тут немає навмисно — з ними повʼязані замовлення.">Активний</th></tr>
    <?php foreach ($stores as $s): ?>
      <tr>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][name]" value="<?= e($s['name']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][city]" value="<?= e($s['city']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][address]" value="<?= e($s['address']) ?>"></td>
        <td><input type="text" name="store[<?= (int)$s['id'] ?>][phone]" value="<?= e($s['phone']) ?>"></td>
        <td>
          <input type="text" name="store[<?= (int)$s['id'] ?>][coords]" value="<?= e(Geo::format($s)) ?>"
                 placeholder="50.4501, 30.5234">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:5px">
            <?php /* Кнопка є лише з ключем: без нього вона відкрила б порожнє
                     вікно, і людина вирішила б, що зламалось саме натискання */ ?>
            <?php if ($maps_key): ?>
              <button type="button" class="btn btn-line btn-xs"
                      data-store-pick="<?= e($s['name']) ?>">📍 обрати на карті</button>
            <?php endif; ?>
            <?php /* Перевірити мітку можна лише оком на самій карті: пара чисел
                     виглядає правдоподібно й тоді, коли вказує на інший район */ ?>
            <?php if (Geo::has($s)): ?>
              <a class="dim" style="font-size:12px" target="_blank" rel="noopener"
                 href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($s['lat'] . ',' . $s['lng'])) ?>">перевірити →</a>
            <?php endif; ?>
          </div>
        </td>
        <td class="col-mid"><input type="checkbox" name="store[<?= (int)$s['id'] ?>][active]" <?= $s['active'] ? 'checked' : '' ?>></td>
      </tr>
      <?php /* Звідки ця точка відправляє посилки. Окремим рядком під магазином,
               а не колонкою: заповнюють це раз і рідко, а в таблиці з чотирьох
               колонок ще два поля зробили б нечитабельним усе інше. */ ?>
      <tr class="store-np-row">
        <td colspan="6" style="padding-top:0">
          <details<?= trim((string)($s['np_warehouse_ref'] ?? '')) !== '' ? ' open' : '' ?>>
            <summary class="dim" style="cursor:pointer;font-size:13px">
              📦 Відправлення Новою Поштою
              <?= trim((string)($s['np_warehouse'] ?? '')) !== ''
                    ? '— ' . e(trim((string)$s['np_city'] . ', ' . (string)$s['np_warehouse'], ' ,'))
                    : '— зі спільного відділення (Налаштування)' ?>
            </summary>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
              <div style="flex:1;min-width:180px">
                <label class="dim" style="font-size:12px">Місто відправлення</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][np_city]" value="<?= e($s['np_city'] ?? '') ?>"
                       id="npCity<?= (int)$s['id'] ?>" placeholder="як у спільних налаштуваннях"
                       autocomplete="new-password" data-lpignore="true" data-1p-ignore spellcheck="false">
                <input type="hidden" name="store[<?= (int)$s['id'] ?>][np_city_ref]" value="<?= e($s['np_city_ref'] ?? '') ?>" id="npCityRef<?= (int)$s['id'] ?>">
              </div>
              <div style="flex:2;min-width:220px">
                <label class="dim" style="font-size:12px">Відділення відправлення</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][np_warehouse]" value="<?= e($s['np_warehouse'] ?? '') ?>"
                       id="npOffice<?= (int)$s['id'] ?>" placeholder="оберіть зі списку"
                       autocomplete="new-password" data-lpignore="true" data-1p-ignore spellcheck="false">
                <input type="hidden" name="store[<?= (int)$s['id'] ?>][np_warehouse_ref]" value="<?= e($s['np_warehouse_ref'] ?? '') ?>" id="npOfficeRef<?= (int)$s['id'] ?>">
              </div>
              <div style="flex:1;min-width:150px">
                <label class="dim" style="font-size:12px">Телефон відправника</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][np_sender_phone]" value="<?= e($s['np_sender_phone'] ?? '') ?>"
                       placeholder="як у спільних налаштуваннях">
              </div>
            </div>
            <p class="dim" style="margin:8px 0 0;font-size:12px">
              Порожньо — посилки цієї точки їдуть із відділення, вказаного в Налаштуваннях.
              Місто й відділення діють лише разом: одне без одного — накладна в нікуди, тому таке поєднання ігнорується.
            </p>
          </details>
          <?php /* Каса тут із тієї ж причини, що й відділення: у точки вона
                   своя, заповнюють раз і рідко, а в таблиці це була б колонка
                   з шістдесятьма символами токена. */ ?>
          <?php
            $ownRoute = trim((string)($s['fiscal_route'] ?? ''));
            $seen = trim((string)($s['agent_seen_at'] ?? ''));
            $agentAlive = $seen !== '' && strtotime($seen) > time() - 300;
          ?>
          <details<?= $ownRoute !== '' || trim((string)($s['vchasno_token'] ?? '')) !== '' ? ' open' : '' ?> style="margin-top:8px">
            <summary class="dim" style="cursor:pointer;font-size:13px">
              🧾 Каса цієї точки
              <?= $ownRoute !== '' ? '— ' . e(mb_strtolower(FiscalProvider::routeLabel($ownRoute))) : '— як у Налаштуваннях' ?>
            </summary>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
              <div style="flex:1;min-width:220px">
                <label class="dim" style="font-size:12px">Як доходимо до каси</label>
                <select name="store[<?= (int)$s['id'] ?>][fiscal_route]">
                  <option value="">як у Налаштуваннях</option>
                  <?php foreach (FiscalProvider::ROUTES as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $ownRoute === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div style="flex:1;min-width:200px">
                <label class="dim" style="font-size:12px">Податкова група точки</label>
                <select name="store[<?= (int)$s['id'] ?>][vchasno_taxgrp]">
                  <option value="0">як у Налаштуваннях</option>
                  <?php foreach (Vchasno::TAX_GROUPS as $code => $label): ?>
                    <option value="<?= (int)$code ?>"<?= (int)($s['vchasno_taxgrp'] ?? 0) === (int)$code ? ' selected' : '' ?>>
                      <?= (int)$code ?> — <?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <?php /* Поля обох маршрутів показуємо разом: перемикати їх
                     javascript-ом заради двох рядків — більше коду, ніж
                     користі, а заповнене «не те» поле нікому не заважає. */ ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
              <div style="flex:2;min-width:240px">
                <label class="dim" style="font-size:12px">Токен каси (для хмари)</label>
                <input type="password" name="store[<?= (int)$s['id'] ?>][vchasno_token]"
                       value="<?= e($s['vchasno_token'] ?? '') ?>"
                       placeholder="порожньо — спільний токен із Налаштувань"
                       autocomplete="off" spellcheck="false">
              </div>
              <div style="flex:1;min-width:180px">
                <label class="dim" style="font-size:12px">Адреса Device Manager</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][dm_url]"
                       value="<?= e($s['dm_url'] ?? '') ?>" placeholder="http://localhost:3939" spellcheck="false">
              </div>
              <div style="flex:1;min-width:150px">
                <label class="dim" style="font-size:12px">Назва каси в DM</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][dm_device]"
                       value="<?= e($s['dm_device'] ?? '') ?>" placeholder="kasa1" spellcheck="false">
              </div>
              <div style="flex:1;min-width:200px">
                <label class="dim" style="font-size:12px">Підпис під нічним Z-звітом</label>
                <input type="text" name="store[<?= (int)$s['id'] ?>][vchasno_cashier]"
                       value="<?= e($s['vchasno_cashier'] ?? '') ?>" maxlength="100"
                       placeholder="як у Налаштуваннях">
              </div>
            </div>
            <p class="dim" style="margin:8px 0 0;font-size:12px">
              Підпис потрібен лише документам, яких не пробивав ніхто живий, — нічному Z-звіту.
              <b>Чеки продажу завжди підписані іменем того, хто продав</b>, і це поле їх не стосується.
              Заповнюйте, якщо точка належить іншому ФОПу.
            </p>

            <p class="dim" style="margin:8px 0 0;font-size:12px">
              <b>Хмара</b> — чек пробиває наш сервер, ключ підпису лежить у постачальника.
              <b>Каса точки</b> — ключ на флешці чи в папці на касовому ПК, а завдання йому
              приносить агент; назовні в магазині нічого не відкрито.
              <b>Каса на цьому пристрої</b> — те саме, але Device Manager стоїть у самого продавця,
              і маршрут йому вмикають у профілі, а не тут.
            </p>

            <?php if ($ownRoute === 'agent'): ?>
              <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--bg3)">
                <div class="dim" style="font-size:12.5px">
                  Агент:
                  <?php if ($seen === ''): ?>
                    <b>жодного разу не виходив на звʼязок</b> — запустіть його на касовому ПК
                  <?php else: ?>
                    <b><?= $agentAlive ? 'на звʼязку' : 'мовчить' ?></b>,
                    востаннє <?= e(date('d.m H:i', strtotime($seen))) ?>
                  <?php endif; ?>
                </div>
                <button class="btn btn-line btn-xs" type="submit" name="_action" value="agent_token"
                        formnovalidate onclick="this.form.store_id.value='<?= (int)$s['id'] ?>'"
                        style="margin-top:8px"
                        data-help-title="Токен агента"
                        data-help="Ним агент на касовому ПК доводить, що він саме з цієї точки, і забирає лише її чеки.

Токен показується РІВНО ОДИН РАЗ — у базі лишається тільки його відбиток. Скопіюйте його в agent.config.json поруч з агентом.

Натиснути ще раз можна будь-коли, але старий токен одразу перестане працювати, і агента доведеться переналаштувати.">
                  🔑 <?= $seen === '' ? 'Створити токен агента' : 'Створити новий токен' ?></button>
              </div>
            <?php endif; ?>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?= View::partial('partials/np_autocomplete') ?>
  <script>
  // Підказки відділень — на кожен рядок свої: id полів різняться номером точки
  (function(){
    if (!window.npAutocomplete) return;
    <?php foreach ($stores as $s): $i = (int)$s['id']; ?>
    window.npAutocomplete({city: 'npCity<?= $i ?>', office: 'npOffice<?= $i ?>',
                           ref: 'npCityRef<?= $i ?>', officeRef: 'npOfficeRef<?= $i ?>'});
    <?php endforeach; ?>
  })();
  </script>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit" data-help-title="Кнопка «Зберегти»"
            data-help="Зберігає всі зміни в таблиці одразу — правки в кількох рядках підуть одним натисканням.

Поки не натиснете, жодна зміна не застосована: підпис поруч показує «Є незбережені зміни», щойно ви щось поправили.

Якщо спробуєте піти зі сторінки з незбереженими правками, браузер перепитає — щоб півгодини роботи не зникли від випадкового кліку по меню.">💾 Зберегти</button>
    <span class="admin-save-note"></span>
  </div>
</form>

<?php if ($maps_key): ?>
  <?php /* Вікно вибору одне на сторінку, а не на рядок: карта важка, і десять
           прихованих карт коштували б десять завантажень квоти Google за
           відкриття сторінки — при тому, що дивляться щоразу в одну. */ ?>
  <div class="modal-back" id="storePicker">
    <div class="modal modal-wide">
      <h3 id="storePickerTitle">Точка на карті</h3>
      <p class="dim" style="margin:0 0 14px;font-size:13px">
        Знайдіть свій вхід і клацніть по ньому. Мітку можна перетягнути.
        Поки не натиснете «Взяти цю точку», у формі нічого не зміниться.
      </p>
      <div class="store-map" id="storePickerMap" style="height:420px"></div>
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px">
        <b id="storePickerVal" style="font-family:monospace">Точку ще не обрано</b>
        <button type="button" class="btn btn-gold btn-sm" id="storePickerApply"
                style="margin-left:auto" disabled>Взяти цю точку</button>
        <button type="button" class="btn btn-line btn-sm" id="storePickerClear">Прибрати координати</button>
      </div>
    </div>
  </div>
  <script>
    window.STORE_MAP = { key: <?= json_js($maps_key) ?>, fallback: <?= json_js($map_start) ?> };
  </script>
  <script src="<?= e(asset_v('js/map.js')) ?>" defer></script>
  <script src="<?= e(asset_v('js/store-map.js')) ?>" defer></script>
<?php endif; ?>
