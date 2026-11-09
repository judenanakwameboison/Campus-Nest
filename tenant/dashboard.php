<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
$uid = (int)$_SESSION['user_id'];
$t = $pdo->prepare("SELECT t.*, r.room_number, r.floor, rt.type_name, u.full_name,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = t.tenant_id) AS paid
    FROM tenants t JOIN rooms r ON r.room_id = t.room_id JOIN room_types rt ON rt.room_type_id = r.room_type_id JOIN users u ON u.user_id = t.user_id
    WHERE t.user_id = ? AND t.status = 'active'");
$t->execute([$uid]); $t = $t->fetch();

if ($t && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = post('title'); $desc = post('description'); $prio = post('priority');
    if (mb_strlen($title) < 3 || mb_strlen($title) > 120 || mb_strlen($desc) < 5 || mb_strlen($desc) > 1000 || !in_array($prio, ['low', 'medium', 'high'], true)) {
        flash('error', 'Enter a title (3 to 120 characters), a description (5 to 1000 characters) and a priority.');
    } else {
        $pdo->prepare('INSERT INTO maintenance_requests (tenant_id, room_id, title, description, priority) VALUES (?,?,?,?,?)')->execute([$t['tenant_id'], $t['room_id'], $title, $desc, $prio]);
        audit($pdo, 'maintenance_requested', 'maintenance_requests', (int)$pdo->lastInsertId(), $title);
        notify_admins($pdo, 'New repair request', $t['full_name'] . ': ' . $title, '/admin/maintenance.php');
        flash('success', 'Your request was sent to the hostel manager.');
    }
    redirect('/tenant/dashboard.php');
}
$pageTitle = 'My dashboard'; $area = 'public';
include __DIR__ . '/../includes/header.php';
if (!$t): ?>
<h1>Welcome</h1><div class="card"><p>You do not have an active lease. Please contact the hostel manager.</p></div>
<?php else:
    $balance = $t['total_due'] - $t['paid'];
    $od = $pdo->prepare('SELECT overdue FROM (' . tenant_money_sql() . ') x WHERE tenant_id = ?'); $od->execute([$t['tenant_id']]); $overdue = max(0, (float)$od->fetchColumn());
    $rev = $pdo->prepare('SELECT rating, comment FROM reviews WHERE tenant_id = ?'); $rev->execute([$t['tenant_id']]); $rev = $rev->fetch();
    $pct = $t['total_due'] > 0 ? min(100, round($t['paid'] / $t['total_due'] * 100)) : 0;
    $pays = $pdo->prepare('SELECT * FROM payments WHERE tenant_id = ? ORDER BY payment_date DESC, payment_id DESC'); $pays->execute([$t['tenant_id']]); $pays = $pays->fetchAll();
    $reqs = $pdo->prepare('SELECT * FROM maintenance_requests WHERE tenant_id = ? ORDER BY created_at DESC'); $reqs->execute([$t['tenant_id']]); $reqs = $reqs->fetchAll();
?>
<h1 class="reveal">Welcome, <?= e($t['full_name']) ?></h1>
<?php if ($overdue > 0): ?><div class="alert warn">Your rent is overdue by <?= e(money($overdue)) ?>. <a href="<?= BASE ?>/tenant/pay.php"><strong>Pay now</strong></a></div><?php endif; ?>
<div class="actions"><a class="btn btn3" href="<?= BASE ?>/tenant/pay.php">Pay rent</a><a class="btn btn2" href="<?= BASE ?>/tenant/chat.php">Chat with manager</a><a class="btn btn2" href="<?= BASE ?>/tenant/roommates.php">Roommate matches</a></div>
<div class="grid g3">
  <div class="card stat"><p class="muted">My room</p><p class="n"><?= e($t['room_number']) ?></p><p class="muted"><?= e($t['type_name']) ?> &middot; Lease <?= e($t['lease_start']) ?> to <?= e($t['lease_end']) ?></p></div>
  <div class="card stat"><p class="muted">Balance due</p><p class="n" style="color:<?= $balance > 0 ? '#9B1C1C' : '#0B6B5C' ?>"><?= e(money($balance)) ?></p>
    <div style="display:flex;align-items:center;gap:14px;margin-top:8px"><div class="ring" data-pct="<?= (int)$pct ?>"></div><p class="muted"><?= (int)$pct ?>% of the lease paid</p></div></div>
  <div class="card stat"><p class="muted">Monthly rent</p><p class="n"><?= e(money($t['monthly_rent'])) ?></p><p class="muted">Total for lease: <?= e(money($t['total_due'])) ?></p></div>
</div>
<div class="card"><h2>Payment history</h2>
<?php if (!$pays): ?><p class="muted" style="margin-top:8px">No payments recorded yet.</p><?php else: ?>
<table><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Amount</th><th></th></tr>
<?php foreach ($pays as $p): ?><tr><td><?= e($p['receipt_no']) ?></td><td><?= e($p['payment_date']) ?></td><td><?= e(ucwords(str_replace('_', ' ', $p['method']))) ?></td><td><?= e(money($p['amount'])) ?></td><td><a href="<?= BASE ?>/receipt.php?id=<?= (int)$p['payment_id'] ?>">View</a> &middot; <a href="<?= BASE ?>/receipt_pdf.php?id=<?= (int)$p['payment_id'] ?>">PDF</a></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<form class="card form" method="post" action="<?= BASE ?>/tenant/review.php"><h2>Rate your room</h2><?= csrf_field() ?>
  <div><span class="rate" role="radiogroup" aria-label="Rating"><?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="st<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int)($rev['rating'] ?? 0) === $i ? 'checked' : '' ?> required><label for="st<?= $i ?>" title="<?= $i ?> stars">&#9733;</label><?php endfor; ?></span></div>
  <div><label for="comment">Your review (optional)</label><input id="comment" name="comment" maxlength="500" value="<?= e($rev['comment'] ?? '') ?>"></div>
  <button class="btn" type="submit"><?= $rev ? 'Update review' : 'Post review' ?></button></form>
<div class="grid g2">
  <div class="card"><h2>My maintenance requests</h2>
  <?php if (!$reqs): ?><p class="muted" style="margin-top:8px">No requests yet.</p><?php else: ?>
  <table><tr><th>Issue</th><th>Reported</th><th>Status</th></tr><?php foreach ($reqs as $m): ?><tr><td><?= e($m['title']) ?></td><td><?= e(substr($m['created_at'], 0, 10)) ?></td><td><?= badge($m['status']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
  <form class="card form" method="post" data-validate><h2>Report a problem</h2><?= csrf_field() ?>
    <div><label for="title">What is wrong?</label><input id="title" name="title" required minlength="3" maxlength="120"></div>
    <div><label for="description">Details</label><textarea id="description" name="description" required minlength="5" maxlength="1000"></textarea></div>
    <div><label for="priority">Priority</label><select id="priority" name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
    <button class="btn" type="submit">Send request</button></form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
