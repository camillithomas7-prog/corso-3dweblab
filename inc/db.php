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

    CREATE TABLE IF NOT EXISTS profiles(
      code_id INTEGER PRIMARY KEY REFERENCES codes(id) ON DELETE CASCADE,
      first_name TEXT NOT NULL DEFAULT '',
      last_name  TEXT NOT NULL DEFAULT '',
      phone_cc   TEXT NOT NULL DEFAULT '+39',
      phone      TEXT NOT NULL DEFAULT '',
      email      TEXT NOT NULL DEFAULT '',
      birthdate  TEXT NOT NULL DEFAULT '',
      completed_at TEXT,
      updated_at TEXT NOT NULL DEFAULT (datetime('now')));

    -- quale prodotto Shopify sblocca quale modulo
    CREATE TABLE IF NOT EXISTS product_map(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      needle TEXT NOT NULL,                 -- id prodotto, id variante, SKU o parte del titolo
      category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
      note TEXT NOT NULL DEFAULT '',
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    -- a cosa ha diritto ogni corsista
    CREATE TABLE IF NOT EXISTS entitlements(
      code_id INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
      category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
      source TEXT NOT NULL DEFAULT 'ordine',   -- ordine | manuale | iniziale
      order_ref TEXT NOT NULL DEFAULT '',
      created_at TEXT NOT NULL DEFAULT (datetime('now')),
      PRIMARY KEY (code_id, category_id));

    CREATE TABLE IF NOT EXISTS messages(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      code_id INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
      sender TEXT NOT NULL,               -- utente | admin
      body TEXT NOT NULL,
      read_admin INTEGER NOT NULL DEFAULT 0,
      read_user  INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE INDEX IF NOT EXISTS ix_msg ON messages(code_id, id);

    CREATE TABLE IF NOT EXISTS questions(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      section TEXT NOT NULL DEFAULT '',
      type TEXT NOT NULL DEFAULT 'single',   -- single | text
      label TEXT NOT NULL,
      help TEXT NOT NULL DEFAULT '',
      options TEXT NOT NULL DEFAULT '',      -- una per riga, solo per single
      required INTEGER NOT NULL DEFAULT 1,
      pos INTEGER NOT NULL DEFAULT 0,
      published INTEGER NOT NULL DEFAULT 1,
      created_at TEXT NOT NULL DEFAULT (datetime('now')));

    CREATE TABLE IF NOT EXISTS answers(
      code_id INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
      question_id INTEGER NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
      value TEXT NOT NULL DEFAULT '',
      updated_at TEXT NOT NULL DEFAULT (datetime('now')),
      PRIMARY KEY (code_id, question_id));

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
    seed_questions($p);
    backfill_entitlements($p);
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
    $pc = array_column($p->query('PRAGMA table_info(profiles)')->fetchAll(), 'name');
    if (!in_array('quiz_at', $pc, true)) $p->exec('ALTER TABLE profiles ADD COLUMN quiz_at TEXT');

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

/** Le domande di partenza, inserite una volta sola. */
function seed_questions(PDO $p): void {
    if ((int)$p->query('SELECT COUNT(*) FROM questions')->fetchColumn() > 0) return;
    $q = $p->prepare('INSERT INTO questions(section,type,label,help,options,pos) VALUES(?,?,?,?,?,?)');
    $A = 'Due parole su di te';
    $B = 'La tua esperienza';

    $q->execute([$A, 'single', 'Cosa fai oggi principalmente?', '',
        "Lavoro dipendente, cerco un'entrata in più\n"
      . "Freelance web, grafica o social\n"
      . "Ho un'agenzia o uno studio\n"
      . "Ho un'attività in un altro settore\n"
      . "Studio, o sono senza lavoro in questo momento", 1]);

    $q->execute([$A, 'single', 'Quanti siti hai fatto in vita tua?', '',
        "Zero\nQualcuno per me o per amici\nLi faccio già per clienti paganti\nNe faccio tanti, è il mio lavoro", 2]);

    $q->execute([$A, 'single', "Con l'intelligenza artificiale a che punto sei?", '',
        "Non l'ho mai usata per costruire niente\n"
      . "La uso per scrivere testi e basta\n"
      . "Ci ho già provato a farci codice ma mi sono bloccato\n"
      . "Ci lavoro già tutti i giorni", 3]);

    $q->execute([$A, 'single', 'Cosa vuoi ottenere nei prossimi tre mesi?', '',
        "Il primo cliente pagante, punto\n"
      . "1.000-2.000 € al mese in più\n"
      . "Sostituire il mio lavoro attuale\n"
      . "Alzare i prezzi ai clienti che ho già\n"
      . "Imparare, i soldi vengono dopo", 4]);

    $q->execute([$A, 'single', 'Quante ore a settimana ci puoi mettere davvero?', 'Sii onesto, serve a noi per capire come aiutarti.',
        "Da 0 a 3 ore\nDa 4 a 7 ore\nDa 8 a 15 ore\nPiù di 15 ore", 5]);

    $q->execute([$B, 'text', "Qual è stata la cosa precisa che ti ha fatto dire «ok, lo compro»?",
        'Anche una frase sola. Quello che ti è passato per la testa in quel momento.', '', 6]);

    $q->execute([$B, 'text', 'Cosa ti ha fatto esitare un attimo prima di pagare?',
        'Puoi essere schietto: ci serve proprio quello.', '', 7]);

    $q->execute([$B, 'text', 'Prima di arrivare qui, cosa avevi già provato per ottenere lo stesso risultato?',
        'Corsi, video, tentativi da solo, persone a cui ti sei rivolto.', '', 8]);

    $q->execute([$B, 'text', 'Come lo racconteresti a un amico, in una frase?',
        'Con parole tue, come lo diresti davvero.', '', 9]);
}

/**
 * I corsisti che esistevano prima degli upsell mantengono tutto.
 * Gira una volta sola: la traccia resta in settings.
 */
function backfill_entitlements(PDO $p): void {
    // Disinnescata. Era un CROSS JOIN codes x categories che assegnava ogni modulo
    // a ogni corsista, e girava a ogni richiesta protetta solo dalla riga in settings:
    // se quella riga mancava, ogni caricamento di pagina rimetteva tutti i moduli a
    // tutti e i bottoni "Togli" sembravano non funzionare. Il recupero dei corsisti
    // storici ora si fa a mano da Accessi, che e' esplicito e verificabile.
    $p->exec("INSERT OR IGNORE INTO settings(k,v) VALUES('entitlements_backfill','disinnescato')");
}
