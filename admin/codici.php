<?php
require_once __DIR__ . '/inc.php';
require_once dirname(__DIR__) . '/inc/mailer.php';
require_admin();
$made = [];

/**
 * Manda la mail col codice e segna a che giro siamo: la prima e' quella
 * dell'ordine, dalla seconda in poi e' un sollecito. L'errore resta scritto
 * sulla riga, cosi' si vede dall'elenco chi non l'ha ricevuta.
 */
function spedisci_codice(array $c, int $numero): void {
    try {
        invia_codice($c, $numero);
    } catch (Throwable $e) {
        db()->prepare('UPDATE codes SET email_error=? WHERE id=?')->execute([$e->getMessage(), $c['id']]);
        throw $e;
    }
    db()->prepare("UPDATE codes SET email_sent_at=datetime('now'), email_error='', email_count=?
                   WHERE id=?")->execute([$numero, $c['id']]);
}

/**
 * Dopo un'azione si torna esattamente dov'eri: stessa ricerca e stesso punto
 * della pagina. Con duecento codici in elenco, ripartire dall'inizio ogni
 * volta che premi un bottone e' una piccola tortura.
 */
function torna_al_punto(): never {
    $p = [];
    $q  = trim((string)($_POST['q'] ?? ''));
    $sc = (int)($_POST['sc'] ?? 0);
    if ($q !== '')  $p['q']  = $q;
    if ($sc > 0)    $p['sc'] = $sc;
    back('codici.php' . ($p ? '?' . http_build_query($p) : ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'gen') {
        $n   = max(1, min(200, (int)($_POST['qty'] ?? 1)));
        $lab = trim((string)($_POST['label'] ?? ''));
        $ord = trim((string)($_POST['order_ref'] ?? ''));
        $em  = trim((string)($_POST['email'] ?? ''));
        $exp = trim((string)($_POST['expires_at'] ?? '')) ?: null;
        $mods = array_map('intval', (array)($_POST['mods'] ?? []));
        $ins = db()->prepare('INSERT INTO codes(code,label,order_ref,email,expires_at) VALUES(?,?,?,?,?)');
        $ent = db()->prepare("INSERT OR IGNORE INTO entitlements(code_id,category_id,source) VALUES(?,?,'manuale')");
        for ($i = 0; $i < $n; $i++) {
            for ($try = 0; $try < 12; $try++) {
                $c = generate_code();
                try {
                    $ins->execute([$c, $lab, $ord, $em, $exp]);
                    $cid = (int)db()->lastInsertId();
                    foreach ($mods as $m) $ent->execute([$cid, $m]);
                    $made[] = $c; break;
                }
                catch (PDOException $e) { /* collisione improbabile: riprova */ }
            }
        }
        boot_session();
        $_SESSION['made'] = $made;
        flash(count($made) . ' codice/i generato/i.');
        back('codici.php');
    } elseif ($a === 'toggle') {
        db()->prepare("UPDATE codes SET status = CASE status WHEN 'active' THEN 'revoked' ELSE 'active' END WHERE id=?")
            ->execute([(int)$_POST['id']]);
        flash('Stato aggiornato.');
    } elseif ($a === 'edit') {
        db()->prepare('UPDATE codes SET label=?, order_ref=?, email=?, expires_at=? WHERE id=?')
            ->execute([trim((string)$_POST['label']), trim((string)$_POST['order_ref']),
                       trim((string)$_POST['email']), trim((string)$_POST['expires_at']) ?: null,
                       (int)$_POST['id']]);
        flash('Codice aggiornato.');
    } elseif ($a === 'del') {
        db()->prepare('DELETE FROM codes WHERE id=?')->execute([(int)$_POST['id']]);
        flash('Codice eliminato.');
    } elseif ($a === 'mail') {
        $q = db()->prepare('SELECT * FROM codes WHERE id=?'); $q->execute([(int)$_POST['id']]);
        $c = $q->fetch();
        if (!$c) flash('Codice inesistente.', 'err');
        else {
            $n = (int)$c['email_count'] + 1;
            try {
                spedisci_codice($c, $n);
                flash(($n > 1 ? $n . 'ª email inviata a ' : 'Email inviata a ') . $c['email']);
            } catch (Throwable $e) {
                flash('Invio fallito: ' . $e->getMessage(), 'err');
            }
        }
    } elseif ($a === 'mail_uno') {
        // un solo invio, chiamato in fila dal bottone del sollecito: risponde in JSON
        header('Content-Type: application/json; charset=utf-8');
        $q = db()->prepare('SELECT * FROM codes WHERE id=?'); $q->execute([(int)($_POST['id'] ?? 0)]);
        $c = $q->fetch();
        if (!$c) exit(json_encode(['error' => 'codice inesistente']));
        $n = (int)$c['email_count'] + 1;
        try {
            spedisci_codice($c, $n);
            exit(json_encode(['ok' => true, 'n' => $n, 'chi' => $c['email']]));
        } catch (Throwable $e) {
            exit(json_encode(['error' => $e->getMessage(), 'chi' => $c['email']]));
        }
    }
    torna_al_punto();
}
boot_session();
$made = $_SESSION['made'] ?? []; unset($_SESSION['made']);

$totLes = (int)db()->query('SELECT COUNT(*) FROM lessons WHERE published=1')->fetchColumn();
$f = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM progress p WHERE p.code_id=c.id AND p.completed=1) AS done,
        pr.first_name, pr.last_name, pr.phone_cc, pr.phone, pr.completed_at AS prof_at
        FROM codes c LEFT JOIN profiles pr ON pr.code_id=c.id';
$par = [];
if ($f !== '') { $sql .= ' WHERE c.code LIKE ? OR c.label LIKE ? OR c.order_ref LIKE ? OR c.email LIKE ?
                            OR pr.first_name LIKE ? OR pr.last_name LIKE ? OR pr.phone LIKE ?';
                 $par = array_fill(0, 7, "%$f%"); }
$sql .= ' ORDER BY c.created_at DESC, c.id DESC LIMIT 400';
$st = db()->prepare($sql); $st->execute($par); $rows = $st->fetchAll();
// chi non e' mai entrato nel portale e un indirizzo ce l'ha: sono loro da sollecitare
$dasoll = db()->query("SELECT id FROM codes
                       WHERE status='active' AND TRIM(email) <> '' AND first_used_at IS NULL
                       ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_COLUMN);

$ed = null;
if ($eid = (int)($_GET['edit'] ?? 0)) { $s=db()->prepare('SELECT * FROM codes WHERE id=?'); $s->execute([$eid]); $ed=$s->fetch(); }

ahead('Codici di accesso', 'codici.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Codici di accesso</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Il cliente entra solo con questo. Niente registrazione, niente password.</p>

<?php if ($made): ?>
<div class="card" style="margin-bottom:22px;border-color:#1c3d2a">
  <div class="hd"><h3 style="color:#9ff0bd">Codici appena generati</h3>
    <button class="btn sm" onclick="copyAll()">Copia tutti</button></div>
  <div class="bd">
    <textarea id="new" class="inp mono" rows="<?= min(10, max(2, count($made))) ?>"
      style="font-family:'Space Grotesk',monospace;letter-spacing:.08em;font-size:16px" readonly><?= e(implode("\n", $made)) ?></textarea>
    <p class="hint" style="margin-top:9px">Copiali adesso e incollali dove ti servono. Restano comunque in elenco qui sotto.</p>
  </div>
</div>
<script>function copyAll(){const t=document.getElementById('new');t.select();document.execCommand('copy');}</script>
<?php endif; ?>

<div class="row c2" style="align-items:start;margin-bottom:22px">
  <div class="card">
    <div class="hd"><h3><?= $ed ? 'Modifica codice' : 'Genera codici' ?></h3>
      <?php if ($ed): ?><a class="btn gh sm" href="codici.php">Annulla</a><?php endif; ?></div>
    <div class="bd">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="<?= $ed?'edit':'gen' ?>">
        <?php if ($ed): ?>
          <input type="hidden" name="id" value="<?= (int)$ed['id'] ?>">
          <div class="fld"><label>Codice</label>
            <input class="inp mono" value="<?= e($ed['code']) ?>" readonly
                   style="color:#6ee7ff;font-family:'Space Grotesk',monospace"></div>
        <?php else: ?>
          <div class="fld"><label>Quanti codici</label>
            <input class="inp" type="number" name="qty" value="1" min="1" max="200">
            <div class="hint">Uno per cliente. Puoi generarne un blocco in anticipo.</div></div>
        <?php endif; ?>
        <div class="fld"><label>Nome cliente <span class="muted">(facoltativo)</span></label>
          <input class="inp" name="label" value="<?= e($ed['label'] ?? '') ?>" placeholder="Mario Rossi"></div>
        <div class="row c2">
          <div class="fld"><label>Numero ordine</label>
            <input class="inp" name="order_ref" value="<?= e($ed['order_ref'] ?? '') ?>" placeholder="#1042"></div>
          <div class="fld"><label>Email</label>
            <input class="inp" type="email" name="email" value="<?= e($ed['email'] ?? '') ?>" placeholder="cliente@email.it"></div>
        </div>
        <div class="fld"><label>Scadenza <span class="muted">(facoltativa)</span></label>
          <input class="inp" type="date" name="expires_at" value="<?= e($ed['expires_at'] ?? '') ?>">
          <div class="hint">Vuoto = accesso a vita.</div></div>
        <?php if (!$ed):
          $cats = db()->query('SELECT id,title FROM categories ORDER BY pos,id')->fetchAll();
          if ($cats): ?>
          <div class="fld"><label>Moduli da sbloccare</label>
            <?php foreach ($cats as $i => $k): ?>
              <label class="chk" style="margin-top:9px"><input type="checkbox" name="mods[]"
                value="<?= (int)$k['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= e($k['title']) ?></label>
            <?php endforeach; ?>
            <div class="hint">Se non ne spunti nessuno il cliente entra ma non vede niente.
              Per gli upsell spunta anche il modulo corrispondente.</div></div>
        <?php endif; endif; ?>
        <button class="btn w"><?= $ed?'Salva':'Genera' ?></button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h3>Come funziona</h3></div>
    <div class="bd" style="font-size:14.5px;color:var(--ink2);line-height:1.7">
      <p style="margin-bottom:12px"><b style="color:var(--ink)">1.</b> Arriva l'ordine sullo store.</p>
      <p style="margin-bottom:12px"><b style="color:var(--ink)">2.</b> Generi un codice qui, ci scrivi nome e numero d'ordine così sai di chi è.</p>
      <p style="margin-bottom:12px"><b style="color:var(--ink)">3.</b> Lo incolli nella mail di conferma ordine di Shopify.</p>
      <p style="margin-bottom:14px"><b style="color:var(--ink)">4.</b> Il cliente lo digita e entra. Nient'altro da fare.</p>
      <div class="msg info" style="margin:0">Se un codice gira dove non deve, premi <b>Revoca</b>: l'accesso si chiude subito, anche a sessione aperta.</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Codici emessi</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if ($dasoll): ?>
        <button class="btn sm" id="soll" data-ids="<?= e(json_encode(array_map('intval', $dasoll))) ?>">
          Sollecita chi non è entrato (<?= count($dasoll) ?>)</button>
      <?php endif; ?>
      <form method="get" style="display:flex;gap:8px">
        <input class="inp" name="q" value="<?= e($f) ?>" placeholder="Cerca codice, nome, telefono…" style="width:210px;padding:8px 11px;font-size:14px">
        <button class="btn gh sm">Cerca</button>
      </form>
    </div>
  </div>
  <?php if ($dasoll): ?>
    <div class="bd" style="padding-top:0"><p class="muted" style="font-size:13.5px;margin:0" id="sollnota">
      Manda di nuovo il codice a chi non ha mai fatto accesso. Ognuno riceve il testo del
      sollecito e sulla sua riga compare a che mail siamo arrivati.</p></div>
  <?php endif; ?>
  <?php if (!$rows): ?><div class="bd"><p class="muted"><?= $f ? 'Nessun risultato.' : 'Nessun codice generato.' ?></p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Codice</th><th>Cliente</th><th>Ordine</th><th>Stato</th><th>Email</th><th>Accessi</th><th>Avanzamento</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): $rev = $r['status']!=='active';
      $scad = $r['expires_at'] && $r['expires_at'] < date('Y-m-d'); ?>
      <tr>
        <td><span class="codecell"><?= e($r['code']) ?></span></td>
        <td>
          <?php $nome = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')); ?>
          <b><?= e($nome ?: ($r['label'] ?: '—')) ?></b>
          <?php if (!$r['prof_at']): ?>
            <span class="pill warn" style="margin-left:6px">dati mancanti</span>
          <?php endif; ?>
          <?php if ($r['phone']): ?>
            <div class="muted mono" style="font-size:12px"><?= e($r['phone_cc'].' '.$r['phone']) ?></div>
          <?php endif; ?>
          <?php if ($r['email']): ?>
            <div class="muted" style="font-size:12px"><?= e($r['email']) ?></div><?php endif; ?>
        </td>
        <td class="muted"><?= e($r['order_ref'] ?: '—') ?></td>
        <td><?php if ($rev): ?><span class="pill bad">revocato</span>
            <?php elseif ($scad): ?><span class="pill warn">scaduto</span>
            <?php elseif ($r['first_used_at']): ?><span class="pill ok">in uso</span>
            <?php else: ?><span class="pill">non usato</span><?php endif; ?></td>
        <td><?php $ne = (int)$r['email_count'];
            if (!$r['email']): ?><span class="muted" style="font-size:12.5px">—</span>
            <?php elseif ($r['email_sent_at']): ?>
              <span class="pill <?= $ne > 1 ? 'warn' : 'ok' ?>"><?= $ne > 1 ? $ne.'ª email' : 'inviata' ?></span>
              <div class="muted" style="font-size:11.5px;margin-top:3px"><?= e(date('d/m/y', strtotime((string)$r['email_sent_at']))) ?></div>
            <?php elseif ($r['email_error']): ?><span class="pill bad" title="<?= e($r['email_error']) ?>">fallita</span>
            <?php elseif ($r['first_used_at']): ?>
              <span class="muted" style="font-size:12.5px"
                    title="Non gliel'abbiamo mandata da qui, ma e' entrato lo stesso: il codice l'ha ricevuto altrove.">non serve</span>
            <?php else: ?><span class="pill">da inviare</span><?php endif; ?>
            <?php if ($r['contacted_at']): ?>
              <div class="muted" style="font-size:11.5px;margin-top:4px"
                   title="Gli hai scritto tu da Gmail">scritto a mano <?= e(date('d/m', strtotime((string)$r['contacted_at']))) ?></div>
            <?php endif; ?></td>
        <td class="muted mono"><?= (int)$r['uses'] ?><?php if($r['last_used_at']): ?>
              <div style="font-size:11.5px"><?= e(date('d/m/y', strtotime($r['last_used_at']))) ?></div><?php endif; ?></td>
        <td class="muted mono"><?= $totLes ? ((int)$r['done'].' / '.$totLes) : '—' ?></td>
        <td><div class="ac">
          <?php if ($r['email']): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="mail"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn gh sm"><?= $r['email_sent_at'] ? 'Rimanda' : 'Invia email' ?></button></form>
          <?php endif; ?>
          <?php if (!$r['first_used_at'] && $r['email']): $gia = (bool)$r['contacted_at']; ?>
            <a class="btn <?= $gia ? 'ok' : 'dg' ?> sm" href="scrivi.php?id=<?= (int)$r['id'] ?>"
               target="_blank" rel="noopener"
               title="<?= $gia
                   ? 'Gli hai gia\' scritto il '.e(date('d/m/Y', strtotime((string)$r['contacted_at']))).'. Premi per riaprire il messaggio.'
                   : 'Da scrivere: apre Gmail col messaggio gia\' pronto, nome, codice e link' ?>">Gmail</a>
          <?php endif; ?>
          <a class="btn gh sm" href="cliente.php?id=<?= (int)$r['id'] ?>">Scheda</a>
          <a class="btn gh sm" href="codici.php?edit=<?= (int)$r['id'] ?>">Modifica</a>
          <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn <?= $rev?'gh':'dg' ?> sm"><?= $rev?'Riattiva':'Revoca' ?></button></form>
          <form method="post" onsubmit="return confirm('Eliminare il codice <?= e($r['code']) ?>? Si perde anche il suo avanzamento.')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn dg sm">×</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<script>
// ogni azione porta con se' la ricerca in corso e il punto in cui sei:
// il server te li rimanda indietro e la pagina si riposiziona da sola
(function () {
  var Q = <?= json_encode($f) ?>;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.matches('form[method="post"]')) return;
    [['sc', Math.round(window.scrollY)], ['q', Q]].forEach(function (kv) {
      if (f.querySelector('[name="' + kv[0] + '"]')) return;
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = kv[0]; i.value = kv[1];
      f.appendChild(i);
    });
  }, true);
  var sc = parseInt(new URLSearchParams(location.search).get('sc') || '', 10);
  if (sc > 0) window.scrollTo(0, sc);
})();

