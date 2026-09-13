<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim((string)$_POST['title']);
        $cat   = (int)$_POST['category_id'];
        if ($title === '' || !$cat) { flash('Servono il titolo e la categoria.', 'err'); back('lezioni.php'); }
        $vt  = in_array($_POST['video_type'] ?? '', ['file','url','embed'], true) ? $_POST['video_type'] : 'file';
        $src = $vt === 'file' ? trim((string)($_POST['video_file'] ?? '')) : trim((string)($_POST['video_link'] ?? ''));
        $dur = (int)($_POST['duration_sec'] ?? 0);
        $pos = (int)($_POST['pos'] ?? 0);
        $pub = isset($_POST['published']) ? 1 : 0;
        $pst = trim((string)($_POST['poster_file'] ?? ''));

        if ($id) {
            $old = db()->prepare('SELECT * FROM lessons WHERE id=?'); $old->execute([$id]); $o = $old->fetch();
            if ($vt === 'file' && $src === '')  $src = $o['video_type']==='file' ? $o['video_src'] : '';
            if ($pst === '') $pst = $o['poster'];
            db()->prepare('UPDATE lessons SET category_id=?,title=?,descr=?,video_type=?,video_src=?,
                           poster=?,duration_sec=?,pos=?,published=? WHERE id=?')
                ->execute([$cat,$title,trim((string)$_POST['descr']),$vt,$src,$pst,$dur,$pos,$pub,$id]);
            flash('Lezione aggiornata.');
        } else {
            if (!$pos) {
                $q = db()->prepare('SELECT COALESCE(MAX(pos),0)+1 FROM lessons WHERE category_id=?');
                $q->execute([$cat]);
                $pos = (int)$q->fetchColumn();
            }
            db()->prepare('INSERT INTO lessons(category_id,title,descr,video_type,video_src,poster,duration_sec,pos,published)
                           VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$cat,$title,trim((string)$_POST['descr']),$vt,$src,$pst,$dur,$pos,$pub]);
            flash('Lezione creata.');
        }
    } elseif ($a === 'del') {
        $id = (int)$_POST['id'];
        $s = db()->prepare('SELECT * FROM lessons WHERE id=?'); $s->execute([$id]); $l = $s->fetch();
        if ($l && $l['video_type']==='file' && $l['video_src']) @unlink(STORAGE.'/video/'.basename($l['video_src']));
        if ($l && $l['poster']) @unlink(STORAGE.'/video/'.basename($l['poster']));
        db()->prepare('DELETE FROM lessons WHERE id=?')->execute([$id]);
        flash('Lezione eliminata.');
    }
    back('lezioni.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY pos, id')->fetchAll();
$rows = db()->query('SELECT l.*, c.title AS cat,
                      (SELECT COUNT(*) FROM materials m WHERE m.lesson_id=l.id) AS nm
                     FROM lessons l LEFT JOIN categories c ON c.id=l.category_id
                     ORDER BY c.pos, c.id, l.pos, l.id')->fetchAll();
$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = db()->prepare('SELECT * FROM lessons WHERE id=?'); $s->execute([$eid]); $ed = $s->fetch(); }

