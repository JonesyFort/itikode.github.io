<?php
declare(strict_types=1);
/** Shows the lesson being edited exactly as students will see it, without saving anything. */
require_once dirname(__DIR__) . '/includes/teacher_layout.php';
require_once dirname(__DIR__) . '/includes/shell.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(u('/teacher/sections.php'));
verifyCsrf();

$currentUser     = currentUser();
$progress        = getUserProgress((int) $currentUser['id']);
$activeSectionId = null;

$title    = trim((string) ($_POST['title'] ?? '')) ?: 'Untitled lesson';
$duration = trim((string) ($_POST['duration'] ?? ''));
$blocks   = lbFromPost($_POST);
$html     = lbCompile($blocks, 0, $title, $duration);

shellStart('Preview: ' . $title, 'Preview (not saved)');
?>
<div class="lx-page">
  <div class="alert alert-warn">This is a preview. Nothing has been saved. Close this tab and press <b>Save section</b> in the editor to publish your changes.</div>
  <div class="lx-lesson own"><?= $html ?></div>
</div>
<?php shellEnd();
