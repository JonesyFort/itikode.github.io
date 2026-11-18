<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/shell.php';
require_once __DIR__ . '/includes/analytics.php';

requireLogin('/login.php');

$currentUser = currentUser();
$progress    = getUserProgress((int) $currentUser['id']);
$role        = $currentUser['role'];
$sections    = getAllSections();
$isStaff     = $role !== 'student';

$id = (int) ($_GET['id'] ?? (array_key_first($sections) ?? 1));
// teachers may preview unpublished drafts
if ($role === 'teacher' && !isset($sections[$id])) { $sections = getAllSections(true); }
if (!isset($sections[$id])) redirect(u('/index.php'));
if (!canAccess($id, $progress, $role) && $role !== 'admin') { flashSet('error', 'Complete previous sections first.'); redirect(u('/index.php')); }

$sec          = $sections[$id];
$sectionCount = count(getAllSections());
$position     = (int) array_search($id, array_keys($sections), true) + 1;
$content      = getSectionContent($id);
$type         = $content['type'];
$questions    = $content['questions'] ?? [];
$isGraded     = in_array($type, ['quiz', 'assessment'], true);
[$prev, $next] = siblingSections($id);
$feedback     = null;
$activeSectionId = $id;

// The first quiz in the module is the pre-test; later quizzes are post-tests.
$quizIds     = array_keys(array_filter(getAllSections(), fn($s) => $s['type'] === 'quiz'));
$firstQuizId = $quizIds[0] ?? null;
$isPre       = ($type === 'quiz' && $id === $firstQuizId);
$preScore    = ($type === 'quiz' && !$isPre && $firstQuizId !== null && is_int($progress[$firstQuizId] ?? null)) ? $progress[$firstQuizId] : null;

// ── Retake: forget the locked answers and start over ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['retake'])) {
    unset($_SESSION['qz'][$id], $_SESSION['qz_result'][$id]);
    redirect(u('/section.php?id=' . $id));
}

// ── POST handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $do = (string) ($_POST['do'] ?? 'submit');

    // 0) A student solved a "Try it yourself" challenge (reported by the lesson page, used by the gradebook).
    if ($type === 'lesson' && $do === 'practice') {
        header('Content-Type: application/json; charset=utf-8');
        $cid = (string) ($_POST['challenge'] ?? '');
        $ok  = preg_match('/^[A-Za-z0-9_-]{1,60}$/', $cid) === 1 && str_contains((string) $content['html'], 'data-id="' . $cid . '"');
        if ($ok) {
            getDB()->prepare("INSERT OR IGNORE INTO practice_progress (user_id, section_id, challenge_id) VALUES (?,?,?)")
                   ->execute([(int) $currentUser['id'], $id, $cid]);
        }
        if ($ok) {   // solved: it is no longer "skipped"
            getDB()->prepare("UPDATE practice_stats SET skipped = 0 WHERE user_id = ? AND section_id = ? AND challenge_id = ?")
                   ->execute([(int) $currentUser['id'], $id, $cid]);
        }
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // 0b) How hard a practice was (reported by the lesson page; students only). Counters only ever go up.
    if ($type === 'lesson' && $do === 'stats') {
        header('Content-Type: application/json; charset=utf-8');
        $cid = (string) ($_POST['challenge'] ?? '');
        $ok  = $role === 'student' && preg_match('/^[A-Za-z0-9_-]{1,60}$/', $cid) === 1 && str_contains((string) $content['html'], 'data-id="' . $cid . '"');
        if ($ok) {
            recordPracticeStats((int) $currentUser['id'], $id, $cid, $_POST);
        }
        echo json_encode(['ok' => $ok]);
        exit;
    }

    // 1) Check one answer (called by the quiz player). The first choice for each
    //    question is locked in the session, so reloading can't be used to fish
    //    for the right answer.
    if ($isGraded && !$isStaff && $do === 'check') {
        header('Content-Type: application/json; charset=utf-8');
        $qi     = isset($_POST['qi']) ? (int) $_POST['qi'] : -1;
        $choice = isset($_POST['choice']) ? (int) $_POST['choice'] : -1;
        $q      = $questions[$qi] ?? null;
        if ($q === null || $choice < 0 || $choice >= count($q['options'])) {
            http_response_code(422);
            echo json_encode(['ok' => false]);
            exit;
        }
        if (!isset($_SESSION['qz'][$id][$qi])) { $_SESSION['qz'][$id][$qi] = $choice; }
        $chosen = (int) $_SESSION['qz'][$id][$qi];
        echo json_encode([
            'ok' => true, 'chosen' => $chosen, 'correct' => $chosen === $q['correct'],
            'correctIndex' => $q['correct'], 'explanation' => $q['explanation'],
        ]);
        exit;
    }

    // 2) Final submit of a quiz / assessment: score it and save progress.
    if ($isGraded && !$isStaff) {
        $locks = $_SESSION['qz'][$id] ?? [];
        $right = 0; $answers = [];
        foreach ($questions as $qi => $q) {
            $posted = isset($_POST['q'][$qi]) ? (int) $_POST['q'][$qi] : -1;
            $chosen = isset($locks[$qi]) ? (int) $locks[$qi] : $posted;
            $answers[$qi] = $chosen;
            if ($chosen === $q['correct']) $right++;
        }
        $count    = count($questions);
        $scorePct = $count > 0 ? (int) round($right / $count * 100) : 0;
        $passed   = $scorePct >= (int) setting('pass_score');
        if ($passed || !isset($progress[$id])) markSectionComplete((int) $currentUser['id'], $id, $scorePct);
        unset($_SESSION['qz'][$id]);
        $_SESSION['qz_result'][$id] = ['type' => $type, 'correct' => $right, 'total' => $count, 'score' => $scorePct, 'passed' => $passed, 'answers' => $answers];
        redirect(u('/section.php?id=' . $id . '&result=1'));
    }

    // 3) Course evaluation (no right or wrong answers).
    if ($type === 'evaluation') {
        $given = [];
        foreach ($questions as $qi => $q) {
            if (isset($_POST['q'][$qi])) $given[$qi] = (int) $_POST['q'][$qi];
        }
        markSectionComplete((int) $currentUser['id'], $id, null);
        $_SESSION['qz_result'][$id] = ['type' => 'evaluation', 'answers' => $given];
        redirect(u('/section.php?id=' . $id . '&result=1'));
    }
}

