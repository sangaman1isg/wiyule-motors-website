<?php
// ── process-signup.php ────────────────────────────────────────
// Kept for compatibility but signup.php now handles its own POST.
// This file is a clean backup processor.
session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require_once __DIR__ . '/../includes/db_connection.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base . "/pages/signup.php");
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name']  ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone      = trim($_POST['phone']      ?? '');
$password   = $_POST['password']        ?? '';
$full_name  = trim($first_name . ' ' . $last_name);

// Validate
if (!$first_name || !$last_name || !$email || !$password) {
    header("Location: " . $base . "/pages/signup.php?error=missing_fields");
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $base . "/pages/signup.php?error=invalid_email");
    exit;
}
if (strlen($password) < 8) {
    header("Location: " . $base . "/pages/signup.php?error=short_password");
    exit;
}

// Check duplicate email
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    header("Location: " . $base . "/pages/signup.php?error=email_exists");
    exit;
}
$stmt->close();

// Hash & insert
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $hash, $phone);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    session_regenerate_id(true);
    $_SESSION['user_id']    = $new_id;
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name']  = $last_name;
    $_SESSION['user_name']  = $full_name;
    $_SESSION['email']      = $email;
    $_SESSION['phone']      = $phone;
    header("Location: " . $base . "/pages/dashboard.php");
    exit;
} else {
    error_log("process-signup insert error: " . $conn->error);
    header("Location: " . $base . "/pages/signup.php?error=server_error");
    exit;
}