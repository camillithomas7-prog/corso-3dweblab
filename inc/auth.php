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
/** Il corsista ha già compilato i suoi dati? */
function profile(int $code_id): ?array {
    $s = db()->prepare('SELECT * FROM profiles WHERE code_id=?');
    $s->execute([$code_id]);
    return $s->fetch() ?: null;
}
function profile_done(int $code_id): bool {
    $p = profile($code_id);
    return $p && $p['completed_at'];
}
/** Il questionario è già stato compilato? Vale per sempre: se aggiungi
 *  domande dopo, chi ha già risposto non viene rimandato indietro. */
function quiz_done(int $code_id): bool {
    $p = profile($code_id);
    return $p && !empty($p['quiz_at']);
}
function quiz_exists(): bool {
    return (int)db()->query('SELECT COUNT(*) FROM questions WHERE published=1')->fetchColumn() > 0;
}

function require_code(): array {
    $c = current_code();
    if (!$c) { header('Location: index.php'); exit; }
    // al primo accesso i dati vanno compilati prima di entrare nel corso
    $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $libere = ['profilo.php', 'questionario.php', 'logout.php'];
    if (!in_array($here, $libere, true)) {
        if (!profile_done((int)$c['id'])) { header('Location: profilo.php'); exit; }
        if (!quiz_done((int)$c['id']) && quiz_exists()) { header('Location: questionario.php'); exit; }
    }
    return $c;
}