// Show the saved result after the redirect.
if (isset($_GET['result']) && isset($_SESSION['qz_result'][$id])) {
    $feedback = $_SESSION['qz_result'][$id];
}

// Auto-complete lessons on first visit
if ($type === 'lesson' && !isset($progress[$id])) {
    markSectionComplete((int) $currentUser['id'], $id, null);
    $progress = getUserProgress((int) $currentUser['id']);
}

// ── View helpers ─────────────────────────────────────────────────────────────
/** Escape text, turning `backticked` parts into <code>. */
function lxRich(string $t): string
{
    $parts = explode('`', $t);
    $last  = count($parts) - 1;
    $out   = '';
    foreach ($parts as $i => $p) {
        if ($i % 2 === 1 && $i < $last) $out .= '<code>' . e($p) . '</code>';
        else $out .= ($i > 0 && $i % 2 === 1 ? '`' : '') . e($p);
    }
    return $out;
}
function lxPrev(?int $prev): string
{
    return $prev !== null
        ? '<a class="lx-btn ghost" href="' . u('/section.php?id=' . $prev) . '">' . icon('chevleft', 15) . ' Previous</a>'
        : '<a class="lx-btn ghost" href="' . u('/index.php') . '">' . icon('chevleft', 15) . ' Dashboard</a>';
}
function lxNext(string $path, string $label, string $extra = ''): string
{
    return '<a class="lx-btn' . ($extra !== '' ? ' ' . $extra : '') . '" href="' . u($path) . '">' . e($label) . ' ' . icon('arrowright', 15) . '</a>';
}

$pill     = getSectionTypePill($sec['type']);
$kicker   = icon('clock', 12) . ' Section ' . $position . ' · ' . e($pill['label']) . ' · ' . e($sec['duration']);
$editBtn  = $role === 'teacher' ? '<a class="lx-btn lime" href="' . u('/teacher/section_edit.php?id=' . $id) . '">Edit this section</a>' : '';
$direction = (string) ($content['direction'] ?? '');

shellStart($sec['name'], $sec['name']);
?>
<div class="lx-page">

