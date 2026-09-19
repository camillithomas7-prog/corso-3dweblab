<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/auth.php';

function ahead(string $title, string $active): void {
    $a = require_admin(); ?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#06070c"><meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> · Admin 3D WEB LAB</title>
<link rel="icon" href="../assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/app.css?v=<?= @filemtime(APP_ROOT."/assets/css/app.css") ?>">
</head><body>
<div class="top"><div class="wrap in">
  <a class="lg" href="dashboard.php"><img src="../assets/img/logo-light.png" alt="3D WEB LAB"></a>
  <span class="pill acc">Admin</span>
  <span class="sp"></span>
  <?php $nl0 = 0; try { $nl0 = unread_admin(); } catch (Throwable $e) {} ?>
  <a class="bell<?= $nl0 ? ' on' : '' ?>" id="bell" href="chat.php"
     title="Messaggi dai clienti" aria-label="Messaggi dai clienti">
    <svg width="19" height="19" viewBox="0 0 20 20" fill="none" stroke="currentColor"
         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10 2.5a5 5 0 0 0-5 5v3l-1.4 2.4A.6.6 0 0 0 4.1 14h11.8a.6.6 0 0 0 .5-.9L15 10.5v-3a5 5 0 0 0-5-5Z"/>
      <path d="M8.2 16.4a2 2 0 0 0 3.6 0"/></svg>
    <span class="bn"<?= $nl0 ? '' : ' hidden' ?>><?= $nl0 > 99 ? '99+' : $nl0 ?></span>
  </a>
  <a class="btn gh sm" href="../corso.php" target="_blank" rel="noopener">Vedi il corso</a>
  <a class="btn gh sm" href="logout.php">Esci</a>
</div></div>
<div class="wrap" style="padding-top:20px;padding-bottom:60px">
<nav class="anav">
  <?php $nl = 0; try { $nl = unread_admin(); } catch (Throwable $e) {}
  foreach ([
    'dashboard.php'=>'Panoramica','categorie.php'=>'Categorie','lezioni.php'=>'Lezioni',
    'materiali.php'=>'Materiali','corsi.php'=>'Nuovi corsi','codici.php'=>'Codici di accesso','accessi.php'=>'Accessi','domande.php'=>'Domande',
    'risposte.php'=>'Risposte','sondaggio.php'=>'Sondaggio','chat.php'=>'Supporto','pacchetti.php'=>'Pacchetti',
    'impostazioni.php'=>'Impostazioni'] as $h=>$l): ?>
    <a href="<?= $h ?>" class="<?= $active===$h?'on':'' ?>"><?= $l ?><?php
      if ($h === 'chat.php'): ?><span class="nb" id="navnb"<?= $nl ? '' : ' hidden' ?>><?= $nl ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</nav>
<?php }

function afoot(): void { ?>
</div>
<script>
(function () {
  var b = document.getElementById('bell'); if (!b) return;
  var n = b.querySelector('.bn'), nav = document.getElementById('navnb');
  var titolo = document.title.replace(/^\(\d+\)\s*/, '');
  function dipingi(k) {
    k = k | 0;
    if (n) { n.hidden = k === 0; n.textContent = k > 99 ? '99+' : k; }
    if (nav) { nav.hidden = k === 0; nav.textContent = k; }
    b.classList.toggle('on', k > 0);
    document.title = k > 0 ? '(' + k + ') ' + titolo : titolo;
  }
  dipingi(n && !n.hidden ? parseInt(n.textContent, 10) : 0);
  function chiedi() {
    if (document.hidden) return;                 // scheda in secondo piano: non interrogo
    fetch('campanella.php', {credentials: 'same-origin'})
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { if (j && typeof j.n === 'number') dipingi(j.n); })
      .catch(function () {});                    // rete assente: riprovo al giro dopo
  }
  setInterval(chiedi, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) chiedi(); });
})();
</script>
</body></html>
<?php }

function flash(?string $m = null, string $type = 'ok'): ?array {
    boot_session();
    if ($m !== null) { $_SESSION['flash'] = ['m'=>$m,'t'=>$type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function show_flash(): void {
    if ($f = flash()) echo '<div class="msg '.e($f['t']).'">'.e($f['m']).'</div>';
}
function back(string $to): never { header("Location: $to"); exit; }

/** Nome file sicuro e irripetibile per lo storage. */
function safe_name(string $orig, string $ext): string {
    $b = pathinfo($orig, PATHINFO_FILENAME);
    $b = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $b);
    $b = trim(substr($b, 0, 60), '-') ?: 'file';
    return date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $b . '.' . $ext;
}
/**
 * Durata di un video. Sugli hosting condivisi shell_exec è quasi sempre
 * disabilitato: in quel caso restituisce 0 e la durata la legge il browser,
 * che ce l'ha già senza scomodare il server.
 */
function video_seconds(string $path): int {
    if (!function_exists('shell_exec')) return 0;
    $dis = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (in_array('shell_exec', $dis, true)) return 0;
    try {
        $bin = trim((string)@shell_exec('command -v ffprobe 2>/dev/null'));
        if (!$bin) return 0;
        $o = @shell_exec(escapeshellcmd($bin) . ' -v error -show_entries format=duration -of default=nw=1:nk=1 '
                         . escapeshellarg($path) . ' 2>/dev/null');
        return (int)round((float)$o);
    } catch (Throwable $e) { return 0; }
}
