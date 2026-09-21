<?php
/**
 * Serve video, poster e PDF solo a chi ha una sessione valida.
 * I file stanno fuori dalla portata del browser: si arriva qui e non altrove.
 * Supporta le richieste Range, senza le quali non puoi spostarti nel video.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';

$t  = (string)($_GET['t'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$isAdmin = (bool)current_admin();
if (!$isAdmin && !current_code()) { http_response_code(403); exit('Accesso non consentito.'); }
if (!$id) { http_response_code(400); exit; }

$path = null; $mime = 'application/octet-stream'; $name = 'file'; $inline = true;

if ($t === 'v' || $t === 'p') {
    $s = db()->prepare('SELECT * FROM lessons WHERE id=?'); $s->execute([$id]);
    $l = $s->fetch();
    if (!$l) { http_response_code(404); exit; }
    if ($t === 'v') {
        if ($l['video_type'] !== 'file' || !$l['video_src']) { http_response_code(404); exit; }
        $path = STORAGE . '/video/' . basename($l['video_src']); $mime = 'video/mp4';
    } else {
        if (!$l['poster']) { http_response_code(404); exit; }
        $path = STORAGE . '/video/' . basename($l['poster']);
        $mime = str_ends_with(strtolower($l['poster']), '.png') ? 'image/png' : 'image/jpeg';
    }
} elseif ($t === 'c') {
    $s = db()->prepare('SELECT * FROM corsi WHERE id=?'); $s->execute([$id]);
    $c = $s->fetch();
    if (!$c || !$c['image'] || str_starts_with((string)$c['image'], 'assets/')) { http_response_code(404); exit; }
    $path = STORAGE . '/img/' . basename((string)$c['image']);
    $mime = str_ends_with(strtolower((string)$c['image']), '.png') ? 'image/png' : 'image/jpeg';
} elseif ($t === 'm') {
    $s = db()->prepare('SELECT * FROM materials WHERE id=?'); $s->execute([$id]);
    $m = $s->fetch();
    if (!$m) { http_response_code(404); exit; }
    $path = STORAGE . '/pdf/' . basename($m['filename']);
    $zip  = mat_tipo($m) === 'zip';
    $mime = $zip ? 'application/zip' : 'application/pdf';
    // il pdf si guarda nel browser, lo zip non si puo' che scaricare
    $inline = !$zip;
    $name = preg_replace('/[^\w\-. ]/u', '', $m['orig_name'] ?: ($m['title'] . ($zip ? '.zip' : '.pdf')));
} elseif ($t === 'g') {
    // allegato di un messaggio: lo vede l'admin e il corsista di quella conversazione
    $s = db()->prepare('SELECT * FROM messages WHERE id=?'); $s->execute([$id]);
    $m = $s->fetch();
    if (!$m || empty($m['file'])) { http_response_code(404); exit; }
    if (!$isAdmin) {
        $c = current_code();
        if (!$c || (int)$c['id'] !== (int)$m['code_id']) { http_response_code(403); exit('Accesso non consentito.'); }
    }
    $path = STORAGE . '/chat/' . basename((string)$m['file']);
    $mime = 'application/pdf';
    $inline = empty($_GET['dl']);          // con ?dl=1 il browser lo scarica invece di aprirlo
    $name = preg_replace('/[^\w\-. ]/u', '', (string)($m['file_name'] ?: $m['file'])) ?: 'documento.pdf';
} else { http_response_code(400); exit; }

if (!$path || !is_file($path)) { http_response_code(404); exit('File non trovato.'); }

$size = filesize($path);
$fp   = fopen($path, 'rb');
if (!$fp) { http_response_code(500); exit; }

while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0, no-store');
header('X-Content-Type-Options: nosniff');
if ($t === 'm' || $t === 'g') header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
                     . '; filename="' . $name . '"');

$start = 0; $end = $size - 1;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $mm)) {
    if ($mm[1] !== '') $start = (int)$mm[1];
    if ($mm[2] !== '') $end   = min((int)$mm[2], $size - 1);
    if ($start > $end || $start >= $size) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$size"); fclose($fp); exit;
    }
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . ($end - $start + 1));

fseek($fp, $start);
$left = $end - $start + 1;
$chunk = 1024 * 256;
set_time_limit(0);
while ($left > 0 && !feof($fp) && !connection_aborted()) {
    $buf = fread($fp, (int)min($chunk, $left));
    if ($buf === false || $buf === '') break;
    echo $buf; flush();
    $left -= strlen($buf);
}
fclose($fp);
