<?php
require_once __DIR__ . '/inc.php';
require_admin();

/** "3:38" → 218 · "218" → 218 */
function a_secondi(string $t): int {
    $t = trim($t);
    if (preg_match('/^(\d+)\s*[:.]\s*(\d{1,2})$/', $t, $m)) return (int)$m[1] * 60 + (int)$m[2];
    return max(0, (int)$t);
}
function da_secondi(int $s): string { return intdiv($s, 60) . ':' . str_pad((string)($s % 60), 2, '0', STR_PAD_LEFT); }

$qz = db()->query('SELECT * FROM vquiz ORDER BY id LIMIT 1')->fetch();
if (!$qz) {
    db()->prepare("INSERT INTO vquiz(lesson_id,at_sec,intro,chiusura,published) VALUES(null,218,?,?,0)")
       ->execute(['Un attimo solo: due domande e riprendi.', 'Grazie, lo leggiamo davvero. Ora puoi riprendere il video.']);
    $qz = db()->query('SELECT * FROM vquiz ORDER BY id LIMIT 1')->fetch();
}
$qid = (int)$qz['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = $_POST['action'] ?? '';

    if ($a === 'conf') {
        $les = (int)($_POST['lesson_id'] ?? 0) ?: null;
        $sec = a_secondi((string)($_POST['at'] ?? '0'));
        $pub = isset($_POST['published']) ? 1 : 0;
        if ($pub && !$les) { flash('Scegli su quale lezione deve comparire.', 'err'); back('sondaggio.php'); }
        db()->prepare('UPDATE vquiz SET lesson_id=?, at_sec=?, intro=?, chiusura=?, published=? WHERE id=?')
           ->execute([$les, $sec, trim((string)$_POST['intro']), trim((string)$_POST['chiusura']), $pub, $qid]);
        // da qui in poi comanda la tua scelta, non l'aggancio automatico
        db()->prepare('INSERT OR IGNORE INTO settings(k,v) VALUES(?,?)')
           ->execute(['vquiz_agganciato', 'scelto a mano · ' . date('c')]);
        flash($pub ? 'Sondaggio attivo su questa lezione.' : 'Sondaggio salvato e spento.');

    } elseif ($a === 'dsave') {
        $did  = (int)($_POST['did'] ?? 0);
        $lab  = trim((string)$_POST['label']);
        if ($lab === '') { flash('Serve il testo della domanda.', 'err'); back('sondaggio.php'); }
        $f = [$_POST['type'] === 'text' ? 'text' : 'single', $lab, trim((string)$_POST['help']),
              trim((string)$_POST['options']), isset($_POST['altro']) ? 1 : 0,
              (int)($_POST['show_if_q'] ?? 0) ?: null, trim((string)$_POST['show_if_v']),
              isset($_POST['required']) ? 1 : 0, (int)($_POST['pos'] ?? 0)];
        if ($did) {
            db()->prepare('UPDATE vq_questions SET type=?,label=?,help=?,options=?,altro=?,
                           show_if_q=?,show_if_v=?,required=?,pos=? WHERE id=? AND quiz_id=?')
               ->execute([...$f, $did, $qid]);
            flash('Domanda aggiornata.');
        } else {
            db()->prepare('INSERT INTO vq_questions(type,label,help,options,altro,show_if_q,show_if_v,required,pos,quiz_id)
                           VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([...$f, $qid]);
            flash('Domanda aggiunta.');
        }
    } elseif ($a === 'ddel') {
        db()->prepare('DELETE FROM vq_questions WHERE id=? AND quiz_id=?')->execute([(int)$_POST['did'], $qid]);
        flash('Domanda eliminata.');
    } elseif ($a === 'azzera') {
        db()->prepare('DELETE FROM vq_done WHERE quiz_id=?')->execute([$qid]);
        flash('Il sondaggio ricomparirà a tutti. Le risposte già date restano.');
    }
    back('sondaggio.php');
}

$lezioni = db()->query("SELECT l.id, l.title, c.title AS cat, l.duration_sec, l.video_type
                        FROM lessons l LEFT JOIN categories c ON c.id=l.category_id
                        WHERE l.video_type IN ('file','url') AND l.video_src<>''
                        ORDER BY c.pos, c.id, l.pos, l.id")->fetchAll();
$dom = db()->prepare('SELECT * FROM vq_questions WHERE quiz_id=? ORDER BY pos, id');
$dom->execute([$qid]); $dom = $dom->fetchAll();

$ed = null;
if ($e = (int)($_GET['d'] ?? 0)) foreach ($dom as $d) if ((int)$d['id'] === $e) $ed = $d;

$fatti = (int)db()->query("SELECT COUNT(*) FROM vq_done WHERE quiz_id=$qid")->fetchColumn();

ahead('Sondaggio', 'sondaggio.php'); show_flash(); ?>
<h1 style="font-size:23px;margin-bottom:6px">Sondaggio dentro il video</h1>
<p class="muted" style="margin-bottom:20px;font-size:14.5px">
  Al minuto che scegli il video si ferma e compare il sondaggio. Non si può chiudere né saltare:
  chi prova ad andare avanti viene riportato indietro a quel punto.</p>

<?php if (!$lezioni): ?>
  <div class="msg err"><b>Nessuna lezione utilizzabile.</b> Serve una lezione con un video caricato
    o con un link diretto. Sui video incorporati (iframe) non posso fermare la riproduzione.</div>
<?php endif; ?>

<div class="row c2" style="align-items:start;margin-bottom:22px">
  <div class="card">
    <div class="hd"><h3>Dove e quando</h3>
      <?php if ($qz['published']): ?><span class="pill ok">attivo</span>
      <?php else: ?><span class="pill">spento</span><?php endif; ?></div>
    <div class="bd">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="conf">

        <div class="fld"><label>Su quale lezione</label>
          <select class="inp" name="lesson_id">
            <option value="0">— nessuna, sondaggio spento —</option>
            <?php foreach ($lezioni as $l): ?>
              <option value="<?= (int)$l['id'] ?>" <?= (int)$qz['lesson_id'] === (int)$l['id'] ? 'selected' : '' ?>>
                <?= e(($l['cat'] ? $l['cat'].' · ' : '') . $l['title']) ?><?= (int)$l['duration_sec'] ? ' ('.hms((int)$l['duration_sec']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select></div>

        <div class="fld"><label>A che minuto</label>
          <input class="inp mono" name="at" value="<?= e(da_secondi((int)$qz['at_sec'])) ?>" placeholder="3:38">
          <div class="hint">Minuti e secondi, come nel lettore. Metti un punto prima della fine del video.</div></div>

        <div class="fld"><label>Titolo del riquadro</label>
          <input class="inp" name="intro" value="<?= e($qz['intro']) ?>"
                 placeholder="Un attimo solo: due domande e riprendi."></div>

        <div class="fld"><label>Messaggio finale</label>
          <input class="inp" name="chiusura" value="<?= e($qz['chiusura']) ?>"
                 placeholder="Grazie, lo leggiamo davvero. Ora puoi riprendere il video."></div>

        <label class="chk" style="margin-top:4px"><input type="checkbox" name="published"
          <?= $qz['published'] ? 'checked' : '' ?>> Attivo</label>

        <button class="btn w" style="margin-top:16px">Salva</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="hd"><h3>Stato</h3><span class="pill"><?= $fatti ?> compilati</span></div>
    <div class="bd" style="font-size:14.5px;color:var(--ink2);line-height:1.7">
      <?php if (!$qz['lesson_id']): ?>
        <div class="msg info" style="margin:0 0 12px">Scegli una lezione e spunta <b>Attivo</b> per farlo partire.</div>
      <?php elseif (!$qz['published']): ?>
        <div class="msg info" style="margin:0 0 12px">È configurato ma <b>spento</b>: nessuno lo vede.</div>
      <?php else: ?>
        <div class="msg ok" style="margin:0 0 12px">Compare a <b><?= e(da_secondi((int)$qz['at_sec'])) ?></b>
          di quella lezione, a chi non l'ha ancora compilato.</div>
      <?php endif; ?>
      <p style="margin-bottom:12px">Ognuno lo vede <b style="color:var(--ink)">una volta sola</b>.
        Le risposte le trovi qui sotto e nella scheda di ogni cliente.</p>
      <?php if ($fatti): ?>
        <form method="post" onsubmit="return confirm('Il sondaggio ricomparirà a tutti i corsisti. Procedere?')">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="azzera">
          <button class="btn gh sm">Fallo ricomparire a tutti</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3><?= $ed ? 'Modifica domanda' : 'Nuova domanda' ?></h3>
    <?php if ($ed): ?><a class="btn gh sm" href="sondaggio.php">Annulla</a><?php endif; ?></div>
  <div class="bd">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="dsave">
      <?php if ($ed): ?><input type="hidden" name="did" value="<?= (int)$ed['id'] ?>"><?php endif; ?>

      <div class="fld"><label>Domanda</label>
        <input class="inp" name="label" required value="<?= e($ed['label'] ?? '') ?>"
               placeholder="Ti interesserebbe un corso dedicato all'e-commerce?"></div>

      <div class="fld"><label>Nota sopra la domanda <span class="muted">(facoltativa)</span></label>
        <input class="inp" name="help" value="<?= e($ed['help'] ?? '') ?>"
               placeholder="Ci aiuti a capire? Sii sincero, ci serve."></div>

      <div class="row c2">
        <div class="fld"><label>Tipo</label>
          <select class="inp" name="type">
            <option value="single" <?= ($ed['type'] ?? '') === 'single' ? 'selected' : '' ?>>Scelta fra opzioni</option>
            <option value="text" <?= ($ed['type'] ?? '') === 'text' ? 'selected' : '' ?>>Risposta libera</option>
          </select></div>
        <div class="fld"><label>Ordine</label>
          <input class="inp" type="number" name="pos" value="<?= (int)($ed['pos'] ?? (count($dom) + 1)) ?>"></div>
      </div>

      <div class="fld"><label>Opzioni <span class="muted">(una per riga)</span></label>
        <textarea class="inp" name="options" rows="4" placeholder="Sì&#10;No"><?= e($ed['options'] ?? '') ?></textarea>
        <label class="chk" style="margin-top:9px"><input type="checkbox" name="altro"
          <?= !empty($ed['altro']) ? 'checked' : '' ?>> Aggiungi «Altro» con campo libero</label></div>

      <div class="row c2">
        <div class="fld"><label>Compare solo se…</label>
          <select class="inp" name="show_if_q">
            <option value="0">— sempre —</option>
            <?php foreach ($dom as $d): if ($ed && (int)$d['id'] === (int)$ed['id']) continue; ?>
              <option value="<?= (int)$d['id'] ?>" <?= (int)($ed['show_if_q'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>>
                <?= e(mb_substr($d['label'], 0, 46)) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="fld"><label>…vale esattamente</label>
          <input class="inp" name="show_if_v" value="<?= e($ed['show_if_v'] ?? '') ?>" placeholder="No"></div>
      </div>

      <label class="chk"><input type="checkbox" name="required"
        <?= !$ed || $ed['required'] ? 'checked' : '' ?>> Obbligatoria</label>

      <button class="btn w" style="margin-top:16px"><?= $ed ? 'Salva domanda' : 'Aggiungi domanda' ?></button>
    </form>
  </div>
</div>

<div class="card" style="margin-bottom:22px">
  <div class="hd"><h3>Domande</h3><span class="pill"><?= count($dom) ?></span></div>
  <?php if (!$dom): ?><div class="bd"><p class="muted">Nessuna domanda.</p></div>
  <?php else: ?>
  <div class="tw"><table class="tb">
    <thead><tr><th>#</th><th>Domanda</th><th>Tipo</th><th>Condizione</th><th></th></tr></thead>
    <tbody><?php foreach ($dom as $d): ?>
      <tr>
        <td class="muted mono"><?= (int)$d['pos'] ?></td>
        <td><b><?= e($d['label']) ?></b>
          <?php if ($d['help']): ?><br><span class="muted" style="font-size:13px"><?= e($d['help']) ?></span><?php endif; ?></td>
        <td><span class="pill"><?= $d['type'] === 'text' ? 'libera' : 'opzioni' ?></span>
          <?php if ($d['altro']): ?><span class="pill acc">+Altro</span><?php endif; ?></td>
        <td class="muted" style="font-size:13px">
          <?php if ($d['show_if_q']):
            $et = '?'; foreach ($dom as $x) if ((int)$x['id'] === (int)$d['show_if_q']) $et = mb_substr($x['label'], 0, 26);
            echo 'se «' . e($et) . '» = <b>' . e($d['show_if_v']) . '</b>';
          else: echo 'sempre'; endif; ?></td>
        <td><div class="ac">
          <a class="btn gh sm" href="sondaggio.php?d=<?= (int)$d['id'] ?>">Modifica</a>
          <form method="post" onsubmit="return confirm('Eliminare questa domanda e le sue risposte?')">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="ddel"><input type="hidden" name="did" value="<?= (int)$d['id'] ?>">
            <button class="btn dg sm">Elimina</button></form>
        </div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="hd"><h3>Risposte</h3><span class="pill"><?= $fatti ?></span></div>
  <div class="bd">
    <?php if (!$fatti): ?>
      <p class="muted">Ancora nessuna risposta. Compariranno qui appena qualcuno arriva a quel punto del video.</p>
    <?php else: foreach ($dom as $d):
      $c = db()->prepare('SELECT COUNT(*) FROM vq_answers WHERE question_id=? AND value<>\'\'');
      $c->execute([(int)$d['id']]); $tot = (int)$c->fetchColumn();
    ?>
      <div style="margin-bottom:24px">
        <p style="font-weight:600;margin-bottom:10px"><?= e($d['label']) ?>
          <span class="muted" style="font-weight:400;font-size:13px">· <?= $tot ?> risposte</span></p>
        <?php if (!$tot): ?>
          <p class="muted" style="font-size:14px">Nessuna risposta a questa domanda.</p>
        <?php elseif ($d['type'] === 'single'):
          $r = db()->prepare("SELECT value, COUNT(*) n FROM vq_answers
                              WHERE question_id=? AND value<>'' GROUP BY value ORDER BY n DESC");
          $r->execute([(int)$d['id']]);
          foreach ($r as $x): $pc = (int)round($x['n'] / $tot * 100); ?>
            <div class="brow">
              <span class="bl"><?= e($x['value']) ?></span>
              <span class="bt"><i style="width:<?= $pc ?>%"></i></span>
              <span class="bv"><?= $pc ?>% <em><?= (int)$x['n'] ?></em></span>
            </div>
          <?php endforeach;
        else:
          $r = db()->prepare("SELECT a.value, c.code, p.first_name, p.last_name
                              FROM vq_answers a JOIN codes c ON c.id=a.code_id
                              LEFT JOIN profiles p ON p.code_id=c.id
                              WHERE a.question_id=? AND a.value<>'' ORDER BY a.updated_at DESC");
          $r->execute([(int)$d['id']]); ?>
          <div style="display:flex;flex-direction:column;gap:9px">
            <?php foreach ($r as $x): ?>
              <div style="padding:11px 13px;border-radius:9px;background:var(--card2);border:1px solid var(--line2)">
                <p style="margin:0 0 5px;font-size:14.5px;line-height:1.55"><?= nl2br(e($x['value'])) ?></p>
                <span class="muted" style="font-size:12px">
                  <?= e(trim(($x['first_name'] ?? '').' '.($x['last_name'] ?? '')) ?: $x['code']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php afoot();
