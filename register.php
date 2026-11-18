<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/includes/classes.php';

if (isLoggedIn()) redirect(u('/index.php'));
if (setting('allow_registration') !== '1') {
    redirect(u('/login.php'));
}

$error = '';
$name = $email = $code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    $code     = trim((string) ($_POST['class_code'] ?? ''));
    $class    = $code !== '' ? classByCode($code) : null;

    $problems = passwordProblems($password, 'student', $email, $name);
    if (!empty($_POST['website'])) {
        $error = 'Could not create the account.';
    } elseif ($name === '' || mb_strlen($name) > 80) {
        $error = 'Enter your name (80 characters max).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($problems) {
        $error = $problems[0];
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } elseif ($code !== '' && (!$class || $class['archived'] || !$class['joining_open'])) {
        $error = 'That class code did not work. Check it with your teacher, or leave it empty and join later.';
    } elseif (empty($_POST['terms'])) {
        $error = 'Please accept the terms to continue.';
    } elseif (ipFailures(60) >= 40) {
        $error = 'Too many attempts. Try again later.';
    } else {
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with that email already exists. Try logging in.';
        } else {
            // Public sign-up always creates a student. Staff accounts are created by an administrator.
            $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'student')")
               ->execute([$name, $email, hashPassword($password)]);
            $newId = (int) $db->lastInsertId();
            auditLog('user.registered', $email, $newId, $name);
            if ($class) { addToClass((int) $class['id'], $newId); auditLog('class.joined', $class['name'], $newId, $name); }
            flashSet('success', $class ? 'Account created and you joined ' . $class['name'] . '. You can log in now.' : 'Account created. You can log in now.');
            redirect(u('/login.php'));
        }
    }
}

authStart('Create account', 'Start your OOP journey.', 'Create a free student account to unlock the 13-section module.');
?>
    <h2>Create your account</h2>
    <p class="sub">It takes less than a minute.</p>
    <?php alertBox($error, 'err'); ?>
    <form method="POST" action="<?= u('/register.php') ?>" novalidate data-once>
      <?= csrfField() ?>
      <div class="honey" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="field"><label class="label" for="name">Full name</label>
        <div class="input-wrap has-icon"><span class="input-icon"><?= icon('user', 17) ?></span>
        <input class="input" id="name" name="name" value="<?= e($name) ?>" required autocomplete="name" maxlength="80" placeholder="Juan Dela Cruz"></div></div>
      <div class="field"><label class="label" for="email">Email address</label>
        <div class="input-wrap has-icon"><span class="input-icon"><?= icon('mail', 17) ?></span>
        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" maxlength="190" placeholder="you@school.edu"></div></div>
      <?php passwordField('password', 'password', 'Password', ['autocomplete' => 'new-password', 'strength' => true, 'min' => 8]); ?>
      <?php passwordField('confirm', 'confirm', 'Confirm password', ['autocomplete' => 'new-password']); ?>
      <div class="field"><label class="label" for="class_code">Class code <span style="font-weight:500;text-transform:none;letter-spacing:0">(optional)</span></label>
        <input class="input" id="class_code" name="class_code" value="<?= e($code) ?>" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="From your teacher, e.g. K7M2QP"></div>
      <div id="match" class="hint" style="margin:-8px 0 12px;font-weight:700"></div>
      <label class="check" style="margin-bottom:18px;align-items:flex-start"><input type="checkbox" name="terms" value="1" required style="margin-top:2px">
        <span style="font-weight:600">I agree to the terms of use and understand my progress is stored on this server.</span></label>
      <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <p style="text-align:center;margin:18px 0 0;font-size:13.5px">Already registered? <a href="<?= u('/login.php') ?>" style="font-weight:800">Log in</a></p>
<?php authEnd(); ?>
