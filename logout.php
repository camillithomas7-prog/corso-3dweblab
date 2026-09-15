<?php
require_once __DIR__ . '/inc/layout.php';
boot_session();

// L'uscita avviene solo su POST: cosi' un link seguito per sbaglio, o preletto
// dal browser, non scollega nessuno. Su GET si chiede conferma.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    unset($_SESSION['code_id']);
    session_destroy();
    header('Location: index.php');
    exit;
}
if (!current_code()) { header('Location: index.php'); exit; }

head('Esci'); ?>
<div class="auth"><div class="authbox">
  <img class="logo" src="assets/img/logo-light.png" alt="3D WEB LAB">
  <div class="card">
    <h1>Vuoi uscire dal corso?</h1>
    <p class="sub">Per rientrare ti servirà di nuovo il tuo codice di accesso.
      Se volevi solo tornare indietro, resta pure qui.</p>
    <a class="btn w" href="corso.php">Resta nel corso</a>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <button class="btn dg w" type="submit">Sì, esci</button>
    </form>
  </div>
</div></div>
<?php foot();
