<?php
/**
 * Invio email via SMTP autenticato.
 * Si usa l'SMTP della casella vera (supporto@3dweblab.it) e non mail():
 * una mail che parte dal dominio giusto arriva in posta in arrivo,
 * una che parte dall'hostname del server finisce nello spam — e qui
 * quella mail è l'unica cosa che dà al cliente ciò che ha pagato.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

class Smtp {
    private $fp; private array $log = [];
    public function __construct(
        private string $host, private int $port, private string $user,
        private string $pass, private string $sec = 'ssl', private int $timeout = 20
    ) {}
    public function log(): string { return implode("\n", $this->log); }

    private function put(string $c): void { fwrite($this->fp, $c . "\r\n"); $this->log[] = '> ' . (stripos($c,'AUTH')===0||strlen($c)>80 ? '…' : $c); }
    private function get(): string {
        $out = '';
        while ($l = fgets($this->fp, 1024)) {
            $out .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        $this->log[] = '< ' . trim($out);
        return $out;
    }
    private function cmd(string $c, string $expect): void {
        $this->put($c);
        $r = $this->get();
        if (strncmp($r, $expect, strlen($expect)) !== 0)
            throw new RuntimeException("SMTP: atteso $expect, ricevuto " . trim($r));
    }

    public function send(string $fromMail, string $fromName, string $to, string $subject,
                         string $html, string $text): void {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $dsn = ($this->sec === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $this->fp = @stream_socket_client($dsn, $en, $es, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) throw new RuntimeException("connessione a {$this->host}:{$this->port} fallita: $es");
        stream_set_timeout($this->fp, $this->timeout);
        $this->get();

        $ehlo = 'EHLO ' . (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost');
        $this->cmd($ehlo, '250');
        if ($this->sec === 'tls') {
            $this->cmd('STARTTLS', '220');
            if (!stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
                throw new RuntimeException('attivazione TLS fallita');
            $this->cmd($ehlo, '250');
        }
        $this->cmd('AUTH LOGIN', '334');
        $this->cmd(base64_encode($this->user), '334');
        $this->cmd(base64_encode($this->pass), '235');
        $this->cmd('MAIL FROM:<' . $fromMail . '>', '250');
        $this->cmd('RCPT TO:<' . $to . '>', '250');
        $this->cmd('DATA', '354');

        $b = '=_' . bin2hex(random_bytes(12));
        $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $h = [
            'From: ' . $enc($fromName) . ' <' . $fromMail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $enc($subject),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $this->host . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $b . '"',
        ];
        $body = implode("\r\n", $h) . "\r\n\r\n"
              . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
              . "--$b--\r\n";
        // un punto a inizio riga va raddoppiato, altrimenti chiude il messaggio
        fwrite($this->fp, preg_replace('/^\./m', '..', $body) . "\r\n.\r\n");
        $r = $this->get();
        if (strncmp($r, '250', 3) !== 0) throw new RuntimeException('invio rifiutato: ' . trim($r));
        $this->put('QUIT'); fclose($this->fp);
    }
}

/** Sostituisce i segnaposto {nome} nel modello. */
function tpl(string $t, array $v): string {
    foreach ($v as $k => $x) $t = str_replace('{' . $k . '}', (string)$x, $t);
    return $t;
}

/**
 * Manda la mail con il codice. Lancia un'eccezione se non riesce.
 * $numero e' a che giro siamo: 1 e' la mail dell'ordine, da 2 in su e' un
 * sollecito a chi non e' ancora entrato, e cambia oggetto e testo d'apertura.
 */
function invia_codice(array $code, int $numero = 1): void {
    $host = setting('smtp_host', ''); $user = setting('smtp_user', '');
    $pass = setting('smtp_pass', '');
    if (!$host || !$user || !$pass) throw new RuntimeException('SMTP non configurato in Impostazioni');
    $to = trim((string)$code['email']);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('indirizzo email mancante o non valido');

    $base = rtrim(setting('site_url', ''), '/');
    if (!$base) $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                      . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $vars = [
        'nome'   => $code['label'] ?: '',
        'codice' => $code['code'],
        'link'   => $base . '/index.php',
        'ordine' => $code['order_ref'] ?: '',
        'corso'  => setting('course_name', '3D WEB LAB'),
    ];
    $sollecito = $numero >= 2;
    $subject = tpl($sollecito
        ? setting('mail_subject_2', 'Il tuo accesso a {corso} ti aspetta')
        : setting('mail_subject', 'Il tuo accesso a {corso}'), $vars);
    $intro   = tpl($sollecito
        ? setting('mail_intro_2', "Ti riscriviamo perché non risulti ancora entrato nel corso: "
            . "può darsi che la prima mail ti sia sfuggita.\nEcco di nuovo il tuo codice, "
            . "bastano dieci secondi per entrare.")
        : setting('mail_intro', "Grazie per il tuo acquisto.\nEcco il codice per entrare nel corso."), $vars);
    $firma   = tpl(setting('mail_sign', '3D WEB LAB'), $vars);

    $html = mail_html($vars, $intro, $firma);
    $text = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $intro)))
          . "\n\nCodice di accesso: " . $vars['codice']
          . "\nEntra qui: " . $vars['link']
          . "\n\n" . strip_tags($firma);

    (new Smtp($host, (int)setting('smtp_port', '465'), $user, $pass, setting('smtp_sec', 'ssl')))
        ->send(setting('smtp_from', $user), setting('smtp_name', '3D WEB LAB'), $to, $subject, $html, $text);
}

