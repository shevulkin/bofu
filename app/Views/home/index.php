<!-- HERO -->
<section class="hero">
  <div>
    <div class="hero-badge"<?= edit_mark('hero_badge', 'title') ?>><?= e(Content::title('hero_badge', 'Аспірант · Бджоляр · Блогер · Підприємець')) ?></div>
    <h1<?= edit_mark('hero_title', 'title') ?>><?= e(Content::title('hero_title', 'Назарій Білько — Beekeeper of Ukraine')) ?></h1>
    <p class="lead"<?= edit_mark('hero_text', 'body') ?>><?= e(Content::get('hero_text')) ?></p>
    <div class="hero-actions">
      <a class="btn btn-gold" href="<?= e(url('/courses')) ?>">Обрати курс</a>
      <a class="btn btn-line" href="<?= e(url('/diploma')) ?>">Перевірити диплом</a>
    </div>
    <div class="hero-points"<?= edit_mark('hero_points') ?>>
      <?php foreach (explode("\n", Content::get('hero_points')) as $pt): if (trim($pt) === '') continue; ?>
        <span><?= e(trim($pt)) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="hero-img">
    <?php /* Це найбільший елемент першого екрана, тобто саме він і вирішує
             оцінку швидкості. fetchpriority підіймає його в черзі перед
             рештою картинок, width/height резервують місце — без них текст
             поруч стрибає, коли фото нарешті приходить. Розміри збігаються з
             тими, що задає CSS (.hero-img img), тож пропорції не пливуть. */ ?>
    <img src="<?= e(asset(Content::image('hero', 'img/about-photo.webp'))) ?>"
         alt="<?= e(Content::title('hero_title')) ?>"
         width="620" height="560" fetchpriority="high" decoding="async"<?= edit_mark('hero', 'image') ?>>
  </div>
</section>

<!-- ЛІЧИЛЬНИКИ -->
<div class="container">
  <div class="counters">
    <?php /* зона на всьому лічильнику, без data-cef: число «підкручується» анімацією,
             і підміна тексту на місці билася б із нею — такі блоки перемальовує сервер */ ?>
    <?php /* Підпис узгоджується з числом: адмін вписує саме число, а «курс/курси/
             курсів» рахує plural(). Раніше підписи були вбиті в шаблон рядком, і
             один-єдиний курс підписувався як «авторських курсів». */ ?>
    <?php
      $cGrad = (int)Content::title('counter_graduates', '153');
      $cCourses = (int)Content::title('counter_courses', '1');
    ?>
    <div class="counter"<?= edit_mark('counter_graduates') ?>><b data-count-to="<?= $cGrad ?>">0</b><span><?= e(plural($cGrad, 'випускник курсів', 'випускники курсів', 'випускників курсів')) ?></span></div>
    <div class="counter"<?= edit_mark('counter_courses') ?>><b><?= $cCourses ?></b><span><?= e(plural($cCourses, 'авторський курс', 'авторські курси', 'авторських курсів')) ?></span></div>
    <div class="counter"<?= edit_mark('counter_founded') ?>><b><?= e(Content::title('counter_founded', '2022')) ?></b><span>рік заснування</span></div>
  </div>
</div>

