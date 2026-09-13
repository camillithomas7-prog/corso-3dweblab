<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function boot_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('corso3dwl');
    session_start();
}

/* ───────── cliente: accesso con il solo codice ───────── */
function current_code(): ?array {
    boot_session();
    if (empty($_SESSION['code_id'])) return null;
    $s = db()->prepare("SELECT * FROM codes WHERE id=? AND status='active'");
    $s->execute([$_SESSION['code_id']]);
    $c = $s->fetch();
    if (!$c) { unset($_SESSION['code_id']); return null; }
    if ($c['expires_at'] && $c['expires_at'] < date('Y-m-d')) { unset($_SESSION['code_id']); return null; }
    return $c;
}
function require_code(): array {
    $c = current_code();
    if (!$c) { header('Location: index.php'); exit; }
    return $c;
}
function normalize_code(string $raw): string {
    $s = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $raw));
    return $s;
}
function find_code(string $raw): ?array {
    $n = normalize_code($raw);
    if ($n === '') return null;
    foreach (db()->query("SELECT * FROM codes")->fetchAll() as $c) {
        if (normalize_code($c['code']) === $n) return $c;
    }
    return null;
}
function generate_code(): string {
    $al = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // niente 0/O/1/I
    $p = function(int $n) use ($al) {
        $o = ''; for ($i=0;$i<$n;$i++) $o .= $al[random_int(0, strlen($al)-1)];
        return $o;
    };
    return '3DWL-' . $p(4) . '-' . $p(4);
}

/* ───────── admin ───────── */
function current_admin(): ?array {
    boot_session();
    if (empty($_SESSION['admin_id'])) return null;
    $s = db()->prepare('SELECT * FROM admins WHERE id=?');
    $s->execute([$_SESSION['admin_id']]);
    return $s->fetch() ?: null;
}
function require_admin(): array {
    $a = current_admin();
    if (!$a) { header('Location: index.php'); exit; }
    return $a;
}

/* ───────── CSRF ───────── */
function csrf(): string {
    boot_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    boot_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('Sessione scaduta, ricarica la pagina.');
    }
}
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function hms(int $s): string {
    if ($s <= 0) return '—';
    $h = intdiv($s,3600); $m = intdiv($s%3600,60); $x = $s%60;
    return $h ? sprintf('%d:%02d:%02d',$h,$m,$x) : sprintf('%d:%02d',$m,$x);
}
function human_bytes(int $b): string {
    $u = ['B','KB','MB','GB']; $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return round($b, $i ? 1 : 0) . ' ' . $u[$i];
}
