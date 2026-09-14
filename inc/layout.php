<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/** Copertina di una lezione: la sua, oppure quella predefinita del brand. */
function poster_url(array $l, string $base = ''): string {
    return !empty($l['poster'])
        ? $base . 'media.php?t=p&id=' . (int)$l['id']
        : $base . 'assets/img/poster-default.jpg';
}

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

function topbar(?array $code = null, string $base = '', string $active = ''): void {
    $mats = 0; $nuovi = 0;
    try {
        $mats = (int)db()->query('SELECT COUNT(*) FROM materials')->fetchColumn();
        if ($code) $nuovi = unread_user((int)$code['id']);
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
    <a href="<?= $base ?>servizi.php" class="<?= $active==='servizi.php'?'on':'' ?>">Servizi</a>
    <a href="<?= $base ?>supporto.php" class="<?= $active==='supporto.php'?'on':'' ?>">
      Supporto<?php if ($nuovi): ?><span class="cnt hot"><?= $nuovi ?></span><?php endif; ?></a>
  </nav>
  <?php endif; ?>
  <span class="sp"></span>
  <?php if ($code): ?>
    <span class="who">accesso <b class="mono"><?= e($code['code']) ?></b></span>
    <a class="btn gh sm" href="<?= $base ?>logout.php">Esci</a>
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
    ['servizi.php',   'Servizi',   '<path d="M3 6.5h14v10H3z"/><path d="M7 6.5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2.5"/>'],
    ['supporto.php',  'Supporto',  '<path d="M17 12a2 2 0 0 1-2 2H6l-3 3V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2Z"/>'],
    ['logout.php',    'Esci',      '<path d="M8 17H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h4"/><path d="M13 14l4-4-4-4M17 10H8"/>'],
  ]; ?>
<nav class="mbar">
  <?php foreach ($items as [$h,$l,$p]): ?>
    <a href="<?= $base.$h ?>" class="<?= $active===$h?'on':'' ?>">
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor"
           stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $p ?></svg><?= $l ?>
      <?php if ($h === 'supporto.php' && $nuovi): ?><span class="mdot"></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>
<?php }

function foot(): void { ?>
<script src="assets/js/pdf.js?v=<?= @filemtime(APP_ROOT.'/assets/js/pdf.js') ?>"></script>
<div class="foot wrap">3D WEB LAB · area riservata ai corsisti<br>
I contenuti sono personali e non cedibili.</div>
</body></html>
<?php }
