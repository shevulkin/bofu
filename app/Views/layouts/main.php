<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? cfg('app_name')) ?></title>
<meta name="description" content="<?= e($meta_description ?? Settings::get('seo_description', '')) ?>">
<meta property="og:title" content="<?= e($page_title ?? cfg('app_name')) ?>">
<meta property="og:description" content="<?= e($meta_description ?? Settings::get('seo_description', '')) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= e(asset('img/avatar.png')) ?>">
<meta name="theme-color" content="#141110">
<link rel="icon" href="<?= e(asset('img/avatar.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/app.css')) ?>">
<link rel="canonical" href="<?= e((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>">
<?php if (!empty($jsonld_product) && !empty($p)): ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'Product',
    'name' => $p['name'], 'description' => $p['short_desc'] ?? '',
    'image' => asset(Catalog::photo($p)),
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'UAH', 'price' => $price ?? 0,
        'availability' => 'https://schema.org/InStock'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
</head>
<body>
<?php if (Settings::bool('sale_banner_active')): ?>
<div class="sale-banner"><?= e(Settings::get('sale_banner_text', '')) ?> · −<?= e(Settings::get('sale_banner_percent', '0')) ?>%</div>
<?php endif; ?>
<?= View::partial('partials/header') ?>
<?php if ($msg = flash('success')): ?><div class="flash"><div class="flash-success"><?= e($msg) ?></div></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="flash"><div class="flash-error"><?= e($msg) ?></div></div><?php endif; ?>
<main><?= $content ?></main>
<?= View::partial('partials/footer') ?>
<?= View::partial('partials/auth_modal') ?>
<div class="cart-toast" id="cartToast" role="status" aria-live="polite">
  <div class="cart-toast-icon">✓</div>
  <div class="cart-toast-body">
    <div class="cart-toast-text">Товар додано в кошик</div>
    <div class="cart-toast-actions">
      <button type="button" class="btn btn-line btn-xs" id="cartToastContinue">Продовжити покупки</button>
      <a href="<?= e(url('/checkout')) ?>" class="btn btn-gold btn-xs">Оформити замовлення</a>
    </div>
  </div>
  <button type="button" class="cart-toast-close" id="cartToastClose" aria-label="Закрити">×</button>
</div>
<button class="to-top" id="toTop" aria-label="Догори">↑</button>
<script src="<?= e(asset_v('js/app.js')) ?>" defer></script>
</body>
</html>
