<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $type = in_array($_POST['type'] ?? '', ['single','text'], true) ? $_POST['type'] : 'single';
        $lab  = trim((string)$_POST['label']);
        $opz  = trim((string)$_POST['options']);
        if ($lab === '') { flash('La domanda non può essere vuota.', 'err'); back('domande.php'); }
        if ($type === 'single' && $opz === '') { flash('Una domanda a scelta ha bisogno delle opzioni.', 'err'); back('domande.php'); }
        $f = [trim((string)$_POST['section']), $type, $lab, trim((string)$_POST['help']),
              $type === 'single' ? $opz : '', isset($_POST['required'])?1:0,
              (int)$_POST['pos'], isset($_POST['published'])?1:0];
        if ($id) {
            db()->prepare('UPDATE questions SET section=?,type=?,label=?,help=?,options=?,required=?,pos=?,published=? WHERE id=?')
               ->execute([...$f, $id]);
            flash('Domanda aggiornata.');
        } else {
            if (!$f[6]) $f[6] = 1 + (int)db()->query('SELECT COALESCE(MAX(pos),0) FROM questions')->fetchColumn();
            db()->prepare('INSERT INTO questions(section,type,label,help,options,required,pos,published) VALUES(?,?,?,?,?,?,?,?)')
               ->execute($f);
            flash('Domanda aggiunta.');
        }
    } elseif ($a === 'del') {
        db()->prepare('DELETE FROM questions WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Domanda eliminata, con le risposte che aveva raccolto.');
    } elseif ($a === 'toggle') {
        db()->prepare('UPDATE questions SET published = 1 - published WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Visibilità aggiornata.');
    }
    back('domande.php');
}

$rows = db()->query('SELECT q.*, (SELECT COUNT(*) FROM answers a WHERE a.question_id=q.id AND a.value<>\'\') AS n
                     FROM questions q ORDER BY q.pos, q.id')->fetchAll();
$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s=db()->prepare('SELECT * FROM questions WHERE id=?'); $s->execute([$eid]); $ed=$s->fetch(); }
$g = fn(string $k, string $d='') => e((string)($ed[$k] ?? $d));

ahead('Domande', 'domande.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Domande del questionario</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Il questionario compare al primo accesso, subito dopo i dati personali, e non si può saltare.
  Chi ha già risposto non viene rimandato indietro se aggiungi domande dopo.</p>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3><?= $ed ? 'Modifica domanda' : 'Nuova domanda' ?></h3>
    <?php if ($ed): ?><a class="btn gh sm" href="domande.php">Annulla</a><?php endif; ?></div>
  <div class="bd">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

      <div class="row c2">
        <div class="fld"><label>Tipo</label>
          <select class="inp" name="type" id="qt">
            <option value="single" <?= ($ed['type'] ?? 'single')==='single'?'selected':'' ?>>Scelta fra opzioni</option>
            <option value="text"   <?= ($ed['type'] ?? '')==='text'?'selected':'' ?>>Risposta libera</option>
          </select></div>
        <div class="fld"><label>Sezione</label>
          <input class="inp" name="section" value="<?= $g('section') ?>" placeholder="Due parole su di te">
          <div class="hint">Le domande con la stessa sezione vengono raggruppate.</div></div>
      </div>

      <div class="fld"><label>Domanda</label>
        <input class="inp" name="label" required value="<?= $g('label') ?>" placeholder="Cosa fai oggi principalmente?"></div>

      <div class="fld"><label>Nota sotto la domanda</label>
        <input class="inp" name="help" value="<?= $g('help') ?>" placeholder="Facoltativa, aiuta a rispondere meglio"></div>

      <div class="fld" id="box-opz"><label>Opzioni</label>
        <textarea class="inp" name="options" style="min-height:120px" placeholder="Una per riga"><?= $g('options') ?></textarea>
        <div class="hint">Una risposta per riga. Vale solo per le domande a scelta.</div></div>

      <div class="row c3">
        <div class="fld"><label>Ordine</label>
          <input class="inp" type="number" name="pos" value="<?= (int)($ed['pos'] ?? 0) ?>"></div>
        <div class="fld"><label>Obbligatoria</label>
          <label class="chk" style="margin-top:11px"><input type="checkbox" name="required"
            <?= (!$ed || $ed['required'])?'checked':'' ?>> Non si può saltare</label></div>
        <div class="fld"><label>Stato</label>
          <label class="chk" style="margin-top:11px"><input type="checkbox" name="published"
            <?= (!$ed || $ed['published'])?'checked':'' ?>> Attiva nel questionario</label></div>
      </div>

      <button class="btn"><?= $ed ? 'Salva modifiche' : 'Aggiungi domanda' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Elenco</h3><span class="pill"><?= count($rows) ?></span></div>
  <?php if (!$rows): ?><div class="bd"><p class="muted">Nessuna domanda: il questionario viene saltato.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>#</th><th>Domanda</th><th>Tipo</th><th>Risposte</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted mono"><?= (int)$r['pos'] ?></td>
        <td><b><?= e($r['label']) ?></b>
          <?php if (!$r['published']): ?><span class="pill warn" style="margin-left:7px">spenta</span><?php endif; ?>
          <?php if (!$r['required']): ?><span class="pill" style="margin-left:7px">facoltativa</span><?php endif; ?>
          <?php if ($r['section']): ?><div class="muted" style="font-size:12px"><?= e($r['section']) ?></div><?php endif; ?></td>
        <td class="muted"><?= $r['type']==='single' ? (substr_count(trim($r['options']),"\n")+1).' opzioni' : 'libera' ?></td>
        <td class="muted mono"><?= (int)$r['n'] ?></td>
        <td><div class="ac">
          <a class="btn gh sm" href="domande.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn gh sm"><?= $r['published'] ? 'Spegni' : 'Accendi' ?></button></form>
          <form method="post" onsubmit="return confirm('Eliminare la domanda e le <?= (int)$r['n'] ?> risposte raccolte?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn dg sm">Elimina</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<script>
const qt = document.getElementById('qt');
function sy(){ document.getElementById('box-opz').classList.toggle('hide', qt.value !== 'single'); }
qt.addEventListener('change', sy); sy();
</script>
<?php afoot();
