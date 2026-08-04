<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Соцмережі</div>
    <h1 style="font-size:44px">Instagram, YouTube &amp; TikTok</h1>
    <p class="lead" style="margin:18px 0 40px">Щоденне життя пасіки, навчальні відео та новини — підписуйтесь.</p>
    <?php $videos = YouTube::latest(6); ?>
    <?php if ($videos): ?>
      <div class="kicker">YouTube</div>
      <div class="section-head"><h2 style="font-size:26px">Останні відео</h2></div>
      <div class="grid grid-3" style="margin-bottom:56px">
        <?php foreach ($videos as $v): ?>
          <a class="card" href="<?= e($v['url']) ?>" target="_blank" rel="noopener">
            <div class="card-img" style="aspect-ratio:16/9"><img src="<?= e($v['thumb']) ?>" alt="<?= e($v['title']) ?>" loading="lazy"><?php if (!empty($v['is_short'])): ?><span class="badge red" style="background:#FF0000;color:#fff">▶ Shorts</span><?php endif; ?></div>
            <div class="card-body">
              <div class="card-title" style="font-size:16px"><?= e($v['title']) ?></div>
              <div class="dim"><?= e($v['published']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="grid grid-3">
      <?php
      $icons = [
        'instagram' => '<svg viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="#E4405F" stroke-width="1.8"><rect x="2.5" y="2.5" width="19" height="19" rx="5.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.6" cy="6.4" r="1.3" fill="#E4405F" stroke="none"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" width="46" height="46"><path fill="#FF0000" d="M23.5 6.5a3 3 0 0 0-2.1-2.2C19.5 3.8 12 3.8 12 3.8s-7.5 0-9.4.5A3 3 0 0 0 .5 6.5 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.5 3 3 0 0 0 2.1 2.2c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.2A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.5z"/><path fill="#fff" d="M9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" width="46" height="46"><path fill="#f6ecd9" d="M16.6 2h3.1c.2 1.9 1.4 3.6 3.3 4v3.1c-1.2 0-2.4-.4-3.4-1v6.9a6.9 6.9 0 1 1-6.9-6.9c.3 0 .7 0 1 .1v3.3a3.6 3.6 0 1 0 2.9 3.5V2z"/><path fill="#25F4EE" d="M16.6 2h1c.1 1 .5 1.9 1.2 2.7A5.6 5.6 0 0 1 16.6 2z" opacity=".9"/><path fill="#FE2C55" d="M13.7 8.3v1c-.3-.1-.7-.1-1-.1a6.9 6.9 0 0 0-4.9 11.7 6.9 6.9 0 0 1 5.9-11.7z" opacity=".85"/></svg>',
      ];
      $nets = [
        ['Instagram', 'social_instagram', 'Фото та сторіз із пасіки', $icons['instagram']],
        ['YouTube', 'social_youtube', 'Навчальні відео та огляди', $icons['youtube']],
        ['TikTok', 'social_tiktok', 'Короткі відео щодня', $icons['tiktok']],
      ];
      foreach ($nets as [$name, $ckey, $desc, $icon]): ?>
        <a class="card" href="<?= e(Content::title($ckey, '#')) ?>" target="_blank" rel="noopener" style="align-items:center;text-align:center;padding:40px 24px"<?= edit_mark($ckey) ?>>
          <div style="height:48px;display:flex;align-items:center;justify-content:center"><?= $icon ?></div>
          <div class="card-title" style="margin-top:12px"><?= e($name) ?></div>
          <div class="card-desc" style="margin-top:6px"><?= e($desc) ?></div>
          <span class="btn btn-line btn-sm" style="margin-top:18px">Перейти →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
