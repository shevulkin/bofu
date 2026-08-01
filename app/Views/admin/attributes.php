<?php
/** @var array $attrs @var array $usage @var array $val_usage @var array $categories @var int $open */
$catName = [];
foreach ($categories as $c) $catName[(int)$c['id']] = $c['name'];
?>
<div class="admin-head">
  <h1 class="h-serif">Характеристики</h1>
  <a class="btn btn-line btn-sm" href="<?= e(url('/admin/products')) ?>">До товарів →</a>
</div>

<p class="dim" style="margin:-8px 0 18px;max-width:760px">
  Спільний словник для всіх товарів: продавець обирає характеристику й значення зі списку, а не набирає руками.
  Прив'яжіть характеристику до категорій — тоді у формі меду не буде «Матеріалу», а в свічок не буде «Медоносу».
  Без прив'язки характеристика пропонується в усіх категоріях.
</p>

<form class="admin-card" method="post" action="<?= e(url('/admin/attributes')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="create">
  <h2 class="h-serif">Нова характеристика</h2>
  <div class="form-grid">
    <div class="field"><label>Назва *</label><input type="text" name="name" required placeholder="Напр. Колір, Об'єм, Матеріал"></div>
    <div class="field"><label>Тип</label>
      <select name="type">
        <?php foreach (Attrs::TYPES as $t => $lbl): ?><option value="<?= e($t) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Одиниця (необов'язково)</label><input type="text" name="unit" placeholder="г, мл, см"></div>
    <div class="field"><label>&nbsp;</label>
      <label class="checkbox"><input type="checkbox" name="filterable" checked> Показувати у фільтрах магазину</label>
    </div>
  </div>
  <div class="field"><label>Значення — по одному в рядок або через кому</label>
    <textarea name="values" rows="3" placeholder="Червоний, Синій, Золотий"></textarea>
  </div>
  <div class="field"><label>Категорії (нічого не обрано = для всіх)</label>
    <div class="pick-grid">
      <?php foreach ($categories as $c): ?>
        <label class="checkbox"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>"> <?= e($c['name']) ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <button class="btn btn-gold btn-sm" type="submit">+ Створити</button>
</form>

<?php if (!$attrs): ?>
  <p class="muted">Поки що словник порожній.</p>
<?php endif; ?>

