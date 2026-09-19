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
if (!modulo_permesso((int)$code['id'], $l['category_id'] === null ? null : (int)$l['category_id'])) {
    header('Location: corso.php?chiuso=1'); exit;
}

// sondaggio dentro il video: solo se attivo su questa lezione e non gia' compilato
$vq = null;
$q = db()->prepare("SELECT v.* FROM vquiz v
                    WHERE v.lesson_id=? AND v.published=1
                      AND NOT EXISTS (SELECT 1 FROM vq_done d WHERE d.quiz_id=v.id AND d.code_id=?)
                    LIMIT 1");
$q->execute([$id, (int)$code['id']]);
if ($vq = $q->fetch() ?: null) {
    $q = db()->prepare('SELECT * FROM vq_questions WHERE quiz_id=? ORDER BY pos, id');
    $q->execute([(int)$vq['id']]);
    $vq['domande'] = array_map(function (array $d) {
        $o = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$d['options']))));
        return ['id' => (int)$d['id'], 'type' => $d['type'], 'label' => $d['label'],
                'help' => $d['help'], 'options' => $o, 'altro' => (int)$d['altro'],
                'se_q' => (int)($d['show_if_q'] ?? 0), 'se_v' => (string)$d['show_if_v'],
                'obbl' => (int)$d['required']];
    }, $q->fetchAll());
    // su un video incorporato non posso mettere in pausa: il sondaggio non parte
    if ($l['video_type'] === 'embed' || !$l['video_src']) $vq = null;
}

