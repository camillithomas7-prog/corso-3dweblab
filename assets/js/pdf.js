/* Apre i PDF dentro la pagina invece che in una scheda nuova.
   Su iPhone e iPad Safari non sa mostrare un PDF dentro un iframe:
   lì si ricade sulla scheda esterna, che è l'unica cosa che funziona. */
(function () {
  const iOS = /iP(hone|ad|od)/.test(navigator.userAgent) ||
              (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (iOS) return;

  const box = document.createElement('div');
  box.className = 'pv';
  box.innerHTML =
    '<div class="pvh">' +
      '<svg width="18" height="18" viewBox="0 0 17 17" fill="none" stroke="#f0a6d8" stroke-width="1.5" ' +
        'stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M4.5 1.5h6l4 4v10a1 1 0 0 1-1 1h-9a1 1 0 0 1-1-1v-13a1 1 0 0 1 1-1Z"/><path d="M10.5 1.5v4h4"/></svg>' +
      '<b></b>' +
      '<a class="btn gh sm" target="_blank" rel="noopener" data-new>Scheda nuova</a>' +
      '<a class="btn gh sm" download data-dl>Scarica</a>' +
      '<button class="pvx" aria-label="Chiudi">' +
        '<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.8" ' +
          'stroke-linecap="round"><path d="M4 4l7 7M11 4l-7 7"/></svg></button>' +
    '</div><div class="pvb"><iframe title="Documento"></iframe></div>';
  document.addEventListener('DOMContentLoaded', () => document.body.appendChild(box));

  const fr = () => box.querySelector('iframe');
  function apri(url, titolo) {
    box.querySelector('b').textContent = titolo || 'Documento';
    box.querySelector('[data-new]').href = url;
    box.querySelector('[data-dl]').href = url;
    fr().src = url + (url.includes('?') ? '&' : '?') + 'v=' + Date.now() + '#view=FitH';
    box.classList.add('on');
    document.body.classList.add('noscroll');
  }
  function chiudi() {
    box.classList.remove('on');
    document.body.classList.remove('noscroll');
    fr().src = 'about:blank';
  }
  box.addEventListener('click', e => { if (e.target === box) chiudi(); });
  box.querySelector('.pvx').addEventListener('click', chiudi);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && box.classList.contains('on')) chiudi(); });

  document.addEventListener('click', e => {
    const a = e.target.closest('a.mat');
    if (!a || !a.href.includes('t=m')) return;
    if (a.dataset.tipo === 'zip') return;   // lo zip si scarica, non si guarda
    e.preventDefault();
    const t = a.querySelector('b');
    apri(a.href, t ? t.textContent : 'Documento');
  });
})();
