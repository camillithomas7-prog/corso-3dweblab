<?php
/**
 * Quante risposte del supporto il corsista non ha ancora letto.
 * Lo interroga la campanella della barra in alto, ogni 15 secondi.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$code = current_code();
if (!$code) { http_response_code(401); exit('{"error":"sessione scaduta"}'); }

$n = 0;
try { $n = unread_user((int)$code['id']); } catch (Throwable $e) { /* mai rompere una pagina */ }

echo json_encode(['n' => $n], JSON_UNESCAPED_UNICODE);
