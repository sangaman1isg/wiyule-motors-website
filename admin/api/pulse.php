<?php
// admin/api/pulse.php — lightweight JSON heartbeat for the live dashboard.
// Returns current counts so the admin page can update without a full reload.
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';

$out = ['ok' => true];
$out['pending'] = (int)($conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$out['total']   = (int)($conn->query("SELECT COUNT(*) c FROM bookings")->fetch_assoc()['c'] ?? 0);

$row = $conn->query("SELECT id, full_name, vehicle_make, vehicle_model FROM bookings ORDER BY id DESC LIMIT 1")->fetch_assoc();
$out['newest_id'] = $row ? (int)$row['id'] : 0;
$out['newest']    = $row ? [
    'name'    => $row['full_name'],
    'vehicle' => trim(($row['vehicle_make'] ?? '') . ' ' . ($row['vehicle_model'] ?? '')),
] : null;

$out['overdue'] = (int)(@$conn->query("SELECT COUNT(*) c FROM invoices WHERE type='invoice' AND payment_status IN ('unpaid','partial') AND archived_at IS NULL AND due_date IS NOT NULL AND due_date < CURDATE()")->fetch_assoc()['c'] ?? 0);

echo json_encode($out);
