<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:56px;align-items:start" data-rg="1">
      <img src="<?= e(asset(Content::image('about_full', 'img/about-photo.png'))) ?>" alt="Про мене" style="border-radius:4px;border:1px solid var(--line2);width:100%;object-fit:cover">
      <div>
        <div class="kicker">Хто я</div>
        <h1 style="font-size:44px"><?= e(Content::title('about_full', 'Бджоляр, блогер, підприємець')) ?></h1>
        <p class="lead" style="margin-top:22px;white-space:pre-line"><?= e(Content::get('about_full')) ?></p>
      </div>
    </div>
    <div class="kicker" style="margin-top:72px">Портфоліо</div>
    <h2>Галерея</h2>
    <div class="gallery-grid" style="margin-top:26px">
      <?php foreach ($gallery as $g): ?>
        <div class="gallery-item"><img src="<?= e(asset($g[1])) ?>" alt="<?= e($g[0]) ?>" loading="lazy"><span><?= e($g[0]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
