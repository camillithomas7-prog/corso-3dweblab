<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$rows = db()->prepare("SELECT n.*, l.title AS ltitle, l.id AS lid, c.title AS ctitle
                       FROM notes n
                       JOIN lessons l ON l.id=n.lesson_id
                       LEFT JOIN categories c ON c.id=l.category_id
                       WHERE n.code_id=? AND n.body<>''
                       ORDER BY c.pos, c.id, l.pos, l.id");
$rows->execute([$code['id']]);
$rows = $rows->fetchAll();

/** Trasforma [12:34] in un collegamento che riporta il video a quel punto. */
function con_salti(string $t, int $lid): string {
    return preg_replace_callback('/\[((?:\d{1,2}:)?\d{1,2}:\d{2})\]/', function ($m) use ($lid) {
        $p = array_reverse(explode(':', $m[1]));
        $s = 0; foreach ($p as $i => $v) $s += (int)$v * (60 ** $i);
        return '<a class="ts" href="lezione.php?id=' . $lid . '&t=' . $s . '">' . e($m[1]) . '</a>';
    }, e($t));
}

head('I miei appunti'); topbar($code, '', 'note.php'); ?>
<div class="wrap" style="padding-top:24px;padding-bottom:90px">
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:20px">
    <div>
      <div class="kicker" style="margin-bottom:7px">Quaderno personale</div>
      <h1 style="font-size:clamp(21px,4vw,29px)">I miei appunti</h1>
      <p class="muted" style="margin-top:7px;font-size:14.5px">
        Tutto quello che hai annotato, lezione per lezione. I minuti sono cliccabili e riportano al punto esatto del video.</p>
    </div>
    <?php if ($rows): ?><button class="btn gh" onclick="window.print()">Stampa tutto</button><?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><div class="bd">
      <h3 style="margin-bottom:8px">Non hai ancora scritto niente</h3>
      <p class="muted">Sotto ogni video trovi il riquadro degli appunti: si salva da solo mentre scrivi,
        e con «Segna il minuto» puoi annotare il punto esatto a cui tornare.</p>
      <div style="margin-top:16px"><a class="btn" href="corso.php">Vai al corso</a></div>
    </div></div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div class="card nblock">
      <div class="hd">
        <div style="min-width:0">
          <div class="kicker"><?= e($r['ctitle'] ?: 'Lezione') ?></div>
          <h3 style="margin-top:4px"><?= e($r['ltitle']) ?></h3>
        </div>
        <a class="btn gh sm" href="lezione.php?id=<?= (int)$r['lid'] ?>">Rivedi</a>
      </div>
      <div class="bd">
        <div class="ntxt"><?= nl2br(con_salti($r['body'], (int)$r['lid'])) ?></div>
        <div class="nfoot">aggiornati il <?= e(date('d/m/Y \a\l\l\e H:i', strtotime($r['updated_at']))) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php mbar('note.php'); foot();
