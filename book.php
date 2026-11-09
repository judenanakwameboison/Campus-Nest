<?php
require_once __DIR__ . '/includes/auth.php';
$old = ['room_id' => (int)($_GET['room_id'] ?? 0), 'full_name' => '', 'email' => '', 'phone' => '', 'move_in_date' => '', 'duration_months' => '9'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = ['room_id' => (int)post('room_id'), 'full_name' => post('full_name'), 'email' => post('email'), 'phone' => post('phone'),
            'move_in_date' => post('move_in_date'), 'duration_months' => post('duration_months')];
    $errors = [];
    $room = $pdo->prepare("SELECT room_id FROM rooms WHERE room_id = ? AND status = 'vacant'");
    $room->execute([$old['room_id']]);
    if (!$room->fetch()) { $errors[] = 'Please choose a room that is available.'; }
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) { $errors[] = 'Enter your full name (2 to 100 characters).'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) { $errors[] = 'Enter a valid email address.'; }
    if (!preg_match('/^[0-9+\s-]{9,15}$/', $old['phone'])) { $errors[] = 'Enter a valid phone number.'; }
    $d = DateTime::createFromFormat('Y-m-d', $old['move_in_date']);
    if (!$d || $d->format('Y-m-d') !== $old['move_in_date'] || $old['move_in_date'] < date('Y-m-d')) { $errors[] = 'Choose a move-in date that is today or later.'; }
    $months = (int)$old['duration_months'];
    if ($months < 1 || $months > 12) { $errors[] = 'Stay length must be between 1 and 12 months.'; }
    if (!$errors) {
        $st = $pdo->prepare('INSERT INTO bookings (room_id, full_name, email, phone, move_in_date, duration_months) VALUES (?,?,?,?,?,?)');
        $st->execute([$old['room_id'], $old['full_name'], $old['email'], $old['phone'], $old['move_in_date'], $months]);
        $newId = (int)$pdo->lastInsertId();
        audit($pdo, 'booking_requested', 'bookings', $newId, 'Room ' . $old['room_id']);
        notify_admins($pdo, 'New booking request', $old['full_name'] . ' requested a room.', '/admin/bookings.php?status=pending');
        flash('success', 'Booking request sent. The hostel manager will contact you on the details you gave.');
        redirect('/index.php');
    }
    foreach ($errors as $er) { flash('error', $er); }
}
$rooms = $pdo->query("SELECT r.room_id, r.room_number, t.type_name, t.price_per_month FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE r.status = 'vacant' ORDER BY r.room_number")->fetchAll();
$pageTitle = 'Request a booking'; $area = 'public';
include __DIR__ . '/includes/header.php';
?>
<div class="login" style="max-width:560px;width:100%">
  <h1 style="margin-bottom:14px">Request a booking</h1>
  <form class="card form" method="post" data-validate>
    <?= csrf_field() ?>
    <div><label for="room_id">Room</label>
      <select id="room_id" name="room_id" required><option value="">Choose a room</option>
        <?php foreach ($rooms as $r): ?><option value="<?= (int)$r['room_id'] ?>" <?= $old['room_id'] === (int)$r['room_id'] ? 'selected' : '' ?>>Room <?= e(public_room_name((int)$r['room_id'], (string)$r['room_number'])) ?> - <?= e($r['type_name']) ?> - <?= e(money($r['price_per_month'])) ?>/month</option><?php endforeach; ?>
      </select></div>
    <div><label for="full_name">Full name</label><input id="full_name" name="full_name" required minlength="2" maxlength="100" value="<?= e($old['full_name']) ?>"></div>
    <div><label for="email">Email</label><input id="email" name="email" type="email" required maxlength="120" value="<?= e($old['email']) ?>"></div>
    <div><label for="phone">Phone</label><input id="phone" name="phone" required data-rule="phone" maxlength="15" value="<?= e($old['phone']) ?>"></div>
    <div class="row">
      <div><label for="move_in_date">Move-in date</label><input id="move_in_date" name="move_in_date" type="date" required min="<?= date('Y-m-d') ?>" value="<?= e($old['move_in_date']) ?>"></div>
      <div><label for="duration_months">Stay (months)</label><input id="duration_months" name="duration_months" type="number" required min="1" max="12" value="<?= e((string)$old['duration_months']) ?>"></div>
    </div>
    <button class="btn" type="submit">Send request</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
