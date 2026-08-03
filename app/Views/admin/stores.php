<div class="admin-head"><h1 class="h-serif">Магазини</h1></div>
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
  <button class="btn btn-gold btn-sm" type="submit" data-help-title="Кнопка «Додати»"
          data-help="Створює нову точку одразу активною — вона відразу зʼявиться на сайті у виборі магазину.

Після створення точці треба задати залишки товарів (у картці товару або в масовому редагуванні) і призначити продавців у розділі «Користувачі». Без залишків вона буде порожньою вітриною.">+ Додати</button>
</form>
<form method="post" action="<?= e(url('/admin/stores')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <table class="tbl">
    <tr><th>Назва</th><th>Місто</th><th>Адреса</th><th>Телефон</th>
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
        <td class="col-mid"><input type="checkbox" name="store[<?= (int)$s['id'] ?>][active]" <?= $s['active'] ? 'checked' : '' ?>></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit" data-help-title="Кнопка «Зберегти»"
            data-help="Зберігає всі зміни в таблиці одразу — правки в кількох рядках підуть одним натисканням.

Поки не натиснете, жодна зміна не застосована: підпис поруч показує «Є незбережені зміни», щойно ви щось поправили.

Якщо спробуєте піти зі сторінки з незбереженими правками, браузер перепитає — щоб півгодини роботи не зникли від випадкового кліку по меню.">💾 Зберегти</button>
    <span class="admin-save-note"></span>
  </div>
</form>
