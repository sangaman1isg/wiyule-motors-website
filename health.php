<?php
/**
 * health.php — Private system health check for Wiyule Motors
 * --------------------------------------------------------------
 * Open this URL anytime to confirm everything is running:
 *   https://wiyulemotors.co.mw/health.php?key=YOUR_SECRET
 *
 * IMPORTANT: change the secret below so the public can't see it.
 * Delete this file or change the key if you ever suspect misuse.
 */

// ---- 1. Simple access protection -------------------------------------------
require_once __DIR__ . '/includes/secrets.php';
$SECRET = WM_HEALTH_KEY;              // defined in the untracked secrets.php
if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(404);
    exit('Not found');
}

date_default_timezone_set('Africa/Blantyre');
header('Content-Type: text/html; charset=utf-8');

// ---- 2. Run checks ---------------------------------------------------------
$checks = [];

// PHP version
$checks[] = [
    'name'  => 'PHP version',
    'ok'    => version_compare(PHP_VERSION, '7.4', '>='),
    'value' => PHP_VERSION,
];

// Database connection
$db_ok = false; $db_msg = 'Not connected'; $db_name = '';
try {
    require __DIR__ . '/includes/db_connection.php';
    if (isset($conn) && !$conn->connect_error) {
        $db_ok = true;
        $res = $conn->query("SELECT DATABASE() AS db");
        $db_name = $res ? ($res->fetch_assoc()['db'] ?? '') : '';
        $db_msg = 'Connected to: ' . $db_name;
    } else {
        $db_msg = isset($conn) ? $conn->connect_error : 'No $conn';
    }
} catch (Throwable $e) {
    $db_msg = 'Error: ' . $e->getMessage();
}
$checks[] = ['name' => 'Database connection', 'ok' => $db_ok, 'value' => $db_msg];

// Required tables
$required_tables = ['users', 'bookings', 'vehicles'];
if ($db_ok) {
    foreach ($required_tables as $t) {
        $exists = false; $count = '—';
        $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
        if ($r && $r->num_rows > 0) {
            $exists = true;
            $cr = $conn->query("SELECT COUNT(*) AS c FROM `$t`");
            if ($cr) $count = number_format($cr->fetch_assoc()['c']);
        }
        $checks[] = [
            'name'  => "Table: $t",
            'ok'    => $exists,
            'value' => $exists ? "$count rows" : 'MISSING',
        ];
    }
}

// Recent activity (bookings in last 7 days) — shows the system is being used
if ($db_ok) {
    $recent = '—';
    $rr = $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    if ($rr) $recent = number_format($rr->fetch_assoc()['c']);
    $checks[] = ['name' => 'Bookings (last 7 days)', 'ok' => true, 'value' => $recent];
}

// Disk space on the partition
$free = @disk_free_space(__DIR__);
$total = @disk_total_space(__DIR__);
if ($free && $total) {
    $pct_free = round($free / $total * 100);
    $checks[] = [
        'name'  => 'Disk space free',
        'ok'    => $pct_free > 5,
        'value' => $pct_free . '% free (' . round($free / 1048576) . ' MB)',
    ];
}

// Error log presence
$logfile = __DIR__ . '/php-error.log';
$log_size = file_exists($logfile) ? filesize($logfile) : 0;
$checks[] = [
    'name'  => 'Error log',
    'ok'    => true,
    'value' => file_exists($logfile) ? round($log_size / 1024, 1) . ' KB (check via cPanel)' : 'No errors logged yet',
];

// ---- 3. Overall status -----------------------------------------------------
$all_ok = true;
foreach ($checks as $c) { if (!$c['ok']) $all_ok = false; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>System Health — Wiyule Motors</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: -apple-system, 'Segoe UI', sans-serif; background:#0b0f1a; color:#f1f5f9; padding:24px; }
    .wrap { max-width:680px; margin:0 auto; }
    .top { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
    .badge { width:54px; height:54px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:26px; }
    .badge.ok { background:rgba(34,197,94,0.16); }
    .badge.bad { background:rgba(239,68,68,0.16); }
    h1 { font-size:20px; font-weight:800; }
    .sub { font-size:13px; color:#94a3b8; margin-top:2px; }
    .row { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:12px; margin-bottom:8px; }
    .row .name { font-size:14px; font-weight:600; }
    .row .val { font-size:13px; color:#94a3b8; display:flex; align-items:center; gap:8px; }
    .dot { width:9px; height:9px; border-radius:50%; }
    .dot.ok { background:#22c55e; }
    .dot.bad { background:#ef4444; }
    .foot { margin-top:20px; font-size:12px; color:#64748b; text-align:center; line-height:1.6; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="badge <?= $all_ok ? 'ok' : 'bad' ?>"><?= $all_ok ? '✅' : '⚠️' ?></div>
        <div>
            <h1><?= $all_ok ? 'All systems operational' : 'Attention needed' ?></h1>
            <div class="sub">Checked <?= date('D, d M Y · g:i A') ?> (Blantyre time)</div>
        </div>
    </div>

    <?php foreach ($checks as $c): ?>
    <div class="row">
        <span class="name"><?= htmlspecialchars($c['name']) ?></span>
        <span class="val">
            <?= htmlspecialchars($c['value']) ?>
            <span class="dot <?= $c['ok'] ? 'ok' : 'bad' ?>"></span>
        </span>
    </div>
    <?php endforeach; ?>

    <div class="foot">
        Private page — keep the <code>?key=</code> secret.<br>
        Refresh anytime to re-check. If the database row is red, that's likely the cause of any 503 errors.
    </div>
</div>
</body>
</html>