/** Prefissi telefonici, Italia per prima. */
function dial_codes(): array {
    return [
        '+39'  => 'Italia', '+41' => 'Svizzera', '+33' => 'Francia', '+49' => 'Germania',
        '+34'  => 'Spagna', '+44' => 'Regno Unito', '+43' => 'Austria', '+32' => 'Belgio',
        '+31'  => 'Paesi Bassi', '+351' => 'Portogallo', '+30' => 'Grecia', '+353' => 'Irlanda',
        '+45'  => 'Danimarca', '+46' => 'Svezia', '+47' => 'Norvegia', '+358' => 'Finlandia',
        '+48'  => 'Polonia', '+420' => 'Cechia', '+421' => 'Slovacchia', '+36' => 'Ungheria',
        '+40'  => 'Romania', '+359' => 'Bulgaria', '+385' => 'Croazia', '+386' => 'Slovenia',
        '+381' => 'Serbia', '+355' => 'Albania', '+356' => 'Malta', '+357' => 'Cipro',
        '+352' => 'Lussemburgo', '+377' => 'Monaco', '+378' => 'San Marino', '+379' => 'Vaticano',
        '+376' => 'Andorra', '+372' => 'Estonia', '+371' => 'Lettonia', '+370' => 'Lituania',
        '+380' => 'Ucraina', '+90' => 'Turchia', '+7' => 'Russia',
        '+1'   => 'Stati Uniti e Canada', '+52' => 'Messico', '+55' => 'Brasile',
        '+54'  => 'Argentina', '+56' => 'Cile', '+57' => 'Colombia', '+51' => 'Perù',
        '+58'  => 'Venezuela', '+593' => 'Ecuador', '+598' => 'Uruguay',
        '+61'  => 'Australia', '+64' => 'Nuova Zelanda', '+81' => 'Giappone', '+82' => 'Corea del Sud',
        '+86'  => 'Cina', '+852' => 'Hong Kong', '+65' => 'Singapore', '+91' => 'India',
        '+62'  => 'Indonesia', '+63' => 'Filippine', '+66' => 'Thailandia', '+84' => 'Vietnam',
        '+972' => 'Israele', '+971' => 'Emirati Arabi Uniti', '+966' => 'Arabia Saudita',
        '+974' => 'Qatar', '+20' => 'Egitto', '+212' => 'Marocco', '+216' => 'Tunisia',
        '+213' => 'Algeria', '+27' => 'Sudafrica', '+234' => 'Nigeria', '+254' => 'Kenya',
    ];
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
/**
 * Il filtro dei moduli è spento finché non colleghi almeno un prodotto:
 * così nessuno resta chiuso fuori per una configurazione incompleta.
 */
function gate_attivo(): bool {
    static $v = null;
    if ($v === null) $v = (int)db()->query('SELECT COUNT(*) FROM product_map')->fetchColumn() > 0;
    return $v;
}

/** Id dei moduli a cui il corsista ha diritto. null = tutti. */
function moduli_permessi(int $code_id): ?array {
    if (!gate_attivo()) return null;
    static $c = [];
    if (!isset($c[$code_id])) {
        $s = db()->prepare('SELECT category_id FROM entitlements WHERE code_id=?');
        $s->execute([$code_id]);
        $c[$code_id] = array_map('intval', array_column($s->fetchAll(), 'category_id'));
    }
    return $c[$code_id];
}
function modulo_permesso(int $code_id, ?int $cat): bool {
    $p = moduli_permessi($code_id);
    return $p === null || ($cat !== null && in_array($cat, $p, true));
}

/** I materiali che il corsista puo' davvero aprire, gia' in ordine di modulo.
 *  Ogni riga porta 'cat' (la categoria di appartenenza, null = materiale generale). */
function materiali_visibili(int $code_id): array {
    static $cache = [];
    if (isset($cache[$code_id])) return $cache[$code_id];
    $rows = db()->query("SELECT m.*, l.title AS ltitle, c.title AS ctitle,
                                COALESCE(m.category_id, l.category_id) AS cat
                         FROM materials m
                         LEFT JOIN lessons l    ON l.id = m.lesson_id
                         LEFT JOIN categories c ON c.id = COALESCE(m.category_id, l.category_id)
                         ORDER BY c.pos, c.id, l.pos, l.id, m.pos, m.id")->fetchAll();
    return $cache[$code_id] = array_values(array_filter($rows, fn($r) =>
        $r['cat'] === null || modulo_permesso($code_id, (int)$r['cat'])));
}

/** Assegna i moduli di una riga d'ordine. Restituisce quelli nuovi. */
function assegna_da_ordine(int $code_id, array $line_items, string $order_ref = ''): array {
    $map = db()->query('SELECT needle, category_id FROM product_map')->fetchAll();
    if (!$map) return [];
    $ins = db()->prepare("INSERT OR IGNORE INTO entitlements(code_id,category_id,source,order_ref)
                          VALUES(?,?,'ordine',?)");
    $nuovi = [];
    foreach ($line_items as $li) {
        $campi = [(string)($li['product_id'] ?? ''), (string)($li['variant_id'] ?? ''),
                  (string)($li['sku'] ?? ''), (string)($li['title'] ?? '')];
        foreach ($map as $m) {
            $ago = trim((string)$m['needle']);
            if ($ago === '') continue;
            $trovato = false;
            foreach ($campi as $c) {
                if ($c === '') continue;
                if ($c === $ago) { $trovato = true; break; }
                if (!ctype_digit($ago) && stripos($c, $ago) !== false) { $trovato = true; break; }
            }
            if (!$trovato) continue;
            $gia = db()->prepare('SELECT 1 FROM entitlements WHERE code_id=? AND category_id=?');
            $gia->execute([$code_id, (int)$m['category_id']]);
            if ($gia->fetchColumn()) continue;
            $ins->execute([$code_id, (int)$m['category_id'], $order_ref]);
            $nuovi[] = (int)$m['category_id'];
        }
    }
    return array_values(array_unique($nuovi));
}

/** Messaggi non letti dal corsista (scritti dall'admin). */
function unread_user(int $code_id): int {
    $s = db()->prepare("SELECT COUNT(*) FROM messages WHERE code_id=? AND sender='admin' AND read_user=0");
    $s->execute([$code_id]); return (int)$s->fetchColumn();
}
/** Messaggi non letti dall'admin, in tutte le conversazioni. */
function unread_admin(): int {
    return (int)db()->query("SELECT COUNT(*) FROM messages WHERE sender='utente' AND read_admin=0")->fetchColumn();
}
/** Un messaggio pronto da mostrare. */
function msg_row(array $m): array {
    return [
        'id'   => (int)$m['id'],
        'mine' => false,
        'body' => (string)$m['body'],
        'who'  => $m['sender'],
        'at'   => date('d/m H:i', strtotime($m['created_at'])),
    ];
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/** Copertina di un corso in vendita: quelle di serie stanno nel repo,
 *  quelle caricate dal pannello passano da media.php. */
function corso_img(array $c, string $base = ''): string {
    $im = trim((string)($c['image'] ?? ''));
    if ($im === '') return '';
    if (str_starts_with($im, 'assets/')) return $base . $im;
    return $base . 'media.php?t=c&id=' . (int)$c['id'];
}

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