function mail_html(array $v, string $intro, string $firma): string {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $saluto = $v['nome'] ? 'Ciao ' . $e($v['nome']) . ',' : 'Ciao,';
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f6">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#0e1018;border-radius:14px;overflow:hidden;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
  <tr><td style="padding:30px 30px 6px"><div style="font-size:19px;font-weight:700;color:#f4f6fb;letter-spacing:-.02em">'
  . $e($v['corso']) . '</div></td></tr>
  <tr><td style="padding:14px 30px 0;color:#a4aec6;font-size:15px;line-height:1.65">'
  . $saluto . '<br>' . nl2br($e($intro)) . '</td></tr>
  <tr><td style="padding:24px 30px 0">
    <div style="background:#141826;border:1px solid #1f2536;border-radius:11px;padding:20px;text-align:center">
      <div style="font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#6c7790;margin-bottom:10px">Il tuo codice di accesso</div>
      <div style="font-size:25px;font-weight:700;letter-spacing:.13em;color:#6ee7ff;font-family:Consolas,Menlo,monospace">'
      . $e($v['codice']) . '</div>
    </div></td></tr>
  <tr><td style="padding:22px 30px 0" align="center">
    <a href="' . $e($v['link']) . '" style="display:inline-block;background:#6ee7ff;color:#0a0b12;text-decoration:none;font-size:15px;font-weight:700;padding:14px 30px;border-radius:9px">Entra nel corso</a>
  </td></tr>
  <tr><td style="padding:18px 30px 0;color:#6c7790;font-size:13px;line-height:1.6" align="center">
    Se il pulsante non funziona apri <a href="' . $e($v['link']) . '" style="color:#6ee7ff">' . $e($v['link']) . '</a><br>
    e inserisci il codice qui sopra. Non serve registrarsi.</td></tr>
  <tr><td style="padding:26px 30px 30px;border-top:1px solid #1f2536;margin-top:20px;color:#4a5368;font-size:12.5px;line-height:1.6">'
  . nl2br($e($firma)) . '<br>Conserva questa mail: il codice serve a ogni accesso.</td></tr>
</table></td></tr></table></body></html>';
}

/** Mail per chi aveva già un accesso e ha appena sbloccato altro. */
function invia_sblocco(array $code, array $titoli): void {
    $host = setting('smtp_host', ''); $user = setting('smtp_user', ''); $pass = setting('smtp_pass', '');
    if (!$host || !$user || !$pass) throw new RuntimeException('SMTP non configurato in Impostazioni');
    $to = trim((string)$code['email']);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('indirizzo email mancante o non valido');

    $base = rtrim(setting('site_url', ''), '/');
    if (!$base) $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                      . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $corso = setting('course_name', '3D WEB LAB');
    $nome  = $code['label'] ?: '';
    $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
    $lista_html = '';
    foreach ($titoli as $t) $lista_html .= '<li style="margin-bottom:6px">' . $e($t) . '</li>';
    $lista_txt = "- " . implode("\n- ", $titoli);

    $subject = 'Hai sbloccato nuovi contenuti su ' . $corso;
    $html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f6">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#0e1018;border-radius:14px;overflow:hidden;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif">
  <tr><td style="padding:30px 30px 6px"><div style="font-size:19px;font-weight:700;color:#f4f6fb;letter-spacing:-.02em">'
  . $e($corso) . '</div></td></tr>
  <tr><td style="padding:14px 30px 0;color:#a4aec6;font-size:15px;line-height:1.65">'
  . ($nome ? 'Ciao ' . $e($nome) . ',' : 'Ciao,') . '<br>grazie per il tuo acquisto. Da adesso trovi dentro il corso anche:</td></tr>
  <tr><td style="padding:18px 30px 0">
    <div style="background:#141826;border:1px solid #1f2536;border-radius:11px;padding:18px 20px">
      <ul style="margin:0;padding-left:18px;color:#6ee7ff;font-size:16px;font-weight:600">' . $lista_html . '</ul>
    </div></td></tr>
  <tr><td style="padding:22px 30px 0" align="center">
    <a href="' . $e($base) . '/index.php" style="display:inline-block;background:#6ee7ff;color:#0a0b12;text-decoration:none;font-size:15px;font-weight:700;padding:14px 30px;border-radius:9px">Entra nel corso</a>
  </td></tr>
  <tr><td style="padding:18px 30px 0;color:#6c7790;font-size:13px;line-height:1.6" align="center">
    Usi il codice di sempre: <b style="color:#a4aec6;letter-spacing:.08em">' . $e($code['code']) . '</b><br>
    Non serve registrarsi né crearne uno nuovo.</td></tr>
  <tr><td style="padding:26px 30px 30px;border-top:1px solid #1f2536;color:#4a5368;font-size:12.5px;line-height:1.6">'
  . nl2br($e(setting('mail_sign', $corso))) . '</td></tr>
</table></td></tr></table></body></html>';

    $text = ($nome ? "Ciao $nome,\n" : "Ciao,\n")
          . "grazie per il tuo acquisto. Da adesso trovi dentro il corso anche:\n\n"
          . $lista_txt . "\n\nEntra qui: " . $base . "/index.php"
          . "\nCodice di sempre: " . $code['code'] . "\n";

    (new Smtp($host, (int)setting('smtp_port', '465'), $user, $pass, setting('smtp_sec', 'ssl')))
        ->send(setting('smtp_from', $user), setting('smtp_name', '3D WEB LAB'), $to, $subject, $html, $text);
}
