<?php
require_once __DIR__ . '/inc/layout.php';
boot_session();
$code = current_code();
if (!$code) { header('Location: index.php'); exit; }
if (!profile_done((int)$code['id'])) { header('Location: profilo.php'); exit; }

$qs = db()->query('SELECT * FROM questions WHERE published=1 ORDER BY pos, id')->fetchAll();
if (!$qs) { header('Location: corso.php'); exit; }

$fatto = quiz_done((int)$code['id']);
$val = [];
$s = db()->prepare('SELECT question_id, value FROM answers WHERE code_id=?');
$s->execute([$code['id']]);
foreach ($s->fetchAll() as $r) $val[(int)$r['question_id']] = $r['value'];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($qs as $q) {
        $id = (int)$q['id'];
        $v  = trim((string)($_POST['q'][$id] ?? ''));
        $val[$id] = $v;
        if (!$q['required']) continue;
        if ($v === '') {
            $err[$id] = $q['type'] === 'single' ? 'Scegli una risposta.' : 'Questa risposta ci serve.';
        } elseif ($q['type'] === 'single') {
            $opz = array_map('trim', preg_split('/\r?\n/', trim($q['options'])));
            if (!in_array($v, $opz, true)) $err[$id] = 'Scegli una delle risposte elencate.';
        } elseif (mb_strlen($v) < 3) {
            $err[$id] = 'Scrivi qualcosa in più, anche solo una frase.';
        }
    }
    if (!$err) {
        $ins = db()->prepare("INSERT INTO answers(code_id,question_id,value,updated_at)
                              VALUES(?,?,?,datetime('now'))
                              ON CONFLICT(code_id,question_id) DO UPDATE SET
                                value=excluded.value, updated_at=datetime('now')");
        foreach ($qs as $q) $ins->execute([$code['id'], $q['id'], $val[(int)$q['id']] ?? '']);
        db()->prepare("UPDATE profiles SET quiz_at=COALESCE(quiz_at, datetime('now')) WHERE code_id=?")
            ->execute([$code['id']]);
        header('Location: ' . ($fatto ? 'questionario.php?ok=1' : 'corso.php')); exit;
    }
}

$n = 0;
$primo_err = 0;
if ($err) { $ks = array_keys($err); $primo_err = (int)$ks[0]; }

head('Questionario');
if ($fatto) topbar($code, '', ''); ?>
<div class="wrap qwrap" style="padding-bottom:60px">

  <?php if (!$fatto): ?><img class="qlogo" src="assets/img/logo-light.png" alt="3D WEB LAB"><?php endif; ?>

  <div class="qbar">
    <div class="qbt"><i id="qfill" style="width:0%"></i></div>
    <div class="qbl">
      <span id="qstep">Domanda 1 di <?= count($qs) ?></span>
      <span id="qleft"></span>
    </div>
  </div>

  <?php if (isset($_GET['ok'])): ?><div class="msg ok">Risposte aggiornate.</div><?php endif; ?>
  <?php if ($err): ?><div class="msg err">Mancano <?= count($err) ?> risposte: te le ho riaperte.</div><?php endif; ?>

  <form method="post" novalidate id="qform">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <?php foreach ($qs as $i => $q): $id = (int)$q['id']; $bad = isset($err[$id]); ?>
      <section class="slide" data-i="<?= $i ?>" data-id="<?= $id ?>"
               data-type="<?= e($q['type']) ?>" data-req="<?= (int)$q['required'] ?>" hidden>
        <?php if ($q['section']): ?><div class="qsec2"><?= e($q['section']) ?></div><?php endif; ?>
        <h2><?= e($q['label']) ?></h2>
        <?php if ($q['help']): ?><p class="qhelp"><?= e($q['help']) ?></p><?php endif; ?>

        <?php if ($q['type'] === 'single'):
          $opz = array_filter(array_map('trim', preg_split('/\r?\n/', trim($q['options'])))); ?>
          <div class="qopts">
            <?php foreach ($opz as $o): ?>
              <label class="qopt<?= ($val[$id] ?? '') === $o ? ' on' : '' ?>">
                <input type="radio" name="q[<?= $id ?>]" value="<?= e($o) ?>"
                       <?= ($val[$id] ?? '') === $o ? 'checked' : '' ?>>
                <span class="dot"></span><span class="tx"><?= e($o) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <textarea class="inp qta" name="q[<?= $id ?>]" rows="4"
                    placeholder="Scrivi con parole tue, anche poche righe…"><?= e($val[$id] ?? '') ?></textarea>
        <?php endif; ?>

        <div class="ferr sferr" <?= $bad ? '' : 'hidden' ?>><?= e($err[$id] ?? '') ?></div>
      </section>
    <?php endforeach; ?>

    <div class="qnav">
      <button type="button" class="btn gh" id="prev" hidden>Indietro</button>
      <button type="button" class="btn" id="next">Avanti</button>
      <button type="submit" class="btn" id="fine" hidden><?= $fatto ? 'Salva le modifiche' : 'Ho finito, entra nel corso' ?></button>
    </div>
    <div class="qdots" id="dots"></div>
  </form>
