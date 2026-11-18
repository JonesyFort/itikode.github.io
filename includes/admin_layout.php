<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui.php';

requireAdmin();

/** Re-checks the signed-in admin's password before a sensitive change. */
function adminPasswordOk(string $pw): bool
{
    return confirmPassword($pw, 'admin.bad_confirmation');
}

function generateTempPassword(): string
{
    $words = ['Maple', 'River', 'Cloud', 'Tiger', 'Lemon', 'Comet', 'Pixel', 'Forest'];
    return $words[random_int(0, 7)] . '-' . $words[random_int(0, 7)] . '-' . random_int(1000, 9999) . '!x';
}

function adminStart(string $title, string $active): void
{
    global $currentUser, $pageTitle;
    $currentUser = currentUser();
    $pageTitle = $title . ' · Admin';
    include __DIR__ . '/header.php';
    $items = [
        'index'    => ['/admin/index.php',        'home',   'Overview'],
        'users'    => ['/admin/users.php',        'users',  'Accounts'],
        'audit'    => ['/admin/audit.php',        'scroll', 'Audit log'],
        'settings' => ['/admin/settings.php',     'cog',    'Settings & security'],
    ];
    ?>
<div class="adm">
  <nav class="adm-nav" aria-label="Admin">
    <div class="t">Admin console</div>
    <?php foreach ($items as $k => [$href, $ic, $label]): ?>
      <a href="<?= u($href) ?>" class="<?= $k === $active ? 'on' : '' ?>"><?= icon($ic, 16) ?> <?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="t" style="margin-top:14px">Shortcuts</div>
    <a href="<?= u('/index.php') ?>"><?= icon('code', 16) ?> View as learner</a>
    <a href="<?= u('/account.php') ?>"><?= icon('key', 16) ?> My security</a>
  </nav>
  <div class="adm-main">
    <?php alertBox(flashGet('success'), 'ok'); alertBox(flashGet('error'), 'err'); ?>
<?php
}

function adminEnd(): void
{
    ?>
  </div>
</div>
<script src="<?= u('/assets/auth.js') ?>"></script>
</body>
</html>
<?php
}

/** True if removing admin power from $userId would leave no active admin. */
function isLastActiveAdmin(int $userId): bool
{
    $st = getDB()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != ?");
    $st->execute([$userId]);
    return (int) $st->fetchColumn() === 0;
}
