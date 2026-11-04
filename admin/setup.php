<?php
// ============================================================
// Wiyule Motors — Admin Setup / Password Reset Script
// Visit this page ONCE to create OR reset the admin account.
// Then DELETE this file immediately for security!
// ============================================================

// ── Kill-switch: inert unless explicitly enabled in secrets.php ──
require_once __DIR__ . '/../includes/secrets.php';
if (!defined('WM_ALLOW_SETUP_TOOLS') || WM_ALLOW_SETUP_TOOLS !== true) {
    http_response_code(404);
    exit('Not found');
}

require_once __DIR__ . '/../includes/db_connection.php';

echo "<style>body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px;line-height:1.6}code{background:#f1f5f9;padding:2px 8px;border-radius:4px}.ok{color:#16a34a}.warn{color:#dc2626;font-weight:bold}</style>";
echo "<h2>Wiyule Motors — Admin Setup</h2><hr>";

// Step 1: Add is_admin column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0 AFTER `phone`");
    echo "<p class='ok'>✅ Added is_admin column to users table</p>";
} else {
    echo "<p class='ok'>✅ is_admin column already exists</p>";
}

// Step 2: Create OR reset admin account
$email    = 'admin@wiyulemotors.com';
$password = WM_ADMIN_SETUP_PASSWORD;   // defined in the untracked secrets.php
$hash     = password_hash($password, PASSWORD_BCRYPT);

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    // Account doesn't exist — create it
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, is_admin) VALUES (?, ?, ?, ?, 1)");
    $name  = 'Wiyule Admin';
    $phone = '+265993575111';
    $stmt->bind_param("ssss", $name, $email, $hash, $phone);
    if ($stmt->execute()) {
        echo "<p class='ok'>✅ Admin account CREATED!</p>";
    } else {
        echo "<p class='warn'>❌ Error: " . htmlspecialchars($conn->error) . "</p>";
    }
    $stmt->close();
} else {
    // Account exists — RESET its password and ensure is_admin = 1
    $stmt = $conn->prepare("UPDATE users SET password = ?, is_admin = 1 WHERE email = ?");
    $stmt->bind_param("ss", $hash, $email);
    if ($stmt->execute()) {
        echo "<p class='ok'>✅ Admin account already existed — password has been RESET and admin access confirmed.</p>";
    } else {
        echo "<p class='warn'>❌ Error: " . htmlspecialchars($conn->error) . "</p>";
    }
    $stmt->close();
}
$check->close();

echo "<hr>";
echo "<p><strong>Your Admin Login:</strong></p>";
echo "<p>Email: <code>admin@wiyulemotors.com</code></p>";
echo "<p>Password: <code>" . htmlspecialchars($password) . "</code></p>";
echo "<p>Login here: <a href='login.php'>Admin Login Page</a></p>";
echo "<hr>";
echo "<p class='warn'>⚠️ IMPORTANT: DELETE THIS FILE (admin/setup.php) NOW for security!</p>";
echo "<p>Anyone who visits this page can reset your admin password. Delete it via cPanel File Manager.</p>";

$conn->close();
?>
