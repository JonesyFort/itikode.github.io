<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';
require_once dirname(__DIR__) . '/includes/classes.php';

$db  = getDB();
$me  = sessionUser();
$tid = (int) $me['id'];
$cid = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$class = classOwnedBy($cid, $tid);
if (!$class) {
    flashSet('error', 'That class was not found.');
    redirect(u('/teacher/classes.php'));
}
$self = '/teacher/class.php?id=' . $cid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'rename') {
        $name = trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 60));
        if ($name === '') flashSet('error', 'Give the class a name.');
        else { $db->prepare("UPDATE classes SET name = ? WHERE id = ?")->execute([$name, $cid]); flashSet('success', 'Class renamed.'); }
    } elseif ($do === 'regen') {
        $db->prepare("UPDATE classes SET join_code = ? WHERE id = ?")->execute([generateJoinCode(), $cid]);
        auditLog('class.code_changed', $class['name']);
        flashSet('success', 'New join code created. The old code no longer works.');
    } elseif ($do === 'toggle_join') {
        $db->prepare("UPDATE classes SET joining_open = 1 - joining_open WHERE id = ?")->execute([$cid]);
        flashSet('success', $class['joining_open'] ? 'Joining is closed. Nobody new can use the code.' : 'Joining is open.');
    } elseif ($do === 'archive') {
        $db->prepare("UPDATE classes SET archived = 1 - archived WHERE id = ?")->execute([$cid]);
        flashSet('success', $class['archived'] ? 'Class restored.' : 'Class archived. It is hidden from the default gradebook view.');
    } elseif ($do === 'remove') {
        $db->prepare("DELETE FROM class_members WHERE class_id = ? AND user_id = ?")->execute([$cid, (int) ($_POST['user_id'] ?? 0)]);
        auditLog('class.member_removed', $class['name'] . ' / user ' . (int) ($_POST['user_id'] ?? 0));
        flashSet('success', 'Student removed from the class. Their account and progress are kept.');
    } elseif ($do === 'add_existing') {
        $added = 0;
        $free  = array_column(unassignedStudents(), 'id');
        foreach ((array) ($_POST['ids'] ?? []) as $rawId) {
            $uid = (int) $rawId;
            if (in_array($uid, array_map('intval', $free), true) && addToClass($cid, $uid)) $added++;
        }
        if ($added) auditLog('class.members_added', $class['name'] . ': ' . $added);
        flashSet($added ? 'success' : 'error', $added ? $added . ' student' . ($added === 1 ? '' : 's') . ' added.' : 'Pick at least one student.');
    } elseif ($do === 'import') {
        $text = (string) ($_POST['rows'] ?? '');
        $f = $_FILES['csv'] ?? null;
        if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) $f['size'] > 0 && (int) $f['size'] <= 200000) {
            $text = (string) file_get_contents((string) $f['tmp_name']);
        }
        $rows = parseStudentRows($text);
        if (!$rows) {
            flashSet('error', 'Paste some students or choose a CSV file first.');
        } else {
            if (function_exists('set_time_limit')) @set_time_limit(180);
            $results = importStudents($class, $rows);
            $made  = count(array_filter($results, fn($r) => $r['status'] === 'created'));
            $moved = count(array_filter($results, fn($r) => $r['status'] === 'added'));
            auditLog('class.import', $class['name'] . ': ' . $made . ' created, ' . $moved . ' existing added');
            $_SESSION['class_import'] = ['class' => $cid, 'results' => $results, 'cut' => count($rows) > IMPORT_MAX_ROWS];
            redirect(u($self . '&done=1'));
        }
    } elseif ($do === 'delete') {
        if (!confirmPassword((string) ($_POST['confirm_pw'] ?? ''), 'teacher.bad_confirmation')) {
            flashSet('error', 'That password was not correct, so the class was not deleted.');
        } else {
            $db->prepare("DELETE FROM classes WHERE id = ?")->execute([$cid]);
            auditLog('class.deleted', $class['name']);
            flashSet('success', 'Class deleted. The students keep their accounts and progress.');
            redirect(u('/teacher/classes.php'));
        }
    }
    redirect(u($self));
}

// results of the last import (shown once)
$import = null;
if (isset($_GET['done']) && isset($_SESSION['class_import']) && (int) $_SESSION['class_import']['class'] === $cid) {
    $import = $_SESSION['class_import'];
    unset($_SESSION['class_import']);
}