$permessi = moduli_permessi((int)$code['id']);
$okc = fn($cat) => $permessi === null || in_array((int)$cat, $permessi, true);
$ord = array_values(array_filter(
    db()->query("SELECT l.id, l.title, l.category_id FROM lessons l
                 LEFT JOIN categories c ON c.id=l.category_id
                 WHERE l.published=1 AND (c.published=1 OR c.id IS NULL)
                 ORDER BY c.pos, c.id, l.pos, l.id")->fetchAll(),
    fn($x) => $okc($x['category_id'])));
$idx = array_search($id, array_column($ord, 'id'));
$prev = $idx > 0 ? $ord[$idx-1] : null;
$next = ($idx !== false && $idx < count($ord)-1) ? $ord[$idx+1] : null;

$s = db()->prepare('SELECT * FROM progress WHERE code_id=? AND lesson_id=?');
$s->execute([$code['id'], $id]);
$p = $s->fetch() ?: ['seconds'=>0,'completed'=>0];

$s = db()->prepare('SELECT * FROM materials WHERE lesson_id=? ORDER BY pos, id');
$s->execute([$id]);
$mats = $s->fetchAll();

$s = db()->prepare('SELECT body, updated_at FROM notes WHERE code_id=? AND lesson_id=?');
$s->execute([$code['id'], $id]);
$nota = $s->fetch() ?: ['body'=>'','updated_at'=>null];

$cats = db()->query("SELECT * FROM categories WHERE published=1 ORDER BY pos, id")->fetchAll();
$les  = db()->query("SELECT * FROM lessons WHERE published=1 ORDER BY pos, id")->fetchAll();
$les = array_values(array_filter($les, fn($x) => $okc($x['category_id'])));
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
    <?php foreach ($cats as $i=>$c): $ll = $byCat[(int)$c['id']] ?? [];
      if (!$okc($c['id']) || !$ll) continue;
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
               oncontextmenu="return false" poster="<?= e(poster_url($l)) ?>">
          <source src="media.php?t=v&id=<?= (int)$l['id'] ?>" type="video/mp4">
        </video>
      <?php elseif ($l['video_type']==='url' && $l['video_src']): ?>
        <video id="vid" controls playsinline preload="metadata" controlsList="nodownload"
               poster="<?= e(poster_url($l)) ?>">
          <source src="<?= e($l['video_src']) ?>" type="video/mp4">
        </video>
      <?php elseif ($l['video_type']==='embed' && $l['video_src']): ?>
        <iframe src="<?= e($l['video_src']) ?>" allow="autoplay; fullscreen; picture-in-picture"
                allowfullscreen loading="lazy"></iframe>
      <?php else: ?>
        <div class="none" style="background:url('<?= e(poster_url($l)) ?>') center/cover">
          <span style="background:rgba(6,7,12,.78);padding:12px 18px;border-radius:9px">
            Il video di questa lezione non è ancora disponibile.</span></div>
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

    <div class="card notes" style="margin-top:22px">
      <div class="hd">
        <h3>I tuoi appunti</h3>
        <div style="display:flex;align-items:center;gap:10px">
          <span class="sv" id="sv"><?= $nota['updated_at'] ? 'salvati alle '.e(date('H:i', strtotime($nota['updated_at']))) : '' ?></span>
          <button type="button" class="btn gh sm" id="stampa">Stampa</button>
        </div>
      </div>
      <div class="bd">
        <div class="ntools">
          <button type="button" class="btn gh sm" id="mark">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="5.6"/><path d="M7 4.2V7l1.9 1.1"/></svg>
            Segna il minuto
          </button>
          <span class="nhint">Scrivi liberamente. Si salva da solo mentre scrivi.</span>
        </div>
        <div class="jumps" id="jumps"></div>
        <textarea class="inp nta" id="nta" placeholder="Qui puoi annotare quello che ti serve ricordare di questa lezione…"><?= e($nota['body']) ?></textarea>
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

<?php if ($vq): ?>
<div class="vqw" id="vqw" hidden>
  <div class="vqbox" role="dialog" aria-modal="true" aria-labelledby="vqt">
    <div class="vqhead">
      <span class="vqk">Una pausa di un minuto</span>
      <h2 id="vqt"><?= e($vq['intro'] ?: 'Due domande e riprendi.') ?></h2>
    </div>
    <form id="vqf" class="vqbody">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="quiz" value="<?= (int)$vq['id'] ?>">
      <?php foreach ($vq['domande'] as $n => $d): ?>
        <div class="vqq" data-q="<?= $d['id'] ?>" data-se-q="<?= $d['se_q'] ?>"
             data-se-v="<?= e($d['se_v']) ?>" data-obbl="<?= $d['obbl'] ?>">
          <?php if ($d['help']): ?><p class="vqhelp"><?= e($d['help']) ?></p><?php endif; ?>
          <p class="vqlab"><span><?= $n + 1 ?></span><?= e($d['label']) ?></p>
          <?php if ($d['type'] === 'text'): ?>
            <textarea class="vqtxt" name="a[<?= $d['id'] ?>]" rows="3"
                      placeholder="Scrivi quello che ti viene" maxlength="2000"></textarea>
          <?php else: ?>
            <div class="vqopts">
              <?php foreach ($d['options'] as $o): ?>
                <label class="vqo"><input type="radio" name="a[<?= $d['id'] ?>]" value="<?= e($o) ?>">
                  <span><?= e($o) ?></span></label>
              <?php endforeach; ?>
              <?php if ($d['altro']): ?>
                <label class="vqo"><input type="radio" name="a[<?= $d['id'] ?>]" value="Altro">
                  <span>Altro</span></label>
                <input class="vqtxt vqaltro" type="text" placeholder="Dicci quale" maxlength="500" hidden>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="vqerr" id="vqerr" hidden></p>
      <button class="btn w" type="submit" id="vqgo">Invia e riprendi il video</button>
    </form>
    <div class="vqdone" id="vqdone" hidden>
      <p><?= e($vq['chiusura'] ?: 'Grazie. Ora puoi riprendere il video.') ?></p>
      <button class="btn w" type="button" id="vqclose">Riprendi</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
const LID=<?= (int)$id ?>;
const JUMP=parseInt(new URLSearchParams(location.search).get('t')||'0',10);
const START=JUMP>0?JUMP:<?= (int)$p['seconds'] ?>;
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

/* ── appunti ──────────────────────────────────────────── */
const nta=document.getElementById('nta'), sv=document.getElementById('sv'), jumps=document.getElementById('jumps');
const hhmm=s=>{s=Math.max(0,Math.round(s));const h=Math.floor(s/3600),m=Math.floor(s%3600/60),x=s%60;
  return h?`${h}:${String(m).padStart(2,'0')}:${String(x).padStart(2,'0')}`:`${m}:${String(x).padStart(2,'0')}`;};
const toSec=t=>t.split(':').reverse().reduce((a,p,i)=>a+parseInt(p,10)*Math.pow(60,i),0);

let tmr=null, last=nta.value;
function salva(){
  if(nta.value===last) return;
  last=nta.value; sv.textContent='salvataggio…'; sv.className='sv on';
  fetch('nota.php',{method:'POST',body:new URLSearchParams({id:LID,body:nta.value})})
    .then(r=>r.ok?r.text():Promise.reject())
    .then(t=>{sv.textContent='salvati alle '+t; sv.className='sv';})
    .catch(()=>{sv.textContent='salvataggio non riuscito'; sv.className='sv ko';});
}
nta.addEventListener('input',()=>{ clearTimeout(tmr); tmr=setTimeout(salva,1200); disegnaSalti(); });
nta.addEventListener('blur',salva);
window.addEventListener('beforeunload',()=>{ if(nta.value!==last)
  navigator.sendBeacon('nota.php',new URLSearchParams({id:LID,body:nta.value})); });

// inserisce il minuto corrente del video al punto in cui stai scrivendo
document.getElementById('mark').addEventListener('click',()=>{
  const t=v?hhmm(v.currentTime):'0:00';
  const p=nta.selectionStart, txt=nta.value;
  const pre=txt.slice(0,p), post=txt.slice(p);
  const ins=(pre && !pre.endsWith('\n')?'\n':'')+'['+t+'] ';
  nta.value=pre+ins+post;
  nta.focus(); nta.selectionStart=nta.selectionEnd=p+ins.length;
  disegnaSalti(); clearTimeout(tmr); tmr=setTimeout(salva,600);
});

// i minuti annotati diventano pulsanti che riportano il video a quel punto
function disegnaSalti(){
  const found=[...new Set((nta.value.match(/\[(\d{1,2}:)?\d{1,2}:\d{2}\]/g)||[]))];
  jumps.innerHTML='';
  if(!found.length || !v){ jumps.style.display='none'; return; }
  jumps.style.display='flex';
  found.forEach(f=>{
    const t=f.slice(1,-1), b=document.createElement('button');
    b.type='button'; b.className='jump'; b.textContent=t;
    b.title='Riporta il video a '+t;
    b.onclick=()=>{ v.currentTime=toSec(t); v.play().catch(()=>{}); window.scrollTo({top:0,behavior:'smooth'}); };
    jumps.appendChild(b);
  });
}
disegnaSalti();
document.getElementById('stampa').addEventListener('click',()=>window.print());
</script>

<?php if ($vq): ?>
<script>
(function () {
  var w = document.getElementById('vqw'), f = document.getElementById('vqf');
  var vd = document.getElementById('vid');
  if (!w || !f || !vd) return;
  var QUANDO = <?= (int)$vq['at_sec'] ?>, fatto = false, aperto = false;

  /* ── la diramazione: una domanda compare solo se la risposta indicata e' quella ── */
  function rami() {
    f.querySelectorAll('.vqq').forEach(function (q) {
      var seq = +q.dataset.seQ;
      if (!seq) return;
      var scelta = f.querySelector('input[name="a[' + seq + ']"]:checked');
      q.hidden = !scelta || scelta.value !== q.dataset.seV;
    });
  }
  /* la voce Altro apre il campo libero */
  function altro() {
    f.querySelectorAll('.vqaltro').forEach(function (inp) {
      var grp = inp.closest('.vqopts');
      var sc = grp.querySelector('input[type=radio]:checked');
      inp.hidden = !sc || sc.value !== 'Altro';
      if (inp.hidden) inp.value = '';
    });
  }
  f.addEventListener('change', function () { rami(); altro(); });
  rami(); altro();

  function apri() {
    if (fatto || aperto) return;
    aperto = true;
    try { vd.pause(); } catch (e) {}
    w.hidden = false;
    document.body.style.overflow = 'hidden';
    var r = f.querySelector('input[type=radio]');
    if (r) r.focus();
  }
  function chiudi() {
    aperto = false; fatto = true;
    w.hidden = true;
    document.body.style.overflow = '';
    vd.play().catch(function () {});
  }

  /* ── scatta al minuto, e non si aggira andando avanti ── */
  vd.addEventListener('timeupdate', function () { if (vd.currentTime >= QUANDO) apri(); });
  vd.addEventListener('seeking', function () {
    if (!fatto && vd.currentTime > QUANDO + 0.5) { vd.currentTime = QUANDO; apri(); }
  });
  vd.addEventListener('play', function () { if (aperto) vd.pause(); });

  /* ── non si chiude: niente Esc, niente clic fuori ── */
  w.addEventListener('click', function (e) { e.stopPropagation(); });
  document.addEventListener('keydown', function (e) {
    if (aperto && e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* ── invio ── */
  var err = document.getElementById('vqerr'), go = document.getElementById('vqgo');
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    err.hidden = true;

    var mancanti = [];
    f.querySelectorAll('.vqq').forEach(function (q) {
      if (q.hidden || q.dataset.obbl !== '1') return;
      var t = q.querySelector('.vqtxt:not(.vqaltro)');
      if (t) { if (!t.value.trim()) mancanti.push(q); return; }
      var sc = q.querySelector('input[type=radio]:checked');
      if (!sc) { mancanti.push(q); return; }
      var alt = q.querySelector('.vqaltro');
      if (sc.value === 'Altro' && alt && !alt.value.trim()) mancanti.push(q);
    });
    if (mancanti.length) {
      err.textContent = mancanti.length === 1
        ? 'Manca una risposta.' : 'Mancano ' + mancanti.length + ' risposte.';
      err.hidden = false;
      mancanti[0].scrollIntoView({block: 'center', behavior: 'smooth'});
      return;
    }

    var d = new FormData(f);
    /* la voce Altro viaggia col testo scritto accanto */
    f.querySelectorAll('.vqaltro').forEach(function (inp) {
      if (inp.hidden || !inp.value.trim()) return;
      var q = inp.closest('.vqq');
      d.set('a[' + q.dataset.q + ']', 'Altro: ' + inp.value.trim());
    });
    /* i rami non percorsi non si mandano */
    f.querySelectorAll('.vqq').forEach(function (q) { if (q.hidden) d.delete('a[' + q.dataset.q + ']'); });

    go.disabled = true; go.textContent = 'Invio…';
    fetch('vquiz.php', {method: 'POST', body: d, credentials: 'same-origin'})
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'errore');
        f.hidden = true;
        document.getElementById('vqdone').hidden = false;
      })
      .catch(function () {
        go.disabled = false; go.textContent = 'Invia e riprendi il video';
        err.textContent = 'Non sono riuscito a salvare. Controlla la connessione e riprova.';
        err.hidden = false;
      });
  });
  document.getElementById('vqclose').addEventListener('click', chiudi);
})();
</script>
<?php endif; ?>
<?php mbar('corso.php'); foot();
