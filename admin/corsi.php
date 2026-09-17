<?php
require_once __DIR__ . '/inc.php';
require_admin();

$dir = STORAGE . '/img';
if (!is_dir($dir)) @mkdir($dir, 0775, true);

/** Il tetto vero per un caricamento diretto, deciso dal server. */
function tetto_upload(): int {
    $v = fn(string $k) => (int)preg_replace_callback('/^(\d+)\s*([KMG])?/i', function ($m) {
        $n = (int)$m[1];
        return (string)($n * ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824][strtoupper($m[2] ?? '')]);
    }, (string)ini_get($k));
    $a = $v('upload_max_filesize'); $b = $v('post_max_size');
    $m = min($a ?: PHP_INT_MAX, $b ?: PHP_INT_MAX);
    return $m === PHP_INT_MAX ? 2097152 : $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';

    if ($a === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $tit   = trim((string)($_POST['title'] ?? ''));
        $sub   = trim((string)($_POST['subtitle'] ?? ''));
        $des   = trim((string)($_POST['descr'] ?? ''));
        $url   = trim((string)($_POST['url'] ?? ''));
        $coup  = trim((string)($_POST['coupon'] ?? ''));
        $badge = trim((string)($_POST['badge'] ?? ''));
        $pr    = (float)str_replace(',', '.', (string)($_POST['price'] ?? '0'));
        $prOff = trim((string)($_POST['price_offer'] ?? ''));
        $prOff = $prOff === '' ? null : (float)str_replace(',', '.', $prOff);
        $pos   = (int)($_POST['pos'] ?? 0);
        $pub   = isset($_POST['published']) ? 1 : 0;

        if ($tit === '') { flash('Serve almeno il titolo.', 'err'); back('corsi.php'); }
        if ($url !== '' && !preg_match('~^https?://~i', $url)) {
            flash('Il link deve cominciare con https://', 'err'); back('corsi.php');
        }

        // copertina: solo se ne è stata scelta una nuova
        $img = null;
        if (!empty($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['cover'];
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
                flash('Immagine troppo pesante: il server accetta fino a '
                      . human_bytes(tetto_upload()) . '.', 'err');
                back('corsi.php');
            }
            if ($f['error'] !== UPLOAD_ERR_OK) { flash('Caricamento non riuscito.', 'err'); back('corsi.php'); }
            $info = @getimagesize($f['tmp_name']);
            $ok   = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($ok[$info[2]])) { flash('Serve un file JPG, PNG o WEBP.', 'err'); back('corsi.php'); }
            $nome = safe_name($f['name'], $ok[$info[2]]);
            if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $nome)) {
                flash('Non riesco a salvare il file sul server.', 'err'); back('corsi.php');
            }
            $img = $nome;
        }

        if ($id) {
            $vecchia = db()->prepare('SELECT image FROM corsi WHERE id=?');
            $vecchia->execute([$id]); $prec = (string)$vecchia->fetchColumn();
            db()->prepare('UPDATE corsi SET title=?,subtitle=?,descr=?,price=?,price_offer=?,
                           coupon=?,url=?,badge=?,pos=?,published=?' . ($img !== null ? ',image=?' : '') . '
                           WHERE id=?')
               ->execute($img !== null
                   ? [$tit,$sub,$des,$pr,$prOff,$coup,$url,$badge,$pos,$pub,$img,$id]
                   : [$tit,$sub,$des,$pr,$prOff,$coup,$url,$badge,$pos,$pub,$id]);
            // la vecchia copertina caricata non serve più, quelle di serie restano
            if ($img !== null && $prec !== '' && !str_starts_with($prec, 'assets/')) @unlink($dir . '/' . basename($prec));
            flash('Corso aggiornato.');
        } else {
            db()->prepare('INSERT INTO corsi(title,subtitle,descr,image,price,price_offer,
                           coupon,url,badge,pos,published) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$tit,$sub,$des,(string)$img,$pr,$prOff,$coup,$url,$badge,$pos,$pub]);
            flash('Corso aggiunto.');
        }
    } elseif ($a === 'toggle') {
        db()->prepare('UPDATE corsi SET published = CASE published WHEN 1 THEN 0 ELSE 1 END WHERE id=?')
           ->execute([(int)$_POST['id']]);
        flash('Visibilità aggiornata.');
    } elseif ($a === 'del') {
        $q = db()->prepare('SELECT image FROM corsi WHERE id=?'); $q->execute([(int)$_POST['id']]);
        $im = (string)$q->fetchColumn();
        db()->prepare('DELETE FROM corsi WHERE id=?')->execute([(int)$_POST['id']]);
        if ($im !== '' && !str_starts_with($im, 'assets/')) @unlink($dir . '/' . basename($im));
        flash('Corso eliminato.');
    }
    back('corsi.php');
}

$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) {
    $q = db()->prepare('SELECT * FROM corsi WHERE id=?'); $q->execute([$eid]);
    $ed = $q->fetch() ?: null;
}
$righe = db()->query('SELECT * FROM corsi ORDER BY pos, id')->fetchAll();

