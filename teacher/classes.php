<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';
require_once dirname(__DIR__) . '/includes/classes.php';

$db  = getDB();
$me  = sessionUser();
$tid = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 60));
    if ($name === '') {
        flashSet('error', 'Give the class a name.');
        redirect(u('/teacher/classes.php'));
    }
    $db->prepare("INSERT INTO classes (teacher_id, name, join_code) VALUES (?, ?, ?)")->execute([$tid, $name, generateJoinCode()]);
    $newId = (int) $db->lastInsertId();
    auditLog('class.created', $name);
    flashSet('success', 'Class created. Share the join code with your students, or add them yourself below.');
    redirect(u('/teacher/class.php?id=' . $newId));
}

$classes    = teacherClasses($tid);
$unassigned = count(unassignedStudents());

teacherStart('Classes', 'classes');
pageHead('Classes', 'Group your students into classes. The gradebook and announcements follow your classes.');
?>
<?php if ($unassigned > 0): ?>
  <div class="alert alert-warn"><?= $unassigned ?> student account<?= $unassigned === 1 ? ' is' : 's are' ?> not in any class yet. Open a class and add them from the "Not in a class yet" list.</div>
<?php endif; ?>

<div class="grid2" style="align-items:start">
  <form method="POST" action="<?= u('/teacher/classes.php') ?>" class="card"><?= csrfField() ?>
    <p class="card-h">New class</p>
    <div class="field"><label class="label" for="c-name">Class name</label>
      <input class="input" id="c-name" name="name" maxlength="60" required placeholder="BSIT 2A · Programming 2"></div>
    <button class="btn btn-primary" type="submit">Create class</button>
    <p class="hint" style="margin-top:10px">You get a 6-character join code to share. Students enter it on their dashboard or when creating an account.</p>
  </form>

  <div>
    <?php if (!$classes): ?>
      <div class="card"><p style="margin:0;font-size:13.5px">You have no classes yet. Create one to start. Your gradebook stays empty until students are in one of your classes.</p></div>
    <?php endif; ?>
    <?php foreach ($classes as $c): ?>
      <a class="card" href="<?= u('/teacher/class.php?id=' . (int) $c['id']) ?>" style="display:block;margin-bottom:12px;text-decoration:none;color:inherit;<?= $c['archived'] ? 'opacity:.65' : '' ?>">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap">
          <div>
            <b style="color:var(--deep);font-size:15px"><?= e($c['name']) ?></b>
            <div class="hint" style="margin:3px 0 0"><?= (int) $c['members'] ?> student<?= (int) $c['members'] === 1 ? '' : 's' ?>
              <?php if ($c['archived']): ?> · <span class="pill pill-gray">Archived</span><?php elseif (!$c['joining_open']): ?> · <span class="pill pill-brown">Joining closed</span><?php endif; ?></div>
          </div>
          <span style="font:800 18px 'JetBrains Mono',ui-monospace,monospace;letter-spacing:.14em;background:var(--cream);border:2px solid var(--lime);border-radius:10px;padding:4px 10px;color:var(--deep)"><?= e($c['join_code']) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php teacherEnd();
