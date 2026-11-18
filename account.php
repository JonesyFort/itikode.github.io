<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/ui.php';

requireLogin('/login.php');

$db   = getDB();
$user = sessionUser();
$uid  = (int) $user['id'];
$msg  = flashGet('success');
$err  = flashGet('error');
$newCodes = $_SESSION['new_recovery_codes'] ?? null;
unset($_SESSION['new_recovery_codes']);

function passwordMatches(array $user, string $pw): bool
{
    if (password_verify($pw, $user['password_hash'])) return true;
    recordAttempt($user['email'], false);
    auditLog('account.bad_password', 'current-password check failed');
    return false;
}

function refreshSession(int $uid): void
{
    $v = (int) getDB()->query("SELECT session_version FROM users WHERE id = " . $uid)->fetchColumn();
    $_SESSION['sv'] = $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $do = (string) ($_POST['do'] ?? '');
    $stay = u('/account.php');

    if ($do === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) flashSet('error', 'Enter a name (80 characters max).');
        else {
            $db->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $uid]);
            auditLog('account.profile', 'name changed');
            flashSet('success', 'Profile saved.');
        }
    } elseif ($do === 'password') {
        $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['password'] ?? ''); $conf = (string) ($_POST['confirm'] ?? '');
        $problems = passwordProblems($new, $user['role'], $user['email'], $user['name']);
        if (!passwordMatches($user, $cur)) flashSet('error', 'Your current password is not correct.');
        elseif ($problems) flashSet('error', $problems[0]);
        elseif ($new !== $conf) flashSet('error', 'The two new passwords do not match.');
        elseif (password_verify($new, $user['password_hash'])) flashSet('error', 'Choose a password you have not used just now.');
        else {
            setUserPassword($uid, $new);          // also signs out every other device
            refreshSession($uid);
            auditLog('account.password_changed');
            flashSet('success', 'Password changed. Other devices have been signed out.');
        }
    } elseif ($do === 'signout_all') {
        $db->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?")->execute([$uid]);
        $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$uid]);
        refreshSession($uid);
        auditLog('account.signout_all');
        flashSet('success', 'Signed out of all other devices.');
    } elseif ($do === '2fa_start') {
        $_SESSION['totp_setup'] = totpNewSecret();
    } elseif ($do === '2fa_cancel') {
        unset($_SESSION['totp_setup']);
    } elseif ($do === '2fa_enable') {
        $secret = (string) ($_SESSION['totp_setup'] ?? '');
        $step = $secret ? totpVerify($secret, (string) ($_POST['code'] ?? '')) : null;
        if ($step === null) flashSet('error', 'That code did not match. Check the time on your phone and try again.');
        else {
            [$plain, $json] = newRecoveryCodes();
            $db->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, recovery_codes = ?, session_version = session_version + 1 WHERE id = ?")
               ->execute([$secret, $step, $json, $uid]);
            refreshSession($uid);
            unset($_SESSION['totp_setup']);
            $_SESSION['new_recovery_codes'] = $plain;
            auditLog('account.2fa_enabled');
            flashSet('success', 'Two-factor authentication is on.');
        }
    } elseif ($do === '2fa_disable' || $do === '2fa_codes') {
        if (!passwordMatches($user, (string) ($_POST['current'] ?? ''))) flashSet('error', 'Your current password is not correct.');
        elseif ($do === '2fa_disable') {
            $db->prepare("UPDATE users SET totp_enabled = 0, totp_secret = NULL, recovery_codes = NULL WHERE id = ?")->execute([$uid]);
            auditLog('account.2fa_disabled');
            flashSet('success', 'Two-factor authentication is off.');
        } else {
            [$plain, $json] = newRecoveryCodes();
            $db->prepare("UPDATE users SET recovery_codes = ? WHERE id = ?")->execute([$json, $uid]);
            $_SESSION['new_recovery_codes'] = $plain;
            auditLog('account.recovery_regenerated');
            flashSet('success', 'New recovery codes generated. The old ones no longer work.');
        }
    }
    redirect($stay);
}

