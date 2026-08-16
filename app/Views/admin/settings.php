<div class="admin-head"><h1 class="h-serif">Налаштування</h1></div>
<form method="post" action="<?= e(url('/admin/settings')) ?>">
  <?= Csrf::field() ?>
  <?php
  // Головний вимикач стоїть над іншими не для краси: у Notify::fire() він —
  // жорсткий гейт, і поки він вимкнений, канальні перемикачі ні на що не
  // впливають. Тому вони й показуються неактивними, а не просто стоять поруч.
  $master = 'notify_all_enabled';
  $masterOn = Settings::bool($master, true);
  $channels = array_diff_key($toggles, [$master => 1]);
  ?>
  <div class="admin-card">
    <h2 class="h-serif">Канали сповіщень</h2>
    <p class="dim" style="margin-bottom:16px">Тексти повідомлень і події — в розділі «Сповіщення».</p>
    <label class="toggle" data-help-title="Головний вимикач сповіщень"
           data-help="Загальний рубильник: поки він вимкнений, не надсилається НІЧОГО, у жоден канал.

Канальні перемикачі нижче при цьому не скидаються — вони просто гаснуть і ні на що не впливають. Увімкнете головний назад, і все повернеться як було.

Беріть його, коли треба швидко припинити всі сповіщення: тестуєте щось на живому сайті або розбираєтесь із помилковою розсилкою.

Для повсякденної роботи він має бути увімкнений.">
      <input type="checkbox" name="toggle[<?= e($master) ?>]" data-master <?= $masterOn ? 'checked' : '' ?>>
      <span class="tr"></span> <b><?= e($toggles[$master]) ?></b>
    </label>
    <div class="toggle-group <?= $masterOn ? '' : 'is-off' ?>" data-group
         data-help-title="Канали сповіщень"
         data-help="Якими шляхами взагалі дозволено надсилати повідомлення: Telegram, Viber, email, пуші.

Це загальний дозвіл на канал. Які саме події яким каналом ідуть і яким текстом — налаштовується окремо, у розділі «Сповіщення».

Вимкнений тут канал не спрацює в жодній події, навіть якщо там він увімкнений.

Канал працює лише тоді, коли для нього заповнені ключі в блоці «Інтеграції та SEO» нижче. Перемикач без ключів нічого не надішле.">

      <?php foreach ($channels as $key => $label): ?>
        <label class="toggle">
          <input type="checkbox" name="toggle[<?= e($key) ?>]" data-child
                 <?= Settings::bool($key, true) ? 'checked' : '' ?> <?= $masterOn ? '' : 'disabled' ?>>
          <span class="tr"></span> <?= e($label) ?>
        </label>
      <?php endforeach; ?>
      <p class="dim toggle-group-note" style="margin:2px 0 0"<?= $masterOn ? ' hidden' : '' ?>>
        Поки головний вимикач вимкнено, не надсилається нічого — налаштування каналів збережені й
        повернуться, щойно ви його ввімкнете.
      </p>
      <?php
      // Головний увімкнено, а канали всі вимкнені — стан, який виглядає
      // налаштованим і при цьому мовчить. Найлегше не помітити саме його.
      $anyChannel = false;
      foreach ($channels as $k => $l) if (Settings::bool($k, true)) { $anyChannel = true; break; }
      ?>
      <p class="check-row is-warn" data-nochan style="margin:4px 0 0"<?= ($masterOn && !$anyChannel) ? '' : ' hidden' ?>>
        <span class="check-icon">⚠️</span>
        <span>Головний вимикач увімкнено, але <b>жоден канал не активний</b> — сповіщення нікуди не підуть.
        Увімкніть хоча б один.</span>
      </p>
    </div>
  </div>
  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Замовлення"
        data-help="Як система поводиться із замовленнями, коли товару немає на складі.

Замовлення розкладається між магазинами автоматично: кожна позиція дістається тій точці, де вона є. Але позицію можна замовити й тоді, коли її немає ніде — товар доробить виробник. Таку позицію теж комусь треба віддати, інакше вона зависне без відповідального.

