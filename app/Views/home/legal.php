<?php /**
 * Правова сторінка: доставка, оплата, повернення, приватність, оферта.
 *
 * Один шаблон на всі пʼять — відрізняється лише заголовок і текст. Набрано
 * вузькою колонкою: це документ, який читають рядок за рядком, а не вітрина.
 */ ?>
<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Умови</div>
    <h1 style="font-size:40px"><?= e($heading) ?></h1>

    <?php if (trim($text) === ''): ?>
      <?php /* Порожня правова сторінка гірша за її відсутність: покупець читає
               порожнечу як «нам нема чого сказати». Кажемо прямо, що текст ще
               не заповнено, і не вдаємо, ніби умови існують. */ ?>
      <p class="lead" style="margin-top:22px">Текст цієї сторінки ще не заповнено.
        Напишіть нам — відповімо на будь-яке питання про умови.</p>
    <?php else: ?>
      <div class="legal-text"<?= edit_mark($block, 'body') ?>><?= e($text) ?></div>
    <?php endif; ?>

    <?php /* Хто продавець — під кожною правовою сторінкою. Закон «Про
             електронну комерцію» вимагає, щоб покупець бачив, з ким укладає
             договір; порожні реквізити показуємо як попередження власнику, а
             не як порожнє місце покупцю. */ ?>
    <div class="legal-entity">
      <?php if (trim($entity) !== ''): ?>
        <p class="legal-entity-name"<?= edit_mark('legal_entity', 'title') ?>><?= e($entity) ?></p>
        <?php if (trim($entity_details) !== ''): ?>
          <p class="dim" style="white-space:pre-line;margin-top:6px"<?= edit_mark('legal_entity', 'body') ?>><?= e($entity_details) ?></p>
        <?php endif; ?>
      <?php else: ?>
        <p class="dim" style="margin:0"<?= edit_mark('legal_entity', 'title') ?>>Реквізити продавця не заповнені.
          Їх треба вказати в адмінці: Контент сайту → «Хто продавець (реквізити)».</p>
      <?php endif; ?>

      <?php $phone = Content::title('contact_phone'); $email = Content::title('contact_email'); ?>
      <?php if ($phone !== '' || $email !== ''): ?>
        <p class="dim" style="margin-top:10px">
          <?php if ($phone !== ''): ?><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
          <?= $phone !== '' && $email !== '' ? ' · ' : '' ?>
          <?php if ($email !== ''): ?><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
        </p>
      <?php endif; ?>
      <?php /* Дати редакції тут свідомо немає: content_blocks не зберігає часу
               зміни, а date() малював би сьогоднішнє число щодня — «редакція
               від сьогодні» на договорі, якого не чіпали рік. Порожнє місце
               чесніше за вигадану дату. */ ?>
    </div>
  </div>
</section>
