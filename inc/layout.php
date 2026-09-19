<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function head(string $title, string $base = ''): void { ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#06070c">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · 3D WEB LAB</title>
<link rel="icon" href="<?= $base ?>assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>assets/css/app.css?v=<?= @filemtime(APP_ROOT."/assets/css/app.css") ?>">
</head>
<body>
<?php }

/** Elenco di PDF scaricabili. Usato sia dentro il corso sia in Materiali. */
function mat_list(array $items, string $base = ''): void {
    if (!$items) return; ?>
  <div class="mats">
    <?php foreach ($items as $m): $zip = mat_tipo($m) === 'zip'; ?>
      <a class="mat" href="<?= $base ?>media.php?t=m&id=<?= (int)$m['id'] ?>"
         data-tipo="<?= $zip ? 'zip' : 'pdf' ?>" target="_blank" rel="noopener">
        <span class="pi<?= $zip ? ' zip' : '' ?>"><?php if ($zip): ?>
          <svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#8ab6ff"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2.2 5.3 8.5 2l6.3 3.3v6.4L8.5 15l-6.3-3.3Z"/><path d="M2.4 5.4 8.5 8.6l6.1-3.2M8.5 8.6V15"/></svg>
        <?php else: ?>
          <svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#f0a6d8"
            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4.5 1.5h6l4 4v10a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z"/><path d="M10.5 1.5v4h4"/></svg>
        <?php endif; ?></span>
        <span class="tx"><b><?= e($m['title']) ?></b>
          <span><?= $m['ltitle'] ? e($m['ltitle']).' · ' : '' ?><?= $zip ? 'ZIP' : 'PDF' ?> · <?= human_bytes((int)$m['bytes']) ?></span></span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#6c7790" stroke-width="1.6"
             stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v9M4.5 7.5L8 11l3.5-3.5M3 13.5h10"/></svg>
      </a>
    <?php endforeach; ?>
  </div>
<?php }

function topbar(?array $code = null, string $base = '', string $active = ''): void {
    $mats = 0; $nuovi = 0;
    try {
        if ($code) {
            $mats  = count(materiali_visibili((int)$code['id']));
            $nuovi = unread_user((int)$code['id']);
        }
    } catch (Throwable $e) {}
    ?>
<div class="top"><div class="wrap in">
  <a class="lg" href="<?= $base ?>corso.php"><img src="<?= $base ?>assets/img/logo-light.png" alt="3D WEB LAB"></a>
  <?php if ($code): ?>
  <nav class="tnav">
    <a href="<?= $base ?>corso.php" class="<?= $active==='corso.php'?'on':'' ?>">Il corso</a>
    <a href="<?= $base ?>materiali.php" class="<?= $active==='materiali.php'?'on':'' ?>">
      Materiali<?php if ($mats): ?><span class="cnt"><?= $mats ?></span><?php endif; ?></a>
    <a href="<?= $base ?>note.php" class="<?= $active==='note.php'?'on':'' ?>">Appunti</a>
    <a href="<?= $base ?>corsi.php" class="<?= $active==='corsi.php'?'on':'' ?>">Nuovi corsi</a>
    <a href="<?= $base ?>servizi.php" class="<?= $active==='servizi.php'?'on':'' ?>">Servizi</a>
    <a href="<?= $base ?>supporto.php" class="<?= $active==='supporto.php'?'on':'' ?>">
      Supporto<span class="cnt hot" id="navcnt"<?= $nuovi ? '' : ' hidden' ?>><?= $nuovi ?></span></a>
  </nav>
  <?php endif; ?>
  <span class="sp"></span>
  <?php if ($code): ?>
    <span class="who">accesso <b class="mono"><?= e($code['code']) ?></b></span>
    <a class="bell<?= $nuovi ? ' on' : '' ?>" id="bell" href="<?= $base ?>supporto.php"
       title="Risposte dal supporto" aria-label="Risposte dal supporto">
      <svg width="19" height="19" viewBox="0 0 20 20" fill="none" stroke="currentColor"
           stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10 2.5a5 5 0 0 0-5 5v3l-1.4 2.4A.6.6 0 0 0 4.1 14h11.8a.6.6 0 0 0 .5-.9L15 10.5v-3a5 5 0 0 0-5-5Z"/>
        <path d="M8.2 16.4a2 2 0 0 0 3.6 0"/></svg>
      <span class="bn"<?= $nuovi ? '' : ' hidden' ?>><?= $nuovi > 99 ? '99+' : $nuovi ?></span>
    </a>
    <a class="btn gh sm" href="<?= $base ?>logout.php" data-esci>Esci</a>
  <?php endif; ?>
</div></div>
<?php }

