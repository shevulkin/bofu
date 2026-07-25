<section class="section" style="text-align:center;padding-top:96px">
  <div class="container narrow">
    <div style="font-size:56px;margin-bottom:18px">🍯</div>
    <h2>Замовлення прийнято!</h2>
    <p class="lead" style="margin:18px auto 8px">Номер вашого замовлення:</p>
    <p style="font-family:var(--serif);font-size:34px;color:var(--gold);font-weight:700"><?= e($order['number']) ?></p>
    <p class="muted" style="margin:16px auto 34px">Ми зв'яжемося з вами найближчим часом для підтвердження.<br>Сума до сплати: <b><?= e(price_fmt($order['total'])) ?></b></p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-gold" href="<?= e(url('/shop')) ?>">Продовжити покупки</a>
      <a class="btn btn-line" href="<?= e(url('/')) ?>">На головну</a>
    </div>
  </div>
</section>
