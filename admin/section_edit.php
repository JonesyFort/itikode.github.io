<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';
require_once dirname(__DIR__) . '/includes/content.php';

$db = getDB();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$types = ['lesson' => 'Lesson (reading + activities)', 'quiz' => 'Quiz (scored, e.g. pre/post-test)', 'assessment' => 'Formative assessment (scored)', 'evaluation' => 'Evaluation / survey (not scored)'];

$sec = ['id' => 0, 'name' => '', 'title' => '', 'type' => 'lesson', 'duration' => '~15 mins', 'direction' => '', 'html' => '', 'published' => 0];
$questions = [];
if ($id) {
    $st = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $st->execute([$id]);
    $sec = $st->fetch() ?: redirect(u('/admin/sections.php'));
    $questions = getSectionContent($id)['questions'];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $sec['name']      = trim((string) ($_POST['name'] ?? ''));
    $sec['title']     = trim((string) ($_POST['title'] ?? ''));
    $sec['type']      = array_key_exists($_POST['type'] ?? '', $types) ? $_POST['type'] : 'lesson';
    $sec['duration']  = trim((string) ($_POST['duration'] ?? ''));
    $sec['direction'] = trim((string) ($_POST['direction'] ?? ''));
    $sec['html']      = (string) ($_POST['html'] ?? '');
    $sec['published'] = !empty($_POST['published']) ? 1 : 0;

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
    if ($sec['type'] === 'lesson' && trim(strip_tags($sec['html'], '<img><iframe><video>')) === '') $errors[] = 'A lesson needs some content.';
    if ($sec['type'] !== 'lesson') {
        if (!$questions) $errors[] = 'Add at least one question.';
        foreach ($questions as $i => $q) {
            if ($q['text'] === '') $errors[] = 'Question ' . ($i + 1) . ' has no text.';
            if (count($q['options']) < 2) $errors[] = 'Question ' . ($i + 1) . ' needs at least two answer options.';
        }
    }

    if (!$errors) {
        $db->beginTransaction();
        if ($id) {
            $db->prepare("UPDATE sections SET name=?, title=?, type=?, duration=?, direction=?, html=?, published=?, updated_at=CURRENT_TIMESTAMP WHERE id=?")
               ->execute([$sec['name'], $sec['title'], $sec['type'], $sec['duration'], $sec['direction'], $sec['html'], $sec['published'], $id]);
        } else {
            $pos = (int) $db->query("SELECT COALESCE(MAX(position),0)+1 FROM sections")->fetchColumn();
            $db->prepare("INSERT INTO sections (position, name, title, type, duration, direction, html, published) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$pos, $sec['name'], $sec['title'], $sec['type'], $sec['duration'], $sec['direction'], $sec['html'], $sec['published']]);
            $id = (int) $db->lastInsertId();
        }
        $db->prepare("DELETE FROM questions WHERE section_id = ?")->execute([$id]);
        $ins = $db->prepare("INSERT INTO questions (section_id, position, part, text, options, correct, explanation) VALUES (?,?,?,?,?,?,?)");
        foreach ($questions as $i => $q) {
            $ins->execute([$id, $i + 1, $q['part'], $q['text'], json_encode($q['options'], JSON_UNESCAPED_UNICODE), $q['correct'], $q['explanation']]);
        }
        $db->commit();
        auditLog($_POST['id'] ? 'content.edited' : 'content.created', $sec['name']);
        flashSet('success', 'Saved "' . $sec['name'] . '".');
        redirect(u('/admin/section_edit.php?id=' . $id));
    }
}

if (!$questions && $sec['type'] !== 'lesson') $questions = [['part' => '', 'text' => '', 'options' => ['', '', '', ''], 'correct' => 0, 'explanation' => '']];

adminStart($id ? 'Edit section' : 'New section', 'sections');
pageHead($id ? 'Edit: ' . $sec['name'] : 'New section', 'Changes appear for learners as soon as you save.',
    '<a class="btn" href="' . u('/admin/sections.php') . '">← All sections</a>' . ($id ? ' <a class="btn btn-lime" target="_blank" rel="noopener" href="' . u('/section.php?id=' . $id) . '">Preview</a>' : ''));
foreach ($errors as $er) alertBox($er, 'err');
?>
<form method="POST" action="<?= u('/admin/section_edit.php' . ($id ? '?id=' . $id : '')) ?>" id="sec-form"><?= csrfField() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <div class="card" style="margin-bottom:14px">
    <p class="card-h">Details</p>
    <div class="grid2">
      <div class="field"><label class="label" for="name">Sidebar name</label><input class="input" id="name" name="name" value="<?= e($sec['name']) ?>" maxlength="60" required placeholder="Topic 5: Abstraction"></div>
      <div class="field"><label class="label" for="title">Page title</label><input class="input" id="title" name="title" value="<?= e($sec['title']) ?>" maxlength="120" required placeholder="Abstraction in Python"></div>
      <div class="field"><label class="label" for="type">Type</label>
        <select class="select" id="type" name="type"><?php foreach ($types as $k => $label): ?><option value="<?= $k ?>" <?= $sec['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label" for="duration">Estimated time</label><input class="input" id="duration" name="duration" value="<?= e($sec['duration']) ?>" maxlength="20" placeholder="~20 mins"></div>
    </div>
    <label class="check"><input type="checkbox" name="published" value="1" <?= $sec['published'] ? 'checked' : '' ?>> Published (visible to learners)</label>
  </div>

  <div class="card" id="lesson-box" style="margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px">
      <p class="card-h" style="margin:0">Lesson content (HTML)</p>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <button type="button" class="btn btn-sm" data-snip="h">Heading</button>
        <button type="button" class="btn btn-sm" data-snip="p">Paragraph</button>
        <button type="button" class="btn btn-sm" data-snip="list">List</button>
        <button type="button" class="btn btn-sm" data-snip="callout">Callout</button>
        <button type="button" class="btn btn-sm" data-snip="code">Code block</button>
        <button type="button" class="btn btn-sm btn-lime" id="prev-btn">Show preview</button>
      </div>
    </div>
    <textarea class="textarea mono" id="html" name="html" rows="18" spellcheck="false"><?= e($sec['html']) ?></textarea>
    <div id="preview" style="display:none;border:2px dashed var(--lime);border-radius:12px;padding:16px;margin-top:10px;background:var(--paper)"></div>
    <p class="hint">Plain HTML is allowed. Only administrators can edit this, so scripts are not filtered.</p>
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

  <div style="position:sticky;bottom:0;background:linear-gradient(transparent,var(--cream) 30%);padding:18px 0 4px;display:flex;gap:10px">
    <button class="btn btn-primary" type="submit">Save section</button>
    <a class="btn" href="<?= u('/admin/sections.php') ?>">Cancel</a>
  </div>
</form>

<script>
(function () {
  var type = document.getElementById('type'), lesson = document.getElementById('lesson-box'), quiz = document.getElementById('quiz-box');
  var qlist = document.getElementById('qlist'), qHint = document.getElementById('q-hint');
  function sync() {
    var isLesson = type.value === 'lesson';
    lesson.style.display = isLesson ? '' : 'none'; quiz.style.display = isLesson ? 'none' : '';
    qHint.style.display = type.value === 'evaluation' ? 'none' : '';
    document.querySelectorAll('#qlist input[type=radio]').forEach(function (r) { r.style.visibility = type.value === 'evaluation' ? 'hidden' : ''; });
  }
  type.addEventListener('change', sync);

  function renumber() {
    qlist.querySelectorAll('[data-q]').forEach(function (card, i) {
      card.querySelector('.qnum').textContent = 'Q' + (i + 1);
      card.querySelectorAll('[data-f]').forEach(function (el) {
        el.name = 'q[' + i + '][' + el.getAttribute('data-f') + ']' + (el.getAttribute('data-f') === 'options' ? '[]' : '');
      });
      card.querySelectorAll('input[type=radio]').forEach(function (r, oi) { r.value = oi; });
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
  document.getElementById('sec-form').addEventListener('submit', renumber);
  renumber(); sync();

  // HTML helpers
  var ta = document.getElementById('html'), pv = document.getElementById('preview'), pb = document.getElementById('prev-btn');
  var snips = {
    h: '<h2 style="font-size:18px;font-weight:900;color:#2D4A1C;margin:18px 0 8px;">Heading</h2>\n',
    p: '<p style="color:#3A5228;font-size:13px;line-height:1.7;margin-bottom:12px;">Write your text here.</p>\n',
    list: '<ul style="padding-left:18px;color:#3A5228;font-size:13px;line-height:1.8;">\n  <li>First point</li>\n  <li>Second point</li>\n</ul>\n',
    callout: '<div style="background:#E6F0DC;border:2px solid #C1E899;border-radius:16px;padding:18px;margin:12px 0;">\n  <div style="font-size:10px;font-weight:700;color:#55883B;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;">Remember</div>\n  <p style="margin:0;color:#2D4A1C;font-size:13px;">Key idea goes here.</p>\n</div>\n',
    code: '<pre style="background:#1F2F14;color:#E6F0DC;border-radius:12px;padding:14px 16px;font-size:12.5px;line-height:1.7;overflow-x:auto;">class Student:\n    def study(self):\n        print("Studying!")</pre>\n'
  };
  document.querySelectorAll('[data-snip]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = ta.selectionStart; ta.value = ta.value.slice(0, s) + snips[b.getAttribute('data-snip')] + ta.value.slice(ta.selectionEnd);
      ta.focus(); if (pv.style.display !== 'none') pv.innerHTML = ta.value;
    });
  });
  pb.addEventListener('click', function () {
    var show = pv.style.display === 'none'; pv.style.display = show ? '' : 'none'; pb.textContent = show ? 'Hide preview' : 'Show preview';
    if (show) pv.innerHTML = ta.value;
  });
  ta.addEventListener('input', function () { if (pv.style.display !== 'none') pv.innerHTML = ta.value; });
})();
</script>
<?php adminEnd();
