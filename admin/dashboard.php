<?php
require_once __DIR__ . '/inc.php';
require_admin();
$n = fn(string $q) => (int)db()->query($q)->fetchColumn();
$cats = $n('SELECT COUNT(*) FROM categories');
$les  = $n('SELECT COUNT(*) FROM lessons');
$mats = $n('SELECT COUNT(*) FROM materials');
$act  = $n("SELECT COUNT(*) FROM codes WHERE status='active'");
$used = $n('SELECT COUNT(*) FROM codes WHERE first_used_at IS NOT NULL');
$mins = (int)(db()->query('SELECT COALESCE(SUM(duration_sec),0) FROM lessons')->fetchColumn());
$last = db()->query("SELECT c.code, c.label, c.last_used_at,
                      (SELECT COUNT(*) FROM progress p WHERE p.code_id=c.id AND p.completed=1) AS done
                     FROM codes c WHERE c.last_used_at IS NOT NULL
                     ORDER BY c.last_used_at DESC LIMIT 8")->fetchAll();
$noVid = db()->query("SELECT COUNT(*) FROM lessons WHERE video_src='' OR video_src IS NULL")->fetchColumn();

ahead('Panoramica', 'dashboard.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:16px">Panoramica</h1>
<div class="stats">
  <div class="stat"><div class="k">Lezioni pubblicate</div><div class="v acc"><?= $les ?></div></div>
  <div class="stat"><div class="k">Durata totale</div><div class="v"><?= $mins ? hms($mins) : '—' ?></div></div>
  <div class="stat"><div class="k">Codici attivi</div><div class="v"><?= $act ?></div></div>
  <div class="stat"><div class="k">Corsisti entrati</div><div class="v"><?= $used ?></div></div>
</div>

<?php if ($les === 0): ?>
  <div class="msg info"><b>Si parte da qui.</b> Crea una categoria, poi carica la prima lezione, infine genera i codici da mandare ai clienti.</div>
<?php elseif ($noVid): ?>
  <div class="msg info"><?= (int)$noVid ?> lezione/i non ha ancora un video associato.</div>
<?php endif; ?>

<div class="row c3" style="margin-bottom:22px">
  <a class="card" href="categorie.php" style="display:block"><div class="bd">
    <div class="kicker" style="margin-bottom:8px">Passo 1</div>
    <h3 style="margin-bottom:5px">Categorie</h3>
    <p class="muted" style="font-size:13.5px"><?= $cats ?> create · organizza il corso in moduli</p></div></a>
  <a class="card" href="lezioni.php" style="display:block"><div class="bd">
    <div class="kicker" style="margin-bottom:8px">Passo 2</div>
    <h3 style="margin-bottom:5px">Lezioni e video</h3>
    <p class="muted" style="font-size:13.5px"><?= $les ?> lezioni · <?= $mats ?> materiali PDF</p></div></a>
  <a class="card" href="codici.php" style="display:block"><div class="bd">
    <div class="kicker" style="margin-bottom:8px">Passo 3</div>
    <h3 style="margin-bottom:5px">Codici di accesso</h3>
    <p class="muted" style="font-size:13.5px">Generali e mandali con l'ordine</p></div></a>
</div>

<div class="card">
  <div class="hd"><h3>Ultimi accessi</h3></div>
  <?php if (!$last): ?>
    <div class="bd"><p class="muted">Nessun corsista è ancora entrato.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Codice</th><th>Cliente</th><th>Ultimo accesso</th><th>Lezioni completate</th></tr></thead>
    <tbody><?php foreach ($last as $r): ?>
      <tr><td class="codecell"><?= e($r['code']) ?></td>
          <td><?= e($r['label'] ?: '—') ?></td>
          <td class="muted"><?= e(date('d/m/Y H:i', strtotime($r['last_used_at']))) ?></td>
          <td><?= (int)$r['done'] ?> / <?= $les ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php afoot();
