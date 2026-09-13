<?php
require_once __DIR__ . '/inc.php';
require_once dirname(__DIR__) . '/inc/mailer.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';
    if ($a === 'save') {
        foreach (['course_name','site_url','shopify_secret','shopify_match',
                  'smtp_host','smtp_port','smtp_sec','smtp_user','smtp_from','smtp_name',
                  'mail_subject','mail_intro','mail_sign'] as $k) {
            if (array_key_exists($k, $_POST)) set_setting($k, trim((string)$_POST[$k]));
        }
        // la password si tocca solo se ne scrivi una nuova
        if (trim((string)($_POST['smtp_pass'] ?? '')) !== '') set_setting('smtp_pass', trim((string)$_POST['smtp_pass']));
        flash('Impostazioni salvate.');
    } elseif ($a === 'test') {
        $to = trim((string)$_POST['test_to']);
        try {
            invia_codice(['code'=>'3DWL-TEST-0000','label'=>'Prova','order_ref'=>'#0000','email'=>$to]);
            flash("Email di prova inviata a $to. Controlla anche lo spam.");
        } catch (Throwable $e) { flash('Invio fallito: ' . $e->getMessage(), 'err'); }
    }
    back('impostazioni.php');
}

$g = fn(string $k, string $d = '') => (string)setting($k, $d);
$hooks = db()->query('SELECT * FROM hooks ORDER BY id DESC LIMIT 25')->fetchAll();
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
     . '://' . ($_SERVER['HTTP_HOST'] ?? 'tuodominio') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/shopify.php';

