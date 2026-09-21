<?php
require_once __DIR__ . '/inc/layout.php';
$code = require_code();
$s = db()->prepare('SELECT * FROM messages WHERE code_id=? ORDER BY id');
$s->execute([$code['id']]);
$msgs = $s->fetchAll();
db()->prepare("UPDATE messages SET read_user=1 WHERE code_id=? AND sender='admin'")->execute([$code['id']]);
$last = $msgs ? (int)end($msgs)['id'] : 0;
$p = profile((int)$code['id']);
$nome = $p ? trim($p['first_name']) : '';

/** «Oggi», «Ieri» o la data per esteso. */
function giorno(string $ts): string {
    $d = date('Y-m-d', strtotime($ts));
    if ($d === date('Y-m-d')) return 'Oggi';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'Ieri';
    return date('j/n/Y', strtotime($ts));
}

head('Supporto'); topbar($code, '', 'supporto.php'); ?>
<style>.foot{display:none}</style>

<div class="msgr">
  <header class="mh">
    <span class="av">
      <img src="assets/img/favicon.png" alt="">
      <i class="on"></i>
    </span>
    <span class="mt">
      <b>3D WEB LAB</b>
      <span>Supporto · di solito rispondiamo in giornata</span>
    </span>
  </header>

  <div class="mscroll" id="scroll">
    <?php if (!$msgs): ?>
      <div class="mempty" id="empty">
        <svg width="34" height="34" viewBox="0 0 30 30" fill="none" stroke="#3a4459" stroke-width="1.4"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M25 18a3 3 0 0 1-3 3H9l-5 4V8a3 3 0 0 1 3-3h15a3 3 0 0 1 3 3Z"/></svg>
        <b><?= $nome ? 'Ciao ' . e($nome) : 'Nessun messaggio' ?></b>
        <span>Sei bloccato su un passaggio, non ti torna qualcosa, hai un problema con l'accesso?
              Scrivici pure, leggiamo tutto.</span>
      </div>
    <?php else: $gprec = ''; ?>
      <?php foreach ($msgs as $m): $g = giorno($m['created_at']);
        if ($g !== $gprec): $gprec = $g; ?>
          <div class="mday" data-day="<?= e($g) ?>"><span><?= e($g) ?></span></div>
        <?php endif; ?>
        <div class="bub <?= $m['sender'] === 'utente' ? 'me' : 'them' ?>" data-id="<?= (int)$m['id'] ?>">
          <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
          <?= allegato_html($m) ?>
          <span class="tm"><?= e(date('H:i', strtotime($m['created_at']))) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form class="mbarr" id="form">
    <textarea id="txt" rows="1" placeholder="Scrivi un messaggio" maxlength="4000"
              autocomplete="off" autocapitalize="sentences"></textarea>
    <button class="msend" type="submit" aria-label="Invia" disabled>
      <svg width="19" height="19" viewBox="0 0 18 18" fill="none" stroke="currentColor"
           stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 2 8.5 9.5M16 2l-4.8 14-2.7-6.5L2 6.8 16 2Z"/></svg>
    </button>
  </form>
  <p class="merr" id="err" hidden></p>
</div>

<script src="assets/js/chat.js?v=<?= @filemtime(APP_ROOT.'/assets/js/chat.js') ?>"></script>
<script>
let CSRF=<?= json_encode(csrf()) ?>;
let last=<?= $last ?>, busy=false, oggi=<?= json_encode(date('j/n/Y')) ?>;
const sc=document.getElementById('scroll'), tx=document.getElementById('txt'),
      fm=document.getElementById('form'), sb=fm.querySelector('.msend'),
      em=document.getElementById('empty');
const giu=(liscio)=>sc.scrollTo({top:sc.scrollHeight,behavior:liscio?'smooth':'auto'});
giu();

function separatore(){
  const ult=[...sc.querySelectorAll('.mday')].pop();
  if(ult && ult.dataset.day==='Oggi') return;
  const d=document.createElement('div');
  d.className='mday'; d.dataset.day='Oggi';
  d.innerHTML='<span>Oggi</span>'; sc.appendChild(d);
}
function bolla(m){
  if(document.querySelector('[data-id="'+m.id+'"]')) return;
  if(em) em.remove();
  separatore();
  const d=document.createElement('div');
  d.className='bub '+(m.mine?'me':'them'); d.dataset.id=m.id;
  d.innerHTML=(m.body?'<p></p>':'')+'<span class="tm">'+m.at.slice(-5)+'</span>';
  if(m.body) d.querySelector('p').textContent=m.body;
  if(m.file) d.insertBefore(allegatoNodo(m.file, m.id, ''), d.querySelector('.tm'));
  sc.appendChild(d);
}
function leggi(){
  fetch('msg.php?since='+last).then(r=>r.json()).then(j=>{
    if(j.csrf) CSRF=j.csrf;                       // token sempre fresco
    if(!j.messages||!j.messages.length) return;
    const inFondo=sc.scrollHeight-sc.scrollTop-sc.clientHeight<80;
    j.messages.forEach(m=>{ bolla(m); last=Math.max(last,m.id); });
    if(inFondo) giu(true);
  }).catch(()=>{});
}
setInterval(leggi, 7000);
document.addEventListener('visibilitychange',()=>{ if(!document.hidden) leggi(); });

function altezza(){ tx.style.height='auto'; tx.style.height=Math.min(tx.scrollHeight,132)+'px'; }
tx.addEventListener('input',()=>{ altezza(); sb.disabled=!tx.value.trim(); });
tx.addEventListener('keydown',e=>{
  if(e.key==='Enter'&&!e.shiftKey&&window.matchMedia('(min-width:760px)').matches){
    e.preventDefault(); fm.requestSubmit();
  }
});
const err=document.getElementById('err');
function avviso(t){ err.textContent=t; err.hidden=!t; }

/** Manda il messaggio. Se il token e' scaduto lo rinfresca e riprova una volta. */
function invia(b, riprova){
  return fetch('msg.php',{method:'POST',body:new URLSearchParams({csrf:CSRF,body:b,since:last})})
    .then(r=>{
      if(r.status===419 && riprova){                // token vecchio: ne prendo uno nuovo
        return fetch('msg.php?since='+last).then(r2=>r2.json()).then(j2=>{
          if(!j2.csrf) throw new Error('sessione');
          CSRF=j2.csrf; return invia(b, false);
        });
      }
      if(r.status===419) throw new Error('sessione');   // anche il token nuovo e' rifiutato
      if(!r.ok) return r.json().catch(()=>({error:'invio non riuscito, riprova'}))
                               .then(j=>{ throw new Error(j.error||'invio non riuscito'); });
      return r.json();
    });
}
fm.addEventListener('submit',e=>{
  e.preventDefault();
  const b=tx.value.trim(); if(!b||busy) return;
  busy=true; sb.disabled=true; tx.value=''; altezza(); avviso('');
  invia(b, true)
    .then(j=>{ (j.messages||[]).forEach(m=>{ bolla(m); last=Math.max(last,m.id); }); giu(true); })
    .catch(x=>{
      tx.value=b; altezza(); sb.disabled=false;
      avviso(String(x.message)==='sessione'
        ? 'Sessione scaduta: ricarica la pagina e riprova.'
        : 'Messaggio non inviato: ' + x.message);
    })
    .finally(()=>{ busy=false; tx.focus(); });
});
</script>
<?php mbar('supporto.php'); foot();
