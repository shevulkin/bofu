<?php
/**
 * Підвал сайту.
 *
 * Центрований і компактний. Колонки тут пробувались і не вижили: під
 * центрування вони не лягають — заголовки різної довжини, списки різної
 * висоти, а майже порожні «Контакти» поруч із повними «Покупцеві» дають
 * рвані краї й дірку посеред екрана. Колонки потребують лівого краю, якого
 * центрований підвал не має.
 *
 * Тому посилання йдуть одним рядком, що переноситься: їх дев'ять — це рівно
 * той обсяг, який читається переліком і ще не потребує рубрикації. Порядок —
 * від найпотрібнішого: магазин, умови покупки, про нас.
 *
 * Порожні значення не показуються: поки телефон і пошта не заповнені, рядка
 * контактів просто немає. Вигаданого номера в підвалі не з'явиться ніколи.
 */
$phone = Content::title('contact_phone');
$email = Content::title('contact_email');
$hours = Content::title('contact_hours');
$entity = Content::title('legal_entity');

$links = [
    ['/shop', 'Магазин'],
    ['/delivery', 'Доставка'],
    ['/payment', 'Оплата'],
    ['/returns', 'Обмін і повернення'],
    ['/stores', 'Наші магазини'],
    ['/about', 'Про мене'],
    ['/courses', 'Курси'],
    ['/diploma', 'Перевірка диплома'],
    ['/partners', 'Партнери'],
];
?>
<footer>
  <div class="container footer-inner">
    <a class="brand footer-brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/favicon.png')) ?>" width="34" height="34" alt="">
      <span class="brand-text">BEEKEEPER OF UKRAINE</span>
    </a>
    <p class="footer-tagline">Мед і продукти бджільництва з власної пасіки. Навчаємо бджільництва з 2022 року.</p>

    <?php /* Спосіб звʼязку — найбільший елемент підвалу після знака: у підвал
             ідуть саме по нього. Телефон антиквою в розмір заголовка, під ним
             тихіше пошта й години. На телефоні номер набирається одним дотиком. */ ?>
    <?php if ($phone !== '' || $email !== '' || $hours !== ''): ?>
      <div class="footer-contacts">
        <?php if ($phone !== ''): ?>
          <a class="footer-phone" href="tel:<?= e(preg_replace('~[^\d+]~', '', $phone)) ?>"<?= edit_mark('contact_phone', 'title') ?>><?= e($phone) ?></a>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <a href="mailto:<?= e($email) ?>"<?= edit_mark('contact_email', 'title') ?>><?= e($email) ?></a>
        <?php endif; ?>
        <?php if ($hours !== ''): ?>
          <span class="footer-hours"<?= edit_mark('contact_hours', 'title') ?>><?= e($hours) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <nav class="footer-nav" aria-label="Розділи сайту">
      <?php foreach ($links as [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="footer-social">
      <a href="<?= e(Content::title('social_instagram', '#')) ?>" target="_blank" rel="noopener" aria-label="Instagram"<?= edit_mark('social_instagram') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2.5" y="2.5" width="19" height="19" rx="5.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.6" cy="6.4" r="1.3" fill="currentColor" stroke="none"/></svg>Instagram</a>
      <a href="<?= e(Content::title('social_youtube', '#')) ?>" target="_blank" rel="noopener" aria-label="YouTube"<?= edit_mark('social_youtube') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M23.5 6.5a3 3 0 0 0-2.1-2.2C19.5 3.8 12 3.8 12 3.8s-7.5 0-9.4.5A3 3 0 0 0 .5 6.5 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.5 3 3 0 0 0 2.1 2.2c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.2A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.5zM9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>YouTube</a>
      <a href="<?= e(Content::title('social_tiktok', '#')) ?>" target="_blank" rel="noopener" aria-label="TikTok"<?= edit_mark('social_tiktok') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M16.6 2h3.1c.2 1.9 1.4 3.6 3.3 4v3.1c-1.2 0-2.4-.4-3.4-1v6.9a6.9 6.9 0 1 1-6.9-6.9c.3 0 .7 0 1 .1v3.3a3.6 3.6 0 1 0 2.9 3.5V2z"/></svg>TikTok</a>
    </div>

    <?php /* Рік і правові посилання — один підпис. Кожне посилання разом зі
             своєю крапкою нерозривним шматком, щоб при перенесенні крапка не
             лишалась висіти на початку нового рядка. */ ?>
    <p class="footer-copy">© <?= date('Y') ?> <?= $entity !== '' ? e($entity) : 'Beekeeper of Ukraine' ?>. Усі права захищено.<span class="footer-legal-item">· <a href="<?= e(url('/offer')) ?>">Публічна оферта</a></span><span class="footer-legal-item">· <a href="<?= e(url('/privacy')) ?>">Політика конфіденційності</a></span></p>
  </div>
</footer>
