<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$pk = db()->query('SELECT * FROM packages WHERE published=1 ORDER BY pos, id')->fetchAll();
$mail = setting('services_mail', 'supporto@3dweblab.it');

/** 1650 → "1.650" */
function eur(float $n): string {
    return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
}
function mailto(string $to, array $p): string {
    $sub = 'Prenotazione pacchetto — ' . $p['title'];
    $body = "Salve,\n\nvorrei prenotare il pacchetto \"{$p['title']}\" ("
          . eur((float)$p['price']) . " €" . ($p['period'] ? ' ' . $p['period'] : '') . ").\n\n"
          . "Nome e cognome:\nTelefono:\nDue righe sul mio progetto:\n\nGrazie.";
    return 'mailto:' . $to . '?subject=' . rawurlencode($sub) . '&body=' . rawurlencode($body);
}

head('Servizi'); topbar($code, '', 'servizi.php'); ?>
<div class="wrap" style="padding-top:26px;padding-bottom:90px">

  <div class="svhead">
    <div class="kicker" style="margin-bottom:9px">Riservato agli studenti</div>
    <h1>Pensiamoci insieme</h1>
    <p>Il corso ti dà il metodo. Se a un certo punto vuoi qualcuno accanto — o vuoi
       semplicemente che il sito lo costruiamo noi — questi sono i modi per lavorare
       con il nostro team, a condizioni che valgono solo per chi ha comprato il corso.</p>
  </div>

  <div class="pkgs">
    <?php foreach ($pk as $p):
      $sc = ((float)$p['price_was'] > 0)
          ? round((1 - (float)$p['price'] / (float)$p['price_was']) * 100) : 0; ?>
      <div class="pkg <?= $p['featured'] ? 'hot' : '' ?>">
        <?php if ($p['badge']): ?><span class="pbadge"><?= e($p['badge']) ?></span><?php endif; ?>
        <div class="ph">
          <div class="kicker"><?= e($p['kicker']) ?></div>
          <h2><?= e($p['title']) ?></h2>
          <p class="pintro"><?= e($p['intro']) ?></p>
        </div>

        <div class="pprice">
          <span class="now"><?= eur((float)$p['price']) ?> €</span>
          <?php if ((float)$p['price_was'] > 0): ?>
            <span class="was"><?= eur((float)$p['price_was']) ?> €</span>
          <?php endif; ?>
          <?php if ($p['period']): ?><span class="per"><?= e($p['period']) ?></span><?php endif; ?>
          <?php if ($sc > 0): ?><span class="off">−<?= $sc ?>%</span><?php endif; ?>
        </div>

        <?php if ($p['bullets']): ?>
        <ul class="pbul">
          <?php foreach (preg_split('/\r?\n/', trim($p['bullets'])) as $b): if (!trim($b)) continue; ?>
            <li>
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor"
                   stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l3 3 6-7"/></svg>
              <span><?= e(trim($b)) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="pfoot">
          <?php if ($p['note']): ?><p class="pnote"><?= e($p['note']) ?></p><?php endif; ?>
          <a class="btn w" href="<?= e(mailto($mail, $p)) ?>">Richiedi questo pacchetto</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="howto">
    <h3>Come si prenota</h3>
    <div class="steps3">
      <div><span>1</span><b>Scrivici</b>
        <p>Premi il pulsante del pacchetto che ti interessa. Si apre una mail già compilata:
           aggiungi il tuo <b>recapito telefonico</b> e invia.</p>
        <p class="mailrow"><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></p></div>
      <div><span>2</span><b>Ti chiamiamo</b>
        <p>Un nostro consulente ti contatta entro <b>12/24 ore</b> per capire cosa ti serve
           e confermare il pacchetto giusto per te.</p></div>
      <div><span>3</span><b>Si parte</b>
        <p>Al telefono definiamo tempi e modalità di pagamento, poi si comincia a lavorare
           sul tuo progetto.</p></div>
    </div>
    <p class="hnote">Prezzi in euro, IVA inclusa dove applicabile. Le condizioni indicate sono
       riservate agli studenti del corso e non sono cedibili.</p>
  </div>
</div>
<?php mbar('servizi.php'); foot();
