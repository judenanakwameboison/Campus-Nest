<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $tid = (int)post('tenant_id'); $a = post('action');
    $t = $pdo->prepare('SELECT t.*, u.email FROM tenants t JOIN users u ON u.user_id = t.user_id WHERE t.tenant_id = ?'); $t->execute([$tid]); $t = $t->fetch();
    if ($t && $a === 'end_lease' && $t['status'] === 'active') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE tenants SET status = 'ended' WHERE tenant_id = ?")->execute([$tid]);
        $pdo->prepare("UPDATE rooms SET status = 'vacant' WHERE room_id = ?")->execute([$t['room_id']]);
        $pdo->prepare("UPDATE users SET status = 'disabled' WHERE user_id = ?")->execute([$t['user_id']]);
        audit($pdo, 'lease_ended', 'tenants', $tid);
        $pdo->commit();
        flash('success', 'Lease ended. The room is vacant again and the tenant login is disabled.');
    } elseif ($t && $a === 'reset_password' && $t['status'] === 'active') {
        $temp = substr(bin2hex(random_bytes(6)), 0, 10);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $t['user_id']]);
        audit($pdo, 'password_reset', 'users', (int)$t['user_id']);
        flash('success', 'New temporary password for ' . $t['email'] . ': ' . $temp . ' (shown once).');
    }
    redirect('/admin/tenants.php');
}
$list = $pdo->query("SELECT t.*, u.full_name, u.email, u.phone, r.room_number, COALESCE(p.paid,0) AS paid
    FROM tenants t JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id
    LEFT JOIN (SELECT tenant_id, SUM(amount) AS paid FROM payments GROUP BY tenant_id) p ON p.tenant_id = t.tenant_id
    ORDER BY t.status, u.full_name")->fetchAll();
$pageTitle = 'Tenants'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Tenants</h1>
<div class="card"><?php if (!$list): ?><p class="muted">No tenants yet. Approve a booking to create one.</p><?php else: ?>
<table><tr><th>Tenant</th><th>Room</th><th>Lease</th><th>Rent</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($list as $t): $bal = $t['total_due'] - $t['paid']; ?><tr>
<td><?= e($t['full_name']) ?><br><span class="muted"><?= e($t['email']) ?> &middot; <?= e($t['phone']) ?></span></td><td><?= e($t['room_number']) ?></td>
<td><?= e($t['lease_start']) ?><br><span class="muted">to <?= e($t['lease_end']) ?></span></td><td><?= e(money($t['monthly_rent'])) ?></td><td><?= e(money($t['paid'])) ?></td>
<td><?= $bal > 0 ? '<span class="b warn">' . e(money($bal)) . '</span>' : '<span class="b ok">Cleared</span>' ?></td><td><?= badge($t['status']) ?></td>
<td><?php if ($t['status'] === 'active'): ?><div class="actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int)$t['tenant_id'] ?>"><input type="hidden" name="action" value="reset_password"><button class="btn btn2 sm" type="submit">Reset password</button></form>
<form method="post" onsubmit="return confirm('End this lease and free the room?');"><?= csrf_field() ?><input type="hidden" name="tenant_id" value="<?= (int)$t['tenant_id'] ?>"><input type="hidden" name="action" value="end_lease"><button class="btn btn4 sm" type="submit">End lease</button></form></div><?php endif; ?></td></tr><?php endforeach; ?>
</table><?php endif; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
