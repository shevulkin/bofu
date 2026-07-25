<section class="section" style="padding-top:48px">
  <div class="container">
    <div class="kicker">Магазин</div>
    <div class="section-head">
      <h2><?= e($current_cat['name'] ?? 'Всі товари') ?></h2>
      <div style="display:flex;gap:16px;align-items:center">
        <span class="dim"><?= count($products) ?> позицій</span>
        <div class="view-switch" id="viewSwitch" title="Вигляд каталогу">
          <button type="button" data-view="v3" title="3 в ряд" aria-label="3 в ряд"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="0" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="0" width="4.4" height="4.4" rx="1"/><rect x="0" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="5.8" width="4.4" height="4.4" rx="1"/><rect x="0" y="11.6" width="4.4" height="4.4" rx="1"/><rect x="5.8" y="11.6" width="4.4" height="4.4" rx="1"/><rect x="11.6" y="11.6" width="4.4" height="4.4" rx="1"/></svg></button>
          <button type="button" data-view="v4" title="Компактно" aria-label="Компактно"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="0" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="0" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="0" width="3.1" height="3.1" rx=".8"/><rect x="0" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="4.3" width="3.1" height="3.1" rx=".8"/><rect x="0" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="8.6" width="3.1" height="3.1" rx=".8"/><rect x="0" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="4.3" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="8.6" y="12.9" width="3.1" height="3.1" rx=".8"/><rect x="12.9" y="12.9" width="3.1" height="3.1" rx=".8"/></svg></button>
          <button type="button" data-view="vlist" title="Списком" aria-label="Списком"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="1" width="4" height="4" rx="1"/><rect x="6" y="2" width="10" height="2" rx="1"/><rect x="0" y="6" width="4" height="4" rx="1"/><rect x="6" y="7" width="10" height="2" rx="1"/><rect x="0" y="11" width="4" height="4" rx="1"/><rect x="6" y="12" width="10" height="2" rx="1"/></svg></button>
        </div>
      </div>
    </div>

    <div class="cat-chips">
      <a class="chip <?= !$current_cat ? 'active' : '' ?>" href="<?= e(url('/shop')) ?>">Усі</a>
      <?php foreach ($categories as $c): ?>
        <a class="chip <?= ($current_cat['id'] ?? null) == $c['id'] ? 'active' : '' ?>" href="<?= e(url('/shop?cat=' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <form class="filters" method="get" action="<?= e(url('/shop')) ?>">
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
      <?php if ($attr_options): ?>
        <div class="filters-row" style="grid-template-columns:repeat(4,1fr);margin-top:14px">
          <?php foreach (array_slice($attr_options, 0, 4, true) as $aname => $values): ?>
            <div><label><?= e($aname) ?></label>
              <select name="attr[<?= e($aname) ?>]">
                <option value="">Будь-яке</option>
                <?php foreach ($values as $v): ?>
                  <option value="<?= e($v) ?>" <?= ($filters['attr'][$aname] ?? '') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
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