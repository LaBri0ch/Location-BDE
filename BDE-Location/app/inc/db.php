<?php
declare(strict_types=1);

const SCHEMA_VERSION = 3;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    return $pdo;
}

function is_installed(): bool
{
    return is_file(INSTALL_LOCK) && is_file(DB_FILE);
}

function require_installed(): void
{
    if (!is_installed()) {
        redirect('install.php');
    }
}

function install_schema(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        stock INTEGER NOT NULL DEFAULT 1,
        caution REAL NOT NULL DEFAULT 0,
        price_day REAL NOT NULL DEFAULT 0,
        helloasso_url TEXT NOT NULL DEFAULT '',
        swikly_url TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        is_admin INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        last_login TEXT,
        phone TEXT NOT NULL DEFAULT '',
        notify INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS admin_invites (
        email TEXT PRIMARY KEY COLLATE NOCASE,
        code_hash TEXT NOT NULL,
        created_by TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS reservations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ref TEXT NOT NULL UNIQUE,
        token TEXT NOT NULL UNIQUE,
        user_id INTEGER,
        item_id INTEGER,
        item_name TEXT NOT NULL,
        qty INTEGER NOT NULL DEFAULT 1,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        days INTEGER NOT NULL,
        borrower TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT '',
        total REAL NOT NULL DEFAULT 0,
        caution REAL NOT NULL DEFAULT 0,
        helloasso_url TEXT NOT NULL DEFAULT '',
        swikly_url TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'demandee',
        admin_note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_resa_item ON reservations(item_id, start_date, end_date);
    CREATE INDEX IF NOT EXISTS idx_resa_status ON reservations(status);
    CREATE INDEX IF NOT EXISTS idx_resa_user ON reservations(user_id);
    CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reservation_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        by_user TEXT NOT NULL DEFAULT '',
        at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts(ip, at);
    PRAGMA user_version = " . SCHEMA_VERSION . ";
    ");
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $c) {
        if ($c['name'] === $column) {
            return true;
        }
    }
    return false;
}

/** Met à niveau une base créée par une version précédente du site (sans rien supprimer). */
function migrate_schema(PDO $pdo): void
{
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() >= SCHEMA_VERSION) {
        return;
    }
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            last_login TEXT
        );
        CREATE TABLE IF NOT EXISTS admin_invites (
            email TEXT PRIMARY KEY COLLATE NOCASE,
            code_hash TEXT NOT NULL,
            created_by TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        );");
        if (!column_exists($pdo, 'items', 'image')) {
            $pdo->exec("ALTER TABLE items ADD COLUMN image TEXT NOT NULL DEFAULT ''");
        }
        if (!column_exists($pdo, 'reservations', 'user_id')) {
            $pdo->exec('ALTER TABLE reservations ADD COLUMN user_id INTEGER');
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_resa_user ON reservations(user_id)');
        if (!column_exists($pdo, 'users', 'phone')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT NOT NULL DEFAULT ''");
        }
        if (!column_exists($pdo, 'users', 'notify')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify INTEGER NOT NULL DEFAULT 1');
        }
        $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Throwable $ignored) {
        }
        throw $e;
    }
}

/* ---------- Réglages ---------- */

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
}
