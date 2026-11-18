-- ITiKODE local database (SQLite). Created automatically by config/database.php
-- or manually with:  php setup.php

CREATE TABLE IF NOT EXISTS users (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    name                TEXT NOT NULL,
    email               TEXT NOT NULL UNIQUE,
    password_hash       TEXT NOT NULL,
    role                TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student','teacher','admin')),
    status              TEXT NOT NULL DEFAULT 'active'  CHECK (status IN ('active','suspended')),
    must_change_password INTEGER NOT NULL DEFAULT 0,
    session_version     INTEGER NOT NULL DEFAULT 1,       -- bump to sign the user out everywhere
    totp_secret         TEXT,                             -- base32, NULL until 2FA is enrolled
    totp_enabled        INTEGER NOT NULL DEFAULT 0,
    totp_last_step      INTEGER NOT NULL DEFAULT 0,       -- blocks code replay
    recovery_codes      TEXT,                             -- JSON array of password_hash()ed one-time codes
    last_login_at       DATETIME,
    last_login_ip       TEXT,
    password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sections (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    position   INTEGER NOT NULL DEFAULT 0,
    name       TEXT NOT NULL,                              -- sidebar / dashboard label
    title      TEXT NOT NULL,                              -- page heading
    type       TEXT NOT NULL DEFAULT 'lesson' CHECK (type IN ('lesson','quiz','assessment','evaluation')),
    duration   TEXT NOT NULL DEFAULT '~15 mins',
    direction  TEXT NOT NULL DEFAULT '',                   -- instructions shown above questions
    html       TEXT NOT NULL DEFAULT '',                   -- lesson body (type = lesson), compiled from `blocks` by the lesson builder
    blocks     TEXT,                                       -- JSON from the teacher's block editor (NULL = built-in / custom layout)
    published  INTEGER NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS questions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    section_id  INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    position    INTEGER NOT NULL DEFAULT 0,
    part        TEXT NOT NULL DEFAULT '',
    text        TEXT NOT NULL,
    options     TEXT NOT NULL,                             -- JSON array of strings
    correct     INTEGER NOT NULL DEFAULT 0,                -- index into options
    explanation TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS user_progress (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    section_id   INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    score        INTEGER DEFAULT NULL,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (user_id, section_id)
);

CREATE TABLE IF NOT EXISTS password_resets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token      TEXT NOT NULL,                              -- sha256 of the emailed token
    expires_at DATETIME NOT NULL,
    used       INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL,
    ip         TEXT NOT NULL,
    success    INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL                            -- unix time
);
CREATE INDEX IF NOT EXISTS idx_attempts_email ON login_attempts (email, created_at);
CREATE INDEX IF NOT EXISTS idx_attempts_ip    ON login_attempts (ip, created_at);

CREATE TABLE IF NOT EXISTS audit_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER,
    actor      TEXT NOT NULL DEFAULT '',
    action     TEXT NOT NULL,
    detail     TEXT NOT NULL DEFAULT '',
    ip         TEXT NOT NULL DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

-- Classroom board: posts teachers write, shown on every learner dashboard.
CREATE TABLE IF NOT EXISTS announcements (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    kind       TEXT NOT NULL DEFAULT 'update' CHECK (kind IN ('update','tip','deadline')),
    title      TEXT NOT NULL,
    body       TEXT NOT NULL DEFAULT '',
    due_date   TEXT,                                       -- YYYY-MM-DD, used by deadlines
    pinned     INTEGER NOT NULL DEFAULT 0,
    published  INTEGER NOT NULL DEFAULT 1,
    class_id   INTEGER,                                    -- NULL = every student; otherwise only that class
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Which "Try it yourself" code challenges each student has solved (for the teacher's analytics).
CREATE TABLE IF NOT EXISTS practice_progress (
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    section_id   INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    challenge_id TEXT NOT NULL,
    solved_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, section_id, challenge_id)
);

-- How hard each practice was for each student (wrong code runs, card-game misses, time, skips). Teachers read this.
CREATE TABLE IF NOT EXISTS practice_stats (
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    section_id   INTEGER NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
    challenge_id TEXT NOT NULL,
    runs         INTEGER NOT NULL DEFAULT 0,
    fails        INTEGER NOT NULL DEFAULT 0,
    flips        INTEGER NOT NULL DEFAULT 0,
    misses       INTEGER NOT NULL DEFAULT 0,
    seconds      INTEGER NOT NULL DEFAULT 0,
    skipped      INTEGER NOT NULL DEFAULT 0,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, section_id, challenge_id)
);

-- Classes: a teacher's group of students. Students join with a short code (or the teacher adds them).
CREATE TABLE IF NOT EXISTS classes (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name         TEXT NOT NULL,
    join_code    TEXT NOT NULL UNIQUE,
    joining_open INTEGER NOT NULL DEFAULT 1,
    archived     INTEGER NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS class_members (
    class_id  INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
    user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, user_id)
);
CREATE INDEX IF NOT EXISTS idx_members_user ON class_members (user_id);
