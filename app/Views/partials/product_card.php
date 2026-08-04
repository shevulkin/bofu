<?php /** @var array $prod */
$cardVariants = Catalog::variants((int)$prod['id']);
$cardVariant = $cardVariants[0] ?? null;
// Є з чого вибирати — вибирає покупець, а не картка. Кладемо в кошик прямо
// звідси лише те, де вибору немає: без варіантів або з єдиним варіантом.
$needsChoice = count($cardVariants) > 1;
// Скільки з картки взагалі можна покласти: null — без обмежень («під замовлення»)
$cardLimit = $needsChoice ? null
    : Cart::limit((int)$prod['id'], $cardVariant ? (int)$cardVariant['id'] : null);
$soldOut = $cardLimit !== null && $cardLimit <= 0;
[$pr, $old] = Catalog::price($prod, $cardVariant);
?>
<div class="card">
  <a class="card-img" href="<?= e(url('/product/' . $prod['slug'])) ?>">
    <?php $photo = Catalog::photo($prod); if ($photo): ?>
      <img src="<?= e(asset(Images::displayThumb($photo))) ?>" alt="<?= e($prod['name']) ?>" loading="lazy">
    <?php else: ?><span class="ph">🍯</span><?php endif; ?>
    <?php if ($old !== null): ?><span class="badge red">Акція</span>
    <?php elseif ($prod['featured']): ?><span class="badge">Хіт</span><?php endif; ?>
  </a>
  <div class="card-body">
    <div class="card-title"><a href="<?= e(url('/product/' . $prod['slug'])) ?>"><?= e($prod['name']) ?></a></div>
    <div class="card-desc"><?= e($prod['short_desc'] ?? '') ?></div>
    <div class="card-foot">
      <span class="price"><?php if ($old !== null): ?><s><?= e(price_fmt($old)) ?></s><?php endif; ?><?= e(price_label($pr, (bool)$prod['made_to_order'])) ?></span>
      <?php if ($needsChoice): ?>
        <a class="btn btn-gold btn-sm" href="<?= e(url('/product/' . $prod['slug'])) ?>">Обрати</a>
      <?php elseif ($soldOut): ?>
        <button class="btn btn-gold btn-sm" type="button" disabled>Немає в наявності</button>
      <?php else: ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" class="add-cart-form" data-product-name="<?= e($prod['name']) ?>"><?= Csrf::field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
          <?php if ($cardVariant): ?><input type="hidden" name="variant_id" value="<?= (int)$cardVariant['id'] ?>"><?php endif; ?>
          <input type="hidden" name="back" value="<?= e(request_path()) ?>">
          <button class="btn btn-gold btn-sm" type="submit">До кошика</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
