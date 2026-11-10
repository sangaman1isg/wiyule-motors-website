<?php
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . $base . "/admin/login.php");
    exit;
}

require_once __DIR__ . '/../includes/db_connection.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

/**
 * Render a phone number as a tap-to-call link + a small WhatsApp icon.
 * stopPropagation so tapping it inside a clickable row doesn't open the row.
 */
function wm_contact_links(?string $phone): string {
    $phone = trim((string)$phone);
    if ($phone === '') return '<span style="color:#cbd5e1">—</span>';
    $tel = htmlspecialchars($phone, ENT_QUOTES);
    $wa  = preg_replace('/\D/', '', $phone);
    if ($wa !== '' && substr($wa, 0, 3) !== '265') $wa = '265' . ltrim($wa, '0');
    $out = '<a href="tel:' . $tel . '" onclick="event.stopPropagation()" style="color:inherit;text-decoration:none">' . $tel . '</a>';
    if ($wa !== '') {
        $out .= ' <a href="https://wa.me/' . $wa . '" target="_blank" rel="noopener" onclick="event.stopPropagation()" title="WhatsApp" '
             .  'style="display:inline-flex;vertical-align:middle;color:#22c55e;text-decoration:none">'
             .  '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg></a>';
    }
    return $out;
}

// ── Fetch stats ──────────────────────────────────────────────
$stats = ['total'=>0, 'pending'=>0, 'confirmed'=>0, 'completed'=>0, 'cancelled'=>0, 'customers'=>0, 'today'=>0];

$r = $conn->query("SELECT COUNT(*) as c FROM bookings"); if($r) $stats['total'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='pending'"); if($r) $stats['pending'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='confirmed'"); if($r) $stats['confirmed'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='completed'"); if($r) $stats['completed'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE status='cancelled'"); if($r) $stats['cancelled'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM users WHERE is_admin=0"); if($r) $stats['customers'] = $r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM bookings WHERE DATE(created_at)=CURDATE()"); if($r) $stats['today'] = $r->fetch_assoc()['c'];

// Scheduled-work counts (by booking_date, excluding cancelled) for quick filters
$due_today = 0; $due_week = 0;
$r = $conn->query("SELECT
        COALESCE(SUM(booking_date = CURDATE()),0) t,
        COALESCE(SUM(booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)),0) w
     FROM bookings WHERE status != 'cancelled'");
if ($r) { $row = $r->fetch_assoc(); $due_today = (int)$row['t']; $due_week = (int)$row['w']; }
$today_str = date('Y-m-d');
$week_str  = date('Y-m-d', strtotime('+7 days'));

// ── Source breakdown for the current calendar month (for Overview widget) ──
// Returns rows like: ['source' => 'Google', 'cnt' => 5]
// Bookings with no source are bucketed under '__unknown__' for separate display.
$month_sources = [];
$month_sources_total = 0;
$r = $conn->query("
    SELECT
        COALESCE(NULLIF(source, ''), '__unknown__') AS src,
        COUNT(*) AS cnt
    FROM bookings
    WHERE YEAR(created_at)  = YEAR(CURDATE())
      AND MONTH(created_at) = MONTH(CURDATE())
    GROUP BY src
    ORDER BY cnt DESC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $month_sources[$row['src']] = (int)$row['cnt'];
        $month_sources_total += (int)$row['cnt'];
    }
}
// Separate known sources from "Not asked" so they render in different sections
$month_unknown_count = $month_sources['__unknown__'] ?? 0;
unset($month_sources['__unknown__']);
$month_sources_max = $month_sources ? max($month_sources) : 0;

// ── Security & Activity stats (last 7 days) ──────────────────
$sec = [
    'signups_week'     => 0,
    'verified_week'    => 0,
    'pending_verify'   => 0,
    'verify_rate'      => 0,
    'failed_logins_24h'=> 0,
    'unique_attackers' => 0,
    'locked_now'       => 0,
    'newest_signup'    => null,
];

$r = $conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($r) $sec['signups_week'] = (int)$r->fetch_assoc()['c'];

// These queries gracefully return 0 if the email_verified column doesn't exist yet
$r = @$conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND email_verified=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($r) $sec['verified_week'] = (int)$r->fetch_assoc()['c'];

$r = @$conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND email_verified=0");
if ($r) $sec['pending_verify'] = (int)$r->fetch_assoc()['c'];

if ($sec['signups_week'] > 0) {
    $sec['verify_rate'] = round($sec['verified_week'] / $sec['signups_week'] * 100);
}

$r = $conn->query("SELECT created_at, full_name FROM users WHERE is_admin=0 ORDER BY created_at DESC LIMIT 1");
if ($r && $r->num_rows) $sec['newest_signup'] = $r->fetch_assoc();

