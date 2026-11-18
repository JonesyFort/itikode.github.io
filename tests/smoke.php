<?php
declare(strict_types=1);
/**
 * Optional self-test.  Run from the project folder:   php tests/smoke.php
 * It uses a throwaway database in your temp folder and never touches data/itikode.db.
 * Send the output to the project owner if any line says [FAIL].
 */
$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itikode_smoke_' . getmypid() . '.db';
putenv('ITIKODE_DB=' . $tmp);

$fails = 0; $checks = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $fails, $checks;
    $checks++;
    if (!$ok) $fails++;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $label . ($ok || $detail === '' ? '' : "\n           " . $detail) . "\n";
}

/* 1 ── syntax of every PHP file */
echo "1. Syntax check\n";
$bad = 0; $n = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || str_contains($p, DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR)) continue;
    $n++;
    try { token_get_all((string) file_get_contents($p), TOKEN_PARSE); }
    catch (ParseError $e) { $bad++; check(substr($p, strlen($root) + 1), false, $e->getMessage() . ' on line ' . $e->getLine()); }
}
check("$n PHP files parse without errors", $bad === 0);

/* 2 ── the PHP lesson compiler must produce exactly the designed pages */
echo "2. Lesson compiler\n";
require_once $root . '/includes/functions.php';
require_once $root . '/includes/lesson_builder.php';
require_once $root . '/includes/seed_lessons.php';
$meta = [
    2 => ['Why Learn OOP in Python?', '~5 mins'], 3 => ['Introduction & Learning Outcomes', '~10 mins'],
    4 => ['Topic 1: Introduction to OOP', '~30 mins'], 6 => ['Topic 2: Inheritance', '~20 mins'],
    8 => ['Topic 3: Encapsulation', '~20 mins'], 10 => ['Topic 4: Polymorphism', '~20 mins'],
];
foreach ($meta as $id => [$title, $dur]) {
    $blocks = lbFromJson(lbSeedJson($id));
    $errs   = lbValidate($blocks, $title);
    $php    = lbCompile($blocks, $id, $title, $dur);
    $ref    = (string) lessonHtml($id);
    $detail = '';
    if ($php !== $ref) {
        $i = 0; $max = min(strlen($php), strlen($ref));
        while ($i < $max && $php[$i] === $ref[$i]) $i++;
        $detail = 'first difference at character ' . $i . "\n           expected: " . substr($ref, max(0, $i - 30), 90) . "\n           got:      " . substr($php, max(0, $i - 30), 90);
    }
    check("lesson $id ($title) compiles to the designed page", $php === $ref && !$errs, $detail . ($errs ? ' ' . implode(' ', $errs) : ''));
}

/* 3 ── classes, join codes, import, announcements (on the throwaway database) */
echo "3. Classes\n";
require_once $root . '/includes/classes.php';
require_once $root . '/includes/analytics.php';
$db      = getDB();
$teacher = (int) $db->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
$stu     = $db->query("SELECT id, email FROM users WHERE role = 'student' LIMIT 1")->fetch();
$tEmail  = (string) $db->query("SELECT email FROM users WHERE role = 'teacher' LIMIT 1")->fetchColumn();
check('sample teacher and student exist', $teacher > 0 && $stu);

$code = generateJoinCode();
check('join code is 6 readable characters', preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $code) === 1, $code);
$db->prepare("INSERT INTO classes (teacher_id, name, join_code) VALUES (?, ?, ?)")->execute([$teacher, 'Smoke class', $code]);
$cid   = (int) $db->lastInsertId();
$class = classOwnedBy($cid, $teacher);
check('class belongs to its teacher', $class !== null && classOwnedBy($cid, $teacher + 999) === null);
$typed = strtolower(substr($code, 0, 3) . ' - ' . substr($code, 3));
check('code works when typed in lowercase with a dash', (classByCode($typed)['id'] ?? 0) === $cid);

check('student joins the class', addToClass($cid, (int) $stu['id']) === true);
check('joining twice does nothing', addToClass($cid, (int) $stu['id']) === false);
check('student sees the class', count(studentClasses((int) $stu['id'])) === 1);
check('teacher scope contains the student', teacherScopeIds($teacher, $cid) === [(int) $stu['id']] && teacherCanSee($teacher, (int) $stu['id']));
check('another teacher sees nobody', teacherScopeIds($teacher + 999, $cid) === [] && !teacherCanSee($teacher + 999, (int) $stu['id']));

$rows = parseStudentRows("name,email\nAna Reyes, ana@test.edu\nben@test.edu\nnot an email\n" . $stu['email'] . "\n" . $tEmail . "\n");
check('import list is read (5 rows, header skipped)', count($rows) === 5, json_encode($rows));
$res = importStudents($class, $rows);
$st  = array_column($res, 'status');
check('import: 2 created, 1 existing student kept, 2 skipped', $st === ['created', 'created', 'skipped', 'already', 'skipped'], json_encode($st));
$pw = $res[0]['password'];
$row = $db->prepare("SELECT password_hash, must_change_password, role FROM users WHERE email = 'ana@test.edu'");
$row->execute();
$ana = $row->fetch();
check('new account is a student with a working temporary password', $ana && $ana['role'] === 'student' && password_verify($pw, $ana['password_hash']));
check('new account must change its password', $ana && (int) $ana['must_change_password'] === 1);
check('imported students are in the class', count(teacherScopeIds($teacher, $cid)) === 3);
check('gradebook respects the class filter', count(gradebookData(teacherScopeIds($teacher, $cid))['students']) === 3 && count(gradebookData([])['students']) === 0);

