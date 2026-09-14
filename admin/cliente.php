<?php
require_once __DIR__ . '/inc.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT * FROM codes WHERE id=?'); $s->execute([$id]);
$c = $s->fetch();
if (!$c) back('codici.php');
$p = profile($id);

$tot = (int)db()->query('SELECT COUNT(*) FROM lessons WHERE published=1')->fetchColumn();
$q = db()->prepare('SELECT COUNT(*) FROM progress WHERE code_id=? AND completed=1'); $q->execute([$id]);
$done = (int)$q->fetchColumn();
$q = db()->prepare('SELECT COUNT(*) FROM notes WHERE code_id=? AND body<>\'\''); $q->execute([$id]);
$nn = (int)$q->fetchColumn();
$q = db()->prepare("SELECT q.label, q.type, q.pos, a.value FROM answers a
                    JOIN questions q ON q.id=a.question_id
                    WHERE a.code_id=? AND a.value<>'' ORDER BY q.pos, q.id");
$q->execute([$id]); $risp = $q->fetchAll();

$q = db()->prepare("SELECT l.title, p.completed, p.seconds, p.updated_at
                    FROM progress p JOIN lessons l ON l.id=p.lesson_id
                    WHERE p.code_id=? ORDER BY p.updated_at DESC LIMIT 12");
$q->execute([$id]); $att = $q->fetchAll();

$eta = '';
if ($p && $p['birthdate']) {
    $d = DateTime::createFromFormat('Y-m-d', $p['birthdate']);
    if ($d) $eta = ' · ' . $d->diff(new DateTime())->y . ' anni';
}
ahead('Scheda cliente', 'codici.php'); show_flash(); ?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
  <div>
    <div class="kicker" style="margin-bottom:7px">Scheda cliente</div>
    <h1 style="font-size:23px"><?= e($p ? trim($p['first_name'].' '.$p['last_name']) : ($c['label'] ?: 'Senza nome')) ?></h1>
    <p class="muted" style="margin-top:6px"><span class="codecell"><?= e($c['code']) ?></span></p>
  </div>
  <a class="btn gh sm" href="codici.php">Torna ai codici</a>
</div>

<?php if (!$p || !$p['completed_at']): ?>
  <div class="msg info">Questo corsista non ha ancora compilato i suoi dati: li chiediamo al primo accesso.</div>
<?php endif; ?>

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Dati forniti dal cliente</h3>
    <?php if ($p && $p['completed_at']): ?>
      <span class="pill ok">compilati il <?= e(date('d/m/Y', strtotime($p['completed_at']))) ?></span><?php endif; ?></div>
  <div class="bd">
    <?php if ($p && $p['completed_at']): ?>
    <dl class="sched">
      <div><dt>Nome e cognome</dt><dd><?= e($p['first_name'].' '.$p['last_name']) ?></dd></div>
      <div><dt>Telefono</dt><dd class="mono"><a href="tel:<?= e($p['phone_cc'].$p['phone']) ?>"><?= e($p['phone_cc'].' '.$p['phone']) ?></a></dd></div>
      <div><dt>Email</dt><dd><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a></dd></div>
      <div><dt>Data di nascita</dt><dd><?= e(date('d/m/Y', strtotime($p['birthdate']))) ?><span class="muted" style="font-weight:400"><?= e($eta) ?></span></dd></div>
    </dl>
    <?php else: ?>
      <p class="muted">Nessun dato ancora. Quello che sai di lui è solo ciò che è arrivato dall'ordine.</p>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Risposte al questionario</h3>
    <?php if ($p && !empty($p['quiz_at'])): ?>
      <span class="pill ok">compilato il <?= e(date('d/m/Y', strtotime($p['quiz_at']))) ?></span>
    <?php else: ?><span class="pill warn">non ancora compilato</span><?php endif; ?></div>
  <div class="bd">
    <?php if (!$risp): ?>
      <p class="muted">Nessuna risposta. Il questionario si apre al primo accesso, subito dopo i dati personali.</p>
    <?php else: foreach ($risp as $r): ?>
      <div class="quote" style="border-left-color:<?= $r['type']==='text' ? 'var(--acc)' : 'var(--line)' ?>">
        <div class="who" style="margin:0 0 6px"><?= e($r['label']) ?></div>
        <p><?= nl2br(e($r['value'])) ?></p>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="k">Ordine</div><div class="v" style="font-size:18px"><?= e($c['order_ref'] ?: '—') ?></div></div>
  <div class="stat"><div class="k">Accessi</div><div class="v"><?= (int)$c['uses'] ?></div></div>
  <div class="stat"><div class="k">Lezioni completate</div><div class="v acc"><?= $done ?><span style="font-size:15px;color:var(--dim)"> / <?= $tot ?></span></div></div>
  <div class="stat"><div class="k">Appunti scritti</div><div class="v"><?= $nn ?></div></div>
</div>

<div class="card">
  <div class="hd"><h3>Attività recente</h3></div>
  <?php if (!$att): ?><div class="bd"><p class="muted">Non ha ancora aperto nessuna lezione.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Lezione</th><th>Stato</th><th>Punto</th><th>Quando</th></tr></thead>
    <tbody><?php foreach ($att as $a): ?>
      <tr><td><?= e($a['title']) ?></td>
          <td><?= $a['completed'] ? '<span class="pill ok">completata</span>' : '<span class="pill">iniziata</span>' ?></td>
          <td class="muted mono"><?= hms((int)$a['seconds']) ?></td>
          <td class="muted"><?= e(date('d/m/Y H:i', strtotime($a['updated_at']))) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php afoot();
