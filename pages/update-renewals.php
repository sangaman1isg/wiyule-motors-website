<?php
// pages/update-renewals.php — a customer sets their vehicle's COF / road-tax /
// insurance expiry dates (for renewal reminders). Owner-gated.
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$vid = (int)($_POST['vehicle_id'] ?? 0);

$dateOrNull = function ($s) {
    $s = trim((string)$s);
    if ($s === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
};
$cof = $dateOrNull($_POST['cof_expiry']       ?? '');
$road= $dateOrNull($_POST['road_tax_expiry']  ?? '');
$ins = $dateOrNull($_POST['insurance_expiry'] ?? '');

if ($vid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle.']);
    exit;
}

$stmt = $conn->prepare("UPDATE vehicles SET cof_expiry=?, road_tax_expiry=?, insurance_expiry=? WHERE id=? AND user_id=?");
$stmt->bind_param('sssii', $cof, $road, $ins, $vid, $uid);
$stmt->execute();
$stmt->close();

// Confirm ownership + read back.
$chk = $conn->prepare("SELECT cof_expiry, road_tax_expiry, insurance_expiry FROM vehicles WHERE id=? AND user_id=? LIMIT 1");
$chk->bind_param('ii', $vid, $uid);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
    exit;
}

echo json_encode(['success' => true] + $row);
