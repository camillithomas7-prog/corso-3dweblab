<?php
require_once __DIR__ . '/inc/layout.php';
boot_session();
$code = current_code();
if (!$code) { header('Location: index.php'); exit; }

$p = profile((int)$code['id']) ?: [
    'first_name'=>'', 'last_name'=>'', 'phone_cc'=>'+39', 'phone'=>'',
    'email'=>$code['email'] ?: '', 'birthdate'=>'', 'completed_at'=>null,
];
$done = (bool)$p['completed_at'];
$err = [];
$cc  = dial_codes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $in = [
        'first_name' => trim((string)($_POST['first_name'] ?? '')),
        'last_name'  => trim((string)($_POST['last_name'] ?? '')),
        'phone_cc'   => (string)($_POST['phone_cc'] ?? '+39'),
        'phone'      => preg_replace('/[^\d]/', '', (string)($_POST['phone'] ?? '')),
        'email'      => trim((string)($_POST['email'] ?? '')),
        'birthdate'  => trim((string)($_POST['birthdate'] ?? '')),
    ];
    if (mb_strlen($in['first_name']) < 2) $err['first_name'] = 'Scrivi il tuo nome.';
    if (mb_strlen($in['last_name'])  < 2) $err['last_name']  = 'Scrivi il tuo cognome.';
    if (!isset($cc[$in['phone_cc']]))     $err['phone_cc']   = 'Scegli il paese.';
    if (strlen($in['phone']) < 6 || strlen($in['phone']) > 15)
        $err['phone'] = 'Numero non valido: solo cifre, senza prefisso internazionale.';
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Indirizzo email non valido.';

    $d = DateTime::createFromFormat('Y-m-d', $in['birthdate']);
    if (!$d || $d->format('Y-m-d') !== $in['birthdate']) {
        $err['birthdate'] = 'Inserisci una data valida.';
    } else {
        $age = (int)$d->diff(new DateTime())->y;
        if ($d > new DateTime())  $err['birthdate'] = 'La data non può essere nel futuro.';
        elseif ($age < 14)        $err['birthdate'] = 'Devi avere almeno 14 anni.';
        elseif ($age > 110)       $err['birthdate'] = 'Controlla l\'anno di nascita.';
    }

    $p = array_merge($p, $in);
    if (!$err) {
        db()->prepare("INSERT INTO profiles(code_id,first_name,last_name,phone_cc,phone,email,birthdate,completed_at,updated_at)
                       VALUES(?,?,?,?,?,?,?,datetime('now'),datetime('now'))
                       ON CONFLICT(code_id) DO UPDATE SET
                         first_name=excluded.first_name, last_name=excluded.last_name,
                         phone_cc=excluded.phone_cc, phone=excluded.phone,
                         email=excluded.email, birthdate=excluded.birthdate,
                         completed_at=COALESCE(profiles.completed_at, datetime('now')),
                         updated_at=datetime('now')")
            ->execute([$code['id'], $in['first_name'], $in['last_name'],
                       $in['phone_cc'], $in['phone'], $in['email'], $in['birthdate']]);
        // il nome vero prende il posto dell'etichetta scritta a mano
        db()->prepare('UPDATE codes SET label=?, email=CASE WHEN email=\'\' THEN ? ELSE email END WHERE id=?')
            ->execute([$in['first_name'].' '.$in['last_name'], $in['email'], $code['id']]);
        header('Location: ' . ($done ? 'profilo.php?ok=1' : 'corso.php')); exit;
    }
}

