<?php
$host   = 'localhost';
$dbname = 'wiyule_motors';
$dbuser = 'root';       // your MySQL username
$dbpass = '';           // your MySQL password

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}