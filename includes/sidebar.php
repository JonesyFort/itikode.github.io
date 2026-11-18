<?php
declare(strict_types=1);
/**
 * Left sidebar for the learner shell.
 * Expects $progress (array), $currentUser (array), $activeSectionId (int|null).
 */
$_sections  = getAllSections();
$_role      = (string) ($currentUser['role'] ?? 'student');
$_total     = max(1, count($_sections));
$_done      = completedCount($progress);
$_pct       = (int) round($_done / $_total * 100);
$_activeId  = $activeSectionId ?? null;
$_n         = 0;
?>
<aside class="lx-side" aria-label="Lesson sections">
  <a class="lx-logo" href="<?= u('/index.php') ?>">
    <span class="lx-logo-mark">&lt;/&gt;</span>
    <div>
      <div class="lx-logo-name"><?= e(setting('site_name')) ?></div>
      <div class="lx-logo-sub"><?= e(setting('module_title')) ?></div>
    </div>
  </a>

  <div class="lx-side-label">Lesson Sections</div>

  <nav class="lx-nav">
    <?php foreach ($_sections as $_id => $_sec):
      $_n++;
      $_locked = !canAccess($_id, $progress, $_role);
      $_isDone = isset($progress[$_id]);
      $_isOn   = ($_id === $_activeId);
      $_cls    = 'lx-item' . ($_isOn ? ' on' : '') . ($_isDone ? ' done' : '') . ($_locked ? ' lock' : '');
    ?>
    <<?= $_locked ? 'div' : 'a' ?> class="<?= $_cls ?>"<?= $_locked ? ' aria-disabled="true"' : ' href="' . u('/section.php?id=' . $_id) . '"' ?><?= $_isOn ? ' aria-current="page"' : '' ?>>
      <span class="lx-num"><?= $_isDone ? icon('check', 12) : $_n ?></span>
      <span class="nm"><?= e($_sec['name']) ?></span>
      <?php if ($_locked): ?><?= icon('lock', 10) ?><?php endif; ?>
    </<?= $_locked ? 'div' : 'a' ?>>
    <?php endforeach; ?>
  </nav>

  <div class="lx-prog">
    <div class="lx-bar"><i style="width:<?= $_pct ?>%"></i></div>
    <div class="lx-prog-row"><b><?= $_done ?> / <?= $_total ?> complete</b><span><?= $_pct ?>%</span></div>
  </div>
</aside>
