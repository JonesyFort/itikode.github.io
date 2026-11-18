<?php
declare(strict_types=1);
/**
 * HTML <head> + sticky top navigation bar.
 * Expects $pageTitle (string) and $currentUser (array|null) in scope.
 */
$_site  = setting('site_name');
$_title = isset($pageTitle) ? e($pageTitle) . ' — ' . e($_site) : e($_site);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $_title ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= u('/assets/app.css') ?>">
  <style>
    * { box-sizing: border-box; }
    body { background-color: #E6F0DC; font-family: 'Inter', sans-serif; margin: 0; }
    pre, code { font-family: 'JetBrains Mono', monospace; }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #C1E899; border-radius: 99px; }
  </style>
</head>
<body>

<nav style="background:#55883B;border-bottom:2px solid #3d6b28;position:sticky;top:0;z-index:50;">
  <div style="max-width:1320px;margin:0 auto;padding:0 16px;display:flex;align-items:center;justify-content:space-between;height:52px;">

    <a href="<?= u('/index.php') ?>" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
      <span style="background:#C1E899;color:#2D4A1C;border-radius:99px;padding:4px 14px;font-size:13px;font-weight:900;letter-spacing:0.04em;border:2px solid #2D4A1C;"><?= e($_site) ?></span>
      <span style="color:rgba(230,240,220,0.65);font-size:11px;font-weight:500;"><?= e(setting('module_title')) ?></span>
    </a>

    <?php if (!empty($currentUser)): ?>
    <div style="display:flex;align-items:center;gap:10px;">
      <?php if ($currentUser['role'] !== 'student'): ?>
      <span style="background:rgba(154,103,53,0.3);color:#E6F0DC;border:1.5px solid #9A6735;border-radius:99px;font-size:9px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;"><?= e(ucfirst($currentUser['role'])) ?> View</span>
      <?php endif; ?>
      <?php if ($currentUser['role'] === 'teacher'): ?>
      <a href="<?= u('/teacher/index.php') ?>" style="background:#C1E899;color:#2D4A1C;border:2px solid #2D4A1C;border-radius:8px;padding:4px 12px;font-size:11px;font-weight:800;text-decoration:none;">Teacher panel</a>
      <?php endif; ?>
      <?php if ($currentUser['role'] === 'admin'): ?>
      <a href="<?= u('/admin/index.php') ?>" style="background:#C1E899;color:#2D4A1C;border:2px solid #2D4A1C;border-radius:8px;padding:4px 12px;font-size:11px;font-weight:800;text-decoration:none;">Admin console</a>
      <?php endif; ?>
      <a href="<?= u('/account.php') ?>" title="Account &amp; security" style="display:flex;align-items:center;gap:7px;text-decoration:none;">
        <div style="width:28px;height:28px;border-radius:50%;background:#C1E899;border:2px solid #2D4A1C;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;color:#2D4A1C;"><?= e(strtoupper(substr($currentUser['name'], 0, 2))) ?></div>
        <span style="color:#E6F0DC;font-size:12px;font-weight:600;"><?= e($currentUser['name']) ?></span>
      </a>
      <form method="POST" action="<?= u('/logout.php') ?>" style="margin:0;"><?= csrfField() ?>
        <button type="submit" style="background:rgba(255,255,255,0.12);color:#E6F0DC;border:1.5px solid rgba(255,255,255,0.2);border-radius:8px;padding:5px 12px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;">Log out</button>
      </form>
    </div>
    <?php else: ?>
    <div style="display:flex;align-items:center;gap:8px;">
      <a href="<?= u('/login.php') ?>"    style="color:#E6F0DC;font-size:12px;font-weight:600;text-decoration:none;padding:5px 10px;">Log in</a>
      <a href="<?= u('/register.php') ?>" style="background:#C1E899;color:#2D4A1C;border-radius:8px;padding:5px 12px;font-size:11px;font-weight:700;text-decoration:none;">Register</a>
    </div>
    <?php endif; ?>

  </div>
</nav>
