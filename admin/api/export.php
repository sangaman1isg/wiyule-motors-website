<?php
// admin/api/export.php — CSV export of bookings or customers (admin only).
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
if (!isset($_SESSION['admin_id'])) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../../includes/db_connection.php';

function wm_csv_out(string $filename, array $header, array $rows): void {
    // CSV must be clean output — don't let PHP warnings leak into the file.
    @ini_set('display_errors', '0');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $header, ',', '"', '\\');
    foreach ($rows as $r) fputcsv($fp, $r, ',', '"', '\\');
    fclose($fp);
    exit;
}

$type = ($_GET['type'] ?? 'bookings') === 'customers' ? 'customers' : 'bookings';

if ($type === 'customers') {
    $csearch = trim($_GET['csearch'] ?? '');
    $sql = "SELECT u.full_name, u.email, u.phone, u.email_verified, u.created_at,
                   COUNT(b.id) AS booking_count, MAX(b.created_at) AS last_booking
            FROM users u LEFT JOIN bookings b ON u.id = b.user_id
            WHERE u.is_admin = 0";
    $params = []; $types = '';
    if ($csearch !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $like = "%$csearch%"; $params = [$like, $like, $like]; $types = 'sss';
    }
    $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = [$r['full_name'], $r['email'], $r['phone'], (int)$r['booking_count'],
                   $r['last_booking'] ? date('Y-m-d', strtotime($r['last_booking'])) : '',
                   $r['created_at'] ? date('Y-m-d', strtotime($r['created_at'])) : '',
                   $r['email_verified'] ? 'yes' : 'no'];
    }
    wm_csv_out('wiyule-customers-' . date('Y-m-d') . '.csv',
        ['Name', 'Email', 'Phone', 'Bookings', 'Last booking', 'Joined', 'Email verified'], $rows);
}

// ── bookings ──
$filter        = $_GET['status'] ?? 'all';
$source_filter = $_GET['source'] ?? 'all';
$search        = trim($_GET['search'] ?? '');
$date_from     = trim($_GET['from'] ?? '');
$date_to       = trim($_GET['to'] ?? '');

$conditions = []; $params = []; $types = '';
if ($filter !== 'all') { $conditions[] = "status = ?"; $params[] = $filter; $types .= 's'; }
else { $conditions[] = "status != 'cancelled'"; }
if ($source_filter !== 'all') {
    if ($source_filter === 'unknown') { $conditions[] = "(source IS NULL OR source = '')"; }
    else { $conditions[] = "source = ?"; $params[] = $source_filter; $types .= 's'; }
}
if ($search !== '') {
    $conditions[] = "(full_name LIKE ? OR phone LIKE ? OR email LIKE ? OR services LIKE ? OR vehicle_make LIKE ?)";
    $like = "%$search%"; array_push($params, $like, $like, $like, $like, $like); $types .= 'sssss';
}
if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) { $conditions[] = "booking_date >= ?"; $params[] = $date_from; $types .= 's'; }
if ($date_to   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   { $conditions[] = "booking_date <= ?"; $params[] = $date_to;   $types .= 's'; }

$sql = "SELECT id, full_name, phone, email, vehicle_make, vehicle_model, vehicle_year, registration,
               services, booking_date, booking_time, status, source, created_at FROM bookings";
if ($conditions) $sql .= " WHERE " . implode(' AND ', $conditions);
$sql .= " ORDER BY booking_date DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($b = $res->fetch_assoc()) {
    $rows[] = [$b['id'], $b['full_name'], $b['phone'], $b['email'],
               trim($b['vehicle_year'] . ' ' . $b['vehicle_make'] . ' ' . $b['vehicle_model']),
               $b['registration'], $b['services'], $b['booking_date'], $b['booking_time'],
               $b['status'], $b['source'], $b['created_at']];
}
wm_csv_out('wiyule-bookings-' . date('Y-m-d') . '.csv',
    ['Ref', 'Customer', 'Phone', 'Email', 'Vehicle', 'Registration', 'Service', 'Date', 'Time', 'Status', 'Source', 'Created'], $rows);
