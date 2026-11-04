<?php
// Set timezone for all pages that include this file
date_default_timezone_set('Africa/Blantyre');

// Load credentials from untracked config file (see db_config.php)
$cfg = require __DIR__ . '/db_config.php';

$conn = new mysqli(
    $cfg['host'],
    $cfg['user'],
    $cfg['pass'],
    $cfg['db']
);

if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    die("Service temporarily unavailable. Please try again later.");
}

$conn->set_charset("utf8mb4");
?>
