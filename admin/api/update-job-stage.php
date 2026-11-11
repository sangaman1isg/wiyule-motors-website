<?php
// admin/api/update-job-stage.php — admin sets a booking's live repair stage.
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';

$id    = (int)($_POST['id'] ?? 0);
$stage = trim($_POST['stage'] ?? '');

// '' clears the stage; otherwise one of the known stages.
$allowed = ['', 'received', 'in_progress', 'ready'];
if (!$id || !in_array($stage, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking or stage.']);
    exit;
}

$stmt = $conn->prepare("UPDATE bookings SET job_stage = ? WHERE id = ?");
$stmt->bind_param('si', $stage, $id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => (bool)$ok, 'stage' => $stage]);
