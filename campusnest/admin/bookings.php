<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bid = (int)post('booking_id'); $a = post('action');
    if ($a === 'reject') {
        $st = $pdo->prepare("UPDATE bookings SET status = 'rejected', reviewed_by = ? WHERE booking_id = ? AND status = 'pending'");
        $st->execute([$_SESSION['user_id'], $bid]);
        if ($st->rowCount()) { audit($pdo, 'booking_rejected', 'bookings', $bid); flash('success', 'Booking rejected.'); }
    } elseif ($a === 'approve') {
        try {
            $pdo->beginTransaction();
            $b = $pdo->prepare("SELECT * FROM bookings WHERE booking_id = ? AND status = 'pending' FOR UPDATE");
            $b->execute([$bid]); $b = $b->fetch();
            if (!$b) { throw new RuntimeException('This booking is no longer pending.'); }
            // Lock the room row so two admins cannot approve the same room at once
            $r = $pdo->prepare('SELECT r.status, t.price_per_month FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE r.room_id = ? FOR UPDATE');
            $r->execute([$b['room_id']]); $r = $r->fetch();
            if (!$r || $r['status'] !== 'vacant') { throw new RuntimeException('This room is no longer available.'); }
            $c = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $c->execute([$b['email']]);
            if ((int)$c->fetchColumn() > 0) { throw new RuntimeException('A user with this email already exists. Ask the applicant for a different email.'); }
            $temp = substr(bin2hex(random_bytes(6)), 0, 10);
            $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?,?,?,?,'tenant')")
                ->execute([$b['full_name'], $b['email'], $b['phone'], password_hash($temp, PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $start = new DateTime($b['move_in_date']);
            $end = (clone $start)->modify('+' . (int)$b['duration_months'] . ' months -1 day');
            $rent = (float)$r['price_per_month'];
            $pdo->prepare('INSERT INTO tenants (user_id, room_id, booking_id, lease_start, lease_end, monthly_rent, total_due) VALUES (?,?,?,?,?,?,?)')
                ->execute([$uid, $b['room_id'], $bid, $start->format('Y-m-d'), $end->format('Y-m-d'), $rent, $rent * (int)$b['duration_months']]);
            $tid = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE room_id = ?")->execute([$b['room_id']]);
            $pdo->prepare("UPDATE bookings SET status = 'approved', reviewed_by = ? WHERE booking_id = ?")->execute([$_SESSION['user_id'], $bid]);
            $pdo->prepare("UPDATE bookings SET status = 'rejected', reviewed_by = ? WHERE room_id = ? AND status = 'pending' AND booking_id <> ?")->execute([$_SESSION['user_id'], $b['room_id'], $bid]);
            audit($pdo, 'booking_approved', 'tenants', $tid, 'Booking ' . $bid);
            $pdo->commit();
            flash('success', 'Approved. Tenant login: ' . $b['email'] . ' / temporary password: ' . $temp . ' (shown once, share it with the tenant).');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($ex instanceof RuntimeException) { flash('error', $ex->getMessage()); }
            else { error_log($ex->getMessage()); flash('error', 'Could not approve the booking.'); }
        }
    }
    redirect('/admin/bookings.php');
}
$filter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$sql = 'SELECT b.*, r.room_number FROM bookings b JOIN rooms r ON r.room_id = b.room_id' . ($filter ? ' WHERE b.status = ?' : '') . ' ORDER BY b.created_at DESC';
$st = $pdo->prepare($sql); $st->execute($filter ? [$filter] : []); $list = $st->fetchAll();
$pageTitle = 'Bookings'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Booking requests</h1>
<p class="actions"><a class="btn sm <?= $filter ? 'btn2' : '' ?>" href="bookings.php">All</a><?php foreach (['pending', 'approved', 'rejected'] as $s): ?><a class="btn sm <?= $filter === $s ? '' : 'btn2' ?>" href="bookings.php?status=<?= $s ?>"><?= ucfirst($s) ?></a><?php endforeach; ?></p>
<div class="card"><?php if (!$list): ?><p class="muted">No bookings found.</p><?php else: ?>
<table><tr><th>Applicant</th><th>Contact</th><th>Room</th><th>Move-in</th><th>Months</th><th>Status</th><th>Action</th></tr>
<?php foreach ($list as $b): ?><tr><td><?= e($b['full_name']) ?></td><td><?= e($b['email']) ?><br><span class="muted"><?= e($b['phone']) ?></span></td><td><?= e($b['room_number']) ?></td><td><?= e($b['move_in_date']) ?></td><td><?= (int)$b['duration_months'] ?></td><td><?= badge($b['status']) ?></td>
<td><?php if ($b['status'] === 'pending'): ?><div class="actions">
<form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn3 sm" type="submit">Approve</button></form>
<form method="post"><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn2 sm" type="submit">Reject</button></form></div><?php endif; ?></td></tr><?php endforeach; ?>
</table><?php endif; ?></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
