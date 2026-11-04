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

$user_id      = (int)$_SESSION['user_id'];
$vehicle_id   = (int)($_POST['vehicle_id'] ?? 0);
$action       = trim($_POST['action'] ?? 'save');
$make         = trim($_POST['make'] ?? '');
$model        = trim($_POST['model'] ?? '');
$year         = trim($_POST['year'] ?? '');
$mileage      = (int)($_POST['mileage'] ?? 0);
$registration = trim($_POST['registration'] ?? '');
$color        = trim($_POST['color'] ?? '');
$engine       = trim($_POST['engine'] ?? '');
$transmission = trim($_POST['transmission'] ?? '');

// DELETE action
if ($action === 'delete' && $vehicle_id > 0) {
    $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $vehicle_id, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Vehicle removed.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not remove vehicle.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Validation
if (empty($make) || empty($model)) {
    echo json_encode(['success' => false, 'message' => 'Make and model are required.']);
    exit;
}

// UPDATE existing vehicle
if ($vehicle_id > 0) {
    $stmt = $conn->prepare("UPDATE vehicles SET make=?, model=?, year=?, registration=?, color=?, mileage=?, engine=?, transmission=? WHERE id=? AND user_id=?");
    // Types: make(s) model(s) year(s) reg(s) color(s) mileage(i) engine(s) transmission(s) vehicle_id(i) user_id(i) = "sssssisssii" — WAIT that's 11 for 10 params
    // Correct: 10 placeholders = 10 type chars: s,s,s,s,s,i,s,s,i,i
    $stmt->bind_param("sssssissii", $make, $model, $year, $registration, $color, $mileage, $engine, $transmission, $vehicle_id, $user_id);
    if ($stmt->execute()) {
        // Sync session so dashboard shows changes immediately
        $_SESSION['vehicle_make']         = $make;
        $_SESSION['vehicle_model']        = $model;
        $_SESSION['vehicle_year']         = $year;
        $_SESSION['vehicle_reg']          = $registration;
        $_SESSION['vehicle_color']        = $color;
        $_SESSION['vehicle_mileage']      = $mileage;
        $_SESSION['vehicle_engine']       = $engine;
        $_SESSION['vehicle_transmission'] = $transmission;
        echo json_encode(['success' => true, 'message' => 'Vehicle updated.', 'vehicle_id' => $vehicle_id]);
    } else {
        error_log("Vehicle update failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Update failed.']);
    }
    $stmt->close();
}
// ADD new vehicle
else {
    // Limit to 5 vehicles per user
    $count = $conn->prepare("SELECT COUNT(*) as c FROM vehicles WHERE user_id = ?");
    $count->bind_param("i", $user_id);
    $count->execute();
    $total = $count->get_result()->fetch_assoc()['c'];
    $count->close();

    if ($total >= 5) {
        echo json_encode(['success' => false, 'message' => 'Maximum 5 vehicles allowed.']);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, registration, color, mileage, engine, transmission) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssiss", $user_id, $make, $model, $year, $registration, $color, $mileage, $engine, $transmission);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $_SESSION['vehicle_make']         = $make;
        $_SESSION['vehicle_model']        = $model;
        $_SESSION['vehicle_year']         = $year;
        $_SESSION['vehicle_mileage']      = $mileage;
        $_SESSION['vehicle_reg']          = $registration;
        $_SESSION['vehicle_color']        = $color;
        $_SESSION['vehicle_engine']       = $engine;
        $_SESSION['vehicle_transmission'] = $transmission;
        echo json_encode(['success' => true, 'message' => 'Vehicle added!', 'vehicle_id' => $new_id]);
    } else {
        error_log("Vehicle insert failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Could not save vehicle.']);
    }
    $stmt->close();
}

$conn->close();
