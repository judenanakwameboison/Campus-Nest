<?php
// api/search.php - results for the Ctrl+K search palette
require_once __DIR__ . '/../includes/auth.php';
$q = trim((string)($_GET['q'] ?? ''));
$out = [];
if (mb_strlen($q) >= 1) {
    $like = '%' . $q . '%';
    $st = $pdo->prepare("SELECT r.room_id, r.room_number, r.status, t.type_name FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE r.room_number LIKE ? OR t.type_name LIKE ? OR (r.room_id BETWEEN 1 AND 4 AND CONCAT('sample_', r.room_id, '.jpg') LIKE ?) ORDER BY r.room_number LIMIT 6");
    $st->execute([$like, $like, $like]);
    foreach ($st->fetchAll() as $r) { $out[] = ['t' => 'Room ' . public_room_name((int)$r['room_id'], (string)$r['room_number']) . ' - ' . $r['type_name'], 's' => ucfirst($r['status']), 'u' => '/room.php?id=' . (int)$r['room_id']]; }
    if (($_SESSION['role'] ?? '') === 'admin') {
        $st = $pdo->prepare("SELECT u.full_name, r.room_number FROM tenants t JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id WHERE u.full_name LIKE ? OR u.email LIKE ? LIMIT 5");
        $st->execute([$like, $like]);
        foreach ($st->fetchAll() as $r) { $out[] = ['t' => $r['full_name'], 's' => 'Tenant, room ' . $r['room_number'], 'u' => '/admin/tenants.php']; }
        $st = $pdo->prepare("SELECT full_name, status FROM bookings WHERE full_name LIKE ? OR email LIKE ? ORDER BY booking_id DESC LIMIT 5");
        $st->execute([$like, $like]);
        foreach ($st->fetchAll() as $r) { $out[] = ['t' => $r['full_name'], 's' => 'Booking, ' . $r['status'], 'u' => '/admin/bookings.php']; }
    }
}
json_out($out);
