<?php
declare(strict_types=1);
/**
 * Classes: a teacher's group of students. Students join with a short code, or the teacher adds
 * them (existing accounts) or imports them from a list (new accounts with temporary passwords).
 */
require_once __DIR__ . '/functions.php';

const JOIN_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no 0/O/1/I, so codes are easy to read aloud
const IMPORT_MAX_ROWS = 100;

function generateJoinCode(): string
{
    $db  = getDB();
    $max = strlen(JOIN_CODE_CHARS) - 1;
    for ($try = 0; $try < 25; $try++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= JOIN_CODE_CHARS[random_int(0, $max)];
        $st = $db->prepare("SELECT 1 FROM classes WHERE join_code = ?");
        $st->execute([$code]);
        if (!$st->fetch()) return $code;
    }
    throw new RuntimeException('Could not create a join code. Please try again.');
}

/** "abc-123" / " ABC 123 " -> "ABC123". */
function normalizeJoinCode(string $raw): string
{
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
}

/** A teacher's classes (active first, then archived) with member counts. */
function teacherClasses(int $teacherId): array
{
    $st = getDB()->prepare("SELECT c.*, (SELECT COUNT(*) FROM class_members m WHERE m.class_id = c.id) AS members
                            FROM classes c WHERE c.teacher_id = ? ORDER BY c.archived, c.created_at DESC, c.id DESC");
    $st->execute([$teacherId]);
    return $st->fetchAll();
}

function classOwnedBy(int $classId, int $teacherId): ?array
{
    $st = getDB()->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
    $st->execute([$classId, $teacherId]);
    $row = $st->fetch();
    return $row ?: null;
}

function classByCode(string $code): ?array
{
    $st = getDB()->prepare("SELECT c.*, u.name AS teacher FROM classes c LEFT JOIN users u ON u.id = c.teacher_id WHERE c.join_code = ?");
    $st->execute([normalizeJoinCode($code)]);
    $row = $st->fetch();
    return $row ?: null;
}

/** The (active) classes a student belongs to. */
function studentClasses(int $userId): array
{
    $st = getDB()->prepare("SELECT c.id, c.name, u.name AS teacher FROM class_members m
                            JOIN classes c ON c.id = m.class_id AND c.archived = 0
                            LEFT JOIN users u ON u.id = c.teacher_id
                            WHERE m.user_id = ? ORDER BY c.name");
    $st->execute([$userId]);
    return $st->fetchAll();
}

/** Adds a student to a class. Returns true if they were not already in it. */
function addToClass(int $classId, int $userId): bool
{
    $st = getDB()->prepare("INSERT OR IGNORE INTO class_members (class_id, user_id) VALUES (?, ?)");
    $st->execute([$classId, $userId]);
    return $st->rowCount() > 0;
}

/**
 * Student ids a teacher may see in the gradebook: members of one of their classes (when $classId is given),
 * or of any of their active classes.
 * @return int[]
 */
function teacherScopeIds(int $teacherId, ?int $classId = null): array
{
    $db = getDB();
    if ($classId !== null && $classId > 0) {
        if (!classOwnedBy($classId, $teacherId)) return [];
        $st = $db->prepare("SELECT DISTINCT m.user_id FROM class_members m JOIN users u ON u.id = m.user_id AND u.role = 'student' WHERE m.class_id = ?");
        $st->execute([$classId]);
    } else {
        $st = $db->prepare("SELECT DISTINCT m.user_id FROM class_members m
                            JOIN classes c ON c.id = m.class_id AND c.teacher_id = ? AND c.archived = 0
                            JOIN users u ON u.id = m.user_id AND u.role = 'student'");
        $st->execute([$teacherId]);
    }
    return array_map('intval', array_column($st->fetchAll(), 'user_id'));
}

/** Is this student in any class (archived ones too) owned by the teacher? */
function teacherCanSee(int $teacherId, int $studentId): bool
{
    $st = getDB()->prepare("SELECT 1 FROM class_members m JOIN classes c ON c.id = m.class_id WHERE c.teacher_id = ? AND m.user_id = ? LIMIT 1");
    $st->execute([$teacherId, $studentId]);
    return (bool) $st->fetch();
}

/** Student accounts that are in no class yet. */
function unassignedStudents(): array
{
    return getDB()->query("SELECT id, name, email FROM users u WHERE role = 'student' AND status = 'active'
                           AND NOT EXISTS (SELECT 1 FROM class_members m WHERE m.user_id = u.id) ORDER BY name COLLATE NOCASE")->fetchAll();
}

/** Reads ?class= on the teacher pages. Returns [class id or null, class row or null]. */
function currentClassFilter(int $teacherId): array
{
    $cid = (int) ($_GET['class'] ?? 0);
    $row = $cid > 0 ? classOwnedBy($cid, $teacherId) : null;
    return [$row ? (int) $row['id'] : null, $row];
}

/** A small "Class: [All my classes v]" switcher that reloads the page with ?class=. */
function classPicker(int $teacherId, ?int $current, string $path): void
{
    $classes = teacherClasses($teacherId);
    if (!$classes) return;
    echo '<form method="GET" action="' . e(u($path)) . '" style="display:flex;align-items:center;gap:8px;margin:0">'
       . '<label class="label" for="cls-pick" style="margin:0">Class</label>'
       . '<select class="select" id="cls-pick" name="class" style="min-width:200px" onchange="this.form.submit()">'
       . '<option value="0">All my classes</option>';
    foreach ($classes as $c) {
        echo '<option value="' . (int) $c['id'] . '"' . ($current === (int) $c['id'] ? ' selected' : '') . '>' . e($c['name']) . ($c['archived'] ? ' (archived)' : '') . '</option>';
    }
    echo '</select><noscript><button class="btn btn-sm" type="submit">Go</button></noscript></form>';
}

/** A temporary password that is easy to type once: Word-xxxx-123 (the student must change it at first login). */
function makeTempPassword(): string
{
    $words = ['Maple', 'River', 'Cloud', 'Tiger', 'Lemon', 'Comet', 'Pixel', 'Forest', 'Coral', 'Panda', 'Amber', 'Falcon', 'Mango', 'Orbit', 'Zebra', 'Cedar'];
    $alpha = 'abcdefghjkmnpqrstuvwxyz23456789';
    $mid = '';
    for ($i = 0; $i < 4; $i++) $mid .= $alpha[random_int(0, strlen($alpha) - 1)];
    return $words[random_int(0, count($words) - 1)] . '-' . $mid . '-' . random_int(100, 999);
}

/**
 * Reads pasted text or an uploaded CSV into rows of ['name' => ..., 'email' => ...].
 * Accepts a header row (name, email), comma / semicolon / tab separators, or just a list of emails.
 */
function parseStudentRows(string $text): array
{
    $text  = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);       // spreadsheet BOM
    $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($text)) ?: [], fn($l) => trim($l) !== ''));
    if (!$lines) return [];
    $first = $lines[0];
    $delim = str_contains($first, "\t") ? "\t" : (substr_count($first, ';') > substr_count($first, ',') ? ';' : ',');
    $rows  = array_map(fn($l) => array_map('trim', str_getcsv($l, $delim, '"', '\\')), $lines);

    $map = ['name' => 0, 'email' => 1];
    $header = false;
    foreach (array_map('strtolower', $rows[0]) as $i => $h) {
        if (in_array($h, ['email', 'e-mail', 'email address'], true)) { $map['email'] = $i; $header = true; }
        elseif (in_array($h, ['name', 'full name', 'student', 'student name'], true)) { $map['name'] = $i; $header = true; }
    }
    if ($header) array_shift($rows);

    $out = [];
    foreach ($rows as $r) {
        if (count($r) === 1 && str_contains($r[0], '@')) { $out[] = ['name' => '', 'email' => $r[0]]; continue; }
        $name  = $r[$map['name']] ?? '';
        $email = $r[$map['email']] ?? '';
        if ($map['name'] === $map['email']) $name = '';
        $out[] = ['name' => $name, 'email' => $email];
    }
    return $out;
}

