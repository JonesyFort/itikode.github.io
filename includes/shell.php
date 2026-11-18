<?php
declare(strict_types=1);
/**
 * Learner shell (dashboard + lesson pages), matching the Figma layout:
 * rounded frame, green sidebar, white top bar, scrolling content area.
 *
 * Expects $currentUser, $progress and (optionally) $activeSectionId in scope.
 *   shellStart('Page title', 'Breadcrumb label or null');
 *   ... page content ...
 *   shellEnd();
 */
require_once __DIR__ . '/ui.php';

function shellStart(string $pageTitle, ?string $crumb = null): void
{
    global $currentUser, $progress, $activeSectionId;
    $site     = setting('site_name');
    $module   = setting('module_title');
    $role     = (string) ($currentUser['role'] ?? 'student');
    $isStaff  = $role !== 'student';
    $words    = preg_split('/\s+/', trim((string) ($currentUser['name'] ?? ''))) ?: [];
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) { $initials .= mb_substr($w, 0, 1); }
    $initials = mb_strtoupper($initials);
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — <?= e($site) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= u('/assets/app.css') ?>">
</head>
<body class="lx" data-uid="<?= (int) ($currentUser['id'] ?? 0) ?>" data-role="<?= e($role) ?>">
<div class="lx-frame">

  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="lx-col">
    <header class="lx-top">
      <div class="lx-crumb">
        <button type="button" class="lx-menu" data-menu aria-label="Open section list"><?= icon('menu', 15) ?></button>
        <a class="lx-home" href="<?= u('/index.php') ?>" aria-label="Dashboard"><?= icon('home', 14) ?></a>
        <span class="lx-sep">›</span>
        <span class="mod"><?= e($module) ?></span>
        <?php if ($crumb !== null && $crumb !== ''): ?>
        <span class="lx-sep">›</span>
        <span class="cur"><?= e($crumb) ?></span>
        <?php endif; ?>
      </div>

      <div class="lx-right">
        <?php if ($isStaff): ?><span class="lx-chip"><?= icon('grad', 12) ?> <?= e($role) ?> View</span><?php endif; ?>
        <?php if ($role === 'teacher'): ?>
        <a class="lx-chip-admin" href="<?= u('/teacher/index.php') ?>"><?= icon('book', 13) ?> <span>Teacher panel</span></a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
        <a class="lx-chip-admin" href="<?= u('/admin/index.php') ?>"><?= icon('shield', 13) ?> <span>Admin console</span></a>
        <?php endif; ?>
        <a class="lx-who" href="<?= u('/account.php') ?>" title="Account &amp; security">
          <b><?= e((string) $currentUser['name']) ?></b><span><?= e((string) $currentUser['email']) ?></span>
        </a>
        <a class="lx-av<?= $isStaff ? ' staff' : '' ?>" href="<?= u('/account.php') ?>" title="Account &amp; security"><?= e($initials) ?></a>
        <form method="POST" action="<?= u('/logout.php') ?>" style="margin:0"><?= csrfField() ?>
          <button type="submit" class="lx-out" aria-label="Log out"><?= icon('logout', 13) ?> <span>Log out</span></button>
        </form>
      </div>
    </header>

    <main class="lx-main">
<?php
}

function shellEnd(): void
{
    ?>
    </main>
  </div>
</div>
<script src="<?= u('/assets/learn.js') ?>"></script>
</body>
</html>
<?php
}

/** Green hero card at the top of a page. $kicker / $aside / $actions are trusted HTML. */
function lxHero(string $kickerHtml, string $title, string $subtitle = '', string $asideHtml = '', string $actionsHtml = ''): void
{
    ?>
  <div class="lx-hero"><div class="lx-hero-in">
    <div class="lx-hero-txt">
      <div class="lx-kick"><?= $kickerHtml ?></div>
      <h1><?= e($title) ?></h1>
      <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php if ($actionsHtml !== ''): ?><div><?= $actionsHtml ?></div><?php endif; ?>
    <?php if ($asideHtml !== ''): ?><div class="lx-hero-aside"><?= $asideHtml ?></div><?php endif; ?>
  </div></div>
<?php
}

/** Icon tile + small label + heading + rule, used above each content block. */
function lxStage(string $emoji, string $part, string $title, bool $done = false, string $idPrefix = ''): void
{
    ?>
  <div class="lx-stage">
    <div class="lx-stage-ic<?= $done ? ' done' : '' ?>"><span aria-hidden="true"><?= $emoji ?></span><?php if ($done): ?><span class="st"><?= icon('star', 10) ?></span><?php endif; ?></div>
    <div style="min-width:0">
      <small<?= $idPrefix ? ' id="' . e($idPrefix) . '-part"' : '' ?>><?= e($part) ?></small>
      <h2<?= $idPrefix ? ' id="' . e($idPrefix) . '-title"' : '' ?>><?= e($title) ?></h2>
    </div>
    <div class="rule"></div>
  </div>
<?php
}
