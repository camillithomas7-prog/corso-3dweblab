<?php
/**
 * Invio e lettura dei messaggi, per corsista e admin.
 * GET  ?since=<id>[&code=<id> se admin]  → messaggi nuovi in JSON
 * POST body=<testo>[&code=<id> se admin] → invia
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');

// oltre post_max_size PHP butta via tutto il POST, campi compresi: senza questo
// controllo l'errore che arriva al browser parla di sessione scaduta e non si capisce
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    exit(json_encode(['error' => "file troppo grande: il limite di questo server e' " . peso(chat_max_bytes())]));
}

$admin = current_admin();
$code  = current_code();
if (!$admin && !$code) { http_response_code(401); exit('{"error":"sessione scaduta"}'); }

/**
 * Chi sta scrivendo. L'admin indica la conversazione col parametro code: e'
 * quello che fa il pannello. Senza quel parametro comanda la sessione da
 * corsista, se c'e': capita di averle tutte e due nello stesso browser quando
 * provi il corso da cliente mentre sei dentro come admin, e da quella pagina
 * si scrive come corsista, nella propria conversazione.
 */
$scelta = (int)($_REQUEST['code'] ?? 0);
if ($admin && $scelta) {
    $cid = $scelta; $io = 'admin';
    $c = db()->prepare('SELECT id FROM codes WHERE id=?'); $c->execute([$cid]);
    if (!$c->fetchColumn()) { http_response_code(404); exit('{"error":"conversazione inesistente"}'); }
} elseif ($code) {
    $cid = (int)$code['id']; $io = 'utente';
} else {
    http_response_code(400); exit('{"error":"conversazione non indicata"}');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $b = trim((string)($_POST['body'] ?? ''));
    if (mb_strlen($b) > 4000) $b = mb_substr($b, 0, 4000);

    // il messaggio puo' portare un PDF: allora il testo diventa una didascalia facoltativa
    $file = ''; $fname = ''; $fsize = 0;
    if (!empty($_FILES['file']['name'])) {
        try { [$file, $fname, $fsize] = salva_allegato($_FILES['file']); }
        catch (RuntimeException $e) {
            http_response_code(400);
            exit(json_encode(['error' => $e->getMessage()]));
        }
    } elseif ($b === '') { http_response_code(400); exit('{"error":"messaggio vuoto"}'); }

    db()->prepare("INSERT INTO messages(code_id,sender,body,read_admin,read_user,file,file_name,file_size)
                   VALUES(?,?,?,?,?,?,?,?)")
        ->execute([$cid, $io, $b, $io === 'admin' ? 1 : 0, $io === 'utente' ? 1 : 0, $file, $fname, $fsize]);
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
// il token viaggia con la risposta: se la pagina ne ha uno vecchio (sessione
// rigenerata in un'altra scheda) lo rinfresca e riprova, senza ricaricare
echo json_encode(['messages' => $out, 'csrf' => csrf(), 'unread' => $io === 'admin' ? unread_admin() : 0]);
