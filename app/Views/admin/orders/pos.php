<?php
/**
 * Каса. Ліворуч — те, з чого набирають чек (сканер, пошук, плитка товарів),
 * праворуч — сам чек: покупець, позиції, підсумок, кнопка оформлення.
 *
 * Дрібні дії (додати, змінити кількість, знайти покупця) ходять на сервер
 * без перезавантаження — у продажу їх по десятку, і кожна не має коштувати
 * мигання екрана. Перемальовує чек pos.js.
 */
?>
<div class="admin-head"><h1 class="h-serif">Каса</h1>
  <?php if ($active): ?>
    <form method="post" action="<?= e(url('/admin/orders/new')) ?>" style="margin:0">
      <?= Csrf::field() ?><input type="hidden" name="_action" value="cancel">
      <button class="btn btn-line btn-sm" type="submit"
              onclick="return confirm('Скасувати продаж? Набраний чек зникне.')">Скасувати продаж</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!$stores): ?>
  <div class="card-warn"><b>Вам не призначено жодної точки.</b>
    Продавати можна лише від імені магазину — попросіть адміністратора призначити вас у розділі «Користувачі».</div>
<?php else: ?>

<?php if ($errors): ?>
  <div class="flash" style="padding:0;margin:0 0 16px"><div class="flash-error">
    <?php foreach ($errors as $i => $err): ?><?= $i ? '<br>' : '' ?><?= e($err) ?><?php endforeach; ?>
  </div></div>
<?php endif; ?>

<div class="pos-wrap">
  <!-- ---------------------------------------------------------------- товари -->
  <div class="pos-goods">
    <div class="admin-card" style="padding:16px">
      <div class="pos-top">
        <div style="min-width:170px" data-help-title="Точка продажу"
             data-help="Магазин, від імені якого йде продаж: з його складу спишеться товар і йому дістанеться замовлення.

Ціни теж його — у точки може бути власний цінник і власна акція, тому після зміни магазину плитка й чек перераховуються.

