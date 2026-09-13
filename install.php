<?php
require_once __DIR__ . '/inc/auth.php';
$done = [];
foreach (['data','storage','storage/video','storage/pdf','storage/tmp'] as $d) {
    $p = APP_ROOT . '/' . $d;
    if (!is_dir($p)) { @mkdir($p, 0775, true); $done[] = "creata $d/"; }
    if (!is_writable($p)) $done[] = "⚠ $d/ NON scrivibile — dai permessi 755";
}
db();  // crea lo schema
$has  = (int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$nAdm0 = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();

// A installazione avvenuta l'installer si spegne da solo: con il deploy da GitHub
// questo file torna sul server a ogni push, e non deve restare una porta aperta.
if ($nAdm0 > 0 && !isset($_GET['force'])) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>Già installato</title>'
       . '<body style="background:#06070c;color:#a4aec6;font-family:system-ui;padding:48px;line-height:1.7">'
       . '<h1 style="color:#f4f6fb;font-size:20px">Installazione già completata</h1>'
       . '<p>Questo pannello è disattivo perché esiste già un accesso admin.</p>'
       . '<p style="margin-top:18px"><a style="color:#6ee7ff" href="admin/index.php">Vai al pannello admin</a></p>');
}

if (isset($_GET['demo']) && !$has) {
    $c = db()->prepare('INSERT INTO categories(title,descr,pos) VALUES(?,?,?)');
    $c->execute(['Fondamenta', 'Da zero alla prima scena 3D che gira nel browser.', 1]);   $c1 = db()->lastInsertId();
    $c->execute(['Costruire il sito', 'Layout, scroll, interazioni e prestazioni.', 2]);    $c2 = db()->lastInsertId();
    $c->execute(['Pubblicare e vendere', 'Dominio, hosting e come si presenta al cliente.', 3]); $c3 = db()->lastInsertId();
    $l = db()->prepare('INSERT INTO lessons(category_id,title,descr,video_type,video_src,duration_sec,pos,published)
                        VALUES(?,?,?,?,?,?,?,1)');
    $demo = [
      [$c1,'Benvenuto e come useremo Claude','Cosa costruiamo insieme e come impostare gli strumenti.','demo1.mp4',0,1],
      [$c1,'La prima scena in dieci minuti','Scena, luci, camera: il minimo che serve per vedere qualcosa.','demo2.mp4',0,2],
      [$c1,'Materiali e luci che sembrano veri','',  '',0,3],
      [$c2,'Layout e sezioni che scorrono','',       '',0,1],
      [$c2,'Animazioni legate allo scroll','',       '',0,2],
      [$c2,'Farlo andare veloce anche da telefono','','',0,3],
      [$c3,'Dominio e hosting passo passo','',       '',0,1],
      [$c3,'Presentarlo al cliente e farsi pagare','','',0,2],
    ];
    foreach ($demo as $d) $l->execute([$d[0],$d[1],$d[2],'file',$d[3],$d[4],$d[5]]);
    for ($i=0;$i<3;$i++) {
        db()->prepare('INSERT INTO codes(code,label,order_ref) VALUES(?,?,?)')
            ->execute([generate_code(), ['Mario Rossi','Giulia Bianchi','Luca Verdi'][$i], '#10'.(40+$i)]);
    }
    $done[] = 'contenuti dimostrativi inseriti';
}
$codes = db()->query('SELECT code,label FROM codes ORDER BY id LIMIT 5')->fetchAll();
$nAdm  = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
?>
<!doctype html><html lang="it"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Installazione · 3D WEB LAB</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css"></head><body>
<div class="wrap" style="max-width:720px;padding-top:40px;padding-bottom:60px">
  <img src="assets/img/logo-light.png" style="height:44px;margin-bottom:26px" alt="3D WEB LAB">
  <h1 style="font-size:25px;margin-bottom:8px">Installazione</h1>
  <p class="muted" style="margin-bottom:22px">Controllo cartelle e database.</p>
  <div class="card" style="margin-bottom:18px"><div class="bd">
    <?php foreach ($done as $d): ?><div style="font-size:14.5px;margin-bottom:6px"><?= e($d) ?></div><?php endforeach; ?>
    <div style="font-size:14.5px;color:#4ade80">✓ database pronto</div>
    <div style="font-size:14.5px;color:<?= $nAdm?'#4ade80':'#fbbf24' ?>">
      <?= $nAdm ? '✓ admin configurato' : '→ admin ancora da creare' ?></div>
  </div></div>

  <?php if (!$has && !isset($_GET['demo'])): ?>
    <div class="msg info">Vuoi vedere subito com'è? <a href="install.php?demo=1" style="color:#6ee7ff"><b>Inserisci contenuti dimostrativi</b></a> — categorie, lezioni e tre codici di prova. Puoi cancellarli dal pannello.</div>
  <?php endif; ?>

  <?php if ($codes): ?>
  <div class="card" style="margin-bottom:18px">
    <div class="hd"><h3>Codici disponibili per la prova</h3></div>
    <div class="bd"><?php foreach ($codes as $c): ?>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #171c2a">
        <span class="codecell"><?= e($c['code']) ?></span><span class="muted"><?= e($c['label']) ?></span></div>
    <?php endforeach; ?></div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="admin/index.php">Vai al pannello admin</a>
    <a class="btn gh" href="index.php">Vai all'area corsisti</a>
  </div>
  <p class="muted" style="font-size:13px;margin-top:22px">Quando è tutto a posto, elimina <b>install.php</b> dal server.</p>
</div></body></html>
