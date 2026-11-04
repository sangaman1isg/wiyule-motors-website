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
$services     = trim($_POST['services']     ?? '');
$booking_date = trim($_POST['date']         ?? '');
$booking_time = trim($_POST['time']         ?? '');
$notes        = trim($_POST['notes']        ?? '');
$claim_offer  = isset($_POST['claim_offer']) && $_POST['claim_offer'] == '1';

$errors = [];
if (empty($full_name))                                  $errors[] = 'Full name is required.';
if (empty($phone))                                      $errors[] = 'Phone number is required.';
if (empty($vehicle_make))                               $errors[] = 'Vehicle make is required.';
if (empty($vehicle_model))                              $errors[] = 'Vehicle model is required.';
if (empty($vehicle_year) || !is_numeric($vehicle_year)) $errors[] = 'Valid vehicle year is required.';
if (empty($services))                                   $errors[] = 'Please select at least one service.';
if (empty($booking_date))                               $errors[] = 'Booking date is required.';
if (empty($booking_time))                               $errors[] = 'Booking time is required.';

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

// ── FIRST-TIME OFFER VALIDATION ─────────────────────────────
// Only qualifies if:
// 1. User claims the offer in this booking
// 2. User is logged in (must have account to track)
// 3. User hasn't used it before (first_offer_used = 0)
// 4. User has no previous bookings (extra safety check)
// 5. Phone number hasn't been used in previous booking (guest safety)
$first_time_discount = 0;
$offer_message = '';

if ($claim_offer && $user_id) {
    // Check 1: Has user already used their first offer?
    $check = $conn->prepare("SELECT first_offer_used FROM users WHERE id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $user_row = $check->get_result()->fetch_assoc();
    $check->close();

    $already_used = $user_row ? (int)$user_row['first_offer_used'] : 1;

    if ($already_used == 0) {
        // Check 2: Does user have any existing bookings? (extra safety)
        $check2 = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE user_id = ?");
        $check2->bind_param("i", $user_id);
        $check2->execute();
        $booking_count = (int)$check2->get_result()->fetch_assoc()['cnt'];
        $check2->close();

        if ($booking_count == 0) {
            // Check 3: Has the phone number been used in any previous booking?
            $check3 = $conn->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE phone = ?");
            $check3->bind_param("s", $phone);
            $check3->execute();
            $phone_count = (int)$check3->get_result()->fetch_assoc()['cnt'];
            $check3->close();

            if ($phone_count == 0) {
                $first_time_discount = 1;
                $offer_message = ' 🎉 10% FIRST-TIME DISCOUNT applied!';
            } else {
                $offer_message = ' (First-time offer not valid — phone already used for a previous booking)';
            }
        } else {
            $offer_message = ' (First-time offer not valid — you have existing bookings)';
        }
    } else {
        $offer_message = ' (First-time offer not valid — already used)';
    }
}

// ── Insert booking ──────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO bookings
        (user_id, full_name, phone, email,
         vehicle_make, vehicle_model, vehicle_year, registration,
         services, booking_date, booking_time, notes, first_time_discount)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssssssssssi",
    $user_id, $full_name, $phone, $email,
    $vehicle_make, $vehicle_model, $vehicle_year, $registration,
    $services, $booking_date, $booking_time, $notes, $first_time_discount
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