$setupSecret = $_SESSION['totp_setup'] ?? null;
$st = $db->prepare("SELECT action, detail, ip, created_at FROM audit_log WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$st->execute([$uid]);
$activity = $st->fetchAll();
$codesLeft = $user['totp_enabled'] ? count(json_decode((string) $user['recovery_codes'], true) ?: []) : 0;
$minLen = in_array($user['role'], ['admin', 'teacher'], true) ? 12 : 8;
$daysOld = (int) floor((time() - strtotime((string) $user['password_changed_at'])) / 86400);

$currentUser = currentUser();
$pageTitle = 'Account & security';
include __DIR__ . '/includes/header.php';
?>
<div style="max-width:880px;margin:22px auto;padding:0 16px 40px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
    <div><h1 style="margin:0;font-size:24px;font-weight:900;color:var(--deep)">Account &amp; security</h1>
    <p style="margin:2px 0 0;font-size:13.5px;color:#6F8F5E">Signed in as <b><?= e($user['email']) ?></b> · <?= e(ucfirst($user['role'])) ?></p></div>
    <a class="btn btn-sm" href="<?= u('/index.php') ?>">Back to dashboard</a>
  </div>
  <div style="height:14px"></div>
  <?php alertBox($msg, 'ok'); alertBox($err, 'err'); ?>
  <?php if ($user['must_change_password']) alertBox('An administrator asked you to choose a new password before continuing.', 'warn'); ?>

  <?php if ($newCodes): ?>
  <div class="card" style="border-color:var(--brown);margin-bottom:16px">
    <p class="card-h" style="color:var(--brown)">Save your recovery codes</p>
    <p style="margin:0 0 10px;font-size:13.5px">Each code works once if you lose your authenticator. They will not be shown again.</p>
    <div class="mono" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px">
      <?php foreach ($newCodes as $c): ?><div style="background:var(--cream);border:2px solid var(--lime);border-radius:10px;padding:8px;text-align:center;font-weight:700"><?= e($c) ?></div><?php endforeach; ?>
    </div>
    <button class="btn btn-sm" type="button" onclick="navigator.clipboard.writeText('<?= e(implode(' ', $newCodes)) ?>').then(()=>this.textContent='Copied')">Copy codes</button>
  </div>
  <?php endif; ?>

  <div class="grid2" style="align-items:start">
    <div class="card">
      <p class="card-h">Profile</p>
      <form method="POST" action="<?= u('/account.php') ?>"><?= csrfField() ?><input type="hidden" name="do" value="profile">
        <div class="field"><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e($user['name']) ?>" maxlength="80" required></div>
        <div class="field"><label class="label">Email</label><input class="input" value="<?= e($user['email']) ?>" disabled><div class="hint">Ask an administrator to change your email.</div></div>
        <button class="btn btn-primary btn-sm">Save profile</button>
      </form>
      <hr style="border:0;border-top:2px solid var(--cream);margin:18px 0">
      <p class="card-h">Sessions</p>
      <p style="font-size:13px;margin:0 0 10px">Last sign-in: <?= e($user['last_login_at'] ?? 'n/a') ?> from <?= e($user['last_login_ip'] ?? 'n/a') ?>.</p>
      <form method="POST" action="<?= u('/account.php') ?>"><?= csrfField() ?><input type="hidden" name="do" value="signout_all">
        <button class="btn btn-sm" data-confirm="Sign out of every other device?">Sign out other devices</button></form>
    </div>

    <div class="card">
      <p class="card-h">Change password</p>
      <?php if ($daysOld >= 90): ?><div class="alert alert-warn">Your password is <?= $daysOld ?> days old. Consider changing it.</div><?php endif; ?>
      <form method="POST" action="<?= u('/account.php') ?>" data-once><?= csrfField() ?><input type="hidden" name="do" value="password">
        <?php passwordField('current', 'current', 'Current password'); ?>
        <?php passwordField('password', 'password', 'New password', ['autocomplete' => 'new-password', 'strength' => true, 'min' => $minLen]); ?>
        <?php passwordField('confirm', 'confirm', 'Confirm new password', ['autocomplete' => 'new-password']); ?>
        <div id="match" class="hint" style="margin:-8px 0 12px;font-weight:700"></div>
        <button class="btn btn-primary btn-sm">Update password</button>
      </form>
    </div>
  </div>

  <div class="card" id="two-factor" style="margin-top:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <p class="card-h" style="margin:0">Two-factor authentication</p>
      <span class="pill <?= $user['totp_enabled'] ? 'pill-green' : 'pill-gray' ?>"><?= $user['totp_enabled'] ? 'On' : 'Off' ?></span>
    </div>
    <?php if ($user['totp_enabled']): ?>
      <p style="font-size:13.5px;margin:0 0 12px">Sign-in asks for a code from your authenticator app. You have <b><?= $codesLeft ?></b> unused recovery codes.</p>
      <form method="POST" action="<?= u('/account.php') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end"><?= csrfField() ?>
        <div style="flex:1;min-width:220px"><label class="label" for="cur2">Confirm with your password</label><input class="input" type="password" id="cur2" name="current" autocomplete="current-password" required></div>
        <button class="btn btn-sm" name="do" value="2fa_codes">New recovery codes</button>
        <button class="btn btn-sm btn-danger" name="do" value="2fa_disable" data-confirm="Turn off two-factor authentication?">Turn off</button>
      </form>
    <?php elseif ($setupSecret): ?>
      <p style="font-size:13.5px;margin:0 0 10px">1. Open an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password). Choose <b>Enter a setup key</b>.<br>2. Enter the key below (time-based), then type the 6-digit code it shows.</p>
      <div class="mono" style="background:var(--cream);border:2px dashed var(--green);border-radius:12px;padding:12px;font-size:16px;font-weight:700;letter-spacing:.12em;text-align:center;margin-bottom:6px;color:var(--deep)"><?= e(implode(' ', str_split($setupSecret, 4))) ?></div>
      <div class="hint" style="margin-bottom:12px">Account: <?= e($user['email']) ?> · Issuer: <?= e(setting('site_name')) ?> · Or paste: <span class="mono" style="word-break:break-all"><?= e(totpUri($setupSecret, $user['email'])) ?></span></div>
      <form method="POST" action="<?= u('/account.php') ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end" data-once><?= csrfField() ?>
        <div style="width:200px"><label class="label" for="code6">6-digit code</label><input class="input mono" id="code6" name="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required placeholder="123456"></div>
        <button class="btn btn-primary btn-sm" name="do" value="2fa_enable">Verify and turn on</button>
        <button class="btn btn-sm" name="do" value="2fa_cancel" formnovalidate>Cancel</button>
      </form>
    <?php else: ?>
      <p style="font-size:13.5px;margin:0 0 12px">Add a second step to sign-in. Even if someone learns your password, they cannot get in without your phone.
        <?php if (in_array($user['role'], ['admin', 'teacher'], true)): ?><b>Strongly recommended for <?= e($user['role']) ?> accounts.</b><?php endif; ?></p>
      <form method="POST" action="<?= u('/account.php') ?>"><?= csrfField() ?><input type="hidden" name="do" value="2fa_start"><button class="btn btn-primary btn-sm">Set up two-factor</button></form>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:14px">
    <p class="card-h">Recent account activity</p>
    <table class="tbl"><thead><tr><th>When</th><th>Event</th><th>IP</th></tr></thead><tbody>
      <?php foreach ($activity as $a): ?><tr><td><?= e($a['created_at']) ?></td><td><?= e($a['action']) ?><?= $a['detail'] ? ' <span style="color:#8AA37A">· ' . e($a['detail']) . '</span>' : '' ?></td><td class="mono"><?= e($a['ip']) ?></td></tr><?php endforeach; ?>
      <?php if (!$activity): ?><tr><td colspan="3" style="color:#8AA37A">Nothing yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<script src="<?= u('/assets/auth.js') ?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
