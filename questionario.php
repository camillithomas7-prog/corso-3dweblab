<?php
require_once __DIR__ . '/inc/layout.php';
boot_session();
$code = current_code();
if (!$code) { header('Location: index.php'); exit; }
if (!profile_done((int)$code['id'])) { header('Location: profilo.php'); exit; }

$qs = db()->query('SELECT * FROM questions WHERE published=1 ORDER BY pos, id')->fetchAll();
if (!$qs) { header('Location: corso.php'); exit; }

$fatto = quiz_done((int)$code['id']);
$val = [];
$s = db()->prepare('SELECT question_id, value FROM answers WHERE code_id=?');
$s->execute([$code['id']]);
foreach ($s->fetchAll() as $r) $val[(int)$r['question_id']] = $r['value'];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($qs as $q) {
        $id = (int)$q['id'];
        $v  = trim((string)($_POST['q'][$id] ?? ''));
        $val[$id] = $v;
        if (!$q['required']) continue;
        if ($v === '') {
            $err[$id] = $q['type'] === 'single' ? 'Scegli una risposta.' : 'Questa risposta ci serve.';
        } elseif ($q['type'] === 'single') {
            $opz = array_map('trim', preg_split('/\r?\n/', trim($q['options'])));
            if (!in_array($v, $opz, true)) $err[$id] = 'Scegli una delle risposte elencate.';
        } elseif (mb_strlen($v) < 3) {
            $err[$id] = 'Scrivi qualcosa in più, anche solo una frase.';
        }
    }
    if (!$err) {
        $ins = db()->prepare("INSERT INTO answers(code_id,question_id,value,updated_at)
                              VALUES(?,?,?,datetime('now'))
                              ON CONFLICT(code_id,question_id) DO UPDATE SET
                                value=excluded.value, updated_at=datetime('now')");
        foreach ($qs as $q) $ins->execute([$code['id'], $q['id'], $val[(int)$q['id']] ?? '']);
        db()->prepare("UPDATE profiles SET quiz_at=COALESCE(quiz_at, datetime('now')) WHERE code_id=?")
            ->execute([$code['id']]);
        header('Location: ' . ($fatto ? 'questionario.php?ok=1' : 'corso.php')); exit;
    }
}

$sez = [];
foreach ($qs as $q) $sez[$q['section'] ?: 'Domande'][] = $q;
$n = 0;

head('Questionario');
if ($fatto) topbar($code, '', ''); ?>
<div class="wrap qwrap" style="padding-bottom:90px">
  <div class="qhead">
    <?php if (!$fatto): ?><img class="qlogo" src="assets/img/logo-light.png" alt="3D WEB LAB"><?php endif; ?>
    <div class="kicker" style="margin-bottom:9px">Ultimo passaggio</div>
    <h1><?= $fatto ? 'Le tue risposte' : 'Aiutaci a fare un corso migliore' ?></h1>
    <p><?= $fatto
      ? 'Puoi rivederle e correggerle quando vuoi.'
      : 'Nove domande, meno di tre minuti. Ci servono per capire chi sei e per migliorare il corso a ogni versione. Le risposte restano fra noi.' ?></p>
    <?php if (isset($_GET['ok'])): ?><div class="msg ok" style="margin-top:16px">Risposte aggiornate.</div><?php endif; ?>
    <?php if ($err): ?><div class="msg err" style="margin-top:16px">
      Mancano <?= count($err) ?> risposte. Sono tutte obbligatorie: le trovi segnate qui sotto.</div><?php endif; ?>
  </div>

  <form method="post" novalidate>
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <?php foreach ($sez as $titolo => $lista): ?>
      <div class="qsec"><span><?= e((string)$titolo) ?></span></div>
      <?php foreach ($lista as $q): $id = (int)$q['id']; $n++; $bad = isset($err[$id]); ?>
        <div class="qcard<?= $bad ? ' bad' : '' ?>">
          <div class="qn"><?= $n ?></div>
          <div class="qbody">
            <h3><?= e($q['label']) ?></h3>
            <?php if ($q['help']): ?><p class="qhelp"><?= e($q['help']) ?></p><?php endif; ?>

            <?php if ($q['type'] === 'single'):
              $opz = array_filter(array_map('trim', preg_split('/\r?\n/', trim($q['options'])))); ?>
              <div class="qopts">
                <?php foreach ($opz as $k => $o): ?>
                  <label class="qopt<?= ($val[$id] ?? '') === $o ? ' on' : '' ?>">
                    <input type="radio" name="q[<?= $id ?>]" value="<?= e($o) ?>"
                           <?= ($val[$id] ?? '') === $o ? 'checked' : '' ?>>
                    <span class="dot"></span><span class="tx"><?= e($o) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <textarea class="inp qta" name="q[<?= $id ?>]" rows="3"
                        placeholder="Scrivi con parole tue…"><?= e($val[$id] ?? '') ?></textarea>
            <?php endif; ?>

            <?php if ($bad): ?><div class="ferr"><?= e($err[$id]) ?></div><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="qfoot">
      <button class="btn w"><?= $fatto ? 'Salva le modifiche' : 'Ho finito, entra nel corso' ?></button>
      <?php if (!$fatto): ?><p>Tutte le domande sono obbligatorie.</p><?php endif; ?>
    </div>
  </form>
</div>
<?php if ($fatto) mbar(''); foot();
