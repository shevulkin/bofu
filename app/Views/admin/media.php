<div class="admin-head">
  <h1 class="h-serif">Медіа-бібліотека</h1>
  <form method="post" action="<?= e(url('/admin/media')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center">
    <?= Csrf::field() ?><input type="hidden" name="_action" value="upload">
    <input type="file" name="image" accept="image/*" required data-help-title="Вибір файлу"
           data-help="Оберіть зображення на своєму комп'ютері.

Завантажене фото потрапляє у спільну бібліотеку — звідти його можна ставити товарам, банерам і в галерею сайту.

Беріть якісний оригінал: сайт сам зробить зменшені копії для каталогу, а от із маленького файлу різкої великої картинки не вийде.">
    <button class="btn btn-gold btn-sm" type="submit" data-help-title="Завантажити з ПК"
            data-help="Додає обране фото в бібліотеку.

Саме по собі завантаження нічого на сайті не змінює: фото просто лягає в загальний список. Щоб воно зʼявилось у покупця, призначте його товару в його картці або оберіть у налаштуваннях банера чи галереї.">+ Завантажити з ПК</button>
  </form>
</div>
<p class="dim" style="margin-bottom:18px">Усі фото сайту. Хрестик видаляє фото звідусіль. Фото, прив'язані до товарів, банерів чи галереї, видалити не можна — спершу приберіть/замініть їх там (посилання під фото). Вбудовані фото дизайну видалити не можна.</p>
<div class="img-grid media-lib">
  <?php foreach ($items as $it): $uses = $it['usage'] ?? []; ?>
    <div class="img-cell">
      <img src="<?= e(asset($it['thumb'])) ?>" alt="" loading="lazy">
      <?php if (!$it['builtin']): ?>
        <?php if ($uses): ?>
          <span class="badge cell-badge" title="Фото використовується — видалення заблоковано"
                data-help-title="Замок на фото"
                data-help="Фото десь використовується, тому видалити його не можна. Число — у скількох місцях.

Це захист від порожніх картинок на сайті: інакше видалене фото зникло б із картки товару чи банера, і покупець побачив би дірку.

Посилання під фото ведуть саме туди, де воно стоїть. Щоб фото можна було видалити, спершу приберіть або замініть його в кожному з цих місць — тоді замок зникне й зʼявиться хрестик.">🔒 <?= count($uses) ?></span>
        <?php else: ?>
          <form method="post" action="<?= e(url('/admin/media')) ?>" class="cell-action">
            <?= Csrf::field() ?><input type="hidden" name="_action" value="delete"><input type="hidden" name="path" value="<?= e($it['path']) ?>">
            <button class="btn btn-danger btn-xs" style="padding:3px 8px" onclick="return confirm('Видалити фото з сайту?')"
                    data-help-title="Видалити фото"
                    data-help="Стирає файл із сайту назавжди — відновити його не можна, доведеться завантажувати наново.

Хрестик є лише в тих фото, які ніде не використовуються: якщо стоїть замок, спершу приберіть фото з товарів і банерів.

Вбудовані зображення дизайну сайту не видаляються взагалі — у них немає ні хрестика, ні замка.">✕</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
      <span class="dim"><?= (int)$it['width'] ?>×<?= (int)$it['height'] ?> · <?= round($it['bytes']/1024) ?> КБ</span>
      <?php if ($uses): ?>
        <div class="cell-uses">
          <?php foreach ($uses as $u): ?>
            <div><a href="<?= e($u['url']) ?>">→ <?= e($u['label']) ?></a></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