<?php if ($type === 'lesson'): ?>
  <?php $ownHero = str_contains($content['html'], 'class="lx-hero"'); // designed lessons include their own hero ?>
  <?php if (!$ownHero) lxHero($kicker, $content['title'], '', '', $editBtn); ?>
  <?php if ($ownHero && $editBtn !== ''): ?><div style="display:flex;justify-content:flex-end;margin-bottom:12px"><?= $editBtn ?></div><?php endif; ?>
  <?php
    $solvedStmt = getDB()->prepare("SELECT challenge_id FROM practice_progress WHERE user_id = ? AND section_id = ?");
    $solvedStmt->execute([(int) $currentUser['id'], $id]);
    $solvedIds = json_encode($solvedStmt->fetchAll(PDO::FETCH_COLUMN));
    $statStmt = getDB()->prepare("SELECT challenge_id, runs, fails, flips, misses, seconds, skipped FROM practice_stats WHERE user_id = ? AND section_id = ?");
    $statStmt->execute([(int) $currentUser['id'], $id]);
    $statMap = [];
    foreach ($statStmt->fetchAll() as $r) {
        $statMap[$r['challenge_id']] = ['runs' => (int) $r['runs'], 'fails' => (int) $r['fails'], 'flips' => (int) $r['flips'], 'misses' => (int) $r['misses'], 'seconds' => (int) $r['seconds'], 'skipped' => (int) $r['skipped']];
    }
    $statsJson = json_encode((object) $statMap);
  ?>
  <div class="lx-lesson<?= $ownHero ? ' own' : '' ?>" data-practice-url="<?= e(u('/section.php?id=' . $id)) ?>" data-csrf="<?= e(csrfToken()) ?>" data-solved="<?= e((string) $solvedIds) ?>" data-stats="<?= e((string) $statsJson) ?>"><?= $content['html'] ?></div>
  <div class="lx-navrow">
    <?= lxPrev($prev) ?>
    <?php if ($next !== null && ($isStaff || canAccess($next, $progress, $role))): ?>
      <?= lxNext('/section.php?id=' . $next, 'Next', 'lx-lessonnext') ?>
    <?php elseif ($next === null): ?>
      <?= lxNext('/index.php', 'Back to Dashboard') ?>
    <?php endif; ?>
  </div>

<?php elseif ($isGraded && $isStaff): ?>
  <?php lxHero($kicker, $content['title'], $direction ?: 'Choose the letter of the best answer.', '', $editBtn); ?>
  <div class="lx-staffbar">
    <?= icon('grad', 20) ?>
    <div><b><?= e($content['title']) ?>: <?= e(ucfirst($role)) ?> view</b><span>All questions with the correct answers shown · <?= count($questions) ?> items. Students answer one question at a time.</span></div>
  </div>
  <?php $currentPart = null; foreach ($questions as $qi => $q):
    if (!empty($q['part']) && $q['part'] !== $currentPart): $currentPart = $q['part']; ?>
    <div class="lx-part"><?= e($q['part']) ?></div>
  <?php endif; ?>
  <div class="lx-kcard" style="<?= $qi === 0 ? 'margin-top:20px' : '' ?>">
    <p><?= $qi + 1 ?>. <?= lxRich($q['text']) ?></p>
    <?php foreach ($q['options'] as $oi => $opt): $c = ($oi === $q['correct']); ?>
    <div class="lx-kopt<?= $c ? ' is-correct' : '' ?>">
      <i><?= chr(65 + $oi) ?></i><span><?= lxRich($opt) ?></span><?php if ($c): ?><?= icon('checkcircle', 15) ?><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!empty($q['explanation'])): ?><div class="lx-expl"><b>Explanation: </b><?= e($q['explanation']) ?></div><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="lx-keybox">
    <h4>Answer key</h4>
    <div class="lx-keys">
      <?php foreach ($questions as $qi => $q): ?><div><small>No. <?= $qi + 1 ?></small><b><?= chr(65 + $q['correct']) ?></b></div><?php endforeach; ?>
    </div>
  </div>
  <div class="lx-navrow">
    <?= lxPrev($prev) ?>
    <?php if ($next !== null): ?><?= lxNext('/section.php?id=' . $next, 'Next') ?><?php endif; ?>
  </div>

