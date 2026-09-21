<?php
require_once __DIR__ . '/inc.php';
require_admin();

$cid = (int)($_GET['id'] ?? 0);

// elenco conversazioni: prima chi aspetta risposta, poi per ultimo messaggio
$conv = db()->query("
  SELECT c.id, c.code, c.order_ref, p.first_name, p.last_name,
         (SELECT COUNT(*) FROM messages m WHERE m.code_id=c.id) AS n,
         (SELECT COUNT(*) FROM messages m WHERE m.code_id=c.id AND m.sender='utente' AND m.read_admin=0) AS nuovi,
         (SELECT CASE WHEN m.body <> '' THEN m.body
                      WHEN m.file <> '' THEN 'PDF: '||m.file_name ELSE '' END
          FROM messages m WHERE m.code_id=c.id ORDER BY m.id DESC LIMIT 1) AS ultimo,
         (SELECT m.sender FROM messages m WHERE m.code_id=c.id ORDER BY m.id DESC LIMIT 1) AS chi,
         (SELECT m.created_at FROM messages m WHERE m.code_id=c.id ORDER BY m.id DESC LIMIT 1) AS quando
  FROM codes c LEFT JOIN profiles p ON p.code_id=c.id
  WHERE EXISTS (SELECT 1 FROM messages m WHERE m.code_id=c.id)
  ORDER BY nuovi DESC, quando DESC")->fetchAll();

// tutti i corsisti attivi: serve per iniziare una conversazione con chi non ha mai scritto
$tutti = db()->query("SELECT c.id, c.code, p.first_name, p.last_name
                      FROM codes c LEFT JOIN profiles p ON p.code_id=c.id
                      WHERE c.status='active'
                      ORDER BY COALESCE(NULLIF(TRIM(p.first_name||' '||p.last_name),''), c.code)")->fetchAll();

$att = null; $msgs = []; $last = 0;
if ($cid) {
    $s = db()->prepare('SELECT c.*, p.first_name, p.last_name, p.email, p.phone_cc, p.phone
                        FROM codes c LEFT JOIN profiles p ON p.code_id=c.id WHERE c.id=?');
    $s->execute([$cid]); $att = $s->fetch();
    if ($att) {
        $s = db()->prepare('SELECT * FROM messages WHERE code_id=? ORDER BY id');
        $s->execute([$cid]); $msgs = $s->fetchAll();
        db()->prepare("UPDATE messages SET read_admin=1 WHERE code_id=? AND sender='utente'")->execute([$cid]);
        $last = $msgs ? (int)end($msgs)['id'] : 0;
    }
}
$nome = fn(array $r) => trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')) ?: $r['code'];

ahead('Supporto', 'chat.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Supporto</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Le conversazioni con i corsisti. In cima chi aspetta una risposta.</p>

<div class="cgrid">
  <aside class="clist">
    <?php if ($tutti): ?>
      <form class="cnew" method="get">
        <label for="dest">Scrivi a un corsista</label>
        <div class="cnew-r">
          <select class="inp" name="id" id="dest">
            <?php foreach ($tutti as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id']===$cid ? 'selected' : '' ?>>
                <?= e($nome($t)) ?><?= trim(($t['first_name'] ?? '').($t['last_name'] ?? '')) !== '' ? ' · '.e($t['code']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn sm">Apri</button>
        </div>
      </form>
    <?php endif; ?>
    <?php if (!$conv): ?>
      <div class="card"><div class="bd"><p class="muted">Nessuna conversazione ancora aperta.
        Scegli un corsista qui sopra e scrivigli per primo.</p></div></div>
    <?php endif; ?>
    <?php foreach ($conv as $c): ?>
      <a class="citem <?= (int)$c['id']===$cid ? 'on' : '' ?>" href="chat.php?id=<?= (int)$c['id'] ?>">
        <div class="ct">
          <b><?= e($nome($c)) ?></b>
          <?php if ((int)$c['nuovi']): ?><span class="cbadge"><?= (int)$c['nuovi'] ?></span><?php endif; ?>
        </div>
        <p><?= $c['chi']==='admin' ? '<span class="muted">Tu: </span>' : '' ?><?= e(mb_substr((string)$c['ultimo'], 0, 62)) ?></p>
        <span class="cw"><?= e(date('d/m H:i', strtotime((string)$c['quando']))) ?></span>
      </a>
    <?php endforeach; ?>
  </aside>

  <section>
  <?php if (!$att): ?>
    <div class="card" style="height:100%;display:grid;place-items:center;min-height:280px">
      <p class="muted" style="text-align:center;padding:30px">Scegli una conversazione a sinistra.</p></div>
  <?php else: ?>
    <div class="card" style="padding:15px 18px;margin-bottom:12px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center">
      <div>
        <b style="font-size:16.5px"><?= e($nome($att)) ?></b>
        <div class="muted" style="font-size:12.5px">
          <span class="codecell"><?= e($att['code']) ?></span>
          <?= $att['phone'] ? ' · '.e($att['phone_cc'].' '.$att['phone']) : '' ?>
          <?= $att['email'] ? ' · '.e($att['email']) : '' ?></div>
      </div>
      <a class="btn gh sm" href="cliente.php?id=<?= (int)$att['id'] ?>">Scheda cliente</a>
    </div>

    <div class="chat">
      <div class="cscroll" id="scroll">
        <?php if (!$msgs): ?>
          <p class="cvuota">Nessun messaggio con <?= e($nome($att)) ?>.<br>
            Quello che scrivi qui gli compare nella sua area Supporto, con la campanella.</p>
        <?php endif; ?>
        <?php foreach ($msgs as $m): ?>
          <div class="bub <?= $m['sender']==='admin' ? 'me' : 'them' ?>" data-id="<?= (int)$m['id'] ?>">
            <?php if ($m['sender']!=='admin'): ?><span class="nm"><?= e($nome($att)) ?></span><?php endif; ?>
            <?php if ($m['body'] !== ''): ?><p><?= nl2br(e($m['body'])) ?></p><?php endif; ?>
            <?= allegato_html($m, '../') ?>
            <span class="tm"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <form class="cbar" id="form">
        <input type="file" id="pdf" accept="application/pdf,.pdf" hidden>
        <button class="cclip" type="button" id="clip" aria-label="Allega un PDF" title="Allega un PDF">
          <svg width="19" height="19" viewBox="0 0 20 20" fill="none" stroke="currentColor"
               stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15.5 9.2 9.8 14.9a3.3 3.3 0 0 1-4.7-4.7l6.2-6.2a2.2 2.2 0 0 1 3.1 3.1l-6.2 6.2a1.1 1.1 0 0 1-1.6-1.6l5.4-5.4"/>
          </svg>
        </button>
        <textarea id="txt" rows="1" placeholder="<?= $msgs ? 'Rispondi…' : 'Scrivi il primo messaggio…' ?>" maxlength="4000"></textarea>
        <button class="csend" type="submit" aria-label="Invia">
          <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor"
               stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 2 8.5 9.5M16 2l-4.8 14-2.7-6.5L2 6.8 16 2Z"/></svg>
        </button>
      </form>
    </div>
    <div class="cpick" id="pick" hidden>
      <span class="pn" id="pickn"></span>
      <button type="button" class="px" id="pickx" aria-label="Togli il file">&times;</button>
    </div>
    <p class="cnote" id="cnote">Invio con <b>Invio</b>, a capo con <b>Maiusc + Invio</b>.
      Con la graffetta mandi un PDF (max <?= e(peso(chat_max_bytes())) ?>).</p>

    <script src="../assets/js/chat.js?v=<?= @filemtime(APP_ROOT.'/assets/js/chat.js') ?>"></script>
    <script>
    const CSRF=<?= json_encode(csrf()) ?>, CID=<?= (int)$cid ?>;
    let last=<?= $last ?>, busy=false;
    const sc=document.getElementById('scroll'), tx=document.getElementById('txt'), fm=document.getElementById('form');
    const giu=()=>sc.scrollTop=sc.scrollHeight; giu();
    const CHI=<?= json_encode($nome($att)) ?>;
    function bolla(m){
      if(document.querySelector('[data-id="'+m.id+'"]')) return;
      const d=document.createElement('div');
      d.className='bub '+(m.mine?'me':'them'); d.dataset.id=m.id;
      d.innerHTML=(m.mine?'':'<span class="nm"></span>')+(m.body?'<p></p>':'')+'<span class="tm">'+m.at+'</span>';
      if(!m.mine) d.querySelector('.nm').textContent=CHI;
      if(m.body) d.querySelector('p').textContent=m.body;
      if(m.file) d.insertBefore(allegatoNodo(m.file, m.id, '../'), d.querySelector('.tm'));
      sc.appendChild(d);
    }
    function leggi(){
      fetch('../msg.php?code='+CID+'&since='+last).then(r=>r.json()).then(j=>{
        if(!j.messages) return;
        const era=sc.scrollHeight-sc.scrollTop-sc.clientHeight<60;
        j.messages.forEach(m=>{ bolla(m); last=Math.max(last,m.id); });
        if(j.messages.length && era) giu();
      }).catch(()=>{});
    }
    setInterval(leggi, 8000);
    tx.addEventListener('input',()=>{ tx.style.height='auto'; tx.style.height=Math.min(tx.scrollHeight,150)+'px'; });
    tx.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); fm.requestSubmit(); }});
    // allegato: la graffetta apre il selettore, il file resta in attesa finche' non invii
    const inp=document.getElementById('pdf'), pick=document.getElementById('pick'),
          pickn=document.getElementById('pickn'), nota=document.getElementById('cnote');
    const LIMITE=<?= chat_max_bytes() ?>;
    function mostraPick(){
      const f=inp.files[0];
      pick.hidden=!f;
      if(f) pickn.textContent='PDF pronto da inviare: '+f.name;
    }
    const NOTA=nota.innerHTML;
    function errore(t){ nota.textContent=t; nota.classList.add('ko'); }
    function pulisciErrore(){ nota.classList.remove('ko'); nota.innerHTML=NOTA; }
    document.getElementById('clip').addEventListener('click',()=>inp.click());
    document.getElementById('pickx').addEventListener('click',()=>{ inp.value=''; mostraPick(); });
    inp.addEventListener('change',()=>{
      const f=inp.files[0];
      if(f && !/\.pdf$/i.test(f.name)){ inp.value=''; errore('Si possono allegare solo file PDF.'); return mostraPick(); }
      if(f && f.size>LIMITE){ inp.value=''; errore('Il PDF e\' troppo grande: il limite e\' <?= e(peso(chat_max_bytes())) ?>.'); return mostraPick(); }
      pulisciErrore(); mostraPick();
    });

    fm.addEventListener('submit',e=>{
      e.preventDefault();
      const b=tx.value.trim(), f=inp.files[0];
      if((!b&&!f)||busy) return;
      busy=true; tx.value=''; tx.style.height='auto';
      const dati=new FormData();
      dati.append('csrf',CSRF); dati.append('code',CID); dati.append('body',b); dati.append('since',last);
      if(f) dati.append('file',f);
      inp.value=''; mostraPick(); pulisciErrore();
      fetch('../msg.php',{method:'POST',body:dati})
        .then(r=>r.json())
        .then(j=>{
          if(j.error){ errore(j.error); tx.value=b; return; }
          (j.messages||[]).forEach(m=>{ bolla(m); last=Math.max(last,m.id); }); giu();
        })
        .catch(()=>{ tx.value=b; errore('Invio non riuscito, riprova.'); })
        .finally(()=>{ busy=false; tx.focus(); });
    });
    </script>
  <?php endif; ?>
  </section>
</div>
<?php afoot();
