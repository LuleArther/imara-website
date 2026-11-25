<?php
declare(strict_types=1);

/**
 * SQLite database (one file, easy to back up on shared hosting).
 * Personal and health fields are encrypted by the app before they are stored (see crypto.php).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $path = (string) config('db_path');
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    @chmod($path, 0640);
    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = db_migrations();
    foreach ($migrations as $target => $sql) {
        if ($version >= $target) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->exec('PRAGMA user_version = ' . (int) $target);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $version = $target;
    }
}

/** Append new migrations with the next number. Never edit an old one once deployed. */
function db_migrations(): array
{
    return [
        1 => <<<SQL
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin','coordinator','clinician','viewer')),
    active INTEGER NOT NULL DEFAULT 1,
    must_change_password INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    last_login_at TEXT
);

CREATE TABLE requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    is_urgent INTEGER NOT NULL DEFAULT 0,
    urgency TEXT NOT NULL,
    -- encrypted personal / health data
    name_enc TEXT,
    phone_enc TEXT,
    concern_enc TEXT,
    guardian_enc TEXT,
    previous_details_enc TEXT,
    outcome_enc TEXT,
    -- blind index for exact phone search (HMAC, not reversible)
    phone_hash TEXT,
    -- non-identifying details used for routing and reports
    age INTEGER,
    is_minor INTEGER NOT NULL DEFAULT 0,
    guardian_consent INTEGER NOT NULL DEFAULT 0,
    gender TEXT,
    location TEXT,
    location_other TEXT,
    insurance TEXT,
    service TEXT NOT NULL,
    service_other TEXT,
    duration TEXT,
    assistance TEXT,
    preferred_facility TEXT,
    previous_care TEXT,
    heard TEXT,
    consent_care INTEGER NOT NULL DEFAULT 0,
    consent_whatsapp INTEGER NOT NULL DEFAULT 0,
    consent_at TEXT,
    privacy_version TEXT,
    client_token TEXT UNIQUE,
    ip_hash TEXT,
    -- referral workflow (filled in by staff)
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    recommended_facility TEXT,
    receiving_institution TEXT,
    referral_date TEXT,
    reviewed_by TEXT,
    follow_up_date TEXT,
    accessed_care TEXT NOT NULL DEFAULT 'unknown',
    closed_reason TEXT,
    first_contact_at TEXT,
    linked_at TEXT,
    closed_at TEXT,
    anonymised_at TEXT
);
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_created ON requests(created_at);
CREATE INDEX idx_requests_phone ON requests(phone_hash);
CREATE INDEX idx_requests_urgent ON requests(is_urgent, status);

CREATE TABLE request_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    type TEXT NOT NULL,
    from_status TEXT,
    to_status TEXT,
    note_enc TEXT
);
CREATE INDEX idx_events_request ON request_events(request_id);

CREATE TABLE submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    reference TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    summary TEXT,
    name_enc TEXT,
    contact_enc TEXT,
    payload_enc TEXT,
    notes_enc TEXT,
    amount INTEGER,
    client_token TEXT UNIQUE,
    ip_hash TEXT
);
CREATE INDEX idx_submissions_type ON submissions(type, status);

CREATE TABLE directory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL CHECK (kind IN ('specialist','facility')),
    name TEXT NOT NULL,
    category TEXT,
    location TEXT,
    description TEXT,
    phone TEXT,
    hours TEXT,
    services TEXT,
    published INTEGER NOT NULL DEFAULT 1,
    sort INTEGER NOT NULL DEFAULT 100,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    user_name TEXT,
    action TEXT NOT NULL,
    entity TEXT,
    entity_id INTEGER,
    ip TEXT,
    details TEXT
);
CREATE INDEX idx_audit_entity ON audit_log(entity, entity_id);
CREATE INDEX idx_audit_created ON audit_log(created_at);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    channel TEXT NOT NULL,
    recipient TEXT NOT NULL,
    subject TEXT,
    status TEXT NOT NULL,
    error TEXT,
    entity TEXT,
    entity_id INTEGER
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT,
    ip TEXT,
    created_at INTEGER NOT NULL,
    success INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_login_attempts ON login_attempts(email, ip, created_at);

CREATE TABLE rate_limits (
    rkey TEXT PRIMARY KEY,
    window_start INTEGER NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE settings (
    skey TEXT PRIMARY KEY,
    value TEXT
);

INSERT INTO settings (skey, value) VALUES
    ('impact_patients', '1000'),
    ('impact_professionals', '20'),
    ('impact_institutions', '5'),
    ('impact_policy', '5'),
    ('impact_youth', '1000'),
    ('impact_note', 'Figures from Imara medical camps and outreaches in Gulu, Kitgum and Lira.'),
    ('retention_months', '24');

INSERT INTO directory (kind, name, category, location, description, published, sort, created_at, updated_at) VALUES
    ('facility', 'Gulu Regional Referral Hospital', 'Public referral hospital', 'Gulu City', 'Government regional referral hospital serving the Acholi sub-region.', 1, 10, datetime('now'), datetime('now')),
    ('facility', 'St. Mary''s Hospital Lacor', 'Private not-for-profit hospital', 'Gulu', 'Large missionary hospital with general and specialist services.', 1, 20, datetime('now'), datetime('now')),
    ('facility', 'Gulu Independent Hospital', 'Private hospital', 'Gulu City', 'Private hospital in Gulu City.', 1, 30, datetime('now'), datetime('now'));
SQL,
    ];
}
