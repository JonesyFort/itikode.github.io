<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/teacher_layout.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$types = ['lesson' => 'Lesson (built with simple blocks)', 'quiz' => 'Quiz (scored, e.g. pre/post-test)', 'assessment' => 'Formative assessment (scored)', 'evaluation' => 'Evaluation / survey (not scored)'];

$sec = ['id' => 0, 'name' => '', 'title' => '', 'type' => 'lesson', 'duration' => '~15 mins', 'direction' => '', 'html' => '', 'blocks' => null, 'published' => 0];
$questions = [];
if ($id) {
    $st = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $st->execute([$id]);
    $sec = $st->fetch() ?: redirect(u('/teacher/sections.php'));
    $questions = getSectionContent($id)['questions'];
}

// Lesson blocks: stored blocks, else the built-in design's blocks, else a blank lesson.
$blocks = lbFromJson($sec['blocks'] ?? null);
$notice = '';
if ($id && $sec['type'] === 'lesson' && !$blocks['parts'] && trim((string) $sec['html']) !== '') {
    $seed = lbSeedBlocks($id);
    if ($seed !== null) {
        $blocks = lbFromJson(json_encode($seed));
        $notice = 'builtin';
    } else {
        $notice = 'custom';
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sec['name']      = trim((string) ($_POST['name'] ?? ''));
    $sec['title']     = trim((string) ($_POST['title'] ?? ''));
    $sec['type']      = array_key_exists($_POST['type'] ?? '', $types) ? $_POST['type'] : 'lesson';
    $sec['duration']  = trim((string) ($_POST['duration'] ?? ''));
    $sec['direction'] = trim((string) ($_POST['direction'] ?? ''));
    $sec['published'] = !empty($_POST['published']) ? 1 : 0;

    $blocks = lbFromPost($_POST);
    $notice = '';

    $questions = [];
    if ($sec['type'] !== 'lesson') {
        foreach ((array) ($_POST['q'] ?? []) as $q) {
            $text = trim((string) ($q['text'] ?? ''));
            $opts = []; $correct = 0; $want = (int) ($q['correct'] ?? 0);
            foreach (array_values((array) ($q['options'] ?? [])) as $oi => $o) {
                $o = trim((string) $o);
                if ($o === '') continue;
                if ($oi === $want) $correct = count($opts);
                $opts[] = $o;
            }
            if ($text === '' && !$opts) continue;
            $questions[] = ['part' => trim((string) ($q['part'] ?? '')), 'text' => $text, 'options' => $opts, 'correct' => $correct,
                            'explanation' => trim((string) ($q['explanation'] ?? ''))];
        }
    }

    if ($sec['name'] === '')  $errors[] = 'Give the section a short name (shown in the sidebar).';
    if ($sec['title'] === '') $errors[] = 'Give the section a page title.';
    if ($sec['type'] === 'lesson') {
        foreach (lbValidate($blocks, $sec['title']) as $er) { if ($er !== 'Give the lesson a title.' || $sec['title'] !== '') $errors[] = $er; }
    } else {
        if (!$questions) $errors[] = 'Add at least one question.';
        foreach ($questions as $i => $q) {
            if ($q['text'] === '') $errors[] = 'Question ' . ($i + 1) . ' has no text.';
            if (count($q['options']) < 2) $errors[] = 'Question ' . ($i + 1) . ' needs at least two answer options.';
        }
    }

    if (!$errors) {
        $db->beginTransaction();
        if ($id) {
            $db->prepare("UPDATE sections SET name=?, title=?, type=?, duration=?, direction=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
               ->execute([$sec['name'], $sec['title'], $sec['type'], $sec['duration'], $sec['direction'], $sec['published'], $id]);
        } else {
            $pos = (int) $db->query("SELECT COALESCE(MAX(position),0)+1 FROM sections")->fetchColumn();
            $db->prepare("INSERT INTO sections (position, name, title, type, duration, direction, html, published) VALUES (?,?,?,?,?,?,'',?)")
               ->execute([$pos, $sec['name'], $sec['title'], $sec['type'], $sec['duration'], $sec['direction'], $sec['published']]);
            $id = (int) $db->lastInsertId();
        }
        if ($sec['type'] === 'lesson') {
            $html = lbCompile($blocks, $id, $sec['title'], $sec['duration']);
            $db->prepare("UPDATE sections SET html = ?, blocks = ? WHERE id = ?")
               ->execute([$html, json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);
        } else {
            $db->prepare("UPDATE sections SET html = '', blocks = NULL WHERE id = ?")->execute([$id]);
        }
        $db->prepare("DELETE FROM questions WHERE section_id = ?")->execute([$id]);
        $ins = $db->prepare("INSERT INTO questions (section_id, position, part, text, options, correct, explanation) VALUES (?,?,?,?,?,?,?)");
        foreach ($questions as $i => $q) {
            $ins->execute([$id, $i + 1, $q['part'], $q['text'], json_encode($q['options'], JSON_UNESCAPED_UNICODE), $q['correct'], $q['explanation']]);
        }
        $db->commit();
        auditLog(!empty($_POST['id']) ? 'content.edited' : 'content.created', $sec['name']);
        flashSet('success', 'Saved "' . $sec['name'] . '".');
        redirect(u('/teacher/section_edit.php?id=' . $id));
    }
}

if (!$questions && $sec['type'] !== 'lesson') $questions = [['part' => '', 'text' => '', 'options' => ['', '', '', ''], 'correct' => 0, 'explanation' => '']];
if (!$blocks['parts']) { $blocks['parts'] = [lbNormalizePart(lbEmptyPart())]; }

$builderData = json_encode([
    'blocks'  => $blocks,
    'accents' => LB_ACCENTS,
    'emojis'  => ['📘', '🧭', '🏛️', '👤', '📋', '⚡', '🌳', '🔐', '🎪', '🐍', '💡', '🎯', '🧩', '🔥', '🎮', '🧪', '📝', '🚀', '🎬', '🗺️'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

teacherStart($id ? 'Edit section' : 'New section', 'sections');
pageHead($id ? 'Edit: ' . $sec['name'] : 'New section', 'Changes appear for students as soon as you save.',
    '<a class="btn" href="' . u('/teacher/sections.php') . '">← All sections</a>' . ($id ? ' <a class="btn btn-lime" target="_blank" rel="noopener" href="' . u('/section.php?id=' . $id) . '">View as student</a>' : ''));
foreach ($errors as $er) alertBox($er, 'err');
if ($notice === 'builtin') alertBox('This is one of the built-in designed lessons. The editor below holds its content; saving keeps the same design and your students\' progress.', 'info');
if ($notice === 'custom')  alertBox('This lesson was written as custom HTML, which the block editor cannot read. The fields below are empty. If you save, the old page is replaced with the blocks you build here.', 'warn');
?>
<form method="POST" action="<?= u('/teacher/section_edit.php' . ($id ? '?id=' . $id : '')) ?>" id="sec-form"><?= csrfField() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="card" style="margin-bottom:14px">
    <p class="card-h">Details</p>
    <div class="grid2">
      <div class="field"><label class="label" for="name">Sidebar name</label><input class="input" id="name" name="name" value="<?= e($sec['name']) ?>" maxlength="60" required placeholder="Topic 5: Abstraction"></div>
      <div class="field"><label class="label" for="title">Title <span class="sub">the big heading on the page</span></label><input class="input" id="title" name="title" value="<?= e($sec['title']) ?>" maxlength="120" required placeholder="Abstraction in Python"></div>
      <div class="field"><label class="label" for="duration">Estimated time</label><input class="input" id="duration" name="duration" value="<?= e($sec['duration']) ?>" maxlength="20" placeholder="~20 mins"></div>
      <div class="field"><label class="label" for="type">Type</label>
        <select class="select" id="type" name="type"><?php foreach ($types as $k => $label): ?><option value="<?= $k ?>" <?= $sec['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    </div>
    <label class="check"><input type="checkbox" name="published" value="1" <?= $sec['published'] ? 'checked' : '' ?>> Published (visible to students)</label>
  </div>

  <div id="lesson-box" style="margin-bottom:14px">
    <div id="lb-root"></div>
    <input type="hidden" name="blocks_json" id="blocks_json" value="">
    <script type="application/json" id="lb-data"><?= $builderData ?></script>
    <noscript><div class="alert alert-warn">JavaScript is needed to build lessons. Please turn it on and reload this page.</div></noscript>
  </div>

  <div id="quiz-box" style="display:none">
    <div class="card" style="margin-bottom:14px">
      <p class="card-h">Instructions shown above the questions</p>
      <input class="input" name="direction" value="<?= e($sec['direction']) ?>" maxlength="200" placeholder="Choose the letter of the best answer.">
    </div>
    <div class="card" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <p class="card-h" style="margin:0">Questions</p>
        <button type="button" class="btn btn-sm btn-lime" id="add-q">+ Add question</button>
      </div>
      <p class="hint" style="margin:-4px 0 12px" id="q-hint">Select the radio button next to the correct answer. Blank options are ignored. Pass mark: <?= (int) setting('pass_score') ?>%.</p>
      <div id="qlist">
        <?php foreach ($questions as $qi => $q): ?>
        <div class="qcard" data-q>
          <div style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
            <b class="qnum" style="color:var(--brown)"></b>
            <input class="input" style="flex:1" data-f="part" name="q[<?= $qi ?>][part]" value="<?= e($q['part']) ?>" placeholder="Part heading (optional), e.g. Part I: Multiple choice">
            <button type="button" class="btn btn-sm" data-act="up" aria-label="Move up">↑</button>
            <button type="button" class="btn btn-sm" data-act="down" aria-label="Move down">↓</button>
            <button type="button" class="btn btn-sm btn-danger" data-act="del" aria-label="Delete question">Delete</button>
          </div>
          <textarea class="textarea" style="min-height:60px" data-f="text" name="q[<?= $qi ?>][text]" placeholder="Question text"><?= e($q['text']) ?></textarea>
          <div class="opts" style="margin-top:10px">
            <?php foreach (array_pad($q['options'], 2, '') as $oi => $o): ?>
            <div class="opt-row"><input type="radio" data-f="correct" name="q[<?= $qi ?>][correct]" value="<?= $oi ?>" <?= $oi === $q['correct'] ? 'checked' : '' ?> title="Correct answer" aria-label="Correct answer">
              <input class="input" data-f="options" name="q[<?= $qi ?>][options][]" value="<?= e($o) ?>" placeholder="Option text">
              <button type="button" class="btn btn-sm" data-act="rm-opt" aria-label="Remove option">×</button></div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-sm" data-act="add-opt" style="margin:2px 0 10px">+ Add option</button>
          <input class="input" data-f="explanation" name="q[<?= $qi ?>][explanation]" value="<?= e($q['explanation']) ?>" placeholder="Explanation shown after submitting (optional)">
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div style="position:sticky;bottom:0;background:linear-gradient(transparent,var(--cream) 30%);padding:18px 0 4px;display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn btn-primary" type="submit">Save section</button>
    <button class="btn" type="submit" id="prev-btn" formaction="<?= u('/teacher/preview.php') ?>" formtarget="_blank">Preview lesson (not saved)</button>
    <a class="btn" href="<?= u('/teacher/sections.php') ?>">Cancel</a>
  </div>
</form>

<script src="<?= u('/assets/builder.js') ?>"></script>
<script>
(function () {
  var type = document.getElementById('type'), lessonBox = document.getElementById('lesson-box'), quizBox = document.getElementById('quiz-box');
  var prevBtn = document.getElementById('prev-btn');
  var qlist = document.getElementById('qlist'), qHint = document.getElementById('q-hint');

  function sync() {
    var isLesson = type.value === 'lesson';
    lessonBox.style.display = isLesson ? '' : 'none'; quizBox.style.display = isLesson ? 'none' : '';
    prevBtn.style.display = isLesson ? '' : 'none';
    qHint.style.display = type.value === 'evaluation' ? 'none' : '';
    document.querySelectorAll('#qlist input[type=radio]').forEach(function (r) { r.style.visibility = type.value === 'evaluation' ? 'hidden' : ''; });
  }
  type.addEventListener('change', sync);

  /* ── quiz questions ── */
  function renumber() {
    var cards = [].slice.call(qlist.querySelectorAll('[data-q]'));
    // remember each card's chosen answer first: renaming radios into a group another card still uses would untick them
    var chosen = cards.map(function (card) {
      var radios = [].slice.call(card.querySelectorAll('input[type=radio]'));
      var at = radios.findIndex(function (r) { return r.checked; });
      return at < 0 ? 0 : at;
    });
    cards.forEach(function (card, i) {
      card.querySelector('.qnum').textContent = 'Q' + (i + 1);
      card.querySelectorAll('[data-f]').forEach(function (el) {
        el.name = 'q[' + i + '][' + el.getAttribute('data-f') + ']' + (el.getAttribute('data-f') === 'options' ? '[]' : '');
      });
    });
    cards.forEach(function (card, i) {
      card.querySelectorAll('input[type=radio]').forEach(function (r, oi) { r.value = oi; r.checked = (oi === chosen[i]); });
    });
  }
  function optRow(checked) {
    var d = document.createElement('div'); d.className = 'opt-row';
    d.innerHTML = '<input type="radio" data-f="correct" title="Correct answer" aria-label="Correct answer"' + (checked ? ' checked' : '') + '>' +
      '<input class="input" data-f="options" placeholder="Option text"><button type="button" class="btn btn-sm" data-act="rm-opt" aria-label="Remove option">×</button>';
    return d;
  }
  function newQ() {
    var d = document.createElement('div'); d.className = 'qcard'; d.setAttribute('data-q', '');
    d.innerHTML = '<div style="display:flex;gap:8px;margin-bottom:8px;align-items:center"><b class="qnum" style="color:var(--brown)"></b>' +
      '<input class="input" style="flex:1" data-f="part" placeholder="Part heading (optional)">' +
      '<button type="button" class="btn btn-sm" data-act="up" aria-label="Move up">↑</button><button type="button" class="btn btn-sm" data-act="down" aria-label="Move down">↓</button>' +
      '<button type="button" class="btn btn-sm btn-danger" data-act="del" aria-label="Delete question">Delete</button></div>' +
      '<textarea class="textarea" style="min-height:60px" data-f="text" placeholder="Question text"></textarea><div class="opts" style="margin-top:10px"></div>' +
      '<button type="button" class="btn btn-sm" data-act="add-opt" style="margin:2px 0 10px">+ Add option</button>' +
      '<input class="input" data-f="explanation" placeholder="Explanation shown after submitting (optional)">';
    var o = d.querySelector('.opts'); for (var i = 0; i < 4; i++) o.appendChild(optRow(i === 0));
    return d;
  }
  document.getElementById('add-q').addEventListener('click', function () { qlist.appendChild(newQ()); renumber(); sync(); qlist.lastChild.querySelector('textarea').focus(); });
  qlist.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var card = b.closest('[data-q]'), act = b.getAttribute('data-act');
    if (act === 'del') { if (qlist.children.length > 1 || confirm('Remove the last question?')) card.remove(); }
    else if (act === 'up' && card.previousElementSibling) qlist.insertBefore(card, card.previousElementSibling);
    else if (act === 'down' && card.nextElementSibling) qlist.insertBefore(card.nextElementSibling, card);
    else if (act === 'add-opt') { card.querySelector('.opts').appendChild(optRow(false)); sync(); }
    else if (act === 'rm-opt') { var opts = card.querySelector('.opts'); if (opts.children.length > 2) b.parentNode.remove(); }
    renumber();
  });

  document.getElementById('sec-form').addEventListener('submit', function () { renumber(); });
  renumber(); sync();
})();
</script>
<?php teacherEnd();
