/* Upload a blocchi: niente limiti di upload_max_filesize, con barra di avanzamento. */
/* Chiede al server quanto può pesare un blocco: su Hostinger il limite
   di default è 2 MB, e un blocco più grande verrebbe scartato in silenzio. */
let CHUNK_SIZE = null;
async function maxChunk() {
  if (CHUNK_SIZE) return CHUNK_SIZE;
  try {
    const r = await fetch('upload.php?probe=1');
    const j = await r.json();
    CHUNK_SIZE = Math.max(262144, j.chunk || 1048576);
  } catch (e) { CHUNK_SIZE = 1048576; }
  return CHUNK_SIZE;
}

async function chunkUpload(file, kind, csrf, onProgress) {
  const CH = await maxChunk();
  const total = Math.max(1, Math.ceil(file.size / CH));
  const uid = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  let idx = 0;
  return await new Promise((resolve, reject) => {
    (function send() {
      if (idx >= total) return;
      const fd = new FormData();
      fd.append('csrf', csrf); fd.append('kind', kind); fd.append('uid', uid);
      fd.append('idx', idx); fd.append('total', total); fd.append('name', file.name);
      fd.append('chunk', file.slice(idx * CH, (idx + 1) * CH));
      fetch('upload.php', { method: 'POST', body: fd })
        .then(async r => {
          const t = await r.text();
          if (!t) throw new Error('il server ha risposto vuoto (HTTP ' + r.status +
                                  '): blocco troppo grande o tempo scaduto');
          try { return JSON.parse(t); }
          catch (e) { throw new Error('risposta inattesa dal server (HTTP ' + r.status + '): ' +
                                      t.replace(/<[^>]*>/g, ' ').trim().slice(0, 160)); }
        })
        .then(j => {
          if (j.error) return reject(new Error(j.error));
          idx++;
          onProgress && onProgress(Math.round(idx / total * 100));
          if (idx >= total) resolve(j); else send();
        })
        .catch(reject);
    })();
  });
}

/* Legge la durata dal file nel browser: il server non ha ffprobe
   e su hosting condiviso non può nemmeno lanciarlo. */
function readDuration(file) {
  return new Promise(resolve => {
    if (!/^video\//.test(file.type)) return resolve(0);
    const v = document.createElement('video');
    v.preload = 'metadata';
    const url = URL.createObjectURL(file);
    const done = s => { URL.revokeObjectURL(url); resolve(s); };
    v.onloadedmetadata = () => done(isFinite(v.duration) ? Math.round(v.duration) : 0);
    v.onerror = () => done(0);
    setTimeout(() => done(0), 8000);
    v.src = url;
  });
}

function wireDrop(dropId, inputId, kind, csrf, hidden, onDone) {
  const drop = document.getElementById(dropId), inp = document.getElementById(inputId);
  const bar = document.getElementById(dropId + '-bar');
  const fill = bar && bar.querySelector('i'), lab = bar && bar.querySelector('.lb b');
  drop.addEventListener('click', () => inp.click());
  ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => {
    e.preventDefault(); drop.classList.add('over');
  }));
  ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => {
    e.preventDefault(); drop.classList.remove('over');
  }));
  drop.addEventListener('drop', e => { if (e.dataTransfer.files[0]) go(e.dataTransfer.files[0]); });
  inp.addEventListener('change', () => { if (inp.files[0]) go(inp.files[0]); });

  async function go(file) {
    const t  = drop.querySelector('.t');
    const st = drop.querySelector('.s');
    const mb = file.size / 1048576;
    const fmt = n => n < 1024 ? n.toFixed(1) + ' MB' : (n / 1024).toFixed(2) + ' GB';

    // blocca il salvataggio finché il file non è arrivato tutto
    const form = drop.closest('form');
    const submit = form && form.querySelector('button[type=submit], button:not([type])');
    const submitLabel = submit ? submit.textContent : '';
    if (submit) { submit.disabled = true; submit.textContent = 'Attendi il caricamento…'; }
    window.onbeforeunload = () => 'Caricamento in corso: se esci ricominci da capo.';

    const clientSeconds = await readDuration(file);

    drop.classList.add('busy');
    bar && bar.classList.add('on');
    if (fill) { fill.style.width = '0%'; fill.style.background = ''; }
    lab && (lab.textContent = file.name);
    const sub = bar && bar.querySelector('.lb span');

    const t0 = Date.now();
    t.innerHTML = 'Caricamento <b class="pc">0%</b>';
    st.textContent = '0 di ' + fmt(mb) + ' — non chiudere la pagina';

    chunkUpload(file, kind, csrf, p => {
      if (fill) fill.style.width = p + '%';
      const pc = t.querySelector('.pc'); if (pc) pc.textContent = p + '%';
      const done = mb * p / 100;
      const el = (Date.now() - t0) / 1000;
      const speed = el > 2 ? done / el : 0;
      const left = speed > 0 ? Math.round((mb - done) / speed) : 0;
      st.textContent = fmt(done) + ' di ' + fmt(mb)
        + (left > 0 ? ' — mancano circa ' + (left > 90 ? Math.ceil(left / 60) + ' min' : left + ' s') : '');
      if (sub) sub.textContent = p + '%';
    })
      .then(j => {
        window.onbeforeunload = null;
        drop.classList.remove('busy');
        drop.classList.add('ok');
        if (fill) fill.style.width = '100%';
        t.innerHTML = '✓ Caricamento completato';
        st.innerHTML = '<b>' + file.name + '</b> · ' + fmt(mb)
                     + ' — ora premi <b>Crea lezione</b> per salvare';
        if (sub) sub.textContent = '100%';
        if (hidden) document.getElementById(hidden).value = j.file;
        if (clientSeconds && !j.seconds) j.seconds = clientSeconds;
        if (submit) { submit.disabled = false; submit.textContent = submitLabel; }
        onDone && onDone(j, file);
      })
      .catch(err => {
        window.onbeforeunload = null;
        drop.classList.remove('busy');
        drop.classList.add('ko');
        t.textContent = '✗ Caricamento non riuscito';
        st.textContent = err.message + ' — riprova';
        if (fill) fill.style.background = '#f87171';
        if (submit) { submit.disabled = false; submit.textContent = submitLabel; }
      });
  }
}
