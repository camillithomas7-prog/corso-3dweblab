<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();

$cats = db()->query("SELECT * FROM categories WHERE published=1 ORDER BY pos, id")->fetchAll();
$les  = db()->query("SELECT * FROM lessons WHERE published=1 ORDER BY pos, id")->fetchAll();
$pr   = [];
$q = db()->prepare('SELECT lesson_id, seconds, completed FROM progress WHERE code_id=?');
$q->execute([$code['id']]);
foreach ($q->fetchAll() as $r) $pr[(int)$r['lesson_id']] = $r;

$byCat = [];
foreach ($les as $l) $byCat[(int)$l['category_id']][] = $l;
$tot  = count($les);
$done = count(array_filter($pr, fn($p) => $p['completed']));
$pct  = $tot ? round($done / $tot * 100) : 0;
$next = null;
foreach ($cats as $c) foreach ($byCat[(int)$c['id']] ?? [] as $l)
    if (!$next && empty($pr[(int)$l['id']]['completed'])) $next = $l;

head('Il tuo corso'); topbar($code); ?>
<div class="wrap shell">
  <aside class="side">
    <div class="prog">
      <div class="t"><span>Avanzamento</span><b><?= $pct ?>%</b></div>
      <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
      <div class="t" style="margin:9px 0 0"><span><?= $done ?> di <?= $tot ?> lezioni completate</span></div>
    </div>
    <?php foreach ($cats as $i => $c): $ll = $byCat[(int)$c['id']] ?? []; if (!$ll) continue; ?>
    <details class="cat" <?= $i === 0 ? 'open' : '' ?>>
      <summary>
        <span class="n"><?= str_pad((string)($i+1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="tt"><b><?= e($c['title']) ?></b><span><?= count($ll) ?> lezioni</span></span>
        <svg class="ar" width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="#6c7790"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l4 4-4 4"/></svg>
      </summary>
      <ul>
        <?php foreach ($ll as $l): $ok = !empty($pr[(int)$l['id']]['completed']); ?>
        <li><a href="lezione.php?id=<?= (int)$l['id'] ?>">
          <span class="ic">
            <?php if ($ok): ?>
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="#4ade80" stroke-width="1.8"
                   stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="7.5" r="6.2"/><path d="M4.8 7.7l2 2 3.4-4"/></svg>
            <?php else: ?>
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="#4a5368" stroke-width="1.6"><circle cx="7.5" cy="7.5" r="6.2"/></svg>
            <?php endif; ?>
          </span>
          <span class="lt"><?= e($l['title']) ?></span>
          <?php if ((int)$l['duration_sec']): ?><span class="du"><?= hms((int)$l['duration_sec']) ?></span><?php endif; ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </details>
    <?php endforeach; ?>
  </aside>

  <main>
    <?php if ($next): ?>
      <div class="card" style="margin-bottom:20px;overflow:hidden">
        <div class="bd" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
          <div style="flex:1;min-width:200px">
            <div class="kicker" style="margin-bottom:7px"><?= $done ? 'Riprendi da qui' : 'Inizia da qui' ?></div>
            <h2 style="font-size:19px;line-height:1.3"><?= e($next['title']) ?></h2>
          </div>
          <a class="btn" href="lezione.php?id=<?= (int)$next['id'] ?>">
            <svg width="15" height="15" viewBox="0 0 15 15" fill="currentColor"><path d="M4 2.6l8 4.9-8 4.9z"/></svg>
            <?= $done ? 'Riprendi' : 'Guarda la prima lezione' ?>
          </a>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$tot): ?>
      <div class="card"><div class="bd">
        <h3 style="margin-bottom:8px">Il corso sta per arrivare</h3>
        <p class="muted">Non ci sono ancora lezioni pubblicate. Il tuo accesso è già attivo: appena carichiamo le prime lezioni le trovi qui.</p>
      </div></div>
    <?php endif; ?>

    <?php foreach ($cats as $c): $ll = $byCat[(int)$c['id']] ?? []; if (!$ll) continue; ?>
      <section style="margin-bottom:30px">
        <div style="margin-bottom:13px">
          <h2 style="font-size:19px"><?= e($c['title']) ?></h2>
          <?php if ($c['descr']): ?><p class="muted" style="font-size:14px;margin-top:5px"><?= e($c['descr']) ?></p><?php endif; ?>
        </div>
        <div class="grid c2 c3">
          <?php foreach ($ll as $l): $ok = !empty($pr[(int)$l['id']]['completed']); ?>
          <a class="lcard" href="lezione.php?id=<?= (int)$l['id'] ?>">
            <div class="th">
              <?php if ($l['poster']): ?><img src="media.php?t=p&id=<?= (int)$l['id'] ?>" alt="" loading="lazy"><?php endif; ?>
              <?php if ($ok): ?><span class="pill ok dn">Completata</span><?php endif; ?>
              <span class="pl"><svg width="15" height="15" viewBox="0 0 15 15" fill="#fff"><path d="M4.5 2.9l7.5 4.6-7.5 4.6z"/></svg></span>
            </div>
            <div class="bd2">
              <div class="kicker"><?= e($c['title']) ?><?= (int)$l['duration_sec'] ? ' · '.hms((int)$l['duration_sec']) : '' ?></div>
              <b><?= e($l['title']) ?></b>
              <?php if ($l['descr']): ?><p><?= e($l['descr']) ?></p><?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
</div>
<?php mbar('corso.php'); foot();
