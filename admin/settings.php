<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $clamp = fn(string $k, int $lo, int $hi) => (string) max($lo, min($hi, (int) ($_POST[$k] ?? DEFAULT_SETTINGS[$k])));
    $new = [
        'site_name'            => mb_substr(trim((string) ($_POST['site_name'] ?? '')) ?: DEFAULT_SETTINGS['site_name'], 0, 40),
        'module_title'         => mb_substr(trim((string) ($_POST['module_title'] ?? '')) ?: DEFAULT_SETTINGS['module_title'], 0, 80),
        'allow_registration'   => !empty($_POST['allow_registration']) ? '1' : '0',
        'pass_score'           => $clamp('pass_score', 1, 100),
        'session_idle_minutes' => $clamp('session_idle_minutes', 5, 240),
        'max_failed_logins'    => $clamp('max_failed_logins', 3, 20),
        'lockout_minutes'      => $clamp('lockout_minutes', 1, 1440),
        'require_2fa_admin'    => !empty($_POST['require_2fa_admin']) ? '1' : '0',
        'require_2fa_teacher'  => !empty($_POST['require_2fa_teacher']) ? '1' : '0',
    ];
    $changed = [];
    foreach ($new as $k => $v) if (setting($k) !== $v) $changed[] = "$k=$v";
    foreach ($new as $k => $v) settingSet($k, $v);
    auditLog('admin.settings_changed', implode(', ', $changed) ?: 'no change');
    flashSet('success', 'Settings saved.');
    redirect(u('/admin/settings.php'));
}

adminStart('Settings & security', 'settings');
pageHead('Settings & security', 'Branding, learning rules and sign-in protection.');
?>
<form method="POST" action="<?= u('/admin/settings.php') ?>"><?= csrfField() ?>
  <div class="grid2" style="align-items:start">
    <div class="card">
      <p class="card-h">Branding &amp; learning</p>
      <div class="field"><label class="label" for="site_name">Platform name</label><input class="input" id="site_name" name="site_name" value="<?= e(setting('site_name')) ?>" maxlength="40"></div>
      <div class="field"><label class="label" for="module_title">Module title</label><input class="input" id="module_title" name="module_title" value="<?= e(setting('module_title')) ?>" maxlength="80">
        <div class="hint">Shown in the top bar and sidebar. Change it when you teach a different subject.</div></div>
      <div class="field"><label class="label" for="pass_score">Passing score (%)</label><input class="input" type="number" id="pass_score" name="pass_score" min="1" max="100" value="<?= (int) setting('pass_score') ?>">
        <div class="hint">Students must reach this to unlock the next section after a quiz.</div></div>
      <label class="check"><input type="checkbox" name="allow_registration" value="1" <?= setting('allow_registration') === '1' ? 'checked' : '' ?>> Let students create their own accounts</label>
    </div>
    <div class="card">
      <p class="card-h">Sign-in protection</p>
      <div class="field"><label class="label" for="max_failed_logins">Failed attempts before lockout</label><input class="input" type="number" id="max_failed_logins" name="max_failed_logins" min="3" max="20" value="<?= (int) setting('max_failed_logins') ?>"></div>
      <div class="field"><label class="label" for="lockout_minutes">Lockout length (minutes)</label><input class="input" type="number" id="lockout_minutes" name="lockout_minutes" min="1" max="1440" value="<?= (int) setting('lockout_minutes') ?>"></div>
      <div class="field"><label class="label" for="session_idle_minutes">Sign out after inactivity (minutes)</label><input class="input" type="number" id="session_idle_minutes" name="session_idle_minutes" min="5" max="240" value="<?= (int) setting('session_idle_minutes') ?>">
        <div class="hint">Administrators are always capped at <?= ADMIN_MAX_IDLE_MINUTES ?> minutes.</div></div>
      <label class="check" style="margin-bottom:8px"><input type="checkbox" name="require_2fa_admin" value="1" <?= setting('require_2fa_admin') === '1' ? 'checked' : '' ?>> Require two-factor for administrators</label>
      <label class="check"><input type="checkbox" name="require_2fa_teacher" value="1" <?= setting('require_2fa_teacher') === '1' ? 'checked' : '' ?>> Require two-factor for teachers</label>
      <div class="hint">People without it are sent to set it up on their next page load.</div>
    </div>
  </div>
  <div style="margin-top:16px"><button class="btn btn-primary">Save settings</button></div>
</form>

<div class="card" style="margin-top:16px">
  <p class="card-h">Always on</p>
  <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.9">
    <li>Passwords are stored with <?= defined('PASSWORD_ARGON2ID') ? 'Argon2id' : 'bcrypt' ?>; staff passwords need 12+ characters.</li>
    <li>CSRF tokens on every form, HttpOnly + SameSite session cookies, session ID rotated at login.</li>
    <li>Per-account lockout and per-IP throttling, plus a hidden bot-trap field on the login form.</li>
    <li>Generic login errors (accounts cannot be discovered), hidden honeypot field against bots.</li>
    <li>Sessions end on role change, suspension or password reset; remember-me is never offered to admins.</li>
    <li>Sensitive admin actions ask for your password again and are written to the audit log.</li>
  </ul>
</div>
<?php adminEnd();
