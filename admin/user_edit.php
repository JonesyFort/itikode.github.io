<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $db->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$id]);
$x = $st->fetch() ?: redirect(u('/admin/users.php'));
$me = sessionUser();
$isSelf = (int) $x['id'] === (int) $me['id'];
$self = u('/admin/user_edit.php?id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $do = (string) ($_POST['do'] ?? '');
    $sensitive = ['role', 'reset_pw', 'disable_2fa', 'delete', 'suspend'];
    $ok = true;
    if (in_array($do, $sensitive, true) && !adminPasswordOk((string) ($_POST['admin_pw'] ?? ''))) {
        flashSet('error', 'Your admin password was wrong. Nothing was changed.');
        $ok = false;
    }

    if ($ok) switch ($do) {
        case 'profile':
            $name = trim((string) ($_POST['name'] ?? '')); $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $dup = $db->prepare("SELECT 1 FROM users WHERE email = ? AND id != ?"); $dup->execute([$email, $id]);
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flashSet('error', 'Enter a valid name and email.');
            elseif ($dup->fetch()) flashSet('error', 'That email belongs to another account.');
            else {
                $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")->execute([$name, $email, $id]);
                auditLog('admin.user_edited', $x['email'] . ' -> ' . $email);
                flashSet('success', 'Details saved.');
            }
            break;
        case 'role':
            $role = in_array($_POST['role'] ?? '', ROLES, true) ? $_POST['role'] : $x['role'];
            if ($isSelf) flashSet('error', 'You cannot change your own role.');
            elseif ($x['role'] === 'admin' && $role !== 'admin' && isLastActiveAdmin($id)) flashSet('error', 'This is the last active administrator.');
            else {
                $db->prepare("UPDATE users SET role = ?, session_version = session_version + 1 WHERE id = ?")->execute([$role, $id]);
                auditLog('admin.role_changed', $x['email'] . ': ' . $x['role'] . ' -> ' . $role);
                flashSet('success', 'Role changed to ' . $role . '. Their sessions were ended.');
            }
            break;
        case 'suspend':
            $to = $x['status'] === 'active' ? 'suspended' : 'active';
            if ($isSelf) flashSet('error', 'You cannot suspend yourself.');
            elseif ($to === 'suspended' && $x['role'] === 'admin' && isLastActiveAdmin($id)) flashSet('error', 'This is the last active administrator.');
            else {
                $db->prepare("UPDATE users SET status = ?, session_version = session_version + 1 WHERE id = ?")->execute([$to, $id]);
                auditLog('admin.user_' . $to, $x['email']);
                flashSet('success', $to === 'suspended' ? 'Account suspended and signed out.' : 'Account re-activated.');
            }
            break;
        case 'reset_pw':
            if ($isSelf) { flashSet('error', 'Change your own password from the My security page.'); break; }
            $pw = generateTempPassword();
            setUserPassword($id, $pw, true);
            clearFailedAttempts($x['email']);
            $_SESSION['temp_pw'] = [$id, $pw];
            auditLog('admin.password_reset', $x['email']);
            flashSet('success', 'Temporary password set. They must change it at next login, and were signed out everywhere.');
            break;
        case 'unlock':
            clearFailedAttempts($x['email']);
            auditLog('admin.unlocked', $x['email']);
            flashSet('success', 'Lockout cleared.');
            break;
        case 'signout':
            $db->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$id]);
            $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$id]);
            auditLog('admin.forced_logout', $x['email']);
            flashSet('success', 'Signed out of all devices.' . ($isSelf ? ' (Your next click will ask you to log in again.)' : ''));
            break;
        case 'disable_2fa':
            $db->prepare("UPDATE users SET totp_enabled = 0, totp_secret = NULL, recovery_codes = NULL WHERE id = ?")->execute([$id]);
            auditLog('admin.2fa_disabled', $x['email']);
            flashSet('success', 'Two-factor authentication removed for this user.');
            break;
        case 'reset_progress':
            $db->prepare("DELETE FROM user_progress WHERE user_id = ?")->execute([$id]);
            auditLog('admin.progress_reset', $x['email']);
            flashSet('success', 'Progress cleared.');
            break;
        case 'delete':
            if ($isSelf) flashSet('error', 'You cannot delete your own account.');
            elseif ($x['role'] === 'admin' && isLastActiveAdmin($id)) flashSet('error', 'This is the last active administrator.');
            else {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                auditLog('admin.user_deleted', $x['email']);
                flashSet('success', 'Account deleted.');
                redirect(u('/admin/users.php'));
            }
            break;
    }
    redirect($self);
}

