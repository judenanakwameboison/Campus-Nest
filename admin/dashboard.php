<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$s = $pdo->query("SELECT COUNT(*) AS total, SUM(status='vacant') AS vacant, SUM(status='occupied') AS occupied, SUM(status='maintenance') AS maint FROM rooms")->fetch();
$total = (int)$s['total']; $occupied = (int)$s['occupied']; $vacant = (int)$s['vacant'];
$pending = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$outstanding = (float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM (" . tenant_money_sql() . ") x")->fetchColumn();
$openFix = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status <> 'resolved'")->fetchColumn();
$expiring = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active' AND lease_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')")->fetchColumn();
$bookings = $pdo->query("SELECT b.booking_id, b.full_name, b.move_in_date, b.duration_months, r.room_number FROM bookings b JOIN rooms r ON r.room_id = b.room_id WHERE b.status = 'pending' ORDER BY b.created_at LIMIT 5")->fetchAll();
$late = $pdo->query("SELECT * FROM (" . tenant_money_sql() . ") x WHERE overdue > 0 ORDER BY overdue DESC LIMIT 5")->fetchAll();
$inc = $pdo->query("SELECT DATE_FORMAT(payment_date,'%b') AS l, DATE_FORMAT(payment_date,'%Y-%m') AS ym, SUM(amount) AS v FROM payments WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY ym, l ORDER BY ym")->fetchAll();
$incJson = json_encode(array_map(fn($r) => ['l' => $r['l'], 'v' => (float)$r['v']], $inc));
$donutJson = json_encode([['v' => $vacant, 'c' => '#1FAE74'], ['v' => $occupied, 'c' => '#0A3D2A'], ['v' => (int)$s['maint'], 'c' => '#F2C14E']]);
$pageTitle = 'Dashboard'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Dashboard</h1>
<div class="grid g4">
  <div class="card stat"><p class="muted">Rooms</p><p class="n" data-count="<?= $total ?>">0</p><p class="muted"><?= $vacant ?> vacant</p></div>
  <div class="card stat"><p class="muted">Occupancy</p><p class="n"><?= $total ? round($occupied / $total * 100) : 0 ?>%</p><p class="muted"><?= $occupied ?> occupied</p></div>
  <div class="card stat"><p class="muted">Pending bookings</p><p class="n" data-count="<?= $pending ?>">0</p><p class="muted"><a href="bookings.php">Review</a></p></div>
  <div class="card stat"><p class="muted">Outstanding rent</p><p class="n" data-count="<?= (float)$outstanding ?>" data-prefix="GHS " data-decimals="2">0</p><p class="muted">Whole-lease balances</p></div>
  <div class="card stat"><p class="muted">Collected this month</p><p class="n" data-count="<?= (float)$month ?>" data-prefix="GHS " data-decimals="2">0</p></div>
  <div class="card stat"><p class="muted">Open repairs</p><p class="n" data-count="<?= $openFix ?>">0</p><p class="muted"><a href="maintenance.php">View</a></p></div>
  <div class="card stat"><p class="muted">Leases ending in 30 days</p><p class="n" data-count="<?= $expiring ?>">0</p></div>
</div>
<div class="grid g2">
  <div class="card reveal"><h2>Income, last 6 months</h2><div class="bars" data-json="<?= e($incJson) ?>"></div></div>
  <div class="card reveal" style="--d:.1s"><h2>Room status</h2><div class="donut"><div class="donut-chart" data-json="<?= e($donutJson) ?>"></div>
    <div class="legend"><div><i style="background:#1FAE74"></i>Vacant: <?= $vacant ?></div><div><i style="background:#0A3D2A"></i>Occupied: <?= $occupied ?></div><div><i style="background:#F2C14E"></i>Maintenance: <?= (int)$s['maint'] ?></div></div></div></div>
</div>
<div class="card"><h2>Pending booking requests</h2>
<?php if (!$bookings): ?><p class="muted" style="margin-top:8px">No pending requests.</p><?php else: ?>
<table><tr><th>Applicant</th><th>Room</th><th>Move-in</th><th>Months</th><th></th></tr>
<?php foreach ($bookings as $b): ?><tr><td><?= e($b['full_name']) ?></td><td><?= e($b['room_number']) ?></td><td><?= e($b['move_in_date']) ?></td><td><?= (int)$b['duration_months'] ?></td><td><a class="btn sm" href="bookings.php?status=pending">Review</a></td></tr><?php endforeach; ?>
</table><?php endif; ?></div>
<div class="card"><h2>Overdue rent</h2>
<?php if (!$late): ?><p class="muted" style="margin-top:8px">Nobody is behind on rent.</p><?php else: ?>
<table><tr><th>Tenant</th><th>Room</th><th>Phone</th><th>Overdue</th></tr>
<?php foreach ($late as $t): ?><tr><td><?= e($t['full_name']) ?></td><td><?= e($t['room_number']) ?></td><td><?= e($t['phone']) ?></td><td><span class="b bad"><?= e(money($t['overdue'])) ?></span></td></tr><?php endforeach; ?>
</table><?php endif; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
