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

head('Supporto'); topbar($code, '', 'supporto.php'); ?>
<div class="wrap chatwrap">
  <div class="chead">
    <div class="kicker" style="margin-bottom:8px">Supporto</div>
    <h1>Scrivici pure</h1>
    <p>Sei bloccato su qualcosa, non ti torna un passaggio, hai un problema con l'accesso.
       Scrivi qui: rispondiamo appena possibile, di solito in giornata.</p>
  </div>

  <div class="chat" id="chat">
    <div class="cscroll" id="scroll">
      <div class="cempty" id="empty" <?= $msgs ? 'hidden' : '' ?>>
        <svg width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="#3a4459" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M25 18a3 3 0 0 1-3 3H9l-5 4V8a3 3 0 0 1 3-3h15a3 3 0 0 1 3 3Z"/></svg>
        <b><?= $nome ? 'Ciao ' . e($nome) . ',' : 'Nessun messaggio' ?></b>
        <span>non hai ancora scritto niente. Comincia quando vuoi.</span>
      </div>
      <?php foreach ($msgs as $m): ?>
        <div class="bub <?= $m['sender'] === 'utente' ? 'me' : 'them' ?>" data-id="<?= (int)$m['id'] ?>">
          <?php if ($m['sender'] !== 'utente'): ?><span class="nm">3D WEB LAB</span><?php endif; ?>
          <p><?= nl2br(e($m['body'])) ?></p>
          <span class="tm"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <form class="cbar" id="form">
      <textarea id="txt" rows="1" placeholder="Scrivi un messaggio…" maxlength="4000"></textarea>
      <button class="csend" type="submit" aria-label="Invia">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 2 8.5 9.5M16 2l-4.8 14-2.7-6.5L2 6.8 16 2Z"/></svg>
      </button>
    </form>
  </div>
  <p class="cnote">Invio con <b>Invio</b>, a capo con <b>Maiusc + Invio</b>.</p>
</div>

<script>
const CSRF=<?= json_encode(csrf()) ?>;
let last=<?= $last ?>, busy=false;
const sc=document.getElementById('scroll'), tx=document.getElementById('txt'),
      fm=document.getElementById('form'), em=document.getElementById('empty');
const giu=()=>sc.scrollTop=sc.scrollHeight;
giu();

function bolla(m){
  if(document.querySelector('[data-id="'+m.id+'"]')) return;
  const d=document.createElement('div');
  d.className='bub '+(m.mine?'me':'them'); d.dataset.id=m.id;
  d.innerHTML=(m.mine?'':'<span class="nm">3D WEB LAB</span>')
    +'<p></p><span class="tm">'+m.at+'</span>';
  d.querySelector('p').textContent=m.body;
  sc.appendChild(d); em.hidden=true;
}
function leggi(){
  fetch('msg.php?since='+last).then(r=>r.json()).then(j=>{
    if(!j.messages) return;
    const era=sc.scrollHeight-sc.scrollTop-sc.clientHeight<60;
    j.messages.forEach(m=>{ bolla(m); last=Math.max(last,m.id); });
    if(j.messages.length && era) giu();
  }).catch(()=>{});
}
setInterval(leggi, 8000);

tx.addEventListener('input',()=>{ tx.style.height='auto'; tx.style.height=Math.min(tx.scrollHeight,150)+'px'; });
tx.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); fm.requestSubmit(); }});
fm.addEventListener('submit',e=>{
  e.preventDefault();
  const b=tx.value.trim(); if(!b||busy) return;
  busy=true; tx.value=''; tx.style.height='auto';
  fetch('msg.php',{method:'POST',body:new URLSearchParams({csrf:CSRF,body:b,since:last})})
    .then(r=>r.json())
    .then(j=>{ (j.messages||[]).forEach(m=>{ bolla(m); last=Math.max(last,m.id); }); giu(); })
    .catch(()=>{ tx.value=b; })
    .finally(()=>{ busy=false; tx.focus(); });
});
</script>
<?php mbar('supporto.php'); foot();
