<?php /** @var array $prod */ [$pr, $old] = Catalog::price($prod); ?>
<div class="card">
  <a class="card-img" href="<?= e(url('/product/' . $prod['slug'])) ?>">
    <?php $photo = Catalog::photo($prod); if ($photo): ?>
      <img src="<?= e(asset($photo)) ?>" alt="<?= e($prod['name']) ?>" loading="lazy">
    <?php else: ?><span class="ph">🍯</span><?php endif; ?>
    <?php if ($old !== null): ?><span class="badge red">Акція</span>
    <?php elseif ($prod['featured']): ?><span class="badge">Хіт</span><?php endif; ?>
  </a>
  <div class="card-body">
    <div class="card-title"><a href="<?= e(url('/product/' . $prod['slug'])) ?>"><?= e($prod['name']) ?></a></div>
    <div class="card-desc"><?= e($prod['short_desc'] ?? '') ?></div>
    <div class="card-foot">
      <span class="price"><?php if ($old !== null): ?><s><?= e(price_fmt($old)) ?></s><?php endif; ?><?= e(price_fmt($pr)) ?></span>
      <form method="post" action="<?= e(url('/cart/add')) ?>"><?= Csrf::field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$prod['id'] ?>">
        <input type="hidden" name="back" value="<?= e(request_path()) ?>">
        <button class="btn btn-gold btn-sm" type="submit">До кошика</button>
      </form>
    </div>
  </div>
</div>