$tempPw = null;
if (!empty($_SESSION['temp_pw']) && (int) $_SESSION['temp_pw'][0] === $id) { $tempPw = $_SESSION['temp_pw'][1]; }
unset($_SESSION['temp_pw']);

$prog = $db->prepare("SELECT s.name, s.type, p.score, p.completed_at FROM user_progress p JOIN sections s ON s.id = p.section_id WHERE p.user_id = ? ORDER BY s.position");
$prog->execute([$id]);
$progress = $prog->fetchAll();
$log = $db->prepare("SELECT * FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$log->execute([$id]);
$lockLeft = lockoutRemaining($x['email']);

function pwBox(string $idSuffix): string
{
    return '<div class="field" style="margin:10px 0 8px"><label class="label" for="pw-' . $idSuffix . '">Your admin password</label>'
         . '<input class="input" type="password" id="pw-' . $idSuffix . '" name="admin_pw" required autocomplete="current-password"></div>';
}

adminStart($x['name'], 'users');
pageHead($x['name'], $x['email'], '<a class="btn" href="' . u('/admin/users.php') . '">← All accounts</a>');
if ($tempPw): ?>
  <div class="alert alert-warn">Temporary password (shown once, share it privately):
    <span class="mono" style="display:inline-block;background:#fff;border:2px solid #E0A93B;border-radius:8px;padding:3px 10px;margin-left:6px;font-weight:700;user-select:all"><?= e($tempPw) ?></span></div>
<?php endif; ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <?= roleBadge($x['role']) ?>
  <span class="pill <?= $x['status'] === 'active' ? 'pill-green' : 'pill-red' ?>"><?= e($x['status']) ?></span>
  <span class="pill <?= $x['totp_enabled'] ? 'pill-green' : 'pill-gray' ?>">2FA <?= $x['totp_enabled'] ? 'on' : 'off' ?></span>
  <?php if ($lockLeft): ?><span class="pill pill-brown">Locked out for <?= ceil($lockLeft / 60) ?> min</span><?php endif; ?>
  <?php if ($x['must_change_password']): ?><span class="pill pill-brown">Must change password</span><?php endif; ?>
  <?php if ($isSelf): ?><span class="pill pill-gray">This is you</span><?php endif; ?>
</div>

<div class="grid2" style="align-items:start">
  <div class="card">
    <p class="card-h">Details</p>
    <form method="POST" action="<?= $self ?>"><?= csrfField() ?><input type="hidden" name="do" value="profile">
      <div class="field"><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e($x['name']) ?>" maxlength="80" required></div>
      <div class="field"><label class="label" for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e($x['email']) ?>" required></div>
      <button class="btn btn-primary btn-sm">Save details</button>
    </form>
    <hr style="border:0;border-top:2px solid var(--cream);margin:18px 0">
    <p class="card-h">Role</p>
    <form method="POST" action="<?= $self ?>"><?= csrfField() ?><input type="hidden" name="do" value="role">
      <select class="select" name="role" <?= $isSelf ? 'disabled' : '' ?> aria-label="Role"><?php foreach (ROLES as $r): ?><option value="<?= $r ?>" <?= $x['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select>
      <?php if (!$isSelf): ?><?= pwBox('role') ?><button class="btn btn-sm">Change role</button><?php else: ?><div class="hint">You cannot change your own role.</div><?php endif; ?>
    </form>
  </div>

  <div class="card">
    <p class="card-h">Security actions</p>
    <div style="display:grid;gap:14px">
      <?php if ($lockLeft): ?>
      <form method="POST" action="<?= $self ?>"><?= csrfField() ?><button class="btn btn-sm btn-lime" name="do" value="unlock">Clear lockout</button></form>
      <?php endif; ?>
      <form method="POST" action="<?= $self ?>"><?= csrfField() ?><button class="btn btn-sm" name="do" value="signout" data-confirm="Sign this user out of all devices?">Sign out of all devices</button></form>

      <form method="POST" action="<?= $self ?>"><?= csrfField() ?>
        <b style="font-size:13px;color:var(--deep)">Reset password</b>
        <div class="hint">Generates a one-time temporary password and ends all sessions.</div><?= pwBox('reset') ?>
        <button class="btn btn-sm btn-brown" name="do" value="reset_pw">Reset password</button></form>

      <?php if ($x['totp_enabled']): ?>
      <form method="POST" action="<?= $self ?>"><?= csrfField() ?>
        <b style="font-size:13px;color:var(--deep)">Remove two-factor</b>
        <div class="hint">For users who lost their device and recovery codes.</div><?= pwBox('2fa') ?>
        <button class="btn btn-sm btn-brown" name="do" value="disable_2fa">Remove two-factor</button></form>
      <?php endif; ?>

      <?php if (!$isSelf): ?>
      <form method="POST" action="<?= $self ?>"><?= csrfField() ?>
        <b style="font-size:13px;color:var(--deep)"><?= $x['status'] === 'active' ? 'Suspend account' : 'Re-activate account' ?></b>
        <div class="hint"><?= $x['status'] === 'active' ? 'Blocks sign-in immediately. Data is kept.' : 'Lets the user sign in again.' ?></div><?= pwBox('susp') ?>
        <button class="btn btn-sm <?= $x['status'] === 'active' ? 'btn-brown' : 'btn-lime' ?>" name="do" value="suspend"><?= $x['status'] === 'active' ? 'Suspend' : 'Re-activate' ?></button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <p class="card-h" style="margin:0">Learning progress (<?= count($progress) ?>/<?= sectionCount() ?>)</p>
    <form method="POST" action="<?= $self ?>"><?= csrfField() ?><button class="btn btn-sm" name="do" value="reset_progress" data-confirm="Clear all progress for this user?">Reset progress</button></form>
  </div>
  <table class="tbl"><thead><tr><th>Section</th><th>Score</th><th>Completed</th></tr></thead><tbody>
    <?php foreach ($progress as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= $p['score'] !== null ? (int) $p['score'] . '%' : '-' ?></td><td><?= e($p['completed_at']) ?></td></tr><?php endforeach; ?>
    <?php if (!$progress): ?><tr><td colspan="3" style="color:#8AA37A">No progress yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<div class="card" style="margin-top:14px">
  <p class="card-h">Recent activity</p>
  <table class="tbl"><tbody>
    <?php foreach ($log->fetchAll() as $r): ?><tr><td><?= e($r['created_at']) ?></td><td><?= e($r['action']) ?> <span style="color:#8AA37A"><?= e($r['detail']) ?></span></td><td class="mono"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <p class="hint">Last login: <?= e($x['last_login_at'] ?? 'never') ?> from <?= e($x['last_login_ip'] ?? 'n/a') ?> · Member since <?= e($x['created_at']) ?></p>
</div>

<?php if (!$isSelf): ?>
<div class="card" style="margin-top:14px;border-color:#D9694F">
  <p class="card-h" style="color:var(--danger)">Danger zone</p>
  <form method="POST" action="<?= $self ?>"><?= csrfField() ?>
    <div class="hint" style="margin:0">Deletes the account and all its progress permanently. Consider suspending instead.</div><?= pwBox('del') ?>
    <button class="btn btn-sm btn-danger" name="do" value="delete" data-confirm="Permanently delete this account?">Delete account</button></form>
</div>
<?php endif; ?>
<?php adminEnd();
