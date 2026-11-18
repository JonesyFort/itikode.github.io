<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

$db = getDB();
$one = fn(string $sql) => (int) $db->query($sql)->fetchColumn();
$dbFile = dirname(__DIR__) . '/data/itikode.db';
$dbSize = is_file($dbFile) ? filesize($dbFile) : 0;
$stats = [
    ['Students',  $one("SELECT COUNT(*) FROM users WHERE role='student'")],
    ['Teachers',  $one("SELECT COUNT(*) FROM users WHERE role='teacher'")],
    ['Admins',    $one("SELECT COUNT(*) FROM users WHERE role='admin'")],
    ['Suspended accounts', $one("SELECT COUNT(*) FROM users WHERE status='suspended'")],
    ['Sign-ins (24h)', (int) $db->query("SELECT COUNT(*) FROM login_attempts WHERE success=1 AND created_at > " . (time() - 86400))->fetchColumn()],
    ['Failed logins (24h)', (int) $db->query("SELECT COUNT(*) FROM login_attempts WHERE success=0 AND created_at > " . (time() - 86400))->fetchColumn()],
    ['Audit events (24h)', $one("SELECT COUNT(*) FROM audit_log WHERE created_at >= datetime('now','-1 day')")],
    ['Database size', $dbSize >= 1048576 ? round($dbSize / 1048576, 1) . ' MB' : max(1, (int) round($dbSize / 1024)) . ' KB'],
];

// security checklist
$me = sessionUser();
$defaultPw = false;
foreach (SEED_USERS as [$n, $mail, $pass, $role]) {
    if ($mail === $me['email'] && password_verify($pass, $me['password_hash'])) $defaultPw = true;
}
$staffNo2fa = $one("SELECT COUNT(*) FROM users WHERE role IN ('admin','teacher') AND status='active' AND totp_enabled=0");
$checks = [
    [!$defaultPw,               'Your admin password is not the sample password', 'Change it on the My security page.'],
    [(bool) $me['totp_enabled'], 'Two-factor is on for your account', 'Set it up under My security.'],
    [setting('require_2fa_admin') === '1', 'Two-factor is required for admins', 'Turn it on in Settings & security.'],
    [!DEMO_MODE,                'Demo accounts panel is hidden on the login page', 'Set DEMO_MODE to false in config/config.php before going live.'],
    [$staffNo2fa === 0,         'Every admin and teacher uses two-factor', "$staffNo2fa staff account(s) still without it."],
];
$recent = $db->query("SELECT * FROM audit_log ORDER BY id DESC LIMIT 10")->fetchAll();

adminStart('Overview', 'index');
pageHead('Overview', 'Accounts, security and system health. Lessons and quizzes are managed by teachers in the Teacher panel.');
?>
<div class="stats">
  <?php foreach ($stats as [$label, $n]): ?><div class="stat"><b><?= $n ?></b><span><?= e($label) ?></span></div><?php endforeach; ?>
</div>
<div class="grid2" style="align-items:start">
  <div class="card">
    <p class="card-h">Security checklist</p>
    <?php foreach ($checks as [$ok, $title, $fix]): ?>
      <div style="display:flex;gap:10px;margin-bottom:10px;align-items:flex-start">
        <span class="pill <?= $ok ? 'pill-green' : 'pill-red' ?>" style="flex:none;margin-top:1px"><?= $ok ? 'OK' : 'To do' ?></span>
        <div style="font-size:13px"><b><?= e($title) ?></b><?php if (!$ok): ?><div class="hint" style="margin-top:2px"><?= e($fix) ?></div><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <p class="card-h">Quick actions</p>
    <div style="display:grid;gap:8px">
      <a class="btn btn-lime" href="<?= u('/admin/users.php?new=1') ?>">Create an account</a>
      <a class="btn" href="<?= u('/admin/users.php') ?>">Manage roles and account status</a>
      <a class="btn" href="<?= u('/admin/settings.php') ?>">Security settings</a>
      <a class="btn" href="<?= u('/admin/audit.php') ?>">Review the audit log</a>
    </div>
  </div>
</div>
<div class="card" style="margin-top:14px">
  <p class="card-h">Latest activity</p>
  <table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Event</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?>
    <tr><td><?= e($r['created_at']) ?></td><td><?= e($r['actor']) ?></td><td><?= e($r['action']) ?> <span style="color:#8AA37A"><?= e($r['detail']) ?></span></td><td class="mono"><?= e($r['ip']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php adminEnd();
