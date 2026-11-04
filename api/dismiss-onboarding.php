<?php
session_start();
header('Content-Type: application/json');

$_SESSION['onboarding_dismissed'] = true;

echo json_encode(['success' => true]);