<?php foreach ($attrs as $a): $aid = (int)$a['id']; $u = $usage[$aid]; ?>
  <details class="admin-card attr-item" <?= $open === $aid ? 'open' : '' ?>>
    <summary>
      <b><?= e($a['name']) ?></b>
      <?php if ($a['unit']): ?><span class="dim">, <?= e($a['unit']) ?></span><?php endif; ?>
      <span class="dim"> · <?= e(Attrs::TYPES[$a['type']] ?? $a['type']) ?></span>
      <?php if ($a['filterable']): ?><span class="tag">у фільтрах</span><?php endif; ?>
      <?php if (!$a['active']): ?><span class="tag tag-off">вимкнена</span><?php endif; ?>
      <span class="dim"> · <?= count($a['values']) ?> знач. · товарів: <?= (int)$u['products'] ?><?= $u['variants'] ? ' · варіантів: ' . (int)$u['variants'] : '' ?></span>
      <div class="dim" style="margin-top:4px">
        <?php if ($a['category_ids']): ?>
          Категорії: <?= e(implode(', ', array_map(fn($i) => $catName[$i] ?? '#' . $i, $a['category_ids']))) ?>
        <?php else: ?>Для всіх категорій<?php endif; ?>
      </div>
    </summary>

    <form method="post" action="<?= e(url('/admin/attributes')) ?>" style="margin-top:18px">
      <?= Csrf::field() ?><input type="hidden" name="id" value="<?= $aid ?>">
      <?php /* дія за замовчуванням: ✕ біля значення не несе _action, а Enter у полі не має нічого видаляти */ ?>
      <input type="hidden" name="_action" value="update">
      <button type="submit" class="submit-default" tabindex="-1" aria-hidden="true"></button>
      <div class="form-grid">
        <div class="field"><label>Назва</label><input type="text" name="name" value="<?= e($a['name']) ?>" required></div>
        <div class="field"><label>Тип</label>
          <select name="type">
            <?php foreach (Attrs::TYPES as $t => $lbl): ?>
              <option value="<?= e($t) ?>" <?= $a['type'] === $t ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Одиниця</label><input type="text" name="unit" value="<?= e($a['unit'] ?? '') ?>"></div>
        <div class="field"><label>Порядок</label><input type="number" name="sort" value="<?= (int)$a['sort'] ?>"></div>
      </div>
      <div style="display:flex;gap:26px;flex-wrap:wrap;margin-bottom:16px">
        <label class="checkbox"><input type="checkbox" name="filterable" <?= $a['filterable'] ? 'checked' : '' ?>> У фільтрах магазину</label>
        <label class="checkbox"><input type="checkbox" name="active" <?= $a['active'] ? 'checked' : '' ?>> Активна (пропонувати у формі товару)</label>
      </div>

      <div class="field"><label>Категорії (нічого не обрано = для всіх)</label>
        <div class="pick-grid">
          <?php foreach ($categories as $c): ?>
            <label class="checkbox"><input type="checkbox" name="categories[]" value="<?= (int)$c['id'] ?>"
              <?= in_array((int)$c['id'], $a['category_ids'], true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (in_array($a['type'], ['select', 'color'], true)): ?>
        <label>Значення</label>
        <table class="tbl" style="margin-bottom:14px">
          <tr><th>Значення</th><?php if ($a['type'] === 'color'): ?><th style="width:120px">Колір</th><?php endif; ?><th style="width:140px">Використань</th><th style="width:60px"></th></tr>
          <?php foreach ($a['values'] as $v): $vid = (int)$v['id']; $cnt = $val_usage[$vid] ?? 0; ?>
            <tr>
              <td><input type="text" name="value[<?= $vid ?>]" value="<?= e($v['value']) ?>"></td>
              <?php if ($a['type'] === 'color'): ?>
                <td><input type="color" name="value_color[<?= $vid ?>]" value="<?= e($v['color'] ?: '#cccccc') ?>" style="width:100%;height:36px;padding:2px"></td>
              <?php endif; ?>
              <td class="dim"><?= $cnt ? $cnt . ' шт.' : 'не використовується' ?></td>
              <td style="text-align:center">
                <button class="btn btn-danger btn-xs" type="submit" name="delete_value_id" value="<?= $vid ?>"
                  onclick="return confirm(<?= e(json_encode('Видалити значення «' . $v['value'] . '»?' . ($cnt ? ' Воно зникне у ' . $cnt . ' товарів/варіантів.' : ''), JSON_UNESCAPED_UNICODE)) ?>)">✕</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
        <div class="field"><label>Додати значення — по одному в рядок або через кому</label>
          <textarea name="new_values" rows="2" placeholder="Нове значення…"></textarea>
        </div>
      <?php else: ?>
        <p class="dim" style="margin-bottom:14px">Тип «<?= e(Attrs::TYPES[$a['type']]) ?>» — значення вводяться прямо у формі товару, список не потрібен.</p>
      <?php endif; ?>

      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <button class="btn btn-gold btn-sm" type="submit">💾 Зберегти</button>
        <button class="btn btn-danger btn-sm" type="submit" name="_action" value="delete"
          onclick="return confirm(<?= e(json_encode('Видалити характеристику «' . $a['name'] . '» разом з усіма значеннями?'
            . ($u['products'] ? ' Вона зникне у ' . $u['products'] . ' товарів.' : '')
            . ($u['variants'] ? ' Торкнеться ' . $u['variants'] . ' варіантів.' : ''), JSON_UNESCAPED_UNICODE)) ?>)">Видалити характеристику</button>
      </div>
    </form>
  </details>
<?php endforeach; ?>
