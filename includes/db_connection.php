<?php
$host   = 'localhost';
$db     = 'wiyule_motors';
$user   = 'root';
$pass   = 'root';   // MAMP default is 'root' — change if yours differs
$port   = 8889;     // MAMP MySQL default port

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}