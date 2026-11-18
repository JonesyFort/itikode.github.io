<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';

$db   = getDB();
$me   = sessionUser();
$tid  = (int) $me['id'];
$myClasses = teacherClasses($tid);
$edit = ['id' => 0, 'kind' => 'update', 'title' => '', 'body' => '', 'due_date' => '', 'pinned' => 0, 'published' => 1, 'class_id' => 0];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $do = (string) ($_POST['do'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($do === 'save') {
        $edit = [
            'id'        => $id,
            'kind'      => array_key_exists((string) ($_POST['kind'] ?? ''), ANNOUNCEMENT_KINDS) ? (string) $_POST['kind'] : 'update',
            'title'     => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120),
            'body'      => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''))), 0, 1000),
            'due_date'  => trim((string) ($_POST['due_date'] ?? '')),
            'pinned'    => !empty($_POST['pinned']) ? 1 : 0,
            'published' => !empty($_POST['published']) ? 1 : 0,
            'class_id'  => (int) ($_POST['class_id'] ?? 0),
        ];
        $d = $edit['due_date'];
        if ($edit['title'] === '') $errors[] = 'Give the announcement a title.';
        if ($d !== '') {
            $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
            if (!$ok) $errors[] = 'That due date is not valid.';
        }
        if ($edit['kind'] === 'deadline' && $d === '') $errors[] = 'Pick a due date for a deadline.';
        if ($edit['class_id'] > 0 && !classOwnedBy($edit['class_id'], $tid)) $errors[] = 'Pick one of your own classes.';

        if (!$errors) {
            if ($id) {
                $db->prepare("UPDATE announcements SET kind=?, title=?, body=?, due_date=?, pinned=?, published=?, class_id=? WHERE id=? AND author_id=?")
                   ->execute([$edit['kind'], $edit['title'], $edit['body'], $d === '' ? null : $d, $edit['pinned'], $edit['published'], $edit['class_id'] > 0 ? $edit['class_id'] : null, $id, $tid]);
                auditLog('announcement.edited', $edit['title']);
            } else {
                $db->prepare("INSERT INTO announcements (author_id, kind, title, body, due_date, pinned, published, class_id) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$tid, $edit['kind'], $edit['title'], $edit['body'], $d === '' ? null : $d, $edit['pinned'], $edit['published'], $edit['class_id'] > 0 ? $edit['class_id'] : null]);
                auditLog('announcement.created', $edit['title']);
            }
            flashSet('success', $id ? 'Announcement updated.' : 'Posted to the classroom board.');
            redirect(u('/teacher/announcements.php'));
        }
    } else {
        $row = $db->prepare("SELECT * FROM announcements WHERE id = ? AND author_id = ?");
        $row->execute([$id, $tid]);
        $a = $row->fetch();
        if ($a) {
            if ($do === 'delete') {
                $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
                auditLog('announcement.deleted', $a['title']);
                flashSet('success', 'Announcement deleted.');
            } elseif ($do === 'pin') {
                $db->prepare("UPDATE announcements SET pinned = 1 - pinned WHERE id = ?")->execute([$id]);
                flashSet('success', $a['pinned'] ? 'Unpinned.' : 'Pinned to the top of the board.');
            } elseif ($do === 'toggle') {
                $db->prepare("UPDATE announcements SET published = 1 - published WHERE id = ?")->execute([$id]);
                flashSet('success', $a['published'] ? 'Hidden from students.' : 'Visible to students.');
            }
        }
        redirect(u('/teacher/announcements.php'));
    }
} elseif (isset($_GET['edit'])) {
    $row = $db->prepare("SELECT * FROM announcements WHERE id = ? AND author_id = ?");
    $row->execute([(int) $_GET['edit'], $tid]);
    $found = $row->fetch();
    if ($found) { $edit = $found; $edit['due_date'] = (string) ($found['due_date'] ?? ''); $edit['class_id'] = (int) ($found['class_id'] ?? 0); }
}

