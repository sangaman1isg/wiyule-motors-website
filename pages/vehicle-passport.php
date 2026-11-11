<?php
// pages/vehicle-passport.php — a printable "service passport" for one of the
// customer's vehicles: every completed service we've done, branded and printable
// to PDF. Great to hand over at resale. Owner-gated.
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . $base . '/pages/login.php');
    exit;
}
require_once __DIR__ . '/../includes/db_connection.php';

$uid = (int)$_SESSION['user_id'];
$vid = (int)($_GET['vehicle'] ?? 0);

// Load the vehicle — only if it belongs to this customer.
$stmt = $conn->prepare("SELECT id, make, model, year, registration, color, mileage FROM vehicles WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $vid, $uid);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$vehicle) {
    header('Location: ' . $base . '/pages/dashboard.php');
    exit;
}

// Completed services for this vehicle (matched by make+model, like the dashboard).
$history = [];
$hs = $conn->prepare("
    SELECT booking_date, services, service_mileage, notes
    FROM bookings
    WHERE user_id = ? AND status = 'completed' AND vehicle_make = ? AND vehicle_model = ?
    ORDER BY booking_date DESC
");
$hs->bind_param('iss', $uid, $vehicle['make'], $vehicle['model']);
$hs->execute();
$history = $hs->get_result()->fetch_all(MYSQLI_ASSOC);
$hs->close();

$owner_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['user_name'] ?? '');
$veh_title  = trim($vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Service Passport — <?= htmlspecialchars($veh_title) ?><?= $vehicle['registration'] ? ' - '.htmlspecialchars($vehicle['registration']) : '' ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing:border-box; margin:0; padding:0; }
    html, body { overflow-x:hidden; }
    body { font-family:'Sora',-apple-system,system-ui,sans-serif; background:#f1f5f9; color:#0f172a; padding:24px 16px 80px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .toolbar { max-width:820px; margin:0 auto 14px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .toolbar a { color:#64748b; text-decoration:none; font-size:14px; }
    .t-btn { background:#0f172a; color:#fff; border:none; padding:10px 18px; border-radius:10px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; }
    .paper { max-width:820px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 10px 40px rgba(0,0,0,0.08); padding:34px 34px 40px; }
    .lh { display:flex; align-items:center; gap:16px; border-bottom:2.5px solid #dc2626; padding-bottom:14px; margin-bottom:20px; }
    .lh img { width:120px; height:auto; }
    .lh-txt { font-size:11px; color:#334155; line-height:1.5; }
    .lh-txt strong { color:#0f172a; font-size:13px; }
    h1 { font-size:22px; font-weight:800; letter-spacing:-0.01em; }
    .sub { color:#64748b; font-size:13px; margin-top:2px; }
    .veh { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:20px 0; }
    @media (max-width:560px){ .veh { grid-template-columns:repeat(2,1fr); } }
    .veh-cell { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; }
    .veh-label { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.06em; }
    .veh-value { font-size:15px; font-weight:700; color:#0f172a; margin-top:2px; }
    table { width:100%; border-collapse:collapse; margin-top:6px; }
    thead th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; border-bottom:2px solid #e2e8f0; padding:9px 10px; }
    tbody td { font-size:13.5px; padding:11px 10px; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    tbody tr:last-child td { border-bottom:none; }
    .date-cell { white-space:nowrap; font-weight:600; }
    .empty { text-align:center; color:#94a3b8; padding:30px 0; font-size:14px; }
    .summary { margin-top:18px; display:flex; gap:10px; flex-wrap:wrap; }
    .chip { background:#fef2f2; color:#b91c1c; font-size:12px; font-weight:700; padding:6px 14px; border-radius:99px; }
    .foot { margin-top:26px; padding-top:14px; border-top:1px solid #e2e8f0; font-size:11px; color:#94a3b8; text-align:center; }
    @media print {
        body { background:#fff; padding:0; }
        .toolbar { display:none; }
        .paper { box-shadow:none; border-radius:0; max-width:none; width:100%; padding:10mm; }
        @page { size:A4; margin:10mm; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <a href="<?= $base ?>/pages/dashboard.php">← Back to dashboard</a>
    <button class="t-btn" onclick="window.print()">🖨 Print / Save as PDF</button>
</div>
<div class="paper">
    <div class="lh">
        <img src="<?= $base ?>/assets/images/logo-letterhead.png" alt="Wiyule & F A Motors">
        <div class="lh-txt">
            <strong>Wiyule &amp; F.A Motors</strong><br>
            Blantyre, Malawi · +265 993 575 111<br>
            wiyulemotors.co.mw
        </div>
    </div>

    <h1>Vehicle Service Passport</h1>
    <div class="sub">A record of services carried out by Wiyule &amp; F.A Motors<?= $owner_name ? ' for '.htmlspecialchars($owner_name) : '' ?>.</div>

    <div class="veh">
        <div class="veh-cell"><div class="veh-label">Vehicle</div><div class="veh-value"><?= htmlspecialchars($veh_title ?: '—') ?></div></div>
        <div class="veh-cell"><div class="veh-label">Registration</div><div class="veh-value"><?= htmlspecialchars($vehicle['registration'] ?: '—') ?></div></div>
        <div class="veh-cell"><div class="veh-label">Colour</div><div class="veh-value"><?= htmlspecialchars($vehicle['color'] ?: '—') ?></div></div>
        <div class="veh-cell"><div class="veh-label">Current mileage</div><div class="veh-value"><?= $vehicle['mileage'] ? number_format($vehicle['mileage']).' km' : '—' ?></div></div>
    </div>

    <?php if (empty($history)): ?>
        <div class="empty">No completed services recorded for this vehicle yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th style="width:120px">Date</th><th>Service</th><th style="width:110px">Mileage</th></tr></thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td class="date-cell"><?= date('d M Y', strtotime($h['booking_date'])) ?></td>
                    <td>
                        <?= htmlspecialchars($h['services']) ?>
                        <?php if (!empty($h['notes'])): ?><div style="color:#94a3b8;font-size:12px;margin-top:2px"><?= htmlspecialchars($h['notes']) ?></div><?php endif; ?>
                    </td>
                    <td><?= !empty($h['service_mileage']) ? number_format($h['service_mileage']).' km' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="summary">
            <span class="chip"><?= count($history) ?> service<?= count($history) === 1 ? '' : 's' ?> on record</span>
            <span class="chip" style="background:#f1f5f9;color:#475569">Since <?= date('Y', strtotime(end($history)['booking_date'])) ?></span>
        </div>
    <?php endif; ?>

    <div class="foot">Generated <?= date('d M Y') ?> · This is a service record, not a roadworthiness certificate.</div>
</div>
</body>
</html>
