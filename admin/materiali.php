<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'add') {
        $t = trim((string)$_POST['title']);
        $f = trim((string)($_POST['pdf_file'] ?? ''));
        if ($t === '' || $f === '') { flash('Servono il titolo e il file PDF.', 'err'); back('materiali.php'); }
        $path  = STORAGE . '/pdf/' . basename($f);
        $les   = (int)($_POST['lesson_id'] ?? 0) ?: null;
        $cat   = (int)($_POST['category_id'] ?? 0) ?: null;
        $pos   = (int)db()->query('SELECT COALESCE(MAX(pos),0)+1 FROM materials')->fetchColumn();
        db()->prepare('INSERT INTO materials(lesson_id,category_id,title,filename,orig_name,bytes,pos)
                       VALUES(?,?,?,?,?,?,?)')
            ->execute([$les, $les ? null : $cat, $t, basename($f),
                       (string)($_POST['orig_name'] ?? $t.'.pdf'),
                       is_file($path) ? filesize($path) : 0, $pos]);
        flash('Materiale caricato.');
    } elseif ($a === 'edit') {
        $les = (int)($_POST['lesson_id'] ?? 0) ?: null;
        db()->prepare('UPDATE materials SET title=?, lesson_id=?, category_id=?, pos=? WHERE id=?')
            ->execute([trim((string)$_POST['title']), $les,
                       $les ? null : ((int)($_POST['category_id'] ?? 0) ?: null),
                       (int)$_POST['pos'], (int)$_POST['id']]);
        flash('Materiale aggiornato.');
    } elseif ($a === 'del') {
        $s = db()->prepare('SELECT * FROM materials WHERE id=?'); $s->execute([(int)$_POST['id']]);
        if ($m = $s->fetch()) @unlink(STORAGE.'/pdf/'.basename($m['filename']));
        db()->prepare('DELETE FROM materials WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Materiale eliminato.');
    }
    back('materiali.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY pos, id')->fetchAll();
$les  = db()->query('SELECT l.id, l.title, c.title AS cat FROM lessons l
                     LEFT JOIN categories c ON c.id=l.category_id
                     ORDER BY c.pos, l.pos, l.id')->fetchAll();
$rows = db()->query('SELECT m.*, l.title AS ltitle, c.title AS ctitle FROM materials m
                     LEFT JOIN lessons l ON l.id=m.lesson_id
                     LEFT JOIN categories c ON c.id=COALESCE(m.category_id,l.category_id)
                     ORDER BY m.pos, m.id')->fetchAll();
$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s=db()->prepare('SELECT * FROM materials WHERE id=?'); $s->execute([$eid]); $ed=$s->fetch(); }

ahead('Materiali', 'materiali.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Materiali PDF</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Allega un PDF a una lezione, oppure lascialo libero e finisce fra i materiali generali.</p>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3><?= $ed ? 'Modifica materiale' : 'Carica un PDF' ?></h3>
    <?php if ($ed): ?><a class="btn gh sm" href="materiali.php">Annulla</a><?php endif; ?></div>
  <div class="bd">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="<?= $ed?'edit':'add' ?>">
      <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

      <?php if (!$ed): ?>
      <div class="fld">
        <div class="drop" id="dm">
          <svg width="26" height="26" viewBox="0 0 26 26" fill="none" stroke="#f0a6d8" stroke-width="1.5"
               stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto">
            <path d="M7 2.5h8l6 6v15a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-20a1 1 0 0 1 1-1Z"/><path d="M15 2.5v6h6"/></svg>
          <div class="t">Trascina il PDF o clicca</div>
          <div class="s">Solo file .pdf</div>
        </div>
        <input type="file" id="fm" accept="application/pdf" hidden>
        <div class="upbar" id="dm-bar"><div class="lb"><b></b><span></span></div><div class="bar"><i style="width:0"></i></div></div>
        <input type="hidden" name="pdf_file" id="pdf_file" value="">
        <input type="hidden" name="orig_name" id="orig_name" value="">
      </div>
      <?php endif; ?>

      <div class="row c3">
        <div class="fld"><label>Titolo mostrato</label>
          <input class="inp" name="title" id="mtitle" required value="<?= e($ed['title'] ?? '') ?>"
                 placeholder="Es. Checklist di progetto"></div>
        <div class="fld"><label>Allega alla lezione</label>
          <select class="inp" name="lesson_id" id="lsel">
            <option value="0">— nessuna, materiale generale —</option>
            <?php foreach ($les as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= ($ed && (int)$ed['lesson_id']===(int)$l['id'])?'selected':'' ?>>
                <?= e(($l['cat'] ? $l['cat'].' · ' : '').$l['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="fld"><label>Oppure alla categoria</label>
          <select class="inp" name="category_id">
            <option value="0">— nessuna —</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= ($ed && (int)$ed['category_id']===(int)$c['id'])?'selected':'' ?>>
                <?= e($c['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <?php if ($ed): ?>
        <div class="fld" style="max-width:180px"><label>Ordine</label>
          <input class="inp" type="number" name="pos" value="<?= (int)$ed['pos'] ?>"></div>
      <?php endif; ?>
      <button class="btn"><?= $ed?'Salva':'Aggiungi materiale' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Elenco materiali</h3><span class="pill"><?= count($rows) ?></span></div>
  <?php if (!$rows): ?><div class="bd"><p class="muted">Nessun PDF caricato.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>#</th><th>Titolo</th><th>Collegato a</th><th>Peso</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted mono"><?= (int)$r['pos'] ?></td>
        <td><b><?= e($r['title']) ?></b></td>
        <td class="muted"><?= e($r['ltitle'] ?: ($r['ctitle'] ?: 'Materiali generali')) ?></td>
        <td class="muted mono"><?= human_bytes((int)$r['bytes']) ?></td>
        <td><div class="ac">
          <a class="btn gh sm" href="../media.php?t=m&id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Apri</a>
          <a class="btn gh sm" href="materiali.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post" onsubmit="return confirm('Eliminare «<?= e($r['title']) ?>»?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn dg sm">Elimina</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if (!$ed): ?>
<script src="../assets/js/upload.js?v=<?= @filemtime(APP_ROOT."/assets/js/upload.js") ?>"></script>
<script>
const CSRF = <?= json_encode(csrf()) ?>;
wireDrop('dm','fm','pdf',CSRF,'pdf_file',(j,file)=>{
  document.getElementById('orig_name').value = file.name;
  const t = document.getElementById('mtitle');
  if (!t.value) t.value = file.name.replace(/\.pdf$/i,'').replace(/[-_]+/g,' ');
});
</script>
<?php endif; afoot();
