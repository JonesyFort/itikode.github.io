<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

$db = getDB();

function renumber(PDO $db): void
{
    $i = 1;
    $upd = $db->prepare("UPDATE sections SET position = ? WHERE id = ?");
    foreach ($db->query("SELECT id FROM sections ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN) as $id) $upd->execute([$i++, $id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $do = (string) ($_POST['do'] ?? '');
    $row = $db->prepare("SELECT * FROM sections WHERE id = ?");
    $row->execute([$id]);
    $sec = $row->fetch();

    if ($sec) {
        renumber($db);
        $sec['position'] = (int) $db->query("SELECT position FROM sections WHERE id = $id")->fetchColumn();
        if ($do === 'up' || $do === 'down') {
            $target = $sec['position'] + ($do === 'up' ? -1 : 1);
            $db->beginTransaction();
            $db->prepare("UPDATE sections SET position = -1 WHERE position = ?")->execute([$target]);
            $db->prepare("UPDATE sections SET position = ? WHERE id = ?")->execute([$target, $id]);
            $db->prepare("UPDATE sections SET position = ? WHERE position = -1")->execute([$sec['position']]);
            $db->commit();
            auditLog('content.reorder', $sec['name'] . ' ' . $do);
        } elseif ($do === 'toggle') {
            $db->prepare("UPDATE sections SET published = 1 - published, updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
            auditLog('content.' . ($sec['published'] ? 'unpublished' : 'published'), $sec['name']);
            flashSet('success', '"' . $sec['name'] . '" is now ' . ($sec['published'] ? 'hidden from learners.' : 'visible to learners.'));
        } elseif ($do === 'duplicate') {
            $db->beginTransaction();
            $db->prepare("INSERT INTO sections (position, name, title, type, duration, direction, html, published) VALUES (?,?,?,?,?,?,?,0)")
               ->execute([$sec['position'] + 1, $sec['name'] . ' (copy)', $sec['title'], $sec['type'], $sec['duration'], $sec['direction'], $sec['html']]);
            $new = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO questions (section_id, position, part, text, options, correct, explanation)
                          SELECT ?, position, part, text, options, correct, explanation FROM questions WHERE section_id = ?")->execute([$new, $id]);
            $db->commit();
            auditLog('content.duplicated', $sec['name']);
            flashSet('success', 'Copy created as a hidden draft.');
            renumber($db);
        } elseif ($do === 'delete') {
            if (!adminPasswordOk((string) ($_POST['admin_pw'] ?? ''))) flashSet('error', 'Wrong password. Nothing was deleted.');
            else {
                $db->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
                auditLog('content.deleted', $sec['name']);
                flashSet('success', '"' . $sec['name'] . '" was deleted along with its questions and learner progress.');
                renumber($db);
            }
        }
    }
    redirect(u('/admin/sections.php'));
}

$sections = $db->query("SELECT s.*, (SELECT COUNT(*) FROM questions q WHERE q.section_id = s.id) AS qn,
                        (SELECT COUNT(*) FROM user_progress p WHERE p.section_id = s.id) AS done
                        FROM sections s ORDER BY position, id")->fetchAll();

adminStart('Lessons & content', 'sections');
pageHead('Lessons & content', 'Add, edit, reorder, hide or remove any lesson, quiz, assessment or evaluation.',
    '<a class="btn btn-primary" href="' . u('/admin/section_edit.php') . '">+ New section</a>');
?>
<div class="card" style="padding:8px 8px 4px;overflow-x:auto">
<table class="tbl">
  <thead><tr><th style="width:44px">#</th><th>Section</th><th>Type</th><th>Questions</th><th>Completed</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($sections as $i => $s): $pill = getSectionTypePill($s['type']); ?>
    <tr>
      <td style="font-weight:800;color:var(--brown)"><?= $i + 1 ?></td>
      <td><b style="color:var(--deep)"><?= e($s['name']) ?></b><div class="hint" style="margin:1px 0 0"><?= e($s['title']) ?> · <?= e($s['duration']) ?></div></td>
      <td><span class="pill" style="background:<?= $pill['bg'] ?>;color:<?= $pill['color'] ?>"><?= e($pill['label']) ?></span></td>
      <td><?= $s['type'] === 'lesson' ? '<span style="color:#aaa">n/a</span>' : (int) $s['qn'] ?></td>
      <td><?= (int) $s['done'] ?></td>
      <td><span class="pill <?= $s['published'] ? 'pill-green' : 'pill-gray' ?>"><?= $s['published'] ? 'Published' : 'Hidden' ?></span></td>
      <td>
        <form method="POST" action="<?= u('/admin/sections.php') ?>" class="actions"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
          <button class="btn btn-sm" name="do" value="up" title="Move up" aria-label="Move up" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
          <button class="btn btn-sm" name="do" value="down" title="Move down" aria-label="Move down" <?= $i === count($sections) - 1 ? 'disabled' : '' ?>>↓</button>
          <a class="btn btn-sm btn-lime" href="<?= u('/admin/section_edit.php?id=' . (int) $s['id']) ?>">Edit</a>
          <a class="btn btn-sm" href="<?= u('/section.php?id=' . (int) $s['id']) ?>" target="_blank" rel="noopener">Preview</a>
          <button class="btn btn-sm" name="do" value="toggle"><?= $s['published'] ? 'Hide' : 'Publish' ?></button>
          <button class="btn btn-sm" name="do" value="duplicate">Duplicate</button>
          <button class="btn btn-sm btn-danger" type="button" onclick="document.getElementById('del-id').value=<?= (int) $s['id'] ?>;document.getElementById('del-name').textContent=<?= e(json_encode($s['name'])) ?>;document.getElementById('del-dlg').showModal()">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="hint">Learners work through published sections in this order. Each section unlocks when the one before it is completed.</p>

<dialog id="del-dlg">
  <form method="POST" action="<?= u('/admin/sections.php') ?>"><?= csrfField() ?>
    <input type="hidden" name="do" value="delete"><input type="hidden" name="id" id="del-id">
    <h3 style="margin:0 0 6px;color:var(--deep)">Delete section?</h3>
    <p style="font-size:13.5px;margin:0 0 12px"><b id="del-name"></b> and its questions and every learner's progress on it will be permanently removed. Consider hiding it instead.</p>
    <div class="field"><label class="label" for="del-pw">Confirm with your admin password</label><input class="input" type="password" id="del-pw" name="admin_pw" required autocomplete="current-password"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-sm" onclick="this.closest('dialog').close()">Cancel</button><button class="btn btn-sm btn-danger">Delete permanently</button></div>
  </form>
</dialog>
<?php adminEnd();
