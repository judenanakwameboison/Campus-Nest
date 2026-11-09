<?php
// api/chat.php - messages between a tenant and the manager (polled by assets/js/chat.js)
require_once __DIR__ . '/../includes/auth.php';
if (!is_logged_in()) { json_out(['error' => 'Please log in.'], 401); }
$role = $_SESSION['role']; $uid = (int)$_SESSION['user_id'];
if ($role === 'tenant') {
    $st = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = ? AND status = 'active'");
    $st->execute([$uid]);
    $tid = (int)$st->fetchColumn();
} else {
    $tid = (int)($_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
}
if ($tid <= 0) { json_out(['error' => 'No conversation.'], 400); }
$ten = $pdo->prepare('SELECT t.user_id, u.full_name FROM tenants t JOIN users u ON u.user_id = t.user_id WHERE t.tenant_id = ?');
$ten->execute([$tid]); $ten = $ten->fetch();
if (!$ten) { json_out(['error' => 'Not found.'], 404); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $body = trim((string)($_POST['body'] ?? ''));
    if ($body === '' || mb_strlen($body) > 1000) { json_out(['error' => 'Write a message of up to 1000 characters.'], 422); }
    $pdo->prepare('INSERT INTO messages (tenant_id, sender_id, body) VALUES (?,?,?)')->execute([$tid, $uid, $body]);
    $id = (int)$pdo->lastInsertId();
    if ($role === 'tenant') { notify_admins($pdo, 'New message', $ten['full_name'] . ': ' . mb_substr($body, 0, 80), '/admin/chat.php?tenant_id=' . $tid); }
    else { notify($pdo, (int)$ten['user_id'], 'Message from the manager', mb_substr($body, 0, 80), '/tenant/chat.php'); }
    json_out(['ok' => true, 'id' => $id]);
}
$after = (int)($_GET['after'] ?? 0);
$st = $pdo->prepare('SELECT message_id, sender_id, body, created_at FROM messages WHERE tenant_id = ? AND message_id > ? ORDER BY message_id LIMIT 100');
$st->execute([$tid, $after]);
$out = [];
foreach ($st->fetchAll() as $m) { $out[] = ['id' => (int)$m['message_id'], 'mine' => (int)$m['sender_id'] === $uid, 'body' => $m['body'], 'time' => date('D H:i', strtotime($m['created_at']))]; }
$pdo->prepare('UPDATE messages SET is_read = 1 WHERE tenant_id = ? AND sender_id <> ? AND is_read = 0')->execute([$tid, $uid]);
json_out($out);
