<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$corsi = db()->query('SELECT * FROM corsi WHERE published=1 ORDER BY pos, id')->fetchAll();

/** 9.99 → "9,99" · 49 → "49" */
function prezzo(float $n): string {
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}

head('Nuovi corsi'); topbar($code, '', 'corsi.php'); ?>
<div class="wrap" style="padding-top:26px;padding-bottom:90px">

  <div class="svhead">
    <div class="kicker" style="margin-bottom:9px">Prezzo riservato ai corsisti</div>
    <h1>Gli altri corsi</h1>
    <p>Sono percorsi separati, ognuno su un servizio che puoi rivendere ai tuoi clienti.
       Li trovi in vendita a prezzo pieno sul sito: tu, che hai già comprato,
       li paghi con lo sconto qui sotto.</p>
  </div>

  <?php if (!$corsi): ?>
    <div class="card"><div class="bd">
      <h3 style="margin-bottom:8px">Ancora nessun corso</h3>
      <p class="muted">Appena ne pubblichiamo uno nuovo lo trovi qui.</p>
    </div></div>
  <?php endif; ?>

  <div class="corsi">
    <?php foreach ($corsi as $c):
      $img  = corso_img($c);
      $off  = $c['price_offer'] !== null && (float)$c['price_offer'] > 0
              && (float)$c['price_offer'] < (float)$c['price'];
      $risp = $off ? (float)$c['price'] - (float)$c['price_offer'] : 0; ?>
      <article class="ccard">
        <?php if ($img): ?>
          <div class="cimg">
            <img src="<?= e($img) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
            <?php if ($c['badge']): ?><span class="cbadge2"><?= e($c['badge']) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="cbody">
          <h2><?= e($c['title']) ?></h2>
          <?php if ($c['subtitle']): ?><p class="csub"><?= e($c['subtitle']) ?></p><?php endif; ?>
          <?php if ($c['descr']): ?><p class="cdes"><?= e($c['descr']) ?></p><?php endif; ?>

          <div class="cprice">
            <?php if ($off): ?>
              <span class="pnew"><?= prezzo((float)$c['price_offer']) ?> €</span>
              <span class="pold"><?= prezzo((float)$c['price']) ?> €</span>
              <span class="psave">risparmi <?= prezzo($risp) ?> €</span>
            <?php else: ?>
              <span class="pnew"><?= prezzo((float)$c['price']) ?> €</span>
            <?php endif; ?>
          </div>

          <?php if ($off && $c['coupon']): ?>
            <div class="ccoup">
              <span class="cl">Il tuo codice sconto</span>
              <button type="button" class="ccode" data-code="<?= e($c['coupon']) ?>"
                      title="Tocca per copiarlo"><?= e($c['coupon']) ?></button>
              <span class="ch">Incollalo al momento del pagamento, nel campo «codice sconto».</span>
            </div>
          <?php endif; ?>

          <?php if ($c['url']): ?>
            <a class="btn w" href="<?= e($c['url']) ?>" target="_blank" rel="noopener">
              Vai al corso</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.querySelectorAll('.ccode').forEach(function (b) {
  b.addEventListener('click', function () {
    var testo = b.dataset.code, fatto = function () {
      var prima = b.textContent;
      b.textContent = 'copiato';
      b.classList.add('ok');
      setTimeout(function () { b.textContent = prima; b.classList.remove('ok'); }, 1600);
    };
    if (navigator.clipboard) { navigator.clipboard.writeText(testo).then(fatto, function () {}); return; }
    // browser vecchi o pagina non sicura: selezione manuale
    var t = document.createElement('textarea');
    t.value = testo; document.body.appendChild(t); t.select();
    try { document.execCommand('copy'); fatto(); } catch (e) {}
    document.body.removeChild(t);
  });
});
</script>
<?php mbar('corsi.php'); foot();
