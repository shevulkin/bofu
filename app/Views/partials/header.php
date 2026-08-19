<?php
/*
 * Категорії для меню шапки.
 *
 * Питаємо їх прямо тут, як footer питає контакти: шапка малюється на кожній
 * сторінці вітрини, і носити цей список через кожен контролер означало б
 * пам'ятати про нього в кожному новому. Запит один і на кожній сторінці
 * однаковий.
 */
$navCats = Catalog::categoryTree();
?>
<header class="topbar">
  <nav class="nav">
    <a class="brand" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/favicon.png')) ?>" width="34" height="34" alt="<?= e(cfg('app_name')) ?>">
      <span class="brand-text">BEEKEEPER OF UKRAINE</span>
    </a>
    <div class="nav-links">
      <a href="<?= e(url('/about')) ?>">Про мене</a>
      <a href="<?= e(url('/courses')) ?>">Курси</a>
      <?php /* «Магазин» — це пункт меню й одночасно вхід у категорії. Сама
               назва лишається звичайним посиланням у каталог, а стрілка поруч
               відкриває список розділів: покупець, який знає, що йому треба,
               потрапляє в потрібний розділ одним кліком із будь-якої сторінки,
               а не через каталог і панель фільтрів.

               Стрілка — окрема кнопка, а не клік по «Магазину»: інакше пункт
               меню перестав би вести туди, куди обіцяє. Без JS меню не
               розкриється, і це не втрата — посилання в каталог працює, а вже
               там ті самі розділи стоять панеллю. */ ?>
      <?php if ($navCats): ?>
        <span class="nav-drop" data-nav-drop>
          <a href="<?= e(url('/shop')) ?>">Магазин</a>
          <button type="button" class="nav-drop-btn" data-nav-drop-btn
                  aria-controls="navShopMenu" aria-expanded="false" aria-label="Категорії магазину">
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5l5 6 5-6"/></svg>
          </button>
          <div class="nav-drop-menu" id="navShopMenu" data-nav-drop-menu hidden>
            <a class="nav-drop-all" href="<?= e(url('/shop')) ?>">Усі товари</a>
            <?php foreach ($navCats as $c): ?>
              <a href="<?= e(shop_url($c['slug'])) ?>"><?= e($c['name']) ?></a>
              <?php foreach ($c['children'] ?? [] as $k): ?>
                <a class="nav-drop-sub" href="<?= e(shop_url($k['slug'])) ?>"><?= e($k['name']) ?></a>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </span>
      <?php else: ?>
        <a href="<?= e(url('/shop')) ?>">Магазин</a>
      <?php endif; ?>
      <a href="<?= e(url('/diploma')) ?>">Диплом</a>
      <a href="<?= e(url('/social')) ?>">Соцмережі</a>
      <a href="<?= e(url('/partners')) ?>">Партнери</a>
      <a href="<?= e(url('/stores')) ?>">Де нас знайти</a>
    </div>
    <?php /* Пошук у шапці, а не всередині згорнутої панелі «Фільтри».
             Покупець, який знає, що йому треба, шукає словом — і до цього
             моменту йому доводилось спершу зайти в каталог, потім розгорнути
             фільтри й аж там знайти поле. Форма звичайна, GET: працює без JS
             і веде в той самий каталог, який уже вміє шукати. */ ?>
    <form class="nav-search" method="get" action="<?= e(url('/shop')) ?>" role="search">
      <label class="sr-only" for="navSearch">Пошук товарів</label>
      <input type="search" id="navSearch" name="q" placeholder="Що шукаєте?"
             value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
      <button type="submit" aria-label="Знайти">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="7" cy="7" r="5"/><path d="M11 11l4 4"/>
        </svg>
      </button>
    </form>
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
        <?php /* Пропозиції ціни. Пункт зʼявляється лише тоді, коли розмова
                 справді триває: у більшості людей її немає ніколи, і постійне
                 посилання обіцяло б розділ, у якому порожньо. Зате поки хід за
                 покупцем, воно потрібне на кожній сторінці — відповідь
                 продавця не має чекати, доки людина згадає, де її шукати. */ ?>
        <?php if (($myOffers = Offers::myTurnCount(Auth::id())) > 0): ?>
          <a href="<?= e(url('/bargain')) ?>">Мої пропозиції · <?= (int)$myOffers ?></a>
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
    <?php /* Пошук першим рядком меню: у вузькій шапці для нього немає місця,
             а потреба в ньому на телефоні не менша, ніж на компʼютері. */ ?>
    <form class="nav-search" method="get" action="<?= e(url('/shop')) ?>" role="search">
      <label class="sr-only" for="navSearchMobile">Пошук товарів</label>
      <input type="search" id="navSearchMobile" name="q" placeholder="Що шукаєте?"
             value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
      <button type="submit" aria-label="Знайти">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="7" cy="7" r="5"/><path d="M11 11l4 4"/>
        </svg>
      </button>
    </form>
    <a href="<?= e(url('/about')) ?>">Про мене</a>
    <a href="<?= e(url('/courses')) ?>">Курси</a>
    <?php /* Той самий вибір, що й у шапці, але згорнутий: у мобільному меню
             сім пунктів сайту, і розгорнутий список категорій відсунув би
             половину з них за край екрана. */ ?>
    <?php if ($navCats): ?>
      <span class="m-drop">
        <a href="<?= e(url('/shop')) ?>">Магазин</a>
        <button type="button" class="nav-drop-btn" data-nav-drop-btn
                aria-controls="navShopMenuMobile" aria-expanded="false" aria-label="Категорії магазину">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5l5 6 5-6"/></svg>
        </button>
      </span>
      <div class="m-drop-menu" id="navShopMenuMobile" data-nav-drop-menu hidden>
        <a class="nav-drop-all" href="<?= e(url('/shop')) ?>">Усі товари</a>
        <?php foreach ($navCats as $c): ?>
          <a href="<?= e(shop_url($c['slug'])) ?>"><?= e($c['name']) ?></a>
          <?php foreach ($c['children'] ?? [] as $k): ?>
            <a class="nav-drop-sub" href="<?= e(shop_url($k['slug'])) ?>"><?= e($k['name']) ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <a href="<?= e(url('/shop')) ?>">Магазин</a>
    <?php endif; ?>
    <a href="<?= e(url('/diploma')) ?>">Диплом</a>
    <a href="<?= e(url('/social')) ?>">Соцмережі</a>
    <a href="<?= e(url('/partners')) ?>">Партнери</a>
    <a href="<?= e(url('/stores')) ?>">Де нас знайти</a>
    <?php if ($auth_user && Auth::isStaff()): ?>
      <a href="<?= e(url('/admin')) ?>"><?= Auth::isAdmin() ? 'Адмінпанель' : 'Кабінет продавця' ?></a>
    <?php elseif ($auth_user): ?>
      <a href="<?= e(url('/orders')) ?>">Мої замовлення</a>
    <?php endif; ?>
    <?php if ($auth_user && !empty($myOffers)): ?>
      <a href="<?= e(url('/bargain')) ?>">Мої пропозиції · <?= (int)$myOffers ?></a>
    <?php endif; ?>
  </div>
</header>
