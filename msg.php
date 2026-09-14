<?php
/**
 * Invio e lettura dei messaggi, per corsista e admin.
 * GET  ?since=<id>[&code=<id> se admin]  → messaggi nuovi in JSON
 * POST body=<testo>[&code=<id> se admin] → invia
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');

$admin = current_admin();
$code  = current_code();
if (!$admin && !$code) { http_response_code(401); exit('{"error":"sessione scaduta"}'); }

// l'admin sceglie la conversazione, il corsista ha solo la sua
$cid = $admin ? (int)($_REQUEST['code'] ?? 0) : (int)$code['id'];
if (!$cid) { http_response_code(400); exit('{"error":"conversazione non indicata"}'); }
if ($admin) {
    $c = db()->prepare('SELECT id FROM codes WHERE id=?'); $c->execute([$cid]);
    if (!$c->fetchColumn()) { http_response_code(404); exit('{"error":"conversazione inesistente"}'); }
}
$io = $admin ? 'admin' : 'utente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $b = trim((string)($_POST['body'] ?? ''));
    if ($b === '') { http_response_code(400); exit('{"error":"messaggio vuoto"}'); }
    if (mb_strlen($b) > 4000) $b = mb_substr($b, 0, 4000);
    db()->prepare("INSERT INTO messages(code_id,sender,body,read_admin,read_user)
                   VALUES(?,?,?,?,?)")
        ->execute([$cid, $io, $b, $io === 'admin' ? 1 : 0, $io === 'utente' ? 1 : 0]);
}

// tutto ciò che è arrivato dall'altra parte risulta letto
db()->prepare($io === 'admin'
    ? "UPDATE messages SET read_admin=1 WHERE code_id=? AND sender='utente' AND read_admin=0"
    : "UPDATE messages SET read_user=1  WHERE code_id=? AND sender='admin'  AND read_user=0")
   ->execute([$cid]);

$since = (int)($_REQUEST['since'] ?? 0);
$q = db()->prepare('SELECT * FROM messages WHERE code_id=? AND id>? ORDER BY id LIMIT 200');
$q->execute([$cid, $since]);
$out = [];
foreach ($q->fetchAll() as $m) {
    $r = msg_row($m);
    $r['mine'] = ($m['sender'] === $io);
    $out[] = $r;
}
echo json_encode(['messages' => $out, 'unread' => $admin ? unread_admin() : 0]);
