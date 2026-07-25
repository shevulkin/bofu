<section class="section" style="padding-top:40px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/shop' . ($cat ? '?cat=' . $cat['slug'] : ''))) ?>">← <?= e($cat['name'] ?? 'Магазин') ?></a></p>
    <div class="product-page">
      <div class="product-gallery">
        <?php $mainPhoto = Catalog::photo($p); ?>
        <img id="mainPhoto" src="<?= e(asset($mainPhoto)) ?>" alt="<?= e($p['name']) ?>">
        <?php if (count($images) > 1): ?>
          <div class="thumbs">
            <?php foreach ($images as $img): ?>
              <img src="<?= e(asset(Images::thumbPath($img['path']))) ?>" data-full="<?= e(asset($img['path'])) ?>" alt="" onclick="document.getElementById('mainPhoto').src=this.dataset.full">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <div class="kicker"><?= e($cat['name'] ?? '') ?></div>
        <h1 style="font-size:40px"><?= e($p['name']) ?></h1>
        <p class="lead" style="margin:16px 0 20px"><?= e($p['short_desc'] ?? '') ?></p>

        <form method="post" action="<?= e(url('/cart/add')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="/product/<?= e($p['slug']) ?>">

          <?php if ($variants): ?>
            <label>Варіант</label>
            <div class="variants">
              <?php foreach ($variants as $i => $v): ?>
                <label class="chip" style="display:inline-flex;align-items:center;gap:7px">
                  <input type="radio" name="variant_id" value="<?= (int)$v['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> style="width:auto">
                  <?= e($v['name']) ?><?php if ($v['price'] !== null && $v['price'] !== ''): ?> · <?= e(price_fmt($v['price'])) ?><?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div style="display:flex;align-items:center;gap:18px;margin:22px 0">
            <span class="price" style="font-size:30px">
              <?php if ($old_price !== null): ?><s><?= e(price_fmt($old_price)) ?></s><?php endif; ?>
              <?= e(price_fmt($price)) ?>
            </span>
            <div class="qty-box">
              <button type="button" onclick="var i=this.parentNode.querySelector('input');i.value=Math.max(1,+i.value-1)">−</button>
              <input type="number" name="qty" value="1" min="1">
              <button type="button" onclick="var i=this.parentNode.querySelector('input');i.value=+i.value+1">+</button>
            </div>
            <button class="btn btn-gold" type="submit">До кошика</button>
          </div>
        </form>

        <div class="availability">
          <?php $anyStock = false; foreach ($availability as $av): $anyStock = $anyStock || $av['qty'] > 0; ?>
            <span class="<?= $av['qty'] > 0 ? 'yes' : 'no' ?>">
              <?= e($av['store']['name'] . ($av['store']['city'] ? ' (' . $av['store']['city'] . ')' : '')) ?>:
              <?= $av['qty'] > 0 ? 'в наявності — ' . (int)$av['qty'] . ' шт.' : 'немає' ?>
              <?php if ($av['price'] !== null && $av['price'] != $price): ?> · ціна тут: <?= e(price_fmt($av['price'])) ?><?php endif; ?>
            </span>
          <?php endforeach; ?>
          <?php if (!$anyStock && $p['made_to_order']): ?>
            <span class="yes" style="color:var(--gold)">Виготовимо під замовлення — ми виробник 🍯</span>
          <?php endif; ?>
        </div>

        <?php if ($attrs): ?>
          <h2 style="font-size:22px;margin-top:34px">Характеристики</h2>
          <table class="specs">
            <?php foreach ($attrs as $a): ?>
              <tr><td><?= e($a['name']) ?></td><td><?= e($a['value']) ?></td></tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>

        <?php if (!empty($p['description']) && $p['description'] !== ($p['short_desc'] ?? '')): ?>
          <h2 style="font-size:22px;margin-top:30px">Опис</h2>
          <p class="muted" style="margin-top:10px;white-space:pre-line"><?= e($p['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($related): ?>
      <div class="kicker" style="margin-top:72px">Схожі товари</div>
      <div class="grid grid-4" style="margin-top:20px">
        <?php foreach ($related as $prod): ?>
          <?= View::partial('partials/product_card', ['prod' => $prod]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
