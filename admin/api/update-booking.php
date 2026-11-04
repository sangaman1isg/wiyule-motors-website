<?php
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

$id      = (int)($_POST['id'] ?? 0);
$status  = trim($_POST['status'] ?? '');
$mileage = isset($_POST['service_mileage']) && $_POST['service_mileage'] !== ''
           ? (int)$_POST['service_mileage']
           : null;

$allowed = ['pending', 'confirmed', 'completed', 'cancelled'];

if (!$id || !in_array($status, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID or status']);
    exit;
}

// When marking as completed, record the service mileage if provided
if ($status === 'completed' && $mileage !== null && $mileage > 0) {
    // Server-side sanity guard: reject obviously impossible readings.
    // (Browser warns the admin; this protects the data even if bypassed.)
    if ($mileage > 9999999) {
        echo json_encode(['success' => false, 'message' => 'Mileage value is unrealistically high.']);
        exit;
    }
    // Find this booking's customer to compare against their last recorded service
    $chk = $conn->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $chk->bind_param("i", $id);
    $chk->execute();
    $chk_row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($chk_row && $chk_row['user_id']) {
        $cu = (int)$chk_row['user_id'];
        $pr = $conn->prepare("SELECT MAX(service_mileage) AS last_km FROM bookings WHERE user_id = ? AND service_mileage > 0 AND id <> ?");
        $pr->bind_param("ii", $cu, $id);
        $pr->execute();
        $pr_row = $pr->get_result()->fetch_assoc();
        $pr->close();
        $last_km = $pr_row['last_km'] ? (int)$pr_row['last_km'] : null;
        // Allow a small tolerance; block only clearly-impossible decreases
        if ($last_km !== null && $mileage < ($last_km - 100)) {
            echo json_encode(['success' => false, 'message' => 'Mileage ('.number_format($mileage).' km) is lower than the last recorded service ('.number_format($last_km).' km). Please re-check — a vehicle\'s mileage cannot decrease.']);
            exit;
        }
    }
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, service_mileage = ? WHERE id = ?");
    $stmt->bind_param("sii", $status, $mileage, $id);
} else {
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
}

if ($stmt->execute() && $stmt->affected_rows >= 0) {
    // If completed with mileage, also update the vehicle's current mileage
    if ($status === 'completed' && $mileage !== null && $mileage > 0) {
        $lookup = $conn->prepare("SELECT user_id, vehicle_make, vehicle_model FROM bookings WHERE id = ?");
        $lookup->bind_param("i", $id);
        $lookup->execute();
        $booking = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($booking && $booking['user_id']) {
            $vupd = $conn->prepare("
                UPDATE vehicles
                SET mileage = ?
                WHERE user_id = ? AND make = ? AND model = ? AND mileage < ?
            ");
            $vupd->bind_param("isssi",
                $mileage,
                $booking['user_id'],
                $booking['vehicle_make'],
                $booking['vehicle_model'],
                $mileage
            );
            $vupd->execute();
            $vupd->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Booking updated',
        'id'      => $id,
        'status'  => $status,
        'mileage' => $mileage
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$stmt->close();
$conn->close();
