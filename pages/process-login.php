<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_POST['email'], $_POST['password'])) {
    die("Invalid request.");
}

$email    = trim($_POST['email']);
$password = $_POST['password'];

// Prepare statement
$stmt = $conn->prepare("SELECT id, full_name, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        // Set session
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];

        // Split name
        $name_parts = explode(' ', $user['full_name'], 2);
        $_SESSION['first_name'] = $name_parts[0];
        $_SESSION['last_name']  = $name_parts[1] ?? '';

        header("Location: dashboard.php");
        exit;

    } else {
        echo "❌ Incorrect password.";
    }

} else {
    echo "❌ Account not found.";
}
?>