$db->prepare("INSERT INTO classes (teacher_id, name, join_code) VALUES (?, 'Other class', ?)")->execute([$teacher, generateJoinCode()]);
$other = (int) $db->lastInsertId();
$ins = $db->prepare("INSERT INTO announcements (author_id, kind, title, body, class_id) VALUES (?, 'update', ?, '', ?)");
$ins->execute([$teacher, 'For everyone', null]);
$ins->execute([$teacher, 'For my class', $cid]);
$ins->execute([$teacher, 'For another class', $other]);
$titles = array_column(getAnnouncements(20, (int) $stu['id']), 'title');
sort($titles);
check('student sees everyone-posts and own-class posts only', $titles === ['For everyone', 'For my class'], json_encode($titles));
check('without a student filter all posts are listed', count(getAnnouncements(20)) === 3);

/* 4 ── card-game rounds and practice effort */
echo "4. Practice rounds and effort\n";
$roundCounts = ['4' => 5, '6' => 4, '8' => 4, '10' => 4];
foreach ($roundCounts as $lid => $expect) {
    $b = lbSeedBlocks((int) $lid);
    $with = 0; $okAll = true; $covered = true;
    foreach ($b['parts'] as $part) {
        if (empty($part['round']['on'])) continue;
        $with++;
        $tiles = $part['round']['tiles'];
        if ($part['round']['mode'] !== 'match' || count($tiles) !== 8 || count(array_unique($tiles)) !== 8) $okAll = false;
        if (!str_contains($part['round']['starter'], '___') || str_contains($part['round']['solution'], '___')) $okAll = false;
        $need = lbBlankAnswers($part['round']['starter'], $part['round']['solution']);
        if ($need === null || array_diff($need, $tiles)) $covered = false;
    }
    check("lesson $lid has $expect rounds with 8 matching words, valid blanks", $with === $expect && $okAll, "found $with");
    check("lesson $lid: the card words cover every blank in the code", $covered);
}
$al = lbBlankAnswers('x = ___ + ___', 'x = 1 + 2');
check('blank answers are read from the solution', $al === ['1', '2'] && lbBlankAnswers('print(___)', 'something else') === null);
$gap = lbFromJson(json_encode(['parts' => [['title' => 'X', 'concept' => 'c', 'round' => ['on' => true, 'task' => 't', 'starter' => 'print(___)', 'solution' => 'print(7)', 'expected' => [['t' => 'line', 'v' => '7']], 'mode' => 'match', 'tiles' => ['a', 'b', 'c', 'd']]]]]));
check('editor warns when the cards miss a word the blank needs', (bool) array_filter(lbValidate($gap, 'X'), fn($e) => str_contains($e, 'missing words')), json_encode(lbValidate($gap, 'X')));
$badBlocks = lbFromJson(json_encode(['parts' => [['title' => 'X', 'concept' => 'c', 'round' => ['on' => true, 'task' => 't', 'starter' => 'print(1)', 'solution' => 'print(1)', 'expected' => [['t' => 'line', 'v' => '1']], 'pairs' => [['clue' => 'a', 'answer' => 'b']]]]]]));
$problems = lbValidate($badBlocks, 'X');
check('editor rejects a round with too few pairs and no blanks', count($problems) >= 2, json_encode($problems));

$lessonId = (int) $db->query("SELECT id FROM sections WHERE html LIKE '%t1-paradigms-round%' LIMIT 1")->fetchColumn();
check('built-in lesson page contains the card game', $lessonId > 0);
$sid = (int) $stu['id'];
recordPracticeStats($sid, $lessonId, 't1-paradigms-round', ['runs' => 3, 'fails' => 2, 'flips' => 10, 'misses' => 4, 'seconds' => 90, 'skipped' => 1]);
recordPracticeStats($sid, $lessonId, 't1-paradigms-round', ['runs' => 1, 'fails' => 1, 'flips' => 2, 'misses' => 1, 'seconds' => 30, 'skipped' => 1]);   // an older report: counters must not go down
$row = $db->prepare("SELECT * FROM practice_stats WHERE user_id = ? AND challenge_id = 't1-paradigms-round'");
$row->execute([$sid]);
$r = $row->fetch();
check('effort counters only go up', $r && (int) $r['runs'] === 3 && (int) $r['fails'] === 2 && (int) $r['seconds'] === 90 && (int) $r['skipped'] === 1);
check('gradebook counts the skipped, unsolved practice', (gradebookData([$sid])['students'][0]['skipped'] ?? -1) === 1);
$db->prepare("INSERT INTO practice_progress (user_id, section_id, challenge_id) VALUES (?, ?, 't1-paradigms-round')")->execute([$sid, $lessonId]);
check('solving it clears the skipped count', (gradebookData([$sid])['students'][0]['skipped'] ?? -1) === 0);
$effort = practiceStatsFor($sid);
check('student page can list the effort (solved)', count($effort) === 1 && (int) $effort[0]['solved'] === 1);
$titles = practiceTitleMap();
check('practice titles are found in the lesson pages', isset($titles['t1-paradigms-round']) && $titles['t1-paradigms-round']['round'] === true && isset($titles['t1-paradigms']));
check('built-in lessons can be updated from the app', refreshBuiltInLessons() === 6
    && str_contains((string) $db->query("SELECT html FROM sections WHERE id = 4")->fetchColumn(), 'data-mode="match"')
    && (string) $db->query("SELECT value FROM settings WHERE key = 'lessons_seed_version'")->fetchColumn() === (string) LESSON_SEED_VERSION);
check('time is shown readably', niceDuration(45) === '45s' && niceDuration(300) === '5 min');

foreach (['', '-wal', '-shm'] as $suffix) { @unlink($tmp . $suffix); }
echo "\n" . ($fails === 0 ? "All $checks checks passed." : "$fails of $checks checks FAILED. Please send this output to the project owner.") . "\n";
exit($fails === 0 ? 0 : 1);
