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
        .then(r => r.json())
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

  function go(file) {
    bar && bar.classList.add('on');
    const mb = (file.size / 1048576).toFixed(1);
    lab && (lab.textContent = file.name + ' · ' + mb + ' MB');
    fill && (fill.style.width = '0%');
    drop.querySelector('.t').textContent = 'Caricamento in corso…';
    chunkUpload(file, kind, csrf, p => { fill && (fill.style.width = p + '%'); })
      .then(j => {
        drop.querySelector('.t').textContent = '✓ ' + file.name;
        drop.querySelector('.s').textContent = 'Caricato. Salva per confermare.';
        if (hidden) document.getElementById(hidden).value = j.file;
        onDone && onDone(j, file);
      })
      .catch(err => {
        drop.querySelector('.t').textContent = 'Caricamento non riuscito';
        drop.querySelector('.s').textContent = err.message;
        fill && (fill.style.background = '#f87171');
      });
  }
}
