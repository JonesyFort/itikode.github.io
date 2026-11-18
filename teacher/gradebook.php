<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';

$tid      = (int) sessionUser()['id'];
[$classId, $classRow] = currentClassFilter($tid);
$myClasses = teacherClasses($tid);
$gb       = gradebookData(teacherScopeIds($tid, $classId));
$sections = $gb['sections'];
$units    = $gb['units'];
$sum      = $gb['summary'];
$totals   = $gb['practiceTotals'];
$preId    = $gb['preId'];
$postId   = $gb['postId'];

/* ── CSV export ─────────────────────────────────────────────────────────── */
if (($_GET['export'] ?? '') === 'csv') {
    $safe = fn($v) => (is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;   // stop spreadsheet formula injection
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="itikode-gradebook-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                                   // so Excel reads UTF-8
    $head = ['Name', 'Email', 'Status', 'Sections completed', 'Completion %'];
    foreach ($sections as $s) {
        if ($s['type'] === 'quiz' || $s['type'] === 'assessment') $head[] = $s['name'] . ' (score %)';
    }
    $head[] = 'Growth (post - pre)';
    foreach ($units as $u) $head[] = $u['label'] . ' code challenges solved';
    $head[] = 'Skipped practices';
    $head[] = 'Wrong code runs';
    $head[] = 'Last activity';
    fputcsv($out, $head);
    foreach ($gb['students'] as $st) {
        $row = [$safe($st['name']), $safe($st['email']), $st['status'], $st['done'] . '/' . $st['total'], $st['pct']];
        foreach ($sections as $sid => $s) {
            if ($s['type'] === 'quiz' || $s['type'] === 'assessment') $row[] = isset($st['progress'][$sid]) ? ($st['progress'][$sid]['score'] ?? '') : '';
        }
        $row[] = $st['growth'] ?? '';
        foreach ($units as $u) $row[] = ($st['practice'][$u['lesson']] ?? 0) . '/' . ($totals[$u['lesson']] ?? 0);
        $row[] = $st['skipped'];
        $row[] = $st['fails'];
        $row[] = $st['last'];
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

ob_start(); classPicker($tid, $classId, '/teacher/gradebook.php'); $picker = (string) ob_get_clean();
teacherStart('Gradebook & analytics', 'gradebook');
pageHead('Gradebook & analytics', $classRow ? 'Live completion, scores and code practice for ' . $classRow['name'] . '.' : 'Live completion, scores and code practice for the students in your classes.',
    '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">' . $picker . '<a class="btn" href="' . u('/teacher/gradebook.php?export=csv&class=' . (int) $classId) . '">Download CSV</a></div>');
?>
<div class="stats">
  <div class="stat"><b><?= (int) $sum['students'] ?></b><span>Students</span></div>
  <div class="stat"><b><?= (int) $sum['avg_pct'] ?>%</b><span>Average completion</span></div>
  <div class="stat"><b><?= $sum['avg_pre'] === null ? '–' : (int) $sum['avg_pre'] . '%' ?></b><span>Avg pre-test</span></div>
  <div class="stat"><b><?= $sum['avg_post'] === null ? '–' : (int) $sum['avg_post'] . '%' ?></b><span>Avg post-test</span></div>
  <div class="stat"><b><?= $sum['avg_growth'] === null ? '–' : (((int) $sum['avg_growth'] > 0 ? '+' : '') . (int) $sum['avg_growth']) ?></b><span>Avg growth (points)</span></div>
  <div class="stat"><b><?= (int) $sum['finished'] ?></b><span>Finished the module</span></div>
</div>

<div class="card" style="margin-bottom:14px">
  <p class="card-h">Completion by section</p>
  <?php $i = 0; foreach ($sections as $sid => $s): $i++; $st = $gb['secStats'][$sid]; ?>
    <div class="mrow">
      <span class="mn"><?= $i ?></span>
      <span class="mt"><?= e($s['name']) ?></span>
      <span class="mbar"><i style="width:<?= (int) $st['pct'] ?>%"></i></span>
      <span class="mv"><?= (int) $st['pct'] ?>% (<?= (int) $st['done'] ?>/<?= (int) $sum['students'] ?>)</span>
      <span class="mv"><?php if ($st['avg'] !== null): ?><span class="chip <?= scoreClass($st['avg']) ?>">avg <?= (int) $st['avg'] ?>%</span><?php endif; ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
  <input class="input" id="gb-filter" style="max-width:280px" placeholder="Search students…" aria-label="Search students">
  <label class="check" style="margin-left:auto"><input type="checkbox" id="gb-auto"> Refresh every 30 seconds</label>
</div>

<div class="card" style="padding:8px 8px 4px;overflow-x:auto">
<table class="tbl" id="gb-table">
  <thead><tr>
    <th>Student</th><th>Progress</th>
    <?php if ($preId !== null): ?><th title="<?= e($sections[$preId]['name']) ?>">Pre-test</th><?php endif; ?>
    <?php foreach ($units as $u): ?><th title="<?= e($u['name']) ?>: lesson done · code challenges · assessment score"><?= e($u['label']) ?></th><?php endforeach; ?>
    <th>Assess. avg</th>
    <?php if ($postId !== null): ?><th title="<?= e($sections[$postId]['name']) ?>">Post-test</th><th>Growth</th><?php endif; ?>
    <th title="Practices skipped and not yet solved">Skipped</th>
    <th>Last activity</th>
  </tr></thead>
  <tbody>
  <?php if (!$gb['students']): ?>
    <tr><td colspan="10" style="padding:22px;color:#8AA37A"><?= $myClasses ? 'No students in ' . e($classRow ? $classRow['name'] : 'your classes') . ' yet. Share the class join code or add students on the class page.' : 'Create a class first. Students appear here once they are in one of your classes.' ?> <a href="<?= u('/teacher/classes.php') ?>">Go to Classes</a></td></tr>
  <?php endif; ?>
  <?php foreach ($gb['students'] as $st): ?>
    <tr data-name="<?= e(strtolower($st['name'] . ' ' . $st['email'])) ?>">
      <td><a href="<?= u('/teacher/student.php?id=' . $st['id'] . '&class=' . (int) $classId) ?>"><b><?= e($st['name']) ?></b></a>
        <?php if ($st['status'] !== 'active'): ?><span class="pill pill-gray" style="margin-left:4px">suspended</span><?php endif; ?>
        <div class="hint" style="margin:1px 0 0"><?= e($st['email']) ?></div></td>
      <td style="min-width:130px"><div class="mbar" style="display:block"><i style="width:<?= (int) $st['pct'] ?>%"></i></div>
        <span class="hint" style="margin:2px 0 0;display:block"><?= (int) $st['done'] ?>/<?= (int) $st['total'] ?> · <?= (int) $st['pct'] ?>%</span></td>
      <?php if ($preId !== null): ?><td><?= $st['pre'] === null ? '<span class="chip sc-none">–</span>' : '<span class="chip ' . scoreClass($st['pre']) . '">' . (int) $st['pre'] . '%</span>' ?></td><?php endif; ?>
      <?php foreach ($units as $u):
        $lessonDone = isset($st['progress'][$u['lesson']]);
        $pr  = $st['practice'][$u['lesson']] ?? 0;
        $pt  = $totals[$u['lesson']] ?? 0;
        $as  = $u['assess'] !== null && isset($st['progress'][$u['assess']]) ? $st['progress'][$u['assess']]['score'] : null; ?>
        <td style="white-space:nowrap"><span class="dot <?= $lessonDone ? 'ok' : '' ?>" title="<?= $lessonDone ? 'Lesson done' : 'Lesson not done' ?>"></span>
          <?php if ($pt > 0): ?><span class="pr" title="Code challenges solved"><?= (int) $pr ?>/<?= (int) $pt ?></span><?php endif; ?>
          <?php if ($u['assess'] !== null): ?><?= $as === null ? '<span class="chip sc-none">–</span>' : '<span class="chip ' . scoreClass($as) . '">' . (int) $as . '%</span>' ?><?php endif; ?></td>
      <?php endforeach; ?>
      <td><?= $st['assess_avg'] === null ? '<span class="chip sc-none">–</span>' : '<span class="chip ' . scoreClass($st['assess_avg']) . '">' . (int) $st['assess_avg'] . '%</span>' ?></td>
      <?php if ($postId !== null): ?>
        <td><?= $st['post'] === null ? '<span class="chip sc-none">–</span>' : '<span class="chip ' . scoreClass($st['post']) . '">' . (int) $st['post'] . '%</span>' ?></td>
        <td><?= $st['growth'] === null ? '–' : '<b style="color:' . ($st['growth'] >= 0 ? 'var(--green)' : '#B3402A') . '">' . ($st['growth'] > 0 ? '+' : '') . (int) $st['growth'] . '</b>' ?></td>
      <?php endif; ?>
      <td><?= $st['skipped'] > 0 ? '<span class="chip sc-low" title="Practices skipped and not yet solved">' . (int) $st['skipped'] . ' skipped</span>' : '<span class="hint" style="margin:0">–</span>' ?></td>
      <td class="hint" style="white-space:nowrap"><?= e(agoLabel($st['last'])) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint">Topic columns show: lesson done (dot), code challenges solved, and the formative assessment score. Scores are green at or above the <?= (int) setting('pass_score') ?>% pass mark. Code-challenge counts are reported by the student's browser, so treat them as a guide rather than proof.</p>

<script>
(function () {
  var f = document.getElementById('gb-filter'), rows = document.querySelectorAll('#gb-table tbody tr[data-name]');
  f.addEventListener('input', function () {
    var q = f.value.trim().toLowerCase();
    rows.forEach(function (r) { r.style.display = r.getAttribute('data-name').indexOf(q) === -1 ? 'none' : ''; });
  });
  var auto = document.getElementById('gb-auto'), t = null;
  try { auto.checked = sessionStorage.getItem('gb-auto') === '1'; } catch (e) {}
  function arm() { if (t) clearInterval(t); if (auto.checked) t = setInterval(function () { location.reload(); }, 30000); }
  auto.addEventListener('change', function () { try { sessionStorage.setItem('gb-auto', auto.checked ? '1' : '0'); } catch (e) {} arm(); });
  arm();
})();
</script>
<?php teacherEnd();
