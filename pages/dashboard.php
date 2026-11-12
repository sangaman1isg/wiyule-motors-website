<?php
session_start();

// Set timezone to Malawi (CAT = UTC+2) so greetings show correct time
date_default_timezone_set('Africa/Blantyre');

// Auto-detects localhost vs live site. In production, $base is always empty
// so URLs resolve from site root. Locally, set to subfolder name if needed.
$base = '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    // Set this only if your local server runs the site from a subfolder
    // e.g. http://localhost/wiyule-motors-website/
    // Leave empty if running from root with `php -S localhost:8000`
    $base = '';
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: " . $base . "/pages/login.php");
    exit;
}

// User ID for all DB queries (define EARLY so it's available everywhere)
$current_user_id = (int)$_SESSION['user_id'];

// ── Database connection ───────────────────────────────────────
require_once __DIR__ . '/../includes/db_connection.php';

$userName  = $_SESSION['user_name']  ?? 'Member';
$userEmail = $_SESSION['user_email'] ?? '';
$userType  = $_SESSION['user_type']  ?? 'individual';
$firstName = explode(' ', $userName)[0];

// Read which panel to open from the URL (?panel=bookings etc.)
$allowed_panels = ['overview','bookings','history','reminders','vehicle','health','notifications','settings'];
$initial_panel  = in_array($_GET['panel'] ?? '', $allowed_panels) ? $_GET['panel'] : 'overview';

// "New user" = literally no vehicles AND no bookings in DB.
// We compute this AFTER loading the data below, so placeholder for now.
$is_new_user = false;

// ─────────────────────────────────────────────────────────────
//  Load fresh user data from database (source of truth)
// ─────────────────────────────────────────────────────────────
$ustmt = $conn->prepare("SELECT full_name, email, phone, created_at FROM users WHERE id = ?");
$ustmt->bind_param("i", $current_user_id);
$ustmt->execute();
$user_db = $ustmt->get_result()->fetch_assoc();
$ustmt->close();

// Always sync session with fresh DB data
if ($user_db) {
    $_SESSION['email']        = $user_db['email'];
    $_SESSION['user_email']   = $user_db['email'];
    $_SESSION['user_name']    = $user_db['full_name'];
    $_SESSION['phone']        = $user_db['phone'] ?? '';
    $_SESSION['member_since'] = $user_db['created_at'] ?? date('Y-m-d');
    $name_parts = explode(' ', $user_db['full_name'], 2);
    $_SESSION['first_name'] = $name_parts[0] ?? '';
    $_SESSION['last_name']  = $name_parts[1] ?? '';
}

$fname = $_SESSION['first_name'] ?? 'User';
$lname = $_SESSION['last_name']  ?? '';

$user = [
    'first_name'      => $fname,
    'last_name'       => $lname,
    'email'           => $_SESSION['email']        ?? '',
    'phone'           => $_SESSION['phone']        ?? '',
    'member_since'    => $_SESSION['member_since'] ?? date('Y-m-d'),
    'avatar_initials' => strtoupper(
                            substr($fname ?: 'U', 0, 1) .
                            substr($lname ?: '', 0, 1)
                         ),
    'type'            => $userType === 'business' ? 'Business' : 'Individual',
    'city'            => $_SESSION['city'] ?? 'Blantyre',
];

// ── Check first-time offer eligibility ────────────────────────
$offer_eligible = false;
$uid_for_check = $current_user_id;
$chk = $conn->prepare("SELECT first_offer_used FROM users WHERE id = ?");
if ($chk) {
    $chk->bind_param("i", $uid_for_check);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();
    $chk->close();
    $offer_eligible = $r && (int)($r['first_offer_used'] ?? 1) === 0;
}

// ── Load ALL vehicles from database ──────────────────────────
$all_vehicles = [];
$vstmt = $conn->prepare("SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC");
$vstmt->bind_param("i", $current_user_id);
$vstmt->execute();
$vresult = $vstmt->get_result();
while ($vrow = $vresult->fetch_assoc()) {
    $all_vehicles[] = $vrow;
}
$vstmt->close();

// Upcoming official renewals (COF / road tax / insurance) across the customer's
// vehicles — anything expired or due within 60 days surfaces as a reminder.
$upcoming_renewals = [];
foreach ($all_vehicles as $av) {
    $vlabel = trim(($av['make'] ?? '') . ' ' . ($av['model'] ?? ''));
    foreach ([['COF', 'cof_expiry'], ['Road tax', 'road_tax_expiry'], ['Insurance', 'insurance_expiry']] as [$rlabel, $rkey]) {
        $dt = $av[$rkey] ?? null;
        if (empty($dt)) continue;
        $ts = strtotime($dt);
        if ($ts === false) continue;
        $days = (int)floor(($ts - strtotime('today')) / 86400);
        if ($days <= 60) {
            $upcoming_renewals[] = ['vehicle' => $vlabel, 'type' => $rlabel, 'date' => $dt, 'days' => $days];
        }
    }
}
usort($upcoming_renewals, fn($a, $b) => $a['days'] <=> $b['days']);

// Primary vehicle: ALWAYS prefer DB (most up-to-date), session is fallback
if (count($all_vehicles) > 0) {
    $pv = $all_vehicles[0];
    $vehicle = [
        'make'                 => $pv['make'] ?? '',
        'model'                => $pv['model'] ?? '',
        'year'                 => $pv['year'] ?? '',
        'registration'         => $pv['registration'] ?? '',
        'color'                => $pv['color'] ?? '',
        'mileage'              => $pv['mileage'] ?? 0,
        'last_service_mileage' => $_SESSION['last_service_mileage'] ?? 0,
        'next_service_mileage' => $_SESSION['next_service_mileage'] ?? 5000,
        'engine'               => $pv['engine'] ?? '',
        'transmission'         => $pv['transmission'] ?? '',
    ];
    // Sync session so other panels stay in sync
    $_SESSION['vehicle_make']  = $pv['make'];
    $_SESSION['vehicle_model'] = $pv['model'];
    $_SESSION['vehicle_year']  = $pv['year'];
    $_SESSION['vehicle_reg']   = $pv['registration'];
} else {
    // No DB vehicles — fall back to session (shouldn't normally happen)
    $vehicle = [
        'make'                 => $_SESSION['vehicle_make'] ?? '',
        'model'                => $_SESSION['vehicle_model'] ?? '',
        'year'                 => $_SESSION['vehicle_year'] ?? '',
        'registration'         => $_SESSION['vehicle_reg'] ?? '',
        'color'                => $_SESSION['vehicle_color'] ?? '',
        'mileage'              => $_SESSION['vehicle_mileage'] ?? 0,
        'last_service_mileage' => $_SESSION['last_service_mileage'] ?? 0,
        'next_service_mileage' => $_SESSION['next_service_mileage'] ?? 5000,
        'engine'               => $_SESSION['vehicle_engine'] ?? '',
        'transmission'         => $_SESSION['vehicle_transmission'] ?? '',
    ];
}

// ── Fetch REAL bookings from database ─────────────────────────

