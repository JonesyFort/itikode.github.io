<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin_layout.php';

$db = getDB();
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 40;
$where = ''; $args = [];
if ($q !== '') { $where = "WHERE action LIKE ? OR actor LIKE ? OR detail LIKE ? OR ip LIKE ?"; $args = array_fill(0, 4, "%$q%"); }

$cnt = $db->prepare("SELECT COUNT(*) FROM audit_log $where"); $cnt->execute($args);
$total = (int) $cnt->fetchColumn();
$pages = max(1, (int) ceil($total / $per));
$st = $db->prepare("SELECT * FROM audit_log $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();

$tone = function (string $a): string {
    if (preg_match('/failed|denied|locked|blocked|bad_|suspended|deleted|disabled/', $a)) return 'pill-red';
    if (preg_match('/success|created|enabled|published|registered/', $a)) return 'pill-green';
    return 'pill-gray';
};

adminStart('Audit log', 'audit');
pageHead('Audit log', 'Every sign-in, failed attempt and administrative change is recorded here.');
?>
<form method="GET" class="toolbar">
  <input class="input" style="max-width:320px" name="q" value="<?= e($q) ?>" placeholder="Search events, people, IPs" aria-label="Search">
  <button class="btn btn-sm">Search</button>
  <?php if ($q !== ''): ?><a class="btn btn-sm" href="<?= u('/admin/audit.php') ?>">Clear</a><?php endif; ?>
  <span class="hint" style="margin:0"><?= $total ?> events</span>
</form>
<div class="card" style="padding:8px 8px 4px;overflow-x:auto">
<table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Event</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td style="white-space:nowrap"><?= e($r['created_at']) ?></td><td><?= e($r['actor'] ?: '-') ?></td>
      <td><span class="pill <?= $tone($r['action']) ?>"><?= e($r['action']) ?></span></td><td style="max-width:340px;word-break:break-word"><?= e($r['detail']) ?></td><td class="mono"><?= e($r['ip']) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" style="color:#8AA37A;padding:20px">No events.</td></tr><?php endif; ?>
</tbody></table>
</div>
<div style="display:flex;gap:8px;align-items:center;margin-top:12px">
  <?php if ($page > 1): ?><a class="btn btn-sm" href="?q=<?= urlencode($q) ?>&p=<?= $page - 1 ?>">← Newer</a><?php endif; ?>
  <span class="hint" style="margin:0">Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-sm" href="?q=<?= urlencode($q) ?>&p=<?= $page + 1 ?>">Older →</a><?php endif; ?>
</div>
<?php adminEnd();
