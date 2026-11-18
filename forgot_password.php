<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';

if (isLoggedIn()) redirect(u('/index.php'));

$error = '';
$sent = false;
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (ipFailures(60) >= 40) {
        $error = 'Too many requests. Try again later.';
    } else {
        recordAttempt('reset:' . $email, false);      // throttles reset spam without locking the real account
        $token = createPasswordResetToken($email);
        $sent = true;                               // same answer whether or not the account exists
        if ($token !== null && SHOW_RESET_LINK) {
            $scheme = isHttps() ? 'https' : 'http';
            $resetLink = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . u('/reset_password.php') . '?token=' . $token;
        }
    }
}

authStart('Forgot password', 'Locked out? No problem.', 'We will help you choose a new password and sign out any other devices.');
?>
    <h2>Reset your password</h2>
    <p class="sub">Enter your email and we will prepare a reset link.</p>
    <?php alertBox($error, 'err'); ?>
    <?php if ($sent): ?>
      <div class="alert alert-ok">If an account exists for that email, a reset link has been prepared. It expires in 30 minutes.</div>
      <?php if ($resetLink): ?>
        <div class="alert alert-warn">Local mode: there is no mail server, so the link is shown here.<br><a href="<?= e($resetLink) ?>" style="word-break:break-all;font-weight:800"><?= e($resetLink) ?></a></div>
      <?php endif; ?>
    <?php else: ?>
    <form method="POST" action="<?= u('/forgot_password.php') ?>" novalidate data-once>
      <?= csrfField() ?>
      <div class="field"><label class="label" for="email">Email address</label>
        <div class="input-wrap has-icon"><span class="input-icon"><?= icon('mail', 17) ?></span>
        <input class="input" type="email" id="email" name="email" required autofocus autocomplete="email" placeholder="you@school.edu"></div></div>
      <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>
    <?php endif; ?>
    <p style="text-align:center;margin:18px 0 0;font-size:13.5px"><a href="<?= u('/login.php') ?>" style="font-weight:800">Back to log in</a></p>
<?php authEnd(); ?>