<?php elseif ($isGraded && $feedback !== null && ($feedback['type'] ?? '') === $type): ?>
  <?php
    $score    = (int) $feedback['score'];
    $stars    = $score >= 80 ? 3 : ($score >= 60 ? 2 : 1);
    $passed   = (bool) $feedback['passed'];
    $answers  = $feedback['answers'];
    $canRetake = $type === 'assessment' || ($type === 'quiz' && !$isPre && !$passed);
    $sub = $type === 'assessment'
        ? 'Here is how you did. Look over any misses below before you move on.'
        : ($isPre ? 'This is your starting point. There is no pass or fail; it just shows what to focus on.'
                  : "See how far you've come since the start of the module.");
    lxHero(icon('clock', 12) . ' Section ' . $position . ' · ' . e($pill['label']) . ' complete', $content['title'] . ' Results', $sub);
    lxStage('🏆', 'Your score', $feedback['correct'] . ' of ' . $feedback['total'] . ' correct', true);
  ?>
  <div class="lx-rgrid">
    <div class="lx-score">
      <div class="lx-trophy"><?= icon('trophy', 36) ?></div>
      <p class="lx-pct"><?= $score ?><small>%</small></p>
      <div class="lx-stars" aria-label="<?= $stars ?> of 3 stars">
        <?php for ($s = 1; $s <= 3; $s++): ?><span class="<?= $s <= $stars ? 'on' : '' ?>"><?= icon('star', 22) ?></span><?php endfor; ?>
      </div>

      <?php if ($preScore !== null): ?>
      <div class="lx-growth">
        <h4><?= icon('trend', 13) ?> Growth</h4>
        <?php foreach ([['Pre-test', $preScore, '#C1E899'], ['Post-test', $score, '#55883B']] as [$l, $v, $c]): ?>
        <div class="r"><div><span><?= e($l) ?></span><span><?= (int) $v ?>%</span></div><div class="tr"><i style="width:<?= (int) $v ?>%;background:<?= $c ?>"></i></div></div>
        <?php endforeach; ?>
        <p style="margin:12px 0 0;font-size:12.5px;line-height:1.5;color:var(--ink)"><?= $score > $preScore ? 'Great growth! You gained ' . ($score - $preScore) . ' points.' : 'Review the topics above to strengthen your OOP foundation.' ?></p>
      </div>
      <?php elseif ($type === 'assessment'): ?>
      <p class="lx-note"><?= $passed ? 'Great work! You are meeting the ' . (int) setting('pass_score') . '% target.' : 'Consider reviewing this topic before moving on. The target is ' . (int) setting('pass_score') . '%.' ?></p>
      <?php elseif ($isPre): ?>
      <p class="lx-note">We will compare this with your post-test at the end to show how much you have learned.</p>
      <?php endif; ?>

      <div class="lx-actions-col">
        <?= $next !== null
            ? '<a class="lx-btn block" href="' . u('/section.php?id=' . $next) . '">Continue ' . icon('arrowright', 15) . '</a>'
            : '<a class="lx-btn block" href="' . u('/index.php') . '">Finish Module ' . icon('arrowright', 15) . '</a>' ?>
        <?php if ($canRetake): ?><a class="lx-btn ghost block" href="<?= u('/section.php?id=' . $id . '&retake=1') ?>">Retake</a><?php endif; ?>
      </div>
    </div>

    <div class="lx-rev">
      <span class="lx-tag">Answer review</span>
      <div class="lx-rev-list">
        <?php foreach ($questions as $qi => $q):
          $ok = ($answers[$qi] ?? -1) === $q['correct']; ?>
        <div class="lx-rev-i<?= $ok ? '' : ' bad' ?>">
          <?= icon($ok ? 'checkcircle' : 'xcircle', 16) ?>
          <div style="min-width:0">
            <p><?= $qi + 1 ?>. <?= lxRich($q['text']) ?></p>
            <?php if (!$ok): ?>
              <?php if (isset($q['options'][$answers[$qi] ?? -1])): ?><div class="sub">You chose: <b><?= lxRich($q['options'][$answers[$qi]]) ?></b></div><?php endif; ?>
              <div class="sub">Correct: <b><?= lxRich($q['options'][$q['correct']] ?? '') ?></b></div>
              <?php if (!empty($q['explanation'])): ?><div class="why"><?= e($q['explanation']) ?></div><?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

