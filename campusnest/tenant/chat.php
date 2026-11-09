<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
$st = $pdo->prepare("SELECT tenant_id FROM tenants WHERE user_id = ? AND status = 'active'");
$st->execute([$_SESSION['user_id']]);
$has = $st->fetchColumn();
$pageTitle = 'Chat with the manager'; $area = 'public'; $extraJs = 'chat.js';
include __DIR__ . '/../includes/header.php';
?>
<h1>Chat with the hostel manager</h1>
<?php if (!$has): ?><div class="card"><p>You need an active lease to use chat.</p></div><?php else: ?>
<div class="card pane" id="chat">
  <div class="msgs" id="msgs" aria-live="polite" tabindex="0"></div>
  <form class="compose" id="compose" autocomplete="off">
    <input id="body" name="body" maxlength="1000" placeholder="Type a message..." aria-label="Message" required>
    <button class="btn" type="submit">Send</button>
  </form>
</div>
<p class="muted">Messages update automatically. The manager is notified when you write.</p>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
