<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$rows = materiali_visibili((int)$code['id']);
$g = [];
foreach ($rows as $r) $g[$r['ctitle'] ?: 'Materiali generali'][] = $r;

head('Materiali'); topbar($code, '', 'materiali.php'); ?>
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
      <?php mat_list($items); ?>
    </section>
  <?php endforeach; ?>
</div>
<?php mbar('materiali.php'); foot();
