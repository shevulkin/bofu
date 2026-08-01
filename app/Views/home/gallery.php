<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Портфоліо</div>
    <h1 style="font-size:44px">Моє життя у галереї</h1>
    <div class="gallery-grid" style="margin-top:36px">
      <?php foreach ($gallery as $g): ?>
        <div class="gallery-item"><img src="<?= e(asset(Images::displayThumb($g[1]))) ?>" alt="<?= e($g[0]) ?>" loading="lazy"><span><?= e($g[0]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
