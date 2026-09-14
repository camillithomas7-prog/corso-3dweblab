<?php
// Connessione SQLite + schema. Funziona identico in locale e su Hostinger.
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('STORAGE',  APP_ROOT . '/storage');

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    $pdo = new PDO('sqlite:' . DATA_DIR . '/corso.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
    schema($pdo);
    return $pdo;
}

function schema(PDO $p): void {
    $p->exec("
    CREATE TABLE IF NOT EXISTS settings(
      k TEXT PRIMARY KEY, v TEXT NOT NULL);

    CREATE TABLE IF NOT EXISTS admins(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT UNIQUE NOT NULL,
      pass_hash TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS categories(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      descr TEXT NOT NULL DEFAULT '',
      pos INTEGER NOT NULL DEFAULT 0,
      published INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS lessons(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      category_id INTEGER REFERENCES categories(id) ON DELETE CASCADE,
      title TEXT NOT NULL,
      descr TEXT NOT NULL DEFAULT '',
      video_type TEXT NOT NULL DEFAULT 'file',   -- file | url | embed
      video_src TEXT NOT NULL DEFAULT '',
      poster TEXT NOT NULL DEFAULT '',
      duration_sec INTEGER NOT NULL DEFAULT 0,
      pos INTEGER NOT NULL DEFAULT 0,
      published INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS materials(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      lesson_id INTEGER REFERENCES lessons(id) ON DELETE CASCADE,
      category_id INTEGER REFERENCES categories(id) ON DELETE CASCADE,
      title TEXT NOT NULL,
      filename TEXT NOT NULL,
      orig_name TEXT NOT NULL DEFAULT '',
      bytes INTEGER NOT NULL DEFAULT 0,
      pos INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS codes(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code TEXT UNIQUE NOT NULL,
      label TEXT NOT NULL DEFAULT '',
      order_ref TEXT NOT NULL DEFAULT '',
      email TEXT NOT NULL DEFAULT '',
      status TEXT NOT NULL DEFAULT 'active',     -- active | revoked
      uses INTEGER NOT NULL DEFAULT 0,
      expires_at TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      first_used_at TEXT, last_used_at TEXT);

    CREATE TABLE IF NOT EXISTS progress(
      code_id INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
      lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
      seconds INTEGER NOT NULL DEFAULT 0,
      completed INTEGER NOT NULL DEFAULT 0,
      updated_at TEXT NOT NULL DEFAULT (datetime('now')),
      PRIMARY KEY (code_id, lesson_id));

    CREATE TABLE IF NOT EXISTS notes(
      code_id INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
      lesson_id INTEGER NOT NULL REFERENCES lessons(id) ON DELETE CASCADE,
      body TEXT NOT NULL DEFAULT '',
      updated_at TEXT NOT NULL DEFAULT (datetime('now')),
      PRIMARY KEY (code_id, lesson_id));

    CREATE TABLE IF NOT EXISTS packages(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      title TEXT NOT NULL,
      kicker TEXT NOT NULL DEFAULT '',
      intro TEXT NOT NULL DEFAULT '',
      bullets TEXT NOT NULL DEFAULT '',
      price REAL NOT NULL DEFAULT 0,
      price_was REAL NOT NULL DEFAULT 0,
      period TEXT NOT NULL DEFAULT '',
      note TEXT NOT NULL DEFAULT '',
      badge TEXT NOT NULL DEFAULT '',
      featured INTEGER NOT NULL DEFAULT 0,
      pos INTEGER NOT NULL DEFAULT 0,
      published INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS hooks(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      source TEXT NOT NULL DEFAULT 'shopify',
      order_id TEXT NOT NULL DEFAULT '',
      order_name TEXT NOT NULL DEFAULT '',
      email TEXT NOT NULL DEFAULT '',
      status TEXT NOT NULL DEFAULT '',
      note TEXT NOT NULL DEFAULT '',
      code_id INTEGER,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE INDEX IF NOT EXISTS ix_les_cat ON lessons(category_id, pos);
    CREATE INDEX IF NOT EXISTS ix_mat_les ON materials(lesson_id);
    CREATE INDEX IF NOT EXISTS ix_hooks ON hooks(created_at DESC);
    ");
    migrate($p);
    seed_packages($p);
}

/** I tre pacchetti di partenza, inseriti una volta sola se la tabella è vuota. */
function seed_packages(PDO $p): void {
    if ((int)$p->query('SELECT COUNT(*) FROM packages')->fetchColumn() > 0) return;
    $i = $p->prepare('INSERT INTO packages(title,kicker,intro,bullets,price,price_was,
                      period,note,badge,featured,pos) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $i->execute([
        'Creiamo il tuo sito 3D',
        'Sviluppo su misura',
        'Il nostro team di sviluppatori costruisce il sito al posto tuo. Tu porti l\'idea, noi la realizziamo.',
        "Subito dopo il pagamento fissiamo una chiamata per capire il progetto\n"
        . "Progettazione e sviluppo del sito 3D completo\n"
        . "Ottimizzazione per desktop e telefono\n"
        . "Messa online e consegna del progetto finito",
        299, 890, '', 'Prezzo riservato agli studenti del corso.', '', 0, 1]);
    $i->execute([
        'Consulenza tecnica',
        'Un\'ora con un developer',
        'Sei bloccato su qualcosa e non ne esci. Un\'ora con un nostro sviluppatore per capire dov\'è il problema e come si risolve.',
        "Un'ora in videochiamata, uno a uno\n"
        . "Porti il tuo progetto e il punto in cui ti sei fermato\n"
        . "Risolviamo insieme, con te che guardi come si fa\n"
        . "Riepilogo scritto di quanto visto",
        99, 180, '', 'Prezzo riservato agli studenti del corso.', 'Il più richiesto', 1, 2]);
    $i->execute([
        '30 giorni con un coach',
        'Accompagnamento al lancio',
        'Un coach esperto di siti e marketing ti segue per un mese intero, dalla costruzione del sito fino alla pubblicità online.',
        "Un coach dedicato al tuo progetto per 30 giorni\n"
        . "Costruzione del sito seguita passo passo\n"
        . "Preparazione dei canali social\n"
        . "Impostazione e lancio delle pubblicità online\n"
        . "Confronto continuo su ogni aspetto del progetto",
        999, 1650, 'al mese',
        'Durata minima 30 giorni. Dal secondo mese il mantenimento del coach costa 750 € al mese.',
        '', 0, 3]);
}

/** Colonne aggiunte dopo il primo rilascio: si applicano una volta sola. */
function migrate(PDO $p): void {
    $cols = array_column($p->query('PRAGMA table_info(codes)')->fetchAll(), 'name');
    foreach ([
        'shopify_order_id' => "TEXT NOT NULL DEFAULT ''",
        'email_sent_at'    => "TEXT",
        'email_error'      => "TEXT NOT NULL DEFAULT ''",
    ] as $c => $def) {
        if (!in_array($c, $cols, true)) $p->exec("ALTER TABLE codes ADD COLUMN $c $def");
    }
    $p->exec("CREATE UNIQUE INDEX IF NOT EXISTS ix_codes_order
              ON codes(shopify_order_id) WHERE shopify_order_id <> ''");
}

function setting(string $k, ?string $def = null): ?string {
    $r = db()->prepare('SELECT v FROM settings WHERE k=?'); $r->execute([$k]);
    $v = $r->fetchColumn();
    return $v === false ? $def : $v;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')
        ->execute([$k, $v]);
}
