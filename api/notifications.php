<?php
// api/notifications.php - polled every 15 seconds by the bell icon
require_once __DIR__ . '/../includes/auth.php';
if (!is_logged_in()) { json_out(['unread' => 0, 'items' => []], 401); }
$uid = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    json_out(['ok' => true]);
}
$st = $pdo->prepare('SELECT notification_id AS id, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY notification_id DESC LIMIT 15');
$st->execute([$uid]);
$items = $st->fetchAll();
$un = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$un->execute([$uid]);
foreach ($items as &$i) { $i['id'] = (int)$i['id']; $i['is_read'] = (int)$i['is_read']; }
json_out(['unread' => (int)$un->fetchColumn(), 'items' => $items]);
