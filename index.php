<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/shell.php';
require_once __DIR__ . '/includes/classes.php';

requireLogin('/login.php');
if (isAdmin() && !empty($_GET['console'])) redirect(u('/admin/index.php'));

$currentUser     = currentUser();
$progress        = getUserProgress((int) $currentUser['id']);
$sections        = getAllSections();
$role            = $currentUser['role'];
$next            = nextSection($progress, $role);
$total           = max(1, count($sections));
$totalDone       = completedCount($progress);
$completedPct    = (int) round($totalDone / $total * 100);
$firstId         = (int) (array_key_first($sections) ?? 1);
$flashErr        = flashGet('error');
$board           = getAnnouncements(6, $role === 'student' ? (int) $currentUser['id'] : null);
$myClasses       = $role === 'student' ? studentClasses((int) $currentUser['id']) : [];
$flashOk         = flashGet('success');
$activeSectionId = null;

// Where the big button on the banner goes.
if ($role === 'student' && $next !== null) {
    $goId    = $next;
    $goLabel = $totalDone === 0 ? 'Start Module' : 'Continue';
} else {
    $goId    = $firstId;
    $goLabel = $role === 'student' ? 'Review Module' : 'Browse All Sections';
}

shellStart('Dashboard', null);
?>
<div class="lx-page" style="max-width:none">

  <?php if ($flashErr): ?><div class="alert alert-err"><?= e($flashErr) ?></div><?php endif; ?>
  <?php if ($flashOk): ?><div class="alert alert-ok"><?= e($flashOk) ?></div><?php endif; ?>

  <section class="lx-banner">
    <div class="k"><?= $totalDone === $total && $role === 'student' ? 'Module complete' : 'Welcome back, ' . e(explode(' ', trim((string) $currentUser['name']))[0]) . '!' ?></div>
    <h1><?= e(setting('site_name')) ?></h1>
    <p>Master Object-Oriented Programming in Python through interactive lessons and assessments.</p>
    <div class="go">
      <a class="lx-go" href="<?= u('/section.php?id=' . $goId) ?>"><?= icon('play', 13) ?> <span><?= e($goLabel) ?></span></a>
    </div>
  </section>

  <?php if ($role === 'student'): ?>
  <section class="lx-classcard" aria-label="Your classes">
    <?php if ($myClasses): ?>
      <div class="lx-cls-list">
        <b>🎓 My class<?= count($myClasses) === 1 ? '' : 'es' ?></b>
        <?php foreach ($myClasses as $c): ?><span class="lx-cls-chip"><?= e($c['name']) ?><?php if (!empty($c['teacher'])): ?> <small>· <?= e($c['teacher']) ?></small><?php endif; ?></span><?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="lx-cls-list"><b>🎓 Join your class</b><span class="hint" style="margin:0">Ask your teacher for the 6-character class code.</span></div>
    <?php endif; ?>
    <form method="POST" action="<?= u('/join.php') ?>" class="lx-join"><?= csrfField() ?>
      <input class="input" name="code" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Class code" aria-label="Class code" required>
      <button class="lx-btn" type="submit"><?= $myClasses ? 'Join another class' : 'Join class' ?></button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($board): ?>
  <section class="lx-board" aria-label="Classroom board">
    <div class="lx-pcard-h"><b>📌 Classroom board</b><span class="lx-badge brown"><?= count($board) ?> post<?= count($board) === 1 ? '' : 's' ?></span></div>
    <div class="lx-board-list">
      <?php foreach ($board as $a):
        $k   = ANNOUNCEMENT_KINDS[$a['kind']] ?? ANNOUNCEMENT_KINDS['update'];
        $due = dueLabel($a['due_date'] ?? null); ?>
      <article class="lx-post<?= $a['pinned'] ? ' pinned' : '' ?>" style="--k:<?= $k['color'] ?>">
        <div class="lx-post-top">
          <span class="lx-post-kind" style="background:<?= $k['bg'] ?>;color:<?= $k['color'] ?>"><?= $k['emoji'] ?> <?= e($k['label']) ?></span>
          <?php if ($a['pinned']): ?><span class="lx-post-pin">Pinned</span><?php endif; ?>
          <?php if ($due): ?><span class="lx-post-due<?= $due['late'] ? ' late' : '' ?>"><?= e($due['text']) ?></span><?php endif; ?>
        </div>
        <b><?= e($a['title']) ?></b>
        <?php if ($a['body'] !== ''): ?><p><?= e($a['body']) ?></p><?php endif; ?>
        <small><?= e(niceDateShort($a['created_at'])) ?> · <?= e((string) ($a['author'] ?? 'Your teacher')) ?><?= !empty($a['class_name']) ? ' · ' . e((string) $a['class_name']) : '' ?></small>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="lx-pcard" aria-label="Your progress">
    <div class="lx-pcard-h">
      <b><?= $role === 'student' ? 'Your Progress' : 'Your Progress (staff view: all sections open)' ?></b>
      <span class="lx-badge"><?= $totalDone ?> / <?= $total ?> completed</span>
    </div>
    <div class="lx-meter" role="progressbar" aria-valuenow="<?= $completedPct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $completedPct ?>%"></i></div>
    <span class="lx-badge"><?= $completedPct ?>% done</span>
  </section>

  <div class="lx-secgrid">
    <?php $n = 0; foreach ($sections as $id => $sec):
      $n++;
      $locked    = !canAccess($id, $progress, $role);
      $completed = isset($progress[$id]);
      $isNext    = !$completed && !$locked && $id === $next;
      $pill      = getSectionTypePill($sec['type']);
      $score     = is_int($progress[$id] ?? null) ? $progress[$id] : null;
      $cls       = 'lx-sec' . ($completed ? ' done' : '') . ($isNext ? ' next' : '') . ($locked ? ' lock' : '');
    ?>
    <<?= $locked ? 'div' : 'a' ?> class="<?= $cls ?>"<?= $locked ? ' aria-disabled="true"' : ' href="' . u('/section.php?id=' . $id) . '"' ?>>
      <span class="lx-sec-n"><?= $completed ? icon('check', 16) : $n ?></span>
      <span class="lx-sec-t">
        <b><?= e($sec['name']) ?></b>
        <em style="color:<?= $pill['color'] ?>;background:<?= $pill['bg'] ?>"><?= e($pill['label']) ?></em>
        <?php if ($completed && $score !== null): ?><em style="color:#9A6735;background:rgba(154,103,53,.12)">Score <?= $score ?>%</em><?php endif; ?>
      </span>
      <?php if ($locked): ?><span class="lk"><?= icon('lock', 13) ?></span><?php endif; ?>
    </<?= $locked ? 'div' : 'a' ?>>
    <?php endforeach; ?>
  </div>

</div>
<?php shellEnd(); ?>
