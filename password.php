<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $st->execute([$_SESSION['user_id']]);
    $hash = $st->fetchColumn();
    $new = (string)($_POST['new_password'] ?? '');
    if (!password_verify((string)($_POST['current_password'] ?? ''), (string)$hash)) { flash('error', 'Your current password is incorrect.'); }
    elseif (strlen($new) < 8) { flash('error', 'The new password must be at least 8 characters.'); }
    elseif ($new !== (string)($_POST['confirm_password'] ?? '')) { flash('error', 'The new passwords do not match.'); }
    else {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        audit($pdo, 'password_changed', 'users', (int)$_SESSION['user_id']);
        flash('success', 'Password changed.');
        redirect('/password.php');
    }
}
$pageTitle = 'Change password'; $area = $_SESSION['role'] === 'admin' ? 'admin' : 'public';
include __DIR__ . '/includes/header.php';
?>
<div style="max-width:440px;width:100%">
  <h1 style="margin-bottom:14px">Change password</h1>
  <form class="card form" method="post" data-validate>
    <?= csrf_field() ?>
    <div><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
    <div><label for="new_password">New password (8+ characters)</label><input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password"></div>
    <div><label for="confirm_password">Confirm new password</label><input id="confirm_password" name="confirm_password" type="password" required data-match="#new_password" autocomplete="new-password"></div>
    <button class="btn" type="submit">Update password</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
