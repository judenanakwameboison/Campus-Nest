<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'send_reminders') {
    csrf_check();
    $late = $pdo->query('SELECT user_id, full_name, overdue FROM (' . tenant_money_sql() . ') x WHERE overdue > 0')->fetchAll();
    $sent = 0;
    foreach ($late as $l) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Rent reminder' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $dup->execute([$l['user_id']]);
        if ((int)$dup->fetchColumn() === 0) { notify($pdo, (int)$l['user_id'], 'Rent reminder', 'You have ' . money($l['overdue']) . ' overdue. Please pay as soon as you can.', '/tenant/pay.php'); $sent++; }
    }
    audit($pdo, 'reminders_sent', null, null, $sent . ' reminders');
    flash('success', $sent . ' rent reminder(s) sent.');
    redirect('/admin/reports.php');
}
$arrears = $pdo->query('SELECT * FROM (' . tenant_money_sql() . ') x WHERE balance > 0 ORDER BY overdue DESC, balance DESC')->fetchAll();
$income = $pdo->query("SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month, COUNT(*) AS n, SUM(amount) AS total FROM payments GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();
$occ = $pdo->query('SELECT status, COUNT(*) AS n FROM rooms GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$ending = $pdo->query("SELECT u.full_name, r.room_number, t.lease_end FROM tenants t JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id WHERE t.status = 'active' AND t.lease_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY t.lease_end")->fetchAll();
$totalRooms = array_sum($occ);
$pageTitle = 'Reports'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px"><h1>Reports</h1><div class="actions noprint"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_reminders"><button class="btn btn3" type="submit">Send rent reminders</button></form><button class="btn" onclick="window.print()">Print</button></div></div>
<div class="grid g3">
  <?php foreach (['vacant' => 'Vacant', 'occupied' => 'Occupied', 'maintenance' => 'Under maintenance'] as $k => $label): $n = (int)($occ[$k] ?? 0); ?>
  <div class="card stat"><p class="muted"><?= $label ?> rooms</p><p class="n"><?= $n ?></p><p class="muted"><?= $totalRooms ? round($n / $totalRooms * 100) : 0 ?>% of <?= $totalRooms ?></p></div>
  <?php endforeach; ?>
</div>
<div class="card"><h2>Outstanding rent</h2><p class="muted" style="margin:4px 0 8px">Overdue = rent due so far this lease minus payments. Balance = whole lease minus payments.</p>
<?php if (!$arrears): ?><p class="muted">Everyone is fully paid.</p><?php else: ?>
<table><tr><th>Tenant</th><th>Room</th><th>Phone</th><th>Paid</th><th>Overdue now</th><th>Total balance</th></tr>
<?php foreach ($arrears as $a): ?><tr><td><?= e($a['full_name']) ?></td><td><?= e($a['room_number']) ?></td><td><?= e($a['phone']) ?></td><td><?= e(money($a['paid'])) ?></td><td><?= $a['overdue'] > 0 ? '<span class="b bad">' . e(money($a['overdue'])) . '</span>' : '<span class="b ok">Up to date</span>' ?></td><td><?= e(money($a['balance'])) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<div class="card"><h2>Monthly income</h2>
<?php if (!$income): ?><p class="muted">No payments yet.</p><?php else: ?><table><tr><th>Month</th><th>Payments</th><th>Total collected</th></tr>
<?php foreach ($income as $i): ?><tr><td><?= e($i['month']) ?></td><td><?= (int)$i['n'] ?></td><td><?= e(money($i['total'])) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<div class="card"><h2>Leases ending in the next 60 days</h2>
<?php if (!$ending): ?><p class="muted">None.</p><?php else: ?><table><tr><th>Tenant</th><th>Room</th><th>Lease ends</th></tr>
<?php foreach ($ending as $l): ?><tr><td><?= e($l['full_name']) ?></td><td><?= e($l['room_number']) ?></td><td><?= e($l['lease_end']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
