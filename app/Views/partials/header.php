<header class="topbar">
  <nav class="nav">
    <a class="brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/avatar.png')) ?>" alt="<?= e(cfg('app_name')) ?>">
      <span class="brand-text">BEEKEEPER OF UKRAINE</span>
    </a>
    <div class="nav-links">
      <a href="<?= e(url('/about')) ?>">Про мене</a>
      <a href="<?= e(url('/courses')) ?>">Курси</a>
      <a href="<?= e(url('/shop')) ?>">Магазин</a>
      <a href="<?= e(url('/diploma')) ?>">Диплом</a>
      <a href="<?= e(url('/social')) ?>">Соцмережі</a>
    </div>
    <div class="nav-side">
      <?php if ($auth_user): ?>
        <?php if (in_array($auth_user['role'], ['admin', 'seller'], true)): ?>
          <a href="<?= e(url('/admin')) ?>"><?= $auth_user['role'] === 'admin' ? 'Адмінпанель' : 'Кабінет продавця' ?></a>
        <?php else: ?>
          <a href="<?= e(url('/orders')) ?>">Мої замовлення</a>
        <?php endif; ?>
        <a class="dim" href="<?= e(url('/profile')) ?>" title="Мій профіль"><?= e($auth_user['name']) ?></a>
        <form method="post" action="<?= e(url('/logout')) ?>" style="display:inline"><?= Csrf::field() ?>
          <button class="btn btn-line btn-xs" type="submit">Вийти</button>
        </form>
      <?php else: ?>
        <a href="#" id="loginBtn">Увійти</a>
      <?php endif; ?>
      <a class="cart-link" href="<?= e(url('/cart')) ?>">Кошик
        <?php if ($cart_count > 0): ?><span class="cart-badge"><?= (int)$cart_count ?></span><?php endif; ?>
      </a>
      <button class="mobile-menu-btn" id="mobileBtn" aria-label="Меню">☰</button>
    </div>
  </nav>
  <div class="mobile-menu" id="mobileMenu">
    <a href="<?= e(url('/about')) ?>">Про мене</a>
    <a href="<?= e(url('/courses')) ?>">Курси</a>
    <a href="<?= e(url('/shop')) ?>">Магазин</a>
    <a href="<?= e(url('/diploma')) ?>">Диплом</a>
    <a href="<?= e(url('/social')) ?>">Соцмережі</a>
    <?php if ($auth_user && in_array($auth_user['role'], ['admin','seller'], true)): ?>
      <a href="<?= e(url('/admin')) ?>"><?= $auth_user['role'] === 'admin' ? 'Адмінпанель' : 'Кабінет продавця' ?></a>
    <?php elseif ($auth_user): ?>
      <a href="<?= e(url('/orders')) ?>">Мої замовлення</a>
    <?php endif; ?>
  </div>
</header>
