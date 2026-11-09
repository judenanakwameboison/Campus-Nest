<?php
// includes/functions.php - shared helpers
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect(string $path): void { header('Location: ' . BASE . $path); exit; }
function money($n): string { return 'GHS ' . number_format((float)$n, 2); }
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function show_flash(): void {
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="alert ' . e($f[0]) . '" role="alert">' . e($f[1]) . '</div>';
    }
    unset($_SESSION['flash']);
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request. Please go back, refresh the page and try again.');
    }
}
function audit(PDO $pdo, string $action, ?string $table = null, ?int $id = null, ?string $details = null): void {
    $st = $pdo->prepare('INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address) VALUES (?,?,?,?,?,?)');
    $st->execute([$_SESSION['user_id'] ?? null, $action, $table, $id, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}
function badge(string $status): string {
    $map = ['vacant'=>'ok','approved'=>'ok','resolved'=>'ok','active'=>'ok','occupied'=>'bad','rejected'=>'bad','ended'=>'bad','high'=>'bad',
            'pending'=>'warn','in_progress'=>'warn','open'=>'warn','maintenance'=>'warn','medium'=>'warn','cancelled'=>'bad','low'=>'ok'];
    return '<span class="b ' . ($map[$status] ?? 'warn') . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}
function public_room_name(int $roomId, string $roomNumber): string {
    return $roomId >= 1 && $roomId <= 4 ? 'sample_' . $roomId . '.jpg' : $roomNumber;
}
function public_room_image(int $roomId, string $roomNumber, ?string $imagePath): ?string {
    return $roomNumber === 'B111' ? 'assets/img/rooms/sample_pano.jpg' : $imagePath;
}
// Inner query: every active tenant with paid, balance and overdue amounts
function tenant_money_sql(): string {
    return "SELECT t.tenant_id, t.user_id, u.full_name, u.phone, r.room_number, t.monthly_rent, t.total_due,
                   t.lease_start, t.lease_end, COALESCE(p.paid,0) AS paid,
                   t.total_due - COALESCE(p.paid,0) AS balance,
                   LEAST(t.total_due, t.monthly_rent * GREATEST(TIMESTAMPDIFF(MONTH, t.lease_start, CURDATE()) + 1, 0)) - COALESCE(p.paid,0) AS overdue
            FROM tenants t
            JOIN users u ON u.user_id = t.user_id
            JOIN rooms r ON r.room_id = t.room_id
            LEFT JOIN (SELECT tenant_id, SUM(amount) AS paid FROM payments GROUP BY tenant_id) p ON p.tenant_id = t.tenant_id
            WHERE t.status = 'active'";
}

// Room search used by the public page and the JSON API
function search_rooms(PDO $pdo, string $q, int $type, float $max): array {
    $sql = "SELECT r.room_id, r.room_number, r.floor, r.status, r.image_path, t.type_name, t.capacity, t.price_per_month,
                   (SELECT COUNT(*) FROM bookings b WHERE b.room_id = r.room_id AND b.status = 'pending') AS pending,
                   (SELECT ROUND(AVG(v.rating),1) FROM reviews v WHERE v.room_id = r.room_id) AS rating,
                   (SELECT COUNT(*) FROM reviews v WHERE v.room_id = r.room_id) AS reviews
            FROM rooms r JOIN room_types t ON t.room_type_id = r.room_type_id WHERE 1 = 1";
    $p = [];
    if ($q !== '')  {
        $sql .= " AND (r.room_number LIKE ? OR t.type_name LIKE ? OR (r.room_id BETWEEN 1 AND 4 AND CONCAT('sample_', r.room_id, '.jpg') LIKE ?))";
        $p[] = "%$q%"; $p[] = "%$q%"; $p[] = "%$q%";
    }
    if ($type > 0)  { $sql .= " AND t.room_type_id = ?"; $p[] = $type; }
    if ($max > 0)   { $sql .= " AND t.price_per_month <= ?"; $p[] = $max; }
    $sql .= " ORDER BY r.room_number";
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rooms = $st->fetchAll();
    foreach ($rooms as &$room) {
        $room['public_name'] = public_room_name((int)$room['room_id'], (string)$room['room_number']);
        $room['image_path'] = public_room_image((int)$room['room_id'], (string)$room['room_number'], $room['image_path']);
    }
    unset($room);
    return $rooms;
}

// ---------- notifications ----------
function notify(PDO $pdo, int $userId, string $title, ?string $body = null, ?string $link = null): void {
    $pdo->prepare('INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)')->execute([$userId, $title, $body, $link]);
}
function notify_admins(PDO $pdo, string $title, ?string $body = null, ?string $link = null): void {
    $ids = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) { notify($pdo, (int)$id, $title, $body, $link); }
}
function stars($rating): string {
    $n = (int)round((float)$rating);
    return str_repeat("\u{2605}", $n) . str_repeat("\u{2606}", 5 - $n);
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- payments ----------
// Records a payment inside a transaction and returns the new payment id (throws RuntimeException on bad input)
function record_payment(PDO $pdo, int $tenantId, float $amount, string $date, string $method, ?string $reference, int $byUser): int {
    $pdo->beginTransaction();
    try {
        $t = $pdo->prepare("SELECT total_due, (SELECT COALESCE(SUM(amount),0) FROM payments WHERE tenant_id = tenants.tenant_id) AS paid FROM tenants WHERE tenant_id = ? AND status = 'active' FOR UPDATE");
        $t->execute([$tenantId]); $t = $t->fetch();
        if (!$t) { throw new RuntimeException('Active tenant not found.'); }
        $amount = round($amount, 2);
        $balance = round($t['total_due'] - $t['paid'], 2);
        if ($amount <= 0) { throw new RuntimeException('The amount must be greater than zero.'); }
        if ($amount > $balance) { throw new RuntimeException('That is more than the balance of ' . money($balance) . '.'); }
        $pdo->prepare('INSERT INTO payments (tenant_id, amount, payment_date, method, reference, receipt_no, recorded_by) VALUES (?,?,?,?,?,?,?)')
            ->execute([$tenantId, $amount, $date, $method, $reference, 'TMP' . bin2hex(random_bytes(5)), $byUser]);
        $pid = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE payments SET receipt_no = ? WHERE payment_id = ?')->execute(['RC-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT), $pid]);
        $pdo->commit();
        return $pid;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

// ---------- tiny PDF writer (no libraries needed) ----------
function pdf_escape(string $s): string {
    $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    if ($c === false) { $c = ''; }
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $c);
}
function simple_pdf(array $items): string {
    $c = '';
    foreach ($items as $it) {
        $col = $it['c'] ?? [0, 0, 0];
        $rg = sprintf('%.3F %.3F %.3F', $col[0], $col[1], $col[2]);
        if (isset($it['rect'])) {
            $r = $it['rect'];
            $c .= $rg . ' rg ' . $r[0] . ' ' . $r[1] . ' ' . $r[2] . ' ' . $r[3] . " re f\n";
        } elseif (isset($it['line'])) {
            $l = $it['line'];
            $c .= $rg . ' RG 0.8 w ' . $l[0] . ' ' . $l[1] . ' m ' . $l[2] . ' ' . $l[3] . " l S\n";
        } else {
            $font = !empty($it['b']) ? '/F2' : '/F1';
            $c .= 'BT ' . $rg . ' rg ' . $font . ' ' . $it['s'] . ' Tf ' . $it['x'] . ' ' . $it['y'] . ' Td (' . pdf_escape($it['t']) . ") Tj ET\n";
        }
    }
    $objs = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        6 => '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . 'endstream',
    ];
    $pdf = "%PDF-1.4\n"; $off = [];
    foreach ($objs as $n => $o) { $off[$n] = strlen($pdf); $pdf .= $n . " 0 obj\n" . $o . "\nendobj\n"; }
    $xr = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($objs as $n => $o) { $pdf .= sprintf("%010d 00000 n \n", $off[$n]); }
    return $pdf . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xr . "\n%%EOF";
}

// ---------- image uploads ----------
// Validates and stores one uploaded image; returns the path relative to the project root
function save_room_image(string $tmp, int $size, int $roomId, int $maxBytes): string {
    if ($size > $maxBytes) { throw new RuntimeException('An image is larger than ' . round($maxBytes / 1048576) . ' MB.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || !getimagesize($tmp)) { throw new RuntimeException('Only JPG, PNG or WebP images are allowed.'); }
    $dir = __DIR__ . '/../assets/img/rooms';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { throw new RuntimeException('Could not create the upload folder.'); }
    $name = 'room_' . $roomId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) { throw new RuntimeException('Could not save the image.'); }
    return 'assets/img/rooms/' . $name;
}
function delete_room_file(?string $path): void {
    if ($path && strpos($path, 'assets/img/rooms/room_') === 0) { @unlink(__DIR__ . '/../' . $path); }   // never delete the bundled samples
}
