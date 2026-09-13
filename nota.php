<?php
// Salvataggio automatico degli appunti del corsista.
require_once __DIR__ . '/inc/auth.php';
$code = current_code();
if (!$code) { http_response_code(401); exit('sessione scaduta'); }
$id   = (int)($_POST['id'] ?? 0);
$body = trim((string)($_POST['body'] ?? ''));
if (!$id) { http_response_code(400); exit; }
if (mb_strlen($body) > 20000) $body = mb_substr($body, 0, 20000);

if ($body === '') {
    db()->prepare('DELETE FROM notes WHERE code_id=? AND lesson_id=?')->execute([$code['id'], $id]);
} else {
    db()->prepare("INSERT INTO notes(code_id,lesson_id,body,updated_at)
                   VALUES(?,?,?,datetime('now'))
                   ON CONFLICT(code_id,lesson_id) DO UPDATE SET
                     body=excluded.body, updated_at=datetime('now')")
        ->execute([$code['id'], $id, $body]);
}
echo date('H:i');
