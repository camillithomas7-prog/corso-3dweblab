<?php
/**
 * Apre Gmail con il messaggio gia' scritto per un cliente che non e' mai
 * entrato: nome, codice e link al posto giusto. La mail parte dalla casella
 * di chi sta scrivendo, non dal nostro SMTP, cosi' la risposta del cliente
 * torna nella sua posta. Qui si segna solo che il contatto e' partito.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT * FROM codes WHERE id=?'); $s->execute([$id]);
$c = $s->fetch();
if (!$c || !trim((string)$c['email'])) { flash('Cliente senza indirizzo email.', 'err'); back('codici.php'); }

/** Il nome di battesimo, scritto come si deve: dal profilo, o da quello dell'ordine. */
function nome_di_battesimo(array $c, ?array $p): string {
    $n = trim((string)($p['first_name'] ?? ''));
    if ($n === '') $n = trim((string)$c['label']);
    $n = trim(explode(' ', $n)[0] ?? '');
    return $n === '' ? '' : mb_convert_case($n, MB_CASE_TITLE, 'UTF-8');
}

$nome  = nome_di_battesimo($c, profile($id));
$corso = setting('course_name', '3D WEB LAB');
$link  = rtrim((string)setting('site_url', ''), '/');
if ($link === '') $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? '');

$oggetto = ($nome ? $nome . ', tutto' : 'Tutto') . " ok con l'accesso a $corso?";
$testo =
    ($nome ? "Ciao $nome," : 'Ciao,') . "\n"
  . "ti scrivo perché ho visto che non hai ancora fatto accesso alla piattaforma "
  . "e volevo capire se era tutto ok o se hai avuto qualche problema a entrare.\n\n"
  . "Ti riepilogo qui sotto quello che ti serve:\n\n"
  . "Link: $link\n"
  . "Codice di accesso: {$c['code']}\n\n"
  . "Non serve registrarsi: apri il link e incolli il codice. "
  . "Se qualcosa non va rispondi pure a questa mail, ci penso io.\n";

db()->prepare("UPDATE codes SET contacted_at=datetime('now') WHERE id=?")->execute([$id]);

$gmail = 'https://mail.google.com/mail/?view=cm&fs=1'
       . '&to=' . rawurlencode(trim((string)$c['email']))
       . '&su=' . rawurlencode($oggetto)
       . '&body=' . rawurlencode($testo);
header('Location: ' . $gmail);
exit;
