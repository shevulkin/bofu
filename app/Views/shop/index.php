<section class="section" style="padding-top:48px">
  <div class="container">
    <div class="kicker">Магазин</div>
    <div class="section-head">
      <h2><?= $brand ? e($brand['name']) : e($current_cat['name'] ?? 'Всі товари') ?></h2>
      <div style="display:flex;gap:16px;align-items:center">
        <span class="dim"><?= count($products) ?> позицій</span>
        <button type="button" class="btn btn-line btn-sm" id="filtersToggle">Фільтри <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px;vertical-align:-1px"><path d="M3 5l5 6 5-6"/></svg></button>
        <div class="view-switch" id="viewSwitch" title="Вигляд каталогу">
          <button type="button" data-view="v3" title="3 в ряд" aria-label="3 в ряд"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="0" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="0" width="4.4" height="4.4" rx="1"/><rect x="0" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="0" y="11.6" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="11.6" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="11.6" width="4.4" height="4.4" rx="1"/></svg></button>
          <button type="button" data-view="v4" title="Компактно" aria-label="Компактно"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="0" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="0" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="0" width="3.1" height="3.1" rx=".8"/><rect x="0" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="0" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="0" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="12.9" width="3.1" height="3.1" rx=".8"/></svg></button>
          <button type="button" data-view="vlist" title="Списком" aria-label="Списком"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="1" width="4" height="4" rx="1"/><rect x="6" y="2" width="10" height="2" rx="1"/><rect x="0" y="6" width="4" height="4" rx="1"/><rect x="6" y="7" width="10" height="2" rx="1"/><rect x="0" y="11" width="4" height="4" rx="1"/><rect x="6" y="12" width="10" height="2" rx="1"/></svg></button>
        </div>
      </div>
    </div>

    <?php /* прийшли з картки товару по бренду — це фактично сторінка бренду:
             лого й опис тут доречніші за будь-де, бо покупець уперше бачить
             назву й питає «хто це?» */ ?>
    <?php if ($brand): $brandCard = !empty($brand['logo']) || !empty($brand['description']); ?>
      <?php /* рамка лише коли в ній щось є: порожня панель із одним рядком
               усередині читається як недовантажений блок, а не як задум */ ?>
      <div class="<?= $brandCard ? 'brand-head' : '' ?>" style="<?= $brandCard ? '' : 'margin:0 0 18px' ?>">
        <?php if (!empty($brand['logo'])): ?>
          <img class="brand-logo" src="<?= e(asset($brand['logo'])) ?>" alt="<?= e($brand['name']) ?>">
        <?php endif; ?>
        <div>
          <?php if (!empty($brand['description'])): ?>
            <p class="lead" style="margin:0 0 8px"><?= e($brand['description']) ?></p>
          <?php endif; ?>
          <p class="dim" style="margin:0">Товари бренду <b><?= e($brand['name']) ?></b>
            · <a href="<?= e(url('/shop')) ?>">показати всі</a></p>
        </div>
      </div>
    <?php endif; ?>

    <?php /* Панель розділів. Підрозділи не лежать поруч із розділами одним
             довгим рядом, а ховаються за стрілкою: інакше «Мед» і чотири його
             сорти займають пів екрана й вибирати доводиться з тридцяти чипів.

             Стрілка нічого не завантажує — вона розгортає підрозділи на місці,
             і людина переходить одразу в потрібний, минаючи сотню позицій
             усього розділу.

             Без JS стрілка не спрацює, тож підрозділи мусять бути досяжні й
             так: розділ відкривається звичайним посиланням, а на його сторінці
             ряд підрозділів уже розгорнутий. Пошуковик із тієї ж причини бачить
             посилання на всі підрозділи — вони в розмітці, лише сховані. */ ?>
    <?php
      $curId = (int)($current_cat['id'] ?? 0);
      // відкрита гілка: розділ обраного підрозділу або сам обраний розділ
      $openId = (int)($parent_cat['id'] ?? 0) ?: $curId;
    ?>
    <nav class="cat-nav" aria-label="Розділи каталогу">
      <div class="cat-chips">
        <a class="chip <?= !$current_cat ? 'active' : '' ?>" href="<?= e(url('/shop')) ?>">Усі</a>
        <?php foreach ($cat_tree as $c): $cid = (int)$c['id']; $kids = $c['children'] ?? []; ?>
          <?php if (!$kids): ?>
            <a class="chip <?= $curId === $cid ? 'active' : '' ?>" href="<?= e(url('/shop?cat=' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
          <?php else: $open = $openId === $cid; ?>
            <span class="chip-group<?= $open ? ' is-open' : '' ?>">
              <?php /* сам розділ лишається клікабельним: у ньому є й власні
                       товари, і ті, що в підрозділах */ ?>
              <a class="chip <?= $curId === $cid ? 'active' : ($open ? 'in-branch' : '') ?>"
                 href="<?= e(url('/shop?cat=' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
              <button type="button" class="chip-more" data-cat-toggle="<?= $cid ?>"
                      aria-controls="catsub-<?= $cid ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>"
                      aria-label="Підрозділи: <?= e($c['name']) ?>" title="Підрозділи">
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5l5 6 5-6"/></svg>
              </button>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php foreach ($cat_tree as $c): $cid = (int)$c['id']; $kids = $c['children'] ?? []; if (!$kids) continue; ?>
        <div class="cat-subs" id="catsub-<?= $cid ?>" data-cat-subs="<?= $cid ?>" <?= $openId === $cid ? '' : 'hidden' ?>>
          <a class="chip chip-sub <?= $curId === $cid ? 'active' : '' ?>"
             href="<?= e(url('/shop?cat=' . $c['slug'])) ?>">Усе в розділі</a>
          <?php foreach ($kids as $k): ?>
            <a class="chip chip-sub <?= $curId === (int)$k['id'] ? 'active' : '' ?>"
               href="<?= e(url('/shop?cat=' . $k['slug'])) ?>"><?= e($k['name']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>

    <?php $hasActiveFilters = $filters['q'] !== '' || $filters['min'] !== '' || $filters['max'] !== '' || !empty($filters['store_id']) || $filters['sort'] !== '' || array_filter($filters['attr']) || $filters['brand']; ?>
    <form class="filters" id="filtersPanel" method="get" action="<?= e(url('/shop')) ?>" style="<?= $hasActiveFilters ? '' : 'display:none' ?>">
      <?php if ($current_cat): ?><input type="hidden" name="cat" value="<?= e($current_cat['slug']) ?>"><?php endif; ?>
      <div class="filters-row">
        <div><label>Пошук</label><input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Назва, опис, артикул…"></div>
        <div><label>Ціна від</label><input type="number" name="min" value="<?= e($filters['min']) ?>" min="0" step="1"></div>
        <div><label>Ціна до</label><input type="number" name="max" value="<?= e($filters['max']) ?>" min="0" step="1"></div>
        <div><label>Наявність</label>
          <select name="store">
            <option value="">Будь-де</option>
            <?php foreach ($stores as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $filters['store_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['name'] . ($s['city'] ? ' (' . $s['city'] . ')' : '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Сортування</label>
          <select name="sort">
            <option value="">За популярністю</option>
            <option value="price_asc" <?= $filters['sort'] === 'price_asc' ? 'selected' : '' ?>>Спочатку дешевші</option>
            <option value="price_desc" <?= $filters['sort'] === 'price_desc' ? 'selected' : '' ?>>Спочатку дорожчі</option>
            <option value="new" <?= $filters['sort'] === 'new' ? 'selected' : '' ?>>Новинки</option>
          </select>
        </div>
        <button class="btn btn-gold btn-sm" type="submit">Застосувати</button>
      </div>
      <?php if ($attr_options || $brand_options): ?>
        <div class="attr-filters">
          <?php /* бренд у тому ж ряду, що й характеристики: для покупця це така
                   сама ознака товару, і шукати її окремо він не стане */ ?>
          <?php if ($brand_options): $chosenBrands = array_map('strval', (array)$filters['brand']); ?>
            <div class="attr-filter">
              <label>Бренд</label>
              <div class="attr-values">
                <?php foreach ($brand_options as $b): ?>
                  <label class="checkbox">
                    <input type="checkbox" name="brand[]" value="<?= e($b['slug']) ?>"
                           <?= in_array((string)$b['slug'], $chosenBrands, true) ? 'checked' : '' ?>>
                    <?= e($b['name']) ?> <span class="dim">(<?= (int)$b['cnt'] ?>)</span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php foreach ($attr_options as $slug => $a): $chosen = (array)($filters['attr'][$slug] ?? []); ?>
            <div class="attr-filter">
              <label><?= e($a['name']) ?><?= $a['unit'] ? ', ' . e($a['unit']) : '' ?></label>
              <div class="attr-values">
                <?php foreach ($a['values'] as $v): ?>
                  <label class="checkbox">
                    <input type="checkbox" name="attr[<?= e($slug) ?>][]" value="<?= e($v['value']) ?>"
                           <?= in_array((string)$v['value'], array_map('strval', $chosen), true) ? 'checked' : '' ?>>
                    <?php if (!empty($v['color'])): ?><i class="swatch" style="background:<?= e($v['color']) ?>"></i><?php endif; ?>
                    <?= e($v['value']) ?> <span class="dim">(<?= (int)$v['count'] ?>)</span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:12px;margin-top:14px;flex-wrap:wrap">
          <button class="btn btn-gold btn-sm" type="submit">Застосувати фільтри</button>
          <?php if ($hasActiveFilters): ?>
            <a class="btn btn-line btn-sm" href="<?= e(url('/shop' . ($current_cat ? '?cat=' . $current_cat['slug'] : ''))) ?>">Скинути</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>

    <?php if (!$products): ?>
      <p class="muted" style="padding:40px 0">Нічого не знайдено. Спробуйте змінити фільтри.</p>
    <?php else: ?>
      <div class="pgrid v3" id="productGrid">
        <?php foreach ($products as $prod): ?>
          <?= View::partial('partials/product_card', ['prod' => $prod]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($other_products): ?>
      <div class="kicker" style="margin-top:64px">Популярне</div>
      <div class="section-head"><h2 style="font-size:26px">Вас може зацікавити</h2></div>
      <div class="grid grid-4">
        <?php foreach ($other_products as $prod): ?>
          <?= View::partial('partials/product_card', ['prod' => $prod]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
/* Стрілка розділу: розгортає його підрозділи на місці, нічого не завантажуючи.
   Одночасно відкритий лише один ряд — два ряди підрозділів під панеллю
   відсувають товари вниз рівно тоді, коли покупець їх шукає. */
(function(){
  var chips = document.querySelectorAll('[data-cat-toggle]');
  if (!chips.length) return;
  Array.prototype.forEach.call(chips, function(btn){
    btn.addEventListener('click', function(){
      var id = btn.getAttribute('data-cat-toggle');
      var row = document.querySelector('[data-cat-subs="' + id + '"]');
      if (!row) return;
      var open = row.hasAttribute('hidden');
      Array.prototype.forEach.call(document.querySelectorAll('[data-cat-subs]'), function(r){
        r.setAttribute('hidden', '');
      });
      Array.prototype.forEach.call(chips, function(b){
        b.setAttribute('aria-expanded', 'false');
        if (b.parentNode) b.parentNode.classList.remove('is-open');
      });
      if (open) {
        row.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
        if (btn.parentNode) btn.parentNode.classList.add('is-open');
      }
    });
  });
})();
(function(){
  var toggle = document.getElementById('filtersToggle');
  var panel = document.getElementById('filtersPanel');
  if (toggle && panel) {
    <?php if ($hasActiveFilters): ?>toggle.classList.add('btn-gold'); toggle.classList.remove('btn-line');<?php endif; ?>
    toggle.addEventListener('click', function(){
      var open = panel.style.display !== 'none';
      panel.style.display = open ? 'none' : '';
    });
  }
})();
(function(){
  var grid = document.getElementById('productGrid');
  var sw = document.getElementById('viewSwitch');
  if (!grid || !sw) return;
  var KEY = 'bofu_shop_view';
  function apply(v){
    grid.classList.remove('v3','v4','vlist');
    grid.classList.add(v);
    sw.querySelectorAll('button').forEach(function(b){ b.classList.toggle('active', b.dataset.view === v); });
    try { localStorage.setItem(KEY, v); } catch(e){}
  }
  var saved = 'v3';
  try { saved = localStorage.getItem(KEY) || 'v3'; } catch(e){}
  if (['v3','v4','vlist'].indexOf(saved) < 0) saved = 'v3';
  apply(saved);
  sw.querySelectorAll('button').forEach(function(b){
    b.addEventListener('click', function(){ apply(b.dataset.view); });
  });
})();
</script>