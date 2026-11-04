<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db_connection.php';

$user_id    = (int)$_SESSION['user_id'];
$booking_id = (int)($_POST['booking_id'] ?? 0);
$action     = trim($_POST['action'] ?? '');

if (!$booking_id || !in_array($action, ['cancel', 'reschedule'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Verify the booking belongs to this user and is cancellable
$stmt = $conn->prepare("SELECT id, status, services, booking_date FROM bookings WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (!in_array($booking['status'], ['pending', 'confirmed'])) {
    echo json_encode(['success' => false, 'message' => 'This booking cannot be modified.']);
    exit;
}

if ($action === 'cancel') {
    $stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not cancel booking.']);
    }
    $stmt->close();
}

$conn->close();
