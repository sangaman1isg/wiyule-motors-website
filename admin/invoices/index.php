<?php
// admin/invoices/index.php — List of all invoices/quotations
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . $base . "/admin/login.php");
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/invoices.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// ── Filters ──
$f_status = $_GET['status'] ?? 'all';
$f_type   = $_GET['type']   ?? 'all';
$f_search = trim($_GET['q'] ?? '');

$where = []; $params = []; $types = '';
if ($f_status !== 'all' && in_array($f_status, ['unpaid','partial','paid','void'])) {
    $where[] = 'payment_status = ?'; $params[] = $f_status; $types .= 's';
}
if ($f_type !== 'all' && in_array($f_type, ['quotation','invoice'])) {
    $where[] = 'type = ?'; $params[] = $f_type; $types .= 's';
}
if ($f_search !== '') {
    $where[] = '(invoice_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)';
    $like = '%' . $f_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

$sql = "SELECT * FROM invoices";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY issued_at DESC LIMIT 200";

$invoices = [];
$stmt = $conn->prepare($sql);
if ($where) $stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Summary stats ──
$totals = ['outstanding' => 0, 'paid_this_month' => 0, 'total_count' => 0];
$r = $conn->query("SELECT COALESCE(SUM(total - amount_paid), 0) c FROM invoices WHERE type='invoice' AND payment_status IN ('unpaid','partial')");
if ($r) $totals['outstanding'] = (float)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) c FROM invoices WHERE type='invoice' AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')");
if ($r) $totals['paid_this_month'] = (float)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM invoices WHERE type='invoice'");
if ($r) $totals['total_count'] = (int)$r->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices — Wiyule Motors Admin</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Sora',sans-serif; background:#f8fafc; color:#0f172a; min-height:100vh; }

    .topbar { background:#fff; border-bottom:1px solid #e2e8f0; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:30; }
    .topbar-brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:#0f172a; }
    .topbar-brand img { width:34px; height:34px; }
    .topbar-brand strong { font-size:16px; font-weight:800; letter-spacing:-0.01em; }
    .topbar-back { display:inline-flex; align-items:center; gap:6px; color:#64748b; font-size:13px; font-weight:600; text-decoration:none; }
    .topbar-back:hover { color:#dc2626; }

    .page { max-width:1200px; margin:0 auto; padding:32px 24px; }
    .page-head { display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:24px; }
    .page-head h1 { font-size:24px; font-weight:800; letter-spacing:-0.02em; }
    .page-head .sub { color:#64748b; font-size:13px; margin-top:4px; }
    .new-btn { display:inline-flex; align-items:center; gap:8px; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; padding:11px 20px; border-radius:11px; font-weight:700; text-decoration:none; font-size:14px; box-shadow:0 4px 14px rgba(220,38,38,0.25); }
    .new-btn:hover { transform:translateY(-1px); }

    .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
    @media(max-width:700px) { .stats { grid-template-columns:1fr; } }
    .stat-card { background:#fff; padding:18px 20px; border-radius:14px; border:1px solid #e2e8f0; }
    .stat-card .label { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; }
    .stat-card .value { font-size:22px; font-weight:800; margin-top:6px; letter-spacing:-0.02em; }
    .stat-card .value.red { color:#dc2626; }
    .stat-card .value.green { color:#16a34a; }

    .filters { background:#fff; padding:14px 16px; border-radius:14px; border:1px solid #e2e8f0; margin-bottom:14px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
    .filters select, .filters input { padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:13px; font-family:inherit; outline:none; }
    .filters input:focus, .filters select:focus { border-color:#dc2626; }
    .filters input[type=text] { min-width:220px; flex:1; }

    .table-wrap { background:#fff; border-radius:14px; border:1px solid #e2e8f0; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:13px 16px; text-align:left; font-size:13px; }
    th { background:#f8fafc; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.04em; font-size:11px; border-bottom:1px solid #e2e8f0; }
    tbody tr { border-bottom:1px solid #f1f5f9; cursor:pointer; transition:background 0.1s; }
    tbody tr:hover { background:#f8fafc; }
    .pill { display:inline-block; padding:3px 9px; border-radius:99px; font-size:11px; font-weight:700; color:#fff; }
    .num { font-family:'SF Mono',Menlo,monospace; font-weight:600; color:#0f172a; }
    .amount { font-weight:700; }
    .empty { padding:60px; text-align:center; color:#94a3b8; font-size:14px; }
</style>
</head>
<body>

<div class="topbar">
    <a href="<?= $base ?>/admin/" class="topbar-brand">
        <img src="<?= $base ?>/assets/images/logo.png" alt="Wiyule">
        <strong>Wiyule Motors — Admin</strong>
    </a>
    <a href="<?= $base ?>/admin/" class="topbar-back">← Back to dashboard</a>
</div>

<div class="page">

    <div class="page-head">
        <div>
            <h1>Invoices &amp; Quotations</h1>
            <p class="sub"><?= $totals['total_count'] ?> invoices on record · VAT 17.5%</p>
        </div>
        <a href="<?= $base ?>/admin/invoices/edit.php?new=1" class="new-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Invoice
        </a>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Outstanding</div>
            <div class="value red"><?= wm_invoice_format_mwk($totals['outstanding']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Paid This Month</div>
            <div class="value green"><?= wm_invoice_format_mwk($totals['paid_this_month']) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Invoices</div>
            <div class="value"><?= $totals['total_count'] ?></div>
        </div>
    </div>

    <form method="GET" class="filters">
        <input type="text" name="q" value="<?= htmlspecialchars($f_search) ?>" placeholder="Search invoice #, customer name, phone…">
        <select name="type">
            <option value="all"       <?= $f_type==='all'?'selected':'' ?>>All types</option>
            <option value="invoice"   <?= $f_type==='invoice'?'selected':'' ?>>Invoices only</option>
            <option value="quotation" <?= $f_type==='quotation'?'selected':'' ?>>Quotations only</option>
        </select>
        <select name="status">
            <option value="all"     <?= $f_status==='all'?'selected':'' ?>>All statuses</option>
            <option value="unpaid"  <?= $f_status==='unpaid'?'selected':'' ?>>Unpaid</option>
            <option value="partial" <?= $f_status==='partial'?'selected':'' ?>>Partial</option>
            <option value="paid"    <?= $f_status==='paid'?'selected':'' ?>>Paid</option>
            <option value="void"    <?= $f_status==='void'?'selected':'' ?>>Void</option>
        </select>
        <button type="submit" style="background:#0f172a;color:#fff;border:none;padding:9px 18px;border-radius:10px;font-weight:600;font-size:13px;cursor:pointer">Filter</button>
        <?php if ($f_status!=='all' || $f_type!=='all' || $f_search!==''): ?>
            <a href="<?= $base ?>/admin/invoices/" style="font-size:12px;color:#64748b;text-decoration:none">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv):
                    [$slabel,$scol] = wm_invoice_status_label($inv['payment_status']);
                    [$tlabel,$tcol] = wm_invoice_type_label($inv['type']);
                ?>
                <tr onclick="window.location='<?= $base ?>/admin/invoices/edit.php?id=<?= (int)$inv['id'] ?>'">
                    <td class="num"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td><span class="pill" style="background:<?= $tcol ?>"><?= $tlabel ?></span></td>
                    <td>
                        <div style="font-weight:600"><?= htmlspecialchars($inv['customer_name']) ?></div>
                        <div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($inv['customer_phone']) ?></div>
                    </td>
                    <td style="color:#64748b"><?= date('d M Y', strtotime($inv['issued_at'])) ?></td>
                    <td class="amount"><?= wm_invoice_format_mwk($inv['total']) ?></td>
                    <td><span class="pill" style="background:<?= $scol ?>"><?= $slabel ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="6" class="empty">No invoices found. <a href="<?= $base ?>/admin/invoices/edit.php?new=1" style="color:#dc2626;font-weight:700">Create the first one →</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