// Upcoming bookings (today or future, not completed/cancelled)
$upcoming_bookings = [];
$stmt = $conn->prepare("
    SELECT id, services, booking_date, booking_time, status, notes, job_stage
    FROM bookings
    WHERE user_id = ?
      AND (booking_date >= CURDATE() OR (status = 'confirmed' AND job_stage <> ''))
      AND status IN ('pending','confirmed')
    ORDER BY booking_date ASC, booking_time ASC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_bookings[] = [
        'id'      => 'WM-' . date('Y', strtotime($row['booking_date'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'db_id'   => $row['id'],
        'service' => $row['services'],
        'date'    => $row['booking_date'],
        'time'    => $row['booking_time'],
        'status'    => $row['status'],
        'job_stage' => $row['job_stage'] ?? '',
        'tech'      => 'TBA',
        'notes'     => $row['notes'] ?? '',
    ];
}
$stmt->close();

// Past bookings (past dates OR completed/cancelled) — excluding active jobs that
// are still in progress (those show as upcoming with a live status tracker).
$service_history = [];
$stmt = $conn->prepare("
    SELECT id, services, booking_date, booking_time, status, notes
    FROM bookings
    WHERE user_id = ?
      AND (booking_date < CURDATE() OR status IN ('completed','cancelled'))
      AND NOT (status = 'confirmed' AND job_stage <> '')
    ORDER BY booking_date DESC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $service_history[] = [
        'id'      => 'WM-' . date('Y', strtotime($row['booking_date'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'db_id'   => $row['id'],
        'service' => $row['services'],
        'date'    => $row['booking_date'],
        'cost'    => '—',
        'status'  => $row['status'] === 'cancelled' ? 'cancelled' : 'completed',
        'tech'    => 'Wiyule Motors',
        'notes'   => $row['notes'] ?? '',
    ];
}
$stmt->close();

// Enrich history with the photos the customer uploaded + the feedback they left.
if (!empty($service_history)) {
    require_once __DIR__ . '/../includes/booking_attachments.php';
    $hist_ids = array_map('intval', array_column($service_history, 'db_id'));

    try { $hist_photo_map = wm_booking_attachments_map($conn, $hist_ids); }
    catch (Throwable $e) { $hist_photo_map = []; }

    $hist_fb_map = [];
    try {
        $fb_in = implode(',', array_fill(0, count($hist_ids), '?'));
        $fbst  = $conn->prepare("SELECT booking_id, rating, comment FROM booking_feedback WHERE booking_id IN ($fb_in)");
        $fbst->bind_param(str_repeat('i', count($hist_ids)), ...$hist_ids);
        $fbst->execute();
        $fbres = $fbst->get_result();
        while ($row = $fbres->fetch_assoc()) $hist_fb_map[(int)$row['booking_id']] = $row;
        $fbst->close();
    } catch (Throwable $e) { $hist_fb_map = []; }

    // Linked invoices (by booking) so a record can offer a real "Download Invoice".
    $hist_inv_map = [];
    try {
        $iv_in = implode(',', array_fill(0, count($hist_ids), '?'));
        $ivst  = $conn->prepare("SELECT booking_id, id FROM invoices WHERE type='invoice' AND payment_status<>'void' AND booking_id IN ($iv_in)");
        $ivst->bind_param(str_repeat('i', count($hist_ids)), ...$hist_ids);
        $ivst->execute();
        $ivres = $ivst->get_result();
        while ($row = $ivres->fetch_assoc()) $hist_inv_map[(int)$row['booking_id']] = (int)$row['id'];
        $ivst->close();
    } catch (Throwable $e) { $hist_inv_map = []; }

    foreach ($service_history as &$h) {
        $bid = (int)$h['db_id'];
        $h['photos']     = array_map(fn($f) => wm_booking_attachment_url($base, $f), $hist_photo_map[$bid] ?? []);
        $fb = $hist_fb_map[$bid] ?? null;
        $h['rating']     = $fb ? (int)$fb['rating'] : 0;
        $h['comment']    = $fb['comment'] ?? '';
        $h['invoice_id'] = $hist_inv_map[$bid] ?? 0;
    }
    unset($h);
}

// Total bookings count for stats
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$total_all_bookings = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ── Real new-user check: no vehicles AND no bookings ─────────
// (Don't show onboarding to returning users — they already did it)
$is_new_user = (count($all_vehicles) === 0 && $total_all_bookings === 0);

// ── Smart Notifications (generated from real booking data) ───
$notifications = [];

// 1. OVERDUE bookings — past date but still pending/confirmed
$stmt = $conn->prepare("
    SELECT id, services, booking_date, booking_time, status
    FROM bookings WHERE user_id = ? AND booking_date < CURDATE()
    AND status IN ('pending','confirmed') ORDER BY booking_date ASC
");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$overdue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($overdue as $ob) {
    $days_late = (int)((time() - strtotime($ob['booking_date'])) / 86400);
    $notifications[] = [
        'type'  => 'warning',
        'title' => 'Overdue Booking #' . $ob['id'],
        'msg'   => $ob['services'] . ' was scheduled for ' . date('d M Y', strtotime($ob['booking_date'])) . ' (' . $days_late . ' day' . ($days_late!==1?'s':'') . ' ago). Please reschedule or contact us.',
        'time'  => $days_late . 'd overdue',
        'read'  => false,
    ];
}

// 2. Bookings TODAY
foreach ($upcoming_bookings as $b) {
    if ($b['date'] === date('Y-m-d')) {
        $notifications[] = [
            'type'  => 'success',
            'title' => 'Service Today!',
            'msg'   => $b['service'] . ' at ' . date('g:i A', strtotime($b['time'])) . '. Please arrive 10 minutes early.',
            'time'  => 'Today',
            'read'  => false,
        ];
    }
}

// 3. Upcoming confirmed bookings (next 7 days)
foreach ($upcoming_bookings as $b) {
    if ($b['date'] !== date('Y-m-d') && $b['status'] === 'confirmed') {
        $days_until = (int)((strtotime($b['date']) - time()) / 86400);
        if ($days_until <= 7 && $days_until > 0) {
            $notifications[] = [
                'type'  => 'success',
                'title' => 'Booking Confirmed',
                'msg'   => $b['service'] . ' — ' . date('D, d M Y', strtotime($b['date'])) . ' at ' . date('g:i A', strtotime($b['time'])),
                'time'  => 'In ' . $days_until . ' day' . ($days_until!==1?'s':''),
                'read'  => false,
            ];
        }
    }
}

// 4. Pending bookings awaiting confirmation
foreach ($upcoming_bookings as $b) {
    if ($b['status'] === 'pending' && $b['date'] >= date('Y-m-d')) {
        $notifications[] = [
            'type'  => 'info',
            'title' => 'Awaiting Confirmation',
            'msg'   => $b['service'] . ' on ' . date('d M Y', strtotime($b['date'])) . '. We\'ll confirm shortly via WhatsApp.',
            'time'  => 'Pending',
            'read'  => false,
        ];
    }
}

// 5. Recently completed bookings (last 14 days)
foreach (array_slice($service_history, 0, 3) as $h) {
    if ($h['status'] === 'completed' && strtotime($h['date']) > strtotime('-14 days')) {
        $notifications[] = [
            'type'  => 'success',
            'title' => 'Service Completed',
            'msg'   => $h['service'] . ' completed on ' . date('d M Y', strtotime($h['date'])) . '. Thank you for choosing Wiyule Motors!',
            'time'  => date('d M', strtotime($h['date'])),
            'read'  => true,
        ];
    }
}

// 6. No vehicles reminder
if (count($all_vehicles) === 0) {
    $notifications[] = [
        'type'  => 'info',
        'title' => 'Add Your Vehicle',
        'msg'   => 'Add your vehicle details to get personalized service recommendations and maintenance reminders.',
        'time'  => 'Action',
        'read'  => false,
    ];
}

// 7. Welcome message if no bookings at all
if (empty($notifications)) {
    $notifications[] = [
        'type'  => 'info',
        'title' => 'Welcome to Wiyule Motors!',
        'msg'   => 'Book your first service to get started. We\'ll send you reminders and status updates here.',
        'time'  => 'Now',
        'read'  => false,
    ];
}

// ── Service Reminders (based on real booking history) ────────
$reminders = [];

// Calculate days since last completed service
$last_service_date = null;
$stmt = $conn->prepare("SELECT booking_date, services FROM bookings WHERE user_id = ? AND status = 'completed' ORDER BY booking_date DESC LIMIT 1");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$last_svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($last_svc) {
    $last_service_date = $last_svc['booking_date'];
    $days_since = (int)((time() - strtotime($last_service_date)) / 86400);

    // General service reminder (every 90 days)
    $urgency = $days_since > 120 ? 'urgent' : ($days_since > 80 ? 'soon' : 'ok');
    $reminders[] = [
        'title'    => 'General Service Check',
        'interval' => 'Every 3 months',
        'icon'     => 'tool',
        'urgency'  => $urgency,
        'due_km'   => ($vehicle['mileage'] ?: 0) + max(0, (90 - $days_since)) * 30,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => $days_since,
        'last_date'  => $last_service_date,
    ];

    // Oil change reminder (every 5000km or 90 days)
    $oil_urgency = $days_since > 100 ? 'urgent' : ($days_since > 75 ? 'soon' : 'ok');
    $reminders[] = [
        'title'    => 'Oil & Filter Change',
        'interval' => 'Every 5,000 km or 3 months',
        'icon'     => 'droplet',
        'urgency'  => $oil_urgency,
        'due_km'   => ($vehicle['mileage'] ?: 0) + 5000,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => $days_since,
        'last_date'  => $last_service_date,
    ];

    // Tyre check (every 6 months)
    $tyre_urgency = $days_since > 200 ? 'urgent' : ($days_since > 160 ? 'soon' : 'ok');
    $reminders[] = [
        'title'    => 'Tyre Inspection & Rotation',
        'interval' => 'Every 6 months or 10,000 km',
        'icon'     => 'circle',
        'urgency'  => $tyre_urgency,
        'due_km'   => ($vehicle['mileage'] ?: 0) + 10000,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => $days_since,
        'last_date'  => $last_service_date,
    ];

    // Brake check (every 12 months)
    $brake_urgency = $days_since > 380 ? 'urgent' : ($days_since > 330 ? 'soon' : 'ok');
    $reminders[] = [
        'title'    => 'Brake System Inspection',
        'interval' => 'Every 12 months or 20,000 km',
        'icon'     => 'disc',
        'urgency'  => $brake_urgency,
        'due_km'   => ($vehicle['mileage'] ?: 0) + 20000,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => $days_since,
        'last_date'  => $last_service_date,
    ];
} else if (count($all_vehicles) > 0) {
    // Has vehicles but no completed services yet
    $reminders[] = [
        'title'    => 'First Service Check',
        'interval' => 'Recommended for new registrations',
        'icon'     => 'tool',
        'urgency'  => 'soon',
        'due_km'   => ($vehicle['mileage'] ?: 0) + 5000,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => 0,
        'last_date'  => null,
    ];
}

// Add overdue booking reminders
foreach ($overdue as $ob) {
    $reminders[] = [
        'title'    => 'Missed: ' . $ob['services'],
        'interval' => 'Was due ' . date('d M Y', strtotime($ob['booking_date'])),
        'icon'     => 'alert-triangle',
        'urgency'  => 'urgent',
        'due_km'   => $vehicle['mileage'] ?: 0,
        'current_km' => $vehicle['mileage'] ?: 0,
        'days_since' => (int)((time() - strtotime($ob['booking_date'])) / 86400),
        'last_date'  => $ob['booking_date'],
    ];
}

// ── Per-Vehicle Health Calculation ───────────────────────────
// Calculate a real health score for EACH vehicle based on:
//  - Mileage relative to 5000km service interval
//  - Days since last completed service
//  - Number of completed services (tracked maintenance = higher)
//  - Any overdue bookings for this vehicle
$vehicles_health = [];

foreach ($all_vehicles as $av) {
    $vh_mileage = (int)($av['mileage'] ?? 0);
    $vh_id      = (int)$av['id'];

    // Single query gets BOTH the last service date AND the total completed count
    // AND the last recorded service mileage (combining what used to be 3 separate
    // queries across the page into ONE — drastically cuts DB load)
    $last_service_days_ago = null;
    $last_service_date_actual = null;
    $completed_count = 0;
    $has_completed_service = false;
    $last_service_km = null;
    $stmt_vh = $conn->prepare("
        SELECT
            MAX(booking_date) AS last_date,
            COUNT(*) AS completed_count,
            MAX(CASE WHEN service_mileage > 0 THEN service_mileage END) AS last_service_km
        FROM bookings
        WHERE user_id = ? AND status = 'completed'
          AND vehicle_make = ? AND vehicle_model = ?
    ");
    if ($stmt_vh) {
        $stmt_vh->bind_param("iss", $current_user_id, $av['make'], $av['model']);
        $stmt_vh->execute();
        $r = $stmt_vh->get_result()->fetch_assoc();
        $stmt_vh->close();
        if ($r && $r['completed_count'] > 0) {
            $completed_count = (int)$r['completed_count'];
            $has_completed_service = true;
            if ($r['last_date']) {
                $last_service_date_actual = $r['last_date'];
                $last_service_days_ago = (int)((time() - strtotime($r['last_date'])) / 86400);
            }
            if ($r['last_service_km']) {
                $last_service_km = (int)$r['last_service_km'];
            }
        }
    }

    // Service interval pct (out of 5000km)
    $vh_interval_pct = $vh_mileage > 0 ? min(100, ($vh_mileage % 5000) / 50) : 0;

    // Honest "service health" score from REAL signals only — how recent the last
    // service was and how far through the 5,000 km interval the car is. (No invented
    // per-component percentages.)
    $health_score = 95;
    if ($last_service_days_ago === null) {
        $health_score -= 10;                        // never serviced with us
    } else {
        if ($last_service_days_ago > 365)      $health_score -= 25;
        elseif ($last_service_days_ago > 180)  $health_score -= 15;
        elseif ($last_service_days_ago > 90)   $health_score -= 5;
    }
    if ($vh_interval_pct > 95)      $health_score -= 20;
    elseif ($vh_interval_pct > 85)  $health_score -= 10;
    elseif ($vh_interval_pct > 70)  $health_score -= 5;
    $health_score += min(5, $completed_count * 2);
    $health_score = max(35, min(100, $health_score));

    // Real next-service estimate: 3 months OR +5,000 km after the last service.
    $next_due_date = $last_service_date_actual
        ? date('d M Y', strtotime($last_service_date_actual . ' +3 months'))
        : null;
    $next_due_km = $last_service_km ? ($last_service_km + 5000) : null;

    // Honest status badge from real data.
    if ($last_service_days_ago === null) {
        $vh_status = ['label'=>'No service record yet', 'tone'=>'amber'];
    } elseif ($last_service_days_ago > 180 || $vh_interval_pct > 95) {
        $vh_status = ['label'=>'Service overdue', 'tone'=>'red'];
    } elseif ($last_service_days_ago > 90 || $vh_interval_pct > 80) {
        $vh_status = ['label'=>'Service due soon', 'tone'=>'amber'];
    } else {
        $vh_status = ['label'=>'Up to date', 'tone'=>'green'];
    }

    // Real facts shown to the customer (replaces the invented component scores).
    $facts = [
        ['label'=>'Last service', 'icon'=>'settings',
         'value'=> $last_service_date_actual ? date('d M Y', strtotime($last_service_date_actual)) : 'No record with us',
         'sub'=> $last_service_days_ago !== null ? $last_service_days_ago.' day'.($last_service_days_ago!==1?'s':'').' ago' : 'book one to start tracking'],
        ['label'=>'Next service due', 'icon'=>'droplet',
         'value'=> $next_due_date ?: '—',
         'sub'=> $next_due_km ? 'or at '.number_format($next_due_km).' km' : 'every 3 months / 5,000 km'],
        ['label'=>'Current mileage', 'icon'=>'circle',
         'value'=> $vh_mileage > 0 ? number_format($vh_mileage).' km' : '—',
         'sub'=> $last_service_km ? 'serviced at '.number_format($last_service_km).' km' : ''],
        ['label'=>'Services completed', 'icon'=>'disc',
         'value'=> (string)$completed_count,
         'sub'=> 'with Wiyule & F.A Motors'],
    ];

    $vehicles_health[$vh_id] = [
        'overall'               => $health_score,
        'status'                => $vh_status,
        'facts'                 => $facts,
        'last_service_days_ago' => $last_service_days_ago,
        'completed_count'       => $completed_count,
        'interval_pct'          => $vh_interval_pct,
        'has_completed_service' => $has_completed_service,
        'last_service_km'       => $last_service_km,
    ];
}

// For the Vehicle Health panel (single-vehicle view), use the FIRST vehicle's data.
$first_vh_id  = count($all_vehicles) > 0 ? (int)$all_vehicles[0]['id'] : 0;
$first_vh     = ($first_vh_id && isset($vehicles_health[$first_vh_id])) ? $vehicles_health[$first_vh_id] : null;
$health_facts = $first_vh['facts'] ?? [];
$health_status = $first_vh['status'] ?? ['label'=>'No vehicle on record', 'tone'=>'amber'];

// ── Helpers ──────────────────────────────────────────────────
function healthColor(int $s): string { return $s>=80 ? '#16a34a' : ($s>=55 ? '#f59e0b' : '#dc2626'); }
function healthBg(int $s): string    { return $s>=80 ? 'bg-green-50 text-green-700' : ($s>=55 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600'); }
function healthLabel(int $s): string { return $s>=80 ? 'Good' : ($s>=55 ? 'Fair' : 'Check'); }
// Service-status tone → [text colour, background]
function statusTone(string $t): array {
    return match($t) {
        'green' => ['#16a34a', '#f0fdf4'],
        'red'   => ['#dc2626', '#fef2f2'],
        default => ['#d97706', '#fffbeb'],   // amber
    };
}

// Renewal (COF / road tax / insurance) badge from an expiry date → [label, textColour, bg].
function wm_renewal_badge(?string $date): array {
    if (empty($date)) return ['Not set', '#94a3b8', 'rgba(255,255,255,0.06)'];
    $ts = strtotime($date);
    if ($ts === false) return ['Not set', '#94a3b8', 'rgba(255,255,255,0.06)'];
    $days = (int)floor(($ts - strtotime('today')) / 86400);
    $d    = date('d M Y', $ts);
    if ($days < 0)   return ['Expired · ' . $d, '#dc2626', '#fef2f2'];
    if ($days <= 30) return ['Due in ' . $days . 'd · ' . $d, '#d97706', '#fffbeb'];
    return ['Valid to ' . $d, '#16a34a', '#f0fdf4'];
}

// Live job-status tracker (Received → In progress → Ready) for an in-shop vehicle.
function wm_job_stage_html(string $stage): string {
    if ($stage === '') return '';
    $steps  = ['received' => 'Received', 'in_progress' => 'In progress', 'ready' => 'Ready for collection'];
    $order  = array_keys($steps);
    $curIdx = array_search($stage, $order, true);
    if ($curIdx === false) return '';
    $out = '<div style="margin-top:10px;background:rgba(220,38,38,0.06);border:1px solid rgba(220,38,38,0.15);border-radius:10px;padding:10px 12px">'
         . '<div style="font-size:10px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px">🔧 Live status</div>'
         . '<div style="display:flex;gap:6px;flex-wrap:wrap">';
    foreach ($order as $i => $k) {
        $done  = $i <= $curIdx;
        $isCur = $i === $curIdx;
        $bg  = $isCur ? '#dc2626' : ($done ? '#fee2e2' : 'rgba(255,255,255,0.05)');
        $col = $isCur ? '#fff'    : ($done ? '#b91c1c' : '#94a3b8');
        $out .= '<span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;background:' . $bg . ';color:' . $col . '">'
             .  ($done ? '✓ ' : '') . htmlspecialchars($steps[$k]) . '</span>';
    }
    return $out . '</div></div>';
}

function statusPill(string $s): string {
    return match($s) {
        'confirmed' => '<span class="status-pill bg-green-100 text-green-700">✓ Confirmed</span>',
        'pending'   => '<span class="status-pill bg-amber-100 text-amber-700">⏳ Pending</span>',
        'completed' => '<span class="status-pill bg-gray-100 text-gray-400">✔ Done</span>',
        'cancelled' => '<span class="status-pill bg-red-100 text-red-600">✕ Cancelled</span>',
        default     => ''
    };
}

function urgencyClasses(string $u): string {
    return match($u) {
        'urgent' => 'border-red-200 bg-red-50',
        'soon'   => 'border-amber-200 bg-amber-50',
        default  => 'border-gray-200 bg-white',
    };
}

function urgencyBadge(string $u): string {
    return match($u) {
        'urgent' => '<span class="reminder-badge bg-red-600 text-white">Overdue</span>',
        'soon'   => '<span class="reminder-badge bg-amber-500 text-white">Due Soon</span>',
        default  => '<span class="reminder-badge bg-green-600 text-white">OK</span>',
    };
}

function notifIcon(string $t): string {
    return match($t) {
        'success' => '<div class="notif-icon bg-green-100 text-green-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>',
        'warning' => '<div class="notif-icon bg-amber-100 text-amber-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>',
        default   => '<div class="notif-icon bg-blue-100 text-blue-600"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>',
    };
}

$unread_count   = count(array_filter($notifications, fn($n) => !$n['read']));
$urgent_count   = count(array_filter($reminders, fn($r) => $r['urgency'] === 'urgent'));
$overall_health = $first_vh['overall'] ?? 100;

// Mileage progress to next service
$miles_done = $vehicle['mileage'] - $vehicle['last_service_mileage'];
$miles_span = $vehicle['next_service_mileage'] - $vehicle['last_service_mileage'];
$service_pct = $miles_span > 0 ? min(100, round($miles_done / $miles_span * 100)) : 0;

/**
 * Returns a filled SVG icon by name (replaces Feather outline icons
 * so the dashboard matches index.php's filled icon style and no longer
 * depends on the feather-icons library). Always sized via width/height
 * so icons never render blank on mobile.
 */
function wm_icon($name, $size = 20) {
    $paths = [
        'tool'           => '<path d="M21.7 7.3a1 1 0 0 0-1.6-.3l-2.5 2.5-2.1-2.1 2.5-2.5a1 1 0 0 0-.3-1.6 5.5 5.5 0 0 0-7.1 6.6l-7 7a2.5 2.5 0 0 0 3.5 3.5l7-7a5.5 5.5 0 0 0 6.6-7.1zM6 19.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>',
        'settings'       => '<path d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V19a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H1a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>',
        'droplet'        => '<path d="M12 2.2S5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.2 12 2.2zm0 16.3a4 4 0 0 1-4-4c0-.6.4-1 1-1s1 .4 1 1a2 2 0 0 0 2 2c.6 0 1 .4 1 1s-.4 1-1 1z"/>',
        'disc'           => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 14a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>',
        'circle'         => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>',
        'wind'           => '<path d="M3 8h11a3 3 0 1 0-3-3 1 1 0 0 1-2 0 5 5 0 1 1 5 5H3a1 1 0 0 1 0-2zm16.5 4H3a1 1 0 0 0 0 2h16.5a2.5 2.5 0 1 1-2.5 2.5 1 1 0 0 0-2 0 4.5 4.5 0 1 0 4.5-4.5z"/>',
        'zap'            => '<path d="M13 2L4.1 12.5a1 1 0 0 0 .8 1.6H10l-1 7.9a1 1 0 0 0 1.8.7L20 12.2a1 1 0 0 0-.8-1.6H14l1-7.9a1 1 0 0 0-1.8-.7z"/>',
        'calendar'       => '<path d="M19 4h-1V3a1 1 0 0 0-2 0v1H8V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9h16z"/>',
        'check-circle'   => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.2l-4-4 1.4-1.4 2.6 2.6 5.6-5.6 1.4 1.4z"/>',
        'alert-triangle' => '<path d="M13.7 3.5a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 20.5h16a2 2 0 0 0 1.7-3l-8-14zM12 17a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4zm1-5a1 1 0 0 1-2 0V9a1 1 0 0 1 2 0z"/>',
        'truck'          => '<path d="M3 4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h1a2.5 2.5 0 0 0 5 0h5a2.5 2.5 0 0 0 5 0h1a1 1 0 0 0 1-1v-4a1 1 0 0 0-.3-.7l-3-3A1 1 0 0 0 17 7h-2V5a1 1 0 0 0-1-1zm14 5h.6l2 2H17zM6.5 16.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm10 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>',
        'dollar-sign'    => '<path d="M12 1a11 11 0 1 0 0 22 11 11 0 0 0 0-22zm1 16.9V19a1 1 0 0 1-2 0v-1.1a3.5 3.5 0 0 1-2.5-2.2 1 1 0 0 1 1.9-.7c.2.6.8 1 1.6 1 .9 0 1.5-.5 1.5-1.1 0-.5-.3-.9-1.7-1.3-1.3-.4-3.1-1-3.1-3 0-1.4 1-2.5 2.3-2.9V5a1 1 0 0 1 2 0v.8a3.4 3.4 0 0 1 2.3 2 1 1 0 0 1-1.8.8c-.2-.5-.7-.9-1.4-.9-.8 0-1.4.4-1.4 1 0 .5.5.7 1.8 1.1 1.3.4 3 1 3 3.2 0 1.5-1 2.6-2.3 3z"/>',
        'star'           => '<path d="M12 2l2.9 6.3 6.8.6a1 1 0 0 1 .6 1.8l-5.2 4.5 1.6 6.7a1 1 0 0 1-1.5 1.1L12 19.3l-5.8 3.5a1 1 0 0 1-1.5-1.1l1.6-6.7-5.2-4.5a1 1 0 0 1 .6-1.8l6.8-.6z"/>',
        'message-circle' => '<path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/>',
        'phone'          => '<path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/>',
        'file-text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 2.5L17.5 8H14zM8 13h8a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2zm0 4h8a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2zm0-8h3a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2z"/>',
        'user'           => '<path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm0 2c-4 0-8 2-8 5v1a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-1c0-3-4-5-8-5z"/>',
        'lock'           => '<path d="M17 9V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3zM9 7a3 3 0 0 1 6 0v2H9zm4 9.7V18a1 1 0 0 1-2 0v-1.3a1.5 1.5 0 1 1 2 0z"/>',
    ];
    $p = $paths[$name] ?? $paths['circle'];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display:inline-block;vertical-align:middle">'.$p.'</svg>';
}

/**
 * Builds an auto-generated studio car render URL from make/model/year/color.
 * Uses imagin.studio's CDN. Picks a dynamic/sporty camera angle and a larger
 * render. If the render fails to load, the <img> onerror swaps to wm_brand_logo().
 */
function wm_car_image($make, $model, $year = '', $color = '') {
    $make = trim(strtolower($make));
    $model = trim(strtolower($model));
    if ($make === '') return '';
    // Map common colors to paint descriptors the service understands
    $c = strtolower(trim($color));
    $paint = 'pspc0001'; // default neutral
    $colormap = [
        'black'=>'pspc0010','white'=>'pspc0011','silver'=>'pspc0004','grey'=>'pspc0003',
        'gray'=>'pspc0003','red'=>'pspc0006','blue'=>'pspc0005','green'=>'pspc0012',
    ];
    if (isset($colormap[$c])) $paint = $colormap[$c];
    $params = http_build_query([
        'customer'      => 'img',
        'make'          => $make,
        'modelFamily'   => $model,
        'angle'         => '29',          // dynamic 3/4 sporty angle
        'zoomType'      => 'fullscreen',
        'groundPlaneAdjustmentMode' => '1',
        'safeMode'      => 'true',        // cleaner crop
        'paintId'       => $paint,
        'fileType'      => 'png',
        'width'         => '800',
    ]);
    if ($year) $params .= '&modelYear=' . urlencode($year);
    return 'https://cdn.imagin.studio/getImage?' . $params;
}

/**
 * Returns a brand logo URL for a given car make (fallback when render fails).
 * Uses a public logo CDN. Falls back to empty (then the car silhouette shows).
 */
function wm_brand_logo($make) {
    $m = strtolower(trim($make));
    $known = ['bmw','toyota','ford','mercedes','mercedes-benz','audi','volkswagen','vw',
              'nissan','honda','hyundai','kia','mazda','mitsubishi','subaru','lexus',
              'land rover','range rover','jeep','chevrolet','isuzu','suzuki','peugeot',
              'renault','volvo','porsche','jaguar','tesla','fiat','mini','dodge'];
    $alias = ['vw'=>'volkswagen','mercedes'=>'mercedes-benz','range rover'=>'land-rover','land rover'=>'land-rover'];
    if (isset($alias[$m])) $m = $alias[$m];
    if (!in_array($m, $known) && !in_array(str_replace(' ','-',$m), array_map(fn($x)=>str_replace(' ','-',$x),$known))) {
        // Unknown brand — return empty so the silhouette icon shows instead
    }
    $slug = str_replace(' ', '-', $m);
    // car-logos CDN (public). If brand missing, this 404s and the silhouette shows.
    return 'https://www.carlogos.org/car-logos/' . $slug . '-logo.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/../includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>My Dashboard — Wiyule Motors</title>

    <link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* ── CSS Variables ───────────────────────────────── */
        :root {
            --red:       #dc2626;
            --red-dark:  #b91c1c;
            --red-light: rgba(220,38,38,0.12);
            --sidebar-w: 260px;
            --sidebar-collapsed: 72px;
            --header-h: 64px;
            --radius:   16px;
            /* Dark command-center palette */
            --bg:        #0b0f1a;
            --bg-2:      #0f1320;
            --card:      #141925;
            --card-2:    #1a1f2e;
            --border:    rgba(255,255,255,0.08);
            --border-2:  rgba(255,255,255,0.14);
            --text:      #f1f5f9;
            --text-2:    #94a3b8;
            --text-3:    #64748b;
            --shadow:   0 1px 3px rgba(0,0,0,0.3), 0 8px 24px rgba(0,0,0,0.25);
            --shadow-lg: 0 16px 40px rgba(0,0,0,0.4);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ─────────────────────────────────────── */
        #sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: #0f172a;
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0; top: 0; bottom: 0;
            z-index: 100;
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1),
                        transform 0.3s cubic-bezier(0.4,0,0.2,1);
            overflow: hidden;
        }
        #sidebar.collapsed { width: var(--sidebar-collapsed); }

        .sidebar-logo {
            display: flex; align-items: center; gap: 12px;
            padding: 18px 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            min-height: 72px;
            overflow: hidden;
        }
        .sidebar-logo-img {
            height: 34px; width: auto; max-width: 160px;
            border-radius: 8px;
            object-fit: contain;
            background: #fff; padding: 3px 7px;
            flex-shrink: 0;
        }
        .sidebar-logo-text {
            font-size: 15px; font-weight: 800;
            color: #fff;
            white-space: nowrap;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .sidebar-logo-text { opacity: 0; pointer-events: none; }

        .sidebar-section {
            padding: 20px 10px 4px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .sidebar-section::-webkit-scrollbar { width: 4px; }
        .sidebar-section::-webkit-scrollbar-track { background: transparent; }
        .sidebar-section::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        .nav-group-label {
            font-size: 10px; font-weight: 700;
            color: #475569;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 8px 10px 6px;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .nav-group-label { opacity: 0; }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 10px;
            border-radius: 12px;
            color: #94a3b8;
            font-size: 14px; font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: #e2e8f0; }
        .nav-item.active {
            background: linear-gradient(135deg, var(--red) 0%, var(--red-dark) 100%);
            color: #fff;
            box-shadow: 0 4px 12px rgba(220,38,38,0.35);
        }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .nav-item span { transition: opacity 0.2s; }
        #sidebar.collapsed .nav-item span { opacity: 0; }

        .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: #fff;
            font-size: 10px; font-weight: 800;
            width: 18px; height: 18px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: opacity 0.2s;
        }
        #sidebar.collapsed .nav-badge { opacity: 0; }

        .sidebar-user {
            padding: 14px 12px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex; align-items: center; gap: 10px;
            cursor: pointer;
            transition: background 0.2s;
            overflow: hidden;
        }
        .sidebar-user:hover { background: rgba(255,255,255,0.04); }
        .sidebar-avatar {
            width: 38px; height: 38px; border-radius: 12px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800;
            flex-shrink: 0;
        }
        .sidebar-user-info { overflow: hidden; transition: opacity 0.2s; }
        .sidebar-user-name { font-size: 13px; font-weight: 700; color: #f8fafc; white-space: nowrap; }
        .sidebar-user-role { font-size: 11px; color: var(--text-2); white-space: nowrap; }
        #sidebar.collapsed .sidebar-user-info { opacity: 0; }

        #collapseBtn {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: var(--text-2);
            transition: all 0.25s ease;
            flex-shrink: 0;
            margin-left: auto;
        }
        #collapseBtn:hover { color: #fff; background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }
        #collapseBtn svg { transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); }
        #sidebar.collapsed #collapseBtn svg { transform: rotate(180deg); }
        #sidebar.collapsed #collapseBtn { margin: 0 auto; }

        /* ── Main content ─────────────────────────────────── */
        #main {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-height: 100vh;
            transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        #main.expanded { margin-left: var(--sidebar-collapsed); }

        /* ── Top Header ───────────────────────────────────── */
        .top-header {
            height: var(--header-h);
            background: rgba(15,19,32,0.8);
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky;
            top: 0; z-index: 50;
        }
        .header-search {
            flex: 1; max-width: 400px;
            display: flex; align-items: center;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 8px 14px;
            gap: 10px;
            transition: border-color 0.2s;
        }
        .header-search:focus-within { border-color: var(--red); }
        .header-search input {
            background: none; border: none; outline: none;
            font-size: 14px; font-family: 'Sora', sans-serif;
            color: var(--text); width: 100%;
        }
        .header-search input::placeholder { color: var(--text-3); }

        .header-actions { display: flex; align-items: center; gap: 12px; margin-left: auto; }

        .icon-btn {
            width: 40px; height: 40px;
            border-radius: 12px;
            border: none; background: rgba(255,255,255,0.05);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-2);
            transition: all 0.2s; position: relative;
        }
        .icon-btn:hover { background: var(--red-light); color: #f87171; }
        .icon-btn .badge {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid var(--bg-2);
        }

        .header-user {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .header-user:hover { background: rgba(255,255,255,0.05); }
        .header-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 800;
        }

        /* ── Header User Dropdown ─────────────────────────── */
        .header-user-wrap { position: relative; }
        .header-user-caret { transition: transform 0.2s; margin-left: 2px; }
        .header-user-wrap.open .header-user-caret { transform: rotate(180deg); }
        .header-user-wrap.open .header-user { background: rgba(255,255,255,0.05); }

        .header-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0;
            width: 260px; background: var(--card-2);
            border: 1.5px solid var(--border); border-radius: 18px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.5);
            overflow: hidden;
            opacity: 0; transform: translateY(-8px) scale(0.97);
            pointer-events: none;
            transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1);
            z-index: 200;
        }
        .header-user-wrap.open .header-dropdown {
            opacity: 1; transform: translateY(0) scale(1); pointer-events: all;
        }
        .hd-user-card {
            background: linear-gradient(135deg, #1a1f2e, #0f1320);
            padding: 18px; display: flex; align-items: center; gap: 12px;
        }
        .hd-avatar {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff; font-size: 15px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .hd-name { font-size: 14px; font-weight: 800; color: #fff; }
        .hd-email { font-size: 11px; color: var(--text-3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
        .hd-items { padding: 8px; }
        .hd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            color: var(--text-2); font-size: 13px; font-weight: 600;
            text-decoration: none; cursor: pointer;
            transition: all 0.15s; margin-bottom: 2px;
        }
        .hd-item:hover { background: rgba(255,255,255,0.06); color: #fff; }
        .hd-danger { color: #f87171; }
        .hd-danger:hover { background: rgba(220,38,38,0.12); }
        .hd-icon {
            width: 32px; height: 32px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .hd-divider { height: 1px; background: var(--border); margin: 6px 8px; }

        /* ── Page body ────────────────────────────────────── */
        .page-body { padding: 28px; display: flex; flex-direction: column; gap: 20px; }

        /* ── Cards ────────────────────────────────────────── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--border-2); }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 22px 0;
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 15px; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }
        .card-title svg { width: 17px; height: 17px; color: #f87171; }
        .card-action {
            font-size: 13px; font-weight: 600; color: #f87171;
            text-decoration: none; cursor: pointer;
            padding: 5px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .card-action:hover { background: var(--red-light); }
        .card-body { padding: 0 22px 22px; }

        /* ── Stat cards — clean flat dark (matches reference) ──────── */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
        .stat-card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 20px 22px;
            position: relative;
            transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), border-color 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-2);
        }

        .stat-card-top {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 18px;
        }
        .stat-toplabel {
            font-size: 13px; font-weight: 600; color: var(--text-2);
            letter-spacing: -0.01em;
        }
        .stat-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .stat-icon.red   { background: rgba(239,68,68,0.16);  color: #f87171; }
        .stat-icon.amber { background: rgba(245,158,11,0.16); color: #fbbf24; }
        .stat-icon.blue  { background: rgba(59,130,246,0.16); color: #60a5fa; }
        .stat-icon.green { background: rgba(34,197,94,0.16);  color: #4ade80; }
        .stat-icon svg   { width: 19px; height: 19px; }

        .stat-value {
            position: relative; z-index: 1;
            font-size: 34px; font-weight: 800; color: #fff;
            line-height: 1; letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
        }
        .stat-label {
            position: relative; z-index: 1;
            font-size: 13px; font-weight: 600; color: #cbd5e1;
            margin-top: 8px;
        }
        .stat-change {
            position: relative; z-index: 1;
            font-size: 12px; color: var(--text-3); margin-top: 8px;
        }
        /* Reference-style action link at bottom of each stat card */
        .stat-link {
            position: relative; z-index: 1;
            display: inline-flex; align-items: center; gap: 5px;
            margin-top: 16px; padding-top: 14px;
            border-top: 1px solid var(--border);
            font-size: 13px; font-weight: 600; cursor: pointer;
            text-decoration: none;
            transition: gap 0.2s ease;
        }
        .stat-link svg { transition: transform 0.2s ease; }
        .stat-link:hover { gap: 9px; }
        .stat-card.red   .stat-link { color: #f87171; }
        .stat-card.amber .stat-link { color: #fbbf24; }
        .stat-card.blue  .stat-link { color: #60a5fa; }
        .stat-card.green .stat-link { color: #4ade80; }
        @media (prefers-reduced-motion: reduce) {
            .stat-card { transition: none !important; }
        }

        /* Recommended for You */
        .rec-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }
        .rec-row:last-child { border-bottom: none; }
        .rec-icon {
            width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .rec-icon svg { width: 20px; height: 20px; }
        .rec-icon.red   { background: rgba(239,68,68,0.16);  color: #f87171; }
        .rec-icon.amber { background: rgba(245,158,11,0.16); color: #fbbf24; }
        .rec-icon.blue  { background: rgba(59,130,246,0.16); color: #60a5fa; }
        .rec-icon.green { background: rgba(34,197,94,0.16);  color: #4ade80; }
        .rec-info { flex: 1; min-width: 0; }
        .rec-name { font-size: 14px; font-weight: 700; color: var(--text); }
        .rec-desc { font-size: 12px; color: var(--text-3); margin-top: 2px; }
        .rec-right { text-align: right; flex-shrink: 0; }
        .rec-price { font-size: 13px; font-weight: 800; color: #4ade80; margin-bottom: 5px; white-space: nowrap; }
        .rec-book {
            background: var(--red); color: #fff; border: none;
            padding: 6px 16px; border-radius: 9px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            font-family: 'Sora', sans-serif; transition: background 0.2s;
        }
        .rec-book:hover { background: var(--red-dark); }

        /* Welcome banner — racing-themed greeting card */
        .welcome-banner {
            background: linear-gradient(135deg, #161b28 0%, #1a1020 100%);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: relative; overflow: hidden;
            gap: 20px;
        }
        /* Racing speed-streak accent on the right */
        .welcome-banner::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 88% 30%, rgba(220,38,38,0.18) 0%, transparent 45%),
                repeating-linear-gradient(115deg, transparent 0 28px, rgba(255,255,255,0.015) 28px 30px);
            pointer-events: none;
        }
        /* Checkered-flag motif, top-right corner */
        .welcome-flag {
            position: absolute; top: 0; right: 0;
            width: 120px; height: 100%;
            opacity: 0.10;
            background-image:
                linear-gradient(45deg, #fff 25%, transparent 25%, transparent 75%, #fff 75%),
                linear-gradient(45deg, #fff 25%, transparent 25%, transparent 75%, #fff 75%);
            background-size: 18px 18px;
            background-position: 0 0, 9px 9px;
            -webkit-mask-image: linear-gradient(to left, #000, transparent);
            mask-image: linear-gradient(to left, #000, transparent);
            pointer-events: none;
        }
        .welcome-grid { display: none; }
        .welcome-text { position: relative; z-index: 1; }
        .welcome-text h2 { font-size: 26px; font-weight: 800; color: #fff; margin-bottom: 4px; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px; }
        .welcome-text p  { font-size: 14px; color: var(--text-2); margin-bottom: 0; }
        .welcome-cta {
            position: relative; overflow: hidden;
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95));
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff;
            padding: 11px 22px;
            border-radius: 14px;
            font-size: 14px; font-weight: 700;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.25);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.35);
        }
        .welcome-cta::before {
            content: ''; position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .welcome-cta:hover::before { left: 120%; }
        .welcome-cta:hover { transform: translateY(-3px) scale(1.02); box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.45); }

        .next-booking-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 18px 22px;
            min-width: 240px;
            backdrop-filter: blur(8px);
        }
        .next-booking-label { font-size: 10px; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .next-booking-service { font-size: 16px; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .next-booking-info { font-size: 13px; color: #94a3b8; }
        .booking-countdown {
            margin-top: 10px;
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(220,38,38,0.2);
            border: 1px solid rgba(220,38,38,0.3);
            color: #fca5a5;
            font-size: 12px; font-weight: 600;
            padding: 4px 10px; border-radius: 99px;
        }

        /* Health ring */
        .health-ring-wrap { display: flex; justify-content: center; padding: 10px 0 6px; }
        .health-ring { position: relative; width: 140px; height: 140px; }
        .health-ring svg { transform: rotate(-90deg); }
        .health-ring-inner {
            position: absolute; inset: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .health-pct { font-size: 32px; font-weight: 900; line-height: 1; }
        .health-sublabel { font-size: 11px; font-weight: 600; color: #94a3b8; margin-top: 2px; }

        .health-items { display: flex; flex-direction: column; gap: 10px; }
        .health-row { display: flex; align-items: center; gap: 12px; }
        .health-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .health-icon svg { width: 15px; height: 15px; }
        .health-bar-wrap { flex: 1; }
        .health-bar-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: var(--text-2); margin-bottom: 5px; }
        .health-bar-track { height: 6px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; }
        .health-bar-fill  { height: 100%; border-radius: 99px; transition: width 1s cubic-bezier(0.4,0,0.2,1); }

        /* Bookings table */
        .booking-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
            cursor: pointer;
            border-radius: 10px;
            padding-left: 8px; padding-right: 8px;
            margin: 0 -8px;
        }
        .booking-row:last-child { border-bottom: none; }
        .booking-row:hover { background: rgba(255,255,255,0.04); }
        .booking-service-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: var(--red-light);
            display: flex; align-items: center; justify-content: center;
            color: var(--red); flex-shrink: 0;
        }
        .booking-service-icon svg { width: 20px; height: 20px; }
        .booking-name { font-size: 14px; font-weight: 700; color: var(--text); }
        .booking-meta { font-size: 12px; color: var(--text-2); margin-top: 2px; }
        .booking-id   { font-size: 12px; font-weight: 600; color: #94a3b8; font-family: monospace; }

        .status-pill { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 99px; }

        /* Timeline */
        .timeline { position: relative; padding-left: 28px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 9px; top: 8px; bottom: 8px;
            width: 2px;
            background: linear-gradient(to bottom, var(--red), #e2e8f0);
        }
        .timeline-item { position: relative; padding-bottom: 22px; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-dot {
            position: absolute;
            left: -22px; top: 4px;
            width: 14px; height: 14px;
            border-radius: 50%;
            background: var(--red);
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px var(--red);
        }
        .timeline-item:not(:first-child) .timeline-dot { background: #cbd5e1; box-shadow: 0 0 0 2px #cbd5e1; }
        .timeline-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .timeline-card:hover { border-color: var(--red); background: var(--red-light); }
        .timeline-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .timeline-service { font-size: 14px; font-weight: 700; color: var(--text); }
        .timeline-cost { font-size: 13px; font-weight: 800; color: var(--red); }
        .timeline-meta { font-size: 12px; color: var(--text-2); }
        .timeline-notes { font-size: 12px; color: #94a3b8; margin-top: 4px; font-style: italic; }

        /* Reminders */
        .reminder-item {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .reminder-item:last-child { margin-bottom: 0; }
        .reminder-item:hover { transform: translateX(3px); }
        .reminder-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .reminder-icon svg { width: 20px; height: 20px; }
        .reminder-body { flex: 1; }
        .reminder-title { font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 2px; }
        .reminder-sub   { font-size: 12px; color: var(--text-2); }
        .reminder-badge { font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 99px; flex-shrink: 0; }
        .km-bar-wrap { margin-top: 5px; }
        .km-bar { height: 4px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; }
        .km-fill { height: 100%; border-radius: 99px; }

        /* Notifications */
        .notif-item {
            display: flex; gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        .notif-item:hover { background: rgba(255,255,255,0.04); margin: 0 -8px; padding: 14px 8px; }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-body { flex: 1; }
        .notif-title { font-size: 13px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; }
        .notif-unread { width: 7px; height: 7px; border-radius: 50%; background: var(--red); flex-shrink: 0; }
        .notif-msg { font-size: 12px; color: var(--text-2); margin-top: 2px; }
        .notif-time { font-size: 11px; color: #94a3b8; white-space: nowrap; }

        /* Vehicle card */
        .vehicle-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border-radius: var(--radius);
            padding: 24px;
            color: #fff;
            position: relative; overflow: hidden;
        }
        .vehicle-card::after {
            content: '🚗';
            position: absolute;
            right: -10px; bottom: -20px;
            font-size: 110px;
            opacity: 0.07;
            line-height: 1;
        }
        /* Vehicle Switcher Tabs */
        .vswitch-tab { transition: all 0.2s ease; }
        .vswitch-tab:hover:not(.active) { background: rgba(255,255,255,0.08) !important; color: #fff !important; }
        #vehicleSwitcherCard > div:first-child::-webkit-scrollbar { display: none; }
        .vehicle-make { font-size: 11px; font-weight: 700; color: #ef4444; text-transform: uppercase; letter-spacing: 0.1em; }
        .vehicle-name { font-size: 22px; font-weight: 900; color: #fff; margin: 4px 0 2px; }
        .vehicle-photo-wrap {
            margin: 14px 0 16px;
            height: 130px;
            display: flex; align-items: center; justify-content: center;
            background: radial-gradient(ellipse at center, rgba(255,255,255,0.06), transparent 70%);
            border-radius: 14px;
            position: relative;
        }
        .vehicle-photo {
            max-width: 100%; max-height: 130px;
            object-fit: contain;
            filter: drop-shadow(0 10px 18px rgba(0,0,0,0.5));
            transition: transform 0.4s cubic-bezier(0.22,1,0.36,1);
        }
        .vehicle-card:hover .vehicle-photo { transform: scale(1.04); }
        .vehicle-photo.is-logo { max-height: 76px; opacity: 0.95; }
        .vehicle-photo-silhouette { color: rgba(255,255,255,0.18); }
        .vehicle-photo-silhouette svg { width: 80px; height: 80px; }
        .vehicle-year { font-size: 14px; color: #94a3b8; margin-bottom: 16px; }
        .vehicle-reg  {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 5px 14px; border-radius: 8px;
            font-size: 14px; font-weight: 800;
            letter-spacing: 2px; color: #e2e8f0;
            margin-bottom: 16px;
        }
        .vehicle-spec { display: flex; gap: 0; flex-wrap: wrap; }
        .vehicle-spec-item {
            flex: 1; min-width: 100px;
            padding: 10px 0;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .vehicle-spec-item:last-child { border-right: none; }
        .vehicle-spec-label { font-size: 10px; font-weight: 600; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .vehicle-spec-value { font-size: 14px; font-weight: 700; color: #e2e8f0; }

        .mileage-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.06); }
        .mileage-label { display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
        .mileage-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 99px; overflow: hidden; }
        .mileage-fill { height: 100%; background: linear-gradient(90deg, var(--red), #f97316); border-radius: 99px; transition: width 1.2s cubic-bezier(0.4,0,0.2,1); }

        /* Quick actions */
        .quick-actions { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }

        /* Reference-style top quick-action row (4 colored tiles) */
        .qrow { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
        .qrow-tile {
            display: flex; align-items: center; gap: 14px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 20px;
            cursor: pointer; text-decoration: none;
            transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), border-color 0.3s, background 0.3s;
        }
        .qrow-tile:hover { transform: translateY(-4px); border-color: var(--border-2); background: var(--card-2); }
        .qrow-ic {
            width: 46px; height: 46px; border-radius: 13px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .qrow-ic svg { width: 23px; height: 23px; color: #fff; }
        .qrow-ic.red   { background: linear-gradient(135deg,#ef4444,#b91c1c); box-shadow: 0 8px 18px -6px rgba(239,68,68,0.6); }
        .qrow-ic.blue  { background: linear-gradient(135deg,#3b82f6,#1d4ed8); box-shadow: 0 8px 18px -6px rgba(59,130,246,0.6); }
        .qrow-ic.slate { background: linear-gradient(135deg,#475569,#1e293b); box-shadow: 0 8px 18px -6px rgba(71,85,105,0.6); }
        .qrow-ic.green { background: linear-gradient(135deg,#22c55e,#15803d); box-shadow: 0 8px 18px -6px rgba(34,197,94,0.6); }
        .qrow-tx-title { display: block; font-size: 14px; font-weight: 700; color: var(--text); }
        .qrow-tx-sub { display: block; font-size: 12px; color: var(--text-3); margin-top: 2px; }
        @media (max-width: 900px) { .qrow { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 480px) { .qrow { grid-template-columns: 1fr; } }
        .quick-btn {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            padding: 16px 12px;
            border-radius: 14px;
            border: 1.5px solid var(--border);
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            font-family: 'Sora', sans-serif;
            transition: all 0.2s;
            text-decoration: none;
        }
        .quick-btn:hover { border-color: var(--red); background: var(--red-light); transform: translateY(-2px); }
        .quick-btn-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .quick-btn-icon svg { width: 22px; height: 22px; }
        .quick-btn-label { font-size: 12px; font-weight: 700; color: var(--text-2); text-align: center; }

        /* Offer banner */
        .offer-banner {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 14px;
            padding: 16px 18px;
            display: flex; align-items: center; gap: 14px;
            position: relative; overflow: hidden;
            cursor: pointer;
        }
        .offer-banner:hover { transform: scale(1.02); transition: transform 0.2s; }
        .offer-banner::after { content: '🎉'; position: absolute; right: 12px; font-size: 36px; opacity: 0.35; }
        .offer-icon { width: 42px; height: 42px; background: rgba(255,255,255,0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .offer-text-title { font-size: 13px; font-weight: 800; color: #fff; }
        .offer-text-sub   { font-size: 12px; color: #bbf7d0; margin-top: 1px; }

        /* Modals */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 200; align-items: center; justify-content: center; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--card-2); border: 1px solid var(--border); border-radius: 20px;
            padding: 32px 28px; max-width: 520px; width: 100%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0.88); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .modal-title { font-size: 18px; font-weight: 800; color: var(--text); margin-bottom: 4px; }
        .modal-sub   { font-size: 13px; color: var(--text-2); margin-bottom: 20px; }
        .modal-field { margin-bottom: 16px; }
        .modal-field label { display: block; font-size: 12px; font-weight: 700; color: var(--text-2); margin-bottom: 5px; }
        .modal-field input,
        .modal-field select,
        .modal-field textarea {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 14px; font-family: 'Sora', sans-serif; color: var(--text);
            outline: none; transition: border-color 0.2s;
            background: rgba(255,255,255,0.04);
        }
        .modal-field select option { background: var(--card-2); color: var(--text); }
        .modal-field input:focus,
        .modal-field select:focus,
        .modal-field textarea:focus { border-color: var(--red); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-modal-primary {
            position: relative; overflow: hidden;
            flex: 1; background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95));
            backdrop-filter: blur(12px) saturate(180%); -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff; border: 1px solid rgba(255,255,255,0.25); padding: 13px; border-radius: 14px;
            font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif;
            cursor: pointer; transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 8px 22px rgba(220,38,38,0.3);
        }
        .btn-modal-primary::before {
            content: ''; position: absolute; top: 0; left: -120%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .btn-modal-primary:hover::before { left: 120%; }
        .btn-modal-primary:hover { transform: translateY(-2px) scale(1.01); box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 14px 32px rgba(220,38,38,0.4); }
        .btn-modal-ghost {
            flex: 0; padding: 13px 20px;
            border: 1.5px solid var(--border); border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif;
            background: none; color: var(--text-2); cursor: pointer; transition: all 0.2s;
        }
        .btn-modal-ghost:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }

        /* Reusable glass action button for dashboard (matches site gbtn) */
        .dash-glass-btn {
            position: relative; overflow: hidden;
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95)) !important;
            backdrop-filter: blur(12px) saturate(180%); -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.25) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 8px 22px rgba(220,38,38,0.3) !important;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s !important;
        }
        .dash-glass-btn::before {
            content: ''; position: absolute; top: 0; left: -120%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease; pointer-events: none;
        }
        .dash-glass-btn:hover::before { left: 120%; }
        .dash-glass-btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 14px 32px rgba(220,38,38,0.4) !important; }
        @media (prefers-reduced-motion: reduce) {
            .btn-modal-primary, .dash-glass-btn { transition: none !important; }
            .btn-modal-primary::before, .dash-glass-btn::before { display: none; }
        }

        /* Onboarding Wizard */
        .onboarding-wizard {
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        .step-item.completed {
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.3);
        }

        /* Toast Notifications */
        #toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 380px;
        }
        .toast {
            background: white;
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            font-family: 'Sora', sans-serif;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }

        /* Skeleton Loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 8px;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Live dot pulse */
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
            display: inline-block;
            animation: livePulse 2s infinite;
        }
        @keyframes livePulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50%      { box-shadow: 0 0 0 5px rgba(34,197,94,0); }
        }

        /* Mobile Bottom Navigation */
        .mobile-bottom-nav {
            display: none;
        }

        /* Responsive */
        @media (max-width: 1280px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media (max-width: 1024px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.mobile-open { transform: translateX(0); }
            #main { margin-left: 0 !important; }
            #collapseBtn { display: none !important; }   /* no collapse on mobile drawer */
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 16px; }
            .next-booking-card { min-width: unset; width: 100%; }
            
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                border-top: 1px solid var(--border);
                justify-content: space-around;
                padding: 12px 16px;
                z-index: 100;
                backdrop-filter: blur(10px);
                background: rgba(15,19,32,0.95);
            }
            .mobile-nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 10px;
                color: var(--text-2);
                text-decoration: none;
                background: none;
                border: none;
                cursor: pointer;
            }
            .mobile-nav-item.active {
                color: #dc2626;
            }
            .mobile-nav-item svg {
                width: 20px;
                height: 20px;
            }
            #main {
                padding-bottom: 70px;
            }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); gap: 12px; }
            .page-body { padding: 14px; gap: 16px; }
            .welcome-banner { padding: 22px 20px; }
            .stat-card { padding: 16px; border-radius: 18px; }
            .stat-value { font-size: 30px; }
            .stat-card-top { margin-bottom: 14px; }
            .stat-icon { width: 38px; height: 38px; }
            .stat-icon svg { width: 19px; height: 19px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-value { font-size: 26px; }
            .stat-label { font-size: 12px; }
            .stat-change { font-size: 10.5px; }
            /* Hide the status pill on the smallest screens to avoid crowding */
            .stat-card-top { margin-bottom: 12px; justify-content: flex-start; }
        }

        .fade-in { animation: fadeInUp 0.5s ease both; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .delay-1 { animation-delay: 0.08s; }
        .delay-2 { animation-delay: 0.16s; }
        .delay-3 { animation-delay: 0.24s; }
        .delay-4 { animation-delay: 0.32s; }
        .delay-5 { animation-delay: 0.40s; }
        /* ── Feedback Button ──────────────────────────────── */
        .feedback-btn {
            position:fixed; bottom:24px; left:24px;
            display:flex; align-items:center; gap:8px;
            background:#fff; color:#94a3b8;
            padding:10px 16px; border-radius:99px;
            box-shadow:0 4px 20px rgba(0,0,0,0.12);
            border:1.5px solid #e5e7eb;
            font-size:13px; font-weight:600;
            font-family:'Sora',sans-serif;
            cursor:pointer; text-decoration:none;
            transition:all 0.3s; z-index:50;
        }
        .feedback-btn:hover { border-color:#dc2626; color:#dc2626; box-shadow:0 6px 24px rgba(0,0,0,0.16); transform:translateY(-2px); }
        .feedback-btn svg { width:16px; height:16px; flex-shrink:0; }
        @media(max-width:1024px) {
            .feedback-btn { bottom:80px; }
        }
        @media(max-width:767px) {
            .feedback-btn span { display:none; }
            .feedback-btn { padding:10px; border-radius:50%; }
        }
        /* ═══════════════════════════════════════════════════════
           ✨ LIQUID GLASS REDESIGN — Overview polish
           Warmer, friendlier, more premium feel
        ═══════════════════════════════════════════════════════ */

        /* Softer page background */
        .page-body { position: relative; }

        /* ── Welcome banner — refined, calm dark glass ── */
        .welcome-banner {
            background: linear-gradient(135deg, #1d1d1f 0%, #2c2c2e 100%) !important;
        }
        .welcome-text h2 {
            font-size: 26px !important;
            font-weight: 700 !important;
            color: #f5f5f7 !important;
            letter-spacing: -0.02em;
        }
        .welcome-text p {
            color: #a1a1a6 !important;
        }

        /* CTA button — liquid glass (matches index.php) */
        .welcome-cta {
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95)) !important;
            border: 1px solid rgba(255,255,255,0.25) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.35) !important;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
        }
        .welcome-cta:hover {
            transform: translateY(-3px) scale(1.02) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.45) !important;
        }

        /* Next-booking card — dark frosted glass */
        .next-booking-card {
            background: rgba(255,255,255,0.06) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255,255,255,0.12) !important;
            box-shadow: none !important;
        }

        /* ── Stat cards — clean flat dark (matches reference) ── */
        .stat-card {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            box-shadow: var(--shadow) !important;
            transition: transform 0.3s cubic-bezier(0.22,1,0.36,1), border-color 0.3s !important;
        }
        .stat-card:hover {
            transform: translateY(-4px) !important;
            border-color: var(--border-2) !important;
            box-shadow: var(--shadow-lg) !important;
        }

        /* Icon tiles */
        .stat-icon {
            border: none;
            transition: transform 0.3s cubic-bezier(0.25, 0.1, 0.25, 1);
        }
        .stat-card:hover .stat-icon { transform: scale(1.06); }

        /* ── Content cards — matching frosted glass ── */
        #panel-overview .card {
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: box-shadow 0.3s ease, border-color 0.3s ease;
        }
        #panel-overview .card:hover {
            border-color: var(--border-2);
            box-shadow: var(--shadow-lg);
        }

        /* Subtle, smooth fade-in (no bounce) */
        @keyframes fadeUp {
            0%   { opacity: 0; transform: translateY(10px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        #panel-overview .stat-card,
        #panel-overview .welcome-banner,
        #panel-overview .card {
            animation: fadeUp 0.5s cubic-bezier(0.25, 0.1, 0.25, 1) backwards;
        }
        #panel-overview .stat-card:nth-child(1) { animation-delay: 0.04s; }
        #panel-overview .stat-card:nth-child(2) { animation-delay: 0.08s; }
        #panel-overview .stat-card:nth-child(3) { animation-delay: 0.12s; }
        #panel-overview .stat-card:nth-child(4) { animation-delay: 0.16s; }

        /* Respect reduced-motion preferences */
        @media (prefers-reduced-motion: reduce) {
            #panel-overview .stat-card,
            #panel-overview .welcome-banner,
            #panel-overview .card { animation: none !important; }
        }
    </style>
</head>
<body>

<!-- Feedback Button -->
<a href="https://wa.me/265993575111?text=Hi%20Wiyule%20Motors%2C%20I%20found%20an%20issue%20on%20your%20website%20dashboard%3A%20" class="feedback-btn" target="_blank" rel="noopener noreferrer" title="Report an issue or give feedback">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span>Feedback</span>
</a>

<!-- ═══════════════════════════════ SIDEBAR ══════════════════ -->
<nav id="sidebar">

    <div class="sidebar-logo">
        <a href="<?= $base ?>/" style="display:flex;align-items:center;gap:12px;text-decoration:none;overflow:hidden;flex:1">
            <img class="sidebar-logo-img"
                 src="<?= $base ?>/assets/images/logo.png"
                 alt="Wiyule Motors">
            <span class="sidebar-logo-text">Wiyule Motors</span>
        </a>
        <button id="collapseBtn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
    </div>

    <div class="sidebar-section">
        <div class="nav-group-label">Main</div>
        <a class="nav-item active" onclick="showPanel('overview')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            <span>Overview</span>
        </a>
        <a class="nav-item" onclick="showPanel('bookings')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>My Bookings</span>
            <?php if(count($upcoming_bookings)): ?><span class="nav-badge"><?= count($upcoming_bookings) ?></span><?php endif; ?>
        </a>
        <a class="nav-item" onclick="showPanel('history')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>
            <span>Service History</span>
        </a>
        <a class="nav-item" onclick="showPanel('reminders')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Reminders</span>
            <?php if($urgent_count): ?><span class="nav-badge"><?= $urgent_count ?></span><?php endif; ?>
        </a>

        <div class="nav-group-label" style="margin-top:12px">Vehicle</div>
        <a class="nav-item" onclick="showPanel('vehicle')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <span>My Vehicles</span>
        </a>
        <a class="nav-item" onclick="showPanel('health')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            <span>Vehicle Health</span>
        </a>

        <div class="nav-group-label" style="margin-top:12px">Account</div>
        <a class="nav-item" onclick="showPanel('notifications')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Notifications</span>
            <?php if($unread_count): ?><span class="nav-badge"><?= $unread_count ?></span><?php endif; ?>
        </a>
        <a class="nav-item" onclick="showPanel('settings')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Settings</span>
        </a>

        <a href="<?= $base ?>/" class="nav-item" style="margin-top:8px">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Back to Site</span>
        </a>
    </div>

    <div class="sidebar-user" onclick="showPanel('settings')">
        <div class="sidebar-avatar"><?= htmlspecialchars($user['avatar_initials']) ?></div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
            <div class="sidebar-user-role"><?= htmlspecialchars($user['type']) ?> Member</div>
        </div>
    </div>
</nav>

<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-[99] hidden lg:hidden" onclick="closeSidebar()"></div>

<!-- ═══════════════════════════════ MAIN ════════════════════ -->
<div id="main">

    <header class="top-header">
        <button class="icon-btn lg:hidden" onclick="openSidebar()" aria-label="Open menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="header-search">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="searchInput" placeholder="Search bookings, services, invoices…" onkeyup="handleSearch(this.value)">
        </div>

        <div class="header-actions">
            <div class="hidden md:flex items-center gap-2 text-xs font-semibold text-gray-400">
                <span class="live-dot"></span> <?= (date('N') >= 6) ? 'Open today · 09:00–13:00' : 'Open today · 08:30–17:00' ?>
            </div>

            <button class="icon-btn" onclick="showPanel('notifications')" aria-label="Notifications">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if($unread_count): ?><span class="badge"></span><?php endif; ?>
            </button>

            <button class="hidden sm:flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-xl hover:bg-red-700 transition border-none cursor-pointer" onclick="openBookModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Book Service
            </button>

            <div class="header-user-wrap" id="headerUserWrap">
                <div class="header-user" onclick="toggleHeaderDropdown()" aria-haspopup="true" aria-expanded="false">
                    <div class="header-avatar"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                    <div class="hidden md:block">
                        <div class="text-sm font-bold text-white"><?= htmlspecialchars($user['first_name']) ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($user['type']) ?></div>
                    </div>
                    <svg class="header-user-caret hidden md:block" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </div>
                <div class="header-dropdown" id="headerDropdown">
                    <div class="hd-user-card">
                        <div class="hd-avatar"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                        <div>
                            <div class="hd-name"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                            <div class="hd-email"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                    </div>
                    <div class="hd-items">
                        <a class="hd-item" onclick="showPanel('overview');closeHeaderDropdown()">
                            <div class="hd-icon" style="background:rgba(255,255,255,0.06);color:#94a3b8"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
                            Dashboard
                        </a>
                        <a class="hd-item" onclick="showPanel('bookings');closeHeaderDropdown()">
                            <div class="hd-icon" style="background:#eff6ff;color:#2563eb"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                            My Bookings
                        </a>
                        <a class="hd-item" onclick="showPanel('vehicle');closeHeaderDropdown()">
                            <div class="hd-icon" style="background:#f0fdf4;color:#15803d"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
                            My Vehicle
                        </a>
                        <a class="hd-item" onclick="showPanel('settings');closeHeaderDropdown()">
                            <div class="hd-icon" style="background:rgba(255,255,255,0.04);color:#94a3b8"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09"/></svg></div>
                            Settings
                        </a>
                        <div class="hd-divider"></div>
                        <a class="hd-item" href="<?= $base ?>/">
                            <div class="hd-icon" style="background:rgba(255,255,255,0.06);color:#94a3b8"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                            Back to Website
                        </a>
                        <a class="hd-item hd-danger" href="<?= $base ?>/pages/logout.php">
                            <div class="hd-icon" style="background:#fef2f2;color:#dc2626"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
                            Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ═══ PAGE PANELS ══════════════════════════════════════ -->

    <!-- ─── OVERVIEW ─── -->
    <div id="panel-overview" class="page-body">

        <!-- Onboarding Wizard for New Users -->
        <?php if ($is_new_user && !isset($_SESSION['onboarding_dismissed'])): ?>
        <div class="onboarding-wizard fade-in" id="onboardingWizard">
            <div style="background:linear-gradient(135deg,rgba(220,38,38,0.12),rgba(245,158,11,0.1));border-radius:16px;padding:24px;margin-bottom:24px;border:1px solid rgba(220,38,38,0.25)">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-white mb-2">🎉 Welcome to Your Dashboard!</h3>
                        <p class="text-gray-300 mb-4">Let's get your vehicle set up in 3 simple steps</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="rounded-xl p-4 text-center step-item cursor-pointer" data-step="1" onclick="completeOnboardingStep(1)">
                                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-2">1</div>
                                <div class="font-semibold text-white">Add Vehicle</div>
                                <div class="text-xs text-gray-400 mt-1">Enter your car details</div>
                            </div>
                            <div class="rounded-xl p-4 text-center step-item cursor-pointer" data-step="2" onclick="completeOnboardingStep(2)">
                                <div class="w-10 h-10 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2">2</div>
                                <div class="font-semibold text-gray-400">Set Preferences</div>
                                <div class="text-xs text-gray-400 mt-1">Notification settings</div>
                            </div>
                            <div class="rounded-xl p-4 text-center step-item cursor-pointer" data-step="3" onclick="completeOnboardingStep(3)">
                                <div class="w-10 h-10 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2">3</div>
                                <div class="font-semibold text-gray-400">Book Service</div>
                                <div class="text-xs text-gray-400 mt-1">Schedule first maintenance</div>
                            </div>
                        </div>
                        
                        <button onclick="startOnboarding()" class="bg-red-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-red-700 transition">
                            Start Setup →
                        </button>
                        <button onclick="dismissOnboarding()" class="ml-3 text-gray-400 hover:text-gray-200 text-sm">
                            Maybe later
                        </button>
                    </div>
                    <button onclick="dismissOnboarding()" class="text-gray-400 hover:text-gray-300">✕</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($offer_eligible): ?>
        <!-- First-Time Customer Offer Banner -->
        <div class="fade-in" style="background:linear-gradient(135deg,#fbbf24,#f59e0b);border-radius:18px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 10px 30px rgba(245,158,11,0.3)">
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.25);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">🎉</div>
            <div style="flex:1;min-width:200px;color:#78350f">
                <div style="font-size:17px;font-weight:900;color:#78350f">Welcome gift: 10% OFF your first service!</div>
                <div style="font-size:13px;color:#92400e;margin-top:3px">One-time offer — redeem it when booking your first service below.</div>
            </div>
            <button onclick="openBookModal()" style="background:#7c2d12;color:#fff;border:none;padding:11px 22px;border-radius:12px;font-size:13px;font-weight:800;font-family:'Sora',sans-serif;cursor:pointer;white-space:nowrap;box-shadow:0 4px 14px rgba(124,45,18,0.3)">
                Claim Now →
            </button>
        </div>
        <?php endif; ?>

        <!-- Welcome banner -->
        <div class="welcome-banner fade-in">
            <div class="welcome-flag"></div>
            <div class="welcome-text">
                <h2>
                    <span>Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($user['first_name']) ?></span>
                    <span style="font-size:24px">🏁</span>
                </h2>
                <?php
                // Personalized subtitle based on user's real state
                // Show "Welcome back" + specific context for returning users
                if (!empty($upcoming_bookings)):
                    $next = $upcoming_bookings[0];
                    $days_away = (int)((strtotime($next['date']) - strtotime('today')) / 86400);
                    if ($days_away === 0) {
                        $sub_text = "Welcome back! You have a service appointment today at " . date('g:i A', strtotime($next['time'])) . ".";
                    } elseif ($days_away === 1) {
                        $sub_text = "Welcome back! Your next appointment is tomorrow at " . date('g:i A', strtotime($next['time'])) . ".";
                    } elseif ($days_away > 1 && $days_away <= 7) {
                        $sub_text = "Welcome back! Your next appointment is in " . $days_away . " days.";
                    } else {
                        $sub_text = "Welcome back! Your next appointment is on " . date('d M', strtotime($next['date'])) . ".";
                    }
                elseif ($total_all_bookings > 0):
                    $sub_text = "Welcome back, " . htmlspecialchars($user['first_name']) . "! Ready to book your next service?";
                elseif (count($all_vehicles) > 0):
                    $veh_count = count($all_vehicles);
                    $sub_text = "Welcome back! You have " . $veh_count . " vehicle" . ($veh_count !== 1 ? 's' : '') . " registered. Let's book your first service.";
                else:
                    $sub_text = "Welcome! Add your vehicle details to get started.";
                endif;
                ?>
                <p><?= $sub_text ?></p>
            </div>
            <?php if(!empty($upcoming_bookings)): $b = $upcoming_bookings[0]; ?>
            <div class="next-booking-card">
                <div class="next-booking-label">📅 Next Appointment</div>
                <div class="next-booking-service"><?= htmlspecialchars($b['service']) ?></div>
                <div class="next-booking-info">
                    <?= date('D, d M Y', strtotime($b['date'])) ?> at <?= date('g:i A', strtotime($b['time'])) ?>
                </div>
                <div class="next-booking-info" style="margin-top:4px">Technician: <?= htmlspecialchars($b['tech']) ?></div>
                <div class="booking-countdown">
                    <span class="live-dot" style="background:#ef4444"></span>
                    <?= htmlspecialchars($b['id']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick action row (reference-style) -->
        <div class="qrow fade-in delay-1">
            <a class="qrow-tile" onclick="<?= empty($vehicle['make']) ? "showPanel('vehicle')" : 'openBookModal()' ?>">
                <span class="qrow-ic red"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.7 7.3a1 1 0 0 0-1.6-.3l-2.5 2.5-2.1-2.1 2.5-2.5a1 1 0 0 0-.3-1.6 5.5 5.5 0 0 0-7.1 6.6l-7 7a2.5 2.5 0 0 0 3.5 3.5l7-7a5.5 5.5 0 0 0 6.6-7.1z"/></svg></span>
                <span><span class="qrow-tx-title">Book a Service</span><span class="qrow-tx-sub">Schedule a new service</span></span>
            </a>
            <a class="qrow-tile" onclick="showPanel('vehicle')">
                <span class="qrow-ic blue"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13l2-5a2 2 0 0 1 1.9-1.3h10.2A2 2 0 0 1 19 8l2 5v6a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H6v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zm3.6-4h10.8l-1.2-3H7.8zM6.5 15a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm11 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg></span>
                <span><span class="qrow-tx-title">Add Vehicle</span><span class="qrow-tx-sub">Register a new vehicle</span></span>
            </a>
            <a class="qrow-tile" onclick="showPanel('history')">
                <span class="qrow-ic slate"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 2.5L17.5 8H14zM8 13h8a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2zm0 4h8a1 1 0 0 1 0 2H8a1 1 0 0 1 0-2z"/></svg></span>
                <span><span class="qrow-tx-title">View History</span><span class="qrow-tx-sub">Past services &amp; invoices</span></span>
            </a>
            <a class="qrow-tile" href="https://wa.me/265993575111?text=<?= urlencode('Hi Wiyule Motors, I need emergency assistance.') ?>" target="_blank">
                <span class="qrow-ic green"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/></svg></span>
                <span><span class="qrow-tx-title">Emergency Support</span><span class="qrow-tx-sub">24/7 roadside help</span></span>
            </a>
        </div>

        <!-- Stats -->
        <?php if(count($overdue) > 0): ?>
        <div style="background:rgba(220,38,38,0.12);border:1.5px solid rgba(220,38,38,0.3);border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:4px" class="fade-in delay-1">
            <div style="width:44px;height:44px;border-radius:12px;background:#dc2626;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div style="flex:1">
                <div style="font-size:15px;font-weight:800;color:#dc2626"><?= count($overdue) ?> Overdue Booking<?= count($overdue)!==1?'s':'' ?></div>
                <div style="font-size:13px;color:#ef4444;margin-top:2px">You have missed appointments. Please reschedule or contact us to rebook.</div>
            </div>
            <a href="https://wa.me/265993575111?text=<?= urlencode('Hi Wiyule Motors, I need to reschedule my overdue booking(s). Can you help?') ?>" target="_blank" style="background:#dc2626;color:#fff;border:none;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;white-space:nowrap;text-decoration:none;display:flex;align-items:center;gap:6px">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                Reschedule
            </a>
        </div>
        <?php endif; ?>

        <div class="stats-grid fade-in delay-1">
            <?php 
            $overdue_count = count($overdue);
            $completed_count = count(array_filter($service_history, fn($h)=>$h['status']==='completed'));
            $upcoming_n = count($upcoming_bookings);
            $stats = [
                [
                    'label'=>'Services Completed', 'value'=>$completed_count, 'icon'=>'check-circle', 'color'=>'red',
                    'sub'=>'All time', 'link'=>['View history', "showPanel('history')"],
                ],
                [
                    'label'=>'Next Service Due', 'value'=> $upcoming_n > 0 ? date('d M', strtotime($upcoming_bookings[0]['date'])) : '—',
                    'icon'=>'calendar', 'color'=>'amber',
                    'sub'=> $upcoming_n > 0 ? 'Scheduled appointment' : 'Nothing booked',
                    'link'=> $upcoming_n > 0 ? ['View booking', "showPanel('bookings')"] : ['Book now', "openBookModal()"],
                ],
                [
                    'label'=>'Total Bookings', 'value'=>$total_all_bookings, 'icon'=>'tool', 'color'=>'blue',
                    'sub'=>'All time', 'link'=>['View all', "showPanel('bookings')"],
                ],
                [
                    'label'=> $overdue_count > 0 ? 'Overdue' : 'My Vehicles',
                    'value'=> $overdue_count > 0 ? $overdue_count : count($all_vehicles),
                    'icon'=> $overdue_count > 0 ? 'alert-triangle' : 'truck',
                    'color'=> $overdue_count > 0 ? 'amber' : 'green',
                    'sub'=> $overdue_count > 0 ? 'Needs attention' : 'Registered',
                    'link'=> $overdue_count > 0 ? ['Reschedule', "showPanel('bookings')"] : ['View details', "showPanel('vehicle')"],
                ],
            ];
            foreach ($stats as $s): ?>
            <div class="stat-card <?= $s['color'] ?>">
                <div class="stat-card-top">
                    <div class="stat-icon <?= $s['color'] ?>"><?= wm_icon($s['icon']) ?></div>
                    <span class="stat-toplabel"><?= htmlspecialchars($s['label']) ?></span>
                </div>
                <div class="stat-value<?= is_numeric($s['value']) ? ' counter-value' : '' ?>"<?= is_numeric($s['value']) ? ' data-target="'.$s['value'].'"' : '' ?>><?= htmlspecialchars($s['value']) ?></div>
                <div class="stat-change"><?= htmlspecialchars($s['sub']) ?></div>
                <a class="stat-link" onclick="<?= $s['link'][1] ?>"><?= htmlspecialchars($s['link'][0]) ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3" style="gap:16px">

            <div class="lg:col-span-2 flex flex-col" style="gap:16px">

                <div class="card fade-in delay-2">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            Upcoming Bookings
                        </div>
                        <a class="card-action" onclick="openBookModal()">+ New booking</a>
                    </div>
                    <div class="card-body" id="upcomingBookingsContainer">
                        <?php if(empty($upcoming_bookings)): ?>
                        <div class="text-center py-10">
                            <div style="font-size:42px;opacity:0.25">📅</div>
                            <p class="text-sm text-gray-400 mt-2">No upcoming bookings</p>
                            <button onclick="openBookModal()" class="mt-4 text-sm font-bold text-red-600 hover:underline border-none background-none cursor-pointer">Book your first service →</button>
                        </div>
                        <?php else: ?>
                        <?php foreach ($upcoming_bookings as $b): ?>
                        <div class="booking-row" data-booking-id="<?= htmlspecialchars($b['id']) ?>">
                            <div class="booking-service-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            </div>
                            <div>
                                <div class="booking-name"><?= htmlspecialchars($b['service']) ?></div>
                                <div class="booking-meta">
                                    <?= date('D, d M Y', strtotime($b['date'])) ?> · <?= date('g:i A', strtotime($b['time'])) ?> · <?= htmlspecialchars($b['tech']) ?>
                                </div>
                            </div>
                            <div class="booking-id"><?= htmlspecialchars($b['id']) ?></div>
                            <div><?= statusPill($b['status']) ?></div>
                        </div>
                        <?= wm_job_stage_html($b['job_stage'] ?? '') ?>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card fade-in delay-3">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>
                            Service History
                        </div>
                        <a class="card-action" onclick="showPanel('history')">View all</a>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php if(empty($service_history)): ?>
                            <div class="text-center py-8">
                                <div style="font-size:42px;opacity:0.25">🔧</div>
                                <p class="text-sm text-gray-400 mt-2">No service history yet</p>
                                <p class="text-xs text-gray-400 mt-1">Your first service will appear here</p>
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($service_history,0,4) as $h): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-card">
                                    <div class="timeline-card-header">
                                        <div class="timeline-service"><?= htmlspecialchars($h['service']) ?></div>
                                        <div class="timeline-cost"><?= htmlspecialchars($h['cost']) ?></div>
                                    </div>
                                    <div class="timeline-meta">
                                        <?= date('d M Y', strtotime($h['date'])) ?> · <?= htmlspecialchars($h['tech']) ?> · <?= htmlspecialchars($h['id']) ?>
                                    </div>
                                    <div class="timeline-notes"><?= htmlspecialchars($h['notes']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recommended for You -->
                <div class="card fade-in delay-3">
                    <div class="card-header">
                        <div class="card-title"><?= wm_icon('star') ?> Recommended for You</div>
                        <a class="card-action" onclick="openBookModal()">Book a service</a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Smart recommendations based on the user's real service interval progress
                        $recs = [];
                        if (!empty($all_vehicles)) {
                            $rv = $all_vehicles[0];
                            $rv_km = (int)($rv['mileage'] ?? 0);
                            $recs[] = ['name'=>'Engine Oil & Filter Change','desc'=>'Recommended every 5,000 km','price'=>'from MWK 45,000','icon'=>'droplet','color'=>'amber'];
                            $recs[] = ['name'=>'Brake Inspection','desc'=>'Safety check & pad wear','price'=>'from MWK 20,000','icon'=>'disc','color'=>'red'];
                            $recs[] = ['name'=>'Tyre Rotation & Balance','desc'=>'Even wear, longer tyre life','price'=>'from MWK 15,000','icon'=>'circle','color'=>'blue'];
                        } else {
                            $recs[] = ['name'=>'Add your vehicle','desc'=>'Get personalised service recommendations','price'=>'','icon'=>'truck','color'=>'green'];
                        }
                        foreach ($recs as $rec): ?>
                        <div class="rec-row">
                            <div class="rec-icon <?= $rec['color'] ?>"><?= wm_icon($rec['icon']) ?></div>
                            <div class="rec-info">
                                <div class="rec-name"><?= htmlspecialchars($rec['name']) ?></div>
                                <div class="rec-desc"><?= htmlspecialchars($rec['desc']) ?></div>
                            </div>
                            <?php if($rec['price']): ?>
                            <div class="rec-right">
                                <div class="rec-price"><?= htmlspecialchars($rec['price']) ?></div>
                                <button class="rec-book" onclick="openBookModal()">Book</button>
                            </div>
                            <?php else: ?>
                            <button class="rec-book" onclick="showPanel('vehicle')">Add</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Service Progress -->
                <?php
                // Compute service progress for the primary vehicle using real data
                $sp_show = false; $sp_pct = 0; $sp_remaining = 5000; $sp_current = 0; $sp_target = 5000;
                if (!empty($all_vehicles)) {
                    $spv = $all_vehicles[0];
                    $sp_km = (int)($spv['mileage'] ?? 0);
                    $sph = $vehicles_health[(int)$spv['id']] ?? null;
                    $sp_last = $sph['last_service_km'] ?? null;
                    if ($sp_last !== null && $sp_km >= $sp_last) {
                        $sp_show = true;
                        $since = $sp_km - $sp_last;
                        $sp_pct = min(100, round($since / 5000 * 100));
                        $sp_remaining = max(0, 5000 - $since);
                        $sp_current = $sp_km;
                        $sp_target = $sp_last + 5000;
                    }
                }
                $sp_circ = 2 * 3.14159 * 34;
                $sp_dash = $sp_circ * (1 - $sp_pct/100);
                ?>
                <div class="card fade-in delay-3">
                    <div class="card-header">
                        <div class="card-title"><?= wm_icon('zap') ?> Service Progress</div>
                        <a class="card-action" onclick="showPanel('vehicle')">View plan</a>
                    </div>
                    <div class="card-body">
                        <?php if($sp_show): ?>
                        <div style="display:flex;align-items:center;gap:20px">
                            <div style="position:relative;width:84px;height:84px;flex-shrink:0">
                                <svg width="84" height="84" viewBox="0 0 84 84" style="transform:rotate(-90deg)">
                                    <circle cx="42" cy="42" r="34" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8"/>
                                    <circle cx="42" cy="42" r="34" fill="none" stroke="<?= $sp_pct >= 90 ? '#ef4444' : ($sp_pct >= 70 ? '#f59e0b' : '#22c55e') ?>" stroke-width="8" stroke-linecap="round" stroke-dasharray="<?= $sp_circ ?>" stroke-dashoffset="<?= $sp_dash ?>" style="transition:stroke-dashoffset 1s ease"/>
                                </svg>
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;color:#fff"><?= $sp_pct ?>%</div>
                            </div>
                            <div style="flex:1">
                                <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:3px"><?= number_format($sp_remaining) ?> km remaining</div>
                                <div style="font-size:12px;color:var(--text-3);margin-bottom:10px">until your next recommended service</div>
                                <div style="height:6px;background:rgba(255,255,255,0.08);border-radius:99px;overflow:hidden">
                                    <div style="height:100%;width:<?= $sp_pct ?>%;border-radius:99px;background:linear-gradient(90deg,#22c55e,#16a34a)"></div>
                                </div>
                                <div style="font-size:11px;color:var(--text-3);margin-top:6px"><?= number_format($sp_current) ?> km / <?= number_format($sp_target) ?> km</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align:center;padding:14px 0">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(34,197,94,0.16);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#4ade80"><?= wm_icon('check-circle', 24) ?></div>
                            <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px">Service history starts here</div>
                            <div style="font-size:12px;color:var(--text-3);margin-bottom:14px">Book your first service with us to start tracking maintenance intervals.</div>
                            <button onclick="openBookModal()" class="rec-book">Book a Service</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="flex flex-col" style="gap:16px">

                <div class="vehicle-card fade-in delay-2" id="vehicleSwitcherCard">
                    <?php if(count($all_vehicles) === 0): ?>
                    <div style="text-align:center;padding:20px 0">
                        <div style="font-size:48px;opacity:0.3;margin-bottom:10px">🚗</div>
                        <div style="font-size:15px;font-weight:800;color:#fff;margin-bottom:4px">No Vehicle Added Yet</div>
                        <div style="font-size:13px;color:#94a3b8;margin-bottom:16px">Add your car details to get personalized service</div>
                        <button onclick="showPanel('vehicle')" style="background:var(--red);color:#fff;border:none;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">+ Add Vehicle</button>
                    </div>
                    <?php else: ?>

                    <?php if(count($all_vehicles) > 1): ?>
                    <!-- Vehicle Switcher Tabs (shown when user has 2+ vehicles) -->
                    <div style="display:flex;gap:6px;margin-bottom:16px;padding:4px;background:rgba(255,255,255,0.06);border-radius:12px;overflow-x:auto;scrollbar-width:none">
                        <?php foreach($all_vehicles as $i => $v): ?>
                        <button type="button"
                                class="vswitch-tab<?= $i === 0 ? ' active' : '' ?>"
                                data-vidx="<?= $i ?>"
                                onclick="switchOverviewVehicle(<?= $i ?>)"
                                style="background:<?= $i === 0 ? 'var(--red)' : 'transparent' ?>;border:none;color:<?= $i === 0 ? '#fff' : '#94a3b8' ?>;padding:8px 14px;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;white-space:nowrap;transition:all 0.2s">
                            <?= htmlspecialchars($v['make'].' '.$v['model']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Active vehicle details (updates when tab clicked) -->
                    <?php foreach($all_vehicles as $i => $v):
                        // Compute per-vehicle service progress based on REAL completed services
                        $v_last_km = (int)($v['mileage'] ?? 0);

                        // Reuse the data already fetched in the consolidated health loop above
                        // (no extra DB query here — that's what was causing the 503 overload)
                        $vh_data_ref = $vehicles_health[(int)$v['id']] ?? null;
                        $v_last_service_km = $vh_data_ref['last_service_km'] ?? null;

                        // Default values for vehicles never serviced with us
                        $v_service_pct = 0;
                        $v_km_remaining = 5000;
                        $v_show_progress = false;
                        $v_km_since_service = 0;

                        if ($v_last_service_km !== null && $v_last_km >= $v_last_service_km) {
                            // We have real service data — calculate from actual last service mileage
                            $v_show_progress = true;
                            $v_km_since_service = $v_last_km - $v_last_service_km;
                            $v_service_pct = min(100, round($v_km_since_service / 5000 * 100));
                            $v_km_remaining = max(0, 5000 - $v_km_since_service);
                        }
                    ?>
                    <div class="vswitch-pane" data-vidx="<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
                        <div class="vehicle-make"><?= htmlspecialchars($v['make']) ?></div>
                        <div class="vehicle-name"><?= htmlspecialchars($v['model']) ?></div>
                        <div class="vehicle-year"><?= htmlspecialchars(($v['year'] ?: '—') .' · '. ($v['color'] ?: '—')) ?></div>
                        <?php if($v['registration']): ?><div class="vehicle-reg"><?= htmlspecialchars($v['registration']) ?></div><?php endif; ?>
                        <?php
                            // Premium clean look: brand badge as primary (no watermark, always loads).
                            // The watermark-free HD render path is kept in wm_car_image() for when
                            // a paid imagin.studio key is added later — swap the two lines below to enable.
                            $brand_logo = wm_brand_logo($v['make']);
                            $car_img = $brand_logo;
                            $is_logo_primary = true;
                        ?>
                        <div class="vehicle-photo-wrap">
                            <img src="<?= htmlspecialchars($car_img) ?>"
                                 alt="<?= htmlspecialchars($v['make'].' '.$v['model']) ?>"
                                 class="vehicle-photo<?= $is_logo_primary ? ' is-logo' : '' ?>"
                                 loading="lazy"
                                 onerror="wmCarImgFallback(this)">
                            <div class="vehicle-photo-silhouette" style="display:none">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13l2-5a2 2 0 0 1 1.9-1.3h10.2A2 2 0 0 1 19 8l2 5v6a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H6v1a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zm3.6-4h10.8l-1.2-3H7.8zM6.5 15a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm11 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>
                            </div>
                        </div>
                        <div class="vehicle-spec">
                            <div class="vehicle-spec-item">
                                <div class="vehicle-spec-label">Mileage</div>
                                <div class="vehicle-spec-value"><?= $v['mileage'] ? number_format($v['mileage']).' km' : '—' ?></div>
                            </div>
                            <div class="vehicle-spec-item" style="padding-left:12px">
                                <div class="vehicle-spec-label">Engine</div>
                                <div class="vehicle-spec-value"><?= $v['engine'] ? htmlspecialchars($v['engine']) : '—' ?></div>
                            </div>
                        </div>
                        <?php if($v_show_progress): ?>
                        <div class="mileage-section">
                            <div class="mileage-label">
                                <span>Service interval progress</span>
                                <span><?= $v_service_pct ?>% used</span>
                            </div>
                            <div class="mileage-bar">
                                <div class="mileage-fill" style="width:<?= $v_service_pct ?>%"></div>
                            </div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:6px">
                                <?php if($v_km_remaining > 0): ?>
                                    <?= number_format($v_km_remaining) ?> km remaining until next service
                                <?php else: ?>
                                    <span style="color:#fca5a5;font-weight:600">⚠ Service overdue</span>
                                <?php endif; ?>
                                <span style="opacity:0.6"> · Last serviced at <?= number_format($v_last_service_km) ?> km</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mileage-section" style="background:rgba(255,255,255,0.04);border:1px dashed rgba(255,255,255,0.15);border-radius:10px;padding:14px;margin-top:14px">
                            <div style="font-size:12px;color:#94a3b8;display:flex;align-items:center;gap:8px;line-height:1.5">
                                <span style="font-size:18px">🆕</span>
                                <div>
                                    <strong style="color:#fff;display:block;margin-bottom:2px">Service history starts here</strong>
                                    Book your first service with us to start tracking maintenance intervals.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- Quick action: book for this vehicle -->
                        <button onclick="bookForVehicle(<?= (int)$v['id'] ?>)"
                                style="margin-top:16px;width:100%;background:rgba(239,68,68,0.18);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;padding:10px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">
                            Book Service for this Vehicle →
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card fade-in delay-3" id="healthCard">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            Vehicle Health
                        </div>
                        <a class="card-action" onclick="showPanel('health')">Details</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($all_vehicles) === 0): ?>
                        <div style="text-align:center;padding:20px 0;color:#94a3b8">
                            <div style="font-size:40px;opacity:0.3;margin-bottom:8px">⚕️</div>
                            <div style="font-size:13px">Add a vehicle to see its health report</div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($all_vehicles as $i => $v):
                            $vh_id   = (int)$v['id'];
                            $vh_data = $vehicles_health[$vh_id] ?? null;
                            if (!$vh_data) continue;
                            $vh_facts   = $vh_data['facts'];
                            $vh_status  = $vh_data['status'];
                            $vh_overall = $vh_data['overall'];
                            [$stCol, $stBg] = statusTone($vh_status['tone']);
                        ?>
                        <div class="health-pane" data-vidx="<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
                            <div class="health-ring-wrap">
                                <div class="health-ring">
                                    <svg width="140" height="140" viewBox="0 0 140 140">
                                        <circle cx="70" cy="70" r="56" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="10"/>
                                        <circle cx="70" cy="70" r="56" fill="none"
                                                stroke="<?= healthColor($vh_overall) ?>"
                                                stroke-width="10"
                                                stroke-linecap="round"
                                                stroke-dasharray="<?= round(2*pi()*56) ?>"
                                                stroke-dashoffset="<?= round(2*pi()*56 * (1 - $vh_overall/100)) ?>"
                                                style="transition:stroke-dashoffset 1.2s cubic-bezier(0.4,0,0.2,1)"/>
                                    </svg>
                                    <div class="health-ring-inner">
                                        <div class="health-pct" style="color:<?= healthColor($vh_overall) ?>"><?= $vh_overall ?>%</div>
                                        <div class="health-sublabel">Service health</div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:center;margin:10px 0 2px">
                                <strong style="color:#f1f5f9;font-size:13px"><?= htmlspecialchars($v['make'].' '.$v['model']) ?></strong>
                                <div style="margin-top:6px"><span style="font-size:11px;font-weight:700;padding:4px 12px;border-radius:99px;color:<?= $stCol ?>;background:<?= $stBg ?>"><?= htmlspecialchars($vh_status['label']) ?></span></div>
                            </div>
                            <div class="health-items" style="margin-top:12px">
                                <?php foreach ($vh_facts as $f): ?>
                                <div class="health-row">
                                    <div class="health-icon" style="background:rgba(255,255,255,0.06);color:#cbd5e1">
                                        <?= wm_icon($f['icon']) ?>
                                    </div>
                                    <div class="health-bar-wrap">
                                        <div class="health-bar-label">
                                            <span><?= htmlspecialchars($f['label']) ?></span>
                                            <span style="color:#f1f5f9;font-weight:700"><?= htmlspecialchars($f['value']) ?></span>
                                        </div>
                                        <?php if(!empty($f['sub'])): ?><div style="font-size:11px;color:#94a3b8;margin-top:2px"><?= htmlspecialchars($f['sub']) ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card fade-in delay-4">
                    <div class="card-header">
                        <div class="card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            Quick Actions
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="#" class="quick-btn" onclick="openBookModal()">
                                <div class="quick-btn-icon bg-red-50 text-red-600"><?= wm_icon('calendar') ?></div>
                                <span class="quick-btn-label">Book Service</span>
                            </a>
                            <a href="https://wa.me/265993575111" target="_blank" class="quick-btn">
                                <div class="quick-btn-icon bg-green-50 text-green-600"><?= wm_icon('message-circle') ?></div>
                                <span class="quick-btn-label">WhatsApp Us</span>
                            </a>
                            <a href="tel:+265993575111" class="quick-btn">
                                <div class="quick-btn-icon bg-red-50 text-red-600"><?= wm_icon('phone') ?></div>
                                <span class="quick-btn-label">Call Now</span>
                            </a>
                            <a href="#" class="quick-btn" onclick="showPanel('history')">
                                <div class="quick-btn-icon" style="background:#f5f5f7;color:#1d1d1f"><?= wm_icon('file-text') ?></div>
                                <span class="quick-btn-label">Invoices</span>
                            </a>
                        </div>

                        <div class="offer-banner" style="margin-top:14px" onclick="openBookModal()">
                            <div class="offer-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div>
                                <div class="offer-text-title">15% Off Auto Detailing</div>
                                <div class="offer-text-sub">Member exclusive · Ends 31 March</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="card fade-in delay-5">
            <div class="card-header">
                <div class="card-title">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Maintenance Reminders
                    <?php if($urgent_count): ?><span class="status-pill bg-red-100 text-red-600"><?= $urgent_count ?> urgent</span><?php endif; ?>
                </div>
                <a class="card-action" onclick="showPanel('reminders')">View all</a>
            </div>
            <div class="card-body">
                <?php if(empty($reminders)): ?>
                <div class="text-center py-8">
                    <div style="font-size:42px;opacity:0.25">✅</div>
                    <p class="text-sm text-gray-400 mt-2">All maintenance is up to date!</p>
                    <p class="text-xs text-gray-400 mt-1">We'll notify you when service is due</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($reminders as $r):
                        $kms_left = max(0, $r['due_km'] - $r['current_km']);
                        $pct = min(100, round(($r['current_km'] - ($r['due_km'] - 8000)) / 8000 * 100));
                        $pct = max(0, $pct);
                    ?>
                    <div class="reminder-item <?= urgencyClasses($r['urgency']) ?>">
                        <div class="reminder-icon <?= $r['urgency']==='urgent' ? 'bg-red-100 text-red-600' : ($r['urgency']==='soon' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                            <?= wm_icon($r['icon']) ?>
                        </div>
                        <div class="reminder-body">
                            <div class="reminder-title"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="reminder-sub">
                                <?= $kms_left > 0 ? number_format($kms_left).' km left' : 'Overdue' ?>
                                · <?= htmlspecialchars($r['interval']) ?>
                            </div>
                            <div class="km-bar-wrap">
                                <div class="km-bar" style="margin-top:6px">
                                    <div class="km-fill" style="width:<?= $pct ?>%; background:<?= $r['urgency']==='urgent' ? '#dc2626' : ($r['urgency']==='soon' ? '#f59e0b' : '#16a34a') ?>"></div>
                                </div>
                            </div>
                        </div>
                        <?= urgencyBadge($r['urgency']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ─── BOOKINGS PANEL ─── (Same as original, trimmed for length - keep from original) -->
    <div id="panel-bookings" class="page-body" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px" class="fade-in">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">My Bookings</h1>
                <p style="font-size:13px;color:#94a3b8;margin-top:2px">Manage all your upcoming and past service appointments</p>
            </div>
            <button onclick="openBookModal()" class="dash-glass-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </button>
        </div>

        <?php foreach ([['Upcoming', $upcoming_bookings], ['Past Services', $service_history]] as [$section, $items]): ?>
        <div class="card fade-in delay-1">
            <div class="card-header">
                <div class="card-title"><?= $section ?> <span style="font-size:12px;background:rgba(255,255,255,0.06);color:#94a3b8;padding:3px 8px;border-radius:99px;font-weight:600"><?= count($items) ?></span></div>
            </div>
            <div class="card-body">
                <?php if(empty($items)): ?>
                <div class="text-center py-8">
                    <div style="font-size:42px;opacity:0.25">📋</div>
                    <p class="text-sm text-gray-400 mt-2">No <?= strtolower($section) ?> found</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="border-bottom:2px solid #f1f5f9">
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Service</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Date & Time</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Ref</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Status</th>
                                <th style="text-align:left;padding:8px 12px;font-weight:700;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:0.06em">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i): ?>
                            <tr style="border-bottom:1px solid #f8fafc;transition:background 0.15s" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <td style="padding:12px;font-weight:600;color:#f1f5f9"><?= htmlspecialchars($i['service']) ?></td>
                                <td style="padding:12px;color:#94a3b8"><?= date('d M Y', strtotime($i['date'])) ?><?= isset($i['time']) ? ' · '.date('g:i A', strtotime($i['time'])) : '' ?></td>
                                <td style="padding:12px;font-family:monospace;color:#94a3b8;font-size:12px"><?= htmlspecialchars($i['id']) ?></td>
                                <td style="padding:12px"><?= statusPill($i['status']) ?></td>
                                <td style="padding:12px">
                                    <?php if($section==='Upcoming' && in_array($i['status'], ['pending','confirmed'])): ?>
                                    <div style="display:flex;gap:6px">
                                        <a href="https://wa.me/265993575111?text=<?= urlencode('Hi, I need to reschedule my booking '.$i['id'].' ('.$i['service'].') on '.date('d M Y',strtotime($i['date'])).'. Can you help?') ?>" target="_blank" style="font-size:12px;font-weight:600;color:#2563eb;background:none;border:1px solid #bfdbfe;padding:5px 10px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif;text-decoration:none">Reschedule</a>
                                        <button onclick="cancelBooking(<?= $i['db_id'] ?>,'<?= htmlspecialchars(addslashes($i['service'])) ?>')" style="font-size:12px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:5px 10px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif">Cancel</button>
                                    </div>
                                    <?php elseif($section==='Past Services'): ?>
                                    <span style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($i['cost'] ?? '—') ?></span>
                                    <?php else: ?>
                                    <span style="font-size:12px;color:#94a3b8">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($section === 'Upcoming' && !empty($i['job_stage'])): ?>
                            <tr><td colspan="5" style="padding:0 12px 12px"><?= wm_job_stage_html($i['job_stage']) ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ─── HISTORY PANEL ─── (Keep from original with empty state) -->
    <div id="panel-history" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">Service History</h1>
            <p style="font-size:13px;color:#94a3b8;margin-top:2px">Complete record of all services performed on your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?></p>
        </div>

        <?php if(empty($service_history)): ?>
        <div class="card fade-in delay-1">
            <div class="card-body text-center py-12">
                <div style="font-size:64px;opacity:0.3">🔧</div>
                <h3 class="text-lg font-bold text-white mt-4">No Service History Yet</h3>
                <p class="text-gray-400 mt-2">Your service records will appear here once you book your first service.</p>
                <button onclick="openBookModal()" class="mt-6 bg-red-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-red-700 transition">Book Your First Service →</button>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 fade-in delay-1">
            <?php
            $total_services = count($service_history);
            $most_recent = $service_history[0]['date'];
            ?>
            <div class="stat-card red"><div class="stat-card-top"><div class="stat-icon red"><?= wm_icon('tool') ?></div><span class="stat-toplabel">Total Services</span></div><div class="stat-value"><?= $total_services ?></div><div class="stat-change">Lifetime record</div></div>
            <div class="stat-card green"><div class="stat-card-top"><div class="stat-icon green"><?= wm_icon('dollar-sign') ?></div><span class="stat-toplabel">Total Invested</span></div><div class="stat-value" style="font-size:26px">MWK 125.5K</div><div class="stat-change">In your vehicle</div></div>
            <div class="stat-card blue"><div class="stat-card-top"><div class="stat-icon blue"><?= wm_icon('calendar') ?></div><span class="stat-toplabel">Last Service</span></div><div class="stat-value" style="font-size:24px"><?= date('M Y', strtotime($most_recent)) ?></div><div class="stat-change">Most recent visit</div></div>
            <div class="stat-card amber"><div class="stat-card-top"><div class="stat-icon amber"><?= wm_icon('star') ?></div><span class="stat-toplabel">Avg. Rating</span></div><div class="stat-value">5.0</div><div class="stat-change">★★★★★ Excellent</div></div>
        </div>

        <div class="card fade-in delay-2">
            <div class="card-header"><div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 0 .5-4.5"/><polyline points="3 2 3 8 9 8"/></svg>Full Timeline</div></div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($service_history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-card">
                            <div class="timeline-card-header">
                                <div class="timeline-service"><?= htmlspecialchars($h['service']) ?></div>
                                <div class="timeline-cost"><?= htmlspecialchars($h['cost']) ?></div>
                            </div>
                            <div class="timeline-meta">
                                <?= date('d M Y', strtotime($h['date'])) ?> · <?= htmlspecialchars($h['tech']) ?> · <span style="font-family:monospace"><?= htmlspecialchars($h['id']) ?></span>
                            </div>
                            <div class="timeline-notes"><?= htmlspecialchars($h['notes']) ?></div>

                            <?php if (!empty($h['photos'])): ?>
                            <div style="margin-top:10px">
                                <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">Photos you sent</div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px">
                                    <?php foreach ($h['photos'] as $pu): ?>
                                    <a href="<?= htmlspecialchars($pu) ?>" target="_blank" rel="noopener" style="display:block;width:60px;height:60px;border-radius:8px;overflow:hidden;border:1px solid rgba(255,255,255,0.12)"><img src="<?= htmlspecialchars($pu) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover"></a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($h['rating'])): ?>
                            <div style="margin-top:10px;background:rgba(255,255,255,0.04);border-radius:10px;padding:10px 12px">
                                <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px">Your feedback</div>
                                <div style="font-size:16px;letter-spacing:2px;color:#f59e0b"><?= str_repeat('★', (int)$h['rating']) ?><span style="color:rgba(255,255,255,0.15)"><?= str_repeat('★', 5 - (int)$h['rating']) ?></span></div>
                                <?php if (!empty($h['comment'])): ?><div style="font-size:12.5px;color:#94a3b8;font-style:italic;margin-top:4px">"<?= htmlspecialchars($h['comment']) ?>"</div><?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div style="margin-top:10px;display:flex;gap:8px">
                                <?php if (!empty($h['invoice_id'])): ?>
                                <a href="<?= $base ?>/admin/invoices/print.php?id=<?= (int)$h['invoice_id'] ?>" target="_blank" rel="noopener" style="font-size:12px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif;text-decoration:none">🧾 Download Invoice</a>
                                <?php endif; ?>
                                <?php if (empty($h['rating'])): ?>
                                <button style="font-size:12px;font-weight:600;color:#94a3b8;background:none;border:1px solid rgba(255,255,255,0.12);padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif">Leave Review</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── REMINDERS PANEL ─── (Keep from original with empty state) -->
    <div id="panel-reminders" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">Maintenance Reminders</h1>
            <p style="font-size:13px;color:#94a3b8;margin-top:2px">Stay on top of your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?>'s maintenance schedule</p>
        </div>

        <?php if (!empty($upcoming_renewals)): ?>
        <div class="card fade-in delay-1" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title">📋 Official renewals due</div></div>
            <div class="card-body">
                <?php foreach ($upcoming_renewals as $ur):
                    [$rl, $rc, $rb] = wm_renewal_badge($ur['date']);
                    $msg = $ur['days'] < 0
                        ? 'Expired ' . abs($ur['days']) . ' day' . (abs($ur['days']) !== 1 ? 's' : '') . ' ago'
                        : ($ur['days'] === 0 ? 'Expires today' : 'Due in ' . $ur['days'] . ' day' . ($ur['days'] !== 1 ? 's' : ''));
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05)">
                    <div style="min-width:0">
                        <div style="font-weight:700;color:#f1f5f9;font-size:14px"><?= htmlspecialchars($ur['type']) ?> · <?= htmlspecialchars($ur['vehicle']) ?></div>
                        <div style="font-size:12px;color:#94a3b8"><?= date('d M Y', strtotime($ur['date'])) ?></div>
                    </div>
                    <span style="font-weight:700;font-size:12px;color:<?= $rc ?>;background:<?= $rb ?>;padding:4px 12px;border-radius:99px;white-space:nowrap;flex-shrink:0"><?= htmlspecialchars($msg) ?></span>
                </div>
                <?php endforeach; ?>
                <div style="font-size:12px;color:#64748b;margin-top:10px">Set or update these dates per vehicle under <strong style="color:#94a3b8">My Vehicles</strong>.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(empty($reminders)): ?>
        <div class="card fade-in delay-1">
            <div class="card-body text-center py-12">
                <div style="font-size:64px;opacity:0.3">✅</div>
                <h3 class="text-lg font-bold text-white mt-4">All Caught Up!</h3>
                <p class="text-gray-400 mt-2">Your vehicle is in great shape. No maintenance reminders at this time.</p>
                <button onclick="showPanel('vehicle')" class="mt-6 bg-gray-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-gray-700 transition">View Vehicle Details</button>
            </div>
        </div>
        <?php else: ?>
        <?php if($urgent_count): ?>
        <div style="background:rgba(220,38,38,0.12);border:1.5px solid rgba(220,38,38,0.3);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px" class="fade-in delay-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <div style="font-size:14px;font-weight:800;color:#dc2626"><?= $urgent_count ?> item<?= $urgent_count>1?'s':'' ?> need immediate attention</div>
                <div style="font-size:13px;color:#ef4444;margin-top:2px">Book a service now to prevent damage and keep your warranty valid.</div>
            </div>
            <button onclick="openBookModal()" style="margin-left:auto;background:#dc2626;color:#fff;border:none;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;white-space:nowrap">Book Now</button>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 fade-in delay-2">
            <?php foreach ($reminders as $r):
                $days = $r['days_since'] ?? 0;
                $bar_color = $r['urgency']==='urgent' ? '#dc2626' : ($r['urgency']==='soon' ? '#f59e0b' : '#16a34a');
                $pct = $r['urgency']==='urgent' ? 95 : ($r['urgency']==='soon' ? 70 : 30);
            ?>
            <div class="card" style="<?= $r['urgency']==='urgent' ? 'border:1.5px solid #fecaca;' : ($r['urgency']==='soon' ? 'border:1.5px solid #fde68a;' : '') ?>">
                <div class="card-body" style="padding:20px">
                    <div style="display:flex;align-items:flex-start;gap:14px">
                        <div style="width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:<?= $r['urgency']==='urgent' ? '#fef2f2' : ($r['urgency']==='soon' ? '#fffbeb' : '#f0fdf4') ?>;color:<?= $bar_color ?>">
                            <?= wm_icon($r['icon']) ?>
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                                <div style="font-size:15px;font-weight:800;color:#f1f5f9"><?= htmlspecialchars($r['title']) ?></div>
                                <?= urgencyBadge($r['urgency']) ?>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-bottom:10px">
                                <?= htmlspecialchars($r['interval']) ?>
                                <?php if($r['last_date']): ?>
                                 · Last: <?= date('d M Y', strtotime($r['last_date'])) ?> (<?= $days ?> day<?= $days!==1?'s':'' ?> ago)
                                <?php endif; ?>
                            </div>
                            <div style="background:rgba(255,255,255,0.06);border-radius:99px;height:8px;overflow:hidden;margin-bottom:6px">
                                <div style="width:<?= $pct ?>%;height:100%;background:<?= htmlspecialchars($bar_color) ?>;border-radius:99px;transition:width 1.2s"></div>
                            </div>
                        </div>
                    </div>
                    <?php if($r['urgency'] !== 'ok'): ?>
                    <button onclick="openBookModal()" style="width:100%;margin-top:14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:10px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer">
                        Schedule Now →
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── VEHICLE PANEL ─── (Keep from original) -->
    <div id="panel-vehicle" class="page-body" style="display:none">
        <div class="fade-in" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">My Vehicles</h1>
                <p style="font-size:13px;color:#94a3b8;margin-top:2px"><?= count($all_vehicles) ?> vehicle<?= count($all_vehicles)!==1?'s':'' ?> registered</p>
            </div>
            <?php if(count($all_vehicles) < 5): ?>
            <button onclick="openVehicleModal()" class="dash-glass-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Vehicle
            </button>
            <?php endif; ?>
        </div>

        <?php if(count($all_vehicles) === 0): ?>
        <!-- Empty state -->
        <div class="card fade-in delay-1" style="text-align:center;padding:48px 24px">
            <div style="width:64px;height:64px;border-radius:16px;background:rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div style="font-size:18px;font-weight:800;color:#f1f5f9;margin-bottom:6px">No vehicles added yet</div>
            <p style="font-size:13px;color:#94a3b8;margin-bottom:20px">Add your vehicle to get personalized service recommendations</p>
            <button onclick="openVehicleModal()" class="dash-glass-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;box-shadow:0 4px 14px rgba(220,38,38,0.3)">+ Add Your First Vehicle</button>
        </div>
        <?php else: ?>
        <!-- Vehicle cards grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach($all_vehicles as $i => $v): ?>
            <div class="vehicle-card fade-in delay-<?= min($i+1, 4) ?>" style="border-radius:16px;position:relative" id="vcard-<?= $v['id'] ?>">
                <?php if($i === 0): ?><div style="position:absolute;top:12px;right:12px;background:rgba(220,38,38,0.9);color:#fff;font-size:10px;font-weight:800;padding:3px 10px;border-radius:99px;letter-spacing:0.08em;text-transform:uppercase">Primary</div><?php endif; ?>
                <div class="vehicle-make"><?= htmlspecialchars($v['make']) ?></div>
                <div class="vehicle-name"><?= htmlspecialchars($v['model']) ?></div>
                <div class="vehicle-year"><?= htmlspecialchars($v['year'] . ($v['color'] ? ' · '.$v['color'] : '')) ?></div>
                <?php if($v['registration']): ?><div class="vehicle-reg"><?= htmlspecialchars($v['registration']) ?></div><?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px">
                    <?php if($v['engine']): ?>
                    <div><div class="vehicle-spec-label">Engine</div><div class="vehicle-spec-value"><?= htmlspecialchars($v['engine']) ?></div></div>
                    <?php endif; ?>
                    <?php if($v['transmission']): ?>
                    <div><div class="vehicle-spec-label">Transmission</div><div class="vehicle-spec-value"><?= htmlspecialchars($v['transmission']) ?></div></div>
                    <?php endif; ?>
                    <div>
                        <div class="vehicle-spec-label">Mileage</div>
                        <div class="vehicle-spec-value" id="mileageVal-<?= (int)$v['id'] ?>"><?= number_format($v['mileage']) ?> km</div>
                        <button type="button" onclick="startMileageEdit(<?= (int)$v['id'] ?>)" style="margin-top:3px;background:none;border:none;color:#60a5fa;font-size:11px;font-weight:600;cursor:pointer;padding:0;font-family:'Sora',sans-serif">✎ Update</button>
                        <div id="mileageEdit-<?= (int)$v['id'] ?>" style="display:none;margin-top:6px">
                            <input type="number" id="mileageInput-<?= (int)$v['id'] ?>" min="0" max="9999999" value="<?= (int)$v['mileage'] ?>" style="width:88px;padding:6px 8px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-family:'Sora',sans-serif;font-size:13px">
                            <div style="display:flex;gap:6px;margin-top:6px">
                                <button type="button" onclick="saveMileage(<?= (int)$v['id'] ?>)" style="background:#16a34a;border:none;color:#fff;padding:6px 10px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Save</button>
                                <button type="button" onclick="cancelMileageEdit(<?= (int)$v['id'] ?>)" style="background:none;border:1px solid rgba(255,255,255,0.15);color:#94a3b8;padding:6px 10px;border-radius:8px;font-size:11px;cursor:pointer;font-family:'Sora',sans-serif">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <div><div class="vehicle-spec-label">Registration</div><div class="vehicle-spec-value"><?= htmlspecialchars($v['registration'] ?: '—') ?></div></div>
                </div>
                <div style="display:flex;gap:8px;margin-top:18px">
                    <button onclick="editVehicle(<?= htmlspecialchars(json_encode($v)) ?>)" style="flex:1;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);color:#e2e8f0;padding:10px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Edit</button>
                    <?php if($i > 0): ?>
                    <button onclick="deleteVehicle(<?= $v['id'] ?>,'<?= htmlspecialchars($v['make'].' '.$v['model']) ?>')" style="background:rgba(220,38,38,0.15);border:1px solid rgba(220,38,38,0.25);color:#fca5a5;padding:10px 16px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Remove</button>
                    <?php endif; ?>
                </div>
                <?php
                    $renewals = [
                        ['COF',       'cof',  $v['cof_expiry']       ?? null],
                        ['Road tax',  'road', $v['road_tax_expiry']  ?? null],
                        ['Insurance', 'ins',  $v['insurance_expiry'] ?? null],
                    ];
                    $inpStyle = "width:100%;padding:7px 8px;border-radius:8px;border:1px solid rgba(255,255,255,0.2);background:rgba(0,0,0,0.3);color:#fff;font-family:'Sora',sans-serif;font-size:13px";
                ?>
                <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.06)">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em">Renewals</div>
                        <button type="button" onclick="startRenewalsEdit(<?= (int)$v['id'] ?>)" style="background:none;border:none;color:#60a5fa;font-size:11px;font-weight:600;cursor:pointer;padding:0;font-family:'Sora',sans-serif">✎ Edit</button>
                    </div>
                    <div id="renewalsView-<?= (int)$v['id'] ?>" style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($renewals as [$lbl, $key, $dt]): [$rl,$rc,$rb] = wm_renewal_badge($dt); ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px">
                            <span style="color:#cbd5e1"><?= $lbl ?></span>
                            <span style="font-weight:700;color:<?= $rc ?>;background:<?= $rb ?>;padding:2px 10px;border-radius:99px;font-size:11px"><?= htmlspecialchars($rl) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="renewalsEdit-<?= (int)$v['id'] ?>" style="display:none;margin-top:8px">
                        <?php foreach ($renewals as [$lbl, $key, $dt]): ?>
                        <label style="display:block;font-size:11px;color:#94a3b8;margin-bottom:8px"><?= $lbl ?> expiry
                            <input type="date" id="<?= $key ?>-<?= (int)$v['id'] ?>" value="<?= htmlspecialchars($dt ?? '') ?>" style="<?= $inpStyle ?>;margin-top:3px">
                        </label>
                        <?php endforeach; ?>
                        <div style="display:flex;gap:6px;margin-top:2px">
                            <button type="button" onclick="saveRenewals(<?= (int)$v['id'] ?>)" style="background:#16a34a;border:none;color:#fff;padding:7px 12px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Save</button>
                            <button type="button" onclick="cancelRenewalsEdit(<?= (int)$v['id'] ?>)" style="background:none;border:1px solid rgba(255,255,255,0.15);color:#94a3b8;padding:7px 12px;border-radius:8px;font-size:11px;cursor:pointer;font-family:'Sora',sans-serif">Cancel</button>
                        </div>
                    </div>
                </div>
                <a href="<?= $base ?>/pages/vehicle-passport.php?vehicle=<?= (int)$v['id'] ?>" target="_blank" rel="noopener" style="display:block;text-align:center;margin-top:12px;background:none;border:1px solid rgba(255,255,255,0.12);color:#94a3b8;padding:9px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;font-family:'Sora',sans-serif">📄 Service passport (PDF)</a>
            </div>
            <?php endforeach; ?>

            <?php if(count($all_vehicles) < 5): ?>
            <div class="card fade-in delay-3" style="display:flex;align-items:center;justify-content:center;min-height:280px">
                <button onclick="openVehicleModal()" style="width:100%;border:2px dashed #e2e8f0;background:none;padding:32px;border-radius:14px;font-size:14px;font-weight:600;color:#94a3b8;cursor:pointer;font-family:'Sora',sans-serif;transition:all 0.2s;display:flex;flex-direction:column;align-items:center;gap:8px" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#94a3b8'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Another Vehicle
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── HEALTH PANEL ─── (Keep from original) -->
    <div id="panel-health" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">Vehicle Health Report</h1>
            <p style="font-size:13px;color:#94a3b8;margin-top:2px">Your vehicle's service status, from your history with us — not a mechanical inspection.</p>
        </div>

        <div class="card fade-in delay-1" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff">
            <div class="card-body" style="padding:28px;display:flex;align-items:center;gap:28px;flex-wrap:wrap">
                <div class="health-ring" style="flex-shrink:0">
                    <svg width="140" height="140" viewBox="0 0 140 140">
                        <circle cx="70" cy="70" r="56" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="12"/>
                        <circle cx="70" cy="70" r="56" fill="none" stroke="<?= healthColor($overall_health) ?>" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="<?= round(2*pi()*56) ?>"
                                stroke-dashoffset="<?= round(2*pi()*56 * (1 - $overall_health/100)) ?>"
                                style="transform:rotate(-90deg);transform-origin:70px 70px;transition:stroke-dashoffset 1.2s"/>
                    </svg>
                    <div class="health-ring-inner">
                        <div class="health-pct" style="color:<?= healthColor($overall_health) ?>"><?= $overall_health ?>%</div>
                        <div class="health-sublabel" style="color:#94a3b8">Service health</div>
                    </div>
                </div>
                <div>
                    <?php [$hsCol, $hsBg] = statusTone($health_status['tone']); ?>
                    <div style="font-size:28px;font-weight:900;color:#fff;margin-bottom:6px">
                        <?= htmlspecialchars($health_status['label']) ?>
                    </div>
                    <div style="font-size:14px;color:#94a3b8;margin-bottom:16px">
                        <?= $health_status['tone'] === 'green'
                            ? 'You\'re on track — keep up with routine servicing to stay current.'
                            : 'Based on your last service date and mileage. Book a service to stay on schedule.' ?>
                    </div>
                    <button onclick="openBookModal()" class="dash-glass-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.4)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Book a Service
                    </button>
                </div>
            </div>
        </div>

        <?php if (empty($health_facts)): ?>
        <div class="card fade-in delay-2"><div class="card-body" style="padding:28px;text-align:center;color:#94a3b8">
            Add a vehicle and book a service to start tracking its service record here.
        </div></div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in delay-2">
            <?php foreach ($health_facts as $f): ?>
            <div class="card">
                <div class="card-body" style="padding:20px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                        <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.06);color:#cbd5e1">
                            <?= wm_icon($f['icon']) ?>
                        </div>
                        <div style="font-size:14px;font-weight:700;color:#94a3b8"><?= htmlspecialchars($f['label']) ?></div>
                    </div>
                    <div style="font-size:24px;font-weight:900;color:#f1f5f9;line-height:1.15"><?= htmlspecialchars($f['value']) ?></div>
                    <?php if(!empty($f['sub'])): ?><div style="font-size:12px;color:#94a3b8;margin-top:6px"><?= htmlspecialchars($f['sub']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── NOTIFICATIONS PANEL ─── (Keep from original) -->
    <div id="panel-notifications" class="page-body" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between" class="fade-in">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">Notifications</h1>
                <p style="font-size:13px;color:#94a3b8;margin-top:2px"><?= $unread_count ?> unread notification<?= $unread_count!==1?'s':'' ?></p>
            </div>
            <button onclick="markAllRead()" style="font-size:13px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:8px 16px;border-radius:10px;cursor:pointer;font-family:'Sora',sans-serif">Mark all read</button>
        </div>

        <div class="card fade-in delay-1">
            <div class="card-body" id="notificationsList">
                <?php foreach ($notifications as $n): ?>
                <div class="notif-item" data-notif-id="<?= md5($n['title'].$n['msg']) ?>" style="<?= !$n['read'] ? 'background:linear-gradient(90deg,rgba(220,38,38,0.03),transparent);' : 'opacity:0.7' ?>" onclick="markRead(this)">
                    <?= notifIcon($n['type']) ?>
                    <div class="notif-body">
                        <div class="notif-title">
                            <?= htmlspecialchars($n['title']) ?>
                            <?php if(!$n['read']): ?><span class="notif-unread"></span><?php endif; ?>
                        </div>
                        <div class="notif-msg"><?= htmlspecialchars($n['msg']) ?></div>
                    </div>
                    <div class="notif-time"><?= htmlspecialchars($n['time']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card fade-in delay-2">
            <div class="card-header"><div class="card-title"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Preferences</div></div>
            <div class="card-body">
                <p style="font-size:13px;color:#94a3b8;margin-bottom:16px">Choose how you'd like to be notified. All notifications are sent via WhatsApp.</p>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php foreach ([
                        ['WhatsApp booking confirmations', true],
                        ['Service reminders & due alerts', true],
                        ['Overdue booking notifications', true],
                        ['Promotional offers and discounts', false],
                    ] as [$label, $checked]): ?>
                    <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:12px 14px;border:1.5px solid rgba(255,255,255,0.12);border-radius:12px;transition:all 0.15s" onmouseover="this.style.borderColor='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0'">
                        <span style="font-size:14px;font-weight:500;color:#94a3b8"><?= htmlspecialchars($label) ?></span>
                        <input type="checkbox" <?= $checked ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:#dc2626;cursor:pointer">
                    </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:10px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    <div style="font-size:13px;color:#15803d"><strong>WhatsApp connected.</strong> You'll receive all notifications on <?= htmlspecialchars($_SESSION['user_phone'] ?? $userPhone ?? '+265 993 575 111') ?>.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── SETTINGS PANEL ─── (Keep from original) -->
    <div id="panel-settings" class="page-body" style="display:none">
        <div class="fade-in">
            <h1 style="font-size:22px;font-weight:900;color:#f1f5f9">Account Settings</h1>
            <p style="font-size:13px;color:#94a3b8;margin-top:2px">Manage your profile, security, and preferences</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="card fade-in delay-1">
                    <div class="card-header"><div class="card-title"><span style="color:#dc2626;margin-right:4px;display:inline-flex"><?= wm_icon('user', 17) ?></span>Profile Information</div></div>
                    <div class="card-body">
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;padding-bottom:22px;border-bottom:1px solid #f1f5f9">
                            <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;flex-shrink:0"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                            <div>
                                <div style="font-size:18px;font-weight:800;color:#f1f5f9"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                                <div style="font-size:13px;color:#94a3b8"><?= htmlspecialchars($user['email']) ?></div>
                                <div style="margin-top:8px;display:flex;gap:8px">
                                    <button style="font-size:12px;font-weight:600;background:none;border:1px solid rgba(255,255,255,0.12);padding:5px 12px;border-radius:8px;cursor:pointer;color:#94a3b8;font-family:'Sora',sans-serif">Change Photo</button>
                                    <span style="background:#f0fdf4;color:#15803d;font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;display:flex;align-items:center;gap:4px">
                                        ✓ Verified Member
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                            <?php foreach ([
                                ['First Name','text',$user['first_name'],'prof_first_name'],
                                ['Last Name','text',$user['last_name'],'prof_last_name'],
                                ['Email Address','email',$user['email'],'prof_email'],
                                ['Phone Number','tel',$user['phone'],'prof_phone'],
                                ['City','text',$user['city'],'prof_city'],
                                ['Member Type','text',$user['type'],'prof_type'],
                            ] as [$label,$type,$value,$fid]): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px"><?= $label ?></label>
                                <input type="<?= $type ?>" id="<?= $fid ?>" value="<?= htmlspecialchars($value) ?>" <?= $fid==='prof_email'||$fid==='prof_type'?'readonly style="width:100%;padding:11px 14px;border:1.5px solid rgba(255,255,255,0.12);border-radius:10px;font-size:14px;font-family:\'Sora\',sans-serif;color:#94a3b8;outline:none;background:rgba(255,255,255,0.04)"':'style="width:100%;padding:11px 14px;border:1.5px solid rgba(255,255,255,0.12);border-radius:10px;font-size:14px;font-family:\'Sora\',sans-serif;color:#f1f5f9;outline:none;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#dc2626\'" onblur="this.style.borderColor=\'#e2e8f0\'"' ?>>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:16px;display:flex;gap:10px">
                            <button onclick="saveProfile()" class="dash-glass-btn" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:11px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer">Save Profile</button>
                            <button style="background:none;border:1.5px solid rgba(255,255,255,0.12);padding:12px 24px;border-radius:11px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;color:#94a3b8">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="card fade-in delay-2">
                    <div class="card-header"><div class="card-title"><span style="color:#dc2626;margin-right:4px;display:inline-flex"><?= wm_icon('lock', 17) ?></span>Change Password</div></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr;gap:12px;max-width:400px">
                            <?php foreach ([
                                ['Current Password','pwd_current'],
                                ['New Password','pwd_new'],
                                ['Confirm New Password','pwd_confirm'],
                            ] as [$f,$fid]): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px"><?= $f ?></label>
                                <input type="password" id="<?= $fid ?>" placeholder="••••••••" style="width:100%;padding:11px 14px;border:1.5px solid rgba(255,255,255,0.12);border-radius:10px;font-size:14px;font-family:'Sora',sans-serif;color:#f1f5f9;outline:none" onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button onclick="updatePassword()" style="margin-top:14px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 22px;border-radius:11px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer">Update Password</button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:18px;padding:24px;color:#fff;position:relative;overflow:hidden" class="fade-in delay-1">
                    <div style="position:absolute;right:-20px;bottom:-20px;font-size:100px;opacity:0.06">🏆</div>
                    <div style="font-size:10px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:0.12em;margin-bottom:10px">Member Card</div>
                    <div style="font-size:20px;font-weight:900;color:#fff;margin-bottom:2px"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:16px"><?= htmlspecialchars($user['type']) ?></div>
                    <div style="font-size:11px;color:#475569;margin-bottom:4px">MEMBER SINCE</div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0"><?= date('d M Y', strtotime($user['member_since'])) ?></div>
                    <div style="margin-top:14px;display:flex;align-items:center;gap:6px">
                        <span class="live-dot"></span>
                        <span style="font-size:11px;color:#22c55e;font-weight:600">Active Member</span>
                    </div>
                </div>

                <div class="card fade-in delay-2">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#f1f5f9;margin-bottom:14px">Account Summary</div>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <?php foreach ([
                                ['Services Booked', count($service_history)],
                                ['Member Points',   '1,200 pts'],
                                ['Savings Earned',  'MWK 8,000'],
                            ] as [$k,$v]): ?>
                            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9">
                                <span style="font-size:13px;color:#94a3b8"><?= $k ?></span>
                                <span style="font-size:13px;font-weight:800;color:#f1f5f9"><?= $v ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card fade-in delay-3" style="border:1.5px solid #fecaca">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#dc2626;margin-bottom:8px">⚠ Danger Zone</div>
                        <p style="font-size:12px;color:#94a3b8;margin-bottom:12px">These actions are irreversible. Please be careful.</p>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <button onclick="deactivateAccount()" style="width:100%;background:none;border:1.5px solid #fecaca;color:#dc2626;padding:10px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif">Deactivate Account</button>
                            <a href="<?= $base ?>/pages/logout.php" style="display:block;text-align:center;background:none;border:1.5px solid rgba(255,255,255,0.12);color:#94a3b8;padding:10px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Mobile Bottom Navigation -->
<div class="mobile-bottom-nav">
    <button class="mobile-nav-item" onclick="showPanel('overview')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        <span>Home</span>
    </button>
    <button class="mobile-nav-item" onclick="showPanel('bookings')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span>Bookings</span>
    </button>
    <button class="mobile-nav-item" onclick="showPanel('reminders')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span>Reminders</span>
    </button>
    <button class="mobile-nav-item" onclick="showPanel('vehicle')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <span>Vehicle</span>
    </button>
    <button class="mobile-nav-item" onclick="showPanel('settings')">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <span>Settings</span>
    </button>
</div>

<!-- ═══ Book Service Modal ═══ -->
<div class="modal-overlay" id="bookModal">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div class="modal-title">Book a Service</div>
            <button onclick="closeBookModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-sub">Schedule an appointment with our expert team.</div>

        <div class="modal-field">
            <label>Service Required *</label>
            <select id="bookingService">
                <option>General Maintenance</option>
                <option>Engine Repair</option>
                <option>Auto Detailing</option>
                <option>Brake Services</option>
                <option>AC Repair</option>
                <option>Auto Body Work</option>
                <option>Polishing</option>
                <option>Battery Services</option>
                <option>Vehicle Wrapping</option>
                <option>Parts Supply</option>
                <option>Other</option>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Preferred Date *</label>
                <input type="date" id="bookingDate" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-field">
                <label>Preferred Time *</label>
                <select id="bookingTime">
                    <option>08:00 AM</option><option>09:00 AM</option><option>10:00 AM</option>
                    <option>11:00 AM</option><option>12:00 PM</option><option>01:00 PM</option>
                    <option>02:00 PM</option><option>03:00 PM</option><option>04:00 PM</option>
                </select>
            </div>
        </div>
        <div class="modal-field">
            <label>Additional Notes</label>
            <textarea id="bookingNotes" rows="3" placeholder="Describe any specific issues or concerns…"></textarea>
        </div>

        <?php if(count($all_vehicles) > 1): ?>
        <!-- Vehicle selector (shown when user has 2+ vehicles) -->
        <div class="modal-field">
            <label>Which Vehicle? *</label>
            <select id="bookingVehicleId">
                <?php foreach($all_vehicles as $v): ?>
                <option value="<?= (int)$v['id'] ?>"
                        data-make="<?= htmlspecialchars($v['make']) ?>"
                        data-model="<?= htmlspecialchars($v['model']) ?>"
                        data-year="<?= htmlspecialchars($v['year']) ?>"
                        data-registration="<?= htmlspecialchars($v['registration']) ?>">
                    <?= htmlspecialchars($v['year'].' '.$v['make'].' '.$v['model'] . ($v['registration'] ? ' ('.$v['registration'].')' : '')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif(count($all_vehicles) === 1): ?>
        <!-- Single vehicle — hidden field -->
        <input type="hidden" id="bookingVehicleId" value="<?= (int)$all_vehicles[0]['id'] ?>">
        <?php endif; ?>

        <?php if($offer_eligible): ?>
        <div style="background:linear-gradient(135deg,#fef3c7 0%,#fef9e7 100%);border:2px solid #fbbf24;border-radius:12px;padding:12px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px">
            <input type="checkbox" id="dashClaimOffer" style="width:20px;height:20px;accent-color:#dc2626;cursor:pointer;flex-shrink:0">
            <label for="dashClaimOffer" style="cursor:pointer;flex:1;font-size:13px;color:#78350f;line-height:1.4">
                <strong style="font-weight:800;color:#92400e">🎉 Apply my 10% first-time discount</strong><br>
                <span style="font-size:11px;color:#a16207">One-time offer — can't be combined with other discounts.</span>
            </label>
        </div>
        <?php endif; ?>

        <div style="background:rgba(255,255,255,0.04);border-radius:12px;padding:12px 14px;font-size:13px;color:#94a3b8;margin-bottom:4px" id="bookingVehicleDisplay">
            <strong style="color:#f1f5f9">Vehicle:</strong> <?= count($all_vehicles) === 0 ? '<em style="color:#94a3b8">Not set — add a vehicle in My Vehicles</em>' : htmlspecialchars($all_vehicles[0]['year'].' '.$all_vehicles[0]['make'].' '.$all_vehicles[0]['model'].($all_vehicles[0]['registration'] ? ' ('.$all_vehicles[0]['registration'].')' : '')) ?>
        </div>

        <div class="modal-actions">
            <button class="btn-modal-ghost" onclick="closeBookModal()">Cancel</button>
            <button class="btn-modal-primary" onclick="submitBooking()">
                Confirm Booking
            </button>
        </div>
    </div>
</div>

<!-- Vehicle Add/Edit Modal -->
<div class="modal-overlay" id="vehicleModal">
    <div class="modal-box">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <div class="modal-title" id="vehicleModalTitle">Add Vehicle</div>
            <button onclick="closeVehicleModal()" style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-sub">Enter your vehicle details below.</div>

        <input type="hidden" id="vehEditId" value="0">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Make *</label>
                <input type="text" id="vehMake" placeholder="e.g. Toyota">
            </div>
            <div class="modal-field">
                <label>Model *</label>
                <input type="text" id="vehModel" placeholder="e.g. Hilux">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Year</label>
                <input type="text" id="vehYear" placeholder="e.g. 2020">
            </div>
            <div class="modal-field">
                <label>Registration</label>
                <input type="text" id="vehReg" placeholder="e.g. MJ 1234">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Color</label>
                <input type="text" id="vehColor" placeholder="e.g. White">
            </div>
            <div class="modal-field">
                <label>Mileage (km)</label>
                <input type="number" id="vehMileage" placeholder="e.g. 45000">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="modal-field">
                <label>Engine</label>
                <input type="text" id="vehEngine" placeholder="e.g. 2.4L Diesel">
            </div>
            <div class="modal-field">
                <label>Transmission</label>
                <select id="vehTransmission">
                    <option value="">Select...</option>
                    <option value="Automatic">Automatic</option>
                    <option value="Manual">Manual</option>
                    <option value="CVT">CVT</option>
                </select>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn-modal-ghost" onclick="closeVehicleModal()">Cancel</button>
            <button class="btn-modal-primary" onclick="saveVehicle()">Save Vehicle</button>
        </div>
    </div>
</div>

<script>
// Car image fallback: brand badge → silhouette (clean, no watermark)
function wmCarImgFallback(img) {
    // Brand logo failed to load → show the clean silhouette icon
    img.style.display = 'none';
    var sil = img.parentNode.querySelector('.vehicle-photo-silhouette');
    if (sil) sil.style.display = 'flex';
}

// Toast Manager
class ToastManager {
    constructor() {
        this.createContainer();
    }
    
    createContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }
        this.container = container;
    }
    
    show(message, type = 'success', duration = 4000) {
        const toast = document.createElement('div');
        const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
        const colors = { success: '#16a34a', error: '#dc2626', warning: '#f59e0b', info: '#3b82f6' };
        
        toast.className = 'toast';
        toast.style.borderLeft = `4px solid ${colors[type]}`;
        toast.innerHTML = `
            <div style="width: 24px; height: 24px; border-radius: 50%; background: ${colors[type]}20; color: ${colors[type]}; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                ${icons[type]}
            </div>
            <div style="flex: 1; color: #1f2937; font-size: 14px; font-weight: 500;">${message}</div>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 18px;">×</button>
        `;
        
        this.container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
}

const toast = new ToastManager();
window.showToast = (msg, type) => toast.show(msg, type);

// Sidebar Functions
let sidebarCollapsed = false;

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const main = document.getElementById('main');
    sidebarCollapsed = !sidebarCollapsed;
    sb.classList.toggle('collapsed', sidebarCollapsed);
    main.classList.toggle('expanded', sidebarCollapsed);
}

function openSidebar() {
    document.getElementById('sidebar').classList.add('mobile-open');
    document.getElementById('sidebarOverlay').classList.remove('hidden');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('mobile-open');
    document.getElementById('sidebarOverlay').classList.add('hidden');
}

// Header User Dropdown
function toggleHeaderDropdown() {
    document.getElementById('headerUserWrap').classList.toggle('open');
}
function closeHeaderDropdown() {
    document.getElementById('headerUserWrap').classList.remove('open');
}
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('headerUserWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeHeaderDropdown();
});

// Panel Navigation
function showPanel(name) {
    document.querySelectorAll('[id^="panel-"]').forEach(p => p.style.display = 'none');
    const panel = document.getElementById('panel-' + name);
    if (panel) panel.style.display = 'flex';

    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const target = Array.from(document.querySelectorAll('.nav-item'))
        .find(el => el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + name + "'"));
    if (target) target.classList.add('active');

    document.querySelectorAll('.mobile-nav-item').forEach(el => el.classList.remove('active'));
    const mobileTarget = Array.from(document.querySelectorAll('.mobile-nav-item'))
        .find(el => el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + name + "'"));
    if (mobileTarget) mobileTarget.classList.add('active');

    if (window.innerWidth < 1024) closeSidebar();
    if(window.feather)feather.replace();
    initBars();
    animateCounters();
}

// Animate counters
function animateCounters() {
    document.querySelectorAll('.counter-value[data-target]').forEach(el => {
        const target = parseInt(el.dataset.target);
        if (target > 0 && !el.dataset.animated) {
            el.dataset.animated = 'true';
            let current = 0;
            const increment = target / 30;
            const updateCounter = () => {
                current += increment;
                if (current < target) {
                    el.innerText = Math.floor(current);
                    requestAnimationFrame(updateCounter);
                } else {
                    el.innerText = target;
                }
            };
            updateCounter();
        }
    });
}

// Initialize bars
function initBars() {
    document.querySelectorAll('.health-bar-fill[data-target]').forEach(el => {
        setTimeout(() => { el.style.width = el.dataset.target + '%'; }, 100);
    });
    document.querySelectorAll('.mileage-fill[data-target]').forEach(el => {
        setTimeout(() => { el.style.width = el.dataset.target + '%'; }, 100);
    });
}

// Booking Modal
function openBookModal() { document.getElementById('bookModal').classList.add('active'); }

// Vehicle Switcher on Overview — swap between registered cars with real stats
function switchOverviewVehicle(idx) {
    // Update tab active state
    document.querySelectorAll('.vswitch-tab').forEach(function(tab) {
        var isActive = parseInt(tab.dataset.vidx) === idx;
        tab.style.background = isActive ? 'var(--red)' : 'transparent';
        tab.style.color = isActive ? '#fff' : '#94a3b8';
        tab.classList.toggle('active', isActive);
    });
    // Show matching vehicle info pane, hide others
    document.querySelectorAll('.vswitch-pane').forEach(function(pane) {
        pane.style.display = parseInt(pane.dataset.vidx) === idx ? 'block' : 'none';
    });
    // Also swap the health pane to match
    document.querySelectorAll('.health-pane').forEach(function(pane) {
        pane.style.display = parseInt(pane.dataset.vidx) === idx ? 'block' : 'none';
    });
    // Re-render feather icons in newly visible health pane
    if (typeof feather !== 'undefined') if(window.feather)feather.replace();
}

// Open booking modal pre-selected for a specific vehicle
function bookForVehicle(vehicleId) {
    var picker = document.getElementById('bookingVehicleId');
    if (picker && picker.tagName === 'SELECT') {
        // Find and select the matching option
        for (var i = 0; i < picker.options.length; i++) {
            if (parseInt(picker.options[i].value) === vehicleId) {
                picker.selectedIndex = i;
                // Trigger change event to update the vehicle display
                picker.dispatchEvent(new Event('change'));
                break;
            }
        }
    }
    openBookModal();
}
function closeBookModal() { document.getElementById('bookModal').classList.remove('active'); }
document.getElementById('bookModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookModal();
});

function submitBooking() {
    const service = document.getElementById('bookingService').value;
    const date    = document.getElementById('bookingDate').value;
    const timeRaw = document.getElementById('bookingTime').value;
    const notes   = document.getElementById('bookingNotes').value;

    if (!date) { toast.show('Please select a date', 'warning'); return; }

    // ── Get selected vehicle (from dropdown if multiple, hidden input if one) ──
    const vehPicker = document.getElementById('bookingVehicleId');
    let make = '', model = '', year = '', registration = '';

    if (!vehPicker) {
        toast.show('Please add a vehicle first in My Vehicles', 'warning');
        return;
    }

    if (vehPicker.tagName === 'SELECT') {
        const opt = vehPicker.options[vehPicker.selectedIndex];
        make         = opt.dataset.make || '';
        model        = opt.dataset.model || '';
        year         = opt.dataset.year || '';
        registration = opt.dataset.registration || '';
    } else {
        // Hidden input — user has exactly one vehicle, fetch from PHP
        make         = <?= json_encode(count($all_vehicles) ? $all_vehicles[0]['make'] : '') ?>;
        model        = <?= json_encode(count($all_vehicles) ? $all_vehicles[0]['model'] : '') ?>;
        year         = <?= json_encode(count($all_vehicles) ? $all_vehicles[0]['year'] : '') ?>;
        registration = <?= json_encode(count($all_vehicles) ? $all_vehicles[0]['registration'] : '') ?>;
    }

    // Convert "08:00 AM" → "08:00"
    let time = timeRaw;
    const match = timeRaw.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (match) {
        let h = parseInt(match[1]);
        if (match[3].toUpperCase() === 'PM' && h < 12) h += 12;
        if (match[3].toUpperCase() === 'AM' && h === 12) h = 0;
        time = String(h).padStart(2,'0') + ':' + match[2];
    }

    // Build form data using session user + selected vehicle
    const formData = new FormData();
    formData.append('name',         <?= json_encode(($user['first_name'].' '.$user['last_name'])) ?>);
    formData.append('phone',        <?= json_encode($user['phone']) ?>);
    formData.append('email',        <?= json_encode($user['email']) ?>);
    formData.append('make',         make || 'TBD');
    formData.append('model',        model || 'TBD');
    formData.append('year',         year || new Date().getFullYear());
    formData.append('registration', registration);
    formData.append('services',     service);
    formData.append('date',         date);
    formData.append('time',         time);
    formData.append('notes',        notes);

    // First-time offer claim
    var claimBox = document.getElementById('dashClaimOffer');
    if (claimBox && claimBox.checked) {
        formData.append('claim_offer', '1');
    }

    // Disable button while submitting
    const btn = document.querySelector('#bookModal .btn-modal-primary');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Submitting…';

    fetch('<?= $base ?>/pages/process-booking.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (data.success) {
                closeBookModal();
                toast.show('Booking confirmed! Ref: ' + (data.booking_id ? '#' + data.booking_id : ''), 'success', 6000);

                // Send WhatsApp notification to owner
                var d = data.data || {};
                var waMsg = '🔧 *NEW BOOKING — Wiyule Motors*\n\n'
                    + '👤 *Customer:* ' + (d.name||'') + '\n'
                    + '📞 *Phone:* ' + (d.phone||'') + '\n'
                    + (d.email ? '📧 *Email:* ' + d.email + '\n' : '')
                    + '\n🚗 *Vehicle:* ' + (d.vehicle ? d.vehicle.year+' '+d.vehicle.make+' '+d.vehicle.model : '') + '\n'
                    + (d.vehicle && d.vehicle.registration ? '🔢 *Reg:* ' + d.vehicle.registration + '\n' : '')
                    + '\n🛠 *Services:* ' + (d.services ? d.services.join(', ') : '') + '\n'
                    + '📅 *Date:* ' + (d.date||'') + '\n'
                    + '🕐 *Time:* ' + (d.time||'') + '\n'
                    + (d.notes ? '\n📝 *Notes:* ' + d.notes + '\n' : '')
                    + (d.first_time_discount == 1
                        ? '\n━━━━━━━━━━━━━━━━━━━\n🎉 *FIRST-TIME CUSTOMER*\n💰 *APPLY 10% DISCOUNT*\n━━━━━━━━━━━━━━━━━━━\n'
                        : (d.is_first_time
                            ? '\n⭐ *First-time customer* (10% offer available but not claimed)\n'
                            : ''))
                    + '\n✅ Booked via Dashboard';
                window.open('https://wa.me/265993575111?text=' + encodeURIComponent(waMsg), '_blank');

                document.getElementById('bookingDate').value = '';
                document.getElementById('bookingNotes').value = '';
                // Reload to show the new booking in all panels
                setTimeout(() => location.reload(), 1500);
            } else {
                toast.show(data.message || 'Booking failed. Please try again.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = origText;
            toast.show('Connection error. Please call +265 993 575 111.', 'error');
        });
}

// Onboarding Functions
function startOnboarding() {
    showPanel('vehicle');
    toast.show('Let\'s start by adding your vehicle details', 'info');
}

function completeOnboardingStep(step) {
    const stepEl = document.querySelector(`.step-item[data-step="${step}"]`);
    if (stepEl) {
        stepEl.classList.add('completed');
        const iconDiv = stepEl.querySelector('.w-10');
        if (iconDiv) {
            iconDiv.innerHTML = '✓';
            iconDiv.classList.add('bg-green-100', 'text-green-600');
            iconDiv.classList.remove('bg-red-100', 'text-red-600');
        }
        const textEl = stepEl.querySelector('.font-semibold');
        if (textEl) textEl.classList.remove('text-gray-400');
        const subEl = stepEl.querySelector('.text-gray-400');
        if (subEl) subEl.classList.remove('text-gray-400');
    }
    
    if (step === 1) showPanel('vehicle');
    else if (step === 2) showPanel('notifications');
    else if (step === 3) openBookModal();
}

function dismissOnboarding() {
    document.getElementById('onboardingWizard').style.display = 'none';
    fetch('<?= $base ?>/api/dismiss-onboarding.php', { method: 'POST' }).catch(e => console.log(e));
}

// Search Function
function handleSearch(query) {
    const searchTerm = query.toLowerCase();
    const panels = ['overview', 'bookings', 'history', 'reminders'];
    
    panels.forEach(panel => {
        const panelEl = document.getElementById(`panel-${panel}`);
        if (!panelEl) return;
        
        const cards = panelEl.querySelectorAll('.card');
        cards.forEach(card => {
            const text = card.innerText.toLowerCase();
            if (searchTerm === '' || text.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// Notification Functions
function markRead(element) {
    const notifDiv = element.closest('.notif-item');
    if (notifDiv) {
        notifDiv.style.opacity = '0.5';
        const unreadDot = notifDiv.querySelector('.notif-unread');
        if (unreadDot) unreadDot.style.display = 'none';
        toast.show('Marked as read', 'info');
    }
}

function markAllRead() {
    document.querySelectorAll('.notif-item').forEach(item => {
        item.style.opacity = '0.5';
        const unreadDot = item.querySelector('.notif-unread');
        if (unreadDot) unreadDot.style.display = 'none';
    });
    toast.show('All notifications marked as read', 'success');
}

// Profile Functions
function saveProfile() {
    var fd = new FormData();
    fd.append('action', 'profile');
    fd.append('first_name', document.getElementById('prof_first_name').value.trim());
    fd.append('last_name', document.getElementById('prof_last_name').value.trim());
    fd.append('phone', document.getElementById('prof_phone').value.trim());
    fd.append('city', document.getElementById('prof_city').value.trim());
    if (!fd.get('first_name') || !fd.get('last_name')) { toast.show('Name is required', 'error'); return; }
    fetch('<?= $base ?>/api/update-profile.php', {method:'POST', body:fd})
        .then(function(r){return r.json()})
        .then(function(d){
            toast.show(d.message, d.success ? 'success' : 'error');
            if (d.success) setTimeout(function(){location.reload()}, 1200);
        })
        .catch(function(){toast.show('Connection error', 'error')});
}

function updatePassword() {
    var fd = new FormData();
    fd.append('action', 'password');
    fd.append('current_password', document.getElementById('pwd_current').value);
    fd.append('new_password', document.getElementById('pwd_new').value);
    fd.append('confirm_password', document.getElementById('pwd_confirm').value);
    if (!fd.get('current_password') || !fd.get('new_password') || !fd.get('confirm_password')) { toast.show('All password fields are required', 'error'); return; }
    if (fd.get('new_password') !== fd.get('confirm_password')) { toast.show('New passwords do not match', 'error'); return; }
    fetch('<?= $base ?>/api/update-profile.php', {method:'POST', body:fd})
        .then(function(r){return r.json()})
        .then(function(d){
            toast.show(d.message, d.success ? 'success' : 'error');
            if (d.success) { document.getElementById('pwd_current').value=''; document.getElementById('pwd_new').value=''; document.getElementById('pwd_confirm').value=''; }
        })
        .catch(function(){toast.show('Connection error', 'error')});
}

function cancelBooking(bookingId, serviceName) {
    if (!confirm('Cancel your booking for ' + serviceName + '? This cannot be undone.')) return;
    var fd = new FormData();
    fd.append('booking_id', bookingId);
    fd.append('action', 'cancel');
    fetch('<?= $base ?>/api/cancel-booking.php', {method:'POST', body:fd})
        .then(function(r){return r.json()})
        .then(function(d){
            toast.show(d.message, d.success ? 'success' : 'error');
            if (d.success) setTimeout(function(){location.reload()}, 1000);
        })
        .catch(function(){toast.show('Connection error', 'error')});
}

function deactivateAccount() {
    if (confirm('Are you sure you want to deactivate your account? This action cannot be undone.')) {
        toast.show('Account deactivated', 'warning');
        setTimeout(() => {
            window.location.href = '<?= $base ?>/pages/logout.php';
        }, 2000);
    }
}

// Vehicle Update
function openVehicleModal(data) {
    document.getElementById('vehicleModalTitle').textContent = data ? 'Edit Vehicle' : 'Add Vehicle';
    document.getElementById('vehEditId').value = data ? data.id : 0;
    document.getElementById('vehMake').value = data ? data.make : '';
    document.getElementById('vehModel').value = data ? data.model : '';
    document.getElementById('vehYear').value = data ? data.year : '';
    document.getElementById('vehReg').value = data ? data.registration : '';
    document.getElementById('vehColor').value = data ? data.color : '';
    document.getElementById('vehMileage').value = data ? data.mileage : '';
    document.getElementById('vehEngine').value = data ? data.engine : '';
    document.getElementById('vehTransmission').value = data ? data.transmission : '';
    document.getElementById('vehicleModal').classList.add('active');
}
function closeVehicleModal() {
    document.getElementById('vehicleModal').classList.remove('active');
}
function editVehicle(data) {
    openVehicleModal(data);
}

// ── Inline odometer update ─────────────────────────────
function startMileageEdit(id)  { document.getElementById('mileageEdit-'+id).style.display = 'block'; }
function cancelMileageEdit(id) { document.getElementById('mileageEdit-'+id).style.display = 'none'; }
async function saveMileage(id) {
    var input = document.getElementById('mileageInput-'+id);
    var val = parseInt(input.value, 10);
    if (isNaN(val) || val < 0 || val > 9999999) { alert('Please enter a valid mileage (0–9,999,999 km).'); return; }
    var fd = new FormData();
    fd.append('vehicle_id', id);
    fd.append('mileage', val);
    try {
        var r = await fetch('<?= $base ?>/pages/update-mileage.php', { method:'POST', body:fd, credentials:'same-origin' });
        var d = await r.json();
        if (d && d.success) {
            document.getElementById('mileageVal-'+id).textContent = Number(d.mileage).toLocaleString() + ' km';
            cancelMileageEdit(id);
        } else {
            alert((d && d.message) || 'Could not update mileage.');
        }
    } catch (e) { alert('Network problem — please try again.'); }
}

// ── Renewal dates (COF / road tax / insurance) ─────────
function startRenewalsEdit(id) {
    document.getElementById('renewalsView-'+id).style.display = 'none';
    document.getElementById('renewalsEdit-'+id).style.display = 'block';
}
function cancelRenewalsEdit(id) {
    document.getElementById('renewalsEdit-'+id).style.display = 'none';
    document.getElementById('renewalsView-'+id).style.display = 'flex';
}
async function saveRenewals(id) {
    var fd = new FormData();
    fd.append('vehicle_id', id);
    fd.append('cof_expiry', document.getElementById('cof-'+id).value);
    fd.append('road_tax_expiry', document.getElementById('road-'+id).value);
    fd.append('insurance_expiry', document.getElementById('ins-'+id).value);
    try {
        var r = await fetch('<?= $base ?>/pages/update-renewals.php', { method:'POST', body:fd, credentials:'same-origin' });
        var d = await r.json();
        if (d && d.success) { location.reload(); }
        else { alert((d && d.message) || 'Could not save renewal dates.'); }
    } catch (e) { alert('Network problem — please try again.'); }
}
function deleteVehicle(id, name) {
    if (!confirm('Remove ' + name + '? This cannot be undone.')) return;
    var fd = new FormData();
    fd.append('vehicle_id', id);
    fd.append('action', 'delete');
    fd.append('make', 'x');
    fd.append('model', 'x');
    fetch('<?= $base ?>/api/update-vehicle.php', { method:'POST', body:fd })
        .then(function(r){return r.json()})
        .then(function(data){
            if (data.success) {
                toast.show('Vehicle removed', 'success');
                setTimeout(function(){location.reload()}, 1000);
            } else {
                toast.show(data.message || 'Could not remove', 'error');
            }
        })
        .catch(function(){toast.show('Connection error','error')});
}
function saveVehicle() {
    var make = document.getElementById('vehMake').value.trim();
    var model = document.getElementById('vehModel').value.trim();
    if (!make || !model) { toast.show('Make and model are required', 'error'); return; }

    var fd = new FormData();
    fd.append('vehicle_id', document.getElementById('vehEditId').value);
    fd.append('make', make);
    fd.append('model', model);
    fd.append('year', document.getElementById('vehYear').value.trim());
    fd.append('registration', document.getElementById('vehReg').value.trim());
    fd.append('color', document.getElementById('vehColor').value.trim());
    fd.append('mileage', document.getElementById('vehMileage').value || 0);
    fd.append('engine', document.getElementById('vehEngine').value.trim());
    fd.append('transmission', document.getElementById('vehTransmission').value);

    var btn = document.querySelector('#vehicleModal .btn-modal-primary');
    btn.textContent = 'Saving...'; btn.disabled = true;

    fetch('<?= $base ?>/api/update-vehicle.php', { method:'POST', body:fd })
        .then(function(r){return r.json()})
        .then(function(data){
            btn.textContent = 'Save Vehicle'; btn.disabled = false;
            if (data.success) {
                closeVehicleModal();
                toast.show(data.message || 'Vehicle saved!', 'success');
                setTimeout(function(){location.reload()}, 1000);
            } else {
                toast.show(data.message || 'Failed to save', 'error');
            }
        })
        .catch(function(){
            btn.textContent = 'Save Vehicle'; btn.disabled = false;
            toast.show('Connection error', 'error');
        });
}
// Keep legacy function working
function updateVehicle(event) {
    event.preventDefault();
    var form = document.getElementById('vehicleUpdateForm');
    if (!form) return;
    var fd = new FormData(form);
    fetch('<?= $base ?>/api/update-vehicle.php', { method:'POST', body:fd })
        .then(function(r){return r.json()})
        .then(function(data){
            if (data.success) { toast.show('Vehicle updated!','success'); setTimeout(function(){location.reload()},1200); }
            else { toast.show(data.message||'Failed','error'); }
        })
        .catch(function(){toast.show('Connection error','error')});
}

// Auto-refresh (simulated)
let refreshInterval;
function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => {
        // Simulate checking for updates
        console.log('Checking for updates...');
    }, 30000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if(window.feather)feather.replace();
    
    const startPanel = <?= json_encode($initial_panel) ?>;
    showPanel(startPanel);
    
    animateCounters();
    startAutoRefresh();

    // Live-update vehicle display when user picks a different vehicle in booking modal
    const vehPicker = document.getElementById('bookingVehicleId');
    if (vehPicker && vehPicker.tagName === 'SELECT') {
        vehPicker.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const display = document.getElementById('bookingVehicleDisplay');
            if (display && opt) {
                const y = opt.dataset.year || '';
                const mk = opt.dataset.make || '';
                const md = opt.dataset.model || '';
                const reg = opt.dataset.registration || '';
                display.innerHTML = '<strong style="color:#f1f5f9">Vehicle:</strong> ' +
                    (y + ' ' + mk + ' ' + md + (reg ? ' (' + reg + ')' : '')).trim();
            }
        });
    }
});
</script>
<?php if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); } ?>
</body>
</html>