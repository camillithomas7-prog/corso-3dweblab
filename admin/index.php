<?php
require_once __DIR__ . '/inc.php';
boot_session();
if (current_admin()) back('dashboard.php');

$err = '';
$first = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $em = trim((string)($_POST['email'] ?? ''));
    $pw = (string)($_POST['pass'] ?? '');
    if ($first) {
        if (!filter_var($em, FILTER_VALIDATE_EMAIL)) $err = 'Email non valida.';
        elseif (strlen($pw) < 8) $err = 'La password deve avere almeno 8 caratteri.';
        else {
            db()->prepare('INSERT INTO admins(email,pass_hash) VALUES(?,?)')
                ->execute([$em, password_hash($pw, PASSWORD_DEFAULT)]);
            $_SESSION['admin_id'] = (int)db()->lastInsertId();
            session_regenerate_id(true);
            back('dashboard.php');
        }
    } else {
        $s = db()->prepare('SELECT * FROM admins WHERE email=?'); $s->execute([$em]);
        $a = $s->fetch();
        if ($a && password_verify($pw, $a['pass_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            back('dashboard.php');
        }
        $err = 'Email o password non corrette.';
        usleep(400000);
    }
}
?>
<!doctype html><html lang="it"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Admin · 3D WEB LAB</title>
<link rel="icon" href="../assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/app.css">
</head><body>
<div class="auth"><div class="authbox">
  <img class="logo" src="../assets/img/logo-light.png" alt="3D WEB LAB">
  <div class="card">
    <h1><?= $first ? 'Crea il tuo accesso admin' : 'Pannello di gestione' ?></h1>
    <p class="sub"><?= $first
      ? 'È la prima volta che apri il pannello: scegli email e password. Verranno chieste solo a te.'
      : 'Accedi per caricare lezioni, materiali e generare i codici.' ?></p>
    <?php if ($err): ?><div class="msg err"><?= e($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="fld"><label for="em">Email</label>
        <input class="inp" id="em" type="email" name="email" required autofocus autocomplete="username"></div>
      <div class="fld"><label for="pw">Password</label>
        <input class="inp" id="pw" type="password" name="pass" required
               autocomplete="<?= $first?'new-password':'current-password' ?>"
               <?= $first?'minlength="8"':'' ?>>
        <?php if ($first): ?><div class="hint">Minimo 8 caratteri. Salvala nel gestore password.</div><?php endif; ?></div>
      <button class="btn w" type="submit"><?= $first ? 'Crea accesso' : 'Entra' ?></button>
    </form>
  </div>
</div></div>
</body></html>
