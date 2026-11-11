<?php
// pages/update-mileage.php — a logged-in customer updates their own vehicle's odometer.
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

$uid     = (int)$_SESSION['user_id'];
$vid     = (int)($_POST['vehicle_id'] ?? 0);
$mileage = (int)($_POST['mileage'] ?? -1);

if ($vid <= 0 || $mileage < 0 || $mileage > 9999999) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid mileage (0–9,999,999 km).']);
    exit;
}

// Update only if the vehicle belongs to this customer.
$stmt = $conn->prepare("UPDATE vehicles SET mileage = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param('iii', $mileage, $vid, $uid);
$stmt->execute();
$stmt->close();

// Confirm ownership + read back (affected_rows can be 0 when the value is unchanged).
$chk = $conn->prepare("SELECT mileage FROM vehicles WHERE id = ? AND user_id = ? LIMIT 1");
$chk->bind_param('ii', $vid, $uid);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
    exit;
}

echo json_encode(['success' => true, 'mileage' => (int)$row['mileage']]);
