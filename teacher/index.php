<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';

$tid  = (int) sessionUser()['id'];

// install the newest built-in lessons (replaces the old Motivation, Introduction and Topic 1-4 pages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'refresh_lessons') {
    verifyCsrf();
    $count = refreshBuiltInLessons();
    auditLog('lessons.refreshed', $count . ' built-in lessons updated');
    flashSet('success', 'Built-in lessons updated (' . $count . ' of 6). Students see the new version straight away.');
    redirect(u('/teacher/index.php'));
}
[$classId, $classRow] = currentClassFilter($tid);
$myClasses = teacherClasses($tid);
$gb   = gradebookData(teacherScopeIds($tid, $classId));
$sum  = $gb['summary'];
$pass = (int) setting('pass_score');

// students who may need a nudge: low progress, a low assessment average, or quiet for a week
$attention = [];
foreach ($gb['students'] as $s) {
    if ($s['status'] !== 'active') continue;
    $why = [];
    if ($s['done'] === 0) $why[] = 'Has not started';
    elseif ($s['pct'] < 40) $why[] = 'Only ' . $s['pct'] . '% complete';
    if ($s['assess_avg'] !== null && $s['assess_avg'] < $pass) $why[] = 'Assessment average ' . $s['assess_avg'] . '%';
    if ($s['done'] > 0 && $s['last'] !== '' && strtotime($s['last'] . ' UTC') < time() - 7 * 86400) $why[] = 'No activity for 7+ days';
    if ($s['skipped'] >= 1) $why[] = 'Skipped ' . $s['skipped'] . ' practice' . ($s['skipped'] === 1 ? '' : 's') . ' (needs to come back)';
    if ($why) $attention[] = ['s' => $s, 'why' => $why];
}
$attention = array_slice($attention, 0, 6);
$board = getAnnouncements(3);

ob_start(); classPicker($tid, $classId, '/teacher/index.php'); $picker = (string) ob_get_clean();
teacherStart('Overview', 'index');
pageHead('Teacher overview', 'Your classroom at a glance: progress, scores and what needs attention.',
    '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">' . $picker . '<a class="btn btn-primary" href="' . u('/teacher/section_edit.php') . '">+ New lesson or quiz</a></div>');
?>
<?php if (lessonsOutdated()): ?>
  <div class="alert alert-warn" style="display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap">
    <span><b>A newer version of the built-in lessons is available</b> (Motivation, Introduction and Topics 1 to 4). Updating replaces any edits you made to those six lessons. Students' progress is kept.</span>
    <form method="POST" action="<?= u('/teacher/index.php') ?>" style="margin:0"><?= csrfField() ?><input type="hidden" name="do" value="refresh_lessons">
      <button class="btn btn-primary" type="submit" onclick="return confirm('Update the six built-in lessons? Any edits you made to them will be replaced.')">Update built-in lessons</button></form>
  </div>
<?php endif; ?>
<?php if (!$myClasses): ?>
  <div class="alert alert-warn"><b>Start here:</b> create a class so your students show up in this overview and the gradebook. <a href="<?= u('/teacher/classes.php') ?>" style="font-weight:800">Create your first class</a></div>
<?php endif; ?>
<div class="stats">
  <div class="stat"><b><?= (int) $sum['students'] ?></b><span>Students</span></div>
  <div class="stat"><b><?= (int) $sum['avg_pct'] ?>%</b><span>Average completion</span></div>
  <div class="stat"><b><?= $sum['avg_pre'] === null ? '–' : (int) $sum['avg_pre'] . '%' ?></b><span>Avg pre-test</span></div>
  <div class="stat"><b><?= $sum['avg_post'] === null ? '–' : (int) $sum['avg_post'] . '%' ?></b><span>Avg post-test</span></div>
  <div class="stat"><b><?= (int) $sum['finished'] ?></b><span>Finished the module</span></div>
  <div class="stat"><b><?= (int) $sum['not_started'] ?></b><span>Not started</span></div>
</div>

<div class="grid2" style="align-items:start">
  <div class="card">
    <p class="card-h">Needs attention</p>
    <?php if (!$attention): ?>
      <p class="hint" style="margin:0">Nobody is flagged right now. Nice.</p>
    <?php else: foreach ($attention as $a): $s = $a['s']; ?>
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--cream)">
        <div style="min-width:0"><a href="<?= u('/teacher/student.php?id=' . $s['id']) ?>"><b><?= e($s['name']) ?></b></a>
          <div class="hint" style="margin:1px 0 0"><?= e(implode(' · ', $a['why'])) ?></div></div>
        <span class="pill pill-gray"><?= (int) $s['pct'] ?>%</span>
      </div>
    <?php endforeach; endif; ?>
    <p style="margin:12px 0 0"><a class="btn btn-sm" href="<?= u('/teacher/gradebook.php') ?>">Open the gradebook</a></p>
  </div>

  <div class="card">
    <p class="card-h">Classroom board</p>
    <?php if (!$board): ?>
      <p class="hint" style="margin:0">Nothing posted yet. Share an update, a coding tip or a deadline with your students.</p>
    <?php else: foreach ($board as $a): $k = ANNOUNCEMENT_KINDS[$a['kind']] ?? ANNOUNCEMENT_KINDS['update']; ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--cream)">
        <span class="pill" style="background:<?= $k['bg'] ?>;color:<?= $k['color'] ?>"><?= e($k['label']) ?></span>
        <b style="margin-left:6px"><?= e($a['title']) ?></b>
      </div>
    <?php endforeach; endif; ?>
    <p style="margin:12px 0 0"><a class="btn btn-sm btn-lime" href="<?= u('/teacher/announcements.php') ?>">Post an announcement</a></p>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <p class="card-h">Completion by section</p>
  <?php $i = 0; foreach ($gb['sections'] as $sid => $s): $i++; $st = $gb['secStats'][$sid]; ?>
    <div class="mrow">
      <span class="mn"><?= $i ?></span>
      <span class="mt"><?= e($s['name']) ?></span>
      <span class="mbar"><i style="width:<?= (int) $st['pct'] ?>%"></i></span>
      <span class="mv"><?= (int) $st['done'] ?>/<?= (int) $sum['students'] ?></span>
      <span class="mv"><?php if ($st['avg'] !== null): ?><span class="chip <?= scoreClass($st['avg']) ?>">avg <?= (int) $st['avg'] ?>%</span><?php endif; ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php teacherEnd();
