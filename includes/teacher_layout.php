<?php
declare(strict_types=1);
/**
 * Shared frame for the Teacher panel (/teacher/*). Teachers own the academic side:
 * lessons and quizzes, the gradebook, and the classroom board. Admins cannot open these pages.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/lesson_builder.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/classes.php';

requireTeacher();

function teacherStart(string $title, string $active): void
{
    global $currentUser, $pageTitle;
    $currentUser = currentUser();
    $pageTitle   = $title . ' · Teacher';
    include __DIR__ . '/header.php';
    $items = [
        'index'         => ['/teacher/index.php',         'home',  'Overview'],
        'classes'       => ['/teacher/classes.php',       'users', 'Classes'],
        'sections'      => ['/teacher/sections.php',      'book',  'Lessons & content'],
        'gradebook'     => ['/teacher/gradebook.php',     'chart', 'Gradebook & analytics'],
        'announcements' => ['/teacher/announcements.php', 'scroll', 'Announcements'],
    ];
    ?>
<div class="adm">
  <nav class="adm-nav" aria-label="Teacher">
    <div class="t">Teacher panel</div>
    <?php foreach ($items as $k => [$href, $ic, $label]): ?>
      <a href="<?= u($href) ?>" class="<?= $k === $active ? 'on' : '' ?>"><?= icon($ic, 16) ?> <?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="t" style="margin-top:14px">Shortcuts</div>
    <a href="<?= u('/index.php') ?>"><?= icon('code', 16) ?> View as learner</a>
    <a href="<?= u('/account.php') ?>"><?= icon('key', 16) ?> My security</a>
  </nav>
  <div class="adm-main">
    <?php alertBox(flashGet('success'), 'ok'); alertBox(flashGet('error'), 'err'); ?>
<?php
}

function teacherEnd(): void
{
    ?>
  </div>
</div>
<script src="<?= u('/assets/auth.js') ?>"></script>
</body>
</html>
<?php
}

/** Turns "2026-11-07 10:12:00" into "Nov 7, 2026" (or a dash). */
function niceDate(?string $ts): string
{
    if (!$ts) return '–';
    $t = strtotime($ts);
    return $t === false ? '–' : date('M j, Y', $t);
}