head('I tuoi dati');
if ($done) topbar($code, '', ''); ?>
<div class="<?= $done ? 'wrap' : 'auth' ?>" style="<?= $done ? 'padding-top:26px;padding-bottom:90px' : '' ?>">
<div class="<?= $done ? '' : 'authbox' ?>" style="<?= $done ? 'max-width:640px' : '' ?>">
  <?php if (!$done): ?><img class="logo" src="assets/img/logo-light.png" alt="3D WEB LAB"><?php endif; ?>

  <div class="card" style="padding:26px 22px">
    <?php if (!$done): ?>
      <h1 style="font-size:23px;margin-bottom:8px">Ci manca solo una cosa</h1>
      <p class="sub" style="margin-bottom:22px">Prima di entrare nel corso lasciaci i tuoi dati.
         Ci servono per riconoscerti, assisterti se hai bisogno e intestare correttamente il tuo accesso.</p>
    <?php else: ?>
      <h1 style="font-size:22px;margin-bottom:8px">I tuoi dati</h1>
      <p class="sub" style="margin-bottom:22px">Puoi aggiornarli quando vuoi.</p>
      <?php if (isset($_GET['ok'])): ?><div class="msg ok">Dati aggiornati.</div><?php endif; ?>
    <?php endif; ?>

    <?php if ($err): ?><div class="msg err">Controlla i campi segnati qui sotto.</div><?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <div class="row c2">
        <div class="fld"><label for="fn">Nome</label>
          <input class="inp<?= isset($err['first_name'])?' bad':'' ?>" id="fn" name="first_name"
                 value="<?= e($p['first_name']) ?>" required autocomplete="given-name" autofocus>
          <?php if(isset($err['first_name'])): ?><div class="ferr"><?= e($err['first_name']) ?></div><?php endif; ?></div>
        <div class="fld"><label for="ln">Cognome</label>
          <input class="inp<?= isset($err['last_name'])?' bad':'' ?>" id="ln" name="last_name"
                 value="<?= e($p['last_name']) ?>" required autocomplete="family-name">
          <?php if(isset($err['last_name'])): ?><div class="ferr"><?= e($err['last_name']) ?></div><?php endif; ?></div>
      </div>

      <div class="fld"><label for="ph">Numero di telefono</label>
        <div class="phone">
          <select class="inp" name="phone_cc" aria-label="Prefisso internazionale">
            <?php foreach ($cc as $pre => $paese): ?>
              <option value="<?= e($pre) ?>" <?= $p['phone_cc']===$pre?'selected':'' ?>>
                <?= e($paese) ?> <?= e($pre) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="inp<?= isset($err['phone'])?' bad':'' ?>" id="ph" name="phone" type="tel"
                 inputmode="numeric" value="<?= e($p['phone']) ?>" required
                 autocomplete="tel-national" placeholder="333 1234567">
        </div>
        <?php if(isset($err['phone'])): ?><div class="ferr"><?= e($err['phone']) ?></div>
        <?php else: ?><div class="hint">Solo cifre, senza il prefisso: quello lo scegli a fianco.</div><?php endif; ?></div>

      <div class="row c2">
        <div class="fld"><label for="em">Email</label>
          <input class="inp<?= isset($err['email'])?' bad':'' ?>" id="em" name="email" type="email"
                 value="<?= e($p['email']) ?>" required autocomplete="email">
          <?php if(isset($err['email'])): ?><div class="ferr"><?= e($err['email']) ?></div><?php endif; ?></div>
        <div class="fld"><label for="bd">Data di nascita</label>
          <input class="inp<?= isset($err['birthdate'])?' bad':'' ?>" id="bd" name="birthdate" type="date"
                 value="<?= e($p['birthdate']) ?>" required max="<?= date('Y-m-d') ?>">
          <?php if(isset($err['birthdate'])): ?><div class="ferr"><?= e($err['birthdate']) ?></div><?php endif; ?></div>
      </div>

      <button class="btn w" style="margin-top:6px"><?= $done ? 'Salva modifiche' : 'Entra nel corso' ?></button>
    </form>

    <?php if (!$done): ?>
      <div class="authfoot" style="text-align:left">
        Tutti i campi sono obbligatori. Usiamo questi dati solo per gestire il tuo accesso
        e assisterti: non li cediamo a nessuno.
      </div>
    <?php endif; ?>
  </div>
</div>
</div>
<?php if ($done) { mbar(''); } foot();
