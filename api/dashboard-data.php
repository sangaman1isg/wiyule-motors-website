<?php
session_start();

header('Content-Type: application/json');

// Return current dashboard data
// Replace with actual database queries
echo json_encode([
    'stats' => [
        count($_SESSION['service_history'] ?? []),
        count($_SESSION['upcoming_bookings'] ?? []),
        5000,
        'MWK 8K'
    ],
    'reminders' => $_SESSION['reminders'] ?? [],
    'bookings' => $_SESSION['upcoming_bookings'] ?? [],
    'notifications' => $_SESSION['notifications'] ?? []
]);
?>