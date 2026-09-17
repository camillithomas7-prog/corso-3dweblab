<?php
/**
 * Riceve file grandi in blocchi da 4 MB, così non conta upload_max_filesize.
 * POST: kind=video|pdf|poster, uid, idx, total, name  + file "chunk"
 */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
header('Content-Type: application/json');

// Qualunque cosa accada, il browser deve ricevere JSON: una risposta vuota
// produce solo un incomprensibile "Unexpected end of JSON input".
$GLOBALS['__sent'] = false;
register_shutdown_function(function () {
    if ($GLOBALS['__sent']) return;
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
        echo json_encode(['error' => 'errore del server: ' . $e['message']]);
    }
});
function reply(array $d, int $code = 200): never {
    $GLOBALS['__sent'] = true;
    http_response_code($code);
    exit(json_encode($d));
}
if (!current_admin()) { reply(['error'=>'non autorizzato'], 403); }

/** Quanto grande può essere un blocco su QUESTO server. */
function ini_bytes(string $k): int {
    $v = trim((string)ini_get($k)); if ($v==='') return 0;
    $u = strtolower(substr($v,-1)); $n = (int)$v;
    return match($u){ 'g'=>$n*1073741824, 'm'=>$n*1048576, 'k'=>$n*1024, default=>(int)$v };
}
function max_chunk(): int {
    $u = ini_bytes('upload_max_filesize') ?: 2*1048576;
    $p = ini_bytes('post_max_size')       ?: 8*1048576;
    // il POST porta anche i campi di testo: lascio un margine
    $m = (int)min($u, max(262144, $p - 262144));
    return max(262144, min($m - 65536, 8*1048576));   // fra 256 KB e 8 MB
}
if (isset($_GET['probe'])) { reply(['chunk'=>max_chunk()]); }

check_csrf();

$kind  = (string)($_POST['kind'] ?? '');
$uid   = preg_replace('/[^a-zA-Z0-9]/', '', (string)($_POST['uid'] ?? ''));
$idx   = (int)($_POST['idx'] ?? 0);
$total = (int)($_POST['total'] ?? 0);
$name  = (string)($_POST['name'] ?? 'file');

$allow = ['video'=>['mp4','mov','m4v','webm'], 'pdf'=>['pdf','zip'], 'poster'=>['jpg','jpeg','png','webp']];
if (!isset($allow[$kind]) || !$uid || $total < 1) { reply(['error'=>'richiesta non valida'], 400); }
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, $allow[$kind], true)) {
    reply(['error'=>'formato non ammesso: .'.$ext], 400);
}
if (empty($_FILES['chunk'])) {
    reply(['error'=>'blocco non ricevuto: supera i limiti del server ('
        . ini_get('upload_max_filesize') . ' per file, ' . ini_get('post_max_size') . ' per richiesta)'], 400);
}
if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    $why = match((int)$_FILES['chunk']['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'blocco troppo grande per il server (limite ' . ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_PARTIAL   => 'trasferimento interrotto',
        UPLOAD_ERR_NO_TMP_DIR=> 'manca la cartella temporanea di PHP',
        UPLOAD_ERR_CANT_WRITE=> 'il server non riesce a scrivere su disco',
        default              => 'errore di upload #' . (int)$_FILES['chunk']['error'],
    };
    reply(['error'=>$why], 400);
}

$tmpdir = STORAGE . '/tmp';
if (!is_dir($tmpdir)) @mkdir($tmpdir, 0775, true);
$part = "$tmpdir/$uid.part";

$in = fopen($_FILES['chunk']['tmp_name'], 'rb');
$out = fopen($part, $idx === 0 ? 'wb' : 'ab');
if (!$in || !$out) { reply(['error'=>'scrittura fallita sul disco del server'], 500); }
stream_copy_to_stream($in, $out);
fclose($in); fclose($out);

if ($idx + 1 < $total) { reply(['ok'=>true,'received'=>$idx+1]); }

// ultimo blocco: sposta nella cartella definitiva
$dir = $kind === 'pdf' ? STORAGE.'/pdf' : STORAGE.'/video';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$final = safe_name($name, $ext);
if (!@rename($part, "$dir/$final")) {
    // filesystem diversi: copio e poi ripulisco
    if (!@copy($part, "$dir/$final")) { reply(['error'=>'impossibile salvare il file caricato'], 500); }
    @unlink($part);
}
@chmod("$dir/$final", 0644);

reply([
  'ok'=>true, 'file'=>$final, 'bytes'=>filesize("$dir/$final"),
  'seconds'=> $kind==='video' ? video_seconds("$dir/$final") : 0,
]);
