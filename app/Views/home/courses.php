<section class="section" style="padding-top:48px">
  <div class="container narrow">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Навчання</div>
    <h1 style="font-size:44px">Курси бджільництва</h1>
    <div class="card" style="margin-top:36px">
      <div class="card-body" style="padding:32px">
        <div class="card-title" style="font-size:28px"<?= edit_mark('course_1', 'title') ?>><?= e(Content::title('course_1')) ?></div>
        <div class="card-desc" style="font-size:15.5px;margin-top:8px"<?= edit_mark('course_1', 'body') ?>><?= e(Content::get('course_1')) ?></div>
        <div class="card-foot" style="margin-top:22px">
          <span class="price" style="font-size:20px">За запитом</span>
          <a class="btn btn-gold" href="<?= e(Content::title('course_1_link', '#')) ?>" target="_blank" rel="noopener"<?= edit_mark('course_1_link') ?>>Записатись</a>
        </div>
      </div>
    </div>
    <div style="margin-top:56px">
      <h2 style="font-size:26px">Часті запитання</h2>
      <div<?= edit_mark('faq') ?>>
        <?php foreach (json_decode(Content::get('faq', 'body', '[]'), true) ?: [] as $qa): ?>
          <details class="faq-item"><summary><?= e($qa[0]) ?></summary><p><?= e($qa[1]) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
