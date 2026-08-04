<div class="admin-head"><h1 class="h-serif">Бренди</h1></div>
<p class="dim" style="margin:0 0 18px;max-width:760px">Чий товар. Бренд, позначений «наш», дає покупцю напис
  «Виготовимо під замовлення — ми виробник». Для решти напис нейтральний: «Виготовляється на замовлення —
  привеземо для вас». Товар без бренду вважається не нашим.</p>

<form class="admin-card" method="post" action="<?= e(url('/admin/brands')) ?>" style="display:flex;gap:14px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div style="flex:1;min-width:200px" data-help-title="Назва бренду"
       data-help="Хто виробник: «SINCERA», «Апіпродукт», «<?= e($own_default) ?>».

Пишіть так, як написано на упаковці, — покупець бачить цю назву в характеристиках товару, і вона ж іде в розмітку для Google.

Назви не повторюються: якщо бренд уже є в списку, додати другий такий самий сайт не дасть.">
    <label>Назва</label><input type="text" name="name" required></div>
  <label class="checkbox" style="margin-bottom:10px" data-help-title="Наш бренд"
         data-help="Позначає ваше власне виробництво.

Лише товари цього бренду отримують напис «ми виробник». Це твердження про походження товару, тому сайт ніколи не ставить його сам — тільки за цією галкою.

Наш бренд може бути лише один: коли ставите галку іншому, з попереднього вона знімається.">
    <input type="checkbox" name="own" value="1"> Наш бренд</label>
  <button class="btn btn-gold btn-sm" type="submit">+ Додати</button>
</form>

<?php if (!$brands): ?>
  <p class="dim">Поки що порожньо. Додайте перший бренд — далі його можна буде обрати в картці товару.</p>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/brands')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <table class="tbl">
    <tr>
      <th>Назва</th>
      <th class="col-mid" data-help-title="Колонка «Наш»"
          data-help="Ваше власне виробництво. Лише ці товари сайт підписує «ми виробник».

Наш бренд може бути лише один — галка переїжджає, а не додається.">Наш</th>
      <th class="col-mid" data-help-title="Колонка «Активний»"
          data-help="Чи пропонувати бренд у картці товару.

Знята галка ховає бренд зі списку вибору, але вже підписані ним товари не змінюються — вони й далі показують цю назву покупцю.

Так правильно виводити з обігу бренд, з яким більше не працюєте, замість видаляти його.">Активний</th>
      <th class="col-mid">Товарів</th>
      <th class="col-mid"></th>
    </tr>
    <?php foreach ($brands as $b): $n = (int)($counts[(int)$b['id']] ?? 0); ?>
      <tr>
        <td><input type="text" name="brand[<?= (int)$b['id'] ?>][name]" value="<?= e($b['name']) ?>"></td>
        <td class="col-mid"><input type="checkbox" name="brand[<?= (int)$b['id'] ?>][own]" <?= $b['own'] ? 'checked' : '' ?>></td>
        <td class="col-mid"><input type="checkbox" name="brand[<?= (int)$b['id'] ?>][active]" <?= $b['active'] ? 'checked' : '' ?>></td>
        <td class="col-mid">
          <?php if ($n): ?>
            <a href="<?= e(url('/admin/products?brand=' . (int)$b['id'])) ?>" title="Показати ці товари"><?= $n ?></a>
          <?php else: ?><span class="dim">0</span><?php endif; ?>
        </td>
        <td class="col-mid">
          <?php /* видалення лише в порожнього: інакше товари лишились би без відповіді, чиї вони */ ?>
          <?php if (!$n): ?>
            <button class="btn btn-danger btn-xs" type="submit" form="del<?= (int)$b['id'] ?>">Видалити</button>
          <?php else: ?><span class="dim" title="Спершу перепідпишіть товари">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти</button>
    <span class="admin-save-note"></span>
  </div>
</form>
<?php /* форми видалення поза таблицею: вкладені форми браузер не приймає */ ?>
<?php foreach ($brands as $b): if (!empty($counts[(int)$b['id']])) continue; ?>
  <form id="del<?= (int)$b['id'] ?>" method="post" action="<?= e(url('/admin/brands')) ?>" style="display:none">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="delete">
    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
  </form>
<?php endforeach; ?>
<?php endif; ?>
