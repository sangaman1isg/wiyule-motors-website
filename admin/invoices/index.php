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
$show_archived = ($_GET['archived'] ?? '') === '1';

$where = []; $params = []; $types = '';
// Archive view: show only archived; default view hides archived.
$where[] = $show_archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL';
if ($f_status !== 'all' && in_array($f_status, ['unpaid','partial','paid','void'])) {
    $where[] = 'payment_status = ?'; $params[] = $f_status; $types .= 's';
}
if ($f_type !== 'all' && in_array($f_type, ['quotation','invoice'])) {
    $where[] = 'type = ?'; $params[] = $f_type; $types .= 's';
}
if ($f_search !== '') {
    $where[] = '(invoice_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR registration LIKE ?)';
    $like = '%' . $f_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'ssss';
}

$sql = "SELECT * FROM invoices";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY issued_at DESC LIMIT 200";

$invoices = [];
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Summary stats ──
$totals = ['outstanding' => 0, 'paid_this_month' => 0, 'total_count' => 0];
$r = $conn->query("SELECT COALESCE(SUM(total - amount_paid), 0) c FROM invoices WHERE type='invoice' AND payment_status IN ('unpaid','partial') AND archived_at IS NULL");
if ($r) $totals['outstanding'] = (float)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) c FROM invoices WHERE type='invoice' AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m') AND archived_at IS NULL");
if ($r) $totals['paid_this_month'] = (float)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM invoices WHERE type='invoice' AND archived_at IS NULL");
if ($r) $totals['total_count'] = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) c FROM invoices WHERE type='quotation' AND archived_at IS NULL");
if ($r) $totals['quote_count'] = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COALESCE(SUM(total),0) c FROM invoices WHERE type='quotation' AND archived_at IS NULL");
if ($r) $totals['quote_value'] = (float)$r->fetch_assoc()['c'];

