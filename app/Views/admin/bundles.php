<?php
/**
 * @var array      $list     усі набори (зокрема вимкнені)
 * @var array|null $b        набір, який зараз редагують
 * @var array|null $preview  його склад із цінами, або null якщо щось зникло
 * @var array      $products товари для вибору
 * @var array      $variants фасовки: [product_id => [{id,name}]]
 */
?>
<div class="admin-head">
  <h1 class="h-serif">Набори</h1>
</div>

<div class="admin-card">
  <h2 class="h-serif" data-help-title="Набори «разом дешевше»"
      data-help="Знижка за поєднання: кілька певних товарів разом коштують менше, ніж кожен окремо.

Від акції відрізняється тим, за що дається. Акція здешевлює один товар усім; набір здешевлює тільки того, хто взяв усе перелічене разом. Від опту — тим, що опт про кількість одного товару, а набір про поєднання різних.

Спрацьовує сам: щойно в кошику зустрілося все зі складу набору, знижка зʼявляється окремим рядком у підсумках. Покупцю набір також пропонується на сторінці кожного товару, який до нього входить.

Неповний набір знижки не дає — інакше «разом дешевше» перестало б означати «разом».">
    Що це таке</h2>
  <p class="card-lead" style="margin:-6px 0 0">
    Знижка рахується по позиціях, тому впирається в ту саму <b>стелю</b>, що акція, опт і промокод.
    Якщо на товарі вже висить акція, набір додасть лише те, що лишилось до стелі.
    Одна й та сама штука не потрапляє у два набори одночасно.
  </p>
</div>

<form class="admin-card" method="post" action="<?= e(url('/admin/bundles')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <h2 class="h-serif">Новий набір</h2>
  <div style="display:flex;gap:14px;align-items:end;flex-wrap:wrap;margin-bottom:18px">
  <div style="flex:1;min-width:220px" data-help-title="Назва набору"
       data-help="Те, що покупець побачить у підсумках кошика: «Набір «Подарунковий» −108 грн».

Пишіть так, щоб назва пояснювала знижку сама: «Подарунковий», «Для початківця», «Мед + прополіс». Внутрішні позначки («набір-3», «акція жовтень») у кошику виглядають як помилка.">
    <label>Назва нового набору</label><input type="text" name="title" required placeholder="Подарунковий"></div>
  <div data-help-title="Як рахувати знижку"
       data-help="Два способи назвати ту саму річ.

«Відсоток» — набір дешевший на N% від суми своїх позицій. Зручний, коли ціни змінюються: знижка перерахується сама.

«Фіксована ціна» — увесь набір коштує рівно N гривень, а знижкою стає різниця зі звичайною ціною. Потрібен, коли набір продають як окрему річ і рівне число на ціннику важливіше за рівний відсоток.

Ціна — за один комплект: два комплекти по 500 коштують 1000.">
    <label>Знижка</label>
    <select name="kind"><option value="percent">відсоток</option><option value="fixed">фіксована ціна</option></select>
  </div>
  <div style="width:120px"><label>Значення</label><input type="text" name="value" placeholder="10"></div>
  </div>

  <?php /* Склад питаємо одразу, а не після збереження назви. Набір без товарів
           не існує як річ, тож крок «створіть спершу порожній набір» був би
           вигаданим: людина заповнює назву й одразу шукає, де обрати товари. */ ?>
  <div style="max-width:760px" data-help-title="Склад набору"
       data-help="Що саме має зустрітись у кошику, щоб знижка спрацювала.

Товарів має бути принаймні два різні — набір з одного це звичайна акція, і для неї є свій блок на сторінці «Акції та промокоди».

«Будь-яка фасовка» означає, що покупцю все одно, яку банку брати: набір збереться з тієї, що вже лежить у кошику. Обирайте конкретну лише тоді, коли інша справді не годиться.

Кількість більша за одиницю робить набір кратним: «2 меду + 1 прополіс» спрацює на 4 меду і 2 прополіси — двічі.">
    <h3 style="margin:0 0 10px;font-size:15px">Склад</h3>
    <?= View::partial('partials/bundle_items', [
          'items' => [], 'products' => $products, 'variants' => $variants]) ?>
  </div>

  <div class="admin-save" style="margin-top:18px">
    <button class="btn btn-gold btn-sm" type="submit">+ Створити набір</button>
  </div>
</form>

<?php if (!$list): ?>
  <div class="admin-card"><p class="dim" style="margin:0">Наборів ще немає. Створіть перший — і він зʼявиться
    на сторінках товарів, які до нього входять.</p></div>
<?php else: ?>

<div class="admin-card">
  <h2 class="h-serif">Усі набори</h2>
  <table class="tbl">
    <tr><th>Назва</th><th>Знижка</th><th class="num">Товарів</th><th class="col-mid">Активний</th><th></th></tr>
    <?php foreach ($list as $row): $isCur = $b && (int)$b['id'] === (int)$row['id']; ?>
      <tr<?= $isCur ? ' style="background:var(--bg3)"' : '' ?>>
        <td><a href="<?= e(url('/admin/bundles?id=' . (int)$row['id'])) ?>"><?= e($row['title']) ?></a></td>
        <td class="muted"><?= $row['kind'] === 'fixed'
              ? e(price_fmt((float)$row['value'])) . ' за комплект'
              : '−' . e(QtyDiscounts::pct((float)$row['value'])) . '%' ?></td>
        <td class="num muted"><?= count($row['items']) ?></td>
        <td class="col-mid muted"><?= $row['active'] ? 'так' : '—' ?></td>
        <td class="col-mid">
          <a class="btn btn-line btn-xs" href="<?= e(url('/admin/bundles?id=' . (int)$row['id'])) ?>">Змінити</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($b): ?>
