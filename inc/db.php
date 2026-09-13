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