Магазин за замовчуванням — це і є та точка, яка їх приймає. Ставте сюди основну: ту, де виробництво, або ту, за якою найуважніше стежать.">
      Замовлення</h2>
    <div class="field" data-help-title="Магазин за замовчуванням"
         data-help="Кому дістаються позиції, яких немає на складі жодної точки, — те, що виготовляється під замовлення.

Такі позиції потрапляють у звичайне підзамовлення цього магазину зі звичайним статусом. Продавець бачить у ньому позначку «замовлено понад залишок» і вирішує: передати іншій точці, де товар є, або довиробити.

Якщо не вибрати нічого, їх забирає перша активна точка за порядком сортування. Це працює, але залежить від порядку в списку магазинів: переставили точки місцями — і замовлення почали падати іншому продавцю, ніж досі. Тому краще вибрати явно.">
      <label>Магазин за замовчуванням <span class="dim">— кому йдуть позиції «під замовлення»</span></label>
      <select name="default_store_id">
        <option value="">Перша активна точка (за порядком)</option>
        <?php foreach ($stores as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= ($default_store_set && (int)$s['id'] === $default_store_id) ? 'selected' : '' ?>><?= e($s['name'] . ($s['city'] ? ', ' . $s['city'] : '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <p class="dim" style="margin:12px 0 0">
      Позиції, яких немає на складі жодної точки, виконує саме цей магазин: товар доробить виробник,
      але відповідальний за замовлення має бути з першої хвилини.
      <?php if (!$default_store_set): ?>
        <br>Зараз вибору немає, і їх забирає
        <b style="color:var(--gold)"><?php
          $auto = '';
          foreach ($stores as $s) if ((int)$s['id'] === $default_store_id) $auto = $s['name'];
          echo e($auto !== '' ? $auto : 'жодна — активних магазинів немає');
        ?></b> — перша активна точка. Порядок магазинів може змінитись, тож надійніше вибрати явно.
      <?php endif; ?>
    </p>
  </div>
  <div class="admin-card">
    <h2 class="h-serif">Видимість для пошукових систем</h2>
    <label class="toggle" data-help-title="Закрити сайт від пошукових систем"
           data-help="Просить Google та інші пошуковики не показувати сайт у результатах пошуку.

Вмикайте, поки сайт наповнюють і тестують: щоб недороблені сторінки не потрапили у видачу.

ОБОВʼЯЗКОВО вимкніть перед запуском. Поки перемикач стоїть, сайт не зʼявиться в Google взагалі, скільки б реклами ви не давали. Поки він увімкнений, угорі кожної сторінки адмінки висить червоне нагадування.

Це не захист паролем: сторінки лишаються доступними всім, хто знає адресу. Пошуковики просто не додають їх у видачу, а вже проіндексовані зникають за кілька днів.">
      <input type="checkbox" name="seo_noindex" <?= Settings::bool('seo_noindex') ? 'checked' : '' ?>>
      <span class="tr"></span> Закрити сайт від пошукових систем
    </label>
    <p class="dim" style="margin:14px 0 0">Поки увімкнено: у <code>robots.txt</code> стоїть <code>Disallow: /</code>,
      на кожній сторінці — <code>noindex, nofollow</code>, карта сайту порожня. Зручно на час налаштування й тестів.
      <b style="color:var(--gold)">Не забудьте вимкнути перед запуском</b> — інакше сайт не потрапить у Google.</p>
    <p class="dim" style="margin:8px 0 0">Це не захист: сторінки лишаються доступними всім, хто знає адресу.
      Пошуковики просто не додають їх у видачу, а вже проіндексовані зникають протягом кількох днів.</p>
  </div>
  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Інтеграції та SEO"
        data-help="Ключі й адреси, якими сайт зʼєднується із зовнішніми сервісами: месенджери, пошта, Нова Пошта, аналітика.

Значення беруться в кабінеті відповідного сервісу. Поля з ключами й токенами показані крапками — це навмисно, щоб їх не підгледіли через плече.

Не вставляйте сюди чужі чи тимчасові ключі «просто спробувати»: від них залежить, чи дійдуть сповіщення про замовлення.

Кнопка перевірки нижче пробує зʼєднання тими значеннями, що зараз у полях, і нічого не зберігає — можна переконатися до збереження.">
      Інтеграції та SEO</h2>
    <?php /* Одна колонка, а не дві: тут не короткі підписи, а ключі, токени й
             адреси на пів сотні символів. У вузькому полі видно початок і
             крапки — звірити його з кабінетом сервісу неможливо. */ ?>
    <div class="form-grid form-grid-1">
      <?php foreach ($text_keys as $key => $label): ?>
        <?php $secret = str_contains($key, 'secret') || str_contains($key, 'token') || str_contains($key, 'key'); ?>
        <div class="field" data-help-title="<?= e($label) ?>"
             data-help="Значення для інтеграції «<?= e($label) ?>».

Візьміть його в кабінеті відповідного сервісу й вставте сюди повністю, без пробілів на початку та в кінці.

Порожнє поле означає, що інтеграція вимкнена: повʼязаний з нею канал сповіщень не працюватиме, навіть якщо його перемикач увімкнений.

Перевірте зʼєднання кнопкою нижче до того, як зберігати.">
          <label><?= e($label) ?></label>
          <?php if ($secret): ?>
            <?php /* Крапки лишаються за замовчуванням — ключі не мають світитись
                     на екрані просто так. Але звірити збережене з тим, що в
                     кабінеті сервісу, треба вміти, інакше єдиний спосіб
                     переконатись — перезаписати наосліп. */ ?>
            <div class="field-secret">
              <input type="password" name="text[<?= e($key) ?>]"
                     value="<?= e(Settings::get($key, '')) ?>" autocomplete="off" spellcheck="false">
              <button type="button" class="field-eye" data-eye
                      title="Показати значення" aria-label="Показати значення">👁</button>
            </div>
          <?php else: ?>
            <input type="text" name="text[<?= e($key) ?>]"
                   value="<?= e(Settings::get($key, '')) ?>" autocomplete="off" spellcheck="false">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php /* Перевірка бере значення просто з полів і нічого не зберігає — інакше
             помилковий токен осідав би в базі ще до того, як стало ясно, що він
             помилковий. Виклики лише читальні: webhook не переставляємо, листів
             і повідомлень не шлемо, бо вони пішли б живим людям. */ ?>
    <div class="check-bar">
      <button class="btn btn-line btn-sm" type="button" id="checkBtn"
              data-help-title="Перевірити зʼєднання"
              data-help="Пробує звʼязатися з Telegram, Viber і Новою Поштою тими значеннями, які ЗАРАЗ у полях вище — навіть якщо ви їх ще не зберегли.

Нічого не зберігає й нікому не пише: запити лише читальні, живі люди повідомлень не отримають.

Результат зʼявиться списком нижче: зелена галка — працює, жовтий знак — щось не так, хрестик — не зʼєдналось.

Робіть це щоразу після зміни ключів. Інакше про непрацюючу інтеграцію дізнаєтесь тоді, коли загубиться сповіщення про замовлення.">🔍 Перевірити з&#39;єднання</button>
      <span class="dim" id="checkNote">Питає Telegram, Viber і Нову Пошту тим, що зараз у полях. Нічого не зберігає.</span>
    </div>
    <div id="checkResult" class="check-list" hidden></div>
    <p class="dim">Google OAuth: створіть ключі в Google Cloud Console → OAuth 2.0 Client ID, redirect URI: <code><?= e(GoogleAuth::redirectUri()) ?></code></p>
    <p class="dim">Telegram: створіть бота через @BotFather, вставте токен. Продавці/адміни отримують Chat ID, написавши боту /start (ID показує, напр., @userinfobot).</p>
    <p class="dim">Нова Пошта: безкоштовний API-ключ у особистому кабінеті novaposhta.ua → Налаштування → Безпека.</p>
    <p class="dim">Web Push: ключі згенеровано автоматично. Пуші на телефоні запрацюють після переносу на HTTPS-домен.</p>
  </div>

  <?php /* Відправник накладних. Це не «ще одна інтеграція», а те, що надрукують
           на кожній посилці, — тому окремою карткою й із поясненням, звідки
           беруться значення. Без цих полів кнопка «створити накладну» в
           замовленні чесно скаже, чого бракує, замість мовчазної відмови НП. */ ?>
  <?php /* id — щоб посилання з картки замовлення («накладну не створити,
           бо немає відправника») вело просто сюди, а не на початок сторінки */ ?>
  <div class="admin-card" id="np-sender">
    <h2 class="h-serif" data-help-title="Відправник Нової Пошти"
        data-help="Дані, з якими створюються експрес-накладні: хто відправник, хто контактна особа, з якого відділення несуть посилки.

Контрагента й контактну особу беремо з вашого кабінету НП — натисніть «Підтягнути», і списки заповняться самі. Вручну ці поля не вигадують: у накладній має стояти саме той контрагент, від імені якого ви відправляєте.

Місто й відділення відправлення — те, куди ви фізично приносите посилки.

Магазин може мати власне відділення відправлення (Магазини → правка точки). Тоді ці налаштування для нього не діють — посилку понесуть у сусіднє відділення, а не через пів країни.">
      Нова Пошта: відправник</h2>
    <p class="dim" style="margin-bottom:16px">
      Ці дані друкуються на кожній накладній. Контрагента й контактну особу підтягніть із кабінету НП —
      вигадати їх не можна, накладна створюється саме від їхнього імені.
      <?php if (!$np_enabled): ?><br><b>Спершу впишіть API-ключ вище й збережіть.</b><?php endif; ?>
    </p>

    <div class="check-bar" style="margin-bottom:16px">
      <button class="btn btn-line btn-sm" type="button" id="npLoadBtn">⬇️ Підтягнути з Нової Пошти</button>
      <span class="dim" id="npLoadNote">Читає список відправників вашого кабінету. Нічого не змінює.</span>
    </div>

    <div class="form-grid">
      <div class="field">
        <label>Контрагент-відправник</label>
        <select name="np[np_sender_ref]" id="npSenderRef">
          <option value="<?= e(Settings::get('np_sender_ref', '')) ?>">
            <?= e(Settings::get('np_sender_name', '') ?: (Settings::get('np_sender_ref', '') ? 'Збережений відправник' : '— не обрано —')) ?>
          </option>
        </select>
        <input type="hidden" name="np[np_sender_name]" id="npSenderName" value="<?= e(Settings::get('np_sender_name', '')) ?>">
      </div>
      <div class="field">
        <label>Контактна особа</label>
        <select name="np[np_sender_contact_ref]" id="npContactRef">
          <option value="<?= e(Settings::get('np_sender_contact_ref', '')) ?>">
            <?= e(Settings::get('np_sender_contact_name', '') ?: (Settings::get('np_sender_contact_ref', '') ? 'Збережена особа' : '— не обрано —')) ?>
          </option>
        </select>
        <input type="hidden" name="np[np_sender_contact_name]" id="npContactName" value="<?= e(Settings::get('np_sender_contact_name', '')) ?>">
      </div>
      <div class="field">
        <label>Телефон відправника</label>
        <input type="text" name="np[np_sender_phone]" id="npSenderPhone" value="<?= e(Settings::get('np_sender_phone', '')) ?>" placeholder="0501234567">
      </div>
      <div class="field">
        <label>Місто відправлення</label>
        <input type="text" name="np[np_sender_city]" id="npCity" value="<?= e(Settings::get('np_sender_city', '')) ?>" placeholder="Почніть вводити місто…" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false">
        <input type="hidden" name="np[np_sender_city_ref]" id="npCityRef" value="<?= e(Settings::get('np_sender_city_ref', '')) ?>">
      </div>
      <div class="field" style="grid-column:1/-1">
        <label>Відділення відправлення</label>
        <input type="text" name="np[np_sender_warehouse]" id="npOffice" value="<?= e(Settings::get('np_sender_warehouse', '')) ?>" placeholder="Номер або адреса відділення" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-form-type="other" spellcheck="false">
        <input type="hidden" name="np[np_sender_warehouse_ref]" id="npOfficeRef" value="<?= e(Settings::get('np_sender_warehouse_ref', '')) ?>">
        <p class="field-hint">Обирайте зі списку — накладна створюється за посиланням на відділення, а не за його назвою.</p>
      </div>
    </div>

    <h3 class="h-serif" style="font-size:16px;margin:22px 0 12px">Типова накладна</h3>
    <p class="dim" style="margin-bottom:14px">Чим заповнюється форма відправлення. Продавець може змінити будь-що перед створенням.</p>
    <div class="form-grid">
      <div class="field">
        <label>Хто платить за доставку</label>
        <select name="np[np_payer]">
          <?php foreach ($np_payers as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= Settings::get('np_payer', 'Recipient') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Спосіб оплати</label>
        <select name="np[np_payment]">
          <?php foreach ($np_payments as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= Settings::get('np_payment', 'Cash') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Опис вантажу</label>
        <input type="text" name="np[np_description]" value="<?= e(Settings::get('np_description', 'Продукти бджільництва')) ?>" maxlength="120">
      </div>
      <div class="field">
        <label>Вага за замовчуванням, кг</label>
        <input type="text" name="np[np_weight_default]" value="<?= e(Settings::get('np_weight_default', '0.5')) ?>" placeholder="0.5">
        <p class="field-hint">Береться, лише коли в товарах не проставлена власна вага (Каталог → товар → Вага).</p>
      </div>
      <div class="field">
        <label>Місць у відправленні</label>
        <input type="text" name="np[np_seats_default]" value="<?= e(Settings::get('np_seats_default', '1')) ?>" placeholder="1">
      </div>
    </div>
    <label class="toggle" style="margin-top:14px"
           data-help-title="Післяплата за замовчуванням"
           data-help="Форма накладної одразу підставить суму частини замовлення як післяплату — покупець заплатить при отриманні.

Вмикайте, якщо у вас так возять більшість посилок: інакше продавець вписуватиме суму щоразу руками, а забута післяплата означає віддану без грошей посилку.

Це лише передзаповнення — обнулити поле перед створенням накладної можна завжди.">
      <input type="checkbox" name="np_cod_default" value="1"<?= Settings::bool('np_cod_default', false) ? ' checked' : '' ?>>
      <span class="tr"></span> Післяплата за замовчуванням (сума замовлення)
    </label>
  </div>
  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Тексти бота при вході"
        data-help="Що саме бот пише людині, яка входить на сайт через Telegram чи Viber.

Вхід завершується лише після того, як людина поділиться номером телефону: без нього неможливо підтвердити замовлення, а покупець із замовленнями на цей номер отримав би другий акаунт.

Ці поля — репліки бота на кожному кроці розмови. Пишіть коротко й по-людськи: це перше враження про магазин.

Порожнє поле повертає типовий текст — сміливо очищайте, якщо свій варіант вийшов гіршим. Слова у фігурних дужках підставляються автоматично.">
      Тексти бота при вході</h2>
    <p class="dim" style="margin-bottom:16px">
      Вхід через Telegram чи Viber завершується лише після того, як людина поділиться номером телефону —
      без нього ми не змогли б підтвердити замовлення, а покупець із замовленнями на цей номер
      отримав би другий акаунт. Це те, що бот пише на кожному кроці.
      <br>Порожнє поле повертає типовий текст. У фігурних дужках — підстановки.
    </p>
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php foreach ($bot_texts as $key => [$default, $hint]): ?>
        <div class="field" style="margin:0">
          <label><?= e($hint) ?></label>
          <textarea name="bot[<?= e($key) ?>]" rows="2" placeholder="<?= e($default) ?>"><?= e(Settings::get($key, '')) ?></textarea>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="dim" style="margin-top:14px">
      Кнопка «назад на сайт» веде на
      <?php if ($bot_site !== ''): ?><code><?= e($bot_site) ?></code><?php else: ?>
        <b style="color:var(--gold)">нікуди — заповніть «Адреса сайту для кнопки в боті» вище</b><?php endif; ?>.
      На домені адреса визначається сама, а от з localhost кнопки не буде: Telegram відхиляє
      посилання на локальну адресу разом з усім повідомленням. Viber до того ж стукає у webhook
      власним запитом, тож на бойовому сервері поле краще заповнити явно.
    </p>
  </div>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти налаштування</button>
    <span class="admin-save-note"></span>
  </div>
</form>

<?= View::partial('partials/np_autocomplete') ?>
<script>
(function(){
  // Місто й відділення відправника — той самий віджет, що й у покупця: люди
  // однаково не памʼятають точних назв відділень, з якого б боку прилавка не стояли
  if (window.npAutocomplete) {
    window.npAutocomplete({city: 'npCity', office: 'npOffice', ref: 'npCityRef', officeRef: 'npOfficeRef'});
  }

  /**
   * Контрагент і контактна особа — з кабінету НП, а не з рук.
   *
   * Вручну ці поля не заповнюють: у накладній має стояти саме той контрагент,
   * від імені якого відправляють, а його Ref ніде, крім API, не побачити.
   * Ключ передаємо з форми — інакше довідник неможливо підтягнути, поки
   * новий ключ ще не збережено.
   */
  var btn = document.getElementById('npLoadBtn'), note = document.getElementById('npLoadNote');
  var senderSel = document.getElementById('npSenderRef'), contactSel = document.getElementById('npContactRef');
  if (!btn || !senderSel || !contactSel) return;
  var senders = [];

  function fill(sel, items, keep){
    var was = keep || sel.value;
    sel.innerHTML = '';
    if (!items.length) {
      sel.appendChild(new Option('— порожньо —', ''));
      return;
    }
    items.forEach(function(it){ sel.appendChild(new Option(it.label, it.ref)); });
    // збережений вибір лишається обраним, якщо він досі є в кабінеті
    if (was && items.some(function(it){ return it.ref === was })) sel.value = was;
    sel.dispatchEvent(new Event('change'));
  }
  function syncNames(){
    document.getElementById('npSenderName').value = senderSel.selectedOptions[0] ? senderSel.selectedOptions[0].text : '';
    document.getElementById('npContactName').value = contactSel.selectedOptions[0] ? contactSel.selectedOptions[0].text : '';
  }
  senderSel.addEventListener('change', function(){
    var s = senders.find(function(x){ return x.ref === senderSel.value });
    if (s) fill(contactSel, s.contacts);
    syncNames();
    // телефон контактної особи — найчастіше саме той, що має стояти в накладній
    var c = s && s.contacts.find(function(x){ return x.ref === contactSel.value });
    var phone = document.getElementById('npSenderPhone');
    if (c && c.phone && !phone.value.trim()) phone.value = c.phone;
  });
  contactSel.addEventListener('change', syncNames);

  btn.addEventListener('click', function(){
    btn.disabled = true;
    note.textContent = 'Питаємо Нову Пошту…';
    var body = new FormData();
    body.append('_csrf', '<?= e(Csrf::token()) ?>');
    var keyField = document.querySelector('[name="text[np_api_key]"]');
    // поле з ключем показане крапками: порожнє означає «не міняли», тоді
    // сервер візьме збережений
    if (keyField && keyField.value.trim() && keyField.value.indexOf('•') === -1) body.append('key', keyField.value.trim());
    fetch('<?= e(url('/api/np/senders')) ?>', {method: 'POST', body: body, credentials: 'same-origin'})
      .then(function(r){ return r.json() })
      .then(function(d){
        btn.disabled = false;
        if (!d.ok) { note.textContent = 'Не вдалося: ' + (d.error || 'невідома причина'); return; }
        senders = d.items || [];
        if (!senders.length) { note.textContent = 'У кабінеті немає жодного відправника — заведіть його на novaposhta.ua'; return; }
        fill(senderSel, senders);
        note.textContent = 'Знайдено відправників: ' + senders.length + '. Оберіть потрібного й збережіть налаштування.';
      })
      .catch(function(){ btn.disabled = false; note.textContent = 'Не вдалося звʼязатися з сервером'; });
  });
})();
</script>