ahead('Nuovi corsi', 'corsi.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Nuovi corsi</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Quello che il corsista vede nella sezione <b>Nuovi corsi</b>: prezzo pieno barrato,
  prezzo riservato, codice sconto e bottone verso la pagina prodotto.</p>

<div class="row c2" style="align-items:start;margin-bottom:22px">
  <div class="card">
    <div class="hd"><h3><?= $ed ? 'Modifica corso' : 'Nuovo corso' ?></h3>
      <?php if ($ed): ?><a class="btn gh sm" href="corsi.php">Annulla</a><?php endif; ?></div>
    <div class="bd">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="save">
        <?php if ($ed): ?><input type="hidden" name="id" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

        <div class="fld"><label>Titolo</label>
          <input class="inp" name="title" required value="<?= e($ed['title'] ?? '') ?>"
                 placeholder="Agente AI telefonico"></div>

        <div class="fld"><label>Sottotitolo</label>
          <input class="inp" name="subtitle" value="<?= e($ed['subtitle'] ?? '') ?>"
                 placeholder="Il servizio ricorrente da rivendere ai tuoi clienti"></div>

        <div class="fld"><label>Descrizione</label>
          <textarea class="inp" name="descr" rows="3"
                    placeholder="Due righe su cosa impara e cosa può rivendere."><?= e($ed['descr'] ?? '') ?></textarea></div>

        <div class="row c2">
          <div class="fld"><label>Prezzo pieno €</label>
            <input class="inp" name="price" inputmode="decimal"
                   value="<?= $ed ? rtrim(rtrim(number_format((float)$ed['price'], 2, ',', ''), '0'), ',') : '49,99' ?>"></div>
          <div class="fld"><label>Prezzo per i corsisti €</label>
            <input class="inp" name="price_offer" inputmode="decimal"
                   value="<?= $ed && $ed['price_offer'] !== null ? rtrim(rtrim(number_format((float)$ed['price_offer'], 2, ',', ''), '0'), ',') : '9,99' ?>"
                   placeholder="vuoto = nessuno sconto"></div>
        </div>

        <div class="fld"><label>Codice sconto</label>
          <input class="inp mono" name="coupon" value="<?= e($ed['coupon'] ?? '3DWEBLAB9') ?>"
                 placeholder="3DWEBLAB9">
          <div class="hint">Deve esistere davvero in Shopify, altrimenti al pagamento viene rifiutato.</div></div>

        <div class="fld"><label>Link alla pagina prodotto</label>
          <input class="inp" name="url" type="url" value="<?= e($ed['url'] ?? '') ?>"
                 placeholder="https://3dweblab.it/products/..."></div>

        <div class="fld"><label>Copertina <span class="muted">(quadrata)</span></label>
          <input class="inp" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
          <div class="hint">JPG, PNG o WEBP, fino a <?= human_bytes(tetto_upload()) ?>.
            <?= $ed && $ed['image'] ? 'Lascia vuoto per tenere quella attuale.' : '' ?></div></div>

        <div class="row c2">
          <div class="fld"><label>Ordine</label>
            <input class="inp" type="number" name="pos" value="<?= (int)($ed['pos'] ?? 0) ?>"></div>
          <div class="fld"><label>Etichetta <span class="muted">(facoltativa)</span></label>
            <input class="inp" name="badge" value="<?= e($ed['badge'] ?? '') ?>" placeholder="Novità"></div>
        </div>

        <label class="chk" style="margin-top:4px"><input type="checkbox" name="published"
          <?= !$ed || $ed['published'] ? 'checked' : '' ?>> Visibile ai corsisti</label>

        <button class="btn w" style="margin-top:16px"><?= $ed ? 'Salva' : 'Aggiungi corso' ?></button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h3>Come lo vede il corsista</h3></div>
    <div class="bd" style="font-size:14.5px;color:var(--ink2);line-height:1.7">
      <p style="margin-bottom:12px">Nella sezione <b style="color:var(--ink)">Nuovi corsi</b> trova una scheda
        per ogni corso pubblicato: copertina, titolo, prezzo pieno barrato accanto al suo prezzo,
        quanto risparmia, il codice sconto da toccare per copiarlo, e il bottone che apre la pagina prodotto.</p>
      <div class="msg info" style="margin:0">Il codice sconto lo devi creare anche in Shopify
        (<b>Sconti → Crea sconto</b>) con lo stesso nome, altrimenti al pagamento non viene accettato.</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Corsi</h3><span class="pill"><?= count($righe) ?></span></div>
  <?php if (!$righe): ?><div class="bd"><p class="muted">Nessun corso. Aggiungine uno qui sopra.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th></th><th>Corso</th><th>Prezzo</th><th>Codice</th><th>Stato</th><th></th></tr></thead>
    <tbody><?php foreach ($righe as $r): $im = corso_img($r, '../'); ?>
      <tr>
        <td style="width:74px">
          <?php if ($im): ?>
            <img src="<?= e($im) ?>" alt="" style="width:58px;height:58px;object-fit:cover;
                 border-radius:8px;border:1px solid var(--line);display:block">
          <?php endif; ?>
        </td>
        <td><b><?= e($r['title']) ?></b>
          <?php if ($r['subtitle']): ?><br><span class="muted" style="font-size:13px"><?= e($r['subtitle']) ?></span><?php endif; ?></td>
        <td class="mono">
          <?php if ($r['price_offer'] !== null): ?>
            <?= number_format((float)$r['price_offer'], 2, ',', '.') ?> €
            <span class="muted" style="text-decoration:line-through"><?= number_format((float)$r['price'], 2, ',', '.') ?></span>
          <?php else: ?><?= number_format((float)$r['price'], 2, ',', '.') ?> €<?php endif; ?>
        </td>
        <td class="mono"><?= e($r['coupon'] ?: '—') ?></td>
        <td><?php if ($r['published']): ?><span class="pill ok">visibile</span>
            <?php else: ?><span class="pill">nascosto</span><?php endif; ?></td>
        <td><div class="ac">
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn gh sm"><?= $r['published'] ? 'Nascondi' : 'Mostra' ?></button></form>
          <a class="btn gh sm" href="corsi.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post" onsubmit="return confirm('Eliminare questo corso?')">
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
