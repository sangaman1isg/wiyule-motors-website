<?php
include '../includes/config.php';

$full_name = $_POST['full_name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$sql = "INSERT INTO users (full_name, email, phone, password) 
        VALUES ('$full_name', '$email', '$phone', '$password')";

if (mysqli_query($conn, $sql)) {
    echo "Account created successfully!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>