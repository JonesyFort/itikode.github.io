<?php
declare(strict_types=1);
/** Shared view helpers for the sign-in, account and admin pages. */

function icon(string $name, int $size = 18): string
{
    $paths = [
        'mail'   => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'lock'   => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'eye'    => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eyeoff' => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><path d="m2 2 20 20"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'code'   => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'user'   => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'   => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'chart'  => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'scroll' => '<path d="M8 21h12a2 2 0 0 0 2-2v-2H10v2a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v3h4"/><path d="M19 17V5a2 2 0 0 0-2-2H4"/>',
        'home'   => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'key'    => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'arrowright'  => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'chevright'   => '<path d="m9 18 6-6-6-6"/>',
        'chevleft'    => '<path d="m15 18-6-6 6-6"/>',
        'clock'       => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'checkcircle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'xcircle'     => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'trophy'      => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'star'        => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
        'play'        => '<path d="M6 3l14 9-14 9z"/>',
        'grad'        => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
        'menu'        => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'trend'       => '<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>',
        'check'  => '<path d="M20 6 9 17l-5-5"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function htmlHead(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> — <?= e(setting('site_name')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= u('/assets/app.css') ?>">
</head>
<?php
}

/** Split-screen shell used by login, 2FA, register, forgot/reset password. */
function authStart(string $title, string $heading = '', string $lead = ''): void
{
    htmlHead($title);
    $heading = $heading ?: 'Learn object-oriented Python by building it.';
    $lead = $lead ?: 'Short lessons, hands-on code challenges and quick quizzes that make classes, objects and inheritance click.';
    ?>
<body>
<div class="auth">
  <aside class="auth-art">
    <div><span class="logo"><?= e(setting('site_name')) ?></span></div>
    <div>
      <h1><?= e($heading) ?></h1>
      <p><?= e($lead) ?></p>
      <ul class="bullets">
        <li><span class="ic"><?= icon('code', 16) ?></span><span><b>Try it yourself.</b> Run real Python in your browser. No installs.</span></li>
        <li><span class="ic"><?= icon('chart', 16) ?></span><span><b>Track your progress.</b> Pre-test to post-test, section by section.</span></li>
        <li><span class="ic"><?= icon('shield', 16) ?></span><span><b>Your account is protected.</b> Lockouts, two-factor sign-in and an audit trail.</span></li>
      </ul>
    </div>
    <div class="codecard" aria-hidden="true">
      <span class="c"># everything is an object</span><br>
      <span class="k">class</span> Student:<br>
      &nbsp;&nbsp;<span class="k">def</span> study(<span class="k">self</span>):<br>
      &nbsp;&nbsp;&nbsp;&nbsp;<span class="k">print</span>(<span class="s">"Levelling up!"</span>)
    </div>
  </aside>
  <main class="auth-main"><div class="auth-card">
<?php
}

function authEnd(): void
{
    ?>
    <div class="trust"><?= icon('lock', 13) ?> Encrypted passwords · Brute-force protection</div>
  </div></main>
</div>
<script src="<?= u('/assets/auth.js') ?>"></script>
</body>
</html>
<?php
}

function alertBox(string $msg, string $kind = 'err'): void
{
    if ($msg === '') return;
    echo '<div class="alert alert-' . e($kind) . '" role="' . ($kind === 'err' ? 'alert' : 'status') . '">' . e($msg) . '</div>';
}

/** Password input with show/hide toggle and optional caps-lock warning. */
function passwordField(string $id, string $name, string $label, array $o = []): void
{
    $auto = $o['autocomplete'] ?? 'current-password';
    ?>
  <div class="field">
    <label class="label" for="<?= e($id) ?>"><?= e($label) ?></label>
    <div class="input-wrap has-icon">
      <span class="input-icon"><?= icon('lock', 17) ?></span>
      <input class="input" type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" required autocomplete="<?= e($auto) ?>"
             placeholder="<?= e($o['placeholder'] ?? '••••••••••') ?>" <?= isset($o['strength']) ? 'data-strength data-min="' . (int) $o['min'] . '"' : '' ?> maxlength="128">
      <button type="button" class="eye" data-eye="<?= e($id) ?>" aria-label="Show password" title="Show password" aria-pressed="false">
        <?php /* the icon shows the CURRENT state: crossed-out eye = hidden, open eye = visible */ ?>
        <span class="i-show"><?= icon('eyeoff', 18) ?></span><span class="i-hide" style="display:none"><?= icon('eye', 18) ?></span>
      </button>
    </div>
    <div class="caps" role="status">Caps Lock is on</div>
    <?php if (isset($o['strength'])): ?>
    <div class="meter" aria-hidden="true"><i></i></div>
    <div style="display:flex;justify-content:space-between;align-items:center"><ul class="rules">
      <li data-rule="len"><?= (int) $o['min'] ?>+ characters</li><li data-rule="case">Upper &amp; lower case</li>
      <li data-rule="num">A number</li><li data-rule="sym">A symbol</li></ul></div>
    <div id="strength-label" class="hint" style="font-weight:800"></div>
    <?php endif; ?>
  </div>
<?php
}

function pageHead(string $title, string $lead = '', string $actions = ''): void
{
    echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap"><div><h1>' . e($title) . '</h1>'
       . ($lead ? '<p class="lead">' . e($lead) . '</p>' : '') . '</div><div>' . $actions . '</div></div>';
}

function roleBadge(string $role): string
{
    $c = ['admin' => 'pill-red', 'teacher' => 'pill-brown', 'student' => 'pill-green'][$role] ?? 'pill-gray';
    return '<span class="pill ' . $c . '">' . e($role) . '</span>';
}
