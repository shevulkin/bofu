<?php
/**
 * Сторінка курсу.
 *
 * Порядок блоків — це порядок питань, які людина ставить перед такою покупкою:
 * що це → що я з цього матиму → наскільки серйозно → як влаштовано → чому вам →
 * чи це визнають → що досі незрозуміло. Програма стоїть ПІСЛЯ результатів
 * навмисно: перелік тем відповідає на «чого ви навчатимете», а купують за
 * відповідь на «ким я стану».
 *
 * @var array $prod  @var array $facts  @var array $photos
 * @var bool  $owned @var bool  $open   @var int   $graduates @var array $faq
 */
[$price, $old] = Catalog::price($prod);
$learn = array_values(array_filter(array_map('trim',
    preg_split('~\R~', (string)($prod['learn_outcomes'] ?? '')) ?: []), fn($s) => $s !== ''));
$program = array_values(array_filter(array_map('trim',
    preg_split('~\R~', (string)($prod['program'] ?? '')) ?: []), fn($s) => $s !== ''));
?>
<section class="section" style="padding-top:40px">
  <div class="container narrow" style="max-width:860px">
    <p style="margin-bottom:22px"><a href="<?= e(url('/courses')) ?>">← Усі курси</a></p>

    <?php /* Обкладинка: назва, суть одним рядком, ціна й дія. Усе, що потрібно
             для рішення «читати далі чи ні», — до першого прокручування. */ ?>
    <div class="course-hero">
      <?php $photo = Catalog::photo($prod); if ($photo): ?>
        <div class="course-hero-img">
          <?php /* Оригінал, а не превʼю: обкладинка йде на всю ширину, і
                   мініатюра з каталогу тут розсипалась би на пікселі */ ?>
          <img src="<?= e(asset($photo)) ?>" alt="<?= e($prod['name']) ?>">
          <?php if ($owned): ?><span class="course-badge">Ваш курс</span><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="kicker" style="margin-top:26px">Навчання</div>
      <h1 style="font-size:40px;line-height:1.1"><?= e($prod['name']) ?></h1>
      <?php if (trim((string)($prod['short_desc'] ?? '')) !== ''): ?>
        <p class="lead" style="margin-top:14px"><?= e($prod['short_desc']) ?></p>
      <?php endif; ?>

      <?php if ($facts): ?>
        <ul class="course-facts course-facts-lg">
          <?php foreach ($facts as $f): ?>
            <li><span><?= e($f['name']) ?></span><b><?= e($f['value']) ?><?= !empty($f['unit']) ? ' ' . e($f['unit']) : '' ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php /* Дія залежить від того, ким людина сюди прийшла. Кнопка «До кошика»
               тому, хто вже заплатив, — найгірше, що може показати сторінка:
               пропонує купити вдруге те, що вже його, і не каже, куди йти
               дивитись. */ ?>
      <div class="course-buy">
        <?php if ($open): ?>
          <div class="course-owned-note">
            <b>✓ Курс уже ваш</b>
            <span>Матеріали зʼявляться в кабінеті — ми напишемо, щойно вони відкриються.</span>
          </div>
          <a class="btn btn-gold" href="<?= e(url('/learning')) ?>">Перейти до навчання →</a>
        <?php else: ?>
          <?php if ($owned): ?>
            <div class="course-owned-note">
              <b>Строк доступу вийшов</b>
              <span>Ви вже проходили цей курс. Щоб повернути доступ, оформіть його ще раз.</span>
            </div>
          <?php endif; ?>
          <span class="price" style="font-size:30px">
            <?php if ($old !== null): ?><s><?= e(price_fmt($old)) ?></s><?php endif; ?>
            <?= e(price_label($price, true)) ?></span>
          <form method="post" action="<?= e(url('/cart/add')) ?>" class="add-cart-form"
                data-product-name="<?= e($prod['name']) ?>"><?= Csrf::field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
            <button class="btn btn-gold" type="submit"><?= $owned ? 'Продовжити доступ' : 'Записатись на курс' ?></button>
          </form>
        <?php endif; ?>
      </div>
      <?php if (!empty($prod['access_days'])): ?>
        <p class="dim" style="margin-top:10px;font-size:13px">Доступ до матеріалів —
          <?= (int)$prod['access_days'] ?> днів після оплати.</p>
      <?php else: ?>
        <p class="dim" style="margin-top:10px;font-size:13px">Доступ до матеріалів залишається назавжди.</p>
      <?php endif; ?>
    </div>

    <?php if ($learn): ?>
      <h2 style="font-size:26px;margin-top:52px">Чого ви навчитесь</h2>
      <ul class="course-learn">
        <?php foreach ($learn as $l): ?><li><?= e($l) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (trim((string)($prod['description'] ?? '')) !== ''): ?>
      <h2 style="font-size:26px;margin-top:48px">Про курс</h2>
      <div class="course-text"><?= nl2br(e($prod['description'])) ?></div>
    <?php endif; ?>

    <?php if ($program): ?>
      <h2 style="font-size:26px;margin-top:48px">Програма</h2>
      <ol class="course-program">
        <?php foreach ($program as $i => $step): ?>
          <li><span><?= $i + 1 ?></span><div><?= e($step) ?></div></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <?php /* Фотографії — після програми, а не замість неї: вони підтверджують
             сказане, але самі нічого не доводять. */ ?>
    <?php if (count($photos) > 1): ?>
      <h2 style="font-size:26px;margin-top:48px">Як це відбувається</h2>
      <div class="course-gallery">
        <?php foreach (array_slice($photos, 0, 6) as $img): ?>
          <img src="<?= e(asset(Images::displayThumb($img['path']))) ?>" alt="" loading="lazy">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php /* Диплом із перевіркою справжності — те, чим цей курс відрізняється
             від «курсу з інстаграма», і те, що вже працює на сайті. Досі про
             перевірку знав лише той, хто сам знайшов /diploma. */ ?>
    <div class="course-diploma">
      <div>
        <h3 class="h-serif" style="font-size:22px;margin:0">Диплом, який можна перевірити</h3>
        <p style="margin:8px 0 0;color:var(--muted);font-size:14.5px;line-height:1.6">
          Після закінчення ви отримуєте диплом із власним номером. Будь-хто —
          роботодавець, партнер, покупець вашого меду — може перевірити його
          справжність просто на сайті, без дзвінків і листів.
          <?php if ($graduates > 0): ?>
            <br>За цим курсом уже видано <b><?= $graduates ?></b> <?= $graduates === 1 ? 'диплом' : 'дипломів' ?>.
          <?php endif; ?>
        </p>
      </div>
      <a class="btn btn-line btn-sm" href="<?= e(url('/diploma')) ?>">Як працює перевірка</a>
    </div>

    <?php if ($faq): ?>
      <h2 style="font-size:26px;margin-top:48px">Часті запитання</h2>
      <div>
        <?php foreach ($faq as $qa): ?>
          <details class="faq-item"><summary><?= e($qa[0]) ?></summary><p><?= e($qa[1]) ?></p></details>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
