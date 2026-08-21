<section class="section" style="padding-top:48px">
  <div class="container">
    <p style="margin-bottom:26px"><a href="<?= e(url('/')) ?>">← На головну</a></p>
    <div class="kicker">Перевірка</div>
    <h1 style="font-size:44px">Перевірка справжності диплому</h1>
    <p class="lead" style="margin:18px 0 30px">Введіть номер диплому, щоб перевірити його справжність (формат BOFU-РРРР-НННН).</p>
    <div class="diploma-box">
      <form method="post" action="<?= e(url('/diploma/check')) ?>" style="display:flex;gap:12px">
        <?= Csrf::field() ?>
        <input type="text" name="number" placeholder="BOFU-2024-001" value="<?= e($result['number'] ?? ($prefill ?? '')) ?>" required style="flex:1">
        <button class="btn btn-gold" type="submit">Перевірити</button>
      </form>
      <?php if ($result !== null): ?>
        <?php if ($result['ok']): $d = $result['diploma']; ?>
          <div class="diploma-result ok">
            <b>✓ Диплом дійсний</b>
            <p>Номер підтверджено в базі випускників Beekeeper of Ukraine.<br>
              Випускник: <b><?= e($d['student']) ?></b><?= $d['course'] ? ' · Курс: ' . e($d['course']) : '' ?><?= $d['issued_at'] ? ' · Виданий: ' . e($d['issued_at']) : '' ?></p>
          </div>
        <?php else: ?>
          <div class="diploma-result fail">
            <b>✕ Диплом не знайдено</b>
            <p>Перевірте правильність номера (формат BOFU-РРРР-НННН) або зверніться до підтримки.</p>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
