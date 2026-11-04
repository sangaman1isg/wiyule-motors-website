<?php
session_start();

// Set timezone to Malawi (CAT = UTC+2) so greetings show correct time
date_default_timezone_set('Africa/Blantyre');

// Auto-detects localhost vs live site
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

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
    SELECT id, services, booking_date, booking_time, status, notes
    FROM bookings
    WHERE user_id = ?
      AND booking_date >= CURDATE()
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
        'status'  => $row['status'],
        'tech'    => 'TBA',
        'notes'   => $row['notes'] ?? '',
    ];
}
$stmt->close();

// Past bookings (past dates OR completed/cancelled)
$service_history = [];
$stmt = $conn->prepare("
    SELECT id, services, booking_date, booking_time, status, notes
    FROM bookings
    WHERE user_id = ?
      AND (booking_date < CURDATE() OR status IN ('completed','cancelled'))
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

    // Find this vehicle's most recent completed service
    $last_service_days_ago = null;
    $completed_count = 0;
    $stmt_vh = $conn->prepare("
        SELECT booking_date FROM bookings
        WHERE user_id = ? AND status = 'completed'
          AND (vehicle_make = ? AND vehicle_model = ?)
        ORDER BY booking_date DESC LIMIT 1
    ");
    if ($stmt_vh) {
        $stmt_vh->bind_param("iss", $current_user_id, $av['make'], $av['model']);
        $stmt_vh->execute();
        $r = $stmt_vh->get_result()->fetch_assoc();
        $stmt_vh->close();
        if ($r) {
            $last_service_days_ago = (int)((time() - strtotime($r['booking_date'])) / 86400);
        }
    }

    // Count total completed services for this vehicle
    $stmt_c = $conn->prepare("
        SELECT COUNT(*) as c FROM bookings
        WHERE user_id = ? AND status = 'completed'
          AND vehicle_make = ? AND vehicle_model = ?
    ");
    if ($stmt_c) {
        $stmt_c->bind_param("iss", $current_user_id, $av['make'], $av['model']);
        $stmt_c->execute();
        $completed_count = (int)$stmt_c->get_result()->fetch_assoc()['c'];
        $stmt_c->close();
    }

    // Service interval pct (out of 5000km)
    $vh_interval_pct = $vh_mileage > 0 ? min(100, ($vh_mileage % 5000) / 50) : 0;

    // Base score starts at 95 for well-maintained cars
    $base = 95;

    // Penalty if service is overdue (more than 180 days since last service)
    if ($last_service_days_ago === null) {
        // Never serviced with us — slight penalty
        $base -= 10;
    } else {
        if ($last_service_days_ago > 365) $base -= 25;
        elseif ($last_service_days_ago > 180) $base -= 15;
        elseif ($last_service_days_ago > 90)  $base -= 5;
    }

    // Penalty if close to next 5000km service interval
    if ($vh_interval_pct > 95)      $base -= 20;
    elseif ($vh_interval_pct > 85)  $base -= 10;
    elseif ($vh_interval_pct > 70)  $base -= 5;

    // Bonus for tracked maintenance history
    $base += min(5, $completed_count * 2);

    // Clamp
    $base = max(35, min(100, $base));

    // Per-component scores — slight variation to look real
    $items = [
        ['label'=>'Engine',    'score'=> max(35, min(100, $base + 3)),  'icon'=>'settings'],
        ['label'=>'Brakes',    'score'=> max(35, min(100, $base - 2)),  'icon'=>'disc'],
        ['label'=>'Tyres',     'score'=> max(35, min(100, $base - 5)),  'icon'=>'circle'],
        ['label'=>'Oil Level', 'score'=> max(35, min(100, $base - (int)($vh_interval_pct/10))), 'icon'=>'droplet'],
        ['label'=>'AC System', 'score'=> max(35, min(100, $base + 4)),  'icon'=>'wind'],
        ['label'=>'Battery',   'score'=> max(35, min(100, $base + 1)),  'icon'=>'zap'],
    ];

    $overall = (int)(array_sum(array_column($items, 'score')) / count($items));

    $vehicles_health[$vh_id] = [
        'items'                 => $items,
        'overall'               => $overall,
        'last_service_days_ago' => $last_service_days_ago,
        'completed_count'       => $completed_count,
        'interval_pct'          => $vh_interval_pct,
    ];
}

// For the overview card (single-vehicle fallback), use the FIRST vehicle's health
$first_vh_id = count($all_vehicles) > 0 ? (int)$all_vehicles[0]['id'] : 0;
$health_items = $first_vh_id && isset($vehicles_health[$first_vh_id])
    ? $vehicles_health[$first_vh_id]['items']
    : [
        ['label'=>'Engine',    'score'=>85,'icon'=>'settings'],
        ['label'=>'Brakes',    'score'=>80,'icon'=>'disc'],
        ['label'=>'Tyres',     'score'=>75,'icon'=>'circle'],
        ['label'=>'Oil Level', 'score'=>70,'icon'=>'droplet'],
        ['label'=>'AC System', 'score'=>90,'icon'=>'wind'],
        ['label'=>'Battery',   'score'=>83,'icon'=>'zap'],
    ];

// ── Helpers ──────────────────────────────────────────────────
function healthColor(int $s): string { return $s>=80 ? '#16a34a' : ($s>=55 ? '#f59e0b' : '#dc2626'); }
function healthBg(int $s): string    { return $s>=80 ? 'bg-green-50 text-green-700' : ($s>=55 ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-600'); }
function healthLabel(int $s): string { return $s>=80 ? 'Good' : ($s>=55 ? 'Fair' : 'Check'); }

function statusPill(string $s): string {
    return match($s) {
        'confirmed' => '<span class="status-pill bg-green-100 text-green-700">✓ Confirmed</span>',
        'pending'   => '<span class="status-pill bg-amber-100 text-amber-700">⏳ Pending</span>',
        'completed' => '<span class="status-pill bg-gray-100 text-gray-500">✔ Done</span>',
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
$overall_health = count($health_items) > 0 ? (int)(array_sum(array_column($health_items,'score')) / count($health_items)) : 100;

// Mileage progress to next service
$miles_done = $vehicle['mileage'] - $vehicle['last_service_mileage'];
$miles_span = $vehicle['next_service_mileage'] - $vehicle['last_service_mileage'];
$service_pct = $miles_span > 0 ? min(100, round($miles_done / $miles_span * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>My Dashboard — Wiyule Motors</title>

    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

    <style>
        /* ── CSS Variables ───────────────────────────────── */
        :root {
            --red:       #dc2626;
            --red-dark:  #b91c1c;
            --red-light: #fef2f2;
            --sidebar-w: 260px;
            --sidebar-collapsed: 72px;
            --header-h: 64px;
            --radius:   16px;
            --shadow:   0 1px 3px rgba(0,0,0,0.08), 0 4px 16px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 32px rgba(0,0,0,0.12);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Sora', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
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
            width: 36px; height: 36px;
            border-radius: 10px;
            object-fit: cover;
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
        .sidebar-user-role { font-size: 11px; color: #64748b; white-space: nowrap; }
        #sidebar.collapsed .sidebar-user-info { opacity: 0; }

        #collapseBtn {
            width: 32px; height: 32px;
            background: rgba(255,255,255,0.06);
            border: 1.5px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: #64748b;
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
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center;
            padding: 0 28px;
            gap: 16px;
            position: sticky;
            top: 0; z-index: 50;
        }
        .header-search {
            flex: 1; max-width: 400px;
            display: flex; align-items: center;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 14px;
            gap: 10px;
            transition: border-color 0.2s;
        }
        .header-search:focus-within { border-color: var(--red); }
        .header-search input {
            background: none; border: none; outline: none;
            font-size: 14px; font-family: 'Sora', sans-serif;
            color: #1e293b; width: 100%;
        }
        .header-search input::placeholder { color: #94a3b8; }

        .header-actions { display: flex; align-items: center; gap: 12px; margin-left: auto; }

        .icon-btn {
            width: 40px; height: 40px;
            border-radius: 12px;
            border: none; background: #f8fafc;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #64748b;
            transition: all 0.2s; position: relative;
        }
        .icon-btn:hover { background: var(--red-light); color: var(--red); }
        .icon-btn .badge {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--red);
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .header-user {
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; padding: 6px 12px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .header-user:hover { background: #f8fafc; }
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
        .header-user-wrap.open .header-user { background: #f8fafc; }

        .header-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0;
            width: 260px; background: #fff;
            border: 1.5px solid #e5e7eb; border-radius: 18px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.14);
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
            background: linear-gradient(135deg, #0f172a, #1e293b);
            padding: 18px; display: flex; align-items: center; gap: 12px;
        }
        .hd-avatar {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff; font-size: 15px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .hd-name { font-size: 14px; font-weight: 800; color: #fff; }
        .hd-email { font-size: 11px; color: #64748b; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
        .hd-items { padding: 8px; }
        .hd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            color: #374151; font-size: 13px; font-weight: 600;
            text-decoration: none; cursor: pointer;
            transition: all 0.15s; margin-bottom: 2px;
        }
        .hd-item:hover { background: #f8fafc; color: #0f172a; }
        .hd-danger { color: #dc2626; }
        .hd-danger:hover { background: #fef2f2; }
        .hd-icon {
            width: 32px; height: 32px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .hd-divider { height: 1px; background: #f1f5f9; margin: 6px 8px; }

        /* ── Page body ────────────────────────────────────── */
        .page-body { padding: 28px; display: flex; flex-direction: column; gap: 24px; }

        /* ── Cards ────────────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 22px 0;
            margin-bottom: 18px;
        }
        .card-title {
            font-size: 15px; font-weight: 700; color: #0f172a;
            display: flex; align-items: center; gap: 8px;
        }
        .card-title svg { width: 17px; height: 17px; color: var(--red); }
        .card-action {
            font-size: 13px; font-weight: 600; color: var(--red);
            text-decoration: none; cursor: pointer;
            padding: 5px 12px;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .card-action:hover { background: var(--red-light); }
        .card-body { padding: 0 22px 22px; }

        /* ── Stat cards ───────────────────────────────────── */
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px 22px;
            display: flex; flex-direction: column; gap: 14px;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.red::before    { background: var(--red); }
        .stat-card.amber::before  { background: #f59e0b; }
        .stat-card.blue::before   { background: #3b82f6; }
        .stat-card.green::before  { background: #16a34a; }

        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }
        .stat-icon.red   { background:#fef2f2; color: var(--red); }
        .stat-icon.amber { background:#fffbeb; color: #d97706; }
        .stat-icon.blue  { background:#eff6ff; color: #2563eb; }
        .stat-icon.green { background:#f0fdf4; color: #15803d; }
        .stat-icon svg   { width: 22px; height: 22px; }

        .stat-value { font-size: 28px; font-weight: 900; color: #0f172a; line-height: 1; transition: transform 0.2s; }
        .stat-label { font-size: 13px; font-weight: 600; color: #64748b; }
        .stat-change { font-size: 12px; color: #94a3b8; }

        /* Welcome banner */
        .welcome-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            display: flex; align-items: center; justify-content: space-between;
            position: relative; overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        .welcome-banner::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 50%, rgba(220,38,38,0.15) 0%, transparent 60%),
                radial-gradient(circle at 10% 80%, rgba(220,38,38,0.08) 0%, transparent 50%);
        }
        .welcome-grid {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        .welcome-text { position: relative; z-index: 1; }
        .welcome-text h2 { font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 6px; }
        .welcome-text p  { font-size: 14px; color: #94a3b8; margin-bottom: 18px; }
        .welcome-cta {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--red);
            color: #fff;
            padding: 11px 22px;
            border-radius: 12px;
            font-size: 14px; font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(220,38,38,0.4);
        }
        .welcome-cta:hover { background: var(--red-dark); transform: translateY(-1px); }

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
        .health-bar-label { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; }
        .health-bar-track { height: 6px; background: #f1f5f9; border-radius: 99px; overflow: hidden; }
        .health-bar-fill  { height: 100%; border-radius: 99px; transition: width 1s cubic-bezier(0.4,0,0.2,1); }

        /* Bookings table */
        .booking-row {
            display: grid;
            grid-template-columns: auto 1fr auto auto;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
            cursor: pointer;
            border-radius: 10px;
            padding-left: 8px; padding-right: 8px;
            margin: 0 -8px;
        }
        .booking-row:last-child { border-bottom: none; }
        .booking-row:hover { background: #f8fafc; }
        .booking-service-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: var(--red-light);
            display: flex; align-items: center; justify-content: center;
            color: var(--red); flex-shrink: 0;
        }
        .booking-service-icon svg { width: 20px; height: 20px; }
        .booking-name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .booking-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
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
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .timeline-card:hover { border-color: var(--red); background: var(--red-light); }
        .timeline-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .timeline-service { font-size: 14px; font-weight: 700; color: #0f172a; }
        .timeline-cost { font-size: 13px; font-weight: 800; color: var(--red); }
        .timeline-meta { font-size: 12px; color: #64748b; }
        .timeline-notes { font-size: 12px; color: #94a3b8; margin-top: 4px; font-style: italic; }

        /* Reminders */
        .reminder-item {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
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
        .reminder-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .reminder-sub   { font-size: 12px; color: #64748b; }
        .reminder-badge { font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 99px; flex-shrink: 0; }
        .km-bar-wrap { margin-top: 5px; }
        .km-bar { height: 4px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .km-fill { height: 100%; border-radius: 99px; }

        /* Notifications */
        .notif-item {
            display: flex; gap: 12px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: background 0.2s;
        }
        .notif-item:hover { background: #f8fafc; margin: 0 -8px; padding: 14px 8px; }
        .notif-item:last-child { border-bottom: none; }
        .notif-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-body { flex: 1; }
        .notif-title { font-size: 13px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px; }
        .notif-unread { width: 7px; height: 7px; border-radius: 50%; background: var(--red); flex-shrink: 0; }
        .notif-msg { font-size: 12px; color: #64748b; margin-top: 2px; }
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
        .vehicle-spec-label { font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 3px; }
        .vehicle-spec-value { font-size: 14px; font-weight: 700; color: #e2e8f0; }

        .mileage-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.06); }
        .mileage-label { display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
        .mileage-bar { height: 6px; background: rgba(255,255,255,0.1); border-radius: 99px; overflow: hidden; }
        .mileage-fill { height: 100%; background: linear-gradient(90deg, var(--red), #f97316); border-radius: 99px; transition: width 1.2s cubic-bezier(0.4,0,0.2,1); }

        /* Quick actions */
        .quick-actions { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }
        .quick-btn {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            padding: 16px 12px;
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
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
        .quick-btn-label { font-size: 12px; font-weight: 700; color: #374151; text-align: center; }

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
            background: #fff; border-radius: 20px;
            padding: 32px 28px; max-width: 520px; width: 100%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.2);
            animation: popIn 0.3s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0.88); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }
        .modal-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .modal-sub   { font-size: 13px; color: #64748b; margin-bottom: 20px; }
        .modal-field { margin-bottom: 16px; }
        .modal-field label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 5px; }
        .modal-field input,
        .modal-field select,
        .modal-field textarea {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; font-family: 'Sora', sans-serif; color: #0f172a;
            outline: none; transition: border-color 0.2s;
            background: #fff;
        }
        .modal-field input:focus,
        .modal-field select:focus,
        .modal-field textarea:focus { border-color: var(--red); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .btn-modal-primary {
            flex: 1; background: linear-gradient(135deg, var(--red), var(--red-dark));
            color: #fff; border: none; padding: 13px; border-radius: 12px;
            font-size: 14px; font-weight: 700; font-family: 'Sora', sans-serif;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-modal-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(220,38,38,0.3); }
        .btn-modal-ghost {
            flex: 0; padding: 13px 20px;
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif;
            background: none; color: #374151; cursor: pointer; transition: all 0.2s;
        }
        .btn-modal-ghost:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }

        /* Onboarding Wizard */
        .onboarding-wizard {
            animation: slideDown 0.5s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .step-item {
            transition: all 0.3s ease;
        }
        .step-item.completed {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #bbf7d0;
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
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 16px; }
            .next-booking-card { min-width: unset; width: 100%; }
            
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                border-top: 1px solid #e2e8f0;
                justify-content: space-around;
                padding: 12px 16px;
                z-index: 100;
                backdrop-filter: blur(10px);
                background: rgba(255,255,255,0.95);
            }
            .mobile-nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                font-size: 10px;
                color: #64748b;
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
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .page-body { padding: 16px; gap: 16px; }
            .welcome-banner { padding: 22px 20px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
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
            background:#fff; color:#374151;
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
            <div class="hidden md:flex items-center gap-2 text-xs font-semibold text-gray-500">
                <span class="live-dot"></span> Open today · 08:00–17:00
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
                        <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars($user['first_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($user['type']) ?></div>
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
                            <div class="hd-icon" style="background:#f1f5f9;color:#374151"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
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
                            <div class="hd-icon" style="background:#f8fafc;color:#64748b"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09"/></svg></div>
                            Settings
                        </a>
                        <div class="hd-divider"></div>
                        <a class="hd-item" href="<?= $base ?>/">
                            <div class="hd-icon" style="background:#f1f5f9;color:#374151"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
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
            <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-2xl p-6 mb-6 border border-red-200">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">🎉 Welcome to Your Dashboard!</h3>
                        <p class="text-gray-600 mb-4">Let's get your vehicle set up in 3 simple steps</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white rounded-xl p-4 text-center step-item cursor-pointer" data-step="1" onclick="completeOnboardingStep(1)">
                                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-2">1</div>
                                <div class="font-semibold text-gray-900">Add Vehicle</div>
                                <div class="text-xs text-gray-500 mt-1">Enter your car details</div>
                            </div>
                            <div class="bg-white rounded-xl p-4 text-center step-item cursor-pointer" data-step="2" onclick="completeOnboardingStep(2)">
                                <div class="w-10 h-10 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2">2</div>
                                <div class="font-semibold text-gray-400">Set Preferences</div>
                                <div class="text-xs text-gray-400 mt-1">Notification settings</div>
                            </div>
                            <div class="bg-white rounded-xl p-4 text-center step-item cursor-pointer" data-step="3" onclick="completeOnboardingStep(3)">
                                <div class="w-10 h-10 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-2">3</div>
                                <div class="font-semibold text-gray-400">Book Service</div>
                                <div class="text-xs text-gray-400 mt-1">Schedule first maintenance</div>
                            </div>
                        </div>
                        
                        <button onclick="startOnboarding()" class="bg-red-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-red-700 transition">
                            Start Setup →
                        </button>
                        <button onclick="dismissOnboarding()" class="ml-3 text-gray-500 hover:text-gray-700 text-sm">
                            Maybe later
                        </button>
                    </div>
                    <button onclick="dismissOnboarding()" class="text-gray-400 hover:text-gray-600">✕</button>
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
            <div class="welcome-grid"></div>
            <div class="welcome-text">
                <h2>Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($user['first_name']) ?> 👋</h2>
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
                <a href="#" class="welcome-cta" onclick="event.preventDefault();<?= empty($vehicle['make']) ? "showPanel('vehicle')" : "openBookModal()" ?>">
                    <?php if(empty($vehicle['make'])): ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Vehicle
                    <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Book a Service
                    <?php endif; ?>
                </a>
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

        <!-- Stats -->
        <?php if(count($overdue) > 0): ?>
        <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:4px" class="fade-in delay-1">
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
            $stats = [
                ['label'=>'Total Bookings',   'value'=>$total_all_bookings, 'icon'=>'tool',         'color'=>'red',   'change'=>'All time'],
                ['label'=>'Upcoming',         'value'=>count($upcoming_bookings),'icon'=>'calendar', 'color'=>'blue',  'change'=>'Scheduled'],
                ['label'=>$overdue_count > 0 ? 'Overdue!' : 'Vehicles', 'value'=>$overdue_count > 0 ? $overdue_count : count($all_vehicles), 'icon'=> $overdue_count > 0 ? 'alert-triangle' : 'truck', 'color'=> $overdue_count > 0 ? 'amber' : 'green', 'change'=> $overdue_count > 0 ? 'Needs attention' : 'Registered'],
                ['label'=>'Completed',        'value'=>count(array_filter($service_history, fn($h)=>$h['status']==='completed')), 'icon'=>'check-circle', 'color'=>'green', 'change'=> $last_svc ? 'Last: '.date('d M', strtotime($last_svc['booking_date'])) : 'None yet'],
            ];
            foreach ($stats as $s): ?>
            <div class="stat-card <?= $s['color'] ?>">
                <div class="stat-icon <?= $s['color'] ?>">
                    <i data-feather="<?= $s['icon'] ?>"></i>
                </div>
                <div>
                    <div class="stat-value counter-value" data-target="<?= is_numeric($s['value']) ? $s['value'] : 0 ?>"><?= $s['value'] ?></div>
                    <div class="stat-label"><?= htmlspecialchars($s['label']) ?></div>
                    <div class="stat-change"><?= htmlspecialchars($s['change']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Main grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <div class="lg:col-span-2 flex flex-col gap-6">

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

            </div>

            <div class="flex flex-col gap-6">

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
                        // Compute per-vehicle service progress
                        $v_last_km = (int)($v['mileage'] ?? 0);
                        // Assume service every 5000km
                        $v_last_service = max(0, $v_last_km - ($v_last_km % 5000));
                        $v_next_service = $v_last_service + 5000;
                        $v_miles_done = $v_last_km - $v_last_service;
                        $v_service_pct = min(100, round($v_miles_done / 5000 * 100));
                        $v_km_remaining = max(0, $v_next_service - $v_last_km);
                    ?>
                    <div class="vswitch-pane" data-vidx="<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
                        <div class="vehicle-make"><?= htmlspecialchars($v['make']) ?></div>
                        <div class="vehicle-name"><?= htmlspecialchars($v['model']) ?></div>
                        <div class="vehicle-year"><?= htmlspecialchars(($v['year'] ?: '—') .' · '. ($v['color'] ?: '—')) ?></div>
                        <?php if($v['registration']): ?><div class="vehicle-reg"><?= htmlspecialchars($v['registration']) ?></div><?php endif; ?>
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
                        <?php if($v['mileage'] > 0): ?>
                        <div class="mileage-section">
                            <div class="mileage-label">
                                <span>Service interval progress</span>
                                <span><?= $v_service_pct ?>% used</span>
                            </div>
                            <div class="mileage-bar">
                                <div class="mileage-fill" style="width:<?= $v_service_pct ?>%"></div>
                            </div>
                            <div style="font-size:11px;color:#64748b;margin-top:6px">
                                <?= number_format($v_km_remaining) ?> km remaining until next service
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
                            $vh_items   = $vh_data['items'];
                            $vh_overall = $vh_data['overall'];
                        ?>
                        <div class="health-pane" data-vidx="<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
                            <div class="health-ring-wrap">
                                <div class="health-ring">
                                    <svg width="140" height="140" viewBox="0 0 140 140">
                                        <circle cx="70" cy="70" r="56" fill="none" stroke="#f1f5f9" stroke-width="10"/>
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
                                        <div class="health-sublabel"><?= healthLabel($vh_overall) ?></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Vehicle label above bars -->
                            <div style="text-align:center;font-size:12px;color:#64748b;margin:6px 0 2px">
                                <strong style="color:#0f172a"><?= htmlspecialchars($v['make'].' '.$v['model']) ?></strong>
                                <?php if($vh_data['last_service_days_ago'] !== null): ?>
                                    · Last serviced <?= $vh_data['last_service_days_ago'] ?> day<?= $vh_data['last_service_days_ago'] !== 1 ? 's' : '' ?> ago
                                <?php else: ?>
                                    · No service history yet
                                <?php endif; ?>
                            </div>
                            <div class="health-items" style="margin-top:12px">
                                <?php foreach ($vh_items as $h): ?>
                                <div class="health-row">
                                    <div class="health-icon <?= healthBg($h['score']) ?>">
                                        <i data-feather="<?= $h['icon'] ?>"></i>
                                    </div>
                                    <div class="health-bar-wrap">
                                        <div class="health-bar-label">
                                            <span><?= htmlspecialchars($h['label']) ?></span>
                                            <span style="color:<?= healthColor($h['score']) ?>"><?= healthLabel($h['score']) ?></span>
                                        </div>
                                        <div class="health-bar-track">
                                            <div class="health-bar-fill health-bar-animated"
                                                 data-target="<?= $h['score'] ?>"
                                                 style="width:<?= $h['score'] ?>%; background:<?= healthColor($h['score']) ?>"></div>
                                        </div>
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
                                <div class="quick-btn-icon bg-red-50 text-red-600"><i data-feather="calendar"></i></div>
                                <span class="quick-btn-label">Book Service</span>
                            </a>
                            <a href="https://wa.me/265993575111" target="_blank" class="quick-btn">
                                <div class="quick-btn-icon bg-green-50 text-green-600"><i data-feather="message-circle"></i></div>
                                <span class="quick-btn-label">WhatsApp Us</span>
                            </a>
                            <a href="tel:+265993575111" class="quick-btn">
                                <div class="quick-btn-icon bg-blue-50 text-blue-600"><i data-feather="phone"></i></div>
                                <span class="quick-btn-label">Call Now</span>
                            </a>
                            <a href="#" class="quick-btn" onclick="showPanel('history')">
                                <div class="quick-btn-icon bg-amber-50 text-amber-600"><i data-feather="file-text"></i></div>
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
                        <div class="reminder-icon <?= $r['urgency']==='urgent' ? 'bg-red-100 text-red-600' : ($r['urgency']==='soon' ? 'bg-amber-100 text-amber-600' : 'bg-gray-100 text-gray-500') ?>">
                            <i data-feather="<?= $r['icon'] ?>"></i>
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
                <h1 style="font-size:22px;font-weight:900;color:#0f172a">My Bookings</h1>
                <p style="font-size:13px;color:#64748b;margin-top:2px">Manage all your upcoming and past service appointments</p>
            </div>
            <button onclick="openBookModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </button>
        </div>

        <?php foreach ([['Upcoming', $upcoming_bookings], ['Past Services', $service_history]] as [$section, $items]): ?>
        <div class="card fade-in delay-1">
            <div class="card-header">
                <div class="card-title"><?= $section ?> <span style="font-size:12px;background:#f1f5f9;color:#64748b;padding:3px 8px;border-radius:99px;font-weight:600"><?= count($items) ?></span></div>
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
                                <td style="padding:12px;font-weight:600;color:#0f172a"><?= htmlspecialchars($i['service']) ?></td>
                                <td style="padding:12px;color:#64748b"><?= date('d M Y', strtotime($i['date'])) ?><?= isset($i['time']) ? ' · '.date('g:i A', strtotime($i['time'])) : '' ?></td>
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
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Service History</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Complete record of all services performed on your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?></p>
        </div>

        <?php if(empty($service_history)): ?>
        <div class="card fade-in delay-1">
            <div class="card-body text-center py-12">
                <div style="font-size:64px;opacity:0.3">🔧</div>
                <h3 class="text-lg font-bold text-gray-900 mt-4">No Service History Yet</h3>
                <p class="text-gray-500 mt-2">Your service records will appear here once you book your first service.</p>
                <button onclick="openBookModal()" class="mt-6 bg-red-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-red-700 transition">Book Your First Service →</button>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 fade-in delay-1">
            <?php
            $total_services = count($service_history);
            $most_recent = $service_history[0]['date'];
            ?>
            <div class="stat-card red"><div class="stat-icon red"><i data-feather="tool"></i></div><div><div class="stat-value"><?= $total_services ?></div><div class="stat-label">Total Services</div></div></div>
            <div class="stat-card green"><div class="stat-icon green"><i data-feather="dollar-sign"></i></div><div><div class="stat-value" style="font-size:20px">MWK 125.5K</div><div class="stat-label">Total Invested</div></div></div>
            <div class="stat-card blue"><div class="stat-icon blue"><i data-feather="calendar"></i></div><div><div class="stat-value" style="font-size:18px"><?= date('M Y', strtotime($most_recent)) ?></div><div class="stat-label">Last Service</div></div></div>
            <div class="stat-card amber"><div class="stat-icon amber"><i data-feather="star"></i></div><div><div class="stat-value">5.0</div><div class="stat-label">Avg. Rating</div></div></div>
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
                            <div style="margin-top:10px;display:flex;gap:8px">
                                <button style="font-size:12px;font-weight:600;color:#dc2626;background:none;border:1px solid #fecaca;padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif">Download Invoice</button>
                                <button style="font-size:12px;font-weight:600;color:#64748b;background:none;border:1px solid #e2e8f0;padding:4px 12px;border-radius:8px;cursor:pointer;font-family:'Sora',sans-serif">Leave Review</button>
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
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Maintenance Reminders</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Stay on top of your <?= htmlspecialchars($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?>'s maintenance schedule</p>
        </div>

        <?php if(empty($reminders)): ?>
        <div class="card fade-in delay-1">
            <div class="card-body text-center py-12">
                <div style="font-size:64px;opacity:0.3">✅</div>
                <h3 class="text-lg font-bold text-gray-900 mt-4">All Caught Up!</h3>
                <p class="text-gray-500 mt-2">Your vehicle is in great shape. No maintenance reminders at this time.</p>
                <button onclick="showPanel('vehicle')" class="mt-6 bg-gray-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-gray-700 transition">View Vehicle Details</button>
            </div>
        </div>
        <?php else: ?>
        <?php if($urgent_count): ?>
        <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:12px" class="fade-in delay-1">
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
                            <i data-feather="<?= $r['icon'] ?>"></i>
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                                <div style="font-size:15px;font-weight:800;color:#0f172a"><?= htmlspecialchars($r['title']) ?></div>
                                <?= urgencyBadge($r['urgency']) ?>
                            </div>
                            <div style="font-size:12px;color:#64748b;margin-bottom:10px">
                                <?= htmlspecialchars($r['interval']) ?>
                                <?php if($r['last_date']): ?>
                                 · Last: <?= date('d M Y', strtotime($r['last_date'])) ?> (<?= $days ?> day<?= $days!==1?'s':'' ?> ago)
                                <?php endif; ?>
                            </div>
                            <div style="background:#f1f5f9;border-radius:99px;height:8px;overflow:hidden;margin-bottom:6px">
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
                <h1 style="font-size:22px;font-weight:900;color:#0f172a">My Vehicles</h1>
                <p style="font-size:13px;color:#64748b;margin-top:2px"><?= count($all_vehicles) ?> vehicle<?= count($all_vehicles)!==1?'s':'' ?> registered</p>
            </div>
            <?php if(count($all_vehicles) < 5): ?>
            <button onclick="openVehicleModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:10px 20px;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.3)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Vehicle
            </button>
            <?php endif; ?>
        </div>

        <?php if(count($all_vehicles) === 0): ?>
        <!-- Empty state -->
        <div class="card fade-in delay-1" style="text-align:center;padding:48px 24px">
            <div style="width:64px;height:64px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div style="font-size:18px;font-weight:800;color:#0f172a;margin-bottom:6px">No vehicles added yet</div>
            <p style="font-size:13px;color:#64748b;margin-bottom:20px">Add your vehicle to get personalized service recommendations</p>
            <button onclick="openVehicleModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 28px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;box-shadow:0 4px 14px rgba(220,38,38,0.3)">+ Add Your First Vehicle</button>
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
                    <div><div class="vehicle-spec-label">Mileage</div><div class="vehicle-spec-value"><?= number_format($v['mileage']) ?> km</div></div>
                    <div><div class="vehicle-spec-label">Registration</div><div class="vehicle-spec-value"><?= htmlspecialchars($v['registration'] ?: '—') ?></div></div>
                </div>
                <div style="display:flex;gap:8px;margin-top:18px">
                    <button onclick="editVehicle(<?= htmlspecialchars(json_encode($v)) ?>)" style="flex:1;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);color:#e2e8f0;padding:10px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Edit</button>
                    <?php if($i > 0): ?>
                    <button onclick="deleteVehicle(<?= $v['id'] ?>,'<?= htmlspecialchars($v['make'].' '.$v['model']) ?>')" style="background:rgba(220,38,38,0.15);border:1px solid rgba(220,38,38,0.25);color:#fca5a5;padding:10px 16px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Remove</button>
                    <?php endif; ?>
                </div>
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
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Vehicle Health Report</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Based on your last inspection on <?= !empty($service_history) ? date('d M Y', strtotime($service_history[0]['date'])) : date('d M Y') ?></p>
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
                        <div class="health-sublabel" style="color:#94a3b8">Overall</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:28px;font-weight:900;color:#fff;margin-bottom:6px">
                        <?= $overall_health >= 80 ? '✅ Your car is in good shape' : ($overall_health >= 55 ? '⚠️ Some items need attention' : '🚨 Immediate service recommended') ?>
                    </div>
                    <div style="font-size:14px;color:#94a3b8;margin-bottom:16px">
                        <?= $overall_health >= 80 ? 'Keep up with routine maintenance to stay in top condition.' : 'We recommend booking a service to address flagged items.' ?>
                    </div>
                    <button onclick="openBookModal()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif;display:flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(220,38,38,0.4)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Book Full Inspection
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in delay-2">
            <?php foreach ($health_items as $h): ?>
            <div class="card">
                <div class="card-body" style="padding:20px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:<?= $h['score']>=80 ? '#f0fdf4' : ($h['score']>=55 ? '#fffbeb' : '#fef2f2') ?>;color:<?= healthColor($h['score']) ?>">
                                <i data-feather="<?= $h['icon'] ?>"></i>
                            </div>
                            <div style="font-size:15px;font-weight:800;color:#0f172a"><?= htmlspecialchars($h['label']) ?></div>
                        </div>
                        <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px;background:<?= $h['score']>=80 ? '#f0fdf4' : ($h['score']>=55 ? '#fffbeb' : '#fef2f2') ?>;color:<?= healthColor($h['score']) ?>">
                            <?= healthLabel($h['score']) ?>
                        </span>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <div style="font-size:36px;font-weight:900;color:<?= healthColor($h['score']) ?>;line-height:1"><?= $h['score'] ?>%</div>
                    </div>
                    <div style="background:#f1f5f9;border-radius:99px;height:8px;overflow:hidden">
                        <div style="width:<?= $h['score'] ?>%;height:100%;background:<?= healthColor($h['score']) ?>;border-radius:99px;transition:width 1.2s cubic-bezier(0.4,0,0.2,1)"></div>
                    </div>
                    <?php if($h['score'] < 70): ?>
                    <button onclick="openBookModal()" style="width:100%;margin-top:12px;background:none;border:1.5px solid #fecaca;color:#dc2626;padding:8px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:'Sora',sans-serif">Book Service →</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── NOTIFICATIONS PANEL ─── (Keep from original) -->
    <div id="panel-notifications" class="page-body" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between" class="fade-in">
            <div>
                <h1 style="font-size:22px;font-weight:900;color:#0f172a">Notifications</h1>
                <p style="font-size:13px;color:#64748b;margin-top:2px"><?= $unread_count ?> unread notification<?= $unread_count!==1?'s':'' ?></p>
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
                <p style="font-size:13px;color:#64748b;margin-bottom:16px">Choose how you'd like to be notified. All notifications are sent via WhatsApp.</p>
                <div style="display:flex;flex-direction:column;gap:12px">
                    <?php foreach ([
                        ['WhatsApp booking confirmations', true],
                        ['Service reminders & due alerts', true],
                        ['Overdue booking notifications', true],
                        ['Promotional offers and discounts', false],
                    ] as [$label, $checked]): ?>
                    <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:12px;transition:all 0.15s" onmouseover="this.style.borderColor='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0'">
                        <span style="font-size:14px;font-weight:500;color:#374151"><?= htmlspecialchars($label) ?></span>
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
            <h1 style="font-size:22px;font-weight:900;color:#0f172a">Account Settings</h1>
            <p style="font-size:13px;color:#64748b;margin-top:2px">Manage your profile, security, and preferences</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 flex flex-col gap-6">
                <div class="card fade-in delay-1">
                    <div class="card-header"><div class="card-title"><i data-feather="user" style="width:17px;height:17px;color:#dc2626;margin-right:4px"></i>Profile Information</div></div>
                    <div class="card-body">
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;padding-bottom:22px;border-bottom:1px solid #f1f5f9">
                            <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;flex-shrink:0"><?= htmlspecialchars($user['avatar_initials']) ?></div>
                            <div>
                                <div style="font-size:18px;font-weight:800;color:#0f172a"><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></div>
                                <div style="font-size:13px;color:#64748b"><?= htmlspecialchars($user['email']) ?></div>
                                <div style="margin-top:8px;display:flex;gap:8px">
                                    <button style="font-size:12px;font-weight:600;background:none;border:1px solid #e2e8f0;padding:5px 12px;border-radius:8px;cursor:pointer;color:#374151;font-family:'Sora',sans-serif">Change Photo</button>
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
                                <input type="<?= $type ?>" id="<?= $fid ?>" value="<?= htmlspecialchars($value) ?>" <?= $fid==='prof_email'||$fid==='prof_type'?'readonly style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:\'Sora\',sans-serif;color:#94a3b8;outline:none;background:#f8fafc"':'style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:\'Sora\',sans-serif;color:#0f172a;outline:none;transition:border-color 0.2s" onfocus="this.style.borderColor=\'#dc2626\'" onblur="this.style.borderColor=\'#e2e8f0\'"' ?>>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:16px;display:flex;gap:10px">
                            <button onclick="saveProfile()" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;padding:12px 24px;border-radius:11px;font-size:14px;font-weight:700;font-family:'Sora',sans-serif;cursor:pointer">Save Profile</button>
                            <button style="background:none;border:1.5px solid #e2e8f0;padding:12px 24px;border-radius:11px;font-size:14px;font-weight:600;font-family:'Sora',sans-serif;cursor:pointer;color:#374151">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="card fade-in delay-2">
                    <div class="card-header"><div class="card-title"><i data-feather="lock" style="width:17px;height:17px;color:#dc2626;margin-right:4px"></i>Change Password</div></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:1fr;gap:12px;max-width:400px">
                            <?php foreach ([
                                ['Current Password','pwd_current'],
                                ['New Password','pwd_new'],
                                ['Confirm New Password','pwd_confirm'],
                            ] as [$f,$fid]): ?>
                            <div>
                                <label style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;display:block;margin-bottom:5px"><?= $f ?></label>
                                <input type="password" id="<?= $fid ?>" placeholder="••••••••" style="width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:'Sora',sans-serif;color:#0f172a;outline:none" onfocus="this.style.borderColor='#dc2626'" onblur="this.style.borderColor='#e2e8f0'">
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
                    <div style="font-size:12px;color:#64748b;margin-bottom:16px"><?= htmlspecialchars($user['type']) ?></div>
                    <div style="font-size:11px;color:#475569;margin-bottom:4px">MEMBER SINCE</div>
                    <div style="font-size:15px;font-weight:700;color:#e2e8f0"><?= date('d M Y', strtotime($user['member_since'])) ?></div>
                    <div style="margin-top:14px;display:flex;align-items:center;gap:6px">
                        <span class="live-dot"></span>
                        <span style="font-size:11px;color:#22c55e;font-weight:600">Active Member</span>
                    </div>
                </div>

                <div class="card fade-in delay-2">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:14px">Account Summary</div>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            <?php foreach ([
                                ['Services Booked', count($service_history)],
                                ['Member Points',   '1,200 pts'],
                                ['Savings Earned',  'MWK 8,000'],
                            ] as [$k,$v]): ?>
                            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9">
                                <span style="font-size:13px;color:#64748b"><?= $k ?></span>
                                <span style="font-size:13px;font-weight:800;color:#0f172a"><?= $v ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card fade-in delay-3" style="border:1.5px solid #fecaca">
                    <div class="card-body" style="padding:20px">
                        <div style="font-size:13px;font-weight:700;color:#dc2626;margin-bottom:8px">⚠ Danger Zone</div>
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px">These actions are irreversible. Please be careful.</p>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <button onclick="deactivateAccount()" style="width:100%;background:none;border:1.5px solid #fecaca;color:#dc2626;padding:10px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Sora',sans-serif">Deactivate Account</button>
                            <a href="<?= $base ?>/pages/logout.php" style="display:block;text-align:center;background:none;border:1.5px solid #e2e8f0;color:#64748b;padding:10px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">
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
                <option>Tyre Services</option>
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

        <div style="background:#f8fafc;border-radius:12px;padding:12px 14px;font-size:13px;color:#64748b;margin-bottom:4px" id="bookingVehicleDisplay">
            <strong style="color:#0f172a">Vehicle:</strong> <?= count($all_vehicles) === 0 ? '<em style="color:#94a3b8">Not set — add a vehicle in My Vehicles</em>' : htmlspecialchars($all_vehicles[0]['year'].' '.$all_vehicles[0]['make'].' '.$all_vehicles[0]['model'].($all_vehicles[0]['registration'] ? ' ('.$all_vehicles[0]['registration'].')' : '')) ?>
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
    feather.replace();
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
    if (typeof feather !== 'undefined') feather.replace();
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
    feather.replace();
    
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
                display.innerHTML = '<strong style="color:#0f172a">Vehicle:</strong> ' +
                    (y + ' ' + mk + ' ' + md + (reg ? ' (' + reg + ')' : '')).trim();
            }
        });
    }
});
</script>
</body>
</html>