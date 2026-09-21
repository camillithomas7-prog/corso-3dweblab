/* La scheda del PDF dentro una bolla di chat, costruita lato browser.
   Gemella di allegato_html() in inc/auth.php: se cambia una, cambia l'altra. */
function allegatoNodo(f, id, base) {
  var url = (base || '') + 'media.php?t=g&id=' + id;
  var fr = document.createDocumentFragment();

  var a = document.createElement('a');
  a.className = 'fbox'; a.href = url; a.target = '_blank'; a.rel = 'noopener';
  a.innerHTML = '<span class="fic"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"'
    + ' stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
    + '<path d="M11.5 2H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V6.5L11.5 2Z"/>'
    + '<path d="M11.5 2v4.5H16"/></svg></span>'
    + '<span class="ftx"><b></b><i></i></span>';
  a.querySelector('b').textContent = f.name;
  a.querySelector('i').textContent = 'PDF' + (f.size ? ' · ' + f.size : '');

  var d = document.createElement('a');
  d.className = 'fdl'; d.href = url + '&dl=1'; d.download = ''; d.textContent = 'Scarica';

  fr.appendChild(a); fr.appendChild(d);
  return fr;
}
