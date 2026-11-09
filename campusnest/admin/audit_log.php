<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$log = $pdo->query('SELECT a.*, u.full_name FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id ORDER BY a.log_id DESC LIMIT 200')->fetchAll();
$pageTitle = 'Audit log'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Audit log</h1>
<p class="muted">The latest 200 actions: who did what, and when.</p>
<div class="card"><table><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr>
<?php foreach ($log as $l): ?><tr><td><?= e($l['created_at']) ?></td><td><?= e($l['full_name'] ?? 'Guest') ?></td><td><?= e($l['action']) ?></td><td><?= e(trim(($l['table_name'] ?? '') . ' #' . ($l['record_id'] ?? ''), ' #')) ?></td><td><?= e($l['details']) ?></td><td><?= e($l['ip_address']) ?></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
