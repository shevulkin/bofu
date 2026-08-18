<?php
/**
 * Редактор оптової шкали. Спільний для трьох ярусів — товару, розділу й
 * загальних налаштувань: форма в них однакова, різниця лише в тому, чий
 * ярус зараз редагують.
 *
 * @var array  $tiers шкала цього яруса (може бути порожня)
 * @var string $name  імʼя поля: $name[0][min_qty], $name[0][percent]
 * @var string $ro    'disabled' для того, хто дивиться без права правити
 */
$tiers = $tiers ?? [];
$name = $name ?? 'tier';
$ro = $ro ?? '';
$rows = array_values($tiers);
for ($i = 0; $i < QtyDiscounts::SPARE_ROWS; $i++) $rows[] = ['min_qty' => '', 'percent' => ''];
?>
<table class="tbl qty-tiers">
  <tr>
    <th style="width:50%">Від скількох штук</th>
    <th>Знижка, %</th>
  </tr>
  <?php foreach ($rows as $i => $t): ?>
    <tr>
      <td>
        <input type="number" min="<?= QtyDiscounts::MIN_QTY ?>" step="1"
               name="<?= e($name) ?>[<?= $i ?>][min_qty]"
               value="<?= e((string)($t['min_qty'] ?? '')) ?>"
               placeholder="напр. 5" <?= $ro ?>>
      </td>
      <td>
        <input type="text" name="<?= e($name) ?>[<?= $i ?>][percent]"
               value="<?= $t['percent'] === '' || $t['percent'] === null ? '' : e(QtyDiscounts::pct((float)$t['percent'])) ?>"
               placeholder="напр. 5" <?= $ro ?>>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<p class="dim" style="margin:8px 0 0;font-size:13px">
  Порожній рядок нічого не задає. Щоб прибрати поріг — очистіть обидва його поля й збережіть.
  Кожен наступний поріг має давати більше за попередній, інакше брати більше немає сенсу.
</p>
