<?php
// pages/submit-feedback.php — stores a customer's rating of how the online booking felt.
// Only the visitor who made the booking (same session) can rate it; one row per booking.
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 0);
$comment    = trim((string)($_POST['comment'] ?? ''));
if (strlen($comment) > 1000) $comment = substr($comment, 0, 1000);
$comment    = ($comment === '') ? null : $comment;

// Validate
if ($booking_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid feedback.']);
    exit;
}

// Authorise: this session must have created the booking it's rating.
$owned = in_array($booking_id, $_SESSION['recent_booking_ids'] ?? [], true);
if (!$owned) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed.']);
    exit;
}

// Upsert — tapping a face records the rating; a later comment updates the same row.
$stmt = $conn->prepare(
    "INSERT INTO booking_feedback (booking_id, rating, comment)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)"
);
$stmt->bind_param('iis', $booking_id, $rating, $comment);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => (bool)$ok]);