$listSt = $db->prepare("SELECT a.*, u.name AS author, cl.name AS class_name FROM announcements a
    LEFT JOIN users u ON u.id = a.author_id LEFT JOIN classes cl ON cl.id = a.class_id
    WHERE a.author_id = ? ORDER BY a.pinned DESC, a.created_at DESC, a.id DESC");
$listSt->execute([$tid]);
$list = $listSt->fetchAll();

teacherStart('Announcements', 'announcements');
pageHead('Classroom announcements', 'Post updates, coding tips and deadlines to one class or to everyone. Published posts appear on student dashboards.');
foreach ($errors as $er) alertBox($er, 'err');
?>
<div class="grid2" style="align-items:start">
  <form method="POST" action="<?= u('/teacher/announcements.php') ?>" class="card"><?= csrfField() ?>
    <input type="hidden" name="do" value="save"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <p class="card-h"><?= $edit['id'] ? 'Edit announcement' : 'New announcement' ?></p>
    <div class="field"><label class="label" for="a-kind">Type</label>
      <select class="select" id="a-kind" name="kind"><?php foreach (ANNOUNCEMENT_KINDS as $k => $kd): ?><option value="<?= $k ?>" <?= $edit['kind'] === $k ? 'selected' : '' ?>><?= $kd['emoji'] ?> <?= e($kd['label']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="label" for="a-class">Send to</label>
      <select class="select" id="a-class" name="class_id"><option value="0" <?= (int) ($edit['class_id'] ?? 0) === 0 ? 'selected' : '' ?>>Everyone (all students)</option>
        <?php foreach ($myClasses as $c): if ($c['archived'] && (int) ($edit['class_id'] ?? 0) !== (int) $c['id']) continue; ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($edit['class_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> only</option><?php endforeach; ?></select></div>
    <div class="field"><label class="label" for="a-title">Title</label><input class="input" id="a-title" name="title" maxlength="120" required value="<?= e($edit['title']) ?>" placeholder="Quiz on Inheritance this Friday"></div>
    <div class="field"><label class="label" for="a-body">Message <span class="sub">optional</span></label><textarea class="textarea" id="a-body" name="body" maxlength="1000" placeholder="Review Topic 2 and finish the code practice before the quiz."><?= e($edit['body']) ?></textarea></div>
    <div class="field" id="due-field"><label class="label" for="a-due">Due date <span class="sub">required for deadlines</span></label><input class="input" type="date" id="a-due" name="due_date" value="<?= e((string) $edit['due_date']) ?>"></div>
    <label class="check" style="margin-bottom:8px"><input type="checkbox" name="pinned" value="1" <?= !empty($edit['pinned']) ? 'checked' : '' ?>> Pin to the top of the board</label>
    <label class="check" style="margin-bottom:14px"><input type="checkbox" name="published" value="1" <?= !empty($edit['published']) ? 'checked' : '' ?>> Visible to students</label>
    <div style="display:flex;gap:8px"><button class="btn btn-primary" type="submit"><?= $edit['id'] ? 'Save changes' : 'Post announcement' ?></button>
      <?php if ($edit['id']): ?><a class="btn" href="<?= u('/teacher/announcements.php') ?>">Cancel</a><?php endif; ?></div>
  </form>

  <div>
    <?php if (!$list): ?><div class="card"><p class="hint" style="margin:0">Nothing posted yet. Your first announcement will show up on student dashboards.</p></div><?php endif; ?>
    <?php foreach ($list as $a): $k = ANNOUNCEMENT_KINDS[$a['kind']] ?? ANNOUNCEMENT_KINDS['update']; $due = dueLabel($a['due_date'] ?? null); ?>
      <div class="card" style="margin-bottom:12px;<?= $a['published'] ? '' : 'opacity:.65' ?>">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-bottom:6px">
          <span class="pill" style="background:<?= $k['bg'] ?>;color:<?= $k['color'] ?>"><?= $k['emoji'] ?> <?= e($k['label']) ?></span>
          <?php if ($a['pinned']): ?><span class="pill pill-brown">Pinned</span><?php endif; ?>
          <span class="pill pill-green"><?= !empty($a['class_name']) ? e($a['class_name']) : 'Everyone' ?></span>
          <?php if (!$a['published']): ?><span class="pill pill-gray">Hidden</span><?php endif; ?>
          <?php if ($due): ?><span class="pill <?= $due['late'] ? 'pill-red' : 'pill-purple' ?>"><?= e($due['text']) ?></span><?php endif; ?>
        </div>
        <b style="color:var(--deep)"><?= e($a['title']) ?></b>
        <?php if ($a['body'] !== ''): ?><p style="margin:4px 0 0;font-size:13px;white-space:pre-line"><?= e($a['body']) ?></p><?php endif; ?>
        <p class="hint" style="margin:6px 0 8px">Posted <?= e(niceDate($a['created_at'])) ?> by <?= e((string) ($a['author'] ?? 'a teacher')) ?></p>
        <form method="POST" action="<?= u('/teacher/announcements.php') ?>" class="actions" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <a class="btn btn-sm btn-lime" href="<?= u('/teacher/announcements.php?edit=' . (int) $a['id']) ?>">Edit</a>
          <button class="btn btn-sm" name="do" value="pin"><?= $a['pinned'] ? 'Unpin' : 'Pin' ?></button>
          <button class="btn btn-sm" name="do" value="toggle"><?= $a['published'] ? 'Hide' : 'Show' ?></button>
          <button class="btn btn-sm btn-danger" name="do" value="delete" onclick="return confirm('Delete this announcement?')">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  var kind = document.getElementById('a-kind'), due = document.getElementById('due-field');
  function sync() { due.style.display = kind.value === 'deadline' ? '' : ''; document.getElementById('a-due').required = kind.value === 'deadline'; }
  kind.addEventListener('change', sync); sync();
})();
</script>
<?php teacherEnd();
