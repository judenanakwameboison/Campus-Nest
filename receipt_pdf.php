<?php
// receipt_pdf.php - downloadable PDF receipt (admins, or the tenant who paid)
require_once __DIR__ . '/includes/auth.php';
require_login();
$st = $pdo->prepare("SELECT p.*, u.full_name, r.room_number, t.total_due, t.user_id AS tenant_user, a.full_name AS admin_name,
        (SELECT COALESCE(SUM(x.amount),0) FROM payments x WHERE x.tenant_id = p.tenant_id AND x.payment_id <= p.payment_id) AS paid_to_date
        FROM payments p JOIN tenants t ON t.tenant_id = p.tenant_id JOIN users u ON u.user_id = t.user_id
        JOIN rooms r ON r.room_id = t.room_id JOIN users a ON a.user_id = p.recorded_by WHERE p.payment_id = ?");
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p || ($_SESSION['role'] !== 'admin' && (int)$p['tenant_user'] !== (int)$_SESSION['user_id'])) { http_response_code(404); exit('Receipt not found.'); }
$green = [0.059, 0.42, 0.275]; $grey = [0.35, 0.40, 0.37]; $ink = [0.06, 0.16, 0.12];
$items = [
    ['rect' => [0, 760, 595, 82], 'c' => $green],
    ['t' => 'CampusNest', 'x' => 40, 'y' => 800, 's' => 28, 'b' => true, 'c' => [1, 1, 1]],
    ['t' => 'Rent payment receipt', 'x' => 40, 'y' => 776, 's' => 13, 'c' => [0.78, 0.96, 0.35]],
    ['t' => $p['receipt_no'], 'x' => 430, 'y' => 790, 's' => 18, 'b' => true, 'c' => [1, 1, 1]],
];
$rows = [
    ['Date', $p['payment_date']],
    ['Received from', $p['full_name'] . '  (Room ' . $p['room_number'] . ')'],
    ['Payment method', ucwords(str_replace('_', ' ', $p['method'])) . ($p['reference'] ? '  -  ' . $p['reference'] : '')],
    ['Amount paid', money($p['amount'])],
    ['Total paid to date', money($p['paid_to_date']) . '  of  ' . money($p['total_due'])],
    ['Balance after payment', money($p['total_due'] - $p['paid_to_date'])],
    ['Recorded by', $p['admin_name']],
];
$y = 700;
foreach ($rows as $r) {
    $big = $r[0] === 'Amount paid';
    $items[] = ['t' => strtoupper($r[0]), 'x' => 40, 'y' => $y, 's' => 9, 'c' => $grey];
    $items[] = ['t' => $r[1], 'x' => 40, 'y' => $y - 20, 's' => $big ? 22 : 13, 'b' => $big, 'c' => $big ? $green : $ink];
    $items[] = ['line' => [40, $y - 32, 555, $y - 32], 'c' => [0.83, 0.91, 0.87]];
    $y -= 62;
}
$items[] = ['t' => 'Thank you. Keep this receipt as proof of payment.', 'x' => 40, 'y' => 60, 's' => 10, 'c' => $grey];
$pdf = simple_pdf($items);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt-' . preg_replace('/[^A-Za-z0-9-]/', '', $p['receipt_no']) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
