<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

$db = getDB();
$err = [];
$form = ['name' => '', 'email' => '', 'role' => 'student'];
$showNew = !empty($_GET['new']);
$tempPw = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $showNew = true;
    $form = ['name' => trim((string) ($_POST['name'] ?? '')), 'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
             'role' => in_array($_POST['role'] ?? '', ROLES, true) ? $_POST['role'] : 'student'];
    $pw = (string) ($_POST['password'] ?? '');
    if ($form['name'] === '' || mb_strlen($form['name']) > 80) $err[] = 'Enter a name (80 characters max).';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if ($pw === '') $pw = $tempPw = generateTempPassword();
    elseif ($p = passwordProblems($pw, $form['role'], $form['email'], $form['name'])) $err[] = $p[0];
    if (!$err) {
        $dup = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $dup->execute([$form['email']]);
        if ($dup->fetch()) $err[] = 'That email is already registered.';
    }
    if (!$err && $form['role'] === 'admin' && !adminPasswordOk((string) ($_POST['admin_pw'] ?? ''))) $err[] = 'Creating an admin needs your own password in the confirmation box.';
    if (!$err) {
        $db->prepare("INSERT INTO users (name, email, password_hash, role, must_change_password) VALUES (?,?,?,?,1)")
           ->execute([$form['name'], $form['email'], hashPassword($pw), $form['role']]);
        $newId = (int) $db->lastInsertId();
        auditLog('admin.user_created', $form['email'] . ' as ' . $form['role']);
        if ($tempPw) { $_SESSION['temp_pw'] = [$newId, $tempPw]; }
        flashSet('success', 'Account created. The user must choose a new password at first login.');
        redirect(u('/admin/user_edit.php?id=' . $newId));
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$role = (string) ($_GET['role'] ?? '');
$sql = "SELECT u.*, (SELECT COUNT(*) FROM user_progress p WHERE p.user_id = u.id) AS done FROM users u WHERE 1=1";
$args = [];
if ($q !== '') { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
if (in_array($role, ROLES, true)) { $sql .= " AND u.role = ?"; $args[] = $role; }
$sql .= " ORDER BY CASE u.role WHEN 'admin' THEN 0 WHEN 'teacher' THEN 1 ELSE 2 END, u.name";
$st = $db->prepare($sql); $st->execute($args);
$users = $st->fetchAll();
$total = sectionCount();

adminStart('Accounts', 'users');
pageHead('Accounts', 'Create, edit, lock, suspend or remove students, teachers and administrators.',
    '<button class="btn btn-primary" onclick="document.getElementById(\'new-dlg\').showModal()">+ New account</button>');
?>
<form method="GET" class="toolbar">
  <input class="input" style="max-width:280px" name="q" value="<?= e($q) ?>" placeholder="Search name or email" aria-label="Search">
  <select class="select" style="max-width:160px" name="role" aria-label="Role"><option value="">All roles</option>
    <?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= ucfirst($r) ?>s</option><?php endforeach; ?></select>
  <button class="btn btn-sm">Filter</button>
</form>
<div class="card" style="padding:8px 8px 4px;overflow-x:auto">
<table class="tbl">
  <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>2FA</th><th>Progress</th><th>Last login</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $x): $locked = lockoutRemaining($x['email']) > 0; ?>
    <tr>
      <td><b style="color:var(--deep)"><?= e($x['name']) ?></b><div class="hint" style="margin:1px 0 0"><?= e($x['email']) ?></div></td>
      <td><?= roleBadge($x['role']) ?></td>
      <td><?php if ($x['status'] === 'suspended'): ?><span class="pill pill-red">Suspended</span><?php elseif ($locked): ?><span class="pill pill-brown">Locked out</span><?php else: ?><span class="pill pill-green">Active</span><?php endif; ?></td>
      <td><?= $x['totp_enabled'] ? '<span class="pill pill-green">On</span>' : '<span class="pill pill-gray">Off</span>' ?></td>
      <td><?= (int) $x['done'] ?>/<?= $total ?></td>
      <td style="font-size:12px"><?= e($x['last_login_at'] ?? 'Never') ?></td>
      <td><div class="actions"><a class="btn btn-sm btn-lime" href="<?= u('/admin/user_edit.php?id=' . (int) $x['id']) ?>">Manage</a></div></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$users): ?><tr><td colspan="7" style="color:#8AA37A;padding:20px">No accounts match.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<dialog id="new-dlg" <?= $err ? 'open' : '' ?>>
  <form method="POST" action="<?= u('/admin/users.php') ?>" data-once><?= csrfField() ?>
    <h3 style="margin:0 0 12px;color:var(--deep)">New account</h3>
    <?php foreach ($err as $er) alertBox($er, 'err'); ?>
    <div class="field"><label class="label" for="n-name">Name</label><input class="input" id="n-name" name="name" value="<?= e($form['name']) ?>" required maxlength="80"></div>
    <div class="field"><label class="label" for="n-email">Email</label><input class="input" type="email" id="n-email" name="email" value="<?= e($form['email']) ?>" required></div>
    <div class="field"><label class="label" for="n-role">Role</label>
      <select class="select" id="n-role" name="role"><?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="label" for="n-pw">Temporary password</label><input class="input mono" id="n-pw" name="password" autocomplete="off" placeholder="Leave blank to generate one">
      <div class="hint">The user must replace it at first login. Staff passwords need 12+ characters.</div></div>
    <div class="field"><label class="label" for="n-apw">Your password (required for new admins)</label><input class="input" type="password" id="n-apw" name="admin_pw" autocomplete="current-password"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-sm" onclick="this.closest('dialog').close()">Cancel</button><button class="btn btn-sm btn-primary">Create account</button></div>
  </form>
</dialog>
<?php if (!$err && !empty($_GET['new'])): ?><script>document.getElementById('new-dlg').showModal()</script><?php endif; ?>
<?php adminEnd();
