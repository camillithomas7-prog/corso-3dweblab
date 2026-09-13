<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$id = (int)($_GET['id'] ?? 0);

$s = db()->prepare('SELECT l.*, c.title AS cat FROM lessons l
                    LEFT JOIN categories c ON c.id=l.category_id
                    WHERE l.id=? AND l.published=1');
$s->execute([$id]);
$l = $s->fetch();
if (!$l) { header('Location: corso.php'); exit; }

$ord = db()->query("SELECT l.id, l.title FROM lessons l
                    LEFT JOIN categories c ON c.id=l.category_id
                    WHERE l.published=1 AND (c.published=1 OR c.id IS NULL)
                    ORDER BY c.pos, c.id, l.pos, l.id")->fetchAll();
$idx = array_search($id, array_column($ord, 'id'));
$prev = $idx > 0 ? $ord[$idx-1] : null;
$next = ($idx !== false && $idx < count($ord)-1) ? $ord[$idx+1] : null;

$s = db()->prepare('SELECT * FROM progress WHERE code_id=? AND lesson_id=?');
$s->execute([$code['id'], $id]);
$p = $s->fetch() ?: ['seconds'=>0,'completed'=>0];

$s = db()->prepare('SELECT * FROM materials WHERE lesson_id=? ORDER BY pos, id');
$s->execute([$id]);
$mats = $s->fetchAll();

$cats = db()->query("SELECT * FROM categories WHERE published=1 ORDER BY pos, id")->fetchAll();
$les  = db()->query("SELECT * FROM lessons WHERE published=1 ORDER BY pos, id")->fetchAll();
$byCat = []; foreach ($les as $x) $byCat[(int)$x['category_id']][] = $x;
$pr = []; $q = db()->prepare('SELECT lesson_id, completed FROM progress WHERE code_id=?');
$q->execute([$code['id']]); foreach ($q->fetchAll() as $r) $pr[(int)$r['lesson_id']] = $r;
$tot = count($les); $done = count(array_filter($pr, fn($x)=>$x['completed']));
$pct = $tot ? round($done/$tot*100) : 0;

head($l['title']); topbar($code, '', 'corso.php'); ?>
<div class="wrap shell">
  <aside class="side">
    <div class="prog">
      <div class="t"><span>Avanzamento</span><b><?= $pct ?>%</b></div>
      <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
    </div>
    <?php foreach ($cats as $i=>$c): $ll = $byCat[(int)$c['id']] ?? []; if(!$ll) continue;
      $here = in_array($id, array_map(fn($x)=>(int)$x['id'], $ll), true); ?>
    <details class="cat" <?= $here?'open':'' ?>>
      <summary>
        <span class="n"><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span>
        <span class="tt"><b><?= e($c['title']) ?></b><span><?= count($ll) ?> lezioni</span></span>
        <svg class="ar" width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="#6c7790"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l4 4-4 4"/></svg>
      </summary>
      <ul><?php foreach ($ll as $x): $ok = !empty($pr[(int)$x['id']]['completed']); ?>
        <li><a href="lezione.php?id=<?= (int)$x['id'] ?>" class="<?= (int)$x['id']===$id?'on':'' ?>">
          <span class="ic"><?php if($ok): ?>
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="#4ade80" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="7.5" r="6.2"/><path d="M4.8 7.7l2 2 3.4-4"/></svg>
          <?php else: ?>
            <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="#4a5368" stroke-width="1.6"><circle cx="7.5" cy="7.5" r="6.2"/></svg>
          <?php endif; ?></span>
          <span class="lt"><?= e($x['title']) ?></span>
        </a></li>
      <?php endforeach; ?></ul>
    </details>
    <?php endforeach; ?>
  </aside>

  <main>
    <div class="player">
      <?php if ($l['video_type']==='file' && $l['video_src']): ?>
        <video id="vid" controls playsinline preload="metadata" controlsList="nodownload"
               oncontextmenu="return false"
               <?= $l['poster'] ? 'poster="media.php?t=p&id='.(int)$l['id'].'"' : '' ?>>
          <source src="media.php?t=v&id=<?= (int)$l['id'] ?>" type="video/mp4">
        </video>
      <?php elseif ($l['video_type']==='url' && $l['video_src']): ?>
        <video id="vid" controls playsinline preload="metadata" controlsList="nodownload">
          <source src="<?= e($l['video_src']) ?>" type="video/mp4">
        </video>
      <?php elseif ($l['video_type']==='embed' && $l['video_src']): ?>
        <iframe src="<?= e($l['video_src']) ?>" allow="autoplay; fullscreen; picture-in-picture"
                allowfullscreen loading="lazy"></iframe>
      <?php else: ?>
        <div class="none">Il video di questa lezione non è ancora disponibile.</div>
      <?php endif; ?>
    </div>

    <div class="lhead">
      <div class="kicker"><?= e($l['cat'] ?? 'Lezione') ?></div>
      <h1><?= e($l['title']) ?></h1>
      <div class="meta">
        <?php if ((int)$l['duration_sec']): ?><span><?= hms((int)$l['duration_sec']) ?></span><?php endif; ?>
        <span id="stlab" class="<?= $p['completed'] ? 'pill ok' : '' ?>"><?= $p['completed'] ? 'Completata' : '' ?></span>
      </div>
      <?php if ($l['descr']): ?><div class="ldesc"><?= e($l['descr']) ?></div><?php endif; ?>
      <div style="margin-top:16px">
        <button class="btn <?= $p['completed'] ? 'gh' : '' ?>" id="mk" data-on="<?= (int)$p['completed'] ?>">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"><path d="M3.2 7.9l3 3 5.6-6.4"/></svg>
          <span><?= $p['completed'] ? 'Segna come da rivedere' : 'Segna come completata' ?></span>
        </button>
      </div>
    </div>

    <?php if ($mats): ?>
    <div class="card" style="margin-top:22px">
      <div class="hd"><h3>Materiali della lezione</h3><span class="pill"><?= count($mats) ?> file</span></div>
      <div class="bd"><div class="mats">
        <?php foreach ($mats as $m): ?>
          <a class="mat" href="media.php?t=m&id=<?= (int)$m['id'] ?>" target="_blank" rel="noopener">
            <span class="pi"><svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#f0a6d8"
              stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4.5 1.5h6l4 4v10a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z"/><path d="M10.5 1.5v4h4"/></svg></span>
            <span class="tx"><b><?= e($m['title']) ?></b><span>PDF · <?= human_bytes((int)$m['bytes']) ?></span></span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="#6c7790" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v9M4.5 7.5L8 11l3.5-3.5M3 13.5h10"/></svg>
          </a>
        <?php endforeach; ?>
      </div></div>
    </div>
    <?php endif; ?>

    <div class="nav2">
      <?php if ($prev): ?><a href="lezione.php?id=<?= (int)$prev['id'] ?>">
        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#6c7790" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round"><path d="M10 4l-4 4.5 4 4.5"/></svg>
        <span style="flex:1"><span>Precedente</span><b><?= e($prev['title']) ?></b></span></a>
      <?php else: ?><span></span><?php endif; ?>
      <?php if ($next): ?><a class="nx" href="lezione.php?id=<?= (int)$next['id'] ?>">
        <span style="flex:1"><span>Successiva</span><b><?= e($next['title']) ?></b></span>
        <svg width="17" height="17" viewBox="0 0 17 17" fill="none" stroke="#6ee7ff" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round"><path d="M7 4l4 4.5-4 4.5"/></svg></a>
      <?php endif; ?>
    </div>
  </main>
</div>
<script>
const LID=<?= (int)$id ?>, START=<?= (int)$p['seconds'] ?>;
const v=document.getElementById('vid'), mk=document.getElementById('mk'), stl=document.getElementById('stlab');
function save(sec,done){
  const b=new URLSearchParams({id:LID,seconds:Math.round(sec||0)});
  if(done!==undefined) b.set('completed',done?1:0);
  fetch('progresso.php',{method:'POST',body:b,headers:{'X-Requested-With':'fetch'}}).catch(()=>{});
}
if(v){
  v.addEventListener('loadedmetadata',()=>{ if(START>5 && START < v.duration-15) v.currentTime=START; });
  let t=0;
  v.addEventListener('timeupdate',()=>{ const n=Date.now(); if(n-t>10000){t=n; save(v.currentTime);} });
  v.addEventListener('pause',()=>save(v.currentTime));
  v.addEventListener('ended',()=>{ save(v.currentTime,true); setDone(true); });
  window.addEventListener('beforeunload',()=>save(v.currentTime));
}
function setDone(on){
  mk.dataset.on=on?1:0;
  mk.classList.toggle('gh',on);
  mk.querySelector('span').textContent=on?'Segna come da rivedere':'Segna come completata';
  stl.className=on?'pill ok':''; stl.textContent=on?'Completata':'';
}
mk.addEventListener('click',()=>{ const on=mk.dataset.on==='1'; setDone(!on); save(v?v.currentTime:0,!on); });
</script>
<?php mbar('corso.php'); foot();
