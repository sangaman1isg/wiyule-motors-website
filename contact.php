<?php
/**
 * Contact Form Handler for Wiyule Motors
 * 
 * Instructions:
 * 1. Upload this file to your server
 * 2. Update the email address below
 * 3. Update index.html line ~685 to use this file:
 *    fetch('contact.php', { method: 'POST', body: JSON.stringify(formData) })
 */

// CONFIGURE THIS - Your email address
$to_email = "info@wiyulemotors.com";

// CORS headers (if needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate required fields
if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars(strip_tags($data['name']));
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(strip_tags($data['phone'] ?? 'Not provided'));
$message = htmlspecialchars(strip_tags($data['message']));

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Email subject
$subject = "Contact Form Submission from $name";

// Email body
$email_body = "
New Contact Form Submission

Name: $name
Email: $email
Phone: $phone

Message:
$message

---
Sent from Wiyule Motors website
Time: " . date('Y-m-d H:i:s') . "
";

// Email headers
$headers = "From: $email\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email
if (mail($to_email, $subject, $email_body, $headers)) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! We will get back to you soon.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error sending your message. Please try again or call us directly.'
    ]);
}
?>
