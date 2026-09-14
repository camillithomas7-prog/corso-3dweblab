<?php
require_once __DIR__ . '/inc.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'add') {
        $needle = trim((string)$_POST['needle']);
        $cat    = (int)$_POST['category_id'];
        if ($needle === '' || !$cat) { flash('Servono il prodotto e il modulo.', 'err'); back('accessi.php'); }
        db()->prepare('INSERT INTO product_map(needle,category_id,note) VALUES(?,?,?)')
           ->execute([$needle, $cat, trim((string)$_POST['note'])]);
        flash('Collegamento creato.');
    } elseif ($a === 'tutti') {
        $cat = (int)$_POST['category_id'];
        $n = db()->prepare("INSERT OR IGNORE INTO entitlements(code_id,category_id,source)
                            SELECT id, ?, 'manuale' FROM codes");
        $n->execute([$cat]);
        flash('Modulo assegnato a ' . $n->rowCount() . ' corsisti che non lo avevano.');
    } elseif ($a === 'orfani') {
        $cat = (int)$_POST['category_id'];
        $n = db()->prepare("INSERT OR IGNORE INTO entitlements(code_id,category_id,source)
                            SELECT c.id, ?, 'manuale' FROM codes c
                            WHERE NOT EXISTS (SELECT 1 FROM entitlements e WHERE e.code_id=c.id)");
        $n->execute([$cat]);
        flash('Modulo assegnato a ' . $n->rowCount() . ' corsisti che erano senza niente.');
    } elseif ($a === 'togli') {
        $cat = (int)$_POST['category_id'];
        $n = db()->prepare("DELETE FROM entitlements WHERE category_id=? AND source IN ('iniziale','manuale')");
        $n->execute([$cat]);
        flash('Modulo tolto a ' . $n->rowCount() . ' corsisti. Chi lo ha comprato lo mantiene.');
    } elseif ($a === 'del') {
        db()->prepare('DELETE FROM product_map WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Collegamento rimosso.');
    }
    back('accessi.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY pos, id')->fetchAll();
$map  = db()->query('SELECT m.*, c.title FROM product_map m
                     JOIN categories c ON c.id=m.category_id ORDER BY c.pos, m.id')->fetchAll();
$attivo = gate_attivo();
$ncodes = (int)db()->query('SELECT COUNT(*) FROM codes')->fetchColumn();

ahead('Accessi', 'accessi.php'); show_flash();
?>
<?php
$tot = (int)db()->query('SELECT COUNT(*) FROM codes')->fetchColumn();
$orfani = (int)db()->query('SELECT COUNT(*) FROM codes c
    WHERE NOT EXISTS (SELECT 1 FROM entitlements e WHERE e.code_id=c.id)')->fetchColumn();
$st = db()->query("SELECT k.id, k.title,
    (SELECT COUNT(*) FROM entitlements e WHERE e.category_id=k.id) AS n,
    (SELECT COUNT(*) FROM entitlements e WHERE e.category_id=k.id AND e.source='ordine') AS n_ord,
    (SELECT COUNT(*) FROM entitlements e WHERE e.category_id=k.id AND e.source='manuale') AS n_man,
    (SELECT COUNT(*) FROM entitlements e WHERE e.category_id=k.id AND e.source='iniziale') AS n_ini
    FROM categories k ORDER BY k.pos, k.id")->fetchAll();
?>

<h1 style="font-size:23px;margin-bottom:6px">Accessi e upsell</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Colleghi un prodotto Shopify a un modulo del corso. All'arrivo di un ordine la piattaforma
  guarda cosa è stato comprato e sblocca solo quei moduli.</p>

<?php if (!$attivo): ?>
  <div class="msg info"><b>Il filtro è spento.</b> Finché non crei almeno un collegamento,
    tutti i corsisti vedono tutti i moduli — così nessuno resta chiuso fuori per una configurazione
    lasciata a metà. Si accende da solo appena aggiungi la prima riga qui sotto.</div>
<?php else: ?>
  <?php if ($orfani): ?>
    <div class="msg err"><b>Attenzione: <?= $orfani ?> corsist<?= $orfani===1?'a non ha':'i non hanno' ?>
      nessun modulo</b> e in questo momento apr<?= $orfani===1?'e':'ono' ?> il corso e non ved<?= $orfani===1?'e':'ono' ?>
      niente. Sistemalo qui sotto con <b>Dai a chi è senza niente</b> sul modulo base.</div>
  <?php else: ?>
    <div class="msg ok"><b>Il filtro è attivo</b> e tutti i <?= $tot ?> corsisti hanno almeno un modulo.
      Dai nuovi ordini in poi contano i collegamenti qui sotto.</div>
  <?php endif; ?>
<?php endif; ?>

<div class="row c2" style="align-items:start;margin-bottom:22px">
  <div class="card">
    <div class="hd"><h3>Nuovo collegamento</h3></div>
    <div class="bd">
      <?php if (!$cats): ?><p class="muted">Crea prima almeno una categoria.</p><?php else: ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="add">
        <div class="fld"><label>Prodotto Shopify</label>
          <input class="inp" name="needle" required placeholder="8123456789012 oppure UPSELL-AVANZATO">
          <div class="hint">ID prodotto, ID variante, SKU, oppure una parte del titolo.
            I numeri devono coincidere esatti; il testo basta che sia contenuto nel titolo.</div></div>
        <div class="fld"><label>Sblocca il modulo</label>
          <select class="inp" name="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="fld"><label>Promemoria <span class="muted">(facoltativo)</span></label>
          <input class="inp" name="note" placeholder="Corso base · Upsell avanzato"></div>
        <button class="btn w">Collega</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h3>Come si comporta</h3></div>
    <div class="bd" style="font-size:14.5px;color:var(--ink2);line-height:1.7">
      <p style="margin-bottom:12px"><b style="color:var(--ink)">Cliente nuovo.</b> Riceve il codice e
        i moduli dei prodotti che ha comprato. Gli altri li vede col lucchetto.</p>
      <p style="margin-bottom:12px"><b style="color:var(--ink)">Cliente che torna.</b> Se la sua email
        ha già un accesso non ne riceve un secondo: il modulo si aggiunge al codice che ha già, e gli
        arriva una mail diversa che dice cosa ha sbloccato.</p>
      <p style="margin-bottom:14px"><b style="color:var(--ink)">Prodotto non collegato.</b> L'ordine
        genera comunque il codice, ma senza moduli. Se vendi un solo corso e non vuoi questo filtro,
        lascia vuota questa pagina.</p>
      <div class="msg info" style="margin:0">Per assegnare o togliere un modulo a un singolo cliente,
        apri la sua <b>scheda</b> da Codici di accesso.</div>
    </div>
  </div>
</div>

<div class="card" id="chi" style="margin-bottom:18px">
  <div class="hd"><h3>Chi ha cosa</h3><span class="pill"><?= $tot ?> corsisti</span></div>
  <?php if ($orfani): ?>
    <div class="bd" style="padding-bottom:0"><div class="msg err">
      <b><?= $orfani ?> corsist<?= $orfani===1?'a è'.' ' :'i sono ' ?>senza nessun modulo</b> e in questo momento
      non vede niente. Succede ai codici creati a mano, o arrivati prima che i collegamenti esistessero.
      Usa <b>Dai a chi è senza niente</b> sul modulo che devono avere.</div></div>
  <?php endif; ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Modulo</th><th>Ce l'hanno</th><th>Da ordine</th><th>A mano</th><th>Storici</th><th></th></tr></thead>
    <tbody><?php foreach ($st as $r): ?>
      <tr>
        <td><b><?= e($r['title']) ?></b></td>
        <td class="mono"><a href="accessi.php?modulo=<?= (int)$r['id'] ?>#chi"
             style="color:#6ee7ff"><?= (int)$r['n'] ?></a> <span class="muted">/ <?= $tot ?></span></td>
        <td class="muted mono"><?= (int)$r['n_ord'] ?></td>
        <td class="muted mono"><?= (int)$r['n_man'] ?></td>
        <td class="muted mono"><?= (int)$r['n_ini'] ?></td>
        <td><div class="ac">
          <?php if ($orfani): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="orfani"><input type="hidden" name="category_id" value="<?= (int)$r['id'] ?>">
            <button class="btn sm">Dai a chi è senza niente</button></form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Assegnare «<?= e($r['title']) ?>» a tutti i <?= $tot ?> corsisti?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="tutti"><input type="hidden" name="category_id" value="<?= (int)$r['id'] ?>">
            <button class="btn gh sm">Dai a tutti</button></form>
          <form method="post" onsubmit="return confirm('Togliere «<?= e($r['title']) ?>» a chi non lo ha comprato?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="togli"><input type="hidden" name="category_id" value="<?= (int)$r['id'] ?>">
            <button class="btn dg sm">Togli a chi non l'ha comprato</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php
  $sel = (int)($_GET['modulo'] ?? 0);
  if ($sel):
      $q = db()->prepare("SELECT c.code, c.label, c.email, e.source, e.order_ref
                          FROM codes c JOIN entitlements e ON e.code_id=c.id
                          WHERE e.category_id=? ORDER BY e.source, c.id DESC");
      $q->execute([$sel]);
      $righe = $q->fetchAll();
      $tit = '';
      foreach ($st as $r) if ((int)$r['id'] === $sel) $tit = $r['title'];
  ?>
  <div class="bd" style="border-top:1px solid var(--line2)">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <b>Chi ha «<?= e($tit) ?>»</b>
      <span class="pill"><?= count($righe) ?></span>
      <a class="btn gh sm" href="accessi.php" style="margin-left:auto">Chiudi</a>
    </div>
    <?php if (!$righe): ?>
      <p class="muted" style="margin:0">Nessuno ha questo modulo.</p>
    <?php else: ?>
    <div class="tw"><table class="tb">
      <thead><tr><th>Codice</th><th>Cliente</th><th>Come l'ha avuto</th></tr></thead>
      <tbody><?php foreach ($righe as $x): ?>
        <tr>
          <td class="mono" style="color:#6ee7ff"><?= e($x['code']) ?></td>
          <td><?= e($x['label'] ?: ($x['email'] ?: '—')) ?></td>
          <td class="muted"><?php
            echo ['ordine' => 'comprato', 'manuale' => 'assegnato a mano',
                  'iniziale' => 'recupero storico'][$x['source']] ?? e($x['source']);
            if ($x['source'] === 'ordine' && $x['order_ref']) echo ' · ' . e($x['order_ref']); ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="hd"><h3>Collegamenti</h3><span class="pill"><?= count($map) ?></span></div>
  <?php if (!$map): ?><div class="bd"><p class="muted">Nessun collegamento: tutti vedono tutto.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Prodotto Shopify</th><th>Sblocca</th><th>Promemoria</th><th></th></tr></thead>
    <tbody><?php foreach ($map as $m): ?>
      <tr>
        <td class="mono"><?= e($m['needle']) ?></td>
        <td><b><?= e($m['title']) ?></b></td>
        <td class="muted"><?= e($m['note'] ?: '—') ?></td>
        <td><div class="ac">
          <form method="post" onsubmit="return confirm('Rimuovere questo collegamento? Chi lo ha già non perde nulla.')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button class="btn dg sm">Rimuovi</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php afoot();
