<?php
/**
 * Наші партнери.
 *
 * Сітка рівних карток: лого, назва, одне-два речення. Партнерів дивляться не
 * читаючи — це сторінка про довіру, і працює вона рядом лого, а не текстом.
 * Тому опис короткий і не обовʼязковий, а картка без лого показує назву
 * великими літерами, щоб не провалюватись діркою в рівному ряду.
 *
 * Уся картка — посилання, коли сайт заданий. Без сайту вона лишається карткою:
 * партнер без сайту (господарство з самим лише Instagram) не має ставати
 * мертвим посиланням, по якому клацають і нічого не стається.
 *
 * @var array $partners
 */
?>
<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Разом</div>
    <h1 style="font-size:44px">Наші партнери</h1>
    <p class="lead" style="margin:18px 0 40px">
      Господарства, школи та крамниці, з якими ми працюємо. З кожним із них нас звели
      спільні бджоли — і за кожного з них ми ручаємось.
    </p>

    <?php if (!$partners): ?>
      <p class="dim">Список партнерів готується.</p>
    <?php else: ?>
      <div class="partner-grid">
        <?php foreach ($partners as $p):
              $tag = $p['url'] ? 'a' : 'div';
              $attr = $p['url'] ? ' href="' . e(safe_url($p['url'])) . '" target="_blank" rel="noopener"' : ''; ?>
          <<?= $tag ?> class="partner<?= $p['url'] ? ' is-link' : '' ?>"<?= $attr ?>>
            <div class="partner-logo">
              <?php if (!empty($p['logo'])): ?>
                <img src="<?= e(asset($p['logo'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <?php else: ?>
                <span class="partner-initial"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
              <?php endif; ?>
            </div>
            <div class="partner-name"><?= e($p['name']) ?></div>
            <?php if (!empty($p['description'])): ?>
              <p class="partner-desc"><?= e($p['description']) ?></p>
            <?php endif; ?>
            <?php if ($p['url']): ?><span class="partner-go">Перейти →</span><?php endif; ?>
          </<?= $tag ?>>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
