<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';

if (isLoggedIn()) redirect(u('/index.php'));
if (empty($_SESSION['pending_2fa'])) redirect(u('/login.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['cancel'])) { unset($_SESSION['pending_2fa']); redirect(u('/login.php')); }
    $code = (string) ($_POST['code'] ?? '');
    if (verifySecondFactor($code)) {
        redirect(u(landingPath((string) ($_SESSION['role'] ?? 'student'))));
    }
    if (empty($_SESSION['pending_2fa'])) {
        redirect(u('/login.php?reason=revoked'));
    }
    $error = 'That code is not valid. Check the latest code in your authenticator app.';
}

$useRecovery = !empty($_GET['recovery']);
authStart('Two-step verification', 'One more step.', 'Two-factor sign-in keeps your account safe even if your password leaks.');
?>
    <div style="width:46px;height:46px;border-radius:14px;background:var(--lime);border:2px solid var(--deep);display:grid;place-items:center;color:var(--deep);margin-bottom:14px"><?= icon('shield', 24) ?></div>
    <h2>Two-step verification</h2>
    <p class="sub"><?= $useRecovery ? 'Enter one of your saved recovery codes.' : 'Enter the 6-digit code from your authenticator app.' ?></p>
    <?php alertBox($error, 'err'); ?>

    <form method="POST" action="<?= u('/two_factor.php' . ($useRecovery ? '?recovery=1' : '')) ?>" data-once>
      <?= csrfField() ?>
      <?php if ($useRecovery): ?>
        <div class="field"><label class="label" for="code">Recovery code</label>
        <input class="input mono" id="code" name="code" required autocomplete="off" placeholder="XXXX-XXXX" maxlength="9" autofocus></div>
      <?php else: ?>
        <input type="hidden" id="code" name="code">
        <div class="otp" role="group" aria-label="6-digit code">
          <?php for ($i = 0; $i < 6; $i++): ?><input inputmode="numeric" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" maxlength="1" aria-label="Digit <?= $i + 1 ?>"><?php endfor; ?>
        </div>
        <div style="height:18px"></div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-block">Verify and continue</button>
    </form>
    <form method="POST" action="<?= u('/two_factor.php') ?>" style="margin-top:12px;text-align:center">
      <?= csrfField() ?>
      <a href="<?= u('/two_factor.php' . ($useRecovery ? '' : '?recovery=1')) ?>" style="font-size:13px;font-weight:700"><?= $useRecovery ? 'Use authenticator code instead' : 'Lost your device? Use a recovery code' ?></a>
      <span style="color:#9BB48A"> · </span>
      <button name="cancel" value="1" style="border:0;background:none;font:700 13px Inter;color:var(--brown);cursor:pointer">Cancel</button>
    </form>
<?php authEnd(); ?>
