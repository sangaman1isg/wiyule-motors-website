<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json');

$full_name    = trim($_POST['name']         ?? '');
$phone        = trim($_POST['phone']        ?? '');
$email        = trim($_POST['email']        ?? '');
$vehicle_make = trim($_POST['make']         ?? '');
$vehicle_model= trim($_POST['model']        ?? '');
$vehicle_year = trim($_POST['year']         ?? '');
$registration = trim($_POST['registration'] ?? '');

// Accept either `services` (plural — from dashboard) or `service` (singular — from /book.php)
$services_raw = $_POST['services'] ?? $_POST['service'] ?? '';
$services     = is_array($services_raw)
    ? implode(', ', array_map('trim', $services_raw))
    : trim($services_raw);

$booking_date = trim($_POST['date']         ?? '');
$booking_time = trim($_POST['time']         ?? '');
$notes        = trim($_POST['notes']        ?? '');
$source       = trim($_POST['source']       ?? '');
$claim_offer  = isset($_POST['claim_offer']) && $_POST['claim_offer'] == '1';

// ── Validate source against known values (defensive — prevents arbitrary data) ──
// Empty source is allowed (customer skipped the optional field)
$allowed_sources = ['Google', 'Friend', 'WhatsApp', 'Instagram', 'Facebook',
                    'Walked past', 'Returning customer', 'Other'];
if ($source !== '' && !in_array($source, $allowed_sources, true)) {
    $source = 'Other';   // Anything unexpected gets bucketed as 'Other' rather than rejected
}
// Convert empty string to NULL so the database stores it cleanly
$source = ($source === '') ? null : $source;

// ── Sensible defaults for fields that the slim /book.php form doesn't collect ──
// (Vehicle details and exact time are captured later by admin when customer arrives.)
if ($vehicle_make  === '') $vehicle_make  = 'TBD';
if ($vehicle_model === '') $vehicle_model = 'TBD';
if ($vehicle_year  === '' || !is_numeric($vehicle_year)) $vehicle_year = (string)date('Y');
if ($booking_time  === '') $booking_time = '09:00:00';

// ── Required fields (the genuine minimums) ──
$errors = [];
if (empty($full_name))    $errors[] = 'Full name is required.';
if (empty($phone))        $errors[] = 'Phone number is required.';
if (empty($services))     $errors[] = 'Please select a service.';
if (empty($booking_date)) $errors[] = 'Booking date is required.';

if (!empty($booking_date)) {
    $today    = new DateTime('today');
    $selected = DateTime::createFromFormat('Y-m-d', $booking_date);
    if (!$selected || $selected < $today) {
        $errors[] = 'Booking date must be today or in the future.';
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

// ── FIRST-TIME DETECTION & OFFER VALIDATION ─────────────────
// Step 1: Detect if this is a first-time customer (by phone number)
// This runs for EVERY booking regardless of claim, so WhatsApp can alert you
$phone_check = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE phone = ?");
$phone_check->bind_param("s", $phone);
$phone_check->execute();
$prev_bookings_count = (int)$phone_check->get_result()->fetch_assoc()['cnt'];
$phone_check->close();

$is_first_time = ($prev_bookings_count === 0);

// Step 2: Apply discount ONLY if:
// - Customer is first-time (phone never used)
// - They checked the claim box
// - They're logged in (so we can track)
// - Their account hasn't already used the offer
$first_time_discount = 0;
$offer_message = '';

if ($claim_offer && $is_first_time && $user_id) {
    $check = $conn->prepare("SELECT first_offer_used FROM users WHERE id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $user_row = $check->get_result()->fetch_assoc();
    $check->close();

    $already_used = $user_row ? (int)$user_row['first_offer_used'] : 1;

    if ($already_used == 0) {
        $first_time_discount = 1;
        $offer_message = ' 🎉 10% FIRST-TIME DISCOUNT applied!';
    } else {
        $offer_message = ' (First-time offer already used on this account)';
    }
} elseif ($claim_offer && !$is_first_time) {
    $offer_message = ' (First-time offer not valid — phone number already has a booking)';
} elseif ($claim_offer && !$user_id) {
    $offer_message = ' (Please sign in to claim the first-time offer)';
}

// ── Insert booking ──────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO bookings
        (user_id, full_name, phone, email,
         vehicle_make, vehicle_model, vehicle_year, registration,
         services, booking_date, booking_time, notes, source, first_time_discount)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssssssssssi",
    $user_id, $full_name, $phone, $email,
    $vehicle_make, $vehicle_model, $vehicle_year, $registration,
    $services, $booking_date, $booking_time, $notes, $source, $first_time_discount
);

if ($stmt->execute()) {
    $booking_id = $stmt->insert_id;

    // Mark the first-time offer as used (only if valid and applied)
    if ($first_time_discount == 1 && $user_id) {
        $mark = $conn->prepare("UPDATE users SET first_offer_used = 1 WHERE id = ?");
        $mark->bind_param("i", $user_id);
        $mark->execute();
        $mark->close();
    }

    echo json_encode([
        'success'             => true,
        'booking_id'          => $booking_id,
        'first_time_discount' => $first_time_discount,
        'is_first_time'       => $is_first_time,
        'offer_message'       => $offer_message,
        'message'             => 'Booking confirmed successfully.' . $offer_message,
        'data'                => [
            'name'         => $full_name,
            'phone'        => $phone,
            'email'        => $email,
            'vehicle'      => [
                'make'         => $vehicle_make,
                'model'        => $vehicle_model,
                'year'         => $vehicle_year,
                'registration' => $registration,
            ],
            'services'            => explode(', ', $services),
            'date'                => $booking_date,
            'time'                => $booking_time,
            'notes'               => $notes,
            'first_time_discount' => $first_time_discount,
            'is_first_time'       => $is_first_time,
        ]
    ]);
} else {
    error_log("Booking insert failed: " . $conn->error);
    echo json_encode([
        'success' => false,
        'message' => 'Could not save your booking. Please call us at +265 993 575 111.'
    ]);
}

$stmt->close();
$conn->close();