function mbar(string $active, string $base = ''): void {
  $nuovi = 0;
  try { if ($c = current_code()) $nuovi = unread_user((int)$c['id']); } catch (Throwable $e) {}
  $items = [
    ['corso.php',     'Corso',     '<path d="M3 5.6h14M3 10h14M3 14.4h9"/>'],
    ['materiali.php', 'Materiali', '<path d="M4 3h7l5 5v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M11 3v5h5"/>'],
    ['note.php',      'Appunti',   '<path d="M5 2.5h10v15H5z"/><path d="M8 6.5h4M8 10h4M8 13.5h2"/>'],
    ['corsi.php',     'Store',     '<path d="M4 4.5h5a2 2 0 0 1 2 2v9a1.6 1.6 0 0 0-1.6-1.6H4Z"/><path d="M16 4.5h-5a2 2 0 0 0-2 2v9a1.6 1.6 0 0 1 1.6-1.6H16Z"/>'],
    ['servizi.php',   'Servizi',   '<path d="M3 6.5h14v10H3z"/><path d="M7 6.5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2.5"/>'],
    ['supporto.php',  'Supporto',  '<path d="M17 12a2 2 0 0 1-2 2H6l-3 3V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"/>'],
    ['logout.php',    'Esci',      '<path d="M8 17H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"/><path d="M13 14l4-4-4-4M17 10H8"/>'],
  ]; ?>
<nav class="mbar">
  <?php foreach ($items as [$h,$l,$p]): ?>
    <a href="<?= $base.$h ?>" class="<?= $active===$h?'on':'' ?>"<?= $h==='logout.php'?' data-esci':'' ?>>
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor"
           stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $p ?></svg><?= $l ?>
      <?php if ($h === 'supporto.php'): ?><span class="mdot" id="mdot"<?= $nuovi ? '' : ' hidden' ?>></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<?php }

function foot(): void { ?>
<script src="assets/js/pdf.js?v=<?= @filemtime(APP_ROOT.'/assets/js/pdf.js') ?>"></script>
<div class="foot wrap">3D WEB LAB · area riservata ai corsisti<br>
I contenuti sono personali e non cedibili.</div>
<?php if (current_code()): ?>
<div class="ask" id="ask-esci" hidden>
  <div class="box" role="dialog" aria-modal="true" aria-labelledby="ask-t">
    <h3 id="ask-t">Vuoi uscire dal corso?</h3>
    <p>Per rientrare ti servirà di nuovo il tuo codice di accesso.
      Se volevi solo tornare indietro, annulla.</p>
    <div class="ab">
      <button class="btn w" type="button" data-no>Resta nel corso</button>
      <form method="post" action="logout.php">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <button class="btn dg w" type="submit">Sì, esci</button>
      </form>
    </div>
  </div>
</div>
<script>
(function () {   // campanella: risposte del supporto, senza dover ricaricare
  var b = document.getElementById('bell'); if (!b) return;
  var n = b.querySelector('.bn'),
      nav = document.getElementById('navcnt'),
      dot = document.getElementById('mdot');
  var titolo = document.title.replace(/^\(\d+\)\s*/, '');
  function dipingi(k) {
    k = k | 0;
    if (n) { n.hidden = k === 0; n.textContent = k > 99 ? '99+' : k; }
    if (nav) { nav.hidden = k === 0; nav.textContent = k; }
    if (dot) dot.hidden = k === 0;
    b.classList.toggle('on', k > 0);
    document.title = k > 0 ? '(' + k + ') ' + titolo : titolo;
  }
  dipingi(n && !n.hidden ? parseInt(n.textContent, 10) : 0);
  function chiedi() {
    if (document.hidden) return;                 // scheda in secondo piano: non interrogo
    fetch('avvisi.php', {credentials: 'same-origin'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { if (j && typeof j.n === 'number') dipingi(j.n); })
      .catch(function () {});                    // rete assente: riprovo al giro dopo
  }
  setInterval(chiedi, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) chiedi(); });
})();
(function () {
  var d = document.getElementById('ask-esci');
  if (!d) return;
  function apri(e) { e.preventDefault(); d.hidden = false;
                     var b = d.querySelector('[data-no]'); if (b) b.focus(); }
  function chiudi() { d.hidden = true; }
  document.querySelectorAll('a[data-esci]').forEach(function (a) { a.addEventListener('click', apri); });
  d.querySelector('[data-no]').addEventListener('click', chiudi);
  d.addEventListener('click', function (e) { if (e.target === d) chiudi(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !d.hidden) chiudi(); });
})();
</script>
<?php endif; ?>
</body></html>
<?php }