<form method="post" action="<?= e(url('/admin/bundles')) ?>">
  <?= Csrf::field() ?>
  <input type="hidden" name="_action" value="save">
  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">

  <div class="admin-card">
    <h2 class="h-serif">Набір: <?= e($b['title']) ?></h2>
    <div class="form-grid">
      <div class="field"><label>Назва</label>
        <input type="text" name="title" value="<?= e($b['title']) ?>" required></div>
      <div class="field" data-help-title="Як рахувати знижку"
           data-help="«Відсоток» — набір дешевший на N% від суми своїх позицій; перераховується сам, коли міняються ціни.

«Фіксована ціна» — увесь набір коштує рівно N гривень за комплект, знижкою стає різниця. Якщо звичайна ціна набору нижча за вказану, знижки просто не буде — відʼємної не буває.">
        <label>Знижка</label>
        <select name="kind">
          <option value="percent"<?= $b['kind'] !== 'fixed' ? ' selected' : '' ?>>відсоток від суми позицій</option>
          <option value="fixed"<?= $b['kind'] === 'fixed' ? ' selected' : '' ?>>фіксована ціна за комплект</option>
        </select>
      </div>
      <div class="field"><label>Значення</label>
        <input type="text" name="value" value="<?= e(QtyDiscounts::pct((float)$b['value'])) ?>"></div>
      <div class="field"><label>Порядок</label>
        <input type="number" name="sort" value="<?= (int)$b['sort'] ?>"></div>
      <label class="checkbox" data-help-title="Активний"
             data-help="Знята галка прибирає набір із кошика й зі сторінок товарів одразу. Склад і знижка зберігаються — увімкнете назад, і все повернеться.

Так вимикають сезонні набори: видаляти їх, щоб зібрати наново через рік, немає потреби.">
        <input type="checkbox" name="active" <?= $b['active'] ? 'checked' : '' ?>> Активний</label>
    </div>
  </div>

  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Склад набору"
        data-help="Що саме має зустрітись у кошику, щоб знижка спрацювала.

Товарів має бути принаймні два різні — набір з одного це звичайна акція, і для неї є свій блок.

«Фасовка: будь-яка» означає, що покупцю все одно, яку банку брати: набір збереться з тієї, що вже лежить у кошику. Обирайте конкретну лише тоді, коли інша справді не годиться.

Кількість більша за одиницю робить набір кратним: «2 меду + 1 прополіс» спрацює на 4 меду і 2 прополіси — двічі.

Порожній рядок нічого не задає. Щоб прибрати позицію — оберіть у ній «— товар —» і збережіть.">
      Склад</h2>
    <?= View::partial("partials/bundle_items", [
          "items" => $b["items"], "products" => $products, "variants" => $variants]) ?>

    <?php if ($preview): ?>
      <div style="margin-top:20px;border-top:1px solid var(--line);padding-top:16px">
        <h3 style="margin:0 0 10px;font-size:15px">Що побачить покупець</h3>
        <table class="tbl">
          <?php foreach ($preview['expanded'] as $it): ?>
            <tr>
              <td><?= e($it['product']['name']) ?><?= $it['variant'] ? ', ' . e($it['variant']['name']) : '' ?></td>
              <td class="num muted"><?= (int)$it['qty'] ?> шт</td>
              <td class="num"><?= e(price_fmt($it['price'] * $it['qty'])) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td colspan="2"><b>Окремо</b></td>
            <td class="num"><s class="dim"><?= e(price_fmt($preview['sum'])) ?></s></td>
          </tr>
          <tr>
            <td colspan="2"><b>Набором</b></td>
            <td class="num"><b style="color:var(--gold)"><?= e(price_fmt($preview['total'])) ?></b></td>
          </tr>
        </table>
        <p class="dim" style="margin:10px 0 0">
          Вигода — <b><?= e(price_fmt($preview['cut'])) ?></b>.
          Ціни без урахування магазину; стеля знижки може зменшити її на конкретному товарі.
        </p>
      </div>
    <?php elseif ($b['items']): ?>
      <p style="margin:16px 0 0;color:var(--danger2)">
        Набір зараз не збереться: якогось товару зі складу вже немає, він вимкнений або в нього немає ціни.
      </p>
    <?php endif; ?>

    <div class="admin-save" style="margin-top:18px">
      <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    </div>
  </div>
</form>

<form method="post" action="<?= e(url('/admin/bundles')) ?>" class="admin-card"
      onsubmit="return confirm('Видалити набір «<?= e($b['title']) ?>»? Скасувати буде нічим.')">
  <?= Csrf::field() ?>
  <input type="hidden" name="_action" value="delete">
  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
  <button class="btn btn-danger btn-sm" type="submit">Видалити набір</button>
  <span class="dim" style="margin-left:12px">Товари й ціни лишаться на місці — зникне лише сам набір.</span>
</form>
<?php endif; ?>
<?php endif; ?>

