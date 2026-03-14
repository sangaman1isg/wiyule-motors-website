<?php
session_start();
require_once '../includes/db_connection.php';

// Errors should be logged, not displayed in production
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Validate input
if (
    !isset($_POST['full_name'], $_POST['email'], $_POST['password'])
) {
    die("Invalid form submission.");
}

$full_name = trim($_POST['full_name']);
$email     = trim($_POST['email']);
$phone     = trim($_POST['phone'] ?? '');
$password  = $_POST['password'];

// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Prepare insert
$stmt = $conn->prepare("
    INSERT INTO users (full_name, email, phone, password)
    VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "ssss",
    $full_name,
    $email,
    $phone,
    $hashed
);

if ($stmt->execute()) {

    // Auto-login after signup
    $_SESSION['user_id']    = $stmt->insert_id;
    $_SESSION['user_name'] = $full_name;

    $name_parts = explode(' ', $full_name, 2);
    $_SESSION['first_name'] = $name_parts[0];
    $_SESSION['last_name']  = $name_parts[1] ?? '';

    header("Location: dashboard.php");
    exit;

} else {

    // Duplicate email error
    if ($conn->errno == 1062) {
        echo "❌ This email is already registered.";
    } else {
        echo "❌ Signup error: " . $conn->error;
    }
}
?>