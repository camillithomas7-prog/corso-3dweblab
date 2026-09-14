<?php
/**
 * Webhook Shopify: ordine pagato → codice di accesso → email al cliente.
 *
 * Da configurare in Shopify: Impostazioni → Notifiche → Webhook
 *   evento  orders/paid          formato JSON
 *   URL     https://TUOSITO/shopify.php
 * Il segreto che Shopify mostra va incollato in Impostazioni del pannello.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/mailer.php';

function fine(int $c, string $m): never { http_response_code($c); exit($m); }
function traccia(array $d): void {
    db()->prepare('INSERT INTO hooks(order_id,order_name,email,status,note,code_id)
                   VALUES(?,?,?,?,?,?)')
        ->execute([$d['oid'] ?? '', $d['name'] ?? '', $d['mail'] ?? '',
                   $d['status'] ?? '', $d['note'] ?? '', $d['code_id'] ?? null]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fine(405, 'solo POST');

$raw    = file_get_contents('php://input') ?: '';
$secret = (string)setting('shopify_secret', '');
$hmac   = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

// Senza questo controllo chiunque potrebbe inviare ordini falsi e ottenere codici.
if ($secret === '') { traccia(['status'=>'errore','note'=>'segreto non configurato']); fine(500, 'segreto assente'); }
$calc = base64_encode(hash_hmac('sha256', $raw, $secret, true));
if (!hash_equals($calc, $hmac)) { traccia(['status'=>'rifiutato','note'=>'firma non valida']); fine(401, 'firma non valida'); }

$o = json_decode($raw, true);
if (!is_array($o)) { traccia(['status'=>'errore','note'=>'JSON illeggibile']); fine(400, 'JSON illeggibile'); }

$oid   = (string)($o['id'] ?? '');
$name  = (string)($o['name'] ?? '');
$mail  = trim((string)($o['email'] ?? ($o['contact_email'] ?? '')));
$cust  = $o['customer'] ?? [];
$nome  = trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''));
if ($nome === '') $nome = trim((string)(($o['billing_address']['name'] ?? '')));
$base  = ['oid'=>$oid, 'name'=>$name, 'mail'=>$mail];

// L'ordine contiene il corso? Se non ho impostato filtri, vale ogni ordine.
$filtri = array_filter(array_map('trim', explode(',', (string)setting('shopify_match', ''))));
$ok = !$filtri;
if ($filtri) {
    foreach (($o['line_items'] ?? []) as $li) {
        foreach ([$li['product_id'] ?? '', $li['variant_id'] ?? '', $li['sku'] ?? '', $li['title'] ?? ''] as $v) {
            foreach ($filtri as $f) {
                if ($v !== '' && (string)$v === $f) { $ok = true; break 3; }
                if ($v !== '' && stripos((string)$v, $f) !== false && !ctype_digit($f)) { $ok = true; break 3; }
            }
        }
    }
}
if (!$ok) { traccia($base + ['status'=>'ignorato','note'=>'nessun prodotto corrispondente']); exit('ignorato'); }

// Shopify riprova più volte lo stesso ordine: un ordine, un codice.
$q = db()->prepare('SELECT * FROM codes WHERE shopify_order_id=?');
$q->execute([$oid]);
if ($gia = $q->fetch()) {
    traccia($base + ['status'=>'duplicato','note'=>'ordine già gestito: '.$gia['code'],'code_id'=>$gia['id']]);
    exit('già gestito');
}

$items = $o['line_items'] ?? [];

// Il cliente ha già un accesso? Allora niente secondo codice: si aggiunge al suo.
$row = null; $ritorno = false;
if ($mail !== '') {
    $q = db()->prepare("SELECT * FROM codes WHERE lower(email)=lower(?) AND status='active'
                        ORDER BY id LIMIT 1");
    $q->execute([$mail]);
    $row = $q->fetch() ?: null;
    if ($row) $ritorno = true;
}

if ($ritorno) {
    $nuovi = assegna_da_ordine((int)$row['id'], $items, $name);
    if (!$nuovi) {
        traccia($base + ['status'=>'nulla da aggiungere',
                         'note'=>'cliente già in possesso di questi contenuti · '.$row['code'],
                         'code_id'=>$row['id']]);
        exit('niente di nuovo');
    }
    $titoli = [];
    $t = db()->prepare('SELECT title FROM categories WHERE id=?');
    foreach ($nuovi as $cid) { $t->execute([$cid]); $titoli[] = (string)$t->fetchColumn(); }
    try {
        invia_sblocco($row, $titoli);
        traccia($base + ['status'=>'sblocco inviato',
                         'note'=>implode(', ', $titoli).' → '.$row['code'], 'code_id'=>$row['id']]);
        exit('ok');
    } catch (Throwable $e) {
        db()->prepare('UPDATE codes SET email_error=? WHERE id=?')->execute([$e->getMessage(), $row['id']]);
        traccia($base + ['status'=>'email fallita','note'=>$e->getMessage(),'code_id'=>$row['id']]);
        exit('sbloccato, email non inviata');
    }
}

// Cliente nuovo: genera il codice
$code = null;
for ($i = 0; $i < 12; $i++) {
    try {
        $c = generate_code();
        db()->prepare('INSERT INTO codes(code,label,order_ref,email,shopify_order_id) VALUES(?,?,?,?,?)')
            ->execute([$c, $nome, $name, $mail, $oid]);
        $code = $c; break;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'ix_codes_order')) { exit('già gestito'); }
    }
}
if (!$code) { traccia($base + ['status'=>'errore','note'=>'generazione codice fallita']); fine(500, 'errore'); }

$q = db()->prepare('SELECT * FROM codes WHERE code=?'); $q->execute([$code]);
$row = $q->fetch();
assegna_da_ordine((int)$row['id'], $items, $name);

// Manda la mail. Se fallisce il codice resta valido: lo rimandi dal pannello.
try {
    invia_codice($row);
    db()->prepare("UPDATE codes SET email_sent_at=datetime('now'), email_error='' WHERE id=?")->execute([$row['id']]);
    traccia($base + ['status'=>'inviato','note'=>'codice '.$code,'code_id'=>$row['id']]);
    exit('ok');
} catch (Throwable $e) {
    db()->prepare('UPDATE codes SET email_error=? WHERE id=?')->execute([$e->getMessage(), $row['id']]);
    traccia($base + ['status'=>'email fallita','note'=>$e->getMessage(),'code_id'=>$row['id']]);
    // 200 di proposito: il codice c'è, non voglio che Shopify riprovi e ne crei un altro
    exit('codice creato, email non inviata');
}