<?php elseif ($isGraded): ?>
  <?php
    $count = count($questions);
    $stageTitle = $type === 'assessment' ? 'Check your understanding' : ($isPre ? 'What do you know so far?' : 'What have you learned?');
    $resume = [];
    foreach (($_SESSION['qz'][$id] ?? []) as $qi => $chosen) {
        if (!isset($questions[$qi])) continue;
        $resume[$qi] = ['chosen' => (int) $chosen, 'correct' => (int) $chosen === $questions[$qi]['correct'],
                        'correctIndex' => $questions[$qi]['correct'], 'explanation' => $questions[$qi]['explanation']];
    }
    $payload = json_encode([
        'questions'  => array_map(fn($q) => ['part' => $q['part'], 'text' => $q['text'], 'options' => $q['options']], $questions),
        'resume'     => (object) $resume,
        'url'        => u('/section.php?id=' . $id),
        'stageTitle' => $stageTitle,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $aside = '<div class="lx-qn"><span>Question</span><span id="lx-count">1/' . $count . '</span></div><div class="lx-tracker" id="lx-tracker"></div>';
    lxHero($kicker, $content['title'], $direction ?: 'Pick the best answer, then press Check Answer.', $count > 0 ? $aside : '', $editBtn);
  ?>
  <?php if ($count === 0): ?>
    <div class="alert alert-info" style="margin-top:24px">This section has no questions yet.</div>
    <div class="lx-navrow"><?= lxPrev($prev) ?><?php if ($next !== null): ?><?= lxNext('/section.php?id=' . $next, 'Next') ?><?php endif; ?></div>
  <?php else: ?>
  <div id="lx-quiz">
    <?php lxStage('❓', 'Question 1 of ' . $count, $stageTitle, false, 'lx-stage'); ?>
    <div class="lx-qgrid">
      <section class="lx-qcard" aria-live="polite">
        <div><span class="lx-tag" id="lx-tag">Question 1</span></div>
        <p class="lx-qtext" id="lx-qtext"></p>
        <div class="lx-hint"><div id="lx-hint">Pick one answer, then press Check Answer.</div></div>
      </section>
      <section class="lx-ocard">
        <div class="lx-opts" id="lx-opts" role="group" aria-label="Answer choices"></div>
        <div class="lx-actions"><span class="msg" id="lx-msg" role="alert"></span><button type="button" class="lx-btn" id="lx-action" disabled>Check Answer</button></div>
      </section>
    </div>
    <form id="lx-form" method="POST" action="<?= u('/section.php?id=' . $id) ?>" hidden><?= csrfField() ?><input type="hidden" name="do" value="submit"></form>
    <script type="application/json" id="lx-data"><?= $payload ?></script>
    <script type="application/json" id="lx-csrf"><?= json_encode(csrfToken()) ?></script>
  </div>
  <noscript><div class="alert alert-warn" style="margin-top:20px">JavaScript is needed to take this quiz. Please turn it on and reload the page.</div></noscript>
  <div class="lx-navrow"><?= lxPrev($prev) ?></div>
  <?php endif; ?>

<?php elseif ($type === 'evaluation'): ?>
  <?php
    $given = $feedback['answers'] ?? [];
    $done  = $feedback !== null;
    lxHero($kicker, $content['title'], $direction ?: 'Select the option that best reflects your experience.', '', $editBtn);
  ?>
  <?php if ($done): ?>
  <div class="lx-thanks"><?= icon('checkcircle', 18) ?> Thank you for your feedback! Your evaluation has been recorded.</div>
  <?php endif; ?>
  <form method="POST" action="<?= u('/section.php?id=' . $id) ?>" style="margin-top:24px"><?= csrfField() ?><input type="hidden" name="do" value="submit">
    <?php foreach ($questions as $qi => $q): ?>
    <div class="lx-evalq">
      <p><?= $qi + 1 ?>. <?= lxRich($q['text']) ?></p>
      <div class="lx-pills" role="radiogroup">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <label><input type="radio" name="q[<?= $qi ?>]" value="<?= $oi ?>" <?= $done ? 'disabled' : '' ?> <?= ($given[$qi] ?? -1) === $oi ? 'checked' : '' ?>><span><?= e($opt) ?></span></label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="lx-navrow">
      <?= lxPrev($prev) ?>
      <?php if (!$done): ?><button type="submit" class="lx-btn">Submit Evaluation</button>
      <?php else: ?><?= lxNext('/index.php', 'Back to Dashboard') ?><?php endif; ?>
    </div>
  </form>

<?php endif; ?>

</div>
<?php shellEnd(); ?>