ahead('Impostazioni', 'impostazioni.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Impostazioni</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">Collegamento con Shopify e invio automatico del codice al cliente.</p>

<form method="post">
<input type="hidden" name="csrf" value="<?= csrf() ?>">
<input type="hidden" name="action" value="save">

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Collegamento a Shopify</h3></div>
  <div class="bd">
    <div class="msg info" style="margin-bottom:16px">
      <b>Indirizzo del webhook</b> — incollalo in Shopify:<br>
      <code class="mono" style="display:inline-block;margin-top:7px;background:#0a0c14;border:1px solid #1f2536;border-radius:6px;padding:8px 11px;font-size:13.5px;word-break:break-all"><?= e($url) ?></code>
      <div style="margin-top:11px;font-size:13.5px;line-height:1.65">
        Shopify → <b>Impostazioni</b> → <b>Notifiche</b> → <b>Webhook</b> → <b>Crea webhook</b><br>
        Evento: <b>Ordine pagato</b> · Formato: <b>JSON</b> · Versione API: l'ultima disponibile
      </div>
    </div>
    <div class="row c2">
      <div class="fld"><label>Chiave di firma di Shopify</label>
        <input class="inp mono" name="shopify_secret" value="<?= e($g('shopify_secret')) ?>" placeholder="incolla qui la chiave che ti mostra Shopify">
        <div class="hint">Shopify la mostra dopo aver creato il webhook. Senza, ogni chiamata viene rifiutata: è ciò che impedisce a chiunque di generare codici finti.</div></div>
      <div class="fld"><label>Quali ordini danno accesso</label>
        <input class="inp" name="shopify_match" value="<?= e($g('shopify_match')) ?>" placeholder="es. 8123456789012, CORSO3D">
        <div class="hint">ID prodotto, SKU o parte del titolo, separati da virgola. <b>Vuoto = ogni ordine</b> genera un codice.</div></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Invio delle email</h3></div>
  <div class="bd">
    <div class="row c3">
      <div class="fld"><label>Server SMTP</label>
        <input class="inp" name="smtp_host" value="<?= e($g('smtp_host')) ?>" placeholder="smtp-relay.brevo.com"></div>
      <div class="fld"><label>Porta</label>
        <input class="inp" name="smtp_port" value="<?= e($g('smtp_port','587')) ?>" placeholder="587"></div>
      <div class="fld"><label>Sicurezza</label>
        <select class="inp" name="smtp_sec">
          <option value="tls" <?= $g('smtp_sec','tls')==='tls'?'selected':'' ?>>STARTTLS (porta 587)</option>
          <option value="ssl" <?= $g('smtp_sec')==='ssl'?'selected':'' ?>>SSL diretto (porta 465)</option>
        </select></div>
    </div>
    <div class="row c2">
      <div class="fld"><label>Utente SMTP</label>
        <input class="inp" name="smtp_user" value="<?= e($g('smtp_user')) ?>" autocomplete="off"></div>
      <div class="fld"><label>Password o chiave SMTP</label>
        <input class="inp" type="password" name="smtp_pass" placeholder="<?= $g('smtp_pass') ? '•••••••• già salvata' : 'incolla qui' ?>" autocomplete="new-password">
        <div class="hint">Lascia vuoto per non cambiarla.</div></div>
    </div>
    <div class="row c2">
      <div class="fld"><label>Indirizzo mittente</label>
        <input class="inp" name="smtp_from" value="<?= e($g('smtp_from')) ?>" placeholder="supporto@3dweblab.it">
        <div class="hint">Dev'essere un mittente autorizzato dal servizio, altrimenti l'invio viene rifiutato.</div></div>
      <div class="fld"><label>Nome mittente</label>
        <input class="inp" name="smtp_name" value="<?= e($g('smtp_name','3D WEB LAB')) ?>"></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Il testo della mail</h3></div>
  <div class="bd">
    <div class="row c2">
      <div class="fld"><label>Nome del corso</label>
        <input class="inp" name="course_name" value="<?= e($g('course_name','3D WEB LAB')) ?>"></div>
      <div class="fld"><label>Indirizzo della piattaforma</label>
        <input class="inp" name="site_url" value="<?= e($g('site_url')) ?>" placeholder="https://corso.3dweblab.it">
        <div class="hint">Finisce nel pulsante «Entra nel corso». Vuoto = indirizzo attuale.</div></div>
    </div>
    <div class="fld"><label>Oggetto</label>
      <input class="inp" name="mail_subject" value="<?= e($g('mail_subject','Il tuo accesso a {corso}')) ?>"></div>
    <div class="fld"><label>Testo di apertura</label>
      <textarea class="inp" name="mail_intro" style="min-height:96px"><?= e($g('mail_intro', "Grazie per il tuo acquisto.\nEcco il codice per entrare nel corso. Non serve registrarsi: basta inserirlo nella pagina di accesso.")) ?></textarea>
      <div class="hint">Segnaposto disponibili: <b>{nome}</b> {codice} {link} {ordine} {corso}</div></div>
    <div class="fld"><label>Firma</label>
      <textarea class="inp" name="mail_sign" style="min-height:64px"><?= e($g('mail_sign', "3D WEB LAB\nPer qualsiasi problema scrivi a supporto@3dweblab.it")) ?></textarea></div>
    <button class="btn">Salva impostazioni</button>
  </div>
</div>
</form>

<div class="card" style="margin-bottom:18px">
  <div class="hd"><h3>Prova l'invio</h3></div>
  <div class="bd">
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="test">
      <div class="fld" style="flex:1;min-width:230px;margin:0"><label>Manda una mail di prova a</label>
        <input class="inp" type="email" name="test_to" required placeholder="tua@email.it"></div>
      <button class="btn gh">Invia prova</button>
    </form>
    <p class="hint" style="margin-top:10px">Salva prima le impostazioni. Arriva una mail identica a quella dei clienti, con il codice finto 3DWL-TEST-0000.</p>
  </div>
</div>

<div class="card">
  <div class="hd"><h3>Ultimi ordini ricevuti da Shopify</h3><span class="pill"><?= count($hooks) ?></span></div>
  <?php if (!$hooks): ?>
    <div class="bd"><p class="muted">Nessuna chiamata ricevuta. Comparirà qui ogni ordine che Shopify invia, anche quelli rifiutati: è il posto dove guardare se qualcosa non funziona.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>Quando</th><th>Ordine</th><th>Email</th><th>Esito</th><th>Dettaglio</th></tr></thead>
    <tbody><?php foreach ($hooks as $h):
      $cl = match($h['status']) { 'inviato'=>'ok', 'duplicato','ignorato'=>'', default=>'bad' }; ?>
      <tr><td class="muted mono"><?= e(date('d/m H:i', strtotime($h['created_at']))) ?></td>
          <td><?= e($h['order_name'] ?: '—') ?></td>
          <td class="muted"><?= e($h['email'] ?: '—') ?></td>
          <td><span class="pill <?= $cl ?>"><?= e($h['status']) ?></span></td>
          <td class="muted" style="font-size:13px"><?= e($h['note']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php afoot();
