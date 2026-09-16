<?php
/**
 * Quanti messaggi dei clienti non ho ancora letto.
 * Lo interroga la campanella della barra in alto, ogni 15 secondi.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!current_admin()) { http_response_code(401); exit('{"error":"sessione scaduta"}'); }

$n = 0; $chi = '';
try {
    $n = unread_admin();
    if ($n) {
        $r = db()->query("SELECT COALESCE(NULLIF(TRIM(p.first_name||' '||p.last_name),''), c.label, c.code) AS chi
                          FROM messages m
                          JOIN codes c ON c.id = m.code_id
                          LEFT JOIN profiles p ON p.code_id = c.id
                          WHERE m.sender='utente' AND m.read_admin=0
                          ORDER BY m.id DESC LIMIT 1")->fetchColumn();
        $chi = (string)($r ?: '');
    }
} catch (Throwable $e) { /* la campanella non deve mai rompere una pagina */ }

echo json_encode(['n' => $n, 'chi' => $chi], JSON_UNESCAPED_UNICODE);