/**
 * Puts the listed students in a class, creating accounts (with a temporary password) for new emails.
 * Teachers can only create student accounts here, never staff.
 * @return array<int,array{name:string,email:string,status:string,note:string,password:string}>
 */
function importStudents(array $class, array $rows): array
{
    require_once __DIR__ . '/security.php';
    $db  = getDB();
    $out = [];
    $seen = [];
    foreach (array_slice($rows, 0, IMPORT_MAX_ROWS) as $r) {
        $email = strtolower(trim((string) $r['email']));
        $name  = trim((string) $r['name']);
        $res   = ['name' => $name, 'email' => $email, 'status' => 'skipped', 'note' => '', 'password' => ''];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $res['note'] = 'Not a valid email address.';
        } elseif (isset($seen[$email])) {
            $res['note'] = 'Listed twice.';
        } else {
            $seen[$email] = true;
            $st = $db->prepare("SELECT id, name, role FROM users WHERE email = ?");
            $st->execute([$email]);
            $existing = $st->fetch();
            if ($existing) {
                $res['name'] = (string) $existing['name'];
                if ($existing['role'] !== 'student') {
                    $res['note'] = 'This email belongs to a teacher or admin account.';
                } else {
                    $res['status'] = addToClass((int) $class['id'], (int) $existing['id']) ? 'added' : 'already';
                    $res['note']   = $res['status'] === 'added' ? 'Existing account added to the class.' : 'Already in this class.';
                }
            } else {
                if ($name === '') {
                    $local = (string) strstr($email, '@', true);
                    $name  = ucwords(trim((string) preg_replace('/[._\-]+/', ' ', $local)));
                }
                $name = mb_substr($name, 0, 80);
                $pw   = makeTempPassword();
                $db->prepare("INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (?, ?, ?, 'student', 1)")
                   ->execute([$name, $email, hashPassword($pw)]);
                addToClass((int) $class['id'], (int) $db->lastInsertId());
                $res = ['name' => $name, 'email' => $email, 'status' => 'created', 'note' => 'New account created.', 'password' => $pw];
            }
        }
        $out[] = $res;
    }
    return $out;
}
