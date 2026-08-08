<footer>
  <div class="container footer-inner">
    <div>
      <div class="brand" style="margin-bottom:10px"><img src="<?= e(asset('img/avatar.png')) ?>" alt=""> <span class="brand-text" style="display:inline">BEEKEEPER OF UKRAINE</span></div>
      © <?= date('Y') ?> Beekeeper of Ukraine. Мед і продукти бджільництва з власної пасіки.
    </div>
    <div class="footer-social">
      <a href="<?= e(Content::title('social_instagram', '#')) ?>" target="_blank" rel="noopener" title="Instagram" style="display:flex;align-items:center;gap:7px"<?= edit_mark('social_instagram') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="2.5" width="19" height="19" rx="5.2"/><circle cx="12" cy="12" r="4.6"/><circle cx="17.6" cy="6.4" r="1.3" fill="currentColor" stroke="none"/></svg>Instagram</a>
      <a href="<?= e(Content::title('social_youtube', '#')) ?>" target="_blank" rel="noopener" title="YouTube" style="display:flex;align-items:center;gap:7px"<?= edit_mark('social_youtube') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M23.5 6.5a3 3 0 0 0-2.1-2.2C19.5 3.8 12 3.8 12 3.8s-7.5 0-9.4.5A3 3 0 0 0 .5 6.5 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.5 3 3 0 0 0 2.1 2.2c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.2A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.5zM9.6 15.6V8.4L15.8 12l-6.2 3.6z"/></svg>YouTube</a>
      <a href="<?= e(Content::title('social_tiktok', '#')) ?>" target="_blank" rel="noopener" title="TikTok" style="display:flex;align-items:center;gap:7px"<?= edit_mark('social_tiktok') ?>><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M16.6 2h3.1c.2 1.9 1.4 3.6 3.3 4v3.1c-1.2 0-2.4-.4-3.4-1v6.9a6.9 6.9 0 1 1-6.9-6.9c.3 0 .7 0 1 .1v3.3a3.6 3.6 0 1 0 2.9 3.5V2z"/></svg>TikTok</a>
    </div>
  </div>
</footer>
