<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

/** Lesson / quiz content, loaded from the database (edited by admins). */
function getSectionContent(int $id): array
{
    $db  = getDB();
    $st  = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return ['type' => 'lesson', 'title' => 'Section ' . $id, 'html' => '<p>Content coming soon.</p>'];

    $out = ['type' => $row['type'], 'title' => $row['title'], 'direction' => $row['direction'], 'html' => $row['html'], 'questions' => []];
    if ($row['type'] !== 'lesson') {
        $q = $db->prepare("SELECT * FROM questions WHERE section_id = ? ORDER BY position, id");
        $q->execute([$id]);
        foreach ($q->fetchAll() as $r) {
            $out['questions'][] = [
                'part' => $r['part'], 'text' => $r['text'],
                'options' => json_decode($r['options'], true) ?: [],
                'correct' => (int) $r['correct'], 'explanation' => $r['explanation'],
            ];
        }
    }
    return $out;
}
