<div class="admin-head"><h1 class="h-serif">Контент сайту</h1></div>
<p class="dim" style="margin-bottom:18px">Тут редагуються всі тексти й фото сайту. Зміни з'являються одразу після збереження.</p>

<form method="post" action="<?= e(url('/admin/content')) ?>">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="save">
  <?php foreach ($groups as $groupName => $keys): ?>
    <div class="admin-card">
      <h2 class="h-serif"><?= e($groupName) ?></h2>
      <?php foreach ($keys as $key): $b = $blocks[$key] ?? ['title' => '', 'body' => '']; ?>
        <div class="form-grid">
          <div class="field" data-help-title="<?= e($field_labels[$key] ?? $key) ?> — заголовок"
               data-help="Верхній, помітний рядок цього блоку на сайті — те, що людина читає першим і найчастіше єдине, що встигає прочитати.

Тримайте коротко: довгий заголовок ламає верстку на телефоні й перестає працювати як заголовок.

Зміни зʼявляються на сайті одразу після збереження, без жодного підтвердження.">
            <label><?= e($field_labels[$key] ?? ($key . ' — заголовок/значення')) ?></label>
            <input type="text" name="block[<?= e($key) ?>][title]" value="<?= e($b['title'] ?? '') ?>">
          </div>
          <div class="field" data-help-title="<?= e($field_labels[$key] ?? $key) ?> — текст"
               data-help="Абзац під заголовком цього блоку.

Переноси рядків зберігаються, тож можна писати кількома абзацами. Розмітка й посилання не підтримуються — це звичайний текст.

Порожнє поле означає, що на сайті буде лише заголовок, без пояснення під ним.">
            <label>текст</label>
            <textarea name="block[<?= e($key) ?>][body]" rows="2"><?= e($b['body'] ?? '') ?></textarea>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="admin-card">
    <h2 class="h-serif" data-help-title="Часті запитання (FAQ)"
        data-help="Блок питань і відповідей на сайті.

Пишіть сюди те, про що покупці справді питають по телефону: скільки йде доставка, як зберігати мед, чи є самовивіз. Кожне питання тут — це дзвінок, якого не буде.

Формулюйте питання словами покупця, а не своїми: «Коли приїде замовлення?» краще за «Терміни виконання».

Щоб додати нове — заповніть пару полів «+ Нове питання» внизу. Щоб прибрати наявне — очистіть його поле питання й збережіть.">
      Часті запитання (FAQ)</h2>
    <div id="faqList">
      <?php foreach ($faq as $qa): ?>
        <div class="form-grid">
          <div class="field"><label>Питання</label><input type="text" name="faq_q[]" value="<?= e($qa[0]) ?>"></div>
          <div class="field"><label>Відповідь</label><textarea name="faq_a[]" rows="2"><?= e($qa[1]) ?></textarea></div>
        </div>
      <?php endforeach; ?>
      <div class="form-grid">
        <div class="field"><label>+ Нове питання</label><input type="text" name="faq_q[]" placeholder="Порожнє = не додавати"></div>
        <div class="field"><label>Відповідь</label><textarea name="faq_a[]" rows="2"></textarea></div>
      </div>
    </div>
  </div>
  <div class="admin-save">
    <button class="btn btn-gold" type="submit">💾 Зберегти всі тексти</button>
    <span class="admin-save-note"></span>
  </div>
</form>

<div class="admin-card" style="margin-top:22px">
  <h2 class="h-serif" data-help-title="Фото блоків"
      data-help="Великі зображення головної сторінки й сторінки «Про мене».

Головний банер — найперше, що бачить відвідувач. Беріть широке горизонтальне фото хорошої якості: воно розтягується на всю ширину екрана, і будь-яка зернистість там помітна.

Фото змінюються окремо від текстів — у цього блоку своя кнопка, і «Зберегти всі тексти» вище на них не впливає.

Самі файли лежать у Медіа-бібліотеці: там видно, де кожне фото використовується, і звідти їх не можна випадково видалити, поки вони стоять тут.">
    Фото блоків (головний банер, «про мене»)</h2>
  <div style="display:flex;gap:26px;flex-wrap:wrap">
    <?php foreach (['hero' => 'Головний банер', 'about_teaser' => 'Фото «Хто я» (головна)', 'about_full' => 'Фото сторінки «Про мене»'] as $key => $label): ?>
      <div style="text-align:center">
        <?php $img = $blocks[$key]['image'] ?? ''; ?>
        <img src="<?= e(asset($img ?: 'img/about-photo.webp')) ?>" style="width:150px;height:150px;object-fit:cover;border-radius:4px;border:1px solid var(--line)">
        <div class="dim" style="margin:6px 0"><?= e($label) ?></div>
        <form method="post" action="<?= e(url('/admin/content')) ?>" id="setImg_<?= $key ?>" style="display:none">
          <?= Csrf::field() ?><input type="hidden" name="_action" value="set_image"><input type="hidden" name="key" value="<?= $key ?>"><input type="hidden" name="media_path" value="">
        </form>
        <button class="btn btn-line btn-xs" type="button" onclick="MediaPicker.open(function(p){var f=document.getElementById('setImg_<?= $key ?>');f.querySelector('[name=media_path]').value=p;f.submit();})">Змінити фото</button>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="admin-card">
  <h2 class="h-serif" data-help-title="Галерея"
      data-help="Стрічка фотографій на сайті — пасіка, процес, продукція, люди.

Це те, що переконує краще за будь-який текст: покупець бачить, що за медом стоїть справжнє господарство.

Кожному фото можна дати підпис — він показується покупцю. Пишіть по суті: «Медозбір 2026», «Наші вулики навесні».

Не заливайте сюди все підряд: десять хороших знімків працюють краще за сотню випадкових.">
    Галерея</h2>
  <div class="img-grid">
    <?php foreach ($gallery as $i => $g): ?>
      <div class="img-cell">
        <img src="<?= e(asset($g[1])) ?>" alt="">
        <span class="dim"><?= e($g[0]) ?></span>
        <form method="post" action="<?= e(url('/admin/content')) ?>"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="gallery_del"><input type="hidden" name="index" value="<?= $i ?>">
          <button class="btn btn-danger btn-xs" style="margin-top:5px" onclick="return confirm('Прибрати фото?')">Прибрати</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:18px;display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    <form method="post" action="<?= e(url('/admin/content')) ?>" id="galAddForm" style="display:flex;gap:12px;align-items:end">
      <?= Csrf::field() ?><input type="hidden" name="_action" value="gallery_pick"><input type="hidden" name="media_path" value="">
      <div><label>Підпис</label><input type="text" name="title" placeholder="Напр. Медозбір 2026"></div>
      <button class="btn btn-line btn-sm" type="button" onclick="var f=document.getElementById('galAddForm');MediaPicker.open(function(p){f.querySelector('[name=media_path]').value=p;f.submit();})">+ Додати фото в галерею</button>
    </form>
    <span class="dim">Вибір із сайту або завантаження з ПК; фото стискається автоматично</span>
  </div>
<?= View::partial('partials/media_picker') ?>
</div>
