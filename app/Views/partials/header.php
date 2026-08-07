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
      <a href="<?= e(url('/partners')) ?>">Партнери</a>
      <a href="<?= e(url('/stores')) ?>">Де нас знайти</a>
    </div>
    <div class="nav-side">
      <?php if ($auth_user): ?>
        <?= View::partial('partials/role_switch') ?>
        <?php /* Вхід у режим редагування живе в адмінці (Контент сайту), а не тут:
                 тексти правлять рідко, а кнопка висіла б у шапці на кожній
                 сторінці вітрини — і в персоналу, і поруч із кошиком покупця.
                 У самому режимі перемикач лишається у смужці внизу. */ ?>
        <?php /* через Auth, а не $auth_user['role'] — інакше режим перегляду сюди не дійде */ ?>
        <?php if (Auth::isStaff()): ?>
          <a href="<?= e(url('/admin')) ?>"><?= Auth::isAdmin() ? 'Адмінпанель' : 'Кабінет продавця' ?></a>
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
    <a href="<?= e(url('/partners')) ?>">Партнери</a>
    <a href="<?= e(url('/stores')) ?>">Де нас знайти</a>
    <?php if ($auth_user && Auth::isStaff()): ?>
      <a href="<?= e(url('/admin')) ?>"><?= Auth::isAdmin() ? 'Адмінпанель' : 'Кабінет продавця' ?></a>
    <?php elseif ($auth_user): ?>
      <a href="<?= e(url('/orders')) ?>">Мої замовлення</a>
    <?php endif; ?>
  </div>
</header>
