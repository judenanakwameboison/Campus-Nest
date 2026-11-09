<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$flow = ['open', 'in_progress', 'resolved'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('request_id'); $status = post('status');
    if (in_array($status, $flow, true)) {
        $pdo->prepare('UPDATE maintenance_requests SET status = ?, resolved_at = ' . ($status === 'resolved' ? 'NOW()' : 'NULL') . ' WHERE request_id = ?')->execute([$status, $id]);
        audit($pdo, 'maintenance_updated', 'maintenance_requests', $id, $status);
        $mu = $pdo->prepare('SELECT t.user_id, m.title FROM maintenance_requests m JOIN tenants t ON t.tenant_id = m.tenant_id WHERE m.request_id = ?'); $mu->execute([$id]); $mu = $mu->fetch();
        if ($mu) { notify($pdo, (int)$mu['user_id'], 'Repair update', $mu['title'] . ' is now ' . str_replace('_', ' ', $status) . '.', '/tenant/dashboard.php'); }
        flash('success', 'Request updated.');
    }
    redirect('/admin/maintenance.php');
}
$list = $pdo->query("SELECT m.*, u.full_name, r.room_number FROM maintenance_requests m JOIN tenants t ON t.tenant_id = m.tenant_id JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = m.room_id
    ORDER BY (m.status = 'resolved'), FIELD(m.priority,'high','medium','low'), m.created_at DESC")->fetchAll();
$pageTitle = 'Maintenance'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Maintenance requests</h1>
<div class="card"><?php if (!$list): ?><p class="muted">No requests yet.</p><?php else: ?>
<table><tr><th>Room</th><th>Tenant</th><th>Issue</th><th>Priority</th><th>Reported</th><th>Status</th><th>Update</th></tr>
<?php foreach ($list as $m): ?><tr><td><?= e($m['room_number']) ?></td><td><?= e($m['full_name']) ?></td><td><strong><?= e($m['title']) ?></strong><br><span class="muted"><?= e($m['description']) ?></span></td><td><?= badge($m['priority']) ?></td><td><?= e(substr($m['created_at'], 0, 10)) ?></td><td><?= badge($m['status']) ?></td>
<td><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$m['request_id'] ?>">
<select name="status" style="width:140px;min-height:36px" aria-label="Status for <?= e($m['title']) ?>"><?php foreach ($flow as $f): ?><option value="<?= $f ?>" <?= $m['status'] === $f ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $f)) ?></option><?php endforeach; ?></select><button class="btn sm" type="submit">Save</button></form></td></tr><?php endforeach; ?>
</table><?php endif; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
