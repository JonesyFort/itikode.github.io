<?php
declare(strict_types=1);
/**
 * Read-only numbers for the teacher's gradebook and analytics.
 * Everything is derived from user_progress (completions + scores) and practice_progress (code challenges).
 */
require_once __DIR__ . '/functions.php';

/** How many "Try it yourself" editors a lesson has (counted from its compiled HTML). */
function practiceTotals(): array
{
    $out = [];
    foreach (getDB()->query("SELECT id, html FROM sections WHERE type = 'lesson'")->fetchAll() as $r) {
        $n = preg_match_all('/class="lx-play" data-id="/', (string) $r['html']);
        $out[(int) $r['id']] = $n === false ? 0 : $n;
    }
    return $out;
}

/**
 * Topic units in module order: a lesson named "Topic N..." plus the first formative assessment after it.
 * @return array<int,array{label:string,name:string,lesson:int,assess:?int}>
 */
function topicUnits(array $sections): array
{
    $units = [];
    $cur = null;
    foreach ($sections as $id => $s) {
        if ($s['type'] === 'lesson' && preg_match('/^\s*Topic\s*(\d+)/i', (string) $s['name'], $m) === 1) {
            $units[] = ['label' => 'T' . $m[1], 'name' => (string) $s['name'], 'lesson' => (int) $id, 'assess' => null];
            $cur = count($units) - 1;
            continue;
        }
        if ($s['type'] === 'assessment' && $cur !== null && $units[$cur]['assess'] === null) $units[$cur]['assess'] = (int) $id;
        if ($s['type'] === 'quiz') $cur = null;
    }
    return $units;
}

/**
 * Everything the gradebook, student page and CSV export need, in one pass.
 * $onlyIds limits the students (null = every student account); teachers pass their class members.
 */
