<?php
// tenant/review.php - saves (or updates) the tenant's review of their room
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/tenant/dashboard.php'); }
csrf_check();
$st = $pdo->prepare("SELECT tenant_id, room_id FROM tenants WHERE user_id = ? AND status = 'active'");
$st->execute([$_SESSION['user_id']]); $t = $st->fetch();
$rating = (int)post('rating'); $comment = mb_substr(post('comment'), 0, 500);
if (!$t) { flash('error', 'You need an active lease to review a room.'); }
elseif ($rating < 1 || $rating > 5) { flash('error', 'Choose a rating from 1 to 5 stars.'); }
else {
    $pdo->prepare('INSERT INTO reviews (room_id, tenant_id, rating, comment) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), room_id = VALUES(room_id)')
        ->execute([$t['room_id'], $t['tenant_id'], $rating, $comment !== '' ? $comment : null]);
    audit($pdo, 'review_saved', 'reviews', (int)$t['room_id'], $rating . ' stars');
    flash('success', 'Thanks! Your review is now on the room page.');
}
redirect('/tenant/dashboard.php');
