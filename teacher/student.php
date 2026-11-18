<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';

$uid = (int) ($_GET['id'] ?? 0);
$tid = (int) sessionUser()['id'];
$cls = (int) ($_GET['class'] ?? 0);
$gb  = teacherCanSee($tid, $uid) ? gradebookData([$uid]) : ['students' => []];
$me  = null;
foreach ($gb['students'] as $s) { if ($s['id'] === $uid) { $me = $s; break; } }
if ($me === null) { flashSet('error', 'That student was not found.'); redirect(u('/teacher/gradebook.php')); }

$sections = $gb['sections'];
$totals   = $gb['practiceTotals'];
$units    = $gb['units'];

teacherStart($me['name'], 'gradebook');
pageHead($me['name'], $me['email'] . ' · last activity ' . agoLabel($me['last']),
    '<a class="btn" href="' . u('/teacher/gradebook.php?class=' . $cls) . '">← Gradebook</a>');
?>
<div class="stats">
  <div class="stat"><b><?= (int) $me['pct'] ?>%</b><span>Complete (<?= (int) $me['done'] ?>/<?= (int) $me['total'] ?>)</span></div>
  <div class="stat"><b><?= $me['pre'] === null ? '–' : (int) $me['pre'] . '%' ?></b><span>Pre-test</span></div>
  <div class="stat"><b><?= $me['assess_avg'] === null ? '–' : (int) $me['assess_avg'] . '%' ?></b><span>Assessment average</span></div>
  <div class="stat"><b><?= $me['post'] === null ? '–' : (int) $me['post'] . '%' ?></b><span>Post-test</span></div>
  <div class="stat"><b><?= $me['growth'] === null ? '–' : (($me['growth'] > 0 ? '+' : '') . (int) $me['growth']) ?></b><span>Growth (points)</span></div>
</div>

<?php if ($units): ?>
<div class="card" style="margin-bottom:14px">
  <p class="card-h">Progress across the topics</p>
  <div class="grid2">
  <?php foreach ($units as $u):
    $lessonDone = isset($me['progress'][$u['lesson']]);
    $pr = $me['practice'][$u['lesson']] ?? 0; $pt = $totals[$u['lesson']] ?? 0;
    $as = $u['assess'] !== null && isset($me['progress'][$u['assess']]) ? $me['progress'][$u['assess']]['score'] : null; ?>
    <div class="tcard">
      <b><?= e($u['name']) ?></b>
      <div class="trow"><span>Lesson</span><span class="pill <?= $lessonDone ? 'pill-green' : 'pill-gray' ?>"><?= $lessonDone ? 'Done' : 'Not yet' ?></span></div>
      <?php if ($pt > 0): ?>
      <div class="trow"><span>Code challenges</span><span><b><?= (int) $pr ?>/<?= (int) $pt ?></b></span></div>
      <div class="mbar" style="display:block"><i style="width:<?= (int) round($pr / $pt * 100) ?>%"></i></div>
      <?php endif; ?>
      <?php if ($u['assess'] !== null): ?>
      <div class="trow"><span><?= e($sections[$u['assess']]['name']) ?></span><?= $as === null ? '<span class="chip sc-none">not taken</span>' : '<span class="chip ' . scoreClass($as) . '">' . (int) $as . '%</span>' ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php $effort = practiceStatsFor($uid); $titles = practiceTitleMap(); ?>
<div class="card" style="margin-bottom:14px;padding:8px 8px 4px;overflow-x:auto">
  <p class="card-h" style="padding:8px 12px 0">Practice effort</p>
  <?php if (!$effort): ?><p class="hint" style="padding:0 12px 8px;margin:4px 0">No practice activity recorded yet. This fills in as the student works through the lessons.</p><?php else: ?>
  <table class="tbl">
    <thead><tr><th>Practice</th><th>Status</th><th title="Times the student pressed Run">Runs</th><th title="Runs that did not give the right output">Wrong runs</th><th title="Card-game flips and wrong matches">Card game</th><th>Time</th></tr></thead>
    <tbody>
    <?php foreach ($effort as $r):
        $info = $titles[$r['challenge_id']] ?? ['title' => $r['challenge_id'], 'section' => '', 'round' => false];
        $hard = $r['fails'] >= 5 || $r['misses'] >= 15 || ($r['skipped'] && !$r['solved']);
        $status = $r['solved'] ? '<span class="pill pill-green">Solved</span>' : ($r['skipped'] ? '<span class="pill pill-brown">Skipped</span>' : '<span class="pill pill-gray">In progress</span>'); ?>
      <tr<?= $hard ? ' style="background:#FFF8E6"' : '' ?>>
        <td><b><?= e(preg_replace('/ · Practice$/', '', $info['title'])) ?></b> <span class="hint" style="margin:0"><?= $info['round'] ? '· card game + practice' : '· try it yourself' ?><?= $info['section'] !== '' ? ' · ' . e($info['section']) : '' ?></span></td>
        <td><?= $status ?></td>
        <td><?= (int) $r['runs'] ?></td>
        <td><?= (int) $r['fails'] > 0 ? '<b style="color:' . ($r['fails'] >= 5 ? '#B3402A' : 'inherit') . '">' . (int) $r['fails'] . '</b>' : '0' ?></td>
        <td><?= $info['round'] ? (int) $r['flips'] . ' flips, ' . (int) $r['misses'] . ' misses' : '–' ?></td>
        <td><?= e(niceDuration((int) $r['seconds'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="hint" style="padding:0 12px 8px">Highlighted rows: 5+ wrong runs, 15+ wrong card matches, or skipped and not yet solved.</p>
  <?php endif; ?>
</div>

<div class="card" style="padding:8px 8px 4px;overflow-x:auto">
<table class="tbl">
  <thead><tr><th style="width:44px">#</th><th>Section</th><th>Type</th><th>Status</th><th>Score</th><th>Code challenges</th><th>Completed</th></tr></thead>
  <tbody>
  <?php $i = 0; foreach ($sections as $sid => $s): $i++; $pill = getSectionTypePill($s['type']); $p = $me['progress'][$sid] ?? null; ?>
    <tr>
      <td style="font-weight:800;color:var(--brown)"><?= $i ?></td>
      <td><b><?= e($s['name']) ?></b></td>
      <td><span class="pill" style="background:<?= $pill['bg'] ?>;color:<?= $pill['color'] ?>"><?= e($pill['label']) ?></span></td>
      <td><span class="pill <?= $p ? 'pill-green' : 'pill-gray' ?>"><?= $p ? 'Completed' : 'Not yet' ?></span></td>
      <td><?= ($p && $p['score'] !== null) ? '<span class="chip ' . scoreClass($p['score']) . '">' . (int) $p['score'] . '%</span>' : '–' ?></td>
      <td><?= ($s['type'] === 'lesson' && ($totals[$sid] ?? 0) > 0) ? (int) ($me['practice'][$sid] ?? 0) . '/' . (int) $totals[$sid] : '–' ?></td>
      <td class="hint" style="margin:0"><?= $p ? e(niceDate($p['at'])) : '–' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint">Scores show the student's recorded result for each quiz and assessment. A retake only replaces the score when it reaches the pass mark.</p>
<?php teacherEnd();