</div>

<script>
const S=[...document.querySelectorAll('.slide')], N=S.length;
const fill=document.getElementById('qfill'), step=document.getElementById('qstep'),
      left=document.getElementById('qleft'), prev=document.getElementById('prev'),
      next=document.getElementById('next'), fine=document.getElementById('fine'),
      dots=document.getElementById('dots');
let i=<?= $primo_err ?: 0 ?>;
<?php if ($primo_err): ?>i=S.findIndex(s=>+s.dataset.id===<?= $primo_err ?>); if(i<0) i=0;<?php endif; ?>

S.forEach((_,k)=>{ const d=document.createElement('span'); d.className='qdot'; dots.appendChild(d); });

function risposta(s){
  if(s.dataset.type==='single'){ const r=s.querySelector('input:checked'); return r?r.value:''; }
  return s.querySelector('textarea').value.trim();
}
function valida(s,mostra){
  const v=risposta(s), req=s.dataset.req==='1', box=s.querySelector('.sferr');
  let msg='';
  if(req && !v) msg = s.dataset.type==='single' ? 'Scegli una risposta.' : 'Questa risposta ci serve.';
  else if(req && s.dataset.type==='text' && v.length<3) msg='Scrivi qualcosa in più, anche solo una frase.';
  if(mostra){ box.textContent=msg; box.hidden=!msg; }
  return !msg;
}
function mostra(k){
  i=Math.max(0,Math.min(N-1,k));
  S.forEach((s,x)=>s.hidden = x!==i);
  const pc=Math.round((i)/N*100);
  fill.style.width=pc+'%';
  step.textContent='Domanda '+(i+1)+' di '+N;
  const m=N-i-1;
  left.textContent = m===0 ? 'ultima' : (m===1 ? 'ne manca 1' : 'ne mancano '+m);
  prev.hidden = i===0;
  next.hidden = i===N-1;
  fine.hidden = i!==N-1;
  [...dots.children].forEach((d,x)=>{
    d.className='qdot'+(x===i?' on':'')+(x<i||valida(S[x],false)&&x!==i?' ok':'');
  });
  window.scrollTo({top:0,behavior:'smooth'});
  const t=S[i].querySelector('textarea'); if(t) setTimeout(()=>t.focus(),120);
}
function avanti(){ if(!valida(S[i],true)) return; if(i<N-1) mostra(i+1); }
next.addEventListener('click',avanti);
prev.addEventListener('click',()=>mostra(i-1));

S.forEach(s=>{
  s.querySelectorAll('.qopt input').forEach(r=>{
    r.addEventListener('change',()=>{
      s.querySelectorAll('.qopt').forEach(l=>l.classList.remove('on'));
      r.closest('.qopt').classList.add('on');
      s.querySelector('.sferr').hidden=true;
      setTimeout(()=>{ if(i<N-1) mostra(i+1); else mostra(i); },260);
    });
  });
  const t=s.querySelector('textarea');
  if(t){
    t.addEventListener('input',()=>{ if(t.value.trim().length>=3) s.querySelector('.sferr').hidden=true; });
    t.addEventListener('keydown',e=>{ if(e.key==='Enter'&&(e.metaKey||e.ctrlKey)){ e.preventDefault(); avanti(); }});
  }
});
document.getElementById('qform').addEventListener('submit',e=>{
  const primo=S.findIndex(s=>!valida(s,false));
  if(primo>=0){ e.preventDefault(); mostra(primo); valida(S[primo],true); }
});
mostra(i);
</script>
<?php if ($fatto) mbar(''); foot();
