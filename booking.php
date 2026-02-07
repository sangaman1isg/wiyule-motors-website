<?php
/**
 * Booking Form Handler for Wiyule Motors
 * 
 * Instructions:
 * 1. Upload this file to your server
 * 2. Update the email address below
 * 3. Update index.html line ~740 to use this file:
 *    fetch('booking.php', { method: 'POST', body: JSON.stringify(formData) })
 */

// CONFIGURE THIS - Your email address
$to_email = "bookings@wiyulemotors.com";

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
$required = ['name', 'phone', 'vehicle', 'services', 'date', 'time'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize inputs
$name = htmlspecialchars(strip_tags($data['name']));
$phone = htmlspecialchars(strip_tags($data['phone']));
$email = !empty($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : 'Not provided';
$vehicle_make = htmlspecialchars(strip_tags($data['vehicle']['make']));
$vehicle_model = htmlspecialchars(strip_tags($data['vehicle']['model']));
$vehicle_year = htmlspecialchars(strip_tags($data['vehicle']['year']));
$vehicle_reg = htmlspecialchars(strip_tags($data['vehicle']['registration'] ?? 'Not provided'));
$services = is_array($data['services']) ? implode(', ', array_map('htmlspecialchars', $data['services'])) : 'None';
$date = htmlspecialchars(strip_tags($data['date']));
$time = htmlspecialchars(strip_tags($data['time']));
$notes = htmlspecialchars(strip_tags($data['notes'] ?? 'No additional notes'));

// Email subject
$subject = "New Service Booking from $name";

// Email body
$email_body = "
New Service Booking Request

CUSTOMER INFORMATION:
Name: $name
Phone: $phone
Email: $email

VEHICLE INFORMATION:
Make: $vehicle_make
Model: $vehicle_model
Year: $vehicle_year
Registration: $vehicle_reg

BOOKING DETAILS:
Services Requested: $services
Preferred Date: $date
Preferred Time: $time

ADDITIONAL NOTES:
$notes

---
Sent from Wiyule Motors website
Submission Time: " . date('Y-m-d H:i:s') . "

IMPORTANT: Please call the customer to confirm this appointment.
";

// Email headers
$headers = "From: noreply@wiyulemotors.com\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email
if (mail($to_email, $subject, $email_body, $headers)) {
    // Optional: Send confirmation email to customer
    if ($email !== 'Not provided' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $customer_subject = "Booking Confirmation - Wiyule Motors";
        $customer_body = "
Dear $name,

Thank you for booking with Wiyule Motors!

We have received your booking request for:
Date: $date at $time
Services: $services
Vehicle: $vehicle_year $vehicle_make $vehicle_model

We will contact you shortly at $phone to confirm your appointment.

If you have any questions, please don't hesitate to contact us at +265 993 575 111.

Best regards,
Wiyule Motors Team
Nyambadwe, Blantyre
";
        $customer_headers = "From: noreply@wiyulemotors.com\r\n";
        mail($email, $customer_subject, $customer_body, $customer_headers);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Booking confirmed! We will contact you shortly.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error processing your booking. Please call us at +265 993 575 111.'
    ]);
}

// Optional: Save to database
// Uncomment and configure if you want to store bookings in a database
/*
try {
    $pdo = new PDO('mysql:host=localhost;dbname=wiyule_motors', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("
        INSERT INTO bookings (name, phone, email, vehicle_make, vehicle_model, vehicle_year, 
                              vehicle_registration, services, booking_date, booking_time, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $name, $phone, $email, $vehicle_make, $vehicle_model, $vehicle_year,
        $vehicle_reg, $services, $date, $time, $notes
    ]);
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
*/
?>