function gradebookData(?array $onlyIds = null): array
{
    $db       = getDB();
    $sections = getAllSections();                       // published, in order
    $units    = topicUnits($sections);
    $totals   = practiceTotals();
    $quizIds  = array_keys(array_filter($sections, fn($s) => $s['type'] === 'quiz'));
    $preId    = $quizIds[0] ?? null;
    $postId   = count($quizIds) > 1 ? $quizIds[count($quizIds) - 1] : null;
    $assessIds = array_keys(array_filter($sections, fn($s) => $s['type'] === 'assessment'));

    $prog = [];
    foreach ($db->query("SELECT user_id, section_id, score, completed_at FROM user_progress")->fetchAll() as $r) {
        $prog[(int) $r['user_id']][(int) $r['section_id']] = ['score' => $r['score'] === null ? null : (int) $r['score'], 'at' => (string) $r['completed_at']];
    }
    $prac = [];
    // wrong runs, card-game misses, time and skipped (still unsolved) practices per student
    $struggle = [];
    foreach ($db->query("SELECT s.user_id,
            SUM(CASE WHEN s.skipped = 1 AND pp.challenge_id IS NULL THEN 1 ELSE 0 END) AS skipped,
            SUM(s.fails) AS fails, SUM(s.misses) AS misses, SUM(s.seconds) AS seconds
        FROM practice_stats s
        LEFT JOIN practice_progress pp ON pp.user_id = s.user_id AND pp.section_id = s.section_id AND pp.challenge_id = s.challenge_id
        GROUP BY s.user_id")->fetchAll() as $r) {
        $struggle[(int) $r['user_id']] = $r;
    }
    foreach ($db->query("SELECT user_id, section_id, COUNT(*) AS c FROM practice_progress GROUP BY user_id, section_id")->fetchAll() as $r) {
        $prac[(int) $r['user_id']][(int) $r['section_id']] = (int) $r['c'];
    }

    $students = [];
    $rows = $db->query("SELECT id, name, email, status, last_login_at FROM users WHERE role = 'student' ORDER BY name COLLATE NOCASE")->fetchAll();
    if ($onlyIds !== null) {
        $keep = array_flip(array_map('intval', $onlyIds));
        $rows = array_values(array_filter($rows, fn($u) => isset($keep[(int) $u['id']])));
    }
    foreach ($rows as $u) {
        $uid  = (int) $u['id'];
        $mine = $prog[$uid] ?? [];
        $done = 0; $last = '';
        foreach ($sections as $sid => $_) {
            if (isset($mine[$sid])) { $done++; if ($mine[$sid]['at'] > $last) $last = $mine[$sid]['at']; }
        }
        $scores = [];
        foreach ($assessIds as $aid) { if (isset($mine[$aid]) && $mine[$aid]['score'] !== null) $scores[] = $mine[$aid]['score']; }
        $pre  = ($preId  !== null && isset($mine[$preId]))  ? $mine[$preId]['score']  : null;
        $post = ($postId !== null && isset($mine[$postId])) ? $mine[$postId]['score'] : null;
        $students[] = [
            'id' => $uid, 'name' => (string) $u['name'], 'email' => (string) $u['email'], 'status' => (string) $u['status'],
            'done' => $done, 'total' => count($sections), 'pct' => count($sections) ? (int) round($done / count($sections) * 100) : 0,
            'pre' => $pre, 'post' => $post, 'growth' => ($pre !== null && $post !== null) ? $post - $pre : null,
            'assess_avg' => $scores ? (int) round(array_sum($scores) / count($scores)) : null,
            'last' => $last !== '' ? $last : (string) ($u['last_login_at'] ?? ''),
            'progress' => $mine, 'practice' => $prac[$uid] ?? [],
            'skipped' => (int) ($struggle[$uid]['skipped'] ?? 0), 'fails' => (int) ($struggle[$uid]['fails'] ?? 0),
            'misses' => (int) ($struggle[$uid]['misses'] ?? 0), 'seconds' => (int) ($struggle[$uid]['seconds'] ?? 0),
        ];
    }

    // per-section completion + average score
    $secStats = [];
    $n = count($students);
    foreach ($sections as $sid => $s) {
        $c = 0; $sum = 0; $sc = 0;
        foreach ($students as $st) {
            if (isset($st['progress'][$sid])) {
                $c++;
                if ($st['progress'][$sid]['score'] !== null) { $sum += $st['progress'][$sid]['score']; $sc++; }
            }
        }
        $secStats[$sid] = ['done' => $c, 'pct' => $n ? (int) round($c / $n * 100) : 0, 'avg' => $sc ? (int) round($sum / $sc) : null];
    }

    $avg = function (string $k) use ($students): ?int {
        $v = array_values(array_filter(array_column($students, $k), fn($x) => $x !== null));
        return $v ? (int) round(array_sum($v) / count($v)) : null;
    };
    return [
        'sections' => $sections, 'units' => $units, 'practiceTotals' => $totals, 'students' => $students, 'secStats' => $secStats,
        'preId' => $preId, 'postId' => $postId, 'assessIds' => $assessIds,
        'summary' => [
            'students'   => $n,
            'avg_pct'    => $avg('pct') ?? 0,
            'avg_pre'    => $avg('pre'), 'avg_post' => $avg('post'), 'avg_growth' => $avg('growth'),
            'finished'   => count(array_filter($students, fn($s) => $s['total'] > 0 && $s['done'] >= $s['total'])),
            'not_started' => count(array_filter($students, fn($s) => $s['done'] === 0)),
        ],
    ];
}

/** "2 days ago" style text for a stored timestamp. */
function agoLabel(string $ts): string
{
    if ($ts === '') return 'never';
    $t = strtotime($ts . ' UTC');
    if ($t === false) return $ts;
    $d = time() - $t;
    if ($d < 90)     return 'just now';
    if ($d < 3600)   return (int) round($d / 60) . ' min ago';
    if ($d < 86400)  return (int) round($d / 3600) . ' h ago';
    return (int) round($d / 86400) . ' d ago';
}

/** Colour class for a score against the pass mark. */
function scoreClass(?int $score): string
{
    if ($score === null) return 'sc-none';
    $pass = (int) setting('pass_score');
    return $score >= $pass ? 'sc-ok' : ($score >= $pass - 20 ? 'sc-mid' : 'sc-low');
}


/** "45s" / "6 min" for a number of seconds. */
function niceDuration(int $sec): string
{
    if ($sec < 60) return $sec . 's';
    return (int) round($sec / 60) . ' min';
}

/** practice id => ['title' => ..., 'section' => ...] read from the lesson pages. */
function practiceTitleMap(): array
{
    $map = [];
    foreach (getDB()->query("SELECT id, name, html FROM sections WHERE type = 'lesson'")->fetchAll() as $row) {
        if (preg_match_all('/data-id="([^"]+)" data-title="([^"]*)"/', (string) $row['html'], $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                $round = str_ends_with($x[1], '-round');
                $map[$x[1]] = ['title' => html_entity_decode($x[2], ENT_QUOTES, 'UTF-8'), 'section' => (string) $row['name'], 'round' => $round];
            }
        }
    }
    return $map;
}

/** One student's effort per practice, hardest first within each lesson. */
function practiceStatsFor(int $userId): array
{
    $st = getDB()->prepare("SELECT s.*, (SELECT 1 FROM practice_progress pp WHERE pp.user_id = s.user_id AND pp.section_id = s.section_id AND pp.challenge_id = s.challenge_id) AS solved
                            FROM practice_stats s JOIN sections sec ON sec.id = s.section_id WHERE s.user_id = ? ORDER BY sec.position, s.challenge_id");
    $st->execute([$userId]);
    return $st->fetchAll();
}


/** Saves one student's effort on one practice. Counters only ever go up; "skipped" follows the latest report. */
function recordPracticeStats(int $userId, int $sectionId, string $challengeId, array $v): void
{
    $n = fn(string $k, int $max): int => max(0, min($max, (int) ($v[$k] ?? 0)));
    getDB()->prepare("INSERT INTO practice_stats (user_id, section_id, challenge_id, runs, fails, flips, misses, seconds, skipped, updated_at)
                      VALUES (?,?,?,?,?,?,?,?,?, CURRENT_TIMESTAMP)
                      ON CONFLICT(user_id, section_id, challenge_id) DO UPDATE SET
                        runs = MAX(runs, excluded.runs), fails = MAX(fails, excluded.fails), flips = MAX(flips, excluded.flips),
                        misses = MAX(misses, excluded.misses), seconds = MAX(seconds, excluded.seconds),
                        skipped = excluded.skipped, updated_at = CURRENT_TIMESTAMP")
           ->execute([$userId, $sectionId, $challengeId, $n('runs', 100000), $n('fails', 100000), $n('flips', 100000), $n('misses', 100000), $n('seconds', 86400), $n('skipped', 1)]);
}