// Login throttle stats: read the same temp file the rate limiter uses
$throttle_file = sys_get_temp_dir() . '/wm_login_attempts.log';
$now_ts = time();
$day_cutoff   = $now_ts - 86400;     // 24h
$hour_cutoff  = $now_ts - 3600;      // 1h
$window_cutoff= $now_ts - (15 * 60); // 15 min (matches throttle window)
$ips_24h = []; $emails_recent = []; $ips_recent = [];
if (file_exists($throttle_file)) {
    foreach (file($throttle_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $p = explode("\t", $line);
        if (count($p) !== 3) continue;
        $ts = (int)$p[1];
        if ($ts < $day_cutoff) continue;
        $sec['failed_logins_24h']++;
        $ips_24h[$p[2]] = true;
        if ($ts >= $window_cutoff) {
            $emails_recent[$p[0]] = ($emails_recent[$p[0]] ?? 0) + 1;
            $ips_recent[$p[2]]    = ($ips_recent[$p[2]] ?? 0) + 1;
        }
    }
}
$sec['unique_attackers'] = count($ips_24h);
foreach ($emails_recent as $count) if ($count >= 5) $sec['locked_now']++;
foreach ($ips_recent as $count) if ($count >= 5) $sec['locked_now']++;

// ── Detect "unusual events" for the alerts panel ──────────────
require_once __DIR__ . '/../includes/admin_alerts.php';
$new_alerts = wm_check_alerts($conn);   // returns alerts that just crossed a threshold (cooldown-gated)

$alerts = [];
if ($sec['failed_logins_24h'] >= 20) {
    $alerts[] = ['level'=>'warn', 'title'=>'High failed login attempts',
                 'detail'=>$sec['failed_logins_24h'].' failed attempts in the last 24h from '.$sec['unique_attackers'].' IP(s)'];
}
if ($sec['locked_now'] > 0) {
    $alerts[] = ['level'=>'warn', 'title'=>'Account(s) currently locked',
                 'detail'=>$sec['locked_now'].' account(s)/IP(s) are in a 15-minute lockout right now'];
}
if ($sec['signups_week'] >= 20) {
    $alerts[] = ['level'=>'info', 'title'=>'Signup spike',
                 'detail'=>$sec['signups_week'].' new signups in the last 7 days — verify they\'re legitimate'];
}
if ($sec['pending_verify'] >= 10) {
    $alerts[] = ['level'=>'info', 'title'=>'Many unverified accounts',
                 'detail'=>$sec['pending_verify'].' accounts haven\'t verified their email — check Brevo for delivery issues'];
}

// ── Fetch bookings ───────────────────────────────────────────
$filter        = $_GET['status'] ?? 'all';
$source_filter = $_GET['source'] ?? 'all';
$search        = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM bookings";
$conditions = [];
$params = [];
$types = '';

if ($filter !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $filter;
    $types .= 's';
} else {
    // Default ("All") view hides cancelled bookings — still reachable via the Cancelled filter
    $conditions[] = "status != 'cancelled'";
}
if ($source_filter !== 'all') {
    if ($source_filter === 'unknown') {
        // Legacy bookings (pre-source-column) + bookings where customer skipped the question
        $conditions[] = "(source IS NULL OR source = '')";
    } else {
        $conditions[] = "source = ?";
        $params[] = $source_filter;
        $types .= 's';
    }
}
if ($search) {
    $conditions[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR services LIKE ? OR vehicle_make LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
// Date-range filter (booking_date)
$date_from = trim($_GET['from'] ?? '');
$date_to   = trim($_GET['to']   ?? '');
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $conditions[] = "booking_date >= ?"; $params[] = $date_from; $types .= 's'; }
if ($date_to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   { $conditions[] = "booking_date <= ?"; $params[] = $date_to;   $types .= 's'; }
if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Attach customer-uploaded problem photos (as public URLs) to each booking row.
require_once __DIR__ . '/../includes/booking_attachments.php';
try {
    $booking_photo_map = wm_booking_attachments_map($conn, array_column($bookings, 'id'));
} catch (Throwable $e) {
    $booking_photo_map = [];   // table not migrated yet — degrade gracefully
}
foreach ($bookings as &$b) {
    $b['photos'] = array_map(
        fn($f) => wm_booking_attachment_url($base, $f),
        $booking_photo_map[(int)$b['id']] ?? []
    );
}
unset($b);

// ── Compute source counts (only show pills for sources that have bookings) ──
$source_counts = ['unknown' => 0];
$r = $conn->query("
    SELECT
        COALESCE(NULLIF(source, ''), '__unknown__') AS src,
        COUNT(*) AS cnt
    FROM bookings
    GROUP BY src
    ORDER BY cnt DESC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $key = $row['src'] === '__unknown__' ? 'unknown' : $row['src'];
        $source_counts[$key] = (int)$row['cnt'];
    }
}

// ── Fetch customers ──────────────────────────────────────────
$customers = [];
$cust_search = trim($_GET['csearch'] ?? '');
if ($cust_search !== '') {
    $cstmt = $conn->prepare("SELECT u.*, COUNT(b.id) as booking_count, MAX(b.created_at) as last_booking
        FROM users u LEFT JOIN bookings b ON u.id = b.user_id
        WHERE u.is_admin = 0 AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
        GROUP BY u.id ORDER BY u.created_at DESC");
    $clike = "%$cust_search%";
    $cstmt->bind_param('sss', $clike, $clike, $clike);
    $cstmt->execute();
    $customers = $cstmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cstmt->close();
} else {
    $r = $conn->query("SELECT u.*, COUNT(b.id) as booking_count, MAX(b.created_at) as last_booking FROM users u LEFT JOIN bookings b ON u.id = b.user_id WHERE u.is_admin = 0 GROUP BY u.id ORDER BY u.created_at DESC");
    if ($r) $customers = $r->fetch_all(MYSQLI_ASSOC);
}

// ── Compute "Service Due" customers ──────────────────────────
// A vehicle is "due" if its last completed service was 80+ days ago,
// or it has never been serviced but was registered 80+ days ago.
$due_customers = [];
$dq = $conn->query("
    SELECT v.id AS vehicle_id, v.make, v.model, v.registration, v.mileage, v.created_at AS vehicle_created,
           u.id AS user_id, u.full_name, u.phone, u.email,
           (SELECT MAX(b.booking_date) FROM bookings b
              WHERE b.user_id = u.id AND b.status = 'completed') AS last_service
    FROM vehicles v
    JOIN users u ON u.id = v.user_id
    WHERE u.is_admin = 0
    ORDER BY last_service ASC
");
if ($dq) {
    while ($row = $dq->fetch_assoc()) {
        $last = $row['last_service'];
        if ($last !== null) {
            // Serviced before — due if 80+ days since the last completed service
            $days_since = floor((time() - strtotime($last)) / 86400);
            if ($days_since < 80) continue;
            $row['days_since'] = $days_since;
            $row['never'] = false;
            $due_customers[] = $row;
        } else {
            // Never serviced — only flag once the vehicle has been on record for 80+ days
            $veh_age = $row['vehicle_created'] ? floor((time() - strtotime($row['vehicle_created'])) / 86400) : 0;
            if ($veh_age < 80) continue;
            $row['days_since'] = null;
            $row['never'] = true;
            $due_customers[] = $row;
        }
    }
}

$active_tab = $_GET['tab'] ?? 'overview';

// ── Build per-customer detail data (vehicles + health + history) ──
// Pre-computed here so clicking a customer shows everything instantly.
$customer_details = [];
foreach ($customers as $c) {
    $uid = (int)$c['id'];
    $detail = ['vehicles' => [], 'history' => []];

    // Vehicles for this customer
    $vr = $conn->prepare("SELECT id, make, model, year, color, registration, mileage, engine FROM vehicles WHERE user_id = ? ORDER BY id");
    $vr->bind_param("i", $uid);
    $vr->execute();
    $vres = $vr->get_result();
    while ($v = $vres->fetch_assoc()) {
        // Last recorded service mileage for this customer
        $last_km = null; $last_date = null;
        $sr = $conn->prepare("SELECT MAX(CASE WHEN service_mileage > 0 THEN service_mileage END) AS last_km, MAX(booking_date) AS last_date FROM bookings WHERE user_id = ? AND status = 'completed'");
        $sr->bind_param("i", $uid);
        $sr->execute();
        $srow = $sr->get_result()->fetch_assoc();
        $last_km = $srow['last_km'] ? (int)$srow['last_km'] : null;
        $last_date = $srow['last_date'] ?? null;
        $sr->close();

        // Health score (same logic as customer dashboard)
        $cur_km = (int)($v['mileage'] ?? 0);
        $score = 95;
        $interval_pct = 0;
        if ($last_km !== null && $cur_km >= $last_km) {
            $since = $cur_km - $last_km;
            $interval_pct = min(100, round($since / 5000 * 100));
        }
        if ($last_date) {
            $days_ago = floor((time() - strtotime($last_date)) / 86400);
            if ($days_ago > 365) $score -= 25;
            elseif ($days_ago > 180) $score -= 15;
            elseif ($days_ago > 90) $score -= 5;
        } else {
            $score -= 10;
        }
        if ($interval_pct > 95) $score -= 20;
        elseif ($interval_pct > 85) $score -= 10;
        elseif ($interval_pct > 70) $score -= 5;
        $score = max(35, min(100, $score));

        $detail['vehicles'][] = [
            'name'  => trim($v['make'].' '.$v['model']),
            'reg'   => $v['registration'] ?: '—',
            'year'  => $v['year'] ?: '—',
            'color' => $v['color'] ?: '—',
            'mileage' => $cur_km ? number_format($cur_km).' km' : '—',
            'engine' => $v['engine'] ?: '—',
            'score' => $score,
            'interval_pct' => $interval_pct,
            'last_service' => $last_date ? date('d M Y', strtotime($last_date)) : 'Never',
        ];
    }
    $vr->close();

    // Service history (last 10 bookings)
    $hr = $conn->prepare("SELECT services, booking_date, status, service_mileage FROM bookings WHERE user_id = ? ORDER BY booking_date DESC LIMIT 10");
    $hr->bind_param("i", $uid);
    $hr->execute();
    $hres = $hr->get_result();
    while ($h = $hres->fetch_assoc()) {
        $detail['history'][] = [
            'service' => $h['services'] ?: 'Service',
            'date'    => $h['booking_date'] ? date('d M Y', strtotime($h['booking_date'])) : '—',
            'status'  => $h['status'],
            'mileage' => $h['service_mileage'] ? number_format($h['service_mileage']).' km' : '',
        ];
    }
    $hr->close();

    // Total spent — payments on invoices linked to this account or matching their phone
    $cphone_raw = $c['phone'] ?? '';
    $sp = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) s FROM invoices WHERE archived_at IS NULL AND (user_id = ? OR (customer_phone <> '' AND customer_phone = ?))");
    $sp->bind_param("is", $uid, $cphone_raw);
    $sp->execute();
    $detail['total_spent'] = (float)$sp->get_result()->fetch_assoc()['s'];
    $sp->close();

    $customer_details[$uid] = $detail;
}

// ── Last recorded service mileage per user (for sanity-check on completion) ──
$last_mileage_by_user = [];
$lm = $conn->query("SELECT user_id, MAX(service_mileage) AS last_km FROM bookings WHERE service_mileage > 0 GROUP BY user_id");
if ($lm) {
    while ($row = $lm->fetch_assoc()) {
        $last_mileage_by_user[(int)$row['user_id']] = (int)$row['last_km'];
    }
}
// Also map current vehicle mileage per user (a service reading shouldn't be wildly below it)
$vehicle_mileage_by_user = [];
$vm = $conn->query("SELECT user_id, MAX(mileage) AS cur_km FROM vehicles WHERE mileage > 0 GROUP BY user_id");
if ($vm) {
    while ($row = $vm->fetch_assoc()) {
        $vehicle_mileage_by_user[(int)$row['user_id']] = (int)$row['cur_km'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/../includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin — Wiyule Motors</title>
    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --red:#dc2626; --red-dark:#b91c1c; --sidebar-w:240px; }
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Sora',sans-serif; background:#f1f5f9; color:#1e293b; display:flex; min-height:100vh; }

        /* Sidebar */
        #sidebar { width:var(--sidebar-w); background:#0f172a; display:flex; flex-direction:column; position:fixed; top:0; bottom:0; left:0; z-index:100; transition:transform 0.3s; }
        .sb-logo { padding:20px; border-bottom:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; gap:12px; text-decoration:none; }
        .sb-logo-icon { width:36px; height:36px; background:linear-gradient(135deg,var(--red),var(--red-dark)); border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .sb-logo-text { font-size:15px; font-weight:800; color:#fff; }
        .sb-logo-sub { font-size:10px; color:#64748b; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; }
        .sb-nav { padding:16px 10px; flex:1; }
        .sb-label { font-size:10px; font-weight:700; color:#475569; letter-spacing:0.12em; text-transform:uppercase; padding:8px 10px 6px; }
        .sb-item { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px; color:#94a3b8; font-size:13px; font-weight:500; cursor:pointer; transition:all 0.15s; text-decoration:none; margin-bottom:2px; }
        .sb-item:hover { background:rgba(255,255,255,0.06); color:#e2e8f0; }
        .sb-item.active { background:linear-gradient(135deg,var(--red),var(--red-dark)); color:#fff; box-shadow:0 4px 12px rgba(220,38,38,0.35); }
        .sb-item svg { width:18px; height:18px; flex-shrink:0; }
        .sb-badge { margin-left:auto; background:var(--red); color:#fff; font-size:10px; font-weight:800; min-width:20px; height:20px; border-radius:10px; display:flex; align-items:center; justify-content:center; padding:0 6px; }
        .sb-user { padding:14px 12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; align-items:center; gap:10px; }
        .sb-avatar { width:36px; height:36px; border-radius:10px; background:linear-gradient(135deg,var(--red),var(--red-dark)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0; }

        /* Main */
        #main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; }
        .top-bar { height:64px; background:#fff; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; padding:0 28px; gap:16px; position:sticky; top:0; z-index:50; }
        .page-body { padding:28px; }

        /* Cards */
        .stat-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.06); transition:transform 0.2s; position:relative; overflow:hidden; }
        .stat-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.1); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .stat-card.red::before { background:#dc2626; }
        .stat-card.amber::before { background:#f59e0b; }
        .stat-card.green::before { background:#16a34a; }
        .stat-card.blue::before { background:#3b82f6; }

        /* Table */
        .data-table { width:100%; border-collapse:collapse; font-size:13px; }
        .data-table th { text-align:left; padding:12px 16px; font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.06em; border-bottom:2px solid #f1f5f9; }
        .data-table td { padding:14px 16px; border-bottom:1px solid #f8fafc; }
        .data-table tr:hover td { background:#f8fafc; }
        .status-pill { font-size:11px; font-weight:700; padding:4px 12px; border-radius:99px; display:inline-block; }
        .status-pending   { background:#fffbeb; color:#d97706; }
        .status-confirmed { background:#f0fdf4; color:#15803d; }
        .status-completed { background:#f1f5f9; color:#64748b; }
        .status-cancelled { background:#fef2f2; color:#dc2626; }

        .action-btn { padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff; color:#374151; transition:all 0.15s; }
        .action-btn:hover { border-color:var(--red); color:var(--red); }
        .action-btn.confirm { border-color:#16a34a; color:#16a34a; }
        .action-btn.confirm:hover { background:#f0fdf4; }
        .action-btn.complete { border-color:#3b82f6; color:#3b82f6; }
        .action-btn.complete:hover { background:#eff6ff; }
        .action-btn.cancel { border-color:#dc2626; color:#dc2626; }
        .action-btn.cancel:hover { background:#fef2f2; }
        .action-btn.invoice-btn { border-color:#0f172a; background:#0f172a; color:#fff; text-decoration:none; display:inline-flex; align-items:center; }
        .action-btn.invoice-btn:hover { background:var(--red); border-color:var(--red); color:#fff; }

        .filter-btn { padding:8px 16px; border-radius:10px; font-size:13px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; border:1.5px solid #e2e8f0; background:#fff; color:#64748b; transition:all 0.15s; text-decoration:none; }
        .filter-btn:hover { border-color:var(--red); color:var(--red); }
        .filter-btn.active { background:var(--red); color:#fff; border-color:var(--red); }

        /* Recent-bookings quick filter chips */
        .rb-filter { display:flex; gap:5px; flex-wrap:wrap; }
        .rb-chip { padding:5px 11px; border-radius:99px; font-size:12px; font-weight:600; font-family:'Sora',sans-serif; cursor:pointer; border:1px solid #e2e8f0; background:#fff; color:#64748b; transition:all 0.12s; }
        .rb-chip:hover { border-color:#cbd5e1; color:#0f172a; }
        .rb-chip.active { background:#0f172a; color:#fff; border-color:#0f172a; }

        /* Flash newly-arrived bookings gold */
        @keyframes wmFlash { 0%,12% { background:#fde68a; } 100% { background:transparent; } }
        tr.wm-flash, tr.wm-flash td { animation: wmFlash 3.5s ease-out; }

        /* Source pills — smaller/lighter, secondary filter row */
        .source-pill {
            padding:4px 10px; border-radius:999px;
            font-size:11.5px; font-weight:600;
            font-family:'Sora',sans-serif;
            text-decoration:none;
            background:#f1f5f9; color:#64748b;
            border:1px solid transparent;
            transition:all 0.12s;
        }
        .source-pill:hover { background:#e2e8f0; color:#0f172a; }
        .source-pill.active {
            background:#dc2626; color:#fff;
            border-color:#b91c1c;
        }

        /* ─── Source breakdown widget (Overview tab) ─── */
        .source-widget {
            background:#fff;
            border-radius:16px;
            padding:20px 22px;
            box-shadow:0 1px 3px rgba(0,0,0,0.06);
            margin-bottom:24px;
        }
        .source-widget-head {
            display:flex; align-items:flex-start; justify-content:space-between;
            gap:12px; margin-bottom:16px;
        }
        .source-widget-title {
            font-size:13px; font-weight:700;
            color:#0f172a;
            text-transform:uppercase;
            letter-spacing:0.04em;
        }
        .source-widget-sub {
            font-size:12px; color:#94a3b8;
            margin-top:3px;
        }
        .source-empty {
            font-size:13px; color:#64748b;
            background:#f8fafc;
            border-radius:10px;
            padding:14px 16px;
            line-height:1.55;
        }
        .source-empty strong { color:#0f172a; }
        .source-bars {
            display:flex; flex-direction:column;
            gap:10px;
        }
        .source-row {
            display:grid;
            grid-template-columns: 130px 1fr 70px;
            gap:12px; align-items:center;
        }
        @media (max-width: 600px) {
            .source-row { grid-template-columns: 100px 1fr 60px; gap:10px; }
        }
        .source-row-label {
            font-size:13px; font-weight:600;
            color:#334155;
            white-space:nowrap;
            overflow:hidden; text-overflow:ellipsis;
        }
        .source-row-bar-wrap {
            background:#f1f5f9;
            border-radius:999px;
            height:18px;
            overflow:hidden;
            position:relative;
        }
        .source-row-bar {
            background:linear-gradient(90deg, #dc2626, #ef4444);
            height:100%;
            border-radius:999px;
            min-width:6px;
            transition:width 0.4s ease-out;
        }
        .source-row-count {
            font-size:13px; font-weight:700;
            color:#0f172a;
            text-align:right;
            font-variant-numeric:tabular-nums;
        }
        .source-row-share {
            font-size:11px; font-weight:500;
            color:#94a3b8;
            margin-left:3px;
        }
        .source-unknown-note {
            margin-top:14px; padding-top:12px;
            border-top:1px dashed #e2e8f0;
            font-size:11.5px;
            color:#94a3b8;
            font-style:italic;
        }

        .panel { display:none; }
        .panel.active { display:block; }

        .search-box { display:flex; align-items:center; background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:10px; padding:8px 14px; gap:8px; flex:1; max-width:360px; }
        .search-box input { border:none; outline:none; background:none; font-size:14px; font-family:'Sora',sans-serif; color:#1e293b; width:100%; }
        .search-box input::placeholder { color:#94a3b8; }

        /* Mobile */
        .mob-toggle { display:none; }
        #sidebarOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99; }
        @media(max-width:768px) {
            #sidebar { transform:translateX(-100%); }
            #sidebar.open { transform:translateX(0); }
            #sidebarOverlay.open { display:block; }
            #main { margin-left:0; }
            .mob-toggle { display:flex; }
            .page-body { padding:16px; }
        }

        /* ── Mobile: turn .cards-mobile data-tables into stacked cards ── */
        @media(max-width:640px) {
            html, body { overflow-x:hidden; }
            #main { min-width:0; }
            .top-bar { padding:0 14px; gap:10px; }
            .search-box { min-width:0; max-width:none; }
            .table-card { background:transparent !important; box-shadow:none !important; border-radius:0 !important; }
            .cards-mobile .table-scroll, .table-scroll { overflow-x:visible; }
            table.data-table.cards-mobile, .cards-mobile thead, .cards-mobile tbody, .cards-mobile tr { display:block; }
            .cards-mobile thead { display:none; }
            .cards-mobile tbody tr {
                background:#fff; border:1px solid #e2e8f0; border-radius:14px;
                margin-bottom:12px; padding:4px 14px 10px;
                box-shadow:0 1px 3px rgba(15,23,42,0.05);
            }
            .cards-mobile tbody tr:hover td { background:transparent; }
            /* Label on top, value below — handles multi-line cells (name/phone/email,
               vehicle make+model/year) without them flowing sideways and overlapping. */
            .cards-mobile td {
                display:block; padding:8px 0; border:none; border-bottom:1px solid #f4f6f9;
                text-align:left; min-width:0; overflow-wrap:anywhere;
            }
            .cards-mobile td:last-child { border-bottom:none; }
            .cards-mobile td::before {
                content:attr(data-label); display:block; margin-bottom:3px;
                font-size:10.5px; font-weight:700; letter-spacing:0.04em;
                text-transform:uppercase; color:#94a3b8;
            }
            .cards-mobile td[data-label=""]::before { display:none; }
            /* Actions row: full-width buttons */
            .cards-mobile td.cell-actions { padding-top:12px; border-bottom:none; }
            .cards-mobile td.cell-actions::before { display:none; }
            .cards-mobile td.cell-actions > div { width:100%; gap:8px; }
            .cards-mobile td.cell-actions .action-btn { flex:1 1 auto; text-align:center; justify-content:center; padding:11px 10px; font-size:13px; }
        }

        /* Toast */
        .toast { position:fixed; bottom:24px; right:24px; background:#fff; border-radius:12px; padding:14px 20px; box-shadow:0 4px 20px rgba(0,0,0,0.15); display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; z-index:999; transform:translateX(120%); transition:transform 0.3s; }
        .toast.show { transform:translateX(0); }
        .toast.success { border-left:4px solid #16a34a; }
        .toast.error { border-left:4px solid #dc2626; }

        .detail-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; padding:20px; }
        .detail-modal.open { display:flex; }
        .detail-box { background:#fff; border-radius:20px; max-width:520px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 32px 80px rgba(0,0,0,0.2); }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <a href="<?= $base ?>/admin/" class="sb-logo">
        <div class="sb-logo-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
            <div class="sb-logo-text">Wiyule Motors</div>
            <div class="sb-logo-sub">Admin Panel</div>
        </div>
    </a>
    <div class="sb-nav">
        <div class="sb-label">Management</div>
        <a class="sb-item <?= $active_tab==='overview'?'active':'' ?>" onclick="showTab('overview')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Overview
        </a>
        <a class="sb-item <?= $active_tab==='bookings'?'active':'' ?>" onclick="showTab('bookings')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Bookings
            <span class="sb-badge" id="pendingBadge" style="<?= $stats['pending'] ? '' : 'display:none' ?>"><?= $stats['pending'] ?></span>
        </a>
        <a class="sb-item <?= $active_tab==='customers'?'active':'' ?>" onclick="showTab('customers')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Customers
            <span class="sb-badge" style="background:#3b82f6"><?= $stats['customers'] ?></span>
        </a>
        <a class="sb-item <?= $active_tab==='duesoon'?'active':'' ?>" onclick="showTab('duesoon')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            Service Due
            <?php if(!empty($due_customers)): ?><span class="sb-badge" style="background:#f59e0b"><?= count($due_customers) ?></span><?php endif; ?>
        </a>

        <div class="sb-label" style="margin-top:16px">Billing</div>
        <a class="sb-item" href="<?= $base ?>/admin/invoices/">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Invoices &amp; Quotes
        </a>
        <a class="sb-item" href="<?= $base ?>/admin/statements/">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v4H3zM3 9h18v4H3zM3 15h18v4H3z"/><circle cx="6" cy="5" r="0.8" fill="currentColor"/><circle cx="6" cy="11" r="0.8" fill="currentColor"/><circle cx="6" cy="17" r="0.8" fill="currentColor"/></svg>
            Statements
        </a>

        <div class="sb-label" style="margin-top:16px">Quick Links</div>
        <a class="sb-item" href="<?= $base ?>/" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            View Website
        </a>
        <a class="sb-item" href="<?= $base ?>/admin/logout.php">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </a>
    </div>
    <div class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
        <div>
            <div style="font-size:13px;font-weight:700;color:#f8fafc"><?= htmlspecialchars($admin_name) ?></div>
            <div style="font-size:11px;color:#64748b">Administrator</div>
        </div>
    </div>
</nav>

<div id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Main -->
<div id="main">
    <header class="top-bar">
        <button class="mob-toggle" onclick="openSidebar()" style="background:none;border:none;cursor:pointer;padding:8px;border-radius:8px;color:#64748b">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div style="font-size:16px;font-weight:700;color:#0f172a">Dashboard</div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
            <span style="font-size:12px;color:#64748b"><?= date('D, d M Y') ?></span>
        </div>
    </header>

    <!-- ═══ OVERVIEW ═══ -->
    <div class="panel <?= $active_tab==='overview'?'active':'' ?>" id="tab-overview">
        <div class="page-body">
            <div style="margin-bottom:24px">
                <h1 style="font-size:22px;font-weight:800;color:#0f172a">Welcome back, <?= htmlspecialchars(explode(' ',$admin_name)[0]) ?></h1>
                <p style="font-size:13px;color:#64748b;margin-top:4px">Here's what's happening with Wiyule Motors today</p>
            </div>

            <!-- ═══ NEW ALERTS BANNER (only when an event crossed a threshold this session) ═══ -->
            <?php if (!empty($new_alerts)): ?>
            <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1.5px solid #f59e0b;border-radius:14px;padding:16px 18px;margin-bottom:24px">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                    <span style="font-size:22px">🔔</span>
                    <div>
                        <div style="font-size:14px;font-weight:800;color:#92400e">New Notifications (<?= count($new_alerts) ?>)</div>
                        <div style="font-size:12px;color:#92400e;opacity:0.85">Tap to send yourself a WhatsApp reminder so you don't lose track.</div>
                    </div>
                </div>
                <?php foreach ($new_alerts as $na): ?>
                <div style="background:#fff;border-radius:10px;padding:11px 14px;margin-top:8px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                    <div style="flex:1;min-width:200px">
                        <div style="font-size:13px;font-weight:700;color:#0f172a"><?= htmlspecialchars($na['title']) ?></div>
                        <div style="font-size:12px;color:#64748b;margin-top:2px"><?= htmlspecialchars($na['detail']) ?></div>
                    </div>
                    <a href="<?= htmlspecialchars($na['wa_link']) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:#22c55e;color:#fff;padding:7px 14px;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
                        Send to my WhatsApp
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:28px">
                <div class="stat-card red">
                    <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">Total Bookings</div>
                    <div style="font-size:32px;font-weight:800;color:#0f172a"><?= $stats['total'] ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px"><?= $stats['today'] ?> today</div>
                </div>
                <div class="stat-card amber">
                    <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">Pending</div>
                    <div style="font-size:32px;font-weight:800;color:#d97706"><?= $stats['pending'] ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px">Needs attention</div>
                </div>
                <div class="stat-card green">
                    <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">Confirmed</div>
                    <div style="font-size:32px;font-weight:800;color:#15803d"><?= $stats['confirmed'] ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px">Scheduled</div>
                </div>
                <div class="stat-card blue">
                    <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:8px">Customers</div>
                    <div style="font-size:32px;font-weight:800;color:#2563eb"><?= $stats['customers'] ?></div>
                    <div style="font-size:12px;color:#94a3b8;margin-top:4px">Registered users</div>
                </div>
            </div>

            <!-- ═══ RECENT BOOKINGS (with quick filter) ═══ -->
            <div class="table-card" style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06);overflow:hidden;margin-bottom:24px">
                <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div style="font-size:15px;font-weight:700;color:#0f172a">Recent Bookings</div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <div class="rb-filter">
                            <button class="rb-chip active" onclick="filterRecent('all',this)">All</button>
                            <button class="rb-chip" onclick="filterRecent('pending',this)">Pending</button>
                            <button class="rb-chip" onclick="filterRecent('confirmed',this)">Confirmed</button>
                            <button class="rb-chip" onclick="filterRecent('completed',this)">Completed</button>
                        </div>
                        <a onclick="showTab('bookings')" style="font-size:13px;font-weight:600;color:var(--red);cursor:pointer;white-space:nowrap">View all →</a>
                    </div>
                </div>
                <div style="overflow-x:auto" class="table-scroll">
                    <table class="data-table cards-mobile">
                        <thead><tr><th>Customer</th><th>Service</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="recentBody">
                            <?php foreach(array_slice($bookings, 0, 10) as $b): ?>
                            <tr data-status="<?= $b['status'] ?>" data-bid="<?= (int)$b['id'] ?>">
                                <td data-label="Customer">
                                    <div style="font-weight:600;color:#0f172a"><?= htmlspecialchars($b['full_name']) ?></div>
                                    <div style="font-size:12px;color:#94a3b8"><?= wm_contact_links($b['phone']) ?></div>
                                    <?php if($b['email']): ?><div style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($b['email']) ?></div><?php endif; ?>
                                </td>
                                <td data-label="Service"><?= htmlspecialchars($b['services']) ?></td>
                                <td data-label="Date" style="white-space:nowrap"><?= date('d M Y', strtotime($b['booking_date'])) ?><br><span style="font-size:12px;color:#94a3b8"><?= $b['booking_time'] ?></span></td>
                                <td data-label="Status"><span class="status-pill status-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                                <td data-label="" class="cell-actions">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <button class="action-btn" onclick="viewBooking(<?= $b['id'] ?>)">View</button>
                                        <?php if($b['status']==='pending'): ?>
                                            <button class="action-btn confirm" onclick="updateStatus(<?= $b['id'] ?>,'confirmed')">Confirm</button>
                                        <?php elseif($b['status']==='confirmed'): ?>
                                            <button class="action-btn complete" onclick="updateStatus(<?= $b['id'] ?>,'completed')">Complete</button>
                                        <?php elseif($b['status']==='completed'): ?>
                                            <a class="action-btn invoice-btn" href="<?= $base ?>/admin/invoices/edit.php?booking_id=<?= $b['id'] ?>" title="Create invoice">🧾 Invoice</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($bookings)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:#94a3b8">No bookings yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="recentEmpty" style="display:none;text-align:center;padding:30px;color:#94a3b8;font-size:13px">No bookings with this status.</div>
            </div>

            <!-- ═══ BOOKING SOURCES — current month ═══ -->
            <div class="source-widget">
                <div class="source-widget-head">
                    <div>
                        <div class="source-widget-title">Where bookings came from</div>
                        <div class="source-widget-sub"><?= date('F Y') ?> · <?= $month_sources_total ?> booking<?= $month_sources_total === 1 ? '' : 's' ?> this month</div>
                    </div>
                </div>

                <?php if ($month_sources_total === 0): ?>
                    <div class="source-empty">
                        No bookings yet this month — your source breakdown will appear here as customers book.
                    </div>
                <?php elseif (empty($month_sources) && $month_unknown_count > 0): ?>
                    <div class="source-empty">
                        <strong><?= $month_unknown_count ?></strong> booking<?= $month_unknown_count === 1 ? '' : 's' ?> this month, but none have source data yet.<br>
                        <span style="font-size:11.5px;color:#94a3b8">Source tracking started recently — new bookings via the website will start showing here.</span>
                    </div>
                <?php else: ?>
                    <div class="source-bars">
                        <?php foreach ($month_sources as $src => $cnt):
                            $pct = $month_sources_max > 0 ? ($cnt / $month_sources_max) * 100 : 0;
                            $share_pct = $month_sources_total > 0 ? round(($cnt / $month_sources_total) * 100) : 0;
                        ?>
                        <div class="source-row">
                            <div class="source-row-label"><?= htmlspecialchars($src) ?></div>
                            <div class="source-row-bar-wrap">
                                <div class="source-row-bar" style="width:<?= number_format($pct, 1) ?>%"></div>
                            </div>
                            <div class="source-row-count"><?= $cnt ?> <span class="source-row-share"><?= $share_pct ?>%</span></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($month_unknown_count > 0): ?>
                        <div class="source-unknown-note">
                            + <?= $month_unknown_count ?> booking<?= $month_unknown_count === 1 ? '' : 's' ?> with no source data (older bookings or customers who skipped the question)
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ═══ ALERTS (only shown when there's something to flag) ═══ -->
            <?php if (!empty($alerts)): ?>
            <div style="margin-bottom:24px">
                <div style="font-size:13px;font-weight:700;color:#0f172a;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#f59e0b"><path d="M13.7 3.5a2 2 0 0 0-3.4 0l-8 14A2 2 0 0 0 4 20.5h16a2 2 0 0 0 1.7-3l-8-14zM12 17a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4zm1-5a1 1 0 0 1-2 0V9a1 1 0 0 1 2 0z"/></svg>
                    Alerts (<?= count($alerts) ?>)
                </div>
                <?php foreach ($alerts as $a):
                    $bg = $a['level']==='warn' ? '#fef3c7' : '#dbeafe';
                    $bd = $a['level']==='warn' ? '#fcd34d' : '#93c5fd';
                    $cl = $a['level']==='warn' ? '#92400e' : '#1e40af';
                ?>
                <div style="background:<?= $bg ?>;border:1.5px solid <?= $bd ?>;border-radius:12px;padding:14px 16px;margin-bottom:8px;display:flex;align-items:flex-start;gap:12px">
                    <div style="font-size:18px"><?= $a['level']==='warn' ? '⚠️' : 'ℹ️' ?></div>
                    <div>
                        <div style="font-size:14px;font-weight:700;color:<?= $cl ?>"><?= htmlspecialchars($a['title']) ?></div>
                        <div style="font-size:13px;color:<?= $cl ?>;opacity:0.85;margin-top:2px"><?= htmlspecialchars($a['detail']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ═══ SECURITY & ACTIVITY (last 7 days) ═══ -->
            <div style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06);padding:22px;margin-bottom:24px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#0f172a">Security &amp; Activity</div>
                        <div style="font-size:12px;color:#94a3b8;margin-top:2px">Last 7 days · refreshed live</div>
                    </div>
                    <span style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;color:#15803d;padding:6px 12px;border-radius:99px;font-size:12px;font-weight:700">
                        <span style="width:8px;height:8px;background:#22c55e;border-radius:50%"></span> Live
                    </span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px">
                    <!-- New signups -->
                    <div style="background:#f8fafc;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em">New Signups</div>
                        <div style="font-size:26px;font-weight:800;color:#0f172a;margin-top:6px"><?= $sec['signups_week'] ?></div>
                        <div style="font-size:11px;color:#94a3b8;margin-top:2px">last 7 days</div>
                    </div>

                    <!-- Verified rate -->
                    <div style="background:#f0fdf4;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:0.05em">Verified</div>
                        <div style="font-size:26px;font-weight:800;color:#15803d;margin-top:6px"><?= $sec['verified_week'] ?>/<?= $sec['signups_week'] ?></div>
                        <div style="font-size:11px;color:#15803d;opacity:0.7;margin-top:2px"><?= $sec['verify_rate'] ?>% verification rate</div>
                    </div>

                    <!-- Pending verification -->
                    <div style="background:<?= $sec['pending_verify'] >= 10 ? '#fef3c7' : '#fffbeb' ?>;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.05em">Unverified</div>
                        <div style="font-size:26px;font-weight:800;color:#92400e;margin-top:6px"><?= $sec['pending_verify'] ?></div>
                        <div style="font-size:11px;color:#92400e;opacity:0.7;margin-top:2px">never confirmed email</div>
                    </div>

                    <!-- Failed logins -->
                    <div style="background:<?= $sec['failed_logins_24h'] >= 20 ? '#fee2e2' : '#f8fafc' ?>;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.05em">Failed Logins</div>
                        <div style="font-size:26px;font-weight:800;color:<?= $sec['failed_logins_24h'] >= 20 ? '#991b1b' : '#0f172a' ?>;margin-top:6px"><?= $sec['failed_logins_24h'] ?></div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px"><?= $sec['unique_attackers'] ?> IP(s), last 24h</div>
                    </div>

                    <!-- Currently locked -->
                    <div style="background:<?= $sec['locked_now'] > 0 ? '#fee2e2' : '#f8fafc' ?>;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.05em">Locked Now</div>
                        <div style="font-size:26px;font-weight:800;color:<?= $sec['locked_now'] > 0 ? '#991b1b' : '#0f172a' ?>;margin-top:6px"><?= $sec['locked_now'] ?></div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px">15-min lockout active</div>
                    </div>

                    <!-- System status -->
                    <div style="background:#f0fdf4;border-radius:12px;padding:14px 16px">
                        <div style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:0.05em">System</div>
                        <div style="font-size:18px;font-weight:800;color:#15803d;margin-top:6px;display:flex;align-items:center;gap:6px">
                            <span style="font-size:22px">✓</span> Healthy
                        </div>
                        <div style="font-size:11px;color:#15803d;opacity:0.7;margin-top:2px">DB · sessions · throttle</div>
                    </div>
                </div>

                <?php if ($sec['newest_signup']): ?>
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div style="font-size:13px;color:#64748b">
                        Newest signup: <strong style="color:#0f172a"><?= htmlspecialchars($sec['newest_signup']['full_name']) ?></strong>
                        <span style="color:#94a3b8">· <?= date('d M, g:i A', strtotime($sec['newest_signup']['created_at'])) ?></span>
                    </div>
                    <a onclick="showTab('customers')" style="font-size:12px;font-weight:600;color:var(--red);cursor:pointer">View all customers →</a>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ═══ BOOKINGS ═══ -->
    <div class="panel <?= $active_tab==='bookings'?'active':'' ?>" id="tab-bookings">
        <div class="page-body">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                <div>
                    <h1 style="font-size:22px;font-weight:800;color:#0f172a">All Bookings</h1>
                    <p style="font-size:13px;color:#64748b;margin-top:4px"><?= count($bookings) ?> booking<?= count($bookings)!==1?'s':'' ?> found</p>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="tab" value="bookings">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
                    <input type="hidden" name="source" value="<?= htmlspecialchars($source_filter) ?>">
                    <div class="search-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, phone, service...">
                    </div>
                    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" title="From date" style="padding:9px 11px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:inherit;color:#334155">
                    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" title="To date" style="padding:9px 11px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-family:inherit;color:#334155">
                    <button type="submit" class="action-btn" style="padding:10px 16px">Apply</button>
                    <?php if($search!==''||$date_from!==''||$date_to!==''): ?><a href="?tab=bookings&status=<?= urlencode($filter) ?>&source=<?= urlencode($source_filter) ?>" class="action-btn" style="padding:10px 14px;text-decoration:none">Clear</a><?php endif; ?>
                    <a href="<?= $base ?>/admin/api/export.php?type=bookings&status=<?= urlencode($filter) ?>&source=<?= urlencode($source_filter) ?>&search=<?= urlencode($search) ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>" class="action-btn" style="padding:10px 14px;text-decoration:none" title="Export these bookings to CSV">⬇ CSV</a>
                </form>
            </div>

            <!-- Quick scheduled-work filters -->
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
                <span style="font-size:12px;font-weight:700;color:#94a3b8">Scheduled:</span>
                <a href="?tab=bookings&status=all&source=<?= urlencode($source_filter) ?>&from=<?= $today_str ?>&to=<?= $today_str ?>" class="filter-btn <?= ($date_from===$today_str && $date_to===$today_str)?'active':'' ?>">📅 Today (<?= $due_today ?>)</a>
                <a href="?tab=bookings&status=all&source=<?= urlencode($source_filter) ?>&from=<?= $today_str ?>&to=<?= $week_str ?>" class="filter-btn <?= ($date_from===$today_str && $date_to===$week_str)?'active':'' ?>">This week (<?= $due_week ?>)</a>
            </div>

            <!-- Filters: status pills (preserves source filter) -->
            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
                <?php foreach(['all'=>'All ('.($stats['total']-$stats['cancelled']).')','pending'=>'Pending ('.$stats['pending'].')','confirmed'=>'Confirmed ('.$stats['confirmed'].')','completed'=>'Completed ('.$stats['completed'].')','cancelled'=>'Cancelled ('.$stats['cancelled'].')'] as $key=>$label): ?>
                <a href="?tab=bookings&status=<?= $key ?>&source=<?= urlencode($source_filter) ?>&search=<?= urlencode($search) ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>" class="filter-btn <?= $filter===$key?'active':'' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Filters: source pills (only shows sources with bookings) -->
            <?php
            // Reverse-sort sources by count for visual prominence
            $total_sourced = array_sum($source_counts);
            if ($total_sourced > 0):
                // Sources that have at least 1 booking
                $sources_with_data = array_filter($source_counts, fn($c) => $c > 0);
            ?>
            <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;align-items:center;font-size:12px;color:#94a3b8">
                <span style="font-weight:600;margin-right:2px">Source:</span>
                <a href="?tab=bookings&status=<?= urlencode($filter) ?>&source=all&search=<?= urlencode($search) ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>"
                   class="source-pill <?= $source_filter==='all'?'active':'' ?>">All</a>
                <?php foreach ($sources_with_data as $src_key => $src_count):
                    $label = ($src_key === 'unknown') ? 'Not asked' : $src_key;
                ?>
                <a href="?tab=bookings&status=<?= urlencode($filter) ?>&source=<?= urlencode($src_key) ?>&search=<?= urlencode($search) ?>&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>"
                   class="source-pill <?= $source_filter===$src_key?'active':'' ?>"><?= htmlspecialchars($label) ?> (<?= $src_count ?>)</a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Table -->
            <div style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06);overflow:hidden" class="table-card">
                <div style="overflow-x:auto" class="table-scroll">
                    <table class="data-table cards-mobile sortable">
                        <thead>
                            <tr><th data-nosort>#</th><th>Customer</th><th>Vehicle</th><th>Service</th><th>Date &amp; Time</th><th>Status</th><th data-nosort>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($bookings as $b): ?>
                            <tr id="row-<?= $b['id'] ?>" data-bid="<?= (int)$b['id'] ?>">
                                <td data-label="Ref" style="font-family:monospace;color:#94a3b8;font-size:12px">#<?= $b['id'] ?></td>
                                <td data-label="Customer">
                                    <div style="font-weight:600;color:#0f172a"><?= htmlspecialchars($b['full_name']) ?></div>
                                    <div style="font-size:12px;color:#94a3b8"><?= wm_contact_links($b['phone']) ?></div>
                                    <?php if($b['email']): ?><div style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($b['email']) ?></div><?php endif; ?>
                                </td>
                                <td data-label="Vehicle">
                                    <div style="font-weight:600;color:#0f172a"><?= htmlspecialchars($b['vehicle_make'].' '.$b['vehicle_model']) ?></div>
                                    <div style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($b['vehicle_year']) ?> <?= $b['registration'] ? '· '.htmlspecialchars($b['registration']) : '' ?></div>
                                </td>
                                <td data-label="Service" style="max-width:180px"><?= htmlspecialchars($b['services']) ?></td>
                                <td data-label="When" style="white-space:nowrap">
                                    <?= date('d M Y', strtotime($b['booking_date'])) ?><br>
                                    <span style="font-size:12px;color:#94a3b8"><?= htmlspecialchars($b['booking_time']) ?></span>
                                    <?php if (!empty($b['source'])): ?>
                                        <div style="margin-top:4px;font-size:11px;color:#64748b;font-weight:500">via <?= htmlspecialchars($b['source']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status"><span class="status-pill status-<?= $b['status'] ?>" id="status-<?= $b['id'] ?>"><?= ucfirst($b['status']) ?></span></td>
                                <td data-label="" class="cell-actions">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                                        <button class="action-btn" onclick="viewBooking(<?= $b['id'] ?>)">View</button>
                                        <?php if($b['status']==='pending'): ?>
                                            <button class="action-btn confirm" onclick="updateStatus(<?= $b['id'] ?>,'confirmed')">Confirm</button>
                                            <button class="action-btn cancel" onclick="updateStatus(<?= $b['id'] ?>,'cancelled')">Cancel</button>
                                        <?php elseif($b['status']==='confirmed'): ?>
                                            <button class="action-btn complete" onclick="updateStatus(<?= $b['id'] ?>,'completed')">Complete</button>
                                            <button class="action-btn cancel" onclick="updateStatus(<?= $b['id'] ?>,'cancelled')">Cancel</button>
                                        <?php elseif($b['status']==='completed'): ?>
                                            <a class="action-btn invoice-btn" href="<?= $base ?>/admin/invoices/edit.php?booking_id=<?= $b['id'] ?>" title="Create invoice from this booking">🧾 Invoice</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($bookings)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:48px;color:#94a3b8;font-size:14px">No bookings match your filter</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ CUSTOMERS ═══ -->
    <div class="panel <?= $active_tab==='customers'?'active':'' ?>" id="tab-customers">
        <div class="page-body">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px">
                <div>
                    <h1 style="font-size:22px;font-weight:800;color:#0f172a">Customers</h1>
                    <p style="font-size:13px;color:#64748b;margin-top:4px"><?= count($customers) ?> <?= $cust_search!=='' ? 'match'.(count($customers)===1?'':'es') : 'registered user'.(count($customers)!==1?'s':'') ?> · <span style="color:#dc2626;font-weight:600">click a row to view their vehicles &amp; history</span></p>
                </div>
                <form method="GET" style="display:flex;gap:8px;align-items:center">
                    <input type="hidden" name="tab" value="customers">
                    <div class="search-box">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="csearch" value="<?= htmlspecialchars($cust_search) ?>" placeholder="Search name, email, phone...">
                    </div>
                    <button type="submit" class="action-btn" style="padding:10px 16px">Search</button>
                    <?php if ($cust_search!==''): ?><a href="<?= $base ?>/admin/?tab=customers" class="action-btn" style="padding:10px 14px;text-decoration:none">Clear</a><?php endif; ?>
                    <a href="<?= $base ?>/admin/api/export.php?type=customers&csearch=<?= urlencode($cust_search) ?>" class="action-btn" style="padding:10px 14px;text-decoration:none" title="Export customers to CSV">⬇ CSV</a>
                </form>
            </div>
            <div class="table-card" style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06);overflow:hidden">
                <div style="overflow-x:auto" class="table-scroll">
                    <table class="data-table cards-mobile sortable">
                        <thead>
                            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Last Booking</th><th>Joined</th><th style="text-align:right" data-nosort>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($customers as $c):
                                $cphone = preg_replace('/\D/', '', $c['phone'] ?? '');
                                if ($cphone !== '' && substr($cphone,0,3) !== '265') $cphone = '265' . ltrim($cphone, '0');
                                $verified = !empty($c['email_verified']);
                                $cname_js = htmlspecialchars(addslashes($c['full_name']), ENT_QUOTES);
                            ?>
                            <tr onclick="viewCustomer(<?= (int)$c['id'] ?>)" style="cursor:pointer">
                                <td data-label="Name" style="font-weight:600;color:#0f172a">
                                    <?= htmlspecialchars($c['full_name']) ?>
                                    <?php if($verified): ?><span title="Email verified" style="color:#16a34a">✓</span><?php else: ?><span title="Email not verified" style="color:#f59e0b;font-size:11px">●</span><?php endif; ?>
                                </td>
                                <td data-label="Email" style="color:#64748b"><?= htmlspecialchars($c['email']) ?></td>
                                <td data-label="Phone" style="color:#64748b"><?= wm_contact_links($c['phone']) ?></td>
                                <td data-label="Bookings"><span style="background:#f1f5f9;padding:4px 10px;border-radius:99px;font-size:12px;font-weight:700"><?= $c['booking_count'] ?></span></td>
                                <td data-label="Last booking" style="color:#64748b;font-size:12px"><?= $c['last_booking'] ? date('d M Y', strtotime($c['last_booking'])) : '—' ?></td>
                                <td data-label="Joined" style="color:#94a3b8;font-size:12px"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                <td data-label="" class="cell-actions" onclick="event.stopPropagation()">
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                                        <button class="action-btn" onclick="viewCustomer(<?= (int)$c['id'] ?>)">View</button>
                                        <?php if($cphone !== ''): ?><a class="action-btn" style="text-decoration:none" target="_blank" rel="noopener" href="https://wa.me/<?= $cphone ?>">WhatsApp</a><?php endif; ?>
                                        <button class="action-btn cancel" onclick="deleteCustomer(<?= (int)$c['id'] ?>, '<?= $cname_js ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($customers)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:48px;color:#94a3b8">No customers yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Service Due panel -->
    <div class="panel <?= $active_tab==='duesoon'?'active':'' ?>" id="tab-duesoon">
        <div class="page-body">
            <div style="margin-bottom:20px">
                <h1 style="font-size:22px;font-weight:800;color:#0f172a">Service Due Reminders</h1>
                <p style="font-size:13px;color:#64748b;margin-top:4px"><?= count($due_customers) ?> vehicle<?= count($due_customers)!==1?'s':'' ?> due for service (80+ days since last visit)</p>
            </div>
            <div style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06);overflow:hidden">
                <div style="overflow-x:auto">
                    <table class="data-table cards-mobile">
                        <thead>
                            <tr><th>Customer</th><th>Vehicle</th><th>Last Service</th><th>Status</th><th>Send Reminder</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($due_customers as $dc):
                                $veh = trim($dc['make'].' '.$dc['model']);
                                $reg = $dc['registration'] ? ' ('.$dc['registration'].')' : '';
                                if ($dc['never']) {
                                    $last_txt = 'Never serviced';
                                    $status_txt = 'New — invite';
                                    $status_color = '#3b82f6';
                                } else {
                                    $last_txt = date('d M Y', strtotime($dc['last_service']));
                                    $status_txt = $dc['days_since'].' days ago';
                                    $status_color = $dc['days_since'] >= 120 ? '#dc2626' : '#f59e0b';
                                }
                                // Pre-written WhatsApp message
                                $msg = "Hi ".$dc['full_name'].", this is Wiyule Motors. ";
                                if ($dc['never']) {
                                    $msg .= "We'd love to welcome your ".$veh." for its first service with us. Book anytime — we'll take great care of it!";
                                } else {
                                    $msg .= "It's been a while since your ".$veh." was last serviced (".$last_txt."). To keep it running smoothly, we recommend booking a service soon. Reply to book!";
                                }
                                $phone = preg_replace('/[^0-9]/', '', $dc['phone']);
                                // Malawi numbers: ensure country code
                                if (substr($phone,0,3) !== '265') {
                                    $phone = '265' . ltrim($phone, '0');
                                }
                                $wa = 'https://wa.me/'.$phone.'?text='.urlencode($msg);
                            ?>
                            <tr>
                                <td data-label="Customer">
                                    <div style="font-weight:700;color:#0f172a"><?= htmlspecialchars($dc['full_name']) ?></div>
                                    <div style="font-size:12px;color:#94a3b8"><?= $dc['phone'] ? wm_contact_links($dc['phone']) : 'No phone' ?></div>
                                </td>
                                <td data-label="Vehicle"><?= htmlspecialchars($veh.$reg) ?></td>
                                <td data-label="Last service"><?= htmlspecialchars($last_txt) ?></td>
                                <td data-label="Status"><span style="display:inline-block;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700;color:#fff;background:<?= $status_color ?>"><?= htmlspecialchars($status_txt) ?></span></td>
                                <td data-label="Reminder">
                                    <?php if($dc['phone']): ?>
                                    <a href="<?= htmlspecialchars($wa) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;background:#22c55e;color:#fff;padding:7px 14px;border-radius:9px;font-size:12px;font-weight:700;text-decoration:none">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
                                        WhatsApp
                                    </a>
                                    <?php else: ?>
                                    <span style="font-size:12px;color:#cbd5e1">No phone</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($due_customers)): ?>
                            <tr><td colspan="5" style="text-align:center;padding:48px;color:#94a3b8">🎉 No customers due for service right now — everyone's up to date!</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Detail Modal -->
<div class="detail-modal" id="detailModal" onclick="if(event.target===this)closeDetail()">
    <div class="detail-box" id="detailContent"></div>
</div>

<!-- Customer Detail Modal -->
<div class="detail-modal" id="customerModal" onclick="if(event.target===this)closeCustomer()">
    <div class="detail-box" id="customerContent"></div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// Booking data for detail view
var bookingData = <?= json_encode($bookings) ?>;
var lastMileageByUser = <?= json_encode($last_mileage_by_user) ?>;
var vehicleMileageByUser = <?= json_encode($vehicle_mileage_by_user) ?>;
var customerDetails = <?= json_encode($customer_details) ?>;
var customerNames = <?= json_encode(array_column($customers, 'full_name', 'id')) ?>;
var customerPhones = <?= json_encode(array_column($customers, 'phone', 'id')) ?>;
var customerEmails = <?= json_encode(array_column($customers, 'email', 'id')) ?>;

function viewCustomer(id) {
    var d = customerDetails[id];
    var name = customerNames[id] || 'Customer';
    var phone = customerPhones[id] || '';
    var email = customerEmails[id] || '';
    if (!d) return;

    function scoreColor(s) { return s >= 85 ? '#16a34a' : (s >= 70 ? '#f59e0b' : '#dc2626'); }
    function scoreLabel(s) { return s >= 85 ? 'Good' : (s >= 70 ? 'Due soon' : 'Needs service'); }

    var html = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">';
    html += '<div><div style="font-size:20px;font-weight:800;color:#0f172a">' + name + '</div>';
    html += '<div style="font-size:13px;color:#64748b;margin-top:2px">' + (phone || 'No phone') + '</div></div>';
    // Total spent chip
    var spent = (d.total_spent != null) ? d.total_spent : 0;
    var spentStr = 'MWK ' + Number(spent).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    html += '<button onclick="closeCustomer()" style="background:#f1f5f9;border:none;width:34px;height:34px;border-radius:9px;cursor:pointer;font-size:18px;color:#64748b">&times;</button></div>';

    // Total spent + quick contact/create actions
    html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:18px">';
    html += '<span style="display:inline-flex;align-items:center;gap:6px;background:#f0fdf4;color:#15803d;padding:8px 14px;border-radius:10px;font-size:13px;font-weight:700">💰 Total spent: ' + spentStr + '</span>';
    if (phone) { html += '<a href="tel:' + esc(phone) + '" class="action-btn" style="text-decoration:none">📞 Call</a>'; }
    html += '</div>';

    // Vehicles
    html += '<div style="font-size:13px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px">Vehicles (' + d.vehicles.length + ')</div>';
    if (d.vehicles.length === 0) {
        html += '<div style="color:#94a3b8;font-size:13px;padding:12px 0;margin-bottom:8px">No vehicles registered.</div>';
    } else {
        d.vehicles.forEach(function(v) {
            html += '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:10px">';
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">';
            html += '<div style="font-weight:700;color:#0f172a">' + v.name + ' <span style="color:#94a3b8;font-weight:500;font-size:12px">' + v.reg + '</span></div>';
            html += '<span style="background:' + scoreColor(v.score) + ';color:#fff;padding:3px 10px;border-radius:99px;font-size:11px;font-weight:700">' + v.score + ' · ' + scoreLabel(v.score) + '</span></div>';
            html += '<div style="display:flex;gap:18px;font-size:12px;color:#64748b;flex-wrap:wrap">';
            html += '<span>Year: <b style="color:#334155">' + v.year + '</b></span>';
            html += '<span>Mileage: <b style="color:#334155">' + v.mileage + '</b></span>';
            html += '<span>Last service: <b style="color:#334155">' + v.last_service + '</b></span>';
            html += '<span>Interval used: <b style="color:#334155">' + v.interval_pct + '%</b></span></div>';
            html += '</div>';
        });
    }

    // Service history
    html += '<div style="font-size:13px;font-weight:800;color:#0f172a;text-transform:uppercase;letter-spacing:0.05em;margin:18px 0 10px">Service History</div>';
    if (d.history.length === 0) {
        html += '<div style="color:#94a3b8;font-size:13px;padding:12px 0">No bookings yet.</div>';
    } else {
        d.history.forEach(function(h) {
            var sc = h.status === 'completed' ? '#16a34a' : (h.status === 'confirmed' ? '#3b82f6' : (h.status === 'cancelled' ? '#dc2626' : '#f59e0b'));
            html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9">';
            html += '<div><div style="font-weight:600;color:#0f172a;font-size:13px">' + h.service + '</div>';
            html += '<div style="font-size:12px;color:#94a3b8">' + h.date + (h.mileage ? ' · ' + h.mileage : '') + '</div></div>';
            html += '<span style="color:' + sc + ';font-size:12px;font-weight:700;text-transform:capitalize">' + h.status + '</span></div>';
        });
    }

    // Quick actions: WhatsApp + create invoice for this customer
    html += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px">';
    if (phone) {
        var wp = phone.replace(/[^0-9]/g, '');
        if (wp.substring(0,3) !== '265') wp = '265' + wp.replace(/^0/, '');
        html += '<a href="https://wa.me/' + wp + '" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#22c55e;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none">Message on WhatsApp</a>';
    }
    var invUrl = <?= json_encode($base . '/admin/invoices/edit.php') ?> + '?new=1&cust_name=' + encodeURIComponent(name) + '&cust_phone=' + encodeURIComponent(phone) + '&cust_email=' + encodeURIComponent(email);
    html += '<a href="' + invUrl + '" style="display:inline-flex;align-items:center;gap:8px;background:#0f172a;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none">🧾 New invoice</a>';
    html += '</div>';

    document.getElementById('customerContent').innerHTML = html;
    document.getElementById('customerModal').classList.add('open');
}
function closeCustomer() {
    document.getElementById('customerModal').classList.remove('open');
}

async function deleteCustomer(id, name) {
    if (!confirm('Delete customer "' + name + '"?\n\nThis permanently removes their account and saved vehicles. Their past bookings are kept (just unlinked). This cannot be undone.')) return;
    const fd = new FormData(); fd.append('id', id);
    try {
        const r = await fetch(<?= json_encode($base . '/admin/api/delete-customer.php') ?>, { method:'POST', body:fd, credentials:'same-origin' });
        const j = await r.json();
        showToast(j.message || (j.success ? 'Deleted' : 'Failed'), j.success ? 'success' : 'error');
        if (j.success) setTimeout(() => location.reload(), 700);
    } catch (e) { showToast('Network error — try again', 'error'); }
}

function filterRecent(status, btn) {
    document.querySelectorAll('.rb-filter .rb-chip').forEach(function(c){ c.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    var shown = 0;
    document.querySelectorAll('#recentBody tr').forEach(function(tr){
        var s = tr.getAttribute('data-status');
        if (!s) return; // skip the "no bookings" placeholder row
        if (status === 'all' || s === status) { tr.style.display = ''; shown++; }
        else tr.style.display = 'none';
    });
    var empty = document.getElementById('recentEmpty');
    if (empty) empty.style.display = (shown === 0) ? 'block' : 'none';
}

function showTab(name) {
    document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('active')});
    document.getElementById('tab-'+name).classList.add('active');
    document.querySelectorAll('.sb-item').forEach(function(i){i.classList.remove('active')});
    var items = document.querySelectorAll('.sb-item');
    for(var i=0;i<items.length;i++){
        if(items[i].getAttribute('onclick') && items[i].getAttribute('onclick').indexOf("'"+name+"'")!==-1) items[i].classList.add('active');
    }
    if(window.innerWidth<769) closeSidebar();
}

function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + (type||'success');
    setTimeout(function(){t.className='toast'},3500);
}

function updateStatus(id, status) {
    var label = status.charAt(0).toUpperCase()+status.slice(1);

    // When completing a booking, require service mileage
    var service_mileage = null;
    if (status === 'completed') {
        // Find this booking's customer + their known mileages for sanity checks
        var bk = null;
        for (var i=0;i<bookingData.length;i++){ if(bookingData[i].id==id){bk=bookingData[i];break;} }
        var uid = bk ? bk.user_id : null;
        var lastKm = uid && lastMileageByUser[uid] ? parseInt(lastMileageByUser[uid]) : null;
        var curVehKm = uid && vehicleMileageByUser[uid] ? parseInt(vehicleMileageByUser[uid]) : null;

        var hint = '';
        if (lastKm) hint += '\nLast recorded service: ' + lastKm.toLocaleString() + ' km';
        if (curVehKm) hint += '\nCustomer\'s vehicle shows: ' + curVehKm.toLocaleString() + ' km';

        var miles = prompt(
            'Marking booking #' + id + ' as Complete.\n\n' +
            'What was the vehicle\'s mileage at time of service? (in km)' +
            hint + '\n\n' +
            'Example: 47500'
        );
        if (miles === null) return; // cancelled
        miles = miles.trim();
        if (miles === '' || isNaN(miles) || parseInt(miles) <= 0) {
            showToast('Please enter a valid mileage number', 'error');
            return;
        }
        var m = parseInt(miles);
        if (m > 9999999) {
            showToast('Mileage looks too high. Please check.', 'error');
            return;
        }

        // ── Sanity check 1: lower than last recorded service (impossible) ──
        if (lastKm && m < lastKm) {
            if (!confirm(
                '⚠️ WARNING: Mileage went DOWN.\n\n' +
                'You entered ' + m.toLocaleString() + ' km, but the last recorded service was ' +
                lastKm.toLocaleString() + ' km.\n\n' +
                'A car\'s mileage cannot decrease. This is likely a typo.\n\n' +
                'Click Cancel to fix it, or OK only if you\'re certain.'
            )) return;
        }
        // ── Sanity check 2: unrealistic jump (> 50,000 km since last service) ──
        else if (lastKm && (m - lastKm) > 50000) {
            if (!confirm(
                '⚠️ WARNING: Very large jump.\n\n' +
                'That\'s ' + (m - lastKm).toLocaleString() + ' km since the last service (' +
                lastKm.toLocaleString() + ' km).\n\n' +
                'That\'s unusually high — please double-check for a typo.\n\n' +
                'Click Cancel to fix it, or OK if correct.'
            )) return;
        }
        // ── Sanity check 3: far below the customer's own vehicle reading ──
        if (curVehKm && m < curVehKm - 1000) {
            if (!confirm(
                '⚠️ NOTE: Below the customer\'s recorded mileage.\n\n' +
                'You entered ' + m.toLocaleString() + ' km, but their vehicle profile shows ' +
                curVehKm.toLocaleString() + ' km.\n\n' +
                'Click Cancel to fix it, or OK if correct.'
            )) return;
        }

        service_mileage = m;
    } else {
        if(!confirm('Mark booking #'+id+' as '+label+'?')) return;
    }

    var fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);
    if (service_mileage !== null) {
        fd.append('service_mileage', service_mileage);
    }

    fetch('<?= $base ?>/admin/api/update-booking.php', {method:'POST', body:fd})
        .then(function(r){return r.json()})
        .then(function(data){
            if(data.success) {
                var msg = 'Booking #'+id+' marked as '+label;
                if (service_mileage !== null) {
                    msg += ' at ' + service_mileage.toLocaleString() + ' km';
                }
                showToast(msg, 'success');
                setTimeout(function(){location.reload()},1200);
            } else {
                showToast(data.message||'Failed to update','error');
            }
        })
        .catch(function(){showToast('Connection error','error')});
}

function viewBooking(id) {
    var b = null;
    for(var i=0;i<bookingData.length;i++){if(bookingData[i].id==id){b=bookingData[i];break;}}
    if(!b) return;

    var dateStr = new Date(b.booking_date+'T00:00:00').toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

    document.getElementById('detailContent').innerHTML =
        '<div style="background:linear-gradient(135deg,#0f172a,#1e293b);padding:24px;border-radius:20px 20px 0 0;color:#fff">'+
            '<div style="display:flex;justify-content:space-between;align-items:center">'+
                '<div><div style="font-size:10px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px">Booking #'+b.id+'</div>'+
                '<div style="font-size:20px;font-weight:800">'+esc(b.full_name)+'</div></div>'+
                '<button onclick="closeDetail()" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:24px">&times;</button>'+
            '</div>'+
        '</div>'+
        '<div style="padding:24px">'+
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Phone</div><div style="font-weight:600">'+esc(b.phone)+'</div></div>'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Email</div><div style="font-weight:600">'+(b.email?esc(b.email):'—')+'</div></div>'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Vehicle</div><div style="font-weight:600">'+esc(b.vehicle_year)+' '+esc(b.vehicle_make)+' '+esc(b.vehicle_model)+'</div></div>'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Registration</div><div style="font-weight:600">'+(b.registration?esc(b.registration):'—')+'</div></div>'+
            '</div>'+
            '<div style="border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:16px">'+
                '<div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Services</div>'+
                '<div style="font-weight:600">'+esc(b.services)+'</div>'+
            '</div>'+
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Date</div><div style="font-weight:600">'+esc(dateStr)+'</div></div>'+
                '<div><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Time</div><div style="font-weight:600">'+esc(b.booking_time)+'</div></div>'+
            '</div>'+
            (b.notes?'<div style="border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:16px"><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:4px">Notes</div><div style="font-style:italic;color:#64748b">'+esc(b.notes)+'</div></div>':'')+
            ((b.photos&&b.photos.length)?'<div style="border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:16px"><div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:8px">Customer photos ('+b.photos.length+')</div><div style="display:flex;flex-wrap:wrap;gap:8px">'+b.photos.map(function(u){return '<a href="'+esc(u)+'" target="_blank" rel="noopener" style="display:block;width:78px;height:78px;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0"><img src="'+esc(u)+'" style="width:100%;height:100%;object-fit:cover" loading="lazy"></a>';}).join('')+'</div></div>':'')+
            '<div style="border-top:1px solid #f1f5f9;padding-top:16px;display:flex;gap:8px;flex-wrap:wrap">'+
                (b.status==='pending'?'<button class="action-btn confirm" onclick="updateStatus('+b.id+',\'confirmed\');closeDetail()">✓ Confirm</button><button class="action-btn cancel" onclick="updateStatus('+b.id+',\'cancelled\');closeDetail()">✕ Cancel</button>':'')+
                (b.status==='confirmed'?'<button class="action-btn complete" onclick="updateStatus('+b.id+',\'completed\');closeDetail()">✓ Mark Complete</button>':'')+
                '<button class="action-btn" onclick="openMessagePicker('+b.id+')" style="border-color:#16a34a;color:#16a34a">💬 Message Customer</button>'+
                '<a href="tel:'+esc(b.phone)+'" class="action-btn" style="text-decoration:none">📞 Call</a>'+
            '</div>'+
        '</div>';
    document.getElementById('detailModal').classList.add('open');
}

// ── WhatsApp Message Templates ─────────────────────────────────
function openMessagePicker(id) {
    var b = null;
    for(var i=0;i<bookingData.length;i++){if(bookingData[i].id==id){b=bookingData[i];break;}}
    if(!b) return;

    var name = (b.full_name || '').split(' ')[0] || 'there';
    var vehicle = ((b.vehicle_year||'') + ' ' + (b.vehicle_make||'') + ' ' + (b.vehicle_model||'')).trim();
    var service = b.services || 'your service';
    var phone = b.phone.replace(/[^0-9]/g,'');
    // Ensure Malawi country code
    if (phone.indexOf('265') !== 0) {
        if (phone.indexOf('0') === 0) phone = '265' + phone.substring(1);
        else phone = '265' + phone;
    }

    // Pre-written templates
    var templates = {
        'confirm': 'Hi ' + name + '! 👋 This is Wiyule Motors confirming your booking for ' + service + (vehicle ? ' on your ' + vehicle : '') + '. We look forward to seeing you. Reply here if you have any questions!',
        'ready': 'Hi ' + name + '! 🚗 Good news — your ' + (vehicle || 'vehicle') + ' is ready for collection at Wiyule Motors. Thank you for choosing us!',
        'followup': 'Hi ' + name + '! 👋 Thank you for visiting Wiyule Motors. How is your ' + (vehicle || 'vehicle') + ' running? We\'d love your feedback, and we\'re here whenever you need us.',
        'reminder': 'Hi ' + name + '! 🔧 It\'s been a while since your last service at Wiyule Motors. Your ' + (vehicle || 'vehicle') + ' may be due for a check-up. Book anytime — reply here or call us!',
        'promo': 'Hi ' + name + '! 🎉 Special offer from Wiyule Motors: book any service this week and get 10% off. Limited time — reply here to book your slot!',
        'blank': ''
    };

    var html =
        '<div style="background:linear-gradient(135deg,#16a34a,#15803d);padding:20px 24px;border-radius:20px 20px 0 0;color:#fff">'+
            '<div style="display:flex;justify-content:space-between;align-items:center">'+
                '<div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;opacity:0.9;margin-bottom:4px">WhatsApp Message</div>'+
                '<div style="font-size:18px;font-weight:800">'+esc(b.full_name)+'</div></div>'+
                '<button onclick="closeDetail()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:24px">&times;</button>'+
            '</div>'+
        '</div>'+
        '<div style="padding:24px">'+
            '<p style="font-size:13px;color:#64748b;margin-bottom:16px">Pick a message template. It opens WhatsApp with the text pre-filled — you can edit before sending.</p>'+
            '<div style="display:flex;flex-direction:column;gap:8px">'+
                msgBtn('✅ Confirm Booking', templates.confirm, phone)+
                msgBtn('🚗 Vehicle Ready for Collection', templates.ready, phone)+
                msgBtn('💬 Post-Service Follow-up', templates.followup, phone)+
                msgBtn('🔧 Service Due Reminder', templates.reminder, phone)+
                msgBtn('🎉 Send 10% Promo', templates.promo, phone)+
                msgBtn('✏️ Blank Chat (write my own)', templates.blank, phone)+
            '</div>'+
        '</div>';

    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailModal').classList.add('open');
}

function msgBtn(label, text, phone) {
    var url = 'https://wa.me/' + phone + (text ? '?text=' + encodeURIComponent(text) : '');
    return '<a href="'+url+'" target="_blank" rel="noopener" '+
           'style="display:flex;align-items:center;gap:10px;padding:14px 16px;border:1.5px solid #e5e7eb;border-radius:12px;'+
           'text-decoration:none;color:#0f172a;font-weight:600;font-size:14px;transition:all 0.15s" '+
           'onmouseover="this.style.borderColor=\'#16a34a\';this.style.background=\'#f0fdf4\'" '+
           'onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.background=\'#fff\'">'+
           label+'</a>';
}

function closeDetail(){document.getElementById('detailModal').classList.remove('open');}
function esc(s){if(!s)return'';var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDetail()});

// ── Flash bookings that arrived since this browser last viewed the dashboard ──
(function(){
    try {
        var key = 'wm_lastSeenBooking';
        var seen = parseInt(sessionStorage.getItem(key) || '0', 10) || 0;
        var maxId = seen;
        var fresh = [];
        document.querySelectorAll('tr[data-bid]').forEach(function(tr){
            var bid = parseInt(tr.getAttribute('data-bid'), 10) || 0;
            if (bid > maxId) maxId = bid;
            if (seen > 0 && bid > seen) fresh.push(tr);
        });
        fresh.forEach(function(tr){ tr.classList.add('wm-flash'); });
        sessionStorage.setItem(key, String(maxId));
    } catch (e) {}
})();

// ── Click-to-sort tables (table.sortable) ──
(function(){
    document.querySelectorAll('table.sortable thead th').forEach(function(th){
        if (th.hasAttribute('data-nosort')) return;
        th.style.cursor = 'pointer';
        th.title = 'Click to sort';
        th.addEventListener('click', function(){
            var table = th.closest('table');
            var ths = Array.prototype.slice.call(th.parentNode.children);
            var idx = ths.indexOf(th);
            var tbody = table.querySelector('tbody');
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function(r){ return r.querySelectorAll('td').length > idx; });
            var asc = th.getAttribute('data-dir') !== 'asc';
            ths.forEach(function(h){ h.removeAttribute('data-dir'); });
            th.setAttribute('data-dir', asc ? 'asc' : 'desc');
            rows.sort(function(a,b){
                var av = (a.children[idx] ? a.children[idx].innerText : '').trim();
                var bv = (b.children[idx] ? b.children[idx].innerText : '').trim();
                var pureNum = function(s){ return s !== '' && s.replace(/[0-9.,\-\s]/g,'') === ''; };
                if (pureNum(av) && pureNum(bv)) {
                    return asc ? (parseFloat(av.replace(/[^0-9.\-]/g,''))||0) - (parseFloat(bv.replace(/[^0-9.\-]/g,''))||0)
                               : (parseFloat(bv.replace(/[^0-9.\-]/g,''))||0) - (parseFloat(av.replace(/[^0-9.\-]/g,''))||0);
                }
                return asc ? av.toLowerCase().localeCompare(bv.toLowerCase()) : bv.toLowerCase().localeCompare(av.toLowerCase());
            });
            rows.forEach(function(r){ tbody.appendChild(r); });
        });
    });
})();

// ── Live dashboard heartbeat: poll for new bookings / updated counts ──
(function(){
    const PULSE_URL = <?= json_encode($base . '/admin/api/pulse.php') ?>;
    let lastNewestId = <?= (int)($conn->query("SELECT COALESCE(MAX(id),0) m FROM bookings")->fetch_assoc()['m']) ?>;
    let firstPoll = true;

    function showNewBooking(b){
        const v = b && b.vehicle ? ' · ' + b.vehicle : '';
        if (typeof showToast === 'function') showToast('🔔 New booking from ' + (b ? b.name : 'a customer') + v, 'success');
        let bar = document.getElementById('newBookingBar');
        if (!bar){
            bar = document.createElement('div');
            bar.id = 'newBookingBar';
            bar.style.cssText = 'position:fixed;top:72px;left:50%;transform:translateX(-50%);z-index:300;background:#16a34a;color:#fff;padding:10px 16px;border-radius:12px;font-size:13px;font-weight:700;box-shadow:0 8px 30px rgba(0,0,0,0.25);cursor:pointer';
            bar.textContent = '🔔 New booking — tap to refresh';
            bar.onclick = function(){ location.reload(); };
            document.body.appendChild(bar);
        }
    }

    async function pulse(){
        try {
            const r = await fetch(PULSE_URL, { credentials:'same-origin' });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;
            const pb = document.getElementById('pendingBadge');
            if (pb){ pb.textContent = d.pending; pb.style.display = d.pending > 0 ? '' : 'none'; }
            if (d.newest_id > lastNewestId){
                if (!firstPoll) showNewBooking(d.newest);
                lastNewestId = d.newest_id;
            }
            firstPoll = false;
        } catch(e){ /* offline / transient — ignore, try again next tick */ }
    }
    setTimeout(pulse, 5000);
    setInterval(pulse, 30000);
})();
</script>
</body>
</html>