ahead('Lezioni', 'lezioni.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Lezioni</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Carica il video, dagli un nome e mettilo nella sua categoria.</p>

<?php if (!$cats): ?>
  <div class="msg info">Prima crea almeno una <a href="categorie.php" style="color:#6ee7ff">categoria</a>.</div>
<?php else: ?>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3><?= $ed ? 'Modifica lezione' : 'Nuova lezione' ?></h3>
    <?php if ($ed): ?><a class="btn gh sm" href="lezioni.php">Annulla</a><?php endif; ?></div>
  <div class="bd">
    <form method="post" id="lf">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

      <div class="row c2">
        <div class="fld"><label>Titolo della lezione</label>
          <input class="inp" name="title" required value="<?= e($ed['title'] ?? '') ?>"
                 placeholder="Es. Creare la prima scena 3D"></div>
        <div class="fld"><label>Categoria</label>
          <select class="inp" name="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($ed && (int)$ed['category_id']===(int)$c['id'])?'selected':'' ?>>
                <?= e($c['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>

      <div class="fld"><label>Descrizione</label>
        <textarea class="inp" name="descr" placeholder="Cosa impara il corsista in questa lezione"><?= e($ed['descr'] ?? '') ?></textarea></div>

      <div class="fld"><label>Da dove arriva il video</label>
        <select class="inp" name="video_type" id="vt">
          <option value="file"  <?= ($ed['video_type'] ?? 'file')==='file'?'selected':'' ?>>Carico il file qui (MP4)</option>
          <option value="url"   <?= ($ed['video_type'] ?? '')==='url'?'selected':'' ?>>Link diretto a un MP4 esterno</option>
          <option value="embed" <?= ($ed['video_type'] ?? '')==='embed'?'selected':'' ?>>Embed (Vimeo, Bunny, YouTube non in elenco)</option>
        </select>
        <div class="hint">Su hosting condiviso i video pesanti si servono male: sopra i 300 MB conviene un player esterno e qui metti l'embed.</div>
      </div>

      <div id="box-file" class="fld">
        <label>File video</label>
        <div class="drop" id="dv">
          <svg width="26" height="26" viewBox="0 0 26 26" fill="none" stroke="#6ee7ff" stroke-width="1.5"
               stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto">
            <path d="M13 17V5M8.5 9.5L13 5l4.5 4.5"/><path d="M3.5 17v3a1 1 0 0 0 1 1h17a1 1 0 0 0 1-1v-3"/></svg>
          <div class="t">Trascina qui il video o clicca</div>
          <div class="s">MP4, MOV, WebM · caricamento a blocchi, nessun limite di dimensione</div>
        </div>
        <input type="file" id="fv" accept="video/mp4,video/quicktime,video/webm" hidden>
        <div class="upbar" id="dv-bar"><div class="lb"><b></b><span></span></div><div class="bar"><i style="width:0"></i></div></div>
        <input type="hidden" name="video_file" id="video_file" value="">
        <?php if ($ed && $ed['video_type']==='file' && $ed['video_src']): ?>
          <div class="hint">Attuale: <span class="mono"><?= e($ed['video_src']) ?></span> — carica un nuovo file solo se vuoi sostituirlo.</div>
        <?php endif; ?>
      </div>

      <div id="box-link" class="fld hide">
        <label>Indirizzo del video</label>
        <input class="inp" name="video_link" id="video_link"
               value="<?= e(($ed && $ed['video_type']!=='file') ? $ed['video_src'] : '') ?>"
               placeholder="https://…/video.mp4  oppure  https://player.vimeo.com/video/…">
      </div>

      <div class="row c3">
        <div class="fld"><label>Durata in secondi</label>
          <input class="inp" type="number" name="duration_sec" id="dur" min="0" value="<?= (int)($ed['duration_sec'] ?? 0) ?>">
          <div class="hint">Si compila da sola caricando il file.</div></div>
        <div class="fld"><label>Ordine nella categoria</label>
          <input class="inp" type="number" name="pos" value="<?= (int)($ed['pos'] ?? 0) ?>"></div>
        <div class="fld"><label>Stato</label>
          <label class="chk" style="margin-top:11px"><input type="checkbox" name="published"
            <?= (!$ed || $ed['published'])?'checked':'' ?>> Visibile ai corsisti</label></div>
      </div>

      <div class="fld"><label>Copertina (facoltativa)</label>
        <div class="drop" id="dp" style="padding:16px">
          <div class="t" style="margin:0">Trascina un'immagine o clicca</div>
          <div class="s">JPG o PNG, 16:9</div>
        </div>
        <input type="file" id="fp" accept="image/jpeg,image/png,image/webp" hidden>
        <div class="upbar" id="dp-bar"><div class="lb"><b></b><span></span></div><div class="bar"><i style="width:0"></i></div></div>
        <input type="hidden" name="poster_file" id="poster_file" value="">
      </div>

      <button class="btn"><?= $ed?'Salva modifiche':'Crea lezione' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Elenco lezioni</h3><span class="pill"><?= count($rows) ?></span></div>
  <?php if (!$rows): ?><div class="bd"><p class="muted">Nessuna lezione ancora.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>#</th><th>Lezione</th><th>Categoria</th><th>Video</th><th>Durata</th><th>PDF</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted mono"><?= (int)$r['pos'] ?></td>
        <td><b><?= e($r['title']) ?></b><?php if(!$r['published']): ?>
              <span class="pill warn" style="margin-left:7px">nascosta</span><?php endif; ?></td>
        <td class="muted"><?= e($r['cat'] ?? '—') ?></td>
        <td><?php if ($r['video_src']): ?>
              <span class="pill ok"><?= $r['video_type']==='file'?'file':($r['video_type']==='url'?'link':'embed') ?></span>
            <?php else: ?><span class="pill bad">manca</span><?php endif; ?></td>
        <td class="muted mono"><?= (int)$r['duration_sec'] ? hms((int)$r['duration_sec']) : '—' ?></td>
        <td class="muted"><?= (int)$r['nm'] ?: '—' ?></td>
        <td><div class="ac">
          <a class="btn gh sm" href="../lezione.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Vedi</a>
          <a class="btn gh sm" href="lezioni.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post" onsubmit="return confirm('Eliminare «<?= e($r['title']) ?>» e il suo video?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn dg sm">Elimina</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<script src="../assets/js/upload.js?v=<?= @filemtime(APP_ROOT."/assets/js/upload.js") ?>"></script>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
const vt = document.getElementById('vt');
function sync(){ const f = vt.value === 'file';
  document.getElementById('box-file').classList.toggle('hide', !f);
  document.getElementById('box-link').classList.toggle('hide', f); }
vt.addEventListener('change', sync); sync();
wireDrop('dv','fv','video',CSRF,'video_file',(j)=>{ if(j.seconds) document.getElementById('dur').value=j.seconds; });
wireDrop('dp','fp','poster',CSRF,'poster_file');
</script>
<?php endif; afoot();