// Il bottone Gmail diventa verde appena lo premi: la scheda dell'elenco resta
// aperta mentre scrivi di la', e cosi' vedi subito chi hai gia' gestito.
document.addEventListener('click', function (e) {
  var a = e.target.closest('a[href^="scrivi.php"]');
  if (!a) return;
  a.classList.remove('dg'); a.classList.add('ok');
  a.title = 'Gli hai appena scritto. Premi per riaprire il messaggio.';
});

// Sollecito: le mail partono una per volta, cosi' nessuna richiesta resta
// appesa mezzo minuto e vedi il conteggio salire mentre vanno.
(function () {
  var b = document.getElementById('soll');
  if (!b) return;
  var nota = document.getElementById('sollnota'), testo = b.textContent.trim();
  var ids = JSON.parse(b.dataset.ids), CSRF = <?= json_encode(csrf()) ?>;

  b.addEventListener('click', function () {
    if (!confirm('Rimandare il codice a ' + ids.length + ' clienti che non sono mai entrati?')) return;
    b.disabled = true;
    var fatte = 0, errori = [];

    function prossimo(i) {
      if (i >= ids.length) return fine();
      b.textContent = 'Invio… ' + (i + 1) + ' di ' + ids.length;
      fetch('codici.php', {
        method: 'POST',
        body: new URLSearchParams({csrf: CSRF, action: 'mail_uno', id: ids[i]})
      })
        .then(function (r) { return r.json(); })
        .catch(function () { return {error: 'rete non raggiungibile'}; })
        .then(function (j) {
          if (j && j.ok) fatte++; else errori.push((j && j.chi ? j.chi + ': ' : '') + ((j && j.error) || 'errore'));
          prossimo(i + 1);
        });
    }
    function fine() {
      b.textContent = testo;
      nota.textContent = fatte + ' email inviate'
        + (errori.length ? ', ' + errori.length + ' non partite (' + errori[0] + ')' : '')
        + '. Ricarico la pagina…';
      setTimeout(function () { location.reload(); }, 1600);
    }
    prossimo(0);
  });
})();
</script>
<?php afoot();
