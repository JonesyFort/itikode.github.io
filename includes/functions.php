<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/security.php';

function getUserProgress(int $userId): array
{
    $stmt = getDB()->prepare("SELECT section_id, score FROM user_progress WHERE user_id = ?");
    $stmt->execute([$userId]);
    $progress = [];
    foreach ($stmt->fetchAll() as $row) {
        $progress[(int) $row['section_id']] = $row['score'] !== null ? (int) $row['score'] : true;
    }
    return $progress;
}

function markSectionComplete(int $userId, int $sectionId, ?int $score = null): void
{
    getDB()->prepare("
        INSERT INTO user_progress (user_id, section_id, score) VALUES (?, ?, ?)
        ON CONFLICT(user_id, section_id) DO UPDATE SET score = excluded.score, completed_at = CURRENT_TIMESTAMP
    ")->execute([$userId, $sectionId, $score]);
}

/**
 * Sections in module order, keyed by id.
 * Students only see published sections; pass $includeDrafts for the admin console.
 */
function getAllSections(bool $includeDrafts = false): array
{
    static $cache = [];
    $key = $includeDrafts ? 'all' : 'pub';
    if (!isset($cache[$key])) {
        $sql = "SELECT id, position, name, title, type, duration, published FROM sections"
             . ($includeDrafts ? '' : ' WHERE published = 1') . " ORDER BY position, id";
        $cache[$key] = [];
        foreach (getDB()->query($sql)->fetchAll() as $r) {
            $cache[$key][(int) $r['id']] = $r;
        }
    }
    return $cache[$key];
}

function sectionCount(): int { return count(getAllSections()); }

/** @return array{0:?int,1:?int} previous and next published section ids */
function siblingSections(int $id): array
{
    $ids = array_keys(getAllSections());
    $i = array_search($id, $ids, true);
    if ($i === false) return [null, $ids[0] ?? null];
    return [$ids[$i - 1] ?? null, $ids[$i + 1] ?? null];
}

function completedCount(array $progress): int
{
    return count(array_intersect_key($progress, getAllSections()));
}

function canAccess(int $sectionId, array $progress, string $role): bool
{
    if ($role === 'teacher' || $role === 'admin') return true;
    $ids = array_keys(getAllSections());
    $i = array_search($sectionId, $ids, true);
    if ($i === false) return false;
    return $i === 0 || isset($progress[$ids[$i - 1]]);
}

function nextSection(array $progress, string $role): ?int
{
    if ($role !== 'student') return null;
    foreach (array_keys(getAllSections()) as $id) {
        if (!isset($progress[$id])) return $id;
    }
    return null;
}

function getSectionTypePill(string $type): array
{
    return match ($type) {
        'quiz'       => ['label' => 'Quiz',       'color' => '#9A6735', 'bg' => 'rgba(154,103,53,0.12)'],
        'assessment' => ['label' => 'Assessment', 'color' => '#7B5EA7', 'bg' => 'rgba(123,94,167,0.12)'],
        'evaluation' => ['label' => 'Evaluation', 'color' => '#7B5EA7', 'bg' => 'rgba(123,94,167,0.12)'],
        default      => ['label' => 'Lesson',     'color' => '#55883B', 'bg' => 'rgba(85,136,59,0.12)'],
    };
}

/* ── Classroom board ─────────────────────────────────────────────────────── */

const ANNOUNCEMENT_KINDS = [
    'update'   => ['label' => 'Update',   'color' => '#55883B', 'bg' => 'rgba(85,136,59,0.14)',   'emoji' => '📣'],
    'tip'      => ['label' => 'Coding tip', 'color' => '#7B5EA7', 'bg' => 'rgba(123,94,167,0.14)', 'emoji' => '💡'],
    'deadline' => ['label' => 'Deadline', 'color' => '#C0592B', 'bg' => 'rgba(192,89,43,0.14)',   'emoji' => '⏰'],
];

/** Published announcements for learner dashboards: pinned first, then newest. Past deadlines stay for 3 days. */
function getAnnouncements(int $limit = 6, ?int $forStudentId = null): array
{
    // posts for "everyone" (no class) plus posts for the student's own classes
    $audience = $forStudentId !== null
        ? " AND (a.class_id IS NULL OR a.class_id = 0 OR a.class_id IN (SELECT class_id FROM class_members WHERE user_id = ?))" : '';
    $st = getDB()->prepare("
        SELECT a.*, u.name AS author, cl.name AS class_name FROM announcements a
        LEFT JOIN users u ON u.id = a.author_id
        LEFT JOIN classes cl ON cl.id = a.class_id
        WHERE a.published = 1 AND (a.due_date IS NULL OR a.due_date = '' OR a.due_date >= date('now','-3 day'))" . $audience . "
        ORDER BY a.pinned DESC, a.created_at DESC, a.id DESC LIMIT ?");
    $i = 1;
    if ($forStudentId !== null) $st->bindValue($i++, $forStudentId, PDO::PARAM_INT);
    $st->bindValue($i, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** "Due today", "Due in 3 days", "Was due 2 days ago" for a YYYY-MM-DD date; null if no valid date. */
function dueLabel(?string $date): ?array
{
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    $days = (int) floor((strtotime($date . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
    $nice = date('M j', strtotime($date));
    if ($days < 0)   return ['text' => "Was due $nice", 'late' => true];
    if ($days === 0) return ['text' => "Due today ($nice)", 'late' => false];
    if ($days === 1) return ['text' => "Due tomorrow ($nice)", 'late' => false];
    return ['text' => "Due $nice · $days days left", 'late' => false];
}

/** "Nov 7" for a stored timestamp. */
function niceDateShort(?string $ts): string
{
    $t = $ts ? strtotime($ts) : false;
    return $t === false ? '' : date('M j', $t);
}
