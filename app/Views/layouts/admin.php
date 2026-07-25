<?php /** @var string $content */ $cur = request_path(); ?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Адмінка') ?></title>
<meta name="theme-color" content="#141110">
<link rel="icon" href="<?= e(asset('img/avatar.png')) ?>">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/avatar.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="admin-mobilebar">
  <button class="mobile-menu-btn" style="display:flex" onclick="document.querySelector('.admin-side').classList.toggle('open')">☰</button>
  <b><?= Auth::isAdmin() ? 'Адмінпанель' : 'Кабінет продавця' ?></b>
</div>
<div class="admin-wrap">
  <aside class="admin-side">
    <a class="brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/avatar.png')) ?>" alt=""> <span class="brand-text" style="font-size:14px">BOFU · <?= Auth::isAdmin() ? 'Адмін' : 'Продавець' ?></span>
    </a>
    <?php
    $items = [
        ['/admin', 'Панель', true],
        ['/admin/orders', 'Замовлення', true],
        ['/admin/products', 'Товари', true],
        ['/admin/products/bulk', 'Масове редагування', true],
        ['/admin/categories', 'Категорії', Auth::isAdmin()],
        ['/admin/stores', 'Магазини', Auth::isAdmin()],
        ['/admin/promos', 'Акції та промокоди', Auth::isAdmin()],
        ['/admin/diplomas', 'Дипломи', Auth::isAdmin()],
        ['/admin/users', 'Користувачі', Auth::isAdmin()],
        ['/admin/content', 'Контент сайту', Auth::isAdmin()],
        ['/admin/media', 'Медіа-бібліотека', Auth::isAdmin()],
        ['/admin/notifications', 'Сповіщення', Auth::isAdmin()],
        ['/admin/settings', 'Налаштування', Auth::isAdmin()],
    ];
    foreach ($items as [$href, $label, $show]): if (!$show) continue; ?>
      <a href="<?= e(url($href)) ?>" class="<?= $cur === $href ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="sep"></div>
    <a href="<?= e(url('/')) ?>">← На сайт</a>
    <form method="post" action="<?= e(url('/logout')) ?>" style="padding:8px 22px"><?= Csrf::field() ?>
      <button class="btn btn-line btn-xs" type="submit" style="width:100%">Вийти (<?= e($auth_user['name'] ?? '') ?>)</button>
    </form>
    <div style="padding:12px 22px">
      <button class="btn btn-line btn-xs" style="width:100%;display:none" id="pwaInstall">📱 Встановити додаток</button>
      <button class="btn btn-line btn-xs" style="width:100%;margin-top:8px;display:none" id="pushEnable">🔔 Увімкнути пуші</button>
    </div>
  </aside>
  <main class="admin-main">
    <?php if ($msg = flash('success')): ?><div class="flash" style="padding:0;margin:0 0 16px"><div class="flash-success"><?= e($msg) ?></div></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="flash" style="padding:0;margin:0 0 16px"><div class="flash-error"><?= e($msg) ?></div></div><?php endif; ?>
    <?= $content ?>
  </main>
</div>
<script>
window.BOFU = { base: '<?= e(url('/')) ?>', vapid: '<?= e(Settings::get('vapid_public', '')) ?>' };
</script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
