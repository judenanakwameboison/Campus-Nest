<?php
// api/rooms.php - JSON endpoint used by the live room search (Fetch)
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
$rooms = search_rooms($pdo, trim((string)($_GET['q'] ?? '')), (int)($_GET['type'] ?? 0), (float)($_GET['max'] ?? 0));
echo json_encode($rooms, JSON_UNESCAPED_UNICODE);
