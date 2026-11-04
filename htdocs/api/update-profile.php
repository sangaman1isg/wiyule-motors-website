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

$user_id   = (int)$_SESSION['user_id'];
$action    = trim($_POST['action'] ?? 'profile');

// ── UPDATE PROFILE ──────────────────────────────────────────
if ($action === 'profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $city       = trim($_POST['city'] ?? '');

    if (empty($first_name) || empty($last_name)) {
        echo json_encode(['success' => false, 'message' => 'Name fields are required.']);
        exit;
    }

    $full_name = $first_name . ' ' . $last_name;

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $full_name, $phone, $user_id);

    if ($stmt->execute()) {
        $_SESSION['user_name']  = $full_name;
        $_SESSION['first_name'] = $first_name;
        $_SESSION['last_name']  = $last_name;
        $_SESSION['phone']      = $phone;   // Dashboard reads this one
        $_SESSION['user_phone'] = $phone;   // Keep for backward compat
        $_SESSION['city']       = $city;
        echo json_encode(['success' => true, 'message' => 'Profile updated!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not update profile.']);
    }
    $stmt->close();
}

// ── CHANGE PASSWORD ─────────────────────────────────────────
elseif ($action === 'password') {
    $current  = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new_pass) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
        exit;
    }

    if ($new_pass !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }

    if (strlen($new_pass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $hash = password_hash($new_pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hash, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not change password.']);
    }
    $stmt->close();
}

$conn->close();
