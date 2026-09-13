<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$rows = db()->query("SELECT m.*, l.title AS ltitle, c.title AS ctitle
                     FROM materials m
                     LEFT JOIN lessons l    ON l.id=m.lesson_id
                     LEFT JOIN categories c ON c.id=COALESCE(m.category_id, l.category_id)
                     ORDER BY c.pos, c.id, l.pos, l.id, m.pos, m.id")->fetchAll();
$g = [];
foreach ($rows as $r) $g[$r['ctitle'] ?: 'Materiali generali'][] = $r;

head('Materiali'); topbar($code); ?>
<div class="wrap" style="padding-top:24px;padding-bottom:90px">
  <div style="margin-bottom:20px">
    <div class="kicker" style="margin-bottom:7px">Area download</div>
    <h1 style="font-size:clamp(21px,4vw,29px)">Materiali del corso</h1>
    <p class="muted" style="margin-top:7px;font-size:14.5px">Tutti i PDF, i modelli e le risorse allegate alle lezioni, in un posto solo.</p>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><div class="bd">
      <h3 style="margin-bottom:8px">Ancora nessun materiale</h3>
      <p class="muted">Quando alleghiamo PDF e risorse alle lezioni li trovi qui.</p>
    </div></div>
  <?php endif; ?>

  <?php foreach ($g as $cat => $items): ?>
    <section style="margin-bottom:26px">
      <h2 style="font-size:17px;margin-bottom:12px"><?= e((string)$cat) ?></h2>
      <div class="mats">
        <?php foreach ($items as $m): ?>
          <a class="mat" href="media.php?t=m&id=<?= (int)$m['id'] ?>" target="_blank" rel="noopener">
            <span class="pi"><svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#f0a6d8"
              stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4.5 1.5h6l4 4v10a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z"/><path d="M10.5 1.5v4h4"/></svg></span>
            <span class="tx"><b><?= e($m['title']) ?></b>
              <span><?= $m['ltitle'] ? e($m['ltitle']).' · ' : '' ?>PDF · <?= human_bytes((int)$m['bytes']) ?></span></span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#6c7790" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v9M4.5 7.5L8 11l3.5-3.5M3 13.5h10"/></svg>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<?php mbar('materiali.php'); foot();
