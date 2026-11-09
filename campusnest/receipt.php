<?php
// receipt.php - printable receipt; visible to admins and to the tenant who paid
require_once __DIR__ . '/includes/auth.php';
require_login();
$st = $pdo->prepare("SELECT p.*, u.full_name, u.email, r.room_number, t.total_due, t.user_id AS tenant_user,
        a.full_name AS admin_name,
        (SELECT COALESCE(SUM(x.amount),0) FROM payments x WHERE x.tenant_id = p.tenant_id AND x.payment_id <= p.payment_id) AS paid_to_date
        FROM payments p JOIN tenants t ON t.tenant_id = p.tenant_id JOIN users u ON u.user_id = t.user_id
        JOIN rooms r ON r.room_id = t.room_id JOIN users a ON a.user_id = p.recorded_by WHERE p.payment_id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p || ($_SESSION['role'] !== 'admin' && (int)$p['tenant_user'] !== (int)$_SESSION['user_id'])) { http_response_code(404); exit('Receipt not found.'); }
$pageTitle = 'Receipt ' . $p['receipt_no']; $area = $_SESSION['role'] === 'admin' ? 'admin' : 'public';
include __DIR__ . '/includes/header.php';
?>
<div class="receipt">
  <h1><?= APP_NAME ?></h1><p class="muted">Rent payment receipt</p>
  <hr style="border:0;border-top:1px solid var(--line);margin:16px 0">
  <table>
    <tr><td class="muted">Receipt no.</td><td><strong><?= e($p['receipt_no']) ?></strong></td></tr>
    <tr><td class="muted">Date</td><td><?= e($p['payment_date']) ?></td></tr>
    <tr><td class="muted">Received from</td><td><?= e($p['full_name']) ?> (Room <?= e($p['room_number']) ?>)</td></tr>
    <tr><td class="muted">Method</td><td><?= e(ucwords(str_replace('_', ' ', $p['method']))) ?><?= $p['reference'] ? ' - ' . e($p['reference']) : '' ?></td></tr>
    <tr><td class="muted">Amount paid</td><td><strong style="font-size:20px"><?= e(money($p['amount'])) ?></strong></td></tr>
    <tr><td class="muted">Total paid to date</td><td><?= e(money($p['paid_to_date'])) ?> of <?= e(money($p['total_due'])) ?></td></tr>
    <tr><td class="muted">Balance after payment</td><td><?= e(money($p['total_due'] - $p['paid_to_date'])) ?></td></tr>
    <tr><td class="muted">Recorded by</td><td><?= e($p['admin_name']) ?></td></tr>
  </table>
  <p style="margin-top:20px" class="noprint"><button class="btn" onclick="window.print()">Print</button> <a class="btn btn3" href="<?= BASE ?>/receipt_pdf.php?id=<?= (int)$p['payment_id'] ?>">Download PDF</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
