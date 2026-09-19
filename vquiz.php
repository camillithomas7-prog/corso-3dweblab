<?php
/**
 * Risposte al sondaggio che compare dentro un video.
 * POST csrf, quiz, a[<id domanda>] → salva e segna il sondaggio come fatto.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');

$code = current_code();
if (!$code) { http_response_code(401); exit('{"error":"sessione scaduta"}'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('{"error":"solo POST"}'); }
check_csrf();

$qz = (int)($_POST['quiz'] ?? 0);
$s  = db()->prepare('SELECT * FROM vquiz WHERE id=? AND published=1');
$s->execute([$qz]);
if (!$s->fetch()) { http_response_code(404); exit('{"error":"sondaggio non attivo"}'); }

$s = db()->prepare('SELECT * FROM vq_questions WHERE quiz_id=? ORDER BY pos, id');
$s->execute([$qz]);
$domande = $s->fetchAll();

$in = (array)($_POST['a'] ?? []);
$val = [];
foreach ($domande as $d) $val[(int)$d['id']] = trim((string)($in[$d['id']] ?? ''));

// una domanda conta solo se la condizione che la fa comparire e' soddisfatta
$serve = function (array $d) use ($val): bool {
    $q = (int)($d['show_if_q'] ?? 0);
    return !$q || ($val[$q] ?? '') === (string)$d['show_if_v'];
};

foreach ($domande as $d) {
    if (!$serve($d)) { $val[(int)$d['id']] = ''; continue; }   // ramo non percorso: niente da salvare
    if ((int)$d['required'] && $val[(int)$d['id']] === '') {
        http_response_code(422);
        exit(json_encode(['error' => 'manca una risposta', 'q' => (int)$d['id']], JSON_UNESCAPED_UNICODE));
    }
    if (mb_strlen($val[(int)$d['id']]) > 2000) $val[(int)$d['id']] = mb_substr($val[(int)$d['id']], 0, 2000);
}

$p = db();
$p->beginTransaction();
try {
    $w = $p->prepare('INSERT INTO vq_answers(code_id,question_id,value,updated_at) VALUES(?,?,?,datetime("now"))
                      ON CONFLICT(code_id,question_id) DO UPDATE SET value=excluded.value, updated_at=excluded.updated_at');
    foreach ($domande as $d) {
        $v = $val[(int)$d['id']];
        if ($v !== '') $w->execute([(int)$code['id'], (int)$d['id'], $v]);
    }
    $p->prepare('INSERT OR IGNORE INTO vq_done(code_id,quiz_id) VALUES(?,?)')
      ->execute([(int)$code['id'], $qz]);
    $p->commit();
} catch (Throwable $e) {
    $p->rollBack();
    http_response_code(500);
    exit('{"error":"salvataggio non riuscito"}');
}

echo '{"ok":true}';
