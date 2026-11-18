<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dataDir = dirname(__DIR__) . '/data';
    if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

    // ITIKODE_DB lets the self-test (tests/smoke.php) use a throwaway database
    $dbFile = getenv('ITIKODE_DB') ?: ($dataDir . '/itikode.db');
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    _migrate($pdo);
    _seedSettings($pdo);
    _seedUsers($pdo);
    _seedSections($pdo);
    $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('lessons_seed_version', ?)")->execute([(string) LESSON_SEED_VERSION]);

    return $pdo;
}

/** Upgrades databases created by an older version (CREATE TABLE IF NOT EXISTS cannot add columns). */
function _migrate(PDO $db): void
{
    $cols = array_column($db->query("PRAGMA table_info(sections)")->fetchAll(), 'name');
    if (!in_array('blocks', $cols, true)) {
        $db->exec("ALTER TABLE sections ADD COLUMN blocks TEXT");
    }
    $acols = array_column($db->query("PRAGMA table_info(announcements)")->fetchAll(), 'name');
    if (!in_array('class_id', $acols, true)) {
        $db->exec("ALTER TABLE announcements ADD COLUMN class_id INTEGER");
    }
}

function _seedSettings(PDO $db): void
{
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach (DEFAULT_SETTINGS as $k => $v) $stmt->execute([$k, $v]);
}

function _seedUsers(PDO $db): void
{
    if ((int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn() > 0) return;
    require_once dirname(__DIR__) . '/includes/security.php';
    $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    foreach (SEED_USERS as [$name, $email, $password, $role]) {
        $stmt->execute([$name, $email, hashPassword($password), $role]);
    }
}

/** First-run content: the 13-section module that used to be hard-coded in PHP. */
function _seedSections(PDO $db): void
{
    if ((int) $db->query("SELECT COUNT(*) FROM sections")->fetchColumn() > 0) return;
    require_once dirname(__DIR__) . '/includes/seed_content.php';

    $meta = [
        1  => ['Pre-test',                'quiz',       '~15 mins'],
        2  => ['Motivation',              'lesson',     '~10 mins'],
        3  => ['Introduction & ILOs',     'lesson',     '~15 mins'],
        4  => ['Topic 1: Intro to OOP',   'lesson',     '~30 mins'],
        5  => ['Formative Assessment 1',  'assessment', '~20 mins'],
        6  => ['Topic 2: Inheritance',    'lesson',     '~20 mins'],
        7  => ['Formative Assessment 2',  'assessment', '~15 mins'],
        8  => ['Topic 3: Encapsulation',  'lesson',     '~20 mins'],
        9  => ['Formative Assessment 3',  'assessment', '~15 mins'],
        10 => ['Topic 4: Polymorphism',   'lesson',     '~20 mins'],
        11 => ['Formative Assessment 4',  'assessment', '~15 mins'],
        12 => ['Post-test',               'quiz',       '~15 mins'],
        13 => ['Course Evaluation',       'evaluation', '~10 mins'],
    ];

    $db->beginTransaction();
    $insS = $db->prepare("INSERT INTO sections (id, position, name, title, type, duration, direction, html) VALUES (?,?,?,?,?,?,?,?)");
    $insQ = $db->prepare("INSERT INTO questions (section_id, position, part, text, options, correct, explanation) VALUES (?,?,?,?,?,?,?)");
    foreach ($meta as $id => [$name, $type, $duration]) {
        $c = seedSectionContent($id);
        $insS->execute([$id, $id, $name, $c['title'] ?? $name, $c['type'] ?? $type, $duration, $c['direction'] ?? '', $c['html'] ?? '']);
        foreach (($c['questions'] ?? []) as $i => $q) {
            $insQ->execute([$id, $i + 1, $q['part'] ?? '', $q['text'], json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                            (int) ($q['correct'] ?? 0), $q['explanation'] ?? '']);
        }
    }
    $db->commit();
}
