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

ahead('Accessi', 'accessi.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Accessi e upsell</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Colleghi un prodotto Shopify a un modulo del corso. All'arrivo di un ordine la piattaforma
  guarda cosa è stato comprato e sblocca solo quei moduli.</p>

<?php if (!$attivo): ?>
  <div class="msg info"><b>Il filtro è spento.</b> Finché non crei almeno un collegamento,
    tutti i corsisti vedono tutti i moduli — così nessuno resta chiuso fuori per una configurazione
    lasciata a metà. Si accende da solo appena aggiungi la prima riga qui sotto.</div>
<?php else: ?>
  <div class="msg ok"><b>Il filtro è attivo.</b> I <?= $ncodes ?> corsisti già registrati mantengono
    tutto ciò che vedevano prima. Dai nuovi ordini in poi contano i collegamenti qui sotto.</div>
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
