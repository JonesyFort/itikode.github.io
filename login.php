<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';

if (isLoggedIn()) redirect(u(landingPath((string) (currentUser()['role'] ?? 'student'))));

$error = '';
$email = '';
$lockSeconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if (!empty($_POST['website'])) {                       // honeypot: real people never fill this in
        auditLog('login.bot_blocked', $email, null, $email);
        sleep(2);
        $error = 'Incorrect email or password.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $res = attemptLogin($email, $password, $remember);
        switch ($res['status']) {
            case 'ok':
                redirect(u(landingPath((string) $res['user']['role'])));
            case '2fa':
                redirect(u('/two_factor.php'));
            case 'locked':
                $lockSeconds = (int) $res['retry_after'];
                $error = 'Too many failed attempts. This account is temporarily locked to keep it safe.';
                break;
            case 'throttled':
                $error = 'Too many attempts from this device. Please wait a few minutes and try again.';
                break;
            case 'suspended':
                $error = 'This account has been disabled. Contact your administrator.';
                break;
            default:
                $left = (int) ($res['remaining'] ?? 0);
                $error = 'Incorrect email or password.' . ($left > 0 && $left <= 3 ? " $left attempt" . ($left === 1 ? '' : 's') . ' left before a temporary lock.' : '');
        }
    }
}

$notice = flashGet('success');
$reason = (string) ($_GET['reason'] ?? ($_SESSION['logout_reason'] ?? ''));
unset($_SESSION['logout_reason']);

authStart('Log in', 'Welcome back, coder.', 'Pick up where you left off, or start a new lesson in object-oriented Python.');
?>
    <h2>Log in</h2>
    <p class="sub">Use your school account to continue.</p>

    <?php alertBox($notice, 'ok'); ?>
    <?php if ($reason === 'timeout') alertBox('You were signed out after a period of inactivity.', 'warn'); ?>
    <?php if ($reason === 'revoked') alertBox('Your session ended. Please sign in again.', 'warn'); ?>
    <?php alertBox($error, 'err'); ?>
    <?php if ($lockSeconds > 0): ?>
      <div class="alert alert-warn" role="status">Try again in <b id="countdown" data-seconds="<?= $lockSeconds ?>"><?= intdiv($lockSeconds, 60) ?>:<?= str_pad((string) ($lockSeconds % 60), 2, '0', STR_PAD_LEFT) ?></b>. You can also <a href="<?= u('/forgot_password.php') ?>">reset your password</a>.</div>
    <?php endif; ?>

    <form method="POST" action="<?= u('/login.php') ?>" novalidate data-once id="login-form">
      <?= csrfField() ?>
      <div class="honey" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

      <div class="field">
        <label class="label" for="email">Email address</label>
        <div class="input-wrap has-icon">
          <span class="input-icon"><?= icon('mail', 17) ?></span>
          <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus placeholder="you@school.edu" maxlength="190">
        </div>
      </div>

      <?php passwordField('password', 'password', 'Password'); ?>

      <div style="display:flex;align-items:center;justify-content:space-between;margin:2px 0 18px">
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <a href="<?= u('/forgot_password.php') ?>" style="font-size:13px;font-weight:700;color:var(--brown);text-decoration:none">Forgot password?</a>
      </div>

      <button type="submit" class="btn btn-primary btn-block" <?= $lockSeconds > 0 ? 'disabled' : '' ?>>Log in</button>
      <p class="hint" style="text-align:center;margin-top:10px">Staying signed in is not available for administrator accounts.</p>
    </form>

    <?php if (setting('allow_registration') === '1'): ?>
    <p style="text-align:center;margin:18px 0 0;font-size:13.5px">New student? <a href="<?= u('/register.php') ?>" style="font-weight:800">Create an account</a></p>
    <?php endif; ?>

    <?php if (DEMO_MODE): ?>
    <div class="divider">Local demo accounts</div>
    <div class="demo">
      <?php foreach (SEED_USERS as [$n, $mail, $pass, $role]): ?>
      <button type="button" data-email="<?= e($mail) ?>" data-pass="<?= e($pass) ?>">
        <span style="flex:none;width:30px;height:30px;border-radius:50%;background:var(--lime);border:2px solid var(--deep);display:grid;place-items:center;color:var(--deep)"><?= icon($role === 'admin' ? 'shield' : ($role === 'teacher' ? 'book' : 'user'), 15) ?></span>
        <span><b><?= ucfirst($role) ?></b><span><?= e($mail) ?></span></span>
      </button>
      <?php endforeach; ?>
      <p class="hint" style="margin:2px 0 0">Click a card to fill in the form. Turn this off with <code>DEMO_MODE</code> in <code>config/config.php</code>.</p>
    </div>
    <script>
      document.querySelectorAll('.demo button').forEach(function (b) {
        b.addEventListener('click', function () {
          document.getElementById('email').value = b.dataset.email;
          document.getElementById('password').value = b.dataset.pass;
          document.getElementById('password').focus();
        });
      });
    </script>
    <?php endif; ?>
<?php authEnd(); ?>
