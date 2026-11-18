<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$row   = validateResetToken($token);
$error = '';

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    $problems = passwordProblems($password, $row['role'], $row['email'], $row['name']);
    if ($problems) $error = $problems[0];
    elseif ($password !== $confirm) $error = 'The two passwords do not match.';
    elseif (consumeResetToken($token, $password)) {
        flashSet('success', 'Password updated. Log in with your new password.');
        redirect(u('/login.php'));
    } else $error = 'This link is no longer valid.';
}

$min = $row && in_array($row['role'], ['admin', 'teacher'], true) ? 12 : 8;
authStart('Choose a new password', 'Fresh start.', 'Pick a strong password you have not used anywhere else.');
?>
    <h2>Choose a new password</h2>
    <?php if (!$row): ?>
      <div class="alert alert-err" style="margin-top:14px">This reset link is invalid or has expired.</div>
      <a class="btn btn-primary btn-block" href="<?= u('/forgot_password.php') ?>">Request a new link</a>
    <?php else: ?>
      <p class="sub">For <b><?= e($row['email']) ?></b>. You will be signed out of every other device.</p>
      <?php alertBox($error, 'err'); ?>
      <form method="POST" action="<?= u('/reset_password.php') ?>" novalidate data-once>
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <?php passwordField('password', 'password', 'New password', ['autocomplete' => 'new-password', 'strength' => true, 'min' => $min]); ?>
        <?php passwordField('confirm', 'confirm', 'Confirm new password', ['autocomplete' => 'new-password']); ?>
        <div id="match" class="hint" style="margin:-8px 0 12px;font-weight:700"></div>
        <button type="submit" class="btn btn-primary btn-block">Update password</button>
      </form>
    <?php endif; ?>
<?php authEnd(); ?>
