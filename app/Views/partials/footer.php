<?php
/**
 * Підвал сайту.
 *
 * Раніше тут стояли логотип, три іконки й копірайт — і жодної відповіді на
 * питання, заради якого в підвал і йдуть: «а ви взагалі хто і як з вами
 * звʼязатись». Тепер чотири колонки в порядку від найпотрібнішого:
 * контакти → умови покупки → про нас → правове.
 *
 * Порожні значення не показуються. Телефон і пошта живуть у блоках контенту,
 * тож поки власник їх не заповнив, колонка контактів просто коротша — але
 * вигаданого номера в підвалі не зʼявиться ніколи.
 */
$phone = Content::title('contact_phone');
$email = Content::title('contact_email');
$hours = Content::title('contact_hours');
$entity = Content::title('legal_entity');
?>
<footer>
  <div class="container footer-grid">
    <div class="footer-col footer-brand-col">
      <div class="brand"><img src="<?= e(asset('img/favicon.png')) ?>" width="34" height="34" alt=""> <span class="brand-text" style="display:inline">BEEKEEPER OF UKRAINE</span></div>
      <p class="dim" style="margin-top:12px">Мед і продукти бджільництва з власної пасіки. Навчаємо бджільництва з 2022 року.</p>
      <div class="footer-social">
        <a href="<?= e(Content::title('social_instagram', '#')) ?>" target="_blank" rel="noopener" title="Instagram" aria-label="Instagram"<?= edit_mark('social_instagram') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2.5" y="2.5" width="19" height="19" rx="5.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.6" cy="6.4" r="1.3" fill="currentColor" stroke="none"/></svg>Instagram</a>
        <a href="<?= e(Content::title('social_youtube', '#')) ?>" target="_blank" rel="noopener" title="YouTube" aria-label="YouTube"<?= edit_mark('social_youtube') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M23.5 6.5a3 3 0 0 0-2.1-2.2C19.5 3.8 12 3.8 12 3.8s-7.5 0-9.4.5A3 3 0 0 0 .5 6.5 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.5 3 3 0 0 0 2.1 2.2c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.2A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.5zM9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>YouTube</a>
        <a href="<?= e(Content::title('social_tiktok', '#')) ?>" target="_blank" rel="noopener" title="TikTok" aria-label="TikTok"<?= edit_mark('social_tiktok') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16.6 2h3.1c.2 1.9 1.4 3.6 3.3 4v3.1c-1.2 0-2.4-.4-3.4-1v6.9a6.9 6.9 0 1 1-6.9-6.9c.3 0 .7 0 1 .1v3.3a3.6 3.6 0 1 0 2.9 3.5V2z"/></svg>TikTok</a>
      </div>
    </div>

    <div class="footer-col">
      <h4>Контакти</h4>
      <ul>
        <?php if ($phone !== ''): ?>
          <?php /* tel: без пробілів і дужок — інакше частина телефонів набирає
                   номер неправильно. Показуємо при цьому людський запис. */ ?>
          <li><a href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"<?= edit_mark('contact_phone', 'title') ?>><?= e($phone) ?></a></li>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <li><a href="mailto:<?= e($email) ?>"<?= edit_mark('contact_email', 'title') ?>><?= e($email) ?></a></li>
        <?php endif; ?>
        <?php if ($hours !== ''): ?>
          <li class="dim"<?= edit_mark('contact_hours', 'title') ?>><?= e($hours) ?></li>
        <?php endif; ?>
        <li><a href="<?= e(url('/stores')) ?>">Наші магазини</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Покупцеві</h4>
      <ul>
        <li><a href="<?= e(url('/delivery')) ?>">Доставка</a></li>
        <li><a href="<?= e(url('/payment')) ?>">Оплата</a></li>
        <li><a href="<?= e(url('/returns')) ?>">Обмін і повернення</a></li>
        <li><a href="<?= e(url('/shop')) ?>">Магазин</a></li>
      </ul>
    </div>

    <div class="footer-col">
      <h4>Про нас</h4>
      <ul>
        <li><a href="<?= e(url('/about')) ?>">Про мене</a></li>
        <li><a href="<?= e(url('/courses')) ?>">Курси</a></li>
        <li><a href="<?= e(url('/diploma')) ?>">Перевірка диплома</a></li>
        <li><a href="<?= e(url('/partners')) ?>">Партнери</a></li>
      </ul>
    </div>
  </div>

  <div class="container footer-legal">
    <p class="footer-copy">
      © <?= date('Y') ?> <?= $entity !== '' ? e($entity) : 'Beekeeper of Ukraine' ?>.
      Усі права захищено.
    </p>
    <p class="footer-copy">
      <a href="<?= e(url('/offer')) ?>">Публічна оферта</a> ·
      <a href="<?= e(url('/privacy')) ?>">Політика конфіденційності</a>
    </p>
  </div>
</footer>