Поки чек порожній, точку можна міняти вільно; коли товар уже набрано — краще не чіпати.">
          <label>Магазин</label>
          <select name="store_id" form="posForm" data-pos-store>
            <?php foreach ($stores as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= (int)$store_id === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['name'] . ($s['city'] ? ' — ' . $s['city'] : '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="np-wrap" style="flex:1;min-width:220px" data-help-title="Сканер і пошук"
             data-help="Поле працює трьома способами.

USB-СКАНЕР: піднесіть сканер до етикетки — він сам надрукує код і натисне Enter, позиція одразу ляже в чек. Тримайте курсор у цьому полі, і більше нічого робити не треба: сканер для компʼютера — це просто клавіатура.

КАМЕРА: кнопка «Камера» поруч. Працює на телефоні (Chrome на Android) — відкрийте касу з нього й наводьте камеру на етикетки, позиції додаватимуться одна за одною.

ПОШУК: наберіть частину назви — нижче зʼявиться список фасовок із цінами й залишками, клік додає в чек.

Щоб сканування взагалі щось знаходило, у товарів мають бути заповнені коди: «Каталог → Коди й штрихкоди».">
          <label>Сканер або пошук</label>
          <input type="text" id="posScan" autocomplete="off" autofocus
                 placeholder="код зі сканера або назва товару">
        </div>
        <button type="button" class="btn btn-line btn-sm" id="posCam">📷 Камера</button>
      </div>
      <p class="dim" id="posMsg" style="margin:10px 0 0;min-height:18px"></p>
      <?php if (!$has_codes): ?>
        <?php /* Сканер, якому нема чого знаходити, виглядає зламаним. Кажемо
                 прямо й ведемо туди, де це чинять — одним кліком. */ ?>
        <p class="field-hint is-bad" style="margin-top:6px">
          Жоден товар ще не має коду — сканер поки не знайде нічого.
          <a href="<?= e(url('/admin/products/codes')) ?>">Заповнити коди й штрихкоди</a>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($cats): ?>
      <div class="cat-chips" style="margin-bottom:12px">
        <a class="chip <?= $cat ? '' : 'active' ?>" href="<?= e(url('/admin/orders/new')) ?>">Усі</a>
        <?php foreach ($cats as $c): ?>
          <a class="chip <?= $cat === (int)$c['id'] ? 'active' : '' ?>"
             href="<?= e(url('/admin/orders/new?cat=' . (int)$c['id'])) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php /* Плитка: тап = +1 у чек. На кількох десятках позицій це швидше за
             будь-який пошук, і саме так влаштовані каси в невеликих магазинах. */ ?>
    <div class="pos-tiles" data-help-title="Плитка товарів"
         data-help="Кожна плитка — одна фасовка. Тап додає одну штуку в чек; тапнули двічі — буде дві.

Ціна й залишок показані для вибраної точки. Нуль не забороняє продаж (товар можуть виготовити під замовлення), але означає, що склад розійшовся з дійсністю — краще виправити залишок.

Плитку можна звузити категорією вгорі.">
      <?php foreach ($tiles as $t): ?>
        <button type="button" class="pos-tile<?= $t['stock'] <= 0 ? ' is-empty' : '' ?>"
                data-pos-add data-product="<?= $t['product_id'] ?>" data-variant="<?= $t['variant_id'] ?>">
          <span class="pos-tile-photo" style="background-image:url('<?= e(asset($t['photo'])) ?>')"></span>
          <span class="pos-tile-name"><?= e($t['title']) ?></span>
          <?php if ($t['variant_name'] !== ''): ?>
            <span class="pos-tile-var"><?= e($t['variant_name']) ?></span>
          <?php endif; ?>
          <span class="pos-tile-foot">
            <b><?= e(price_fmt($t['price'])) ?></b>
            <span class="<?= $t['stock'] > 0 ? 'dim' : 'pos-tile-zero' ?>">
              <?= $t['stock'] > 0 ? (int)$t['stock'] . ' шт.' : ($t['made_to_order'] ? 'під замовлення' : 'немає') ?>
            </span>
          </span>
        </button>
      <?php endforeach; ?>
      <?php if (!$tiles): ?><p class="dim">У цій категорії немає активних товарів.</p><?php endif; ?>
    </div>
  </div>

  <!-- ---------------------------------------------------------------- чек -->
  <form class="pos-check" method="post" action="<?= e(url('/admin/orders/new')) ?>" id="posForm">
    <?= Csrf::field() ?>

    <div class="admin-card" style="padding:16px">
      <h2 class="h-serif" style="font-size:18px;margin-bottom:12px">Покупець</h2>
      <div style="display:flex;gap:8px;align-items:end">
        <div style="flex:1" data-help-title="Телефон покупця"
             data-help="Необовʼязково — і це головне в цьому полі. Продаж на місці цілком може бути анонімним: людина зайшла, купила, пішла.

Якщо номер є, натисніть «Знайти»: знайдемо акаунт — замовлення потрапить в історію покупок цієї людини; не знайдемо — заведемо новий акаунт при оформленні, і покупець зможе увійти на сайт цим самим номером.

Номер можна вписати будь-коли: на початку, посеред набору чи перед самим оформленням.">
          <label>Телефон</label>
          <input type="text" name="phone" id="posPhone" value="<?= e($customer['phone']) ?>"
                 placeholder="+380…" autocomplete="off">
        </div>
        <button type="button" class="btn btn-line btn-sm" data-pos-find>Знайти</button>
      </div>
      <div style="margin-top:10px">
        <label>Імʼя</label>
        <input type="text" name="name" id="posName" value="<?= e($customer['name']) ?>" autocomplete="off">
      </div>
      <?php /* Стан покупця словами, а не кольором поля: «знайдено», «створимо
               акаунт» і «анонім» — три різні речі, і плутати їх дорого. Той
               самий блок повторюється біля кнопки оформлення, бо рішення
               ухвалюють саме там, а не тут. */ ?>
      <div class="pos-who is-<?= e($customer['state']) ?>" data-pos-note>
        <span class="pos-who-icon" data-pos-icon><?= e($customer['icon']) ?></span>
        <span data-pos-text><?= e($customer['note']) ?></span>
      </div>
    </div>

    <div class="admin-card pos-lines-card" style="padding:16px">
      <h2 class="h-serif" style="font-size:18px;margin-bottom:12px">Чек</h2>
      <table class="tbl pos-lines" id="posLines">
        <?php foreach ($lines as $r): ?>
          <tr>
            <td>
              <?= e($r['product']['name']) ?>
              <?php if ($r['variant']): ?><div class="dim"><?= e($r['variant']['name']) ?></div><?php endif; ?>
            </td>
            <td class="num" style="white-space:nowrap">
              <button type="button" class="pos-qty-btn" data-pos-qty="<?= e($r['key']) ?>" data-to="<?= (int)$r['qty'] - 1 ?>">−</button>
              <b><?= (int)$r['qty'] ?></b>
              <button type="button" class="pos-qty-btn" data-pos-qty="<?= e($r['key']) ?>" data-to="<?= (int)$r['qty'] + 1 ?>">+</button>
            </td>
            <td class="num"><?= e(price_fmt($r['sum'])) ?></td>
            <td><button type="button" class="btn btn-line btn-xs" data-pos-qty="<?= e($r['key']) ?>" data-to="0">×</button></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <p class="dim" id="posEmpty" <?= $lines ? 'hidden' : '' ?> style="margin:0">
        Чек порожній. Тапніть по плитці, піднесіть сканер до етикетки — або вийдіть на сайт і додавайте звідти.
      </p>
      <div class="pos-total">Разом <b id="posTotal"><?= e(price_fmt($totals['total'])) ?></b></div>
    </div>

    <div class="admin-card" style="padding:16px">
      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:12px" data-help-title="Спосіб продажу"
           data-help="«У магазині» — покупець перед вами: товар віддається одразу, доставка не потрібна.

«Телефоном» — подзвонили. Тоді номер обовʼязковий: без нього замовлення нікому підтвердити.

Від вибору залежать поля доставки нижче й те, чи закривається замовлення одразу.">
        <?php foreach (['offline' => 'У магазині', 'phone' => 'Телефоном'] as $key => $label): ?>
          <label class="toggle" style="gap:8px">
            <input type="radio" name="source" value="<?= e($key) ?>" data-pos-source
                   style="position:static;opacity:1" <?= $source === $key ? 'checked' : '' ?>>
            <?= e($label) ?></label>
        <?php endforeach; ?>
      </div>

      <div data-pos-delivery <?= $source === 'offline' ? 'hidden' : '' ?>>
        <label>Доставка</label>
        <div style="display:flex;gap:14px;flex-wrap:wrap;margin:6px 0 12px">
          <?php foreach (OrderFlow::DELIVERY as $key => $label): ?>
            <label class="toggle" style="gap:8px">
              <input type="radio" name="delivery" value="<?= e($key) ?>" data-pos-dlv
                     style="position:static;opacity:1" <?= $form['delivery'] === $key ? 'checked' : '' ?>>
              <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <div data-pos-np <?= $form['delivery'] === 'np' ? '' : 'hidden' ?>>
          <label>Місто</label>
          <input type="text" name="np_city" id="npCity" value="<?= e($form['city']) ?>" autocomplete="off">
          <input type="hidden" name="city_ref" id="npCityRef" value="<?= e($form['city_ref']) ?>">
          <input type="hidden" name="np_office_ref" id="npOfficeRef" value="<?= e($form['np_office_ref']) ?>">
          <input type="hidden" name="np_street_ref" id="npStreetRef" value="<?= e($form['np_street_ref']) ?>">
          <?php /* Куди везти: у відділення чи додому. Обирати з довідника
                   обовʼязково — накладна створюється за посиланням, а не за назвою. */ ?>
          <label style="margin-top:8px">Куди</label>
          <select name="np_type" id="posNpType">
            <option value="warehouse"<?= $form['np_type'] === 'warehouse' ? ' selected' : '' ?>>У відділення / поштомат</option>
            <option value="courier"<?= $form['np_type'] === 'courier' ? ' selected' : '' ?>>Курʼєром на адресу</option>
          </select>
          <div data-pos-np-wh<?= $form['np_type'] === 'courier' ? ' hidden' : '' ?>>
            <label style="margin-top:8px">Відділення</label>
            <input type="text" name="np_office" id="npOffice" value="<?= e($form['np_office']) ?>" autocomplete="off">
          </div>
          <div data-pos-np-courier<?= $form['np_type'] === 'courier' ? '' : ' hidden' ?>>
            <label style="margin-top:8px">Вулиця</label>
            <input type="text" name="np_street" id="npStreet" value="<?= e($form['np_street']) ?>" autocomplete="off">
            <div style="display:flex;gap:8px;margin-top:8px">
              <div style="flex:1"><label>Будинок</label>
                <input type="text" name="np_house" id="npHouse" value="<?= e($form['np_house']) ?>" maxlength="20"></div>
              <div style="flex:1"><label>Квартира</label>
                <input type="text" name="np_flat" id="npFlat" value="<?= e($form['np_flat']) ?>" maxlength="20"></div>
            </div>
          </div>
        </div>
        <div data-pos-addr <?= $form['delivery'] === 'other' ? '' : 'hidden' ?>>
          <label>Адреса</label>
          <input type="text" name="address" value="<?= e($form['address']) ?>">
        </div>
        <div style="margin-top:8px">
          <label>Email (необовʼязково)</label>
          <input type="text" name="email" value="<?= e($form['email']) ?>">
        </div>
      </div>

      <div style="margin-top:10px" data-help-title="Промокод"
           data-help="Код, який назвав покупець. Перевіряється так само, як на сайті: строк дії, ліміт використань і ліміт «один раз на людину» (його рахуємо за номером телефону).

Неробочий код не пропустить оформлення — побачите причину вгорі екрана. Знижку рахує сервер за тими ж правилами, що й у кошику.">
        <label>Промокод</label>
        <input type="text" name="promo_code" value="<?= e($form['promo_code']) ?>">
      </div>
      <div style="margin-top:10px">
        <label>Коментар</label>
        <input type="text" name="comment" value="<?= e($form['comment']) ?>" placeholder="як домовились">
      </div>
      <div style="margin-top:12px" data-pos-handed <?= $form['delivery'] === 'pickup' ? '' : 'hidden' ?>
           data-help-title="Товар віддано покупцю"
           data-help="Ставте, коли товар уже в руках покупця: замовлення одразу закриється статусом «Доставлено», а залишки спишуться зі складу точки.

Знімайте, якщо людина ще прийде забрати (відклали, домовились на завтра) — тоді замовлення лишиться в роботі, і магазин отримає про нього сповіщення, як про будь-яке нове.">
        <label class="toggle">
          <input type="checkbox" name="handed" <?= $form['handed'] ? 'checked' : '' ?>><span class="tr"></span>
          Товар віддано покупцю</label>
      </div>
    </div>

    <div class="pos-actions">
      <div class="pos-who is-<?= e($customer['state']) ?>" data-pos-note style="width:100%">
        <span class="pos-who-icon" data-pos-icon><?= e($customer['icon']) ?></span>
        <span data-pos-text><?= e($customer['note']) ?></span>
      </div>
      <button class="btn btn-gold" type="submit" name="_action" value="save">💾 Оформити замовлення</button>
      <a class="btn btn-line" href="<?= e(url('/shop')) ?>" data-help-title="Кнопка «Вийти на сайт»"
         data-help="Відкриває вітрину, не втрачаючи чек: унизу зʼявиться смужка продажу, і кнопки «У кошик» на сайті додаватимуть товар у цей самий чек.

Це для випадку, коли покупцеві треба показати картку товару — фото, опис, характеристики. Повернутись до каси можна з тієї ж смужки.">Вийти на сайт →</a>
    </div>
  </form>
</div>

<?php if ($np_enabled): ?><?= View::partial('partials/np_autocomplete') ?><?php endif; ?>
<script>
window.POS = {
  base: '<?= e(url('/')) ?>',
  csrf: '<?= e(Csrf::token()) ?>',
  postUrl: '<?= e(url('/admin/orders/new')) ?>',
  searchUrl: '<?= e(url('/admin/orders/search')) ?>'
};
</script>
<?php /* Читання штрихкодів своїми силами — потрібне там, де браузер не вміє
         сам (а на Windows не вміє жоден). Вантажимо лише там, де сканують. */ ?>
<script src="<?= e(asset_v('js/barcode.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/scan.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/pos.js')) ?>" defer></script>
<?php endif; ?>
