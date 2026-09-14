<?php
require_once __DIR__ . '/inc.php';
require_admin();

$qs = db()->query('SELECT * FROM questions ORDER BY pos, id')->fetchAll();
$tot = (int)db()->query("SELECT COUNT(*) FROM profiles WHERE quiz_at IS NOT NULL")->fetchColumn();

// esportazione completa, una riga per corsista
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="risposte-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // così Excel legge gli accenti
    $head = ['Nome', 'Cognome', 'Email', 'Telefono', 'Codice', 'Ordine', 'Compilato il'];
    foreach ($qs as $q) $head[] = $q['label'];
    fputcsv($out, $head, ';');
    $rs = db()->query("SELECT c.id, c.code, c.order_ref, p.first_name, p.last_name, p.email,
                              p.phone_cc, p.phone, p.quiz_at
                       FROM profiles p JOIN codes c ON c.id=p.code_id
                       WHERE p.quiz_at IS NOT NULL ORDER BY p.quiz_at DESC")->fetchAll();
    $ga = db()->prepare('SELECT question_id, value FROM answers WHERE code_id=?');
    foreach ($rs as $r) {
        $ga->execute([$r['id']]);
        $a = [];
        foreach ($ga->fetchAll() as $x) $a[(int)$x['question_id']] = $x['value'];
        $row = [$r['first_name'], $r['last_name'], $r['email'], $r['phone_cc'].' '.$r['phone'],
                $r['code'], $r['order_ref'], date('d/m/Y H:i', strtotime($r['quiz_at']))];
        foreach ($qs as $q) $row[] = $a[(int)$q['id']] ?? '';
        fputcsv($out, $row, ';');
    }
    fclose($out); exit;
}

ahead('Risposte', 'risposte.php'); show_flash(); ?>
<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:20px">
  <div>
    <h1 style="font-size:23px;margin-bottom:6px">Risposte al questionario</h1>
    <p class="muted" style="font-size:14.5px"><b style="color:var(--ink)"><?= $tot ?></b>
      corsist<?= $tot === 1 ? 'a ha' : 'i hanno' ?> compilato il questionario.</p>
  </div>
  <?php if ($tot): ?><a class="btn gh" href="risposte.php?export=csv">Scarica tutto in CSV</a><?php endif; ?>
</div>

<?php if (!$tot): ?>
  <div class="card"><div class="bd"><p class="muted">
    Nessuna risposta ancora. Compariranno qui appena il primo corsista entrerà nel corso.</p></div></div>
<?php else: foreach ($qs as $q):
  $id = (int)$q['id'];
  if ($q['type'] === 'single'):
    $opz = array_filter(array_map('trim', preg_split('/\r?\n/', trim($q['options']))));
    $c = db()->prepare('SELECT value, COUNT(*) n FROM answers WHERE question_id=? AND value<>\'\' GROUP BY value');
    $c->execute([$id]);
    $cnt = []; $somma = 0;
    foreach ($c->fetchAll() as $r) { $cnt[$r['value']] = (int)$r['n']; $somma += (int)$r['n']; }
    arsort($cnt); ?>
    <div class="card qres"><div class="bd">
      <div class="qh"><b><?= e($q['label']) ?></b><span><?= $somma ?> rispost<?= $somma === 1 ? 'a' : 'e' ?><?= $q['published'] ? '' : ' · domanda spenta' ?></span></div>
      <?php if (!$somma): ?><p class="muted">Nessuna risposta.</p><?php endif; ?>
      <?php foreach ($opz as $o): $v = $cnt[$o] ?? 0; $pc = $somma ? round($v / $somma * 100) : 0; ?>
        <div class="brow">
          <span class="bl"><?= e($o) ?></span>
          <span class="bt"><i style="width:<?= $pc ?>%"></i></span>
          <span class="bv"><?= $pc ?>% <em><?= $v ?></em></span>
        </div>
      <?php endforeach; ?>
      <?php // risposte che non corrispondono più a nessuna opzione (opzione rinominata)
        foreach ($cnt as $k => $v):
          if (in_array($k, $opz, true)) continue;
          $pc = $somma ? round($v / $somma * 100) : 0; ?>
        <div class="brow">
          <span class="bl muted"><?= e($k) ?> <span style="font-size:11.5px">(opzione non più in elenco)</span></span>
          <span class="bt"><i style="width:<?= $pc ?>%;background:#3a4250"></i></span>
          <span class="bv"><?= $pc ?>% <em><?= $v ?></em></span>
        </div>
      <?php endforeach; ?>
    </div></div>

  <?php else:
    $t = db()->prepare("SELECT a.value, a.updated_at, c.id AS cid, c.code,
                               p.first_name, p.last_name
                        FROM answers a JOIN codes c ON c.id=a.code_id
                        LEFT JOIN profiles p ON p.code_id=c.id
                        WHERE a.question_id=? AND a.value<>'' ORDER BY a.updated_at DESC LIMIT 200");
    $t->execute([$id]); $risp = $t->fetchAll(); ?>
    <div class="card qres"><div class="bd">
      <div class="qh"><b><?= e($q['label']) ?></b><span><?= count($risp) ?> rispost<?= count($risp) === 1 ? 'a' : 'e' ?></span></div>
      <?php if (!$risp): ?><p class="muted">Nessuna risposta.</p><?php endif; ?>
      <?php foreach ($risp as $r): ?>
        <div class="quote">
          <p><?= nl2br(e($r['value'])) ?></p>
          <div class="who"><a href="cliente.php?id=<?= (int)$r['cid'] ?>"><?= e(trim($r['first_name'].' '.$r['last_name']) ?: $r['code']) ?></a>
            · <?= e(date('d/m/Y', strtotime($r['updated_at']))) ?></div>
        </div>
      <?php endforeach; ?>
    </div></div>
  <?php endif; endforeach; endif; ?>
<?php afoot();
