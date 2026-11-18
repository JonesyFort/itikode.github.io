<?php
declare(strict_types=1);
/**
 * Database setup for local development (command line only).
 *
 *   php setup.php            create the database and sample accounts if missing
 *   php setup.php --reset    DELETE the database and start again from scratch
 *   php setup.php --users    list accounts
 *   php setup.php --refresh-lessons   replace the Motivation, Introduction and Topic 1-4 pages with the
 *                            designed versions (overwrites any edits made to those six lessons)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/includes/auth.php';

$file = __DIR__ . '/data/itikode.db';
if (in_array('--reset', $argv, true)) {
    foreach ([$file, $file . '-wal', $file . '-shm'] as $f) if (is_file($f)) unlink($f);
    echo "Old database removed.\n";
}

$db = getDB();
if (in_array('--refresh-lessons', $argv, true)) {
    require_once __DIR__ . '/includes/lesson_builder.php';
    $n = refreshBuiltInLessons();
    echo "Built-in lessons updated: $n of 6\n";
}
echo "Database ready: $file\n";
if (in_array('--users', $argv, true) || in_array('--reset', $argv, true) || !in_array('--quiet', $argv, true)) {
    echo str_pad('ROLE', 9) . str_pad('EMAIL', 26) . "SAMPLE PASSWORD\n";
    foreach (SEED_USERS as [$n, $mail, $pass, $role]) echo str_pad($role, 9) . str_pad($mail, 26) . $pass . "\n";
}
printf("Sections: %d, questions: %d, users: %d\n",
    $db->query("SELECT COUNT(*) FROM sections")->fetchColumn(),
    $db->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    $db->query("SELECT COUNT(*) FROM users")->fetchColumn());
