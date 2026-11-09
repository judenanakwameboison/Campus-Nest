<?php
// tenant/pay.php - simulated mobile money checkout (demo mode: no real money moves)
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
$uid = (int)$_SESSION['user_id'];
$st = $pdo->prepare("SELECT t.tenant_id, t.monthly_rent, t.total_due, u.full_name, r.room_number,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = t.tenant_id) AS paid
    FROM tenants t JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id WHERE t.user_id = ? AND t.status = 'active'");
$st->execute([$uid]); $t = $st->fetch();
if (!$t) { flash('warn', 'You do not have an active lease.'); redirect('/tenant/dashboard.php'); }
$balance = round($t['total_due'] - $t['paid'], 2);
$od = $pdo->prepare('SELECT overdue FROM (' . tenant_money_sql() . ') x WHERE tenant_id = ?'); $od->execute([$t['tenant_id']]);
$overdue = max(0, round((float)$od->fetchColumn(), 2));
$providers = ['MTN MoMo', 'Telecel Cash', 'AirtelTigo Money'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $step = post('step');
    if ($step === 'start') {
        $amount = round((float)post('amount'), 2); $provider = post('provider'); $phone = preg_replace('/\s+/', '', post('phone'));
        if (!in_array($provider, $providers, true)) { flash('error', 'Choose a mobile money provider.'); }
        elseif (!preg_match('/^0[0-9]{9}$/', $phone)) { flash('error', 'Enter a valid 10-digit mobile money number, like 0241234567.'); }
        elseif ($amount <= 0 || $amount > $balance) { flash('error', 'Enter an amount between GHS 0.01 and your balance of ' . money($balance) . '.'); }
        else {
            $_SESSION['checkout'] = ['tenant_id' => (int)$t['tenant_id'], 'amount' => $amount, 'provider' => $provider, 'phone' => $phone,
                                     'ref' => 'SIM-' . strtoupper(bin2hex(random_bytes(4))), 'expires' => time() + 300];
            redirect('/tenant/pay.php?step=confirm');
        }
        redirect('/tenant/pay.php');
    }
    if ($step === 'confirm') {
        $c = $_SESSION['checkout'] ?? null;
        if (!$c || $c['expires'] < time() || (int)$c['tenant_id'] !== (int)$t['tenant_id']) { unset($_SESSION['checkout']); flash('error', 'That payment request expired. Please start again.'); redirect('/tenant/pay.php'); }
        if (!preg_match('/^[0-9]{4}$/', post('pin'))) { flash('error', 'Enter your 4-digit PIN.'); redirect('/tenant/pay.php?step=confirm'); }   // the PIN is never stored
        try {
            $pid = record_payment($pdo, (int)$t['tenant_id'], (float)$c['amount'], date('Y-m-d'), 'mobile_money', $c['provider'] . ' ' . $c['ref'], $uid);
        } catch (RuntimeException $ex) { unset($_SESSION['checkout']); flash('error', $ex->getMessage()); redirect('/tenant/pay.php'); }
        unset($_SESSION['checkout']);
        audit($pdo, 'online_payment', 'payments', $pid, money($c['amount']) . ' via ' . $c['provider'] . ' (demo)');
        notify_admins($pdo, 'Online payment received', $t['full_name'] . ' paid ' . money($c['amount']) . ' (Room ' . $t['room_number'] . ').', '/admin/payments.php');
        flash('success', 'Payment successful. Your receipt is ready.');
        redirect('/receipt.php?id=' . $pid);
    }
}
$checkout = null;
if (($_GET['step'] ?? '') === 'confirm') {
    $checkout = $_SESSION['checkout'] ?? null;
    if (!$checkout || $checkout['expires'] < time()) { unset($_SESSION['checkout']); flash('error', 'That payment request expired. Please start again.'); redirect('/tenant/pay.php'); }
}
$default = $overdue > 0 ? min($overdue, $balance) : min((float)$t['monthly_rent'], $balance);
$pageTitle = 'Pay rent'; $area = 'public'; $extraJs = 'pay.js';
include __DIR__ . '/../includes/header.php';
?>
<h1>Pay rent</h1>
<div class="demo">Demo mode: this simulates mobile money. No real money moves.</div>
<?php if ($balance <= 0): ?>
  <div class="card"><h2>You are fully paid. Thank you!</h2></div>
<?php elseif ($checkout): ?>
  <div class="grid g2" style="align-items:center">
    <div class="card"><h2>Confirm on your phone</h2>
      <p class="muted" style="margin:8px 0 14px">We sent a payment request to your phone. Enter your PIN to approve it.</p>
      <table><tr><td class="muted">Provider</td><td><?= e($checkout['provider']) ?></td></tr><tr><td class="muted">Number</td><td><?= e($checkout['phone']) ?></td></tr>
      <tr><td class="muted">Amount</td><td><strong><?= e(money($checkout['amount'])) ?></strong></td></tr><tr><td class="muted">Reference</td><td><?= e($checkout['ref']) ?></td></tr></table>
      <p style="margin-top:14px"><a href="<?= BASE ?>/tenant/pay.php">Cancel</a></p></div>
    <div class="phone"><div class="screen" id="screen" data-phone="<?= e($checkout['phone']) ?>" data-amount="<?= e(number_format($checkout['amount'], 2)) ?>" data-provider="<?= e($checkout['provider']) ?>"></div></div>
  </div>
  <form id="pinForm" method="post"><?= csrf_field() ?><input type="hidden" name="step" value="confirm"><input type="hidden" name="pin" id="pin"></form>
<?php else: ?>
  <div class="grid g3">
    <div class="card stat"><p class="muted">Balance</p><p class="n"><?= e(money($balance)) ?></p></div>
    <div class="card stat"><p class="muted">Overdue now</p><p class="n" style="color:<?= $overdue > 0 ? '#E5484D' : 'inherit' ?>"><?= e(money($overdue)) ?></p></div>
    <div class="card stat"><p class="muted">Monthly rent</p><p class="n"><?= e(money($t['monthly_rent'])) ?></p></div>
  </div>
  <form class="card form" method="post" data-validate><h2>Mobile money payment</h2><?= csrf_field() ?><input type="hidden" name="step" value="start">
    <div><label>Provider</label><div class="prov"><?php foreach ($providers as $i => $pv): ?><label><input type="radio" name="provider" value="<?= e($pv) ?>" <?= $i === 0 ? 'checked' : '' ?>><span><?= e($pv) ?></span></label><?php endforeach; ?></div></div>
    <div class="row">
      <div><label for="phone">Mobile money number</label><input id="phone" name="phone" required data-rule="phone" maxlength="12" placeholder="0241234567" inputmode="numeric"></div>
      <div><label for="amount">Amount (GHS)</label><input id="amount" name="amount" type="number" required min="0.01" max="<?= e((string)$balance) ?>" step="0.01" value="<?= e(number_format($default, 2, '.', '')) ?>"></div>
    </div>
    <div><button class="btn big" type="submit">Pay now</button></div>
  </form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
