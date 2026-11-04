<?php
// admin/api/send-reminders.php — owner-triggered "send reminders now" (admin only).
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/reminders.php';   // provides $conn + functions

$dry = isset($_POST['dryrun']) && $_POST['dryrun'] == '1';
$res = wm_run_reminders($conn, $dry);
$sent = $res['payments']['sent'] + $res['quotes']['sent'];
$would = $res['payments']['would_send'] + $res['quotes']['would_send'];
echo json_encode([
    'success' => true,
    'message' => $dry
        ? ($would . ' reminder(s) would be sent')
        : ($sent === 0 ? 'No reminders needed right now' : $sent . ' reminder(s) sent'),
    'result'  => $res,
]);
