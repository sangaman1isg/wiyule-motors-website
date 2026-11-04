<?php
// ── process-login.php ─────────────────────────────────────────
// Kept for compatibility. login.php now handles its own POST.
// This file is a clean backup processor.
session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . $base . "/pages/login.php");
    exit;
}

if (!isset($_POST['email'], $_POST['password'])) {
    header("Location: " . $base . "/pages/login.php?error=missing_fields");
    exit;
}

$email    = trim($_POST['email']);
$password = $_POST['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . $base . "/pages/login.php?error=invalid_email");
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['user_name'] = $user['full_name'];

        $name_parts             = explode(' ', $user['full_name'], 2);
        $_SESSION['first_name'] = $name_parts[0];
        $_SESSION['last_name']  = $name_parts[1] ?? '';
        
        // After successful registration/login for new users
        $_SESSION['user_created'] = time();
        $_SESSION['onboarding_completed'] = false;

        $stmt->close();
        header("Location: " . $base . "/pages/dashboard.php");
        exit;
    }
}

// ── Vague error — don't reveal if email exists ────────────────
$stmt->close();
header("Location: " . $base . "/pages/login.php?error=invalid_credentials");
exit;