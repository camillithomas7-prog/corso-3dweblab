<?php
require_once __DIR__ . '/inc/auth.php';
$code = current_code();
if (!$code) { http_response_code(401); exit; }
$id  = (int)($_POST['id'] ?? 0);
$sec = max(0, (int)($_POST['seconds'] ?? 0));
if (!$id) { http_response_code(400); exit; }
if (array_key_exists('completed', $_POST)) {
    db()->prepare("INSERT INTO progress(code_id,lesson_id,seconds,completed,updated_at)
                   VALUES(?,?,?,?,datetime('now'))
                   ON CONFLICT(code_id,lesson_id) DO UPDATE SET
                     seconds=excluded.seconds, completed=excluded.completed, updated_at=datetime('now')")
        ->execute([$code['id'], $id, $sec, (int)!!$_POST['completed']]);
} else {
    db()->prepare("INSERT INTO progress(code_id,lesson_id,seconds,updated_at)
                   VALUES(?,?,?,datetime('now'))
                   ON CONFLICT(code_id,lesson_id) DO UPDATE SET
                     seconds=excluded.seconds, updated_at=datetime('now')")
        ->execute([$code['id'], $id, $sec]);
}
echo 'ok';
