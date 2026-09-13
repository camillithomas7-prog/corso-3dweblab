<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'add') {
        $t = trim((string)$_POST['title']);
        if ($t === '') { flash('Il titolo non può essere vuoto.', 'err'); back('categorie.php'); }
        $pos = (int)db()->query('SELECT COALESCE(MAX(pos),0)+1 FROM categories')->fetchColumn();
        db()->prepare('INSERT INTO categories(title,descr,pos) VALUES(?,?,?)')
            ->execute([$t, trim((string)$_POST['descr']), $pos]);
        flash('Categoria creata.');
    } elseif ($a === 'edit') {
        db()->prepare('UPDATE categories SET title=?, descr=?, pos=?, published=? WHERE id=?')
            ->execute([trim((string)$_POST['title']), trim((string)$_POST['descr']),
                       (int)$_POST['pos'], isset($_POST['published'])?1:0, (int)$_POST['id']]);
        flash('Categoria aggiornata.');
    } elseif ($a === 'del') {
        $id = (int)$_POST['id'];
        $q = db()->prepare('SELECT COUNT(*) FROM lessons WHERE category_id=?'); $q->execute([$id]);
        if ((int)$q->fetchColumn() > 0) flash('Prima sposta o elimina le lezioni di questa categoria.', 'err');
        else { db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); flash('Categoria eliminata.'); }
    }
    back('categorie.php');
}
$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM lessons l WHERE l.category_id=c.id) AS n
                     FROM categories c ORDER BY c.pos, c.id')->fetchAll();
$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) {
    $s = db()->prepare('SELECT * FROM categories WHERE id=?'); $s->execute([$eid]); $ed = $s->fetch();
}
ahead('Categorie', 'categorie.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Categorie</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Sono i moduli del corso. Ogni lezione va dentro una categoria.</p>

<div class="row c2" style="align-items:start">
  <div class="card">
    <div class="hd"><h3><?= $ed ? 'Modifica categoria' : 'Nuova categoria' ?></h3>
      <?php if ($ed): ?><a class="btn gh sm" href="categorie.php">Annulla</a><?php endif; ?></div>
    <div class="bd">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="<?= $ed?'edit':'add' ?>">
        <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>
        <div class="fld"><label>Titolo</label>
          <input class="inp" name="title" required value="<?= e($ed['title'] ?? '') ?>"
                 placeholder="Es. Fondamenti del 3D sul web"></div>
        <div class="fld"><label>Descrizione</label>
          <textarea class="inp" name="descr" placeholder="Una riga che spiega cosa copre il modulo"><?= e($ed['descr'] ?? '') ?></textarea></div>
        <?php if ($ed): ?>
          <div class="row c2">
            <div class="fld"><label>Ordine</label><input class="inp" type="number" name="pos" value="<?= (int)$ed['pos'] ?>"></div>
            <div class="fld"><label>Stato</label>
              <label class="chk" style="margin-top:9px"><input type="checkbox" name="published" <?= $ed['published']?'checked':'' ?>> Visibile ai corsisti</label></div>
          </div>
        <?php endif; ?>
        <button class="btn w"><?= $ed?'Salva modifiche':'Crea categoria' ?></button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h3>Elenco</h3><span class="pill"><?= count($rows) ?></span></div>
    <?php if (!$rows): ?><div class="bd"><p class="muted">Nessuna categoria. Creane una qui a fianco.</p></div>
    <?php else: ?>
    <div class="tw"><table class="tb">
      <thead><tr><th>#</th><th>Titolo</th><th>Lezioni</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?>
        <tr>
          <td class="muted mono"><?= (int)$r['pos'] ?></td>
          <td><b><?= e($r['title']) ?></b>
            <?php if (!$r['published']): ?><span class="pill warn" style="margin-left:7px">nascosta</span><?php endif; ?></td>
          <td class="muted"><?= (int)$r['n'] ?></td>
          <td><div class="ac">
            <a class="btn gh sm" href="categorie.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
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
</div>
<?php afoot();
