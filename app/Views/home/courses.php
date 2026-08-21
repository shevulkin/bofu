<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Навчання</div>
    <h1 style="font-size:44px">Курси бджільництва</h1>

    <?php
    /*
     * Опис під заголовком лишається текстовим блоком: це слова про навчання
     * загалом, а не про конкретний курс, і правити їх зручніше в режимі
     * редагування сайту, а не в картці товару.
     */
    ?>
    <?php if (trim((string)Content::get('course_1')) !== ''): ?>
      <p class="lead" style="margin-top:18px"<?= edit_mark('course_1', 'body') ?>><?= e(Content::get('course_1')) ?></p>
    <?php endif; ?>

    <?php if (!$courses): ?>
      <?php /* Порожньо — кажемо про це чесно й лишаємо спосіб звʼязатись.
               Мовчазна порожня сторінка читається як поламаний сайт. */ ?>
      <div class="card" style="margin-top:36px"><div class="card-body" style="padding:32px">
        <div class="card-title" style="font-size:22px">Набір іще не відкрито</div>
        <div class="card-desc" style="margin-top:8px">Щойно оголосимо новий потік — курс зʼявиться тут.
          Написати нам можна <a href="<?= e(url('/social')) ?>">у месенджерах</a>.</div>
      </div></div>
    <?php else: ?>
      <?php /* Своя картка, а не товарна: курс читають, а не роздивляються,
               і фото на пів екрана відсуває саме те, заради чого прийшли. */ ?>
      <div class="course-list">
        <?php foreach ($courses as $prod): ?>
          <?= View::partial('partials/course_card', ['prod' => $prod]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($faq): ?>
    <div style="margin-top:56px">
      <h2 style="font-size:26px">Часті запитання про навчання</h2>
      <div<?= edit_mark('faq_course') ?>>
        <?php foreach ($faq as $qa): ?>
          <details class="faq-item"><summary><?= e($qa[0]) ?></summary><p><?= e($qa[1]) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
