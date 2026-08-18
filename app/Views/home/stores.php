<?php
/**
 * Де нас знайти.
 *
 * Карта й список стоять разом, а не одне замість одного. Карта відповідає на
 * «куди їхати», список — на «що переслати в месенджер і продиктувати таксисту».
 * Тому адреса лишається текстом, який можна виділити, а телефон — посиланням,
 * яке з телефона одразу дзвонить.
 *
 * Карти може не бути — не заданий ключ, точки без координат, не завантажився
 * чужий скрипт. Сторінка від цього не порожніє: список і кнопки маршруту
 * працюють самі по собі, і саме вони тут основа.
 *
 * @var array $stores @var string $map_key @var array $map_points
 */
?>
<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Контакти</div>
    <h1 style="font-size:44px">Де нас знайти</h1>
    <p class="lead" style="margin:18px 0 34px">
      <?= count($stores) === 1 ? 'Наша точка продажу' : 'Наші точки продажу' ?> — заходьте за медом,
      або оформіть самовивіз на сайті й заберіть уже зібране замовлення.
    </p>

    <?php if (!$stores): ?>
      <p class="dim">Точок продажу поки немає — замовлення надсилаємо Новою Поштою.</p>
    <?php else: ?>

      <?php if ($map_key && $map_points): ?>
        <div class="store-map" id="storesMap"></div>
      <?php endif; ?>

      <div class="store-cards">
        <?php foreach ($stores as $s): $route = Geo::routeUrl($s); ?>
          <div class="store-card">
            <h3><?= e($s['name']) ?></h3>
            <?php $where = trim(implode(', ', array_filter([$s['city'] ?? '', $s['address'] ?? '']))); ?>
            <?php if ($where !== ''): ?><p><?= e($where) ?></p><?php endif; ?>
            <?php /* tel: лише з цифрами — з пробілами й дужками частина телефонів
                     набирає не той номер */ ?>
            <?php if ($s['phone']): ?>
              <p><a href="tel:<?= e(preg_replace('~[^\d+]~', '', (string)$s['phone'])) ?>"><?= e($s['phone']) ?></a></p>
            <?php endif; ?>
            <?php /* Графік — те, заради чого сторінку відкривають найчастіше:
                     «чи відчинено зараз». Порожній не показуємо: рядок
                     «Графік: —» гірший за його відсутність. */ ?>
            <?php if (!empty($s['hours'])): ?>
              <p class="store-hours"><?= e($s['hours']) ?></p>
            <?php endif; ?>
            <?php if ($route !== ''): ?>
              <a class="btn btn-line btn-sm" style="margin-top:10px" href="<?= e($route) ?>"
                 target="_blank" rel="noopener">Прокласти маршрут →</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($map_key && $map_points): ?>
        <script src="<?= e(asset_v('js/map.js')) ?>"></script>
        <script>
          window.BofuMap.render(document.getElementById('storesMap'), {
            key: <?= json_js($map_key) ?>,
            points: <?= json_js($map_points) ?>
          });
        </script>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>
