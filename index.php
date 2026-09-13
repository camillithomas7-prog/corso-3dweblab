<?php
require_once __DIR__ . '/inc/layout.php';
boot_session();
if (current_code()) { header('Location: corso.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    // freno agli attacchi a forza bruta
    $_SESSION['tries'] = ($_SESSION['tries'] ?? 0) + 1;
    $_SESSION['t0'] = $_SESSION['t0'] ?? time();
    if ($_SESSION['tries'] > 8 && time() - $_SESSION['t0'] < 300) {
        $err = 'Troppi tentativi. Riprova fra qualche minuto.';
    } else {
        $c = find_code((string)($_POST['code'] ?? ''));
        if (!$c)                       $err = 'Codice non riconosciuto. Controlla di averlo copiato per intero.';
        elseif ($c['status'] !== 'active') $err = 'Questo codice è stato disattivato. Scrivici e lo sistemiamo.';
        elseif ($c['expires_at'] && $c['expires_at'] < date('Y-m-d')) $err = 'Questo codice è scaduto.';
        else {
            db()->prepare("UPDATE codes SET uses=uses+1, last_used_at=datetime('now'),
                           first_used_at=COALESCE(first_used_at, datetime('now')) WHERE id=?")->execute([$c['id']]);
            session_regenerate_id(true);
            $_SESSION['code_id'] = (int)$c['id'];
            unset($_SESSION['tries'], $_SESSION['t0']);
            header('Location: corso.php'); exit;
        }
    }
}
head('Accedi al corso');
?>
<div class="auth"><div class="authbox">
  <img class="logo" src="assets/img/logo-light.png" alt="3D WEB LAB">
  <div class="card">
    <h1>Accedi al corso</h1>
    <p class="sub">Inserisci il codice che hai ricevuto insieme all'ordine. Non serve registrarsi né creare una password.</p>
    <?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="fld">
        <label for="code">Codice di accesso</label>
        <input class="codein" id="code" name="code" placeholder="3DWL-XXXX-XXXX"
               maxlength="20" required autofocus inputmode="text" autocapitalize="characters" spellcheck="false">
      </div>
      <button class="btn w" type="submit">Entra nel corso</button>
    </form>
    <div class="authfoot">
      Il codice è nella mail di conferma dell'ordine.<br>
      Non lo trovi? Scrivi a <a href="mailto:info@3dweblab.it">info@3dweblab.it</a>
    </div>
  </div>
</div></div>
<script>
// formatta il codice mentre lo digiti: 3DWL-ABCD-EFGH
const f=document.getElementById('code');
f.addEventListener('input',e=>{
  let v=e.target.value.toUpperCase().replace(/[^A-Z0-9]/g,'');
  if(v.startsWith('3DWL')) v=v.slice(4);
  let o='3DWL'; if(v.length) o+='-'+v.slice(0,4); if(v.length>4) o+='-'+v.slice(4,8);
  e.target.value=o;
});
f.addEventListener('focus',e=>{ if(!e.target.value) e.target.value='3DWL-'; });
</script>
</body></html>
