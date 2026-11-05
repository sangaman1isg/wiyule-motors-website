<?php
// admin/api/delete-customer.php — delete a registered customer (admin only).
// Removes the user + their saved vehicles; past bookings are kept but unlinked.
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Missing customer id']); exit; }

// Look up the target; never allow deleting an admin account through this endpoint.
$chk = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$chk->bind_param('i', $id);
$chk->execute();
$u = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$u) { echo json_encode(['success' => false, 'message' => 'Customer not found']); exit; }
if ((int)$u['is_admin'] === 1) { echo json_encode(['success' => false, 'message' => 'Cannot delete an admin account']); exit; }

$conn->begin_transaction();
try {
    // Keep booking history but unlink it from the deleted account
    $s = $conn->prepare("UPDATE bookings SET user_id = NULL WHERE user_id = ?");
    $s->bind_param('i', $id); $s->execute(); $s->close();

    // Remove their saved vehicles
    $s = $conn->prepare("DELETE FROM vehicles WHERE user_id = ?");
    $s->bind_param('i', $id); $s->execute(); $s->close();

    // Delete the account (guard is_admin again at the SQL level)
    $s = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
    $s->bind_param('i', $id); $s->execute();
    $deleted = $s->affected_rows; $s->close();

    $conn->commit();
    echo json_encode($deleted > 0
        ? ['success' => true, 'message' => 'Customer deleted']
        : ['success' => false, 'message' => 'Nothing was deleted']);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
