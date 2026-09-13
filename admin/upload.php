<?php
/**
 * Riceve file grandi in blocchi da 4 MB, così non conta upload_max_filesize.
 * POST: kind=video|pdf|poster, uid, idx, total, name  + file "chunk"
 */
declare(strict_types=1);
require_once __DIR__ . '/inc.php';
header('Content-Type: application/json');
if (!current_admin()) { http_response_code(403); exit(json_encode(['error'=>'non autorizzato'])); }

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
if (isset($_GET['probe'])) { exit(json_encode(['chunk'=>max_chunk()])); }

check_csrf();

$kind  = (string)($_POST['kind'] ?? '');
$uid   = preg_replace('/[^a-zA-Z0-9]/', '', (string)($_POST['uid'] ?? ''));
$idx   = (int)($_POST['idx'] ?? 0);
$total = (int)($_POST['total'] ?? 0);
$name  = (string)($_POST['name'] ?? 'file');

$allow = ['video'=>['mp4','mov','m4v','webm'], 'pdf'=>['pdf'], 'poster'=>['jpg','jpeg','png','webp']];
if (!isset($allow[$kind]) || !$uid || $total < 1) { http_response_code(400); exit(json_encode(['error'=>'richiesta non valida'])); }
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, $allow[$kind], true)) {
    http_response_code(400); exit(json_encode(['error'=>'formato non ammesso: .'.$ext]));
}
if (empty($_FILES['chunk'])) {
    http_response_code(400);
    exit(json_encode(['error'=>'blocco non ricevuto: supera i limiti del server ('
        . ini_get('upload_max_filesize') . ' per file, ' . ini_get('post_max_size') . ' per richiesta)']));
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
    http_response_code(400); exit(json_encode(['error'=>$why]));
}

$tmpdir = STORAGE . '/tmp';
if (!is_dir($tmpdir)) @mkdir($tmpdir, 0775, true);
$part = "$tmpdir/$uid.part";

$in = fopen($_FILES['chunk']['tmp_name'], 'rb');
$out = fopen($part, $idx === 0 ? 'wb' : 'ab');
if (!$in || !$out) { http_response_code(500); exit(json_encode(['error'=>'scrittura fallita'])); }
stream_copy_to_stream($in, $out);
fclose($in); fclose($out);

if ($idx + 1 < $total) { echo json_encode(['ok'=>true,'received'=>$idx+1]); exit; }

// ultimo blocco: sposta nella cartella definitiva
$dir = $kind === 'pdf' ? STORAGE.'/pdf' : STORAGE.'/video';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$final = safe_name($name, $ext);
if (!rename($part, "$dir/$final")) { http_response_code(500); exit(json_encode(['error'=>'spostamento fallito'])); }
@chmod("$dir/$final", 0644);

echo json_encode([
  'ok'=>true, 'file'=>$final, 'bytes'=>filesize("$dir/$final"),
  'seconds'=> $kind==='video' ? video_seconds("$dir/$final") : 0,
]);