<!-- КАТЕГОРІЇ -->
<section class="section" id="shop">
  <div class="container">
    <div class="kicker">Магазин</div>
    <div class="section-head">
      <h2>Категорії товарів</h2>
      <a class="btn btn-line btn-sm" href="<?= e(url('/shop')) ?>">Весь магазин →</a>
    </div>
    <div class="cat-chips">
      <?php foreach ($categories as $c): ?>
        <a class="chip" href="<?= e(shop_url($c['slug'])) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="kicker" style="margin-top:26px">Обрано для вас</div>
    <div class="section-head"><h2 style="font-size:28px">Рекомендовані товари</h2></div>
    <div class="grid grid-3">
      <?php foreach ($featured as $prod): ?>
        <?= View::partial('partials/product_card', ['prod' => $prod]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ПРО МЕНЕ (тизер) -->
<section class="section" style="background:var(--bg2)">
  <div class="container" style="display:grid;grid-template-columns:1fr 1.2fr;gap:56px;align-items:center" data-rg="1">
    <img src="<?= e(asset(Content::image('about_teaser', 'img/about-photo.webp'))) ?>" alt="Про мене" style="border-radius:4px;border:1px solid var(--line2);max-height:520px;object-fit:cover;width:100%"<?= edit_mark('about_teaser', 'image') ?>>
    <div>
      <div class="kicker">Хто я</div>
      <h2<?= edit_mark('about_teaser_title', 'title') ?>><?= e(Content::title('about_teaser_title', 'Бджоляр, блогер, підприємець')) ?></h2>
      <p class="lead" style="margin:20px 0 26px"<?= edit_mark('about_teaser', 'body') ?>><?= e(Content::get('about_teaser')) ?></p>
      <a class="btn btn-line" href="<?= e(url('/about')) ?>">Читати повністю →</a>
    </div>
  </div>
</section>

<!-- ГАЛЕРЕЯ (тизер) -->
<section class="section">
  <div class="container">
    <div class="kicker">Портфоліо</div>
    <div class="section-head">
      <h2>Моє життя у галереї</h2>
      <a class="btn btn-line btn-sm" href="<?= e(url('/gallery')) ?>">Уся галерея →</a>
    </div>
    <div class="gallery-grid" data-lightbox<?= edit_mark('gallery') ?>>
      <?php foreach ($gallery_preview as $g): ?>
        <div class="gallery-item" data-full="<?= e(asset($g[1])) ?>"><img src="<?= e(asset(Images::displayThumb($g[1]))) ?>" alt="<?= e($g[0]) ?>" loading="lazy"><span><?= e($g[0]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- КУРС -->
<section class="section" style="background:var(--bg2)">
  <div class="container">
    <div class="kicker" style="text-align:center">Навчання</div>
    <h2 style="text-align:center;margin-bottom:30px">Курс бджільництва</h2>
    <div class="card center-block" style="max-width:640px">
      <div class="card-body" style="padding:30px">
        <div class="card-title" style="font-size:26px"<?= edit_mark('course_1', 'title') ?>><?= e(Content::title('course_1')) ?></div>
        <div class="card-desc" style="font-size:15px"<?= edit_mark('course_1', 'body') ?>><?= e(Content::get('course_1')) ?></div>
        <div class="card-foot">
          <span class="price">За запитом</span>
          <?php /* адреса живе в атрибуті href — правиться, але на місці не показується */ ?>
          <a class="btn btn-gold btn-sm" href="<?= e(safe_url(Content::title('course_1_link', '#'))) ?>" target="_blank" rel="noopener"<?= edit_mark('course_1_link') ?>>Записатись</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ЧОМУ Я -->
<section class="section">
  <div class="container narrow">
    <div class="kicker">Чому саме я</div>
    <h2>Практичний досвід, а не теорія</h2>
    <div style="margin-top:18px">
      <?php foreach (['why_1', 'why_2', 'why_3'] as $i => $key): ?>
        <div class="why-item">
          <div class="why-num">0<?= $i + 1 ?></div>
          <div>
            <div style="font-family:var(--serif);font-size:22px;font-weight:600"<?= edit_mark($key, 'title') ?>><?= e(Content::title($key)) ?></div>
            <p class="muted" style="margin-top:6px"<?= edit_mark($key, 'body') ?>><?= e(Content::get($key)) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section" style="background:var(--bg2)">
  <div class="container narrow">
    <div class="kicker">Питання</div>
    <h2>Часті запитання</h2>
    <div style="margin-top:16px"<?= edit_mark('faq') ?>>
      <?php foreach ($faq as $qa): ?>
        <details class="faq-item"><summary><?= e($qa[0]) ?></summary><p><?= e($qa[1]) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ДИПЛОМ -->
<section class="section section-center">
  <div class="container">
    <div class="kicker">Перевірка</div>
    <h2>Перевірка справжності диплому</h2>
    <p class="lead" style="margin:14px auto 24px">Введіть номер диплому, щоб перевірити його справжність.</p>
    <form class="diploma-box center-block" method="post" action="<?= e(url('/diploma/check')) ?>" style="display:flex;gap:12px;align-items:center">
      <?= Csrf::field() ?>
      <input type="text" name="number" placeholder="BOFU-2024-001" required style="flex:1">
      <button class="btn btn-gold" type="submit">Перевірити</button>
    </form>
  </div>
</section>

<!-- СОЦМЕРЕЖІ -->
<section class="section">
  <div class="container">
    <div class="kicker">Соцмережі</div>
    <div class="section-head">
      <h2>Instagram, YouTube &amp; TikTok</h2>
      <a class="btn btn-line btn-sm" href="<?= e(url('/social')) ?>">Усі відео та пости →</a>
    </div>
    <?php $videos = YouTube::latest(3); ?>
    <?php if ($videos): ?>
      <div class="grid grid-3" style="margin-bottom:26px">
        <?php foreach ($videos as $v): ?>
          <a class="card" href="<?= e(safe_url($v['url'])) ?>" target="_blank" rel="noopener">
            <div class="card-img" style="aspect-ratio:16/9"><img src="<?= e($v['thumb']) ?>" alt="<?= e($v['title']) ?>" loading="lazy"><?php if (!empty($v['is_short'])): ?><span class="badge red" style="background:#FF0000;color:#fff">▶ Shorts</span><?php endif; ?></div>
            <div class="card-body">
              <div class="card-title" style="font-size:16px"><?= e($v['title']) ?></div>
              <div class="dim"><?= e($v['published']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div style="display:flex;gap:18px;flex-wrap:wrap">
      <?php
      $mini = [
        ['Instagram', 'social_instagram', '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#E4405F" stroke-width="2"><rect x="2.5" y="2.5" width="19" height="19" rx="5.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.6" cy="6.4" r="1.3" fill="#E4405F" stroke="none"/></svg>'],
        ['YouTube', 'social_youtube', '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#FF0000" d="M23.5 6.5a3 3 0 0 0-2.1-2.2C19.5 3.8 12 3.8 12 3.8s-7.5 0-9.4.5A3 3 0 0 0 .5 6.5 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.5 3 3 0 0 0 2.1 2.2c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.2A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.5z"/><path fill="#fff" d="M9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>'],
        ['TikTok', 'social_tiktok', '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="#f6ecd9" d="M16.6 2h3.1c.2 1.9 1.4 3.6 3.3 4v3.1c-1.2 0-2.4-.4-3.4-1v6.9a6.9 6.9 0 1 1-6.9-6.9c.3 0 .7 0 1 .1v3.3a3.6 3.6 0 1 0 2.9 3.5V2z"/></svg>'],
      ];
      foreach ($mini as [$name, $ckey, $icon]): ?>
        <a class="chip" href="<?= e(safe_url(Content::title($ckey, '#'))) ?>" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:9px;text-transform:none;letter-spacing:0;font-size:13.5px"<?= edit_mark($ckey) ?>><?= $icon ?><?= e($name) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section" style="background:var(--bg2);text-align:center">
  <div class="container narrow">
    <h2<?= edit_mark('final_cta_title', 'title') ?>><?= e(Content::title('final_cta_title', 'Готові розпочати?')) ?></h2>
    <p class="lead" style="margin:16px auto 30px"<?= edit_mark('final_cta_text', 'body') ?>><?= e(Content::get('final_cta_text')) ?></p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-gold" href="<?= e(url('/courses')) ?>">Записатись на курс</a>
      <a class="btn btn-line" href="<?= e(url('/shop')) ?>">Перейти в магазин</a>
    </div>
  </div>
</section>