// Which view: invoices-only, quotations-only, or all. Drives the toggle + stat cards.
$view = in_array($f_type, ['invoice','quotation'], true) ? $f_type : 'all';
function wm_inv_qs($type, $status, $q, $archived) {
    $p = ['status'=>$status, 'q'=>$q];
    if ($type !== 'all') $p['type'] = $type;
    if ($archived) $p['archived'] = '1';
    return http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== 'all'));
}
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

    td.actions { white-space:nowrap; text-align:right; }
    .act-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 10px; border-radius:8px; font-size:12px; font-weight:700; border:1px solid #e2e8f0; background:#fff; color:#334155; cursor:pointer; font-family:inherit; margin-left:4px; }
    .act-btn:hover { background:#f1f5f9; }
    .act-btn.green { color:#16a34a; border-color:#bbf7d0; } .act-btn.green:hover { background:#f0fdf4; }
    .act-btn.blue  { color:#2563eb; border-color:#bfdbfe; } .act-btn.blue:hover  { background:#eff6ff; }
    .act-btn.red   { color:#dc2626; border-color:#fecaca; } .act-btn.red:hover   { background:#fef2f2; }
    .act-btn.amber { color:#b45309; border-color:#fed7aa; } .act-btn.amber:hover { background:#fff7ed; }
    .toggle-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#64748b; text-decoration:none; padding:8px 14px; border:1px solid #e2e8f0; border-radius:10px; background:#fff; }
    /* Invoices / Quotations segmented toggle */
    .seg { display:inline-flex; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; padding:4px; gap:4px; margin-bottom:20px; flex-wrap:wrap; }
    .seg-btn { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border-radius:9px; font-size:13.5px; font-weight:700; color:#64748b; text-decoration:none; transition:all 0.12s; }
    .seg-btn:hover { color:#0f172a; }
    .seg-btn.active { background:#fff; color:#0f172a; box-shadow:0 1px 3px rgba(15,23,42,0.12); }
    .seg-btn .seg-c { font-size:11px; font-weight:800; background:#e2e8f0; color:#64748b; padding:1px 8px; border-radius:99px; }
    .seg-btn.active .seg-c { background:#dc2626; color:#fff; }
    @media(max-width:640px){ .seg { width:100%; } .seg-btn { flex:1; justify-content:center; } }
    .toggle-link:hover { color:#0f172a; background:#f8fafc; }
    .archived-row { background:#fcfcfd; }
    .archived-row .num, .archived-row td { opacity:0.72; }
    .toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); padding:13px 20px; border-radius:11px; font-weight:600; font-size:14px; color:#fff; box-shadow:0 8px 30px rgba(0,0,0,0.18); z-index:100; opacity:0; pointer-events:none; transition:opacity 0.2s; }
    .toast.show { opacity:1; } .toast.success { background:#16a34a; } .toast.error { background:#dc2626; }

    /* ── Mobile: turn the table into stacked cards ── */
    @media (max-width: 640px) {
        html, body { max-width:100%; overflow-x:hidden; }
        .topbar { padding:12px 14px; }
        .topbar-brand img { width:28px; height:28px; }
        .topbar-brand strong { font-size:14px; }
        .topbar-back { font-size:12px; }
        .page { padding:18px 12px; }
        .page-head h1 { font-size:21px; }
        .stats { gap:10px; margin-bottom:16px; }
        .filters { gap:8px; }
        .filters input[type=text] { min-width:0; flex:1 1 100%; }
        .filters select { flex:1 1 calc(50% - 5px); min-width:0; }

        /* Natural block widths (no width:100% — lets padding nest correctly) */
        .table-wrap { background:transparent; border:none; overflow:visible; }
        table, thead, tbody, tr { display:block; }
        thead { display:none; }
        tbody tr {
            background:#fff; border:1px solid #e2e8f0; border-radius:14px;
            margin-bottom:12px; padding:4px 14px 10px;
            box-shadow:0 1px 3px rgba(15,23,42,0.04);
        }
        tbody tr:hover { background:#fff; }
        td {
            display:flex; align-items:center; justify-content:space-between; gap:12px;
            padding:9px 0; border:none; text-align:right; font-size:14px; min-width:0;
        }
        td + td { border-top:1px solid #f4f6f9; }
        td::before {
            content:attr(data-label);
            font-weight:700; color:#64748b; text-transform:uppercase;
            font-size:10.5px; letter-spacing:0.04em; text-align:left; flex-shrink:0;
        }
        td > * { min-width:0; }
        td.num { font-size:16px; font-weight:800; }
        /* Actions: full-width button row with big tap targets */
        td.actions { flex-wrap:wrap; gap:8px; padding-top:12px; margin-top:2px; border-top:1px solid #eef2f7; }
        td.actions::before { display:none; }
        td.actions .act-btn { flex:1 1 auto; justify-content:center; margin-left:0; padding:12px 10px; font-size:13px; }
    }
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

    <?php
        $head_title = $show_archived ? 'Archived'
                    : ($view === 'invoice' ? 'Invoices' : ($view === 'quotation' ? 'Quotations' : 'Invoices &amp; Quotations'));
        $new_is_quote = ($view === 'quotation');
    ?>
    <div class="page-head">
        <div>
            <h1><?= $head_title ?></h1>
            <p class="sub">
                <?php if ($show_archived): ?>
                    Archived documents — hidden from the active list but kept on record.
                <?php elseif ($view === 'quotation'): ?>
                    <?= $totals['quote_count'] ?> quotation<?= $totals['quote_count']===1?'':'s' ?> on record
                <?php elseif ($view === 'invoice'): ?>
                    <?= $totals['total_count'] ?> invoice<?= $totals['total_count']===1?'':'s' ?> on record · VAT 17.5%
                <?php else: ?>
                    <?= $totals['total_count'] ?> invoices · <?= $totals['quote_count'] ?> quotations · VAT 17.5%
                <?php endif; ?>
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <?php if ($show_archived): ?>
                <a href="<?= $base ?>/admin/invoices/" class="toggle-link">← Active list</a>
            <?php else: ?>
                <button type="button" id="remindBtn" onclick="sendReminders()" class="toggle-link" title="Email payment reminders for overdue invoices + expiry nudges for ageing quotations">✉ Send reminders</button>
                <a href="<?= $base ?>/admin/invoices/?archived=1" class="toggle-link">🗄 Archived</a>
                <a href="<?= $base ?>/admin/invoices/edit.php?new=1<?= $new_is_quote ? '&type=quotation' : '' ?>" class="new-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New <?= $new_is_quote ? 'Quotation' : 'Invoice' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$show_archived): ?>
    <div class="seg">
        <a href="?<?= wm_inv_qs('all', $f_status, $f_search, false) ?>" class="seg-btn <?= $view==='all'?'active':'' ?>">All <span class="seg-c"><?= $totals['total_count'] + $totals['quote_count'] ?></span></a>
        <a href="?<?= wm_inv_qs('invoice', $f_status, $f_search, false) ?>" class="seg-btn <?= $view==='invoice'?'active':'' ?>">🧾 Invoices <span class="seg-c"><?= $totals['total_count'] ?></span></a>
        <a href="?<?= wm_inv_qs('quotation', $f_status, $f_search, false) ?>" class="seg-btn <?= $view==='quotation'?'active':'' ?>">📄 Quotations <span class="seg-c"><?= $totals['quote_count'] ?></span></a>
    </div>
    <?php endif; ?>

    <div class="stats">
        <?php if ($view === 'quotation'): ?>
            <div class="stat-card">
                <div class="label">Quotations</div>
                <div class="value"><?= $totals['quote_count'] ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Total Quoted Value</div>
                <div class="value"><?= wm_invoice_format_mwk($totals['quote_value']) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Avg. per Quote</div>
                <div class="value"><?= wm_invoice_format_mwk($totals['quote_count'] > 0 ? $totals['quote_value'] / $totals['quote_count'] : 0) ?></div>
            </div>
        <?php else: ?>
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
        <?php endif; ?>
    </div>

    <form method="GET" class="filters">
        <input type="hidden" name="type" value="<?= $view === 'all' ? '' : htmlspecialchars($view) ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($f_search) ?>" placeholder="Search invoice #, customer name, phone…">
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
                    <th>Reg No</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv):
                    [$slabel,$scol] = wm_invoice_status_label($inv['payment_status']);
                    [$tlabel,$tcol] = wm_invoice_type_label($inv['type']);
                    $iid = (int)$inv['id'];
                ?>
                <tr class="<?= $show_archived ? 'archived-row' : '' ?>" onclick="window.location='<?= $base ?>/admin/invoices/edit.php?id=<?= $iid ?>'">
                    <td class="num" data-label="Invoice #"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td data-label="Type"><span class="pill" style="background:<?= $tcol ?>"><?= $tlabel ?></span></td>
                    <td data-label="Customer">
                        <div style="min-width:0">
                            <div style="font-weight:600"><?= htmlspecialchars($inv['customer_name']) ?></div>
                            <div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($inv['customer_phone']) ?></div>
                        </div>
                    </td>
                    <td data-label="Reg No"><?php if (!empty($inv['registration'])): ?><span style="font-weight:600"><?= htmlspecialchars($inv['registration']) ?></span><?php else: ?><span style="color:#cbd5e1">—</span><?php endif; ?></td>
                    <td data-label="Date" style="color:#64748b"><?= date('d M Y', strtotime($inv['issued_at'])) ?></td>
                    <td class="amount" data-label="Total"><?= wm_invoice_format_mwk($inv['total']) ?></td>
                    <td data-label="Status"><span class="pill" style="background:<?= $scol ?>"><?= $slabel ?></span></td>
                    <td class="actions" onclick="event.stopPropagation()">
                        <?php if ($show_archived): ?>
                            <button class="act-btn green" onclick="invAction(<?= $iid ?>,'restore','Restore this document to the active list?')">↩ Restore</button>
                        <?php else: ?>
                            <?php if ($inv['type'] === 'quotation'): ?>
                                <button class="act-btn blue" onclick="invAction(<?= $iid ?>,'convert_to_invoice','Convert quotation <?= htmlspecialchars($inv['invoice_number']) ?> into an invoice?')">→ Invoice</button>
                            <?php elseif (in_array($inv['payment_status'], ['unpaid','partial'])): ?>
                                <button class="act-btn green" onclick="invAction(<?= $iid ?>,'mark_paid','Mark <?= htmlspecialchars($inv['invoice_number']) ?> as fully paid?')">✓ Mark paid</button>
                            <?php elseif ($inv['payment_status'] === 'paid'): ?>
                                <button class="act-btn amber" onclick="invAction(<?= $iid ?>,'mark_unpaid','Mark <?= htmlspecialchars($inv['invoice_number']) ?> as unpaid?')">↩ Mark unpaid</button>
                            <?php endif; ?>
                            <button class="act-btn red" onclick="invAction(<?= $iid ?>,'archive','Archive <?= htmlspecialchars($inv['invoice_number']) ?>? It will be hidden from the active list but kept on record (recoverable).')">🗄 Archive</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="8" class="empty"><?= $show_archived ? 'Nothing archived.' : 'No invoices found.' ?> <?php if(!$show_archived): ?><a href="<?= $base ?>/admin/invoices/edit.php?new=1" style="color:#dc2626;font-weight:700">Create the first one →</a><?php endif; ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="toast" class="toast"></div>

<script>
const BASE = <?= json_encode($base) ?>;

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + (type || 'success');
    setTimeout(() => t.className = 'toast', 2600);
}

async function invAction(id, action, confirmMsg) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', id);
    try {
        const resp = await fetch(BASE + '/admin/invoices/api.php', { method:'POST', body:fd, credentials:'same-origin' });
        const j = await resp.json();
        if (j.success) {
            showToast(j.message || 'Done', 'success');
            setTimeout(() => location.reload(), 650);
        } else {
            showToast(j.message || 'Action failed', 'error');
        }
    } catch (e) {
        showToast('Network error — try again', 'error');
    }
}

// Email payment reminders (overdue invoices) + quotation expiry nudges.
async function sendReminders() {
    const btn = document.getElementById('remindBtn');
    if (!confirm('Email reminders now?\n\nSends payment reminders to customers with overdue invoices, and expiry nudges for quotations nearing their 14-day limit. Each customer is reminded at most once a week.')) return;
    if (btn){ btn.disabled = true; btn.textContent = 'Sending…'; }
    try {
        const r = await fetch(BASE + '/admin/api/send-reminders.php', { method:'POST', credentials:'same-origin' });
        const j = await r.json();
        showToast(j.message || (j.success ? 'Done' : 'Failed'), j.success ? 'success' : 'error');
    } catch (e) { showToast('Network error — try again', 'error'); }
    if (btn){ btn.disabled = false; btn.textContent = '✉ Send reminders'; }
}
</script>

</body>
</html>
