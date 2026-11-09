<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) { redirect(($_SESSION['role'] === 'admin' ? '/admin' : '/tenant') . '/dashboard.php'); }
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = post('email');
    if (($_SESSION['lock_until'] ?? 0) > time()) {
        flash('error', 'Too many failed attempts. Please wait a minute and try again.');
    } else {
        $st = $pdo->prepare("SELECT user_id, password_hash, role FROM users WHERE email = ? AND status = 'active'");
        $st->execute([$email]);
        $user = $st->fetch();
        if ($user && password_verify(post('password'), $user['password_hash'])) {
            session_regenerate_id(true);                 // prevents session fixation
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            audit($pdo, 'login', 'users', (int)$user['user_id']);
            redirect(($user['role'] === 'admin' ? '/admin' : '/tenant') . '/dashboard.php');
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) { $_SESSION['lock_until'] = time() + 60; $_SESSION['fails'] = 0; }
        flash('error', 'Invalid email or password.');    // same message for every failure
    }
}
$pageTitle = 'Log in'; $area = 'public';
include __DIR__ . '/includes/header.php';
?>
<div class="split reveal zoom">
  <div class="vis">
    <div class="layer" style="width:300px;height:300px;border-radius:50%;background:radial-gradient(circle,rgba(201,245,90,.5),transparent 65%);top:-80px;right:-80px;animation:bob 7s ease-in-out infinite"></div>
    <div class="layer" style="width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(76,197,143,.6),transparent 65%);bottom:60px;left:-70px;animation:bob 9s ease-in-out infinite reverse"></div>
    <h2>Your hostel, <span style="color:var(--lime)">organised.</span></h2><p>Pay rent, download receipts and report repairs from one place.</p>
  </div>
  <form class="fm" method="post" data-validate>
    <div><h1>Welcome back</h1><p class="muted" style="margin-top:4px">Log in as an administrator or tenant.</p></div>
    <?= csrf_field() ?>
    <div><label for="email">Email</label><input id="email" name="email" type="email" required value="<?= e($email) ?>" autocomplete="username"></div>
    <div><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn big" type="submit">Log in</button>
    <p class="muted">No account yet? <a href="<?= BASE ?>/book.php">Request a booking</a></p>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