$memberIds = teacherScopeIds($tid, $cid);
$gb        = gradebookData($memberIds);
$free      = unassignedStudents();

teacherStart($class['name'], 'classes');
pageHead($class['name'], 'Join code, roster and adding students.',
    '<a class="btn" href="' . e(u('/teacher/gradebook.php?class=' . $cid)) . '">Open gradebook</a>');
?>
<?php if ($import): ?>
  <?php
    $created = array_values(array_filter($import['results'], fn($r) => $r['status'] === 'created'));
    $csv = "name,email,temporary password\r\n";
    foreach ($created as $r) { $csv .= '"' . str_replace('"', '""', $r['name']) . '","' . $r['email'] . '","' . $r['password'] . "\"\r\n"; }
  ?>
  <div class="card" id="import-result" style="border-color:var(--green);margin-bottom:16px">
    <p class="card-h">Import finished</p>
    <?php if ($created): ?>
      <div class="alert alert-warn"><b>Save these temporary passwords now.</b> They are shown only once and are not stored anywhere readable. Each student must choose a new password at first login.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <a class="btn btn-primary" download="<?= e(preg_replace('/[^A-Za-z0-9]+/', '-', $class['name']) ?: 'class') ?>-logins.csv" href="data:text/csv;charset=utf-8,<?= rawurlencode("\xEF\xBB\xBF" . $csv) ?>">Download logins (CSV)</a>
        <button class="btn" type="button" onclick="document.body.classList.add('print-creds');window.print();document.body.classList.remove('print-creds')">Print</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($import['cut'])): ?><div class="alert alert-warn">Only the first <?= IMPORT_MAX_ROWS ?> rows were imported. Import the rest in a second batch.</div><?php endif; ?>
    <div style="overflow:auto"><table class="tbl">
      <thead><tr><th>Name</th><th>Email</th><th>Result</th><th>Temporary password</th></tr></thead><tbody>
      <?php foreach ($import['results'] as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td>
          <td><span class="pill <?= $r['status'] === 'created' ? 'pill-green' : ($r['status'] === 'skipped' ? 'pill-red' : 'pill-purple') ?>"><?= e($r['status']) ?></span> <span class="hint"><?= e($r['note']) ?></span></td>
          <td style="font-family:'JetBrains Mono',ui-monospace,monospace"><?= e($r['password']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
<?php endif; ?>

<div class="grid2" style="align-items:start">
  <div class="card">
    <p class="card-h">Join code</p>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span id="code" style="font:800 34px 'JetBrains Mono',ui-monospace,monospace;letter-spacing:.2em;background:var(--cream);border:2px solid var(--lime);border-radius:14px;padding:8px 16px;color:var(--deep)"><?= e($class['join_code']) ?></span>
      <button class="btn btn-sm" type="button" id="copy">Copy</button>
    </div>
    <p class="hint" style="margin:10px 0">Students type this code on their dashboard ("Join a class") or in the registration form.
      Joining is <b><?= $class['joining_open'] ? 'open' : 'closed' ?></b>.</p>
    <form method="POST" action="<?= u('/teacher/class.php') ?>" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrfField() ?><input type="hidden" name="id" value="<?= $cid ?>">
      <button class="btn btn-sm" name="do" value="toggle_join"><?= $class['joining_open'] ? 'Close joining' : 'Open joining' ?></button>
      <button class="btn btn-sm" name="do" value="regen" onclick="return confirm('Make a new code? The old code will stop working.')">New code</button>
      <button class="btn btn-sm" name="do" value="archive"><?= $class['archived'] ? 'Restore class' : 'Archive class' ?></button>
    </form>
    <form method="POST" action="<?= u('/teacher/class.php') ?>" style="margin-top:14px"><?= csrfField() ?><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="do" value="rename">
      <label class="label" for="rn">Class name</label>
      <div style="display:flex;gap:8px"><input class="input" id="rn" name="name" maxlength="60" value="<?= e($class['name']) ?>"><button class="btn btn-sm" type="submit">Rename</button></div>
    </form>
  </div>

  <form method="POST" action="<?= u('/teacher/class.php') ?>" enctype="multipart/form-data" class="card"><?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="do" value="import">
    <p class="card-h">Add or import students</p>
    <div class="field"><label class="label" for="rows">One student per line: name, email</label>
      <textarea class="textarea" id="rows" name="rows" rows="5" spellcheck="false" placeholder="Juan Dela Cruz, juan@school.edu&#10;Maria Santos, maria@school.edu&#10;alex@school.edu"></textarea>
      <p class="hint">New emails get a student account with a temporary password. Emails that already exist are just added to this class.</p></div>
    <div class="field"><label class="label" for="csv">Or upload a CSV file <span class="sub">(columns: name, email)</span></label><input type="file" id="csv" name="csv" accept=".csv,.txt,text/csv"></div>
    <button class="btn btn-primary" type="submit">Add students</button>
    <p class="hint" style="margin-top:8px">Up to <?= IMPORT_MAX_ROWS ?> students at a time.</p>
  </form>
</div>

<?php if ($free): ?>
<form method="POST" action="<?= u('/teacher/class.php') ?>" class="card" style="margin-top:16px"><?= csrfField() ?>
  <input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="do" value="add_existing">
  <p class="card-h">Not in a class yet (<?= count($free) ?>)</p>
  <label class="check" style="margin-bottom:8px"><input type="checkbox" id="all-free"> Select all</label>
  <div style="max-height:220px;overflow:auto;border:2px solid var(--lime);border-radius:12px;padding:8px 12px;margin-bottom:12px">
    <?php foreach ($free as $s): ?>
      <label class="check" style="padding:4px 0"><input type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" class="free"> <?= e($s['name']) ?> <span class="hint" style="margin:0 0 0 6px"><?= e($s['email']) ?></span></label>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary" type="submit">Add selected to this class</button>
</form>
<?php endif; ?>

<div class="card" style="margin-top:16px">
  <p class="card-h">Roster (<?= count($gb['students']) ?>)</p>
  <?php if (!$gb['students']): ?><p class="hint" style="margin:0">No students yet. Share the join code or add students above.</p><?php else: ?>
  <div style="overflow:auto"><table class="tbl">
    <thead><tr><th>Student</th><th>Progress</th><th>Last active</th><th></th></tr></thead><tbody>
    <?php foreach ($gb['students'] as $st): ?>
      <tr>
        <td><a href="<?= u('/teacher/student.php?id=' . $st['id'] . '&class=' . $cid) ?>"><b><?= e($st['name']) ?></b></a><div class="hint" style="margin:1px 0 0"><?= e($st['email']) ?></div></td>
        <td style="min-width:130px"><div class="mbar" style="display:block"><i style="width:<?= (int) $st['pct'] ?>%"></i></div><span class="hint" style="margin:2px 0 0;display:block"><?= (int) $st['done'] ?>/<?= (int) $st['total'] ?> · <?= (int) $st['pct'] ?>%</span></td>
        <td><?= $st['last'] !== '' ? e(agoLabel($st['last'])) : '<span class="hint">never</span>' ?></td>
        <td class="actions"><form method="POST" action="<?= u('/teacher/class.php') ?>" style="margin:0"><?= csrfField() ?><input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="do" value="remove"><input type="hidden" name="user_id" value="<?= (int) $st['id'] ?>">
          <button class="btn btn-sm" onclick="return confirm('Remove <?= e(addslashes($st['name'])) ?> from this class? Their account and progress are kept.')">Remove</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<form method="POST" action="<?= u('/teacher/class.php') ?>" class="card" style="margin-top:16px"><?= csrfField() ?>
  <input type="hidden" name="id" value="<?= $cid ?>"><input type="hidden" name="do" value="delete">
  <p class="card-h" style="color:var(--danger)">Delete this class</p>
  <p class="hint" style="margin:0 0 10px">Students keep their accounts and progress; they just leave this class. Type your password to confirm.</p>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><input class="input" type="password" name="confirm_pw" autocomplete="current-password" placeholder="Your password" style="max-width:260px" required>
    <button class="btn btn-danger" type="submit">Delete class</button></div>
</form>

<script>
(function () {
  var copy = document.getElementById('copy'), code = document.getElementById('code');
  if (copy) copy.addEventListener('click', function () {
    var t = code.textContent.trim();
    function done() { copy.textContent = 'Copied'; setTimeout(function () { copy.textContent = 'Copy'; }, 1500); }
    if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, function () {});
  });
  var all = document.getElementById('all-free');
  if (all) all.addEventListener('change', function () { [].forEach.call(document.querySelectorAll('.free'), function (c) { c.checked = all.checked; }); });
})();
</script>
<?php teacherEnd();
