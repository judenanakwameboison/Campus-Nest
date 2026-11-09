<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
$methods = ['cash' => 'Cash', 'mobile_money' => 'Mobile money', 'bank_transfer' => 'Bank transfer'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $tid = (int)post('tenant_id'); $amount = round((float)post('amount'), 2); $date = post('payment_date'); $method = post('method');
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date || $date > date('Y-m-d')) { throw new RuntimeException('Choose a valid payment date (not in the future).'); }
        if (!isset($methods[$method])) { throw new RuntimeException('Choose a payment method.'); }
        if ($amount <= 0) { throw new RuntimeException('The amount must be greater than zero.'); }
        $pdo->beginTransaction();
        $t = $pdo->prepare("SELECT total_due, (SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = tenants.tenant_id) AS paid FROM tenants WHERE tenant_id = ? AND status = 'active' FOR UPDATE");
        $t->execute([$tid]); $t = $t->fetch();
        if (!$t) { throw new RuntimeException('Choose an active tenant.'); }
        $balance = round($t['total_due'] - $t['paid'], 2);
        if ($amount > $balance) { throw new RuntimeException('That is more than the balance of ' . money($balance) . '.'); }
        $pdo->prepare('INSERT INTO payments (tenant_id, amount, payment_date, method, reference, receipt_no, recorded_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$tid, $amount, $date, $method, substr(post('reference'), 0, 60) ?: null, 'TMP' . bin2hex(random_bytes(5)), $_SESSION['user_id']]);
        $pid = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE payments SET receipt_no = ? WHERE payment_id = ?')->execute(['RC-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT), $pid]);
        audit($pdo, 'payment_recorded', 'payments', $pid, money($amount));
        $tu = $pdo->prepare('SELECT user_id FROM tenants WHERE tenant_id = ?'); $tu->execute([$tid]);
        notify($pdo, (int)$tu->fetchColumn(), 'Payment received', money($amount) . ' recorded. Receipt RC-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT) . '.', '/tenant/dashboard.php');
        $pdo->commit();
        flash('success', 'Payment recorded. Balance remaining: ' . money($balance - $amount) . '.');
        redirect('/receipt.php?id=' . $pid);
    } catch (RuntimeException $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        flash('error', $ex->getMessage());
        redirect('/admin/payments.php');
    }
}
$tenants = $pdo->query('SELECT tenant_id, full_name, room_number, balance FROM (' . tenant_money_sql() . ') x WHERE balance > 0 ORDER BY full_name')->fetchAll();
$recent = $pdo->query("SELECT p.*, u.full_name, r.room_number FROM payments p JOIN tenants t ON t.tenant_id = p.tenant_id JOIN users u ON u.user_id = t.user_id JOIN rooms r ON r.room_id = t.room_id ORDER BY p.payment_id DESC LIMIT 50")->fetchAll();
$pageTitle = 'Payments'; $area = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<h1>Rent payments</h1>
<form class="card form" method="post" data-validate><h2>Record a payment</h2><?= csrf_field() ?>
  <div class="row">
    <div><label for="tenant_id">Tenant</label><select id="tenant_id" name="tenant_id" required><option value="">Choose tenant</option>
      <?php foreach ($tenants as $t): ?><option value="<?= (int)$t['tenant_id'] ?>"><?= e($t['full_name']) ?> (Room <?= e($t['room_number']) ?>) - owes <?= e(money($t['balance'])) ?></option><?php endforeach; ?></select></div>
    <div><label for="amount">Amount (GHS)</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required></div>
    <div><label for="payment_date">Date</label><input id="payment_date" name="payment_date" type="date" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>"></div>
    <div><label for="method">Method</label><select id="method" name="method" required><?php foreach ($methods as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
    <div><label for="reference">Reference (optional)</label><input id="reference" name="reference" maxlength="60"></div>
  </div>
  <div><button class="btn" type="submit">Record payment</button></div>
</form>
<div class="card"><h2>Recent payments</h2><table><tr><th>Receipt</th><th>Date</th><th>Tenant</th><th>Room</th><th>Method</th><th>Amount</th><th></th></tr>
<?php foreach ($recent as $p): ?><tr><td><?= e($p['receipt_no']) ?></td><td><?= e($p['payment_date']) ?></td><td><?= e($p['full_name']) ?></td><td><?= e($p['room_number']) ?></td><td><?= e($methods[$p['method']] ?? $p['method']) ?></td><td><?= e(money($p['amount'])) ?></td><td><a href="<?= BASE ?>/receipt.php?id=<?= (int)$p['payment_id'] ?>">Receipt</a></td></tr><?php endforeach; ?></table></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
