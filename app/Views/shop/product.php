<section class="section" style="padding-top:40px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/shop' . ($cat ? '?cat=' . $cat['slug'] : ''))) ?>">← <?= e($cat['name'] ?? 'Магазин') ?></a></p>
    <div class="product-page">
      <div class="product-gallery">
        <?php $mainPhoto = $images[0]['path']; ?>
        <img id="mainPhoto" src="<?= e(asset($mainPhoto)) ?>" alt="<?= e($p['name']) ?>">
        <?php if (count($images) > 1): ?>
          <div class="thumbs" id="productThumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="thumb <?= $i === 0 ? 'active' : '' ?>" data-full="<?= e(asset($img['path'])) ?>"
                      aria-label="Фото <?= $i + 1 ?> з <?= count($images) ?>">
                <img src="<?= e(asset(Images::displayThumb($img['path']))) ?>" alt="<?= e($p['name']) ?> — фото <?= $i + 1 ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <div class="kicker"><?= e($cat['name'] ?? '') ?></div>
        <h1 style="font-size:40px"><?= e($p['name']) ?></h1>
        <p class="lead" style="margin:16px 0 20px"><?= e($p['short_desc'] ?? '') ?></p>

        <?php
        // Стан кнопки до першого кліку по варіанту. Далі його переставляє JS
        // разом із ціною й наявністю — див. updateAddButton() в app.js.
        $stockNow = 0; foreach ($availability as $av) $stockNow += (int)$av['qty'];
        $outOfStock = $stockNow <= 0 && !$p['made_to_order'];
        ?>
        <form method="post" action="<?= e(url('/cart/add')) ?>" class="add-cart-form" data-product-name="<?= e($p['name']) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="back" value="/product/<?= e($p['slug']) ?>">

          <?php if ($variants): ?>
            <div id="variantPicker" data-first="<?= (int)$variants[0]['id'] ?>">
              <?php if ($variant_axes): ?>
                <?php foreach ($variant_axes as $ax): ?>
                  <label><?= e($ax['name']) ?></label>
                  <div class="variants" data-axis="<?= (int)$ax['id'] ?>">
                    <?php foreach ($ax['values'] as $val): ?>
                      <button type="button" class="chip opt" data-axis="<?= (int)$ax['id'] ?>" data-value="<?= e($val['value']) ?>">
                        <?php if (!empty($val['color'])): ?><i class="swatch" style="background:<?= e($val['color']) ?>"></i><?php endif; ?>
                        <?= e($val['value']) ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <label>Варіант</label>
                <div class="variants">
                  <?php foreach ($variants as $i => $v): ?>
                    <button type="button" class="chip opt-plain <?= $i === 0 ? 'active' : '' ?>" data-variant="<?= (int)$v['id'] ?>">
                      <?= e($v['name']) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <input type="hidden" name="variant_id" id="variantId" value="<?= (int)$variants[0]['id'] ?>">
              <p class="dim" id="variantNote" style="margin:10px 0 0"></p>
            </div>
          <?php endif; ?>

          <div style="display:flex;align-items:center;gap:18px;margin:22px 0">
            <span class="price" style="font-size:30px">
              <s id="priceOld" <?= $old_price === null ? 'hidden' : '' ?>><?= $old_price !== null ? e(price_fmt($old_price)) : '' ?></s>
              <span id="priceNow"><?= e(price_label($price, (bool)$p['made_to_order'])) ?></span>
            </span>
            <div class="qty-box">
              <button type="button" onclick="var i=this.parentNode.querySelector('input');i.value=Math.max(1,+i.value-1)">−</button>
              <input type="number" name="qty" value="1" min="1"
                     <?= $p['made_to_order'] || $stockNow <= 0 ? '' : 'max="' . $stockNow . '"' ?>>
              <button type="button" onclick="var i=this.parentNode.querySelector('input'),m=+i.max||Infinity;i.value=Math.min(m,+i.value+1)">+</button>
            </div>
            <button class="btn btn-gold" type="submit" id="addToCart" <?= $outOfStock ? 'disabled' : '' ?>>
              <?= $outOfStock ? 'Немає в наявності' : 'До кошика' ?></button>
          </div>
        </form>

        <?php $lowStock = ($p['low_stock_threshold'] ?? null) !== null && $p['low_stock_threshold'] !== '' ? (int)$p['low_stock_threshold'] : null; ?>
        <div class="availability" id="availability">
          <?php if ($variants): ?><p class="dim" style="margin:0 0 8px">Наявність показана для обраного варіанта</p><?php endif; ?>
          <?php $anyStock = false; foreach ($availability as $av): $sid = (int)$av['store']['id']; $anyStock = $anyStock || $av['qty'] > 0; ?>
            <span class="<?= $av['qty'] > 0 ? 'yes' : 'no' ?>" data-store="<?= $sid ?>"
                  data-stock="<?= e(json_encode($av['by_variant'], JSON_UNESCAPED_UNICODE)) ?>"
                  data-label="<?= e($av['store']['name'] . ($av['store']['city'] ? ' (' . $av['store']['city'] . ')' : '')) ?>">
              <span class="av-text"><?= e($av['store']['name'] . ($av['store']['city'] ? ' (' . $av['store']['city'] . ')' : '')) ?>:
              <?= $av['qty'] > 0 ? ($lowStock !== null && $av['qty'] <= $lowStock ? 'закінчується' : 'в наявності') : 'немає' ?>
              <?php if ($av['price'] !== null && $av['price'] != $price): ?> · ціна тут: <?= e(price_fmt($av['price'])) ?><?php endif; ?></span>
            </span>
          <?php endforeach; ?>
          <?php if ($p['made_to_order']): ?>
            <span class="yes" id="madeToOrder" style="color:var(--gold)<?= $anyStock ? ';display:none' : '' ?>"><?= e(Catalog::madeToOrderNote($p)) ?></span>
          <?php endif; ?>
        </div>

        <?php /* Немає ніде — єдине місце, де людині нема що робити з цією
                 сторінкою. Даємо їй причину не йти назовсім. */ ?>
        <div id="watchBox" style="margin-top:14px<?= $anyStock ? ';display:none' : '' ?>">
          <?php if ($watching): ?>
            <p class="dim" id="watchDone" style="margin:0">🔔 Ви в черзі — напишемо, щойно зʼявиться.
              Куди саме писати — <a href="<?= e(url('/profile')) ?>">у кабінеті</a>.</p>
          <?php else: ?>
            <form method="post" action="<?= e(url('/stock/watch')) ?>" style="margin:0">
              <?= Csrf::field() ?>
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="variant_id" id="watchVariant" value="<?= $variants ? (int)$variants[0]['id'] : '' ?>">
              <input type="hidden" name="back" value="<?= e(request_path()) ?>">
              <button class="btn btn-line btn-sm" type="submit">🔔 Повідомити, коли зʼявиться</button>
            </form>
          <?php endif; ?>
        </div>

        <?php $prodBrands = Catalog::brandsOf($p); ?>
        <?php if ($attrs || $prodBrands): ?>
          <h2 style="font-size:22px;margin-top:34px">Характеристики</h2>
          <table class="specs">
            <?php /* Бренд першим рядком: «чий це товар» — питання, яке виникає
                     раніше за вагу й обʼєм, надто коли позиція не наша.
                     Посиланням — щоб з нього можна було піти до решти товарів
                     цього виробника, як і з будь-якої іншої характеристики. */ ?>
            <?php if ($prodBrands): ?>
              <tr>
                <td><?= count($prodBrands) > 1 ? 'Бренди' : 'Бренд' ?></td>
                <td><?php foreach ($prodBrands as $i => $b): ?><?= $i ? ', ' : '' ?><a
                      class="brand-link" href="<?= e(url('/shop?brand=' . $b['slug'])) ?>"
                      title="Показати всі товари цього бренду"><?php
                        if (!empty($b['logo'])): ?><img src="<?= e(asset(Images::displayThumb($b['logo']))) ?>"
                             alt="" loading="lazy"><?php endif; ?><?= e($b['name']) ?></a><?php endforeach; ?></td>
              </tr>
            <?php endif; ?>
            <?php foreach ($attrs as $a): ?>
              <tr>
                <td><?= e($a['name']) ?><?= !empty($a['unit']) ? ', ' . e($a['unit']) : '' ?></td>
                <td>
                  <?php if (!empty($a['color'])): ?><i class="swatch" style="background:<?= e($a['color']) ?>"></i> <?php endif; ?>
                  <?php if (!empty($a['filterable']) && !empty($a['attr_slug'])): ?>
                    <a href="<?= e(url('/shop?' . http_build_query([
                        'cat' => $cat['slug'] ?? null,
                        'attr' => [$a['attr_slug'] => [$a['value']]],
                      ]))) ?>" title="Показати всі товари з таким значенням"><?= e($a['value']) ?></a>
                  <?php else: ?><?= e($a['value']) ?><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>

        <?php if (!empty($p['description']) && $p['description'] !== ($p['short_desc'] ?? '')): ?>
          <h2 style="font-size:22px;margin-top:30px">Опис</h2>
          <p class="muted" style="margin-top:10px;white-space:pre-line"><?= e($p['description']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (count($images) > 1): ?>
      <script>
        (function () {
          var box = document.getElementById('productThumbs'), main = document.getElementById('mainPhoto');
          box.addEventListener('click', function (e) {
            var t = e.target.closest('.thumb');
            if (!t) return;
            main.src = t.dataset.full;
            box.querySelectorAll('.thumb').forEach(function (b) { b.classList.toggle('active', b === t); });
          });
        })();
      </script>
    <?php endif; ?>

    <?php if ($variants): ?>
      <script>
        window.BOFU_VARIANTS = <?= json_js($variant_data) ?>;
        window.BOFU_MADE_TO_ORDER = <?= $p['made_to_order'] ? 'true' : 'false' ?>;
        window.BOFU_MTO_NOTE = <?= json_js(Catalog::madeToOrderShort($p)) ?>;
        window.BOFU_LOW_STOCK_THRESHOLD = <?= $lowStock !== null ? $lowStock : 'null' ?>;
      </script>
    <?php endif; ?>

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
