<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$threads = $pdo->query("SELECT t.tenant_id, u.full_name, r.room_number,
    (SELECT body FROM messages m WHERE m.tenant_id = t.tenant_id ORDER BY m.message_id DESC LIMIT 1) AS last_body,
    (SELECT COUNT(*) FROM messages m WHERE m.tenant_id = t.tenant_id AND m.is_read = 0 AND m.sender_id = t.user_id) AS unread
    FROM tenants t JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id
    WHERE t.status = 'active' ORDER BY (SELECT MAX(m.message_id) FROM messages m WHERE m.tenant_id = t.tenant_id) DESC, u.full_name")->fetchAll();
$sel = (int)($_GET['tenant_id'] ?? 0);
$current = null;
foreach ($threads as $t) { if ((int)$t['tenant_id'] === $sel) { $current = $t; } }
$pageTitle = 'Chat'; $area = 'admin'; $extraJs = $current ? 'chat.js' : null;
include __DIR__ . '/../includes/header.php';
?>
<h1>Tenant chat</h1>
<div class="chat">
  <div class="threads">
    <?php if (!$threads): ?><p class="muted">No active tenants yet.</p><?php endif; ?>
    <?php foreach ($threads as $t): ?>
    <a href="chat.php?tenant_id=<?= (int)$t['tenant_id'] ?>" class="<?= (int)$t['tenant_id'] === $sel ? 'on' : '' ?>">
      <span><strong><?= e($t['full_name']) ?></strong> <span class="muted">Room <?= e($t['room_number']) ?></span><small><?= e($t['last_body'] ?? 'No messages yet') ?></small></span>
      <?php if ((int)$t['unread'] > 0): ?><span class="b bad"><?= (int)$t['unread'] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if ($current): ?>
  <div class="card pane" id="chat" data-tenant="<?= (int)$current['tenant_id'] ?>">
    <h2 style="margin-bottom:8px">Chatting with <?= e($current['full_name']) ?></h2>
    <div class="msgs" id="msgs" aria-live="polite" tabindex="0"></div>
    <form class="compose" id="compose" autocomplete="off">
      <input id="body" name="body" maxlength="1000" placeholder="Reply..." aria-label="Reply" required>
      <button class="btn" type="submit">Send</button>
    </form>
  </div>
  <?php else: ?><div class="card"><p class="muted">Choose a tenant on the left to open the conversation.</p></div><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
