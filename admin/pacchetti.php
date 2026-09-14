<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $f = [
            trim((string)$_POST['title']), trim((string)$_POST['kicker']),
            trim((string)$_POST['intro']), trim((string)$_POST['bullets']),
            (float)str_replace(',', '.', (string)$_POST['price']),
            (float)str_replace(',', '.', (string)$_POST['price_was']),
            trim((string)$_POST['period']), trim((string)$_POST['note']),
            trim((string)$_POST['badge']), isset($_POST['featured']) ? 1 : 0,
            (int)$_POST['pos'], isset($_POST['published']) ? 1 : 0,
        ];
        if ($f[0] === '') { flash('Il titolo non può essere vuoto.', 'err'); back('pacchetti.php'); }
        if ($id) {
            db()->prepare('UPDATE packages SET title=?,kicker=?,intro=?,bullets=?,price=?,price_was=?,
                           period=?,note=?,badge=?,featured=?,pos=?,published=? WHERE id=?')
               ->execute([...$f, $id]);
            flash('Pacchetto aggiornato.');
        } else {
            db()->prepare('INSERT INTO packages(title,kicker,intro,bullets,price,price_was,
                           period,note,badge,featured,pos,published) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute($f);
            flash('Pacchetto creato.');
        }
    } elseif ($a === 'del') {
        db()->prepare('DELETE FROM packages WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Pacchetto eliminato.');
    } elseif ($a === 'mail') {
        set_setting('services_mail', trim((string)$_POST['services_mail']));
        flash('Indirizzo per le richieste aggiornato.');
    }
    back('pacchetti.php');
}

$rows = db()->query('SELECT * FROM packages ORDER BY pos, id')->fetchAll();
$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s = db()->prepare('SELECT * FROM packages WHERE id=?'); $s->execute([$eid]); $ed = $s->fetch(); }
$g = fn(string $k, string $d = '') => e((string)($ed[$k] ?? $d));
$num = fn(string $k) => $ed ? rtrim(rtrim(number_format((float)$ed[$k], 2, '.', ''), '0'), '.') : '';

ahead('Pacchetti', 'pacchetti.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Pacchetti e servizi</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Quello che i corsisti vedono nella sezione <b>Servizi</b>. Prezzi in euro.</p>

<div class="card" style="margin-bottom:18px">
  <div class="bd">
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="mail">
      <div class="fld" style="flex:1;min-width:250px;margin:0">
        <label>Le richieste arrivano a</label>
        <input class="inp" type="email" name="services_mail" required
               value="<?= e(setting('services_mail', 'supporto@3dweblab.it')) ?>"></div>
      <button class="btn gh">Salva indirizzo</button>
    </form>
    <p class="hint" style="margin-top:9px">Il pulsante di ogni pacchetto apre una mail già scritta verso questo indirizzo, con la richiesta del recapito telefonico.</p>
  </div>
</div>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3><?= $ed ? 'Modifica pacchetto' : 'Nuovo pacchetto' ?></h3>
    <?php if ($ed): ?><a class="btn gh sm" href="pacchetti.php">Annulla</a><?php endif; ?></div>
  <div class="bd">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

      <div class="row c2">
        <div class="fld"><label>Titolo</label>
          <input class="inp" name="title" required value="<?= $g('title') ?>" placeholder="Creiamo il tuo sito 3D"></div>
        <div class="fld"><label>Sopratitolo</label>
          <input class="inp" name="kicker" value="<?= $g('kicker') ?>" placeholder="Sviluppo su misura"></div>
      </div>

      <div class="fld"><label>Descrizione breve</label>
        <textarea class="inp" name="intro" style="min-height:70px" placeholder="Due righe che spiegano cos'è"><?= $g('intro') ?></textarea></div>

      <div class="fld"><label>Cosa comprende</label>
        <textarea class="inp" name="bullets" style="min-height:120px" placeholder="Una voce per riga"><?= $g('bullets') ?></textarea>
        <div class="hint">Una voce per riga. Diventano l'elenco con le spunte.</div></div>

      <div class="row c3">
        <div class="fld"><label>Prezzo in euro</label>
          <input class="inp" name="price" required value="<?= $num('price') ?>" placeholder="299"></div>
        <div class="fld"><label>Prezzo barrato</label>
          <input class="inp" name="price_was" value="<?= $num('price_was') ?>" placeholder="890">
          <div class="hint">Lascia 0 per non mostrarlo.</div></div>
        <div class="fld"><label>Periodicità</label>
          <input class="inp" name="period" value="<?= $g('period') ?>" placeholder="al mese">
          <div class="hint">Vuoto per un pagamento unico.</div></div>
      </div>

      <div class="fld"><label>Nota sotto il prezzo</label>
        <input class="inp" name="note" value="<?= $g('note') ?>" placeholder="Durata minima 30 giorni. Dal secondo mese…"></div>

      <div class="row c3">
        <div class="fld"><label>Etichetta in evidenza</label>
          <input class="inp" name="badge" value="<?= $g('badge') ?>" placeholder="Il più richiesto"></div>
        <div class="fld"><label>Ordine</label>
          <input class="inp" type="number" name="pos" value="<?= (int)($ed['pos'] ?? 0) ?>"></div>
        <div class="fld"><label>Opzioni</label>
          <label class="chk" style="margin-top:11px"><input type="checkbox" name="published"
            <?= (!$ed || $ed['published']) ? 'checked' : '' ?>> Visibile</label>
          <label class="chk" style="margin-top:8px"><input type="checkbox" name="featured"
            <?= ($ed && $ed['featured']) ? 'checked' : '' ?>> Metti in risalto</label></div>
      </div>

      <button class="btn"><?= $ed ? 'Salva modifiche' : 'Crea pacchetto' ?></button>
    </form>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Elenco</h3><span class="pill"><?= count($rows) ?></span></div>
  <?php if (!$rows): ?><div class="bd"><p class="muted">Nessun pacchetto.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>#</th><th>Pacchetto</th><th>Prezzo</th><th>Era</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td class="muted mono"><?= (int)$r['pos'] ?></td>
        <td><b><?= e($r['title']) ?></b>
          <?php if (!$r['published']): ?><span class="pill warn" style="margin-left:7px">nascosto</span><?php endif; ?>
          <?php if ($r['badge']): ?><span class="pill acc" style="margin-left:7px"><?= e($r['badge']) ?></span><?php endif; ?></td>
        <td class="mono"><?= rtrim(rtrim(number_format((float)$r['price'], 2, ',', '.'), '0'), ',') ?> €<?= $r['period'] ? ' '.e($r['period']) : '' ?></td>
        <td class="muted mono"><?= (float)$r['price_was'] ? rtrim(rtrim(number_format((float)$r['price_was'], 2, ',', '.'), '0'), ',').' €' : '—' ?></td>
        <td><div class="ac">
          <a class="btn gh sm" href="pacchetti.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
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
<?php afoot();
