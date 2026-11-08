<?php
// admin/statements/index.php — Insurance company statements & "Others" bucket
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

if (!isset($_SESSION['admin_id'])) {
    header("Location: " . $base . "/admin/login.php");
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/invoices.php';
require_once __DIR__ . '/../../includes/insurance.php';

// ── Determine view: list or per-company detail ──
$selected_company = $_GET['company'] ?? null;
$year             = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
$status_filter    = in_array($_GET['status'] ?? '', ['unpaid', 'paid']) ? $_GET['status'] : 'all';

// Available years for selector (always include current year)
$years = wm_ins_distinct_years($conn);
if (!in_array((int)date('Y'), $years, true)) array_unshift($years, (int)date('Y'));

// Handle deactivate / reactivate actions (admin can soft-delete companies)
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $cid = (int)($_POST['company_id'] ?? 0);
    if ($act === 'deactivate' && $cid > 0) {
        wm_ins_company_deactivate($conn, $cid);
        $flash = 'Company hidden. Existing invoices keep their link.';
    } elseif ($act === 'reactivate' && $cid > 0) {
        wm_ins_company_reactivate($conn, $cid);
        $flash = 'Company reactivated.';
    } elseif ($act === 'add' && trim($_POST['name'] ?? '') !== '') {
        $new_id = wm_ins_company_add($conn, $_POST['name']);
        $flash = $new_id ? 'Company added.' : 'Could not add company.';
    }
    // Redirect to clear POST (PRG pattern)
    $back = $base . '/admin/statements/' . ($selected_company ? '?company=' . urlencode($selected_company) : '');
    header('Location: ' . $back . (strpos($back, '?') === false ? '?' : '&') . 'flash=' . urlencode($flash));
    exit;
}
if (isset($_GET['flash'])) $flash = $_GET['flash'];

// ── If detail view requested, prepare detail data ──
$detail_company   = null;   // null when in list view
$detail_invoices  = [];
$detail_totals    = null;

if ($selected_company !== null) {
    if ($selected_company === 'others') {
        $detail_company = ['id' => null, 'name' => 'Others', 'is_active' => 1];
    } else {
        $cid = (int)$selected_company;
        $detail_company = wm_ins_company_get($conn, $cid);
        if (!$detail_company) {
            // Bogus id — bounce to list view
            header('Location: ' . $base . '/admin/statements/');
            exit;
        }
    }
    $cid_for_query = $detail_company['id'] ?? null;
    $detail_invoices = wm_ins_invoices_for_company($conn, $cid_for_query, $year, $status_filter);

    // Aggregate totals for the detail header (apply same year filter)
    // Reuse summary helper then pick out our row.
    $all_summary = wm_ins_statements_summary($conn, $year);
    foreach ($all_summary as $row) {
        if ($row['id'] === ($detail_company['id'] ?? null) && $row['name'] === $detail_company['name']) {
            $detail_totals = $row;
            break;
        }
    }
} else {
    // List view — fetch summary for all companies
    $summary = wm_ins_statements_summary($conn, $year);
    // Sort: companies with outstanding > 0 first (DESC), then by name
    usort($summary, function ($a, $b) {
        if ($a['outstanding'] != $b['outstanding']) return $b['outstanding'] <=> $a['outstanding'];
        return strcmp($a['name'], $b['name']);
    });
}

// All companies for the "Manage Companies" section
$all_companies = wm_ins_companies_all($conn, false);

// Count of imported statement lines still awaiting reconciliation (0 if no import)
$review_open = wm_import_reviews_open_count($conn);

$fmt = function($n) { return wm_invoice_format_mwk($n); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $detail_company ? htmlspecialchars($detail_company['name']) . ' — Statement' : 'Statements' ?> · Wiyule Admin</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sora', system-ui, sans-serif;
        background: #f8fafc;
        color: #0f172a;
        line-height: 1.5;
    }
    .topbar {
        background: #0f172a; color: #fff;
        padding: 14px 28px;
        display: flex; align-items: center; gap: 16px;
        position: sticky; top: 0; z-index: 10;
    }
    .topbar a { color: #fff; text-decoration: none; opacity: 0.8; font-size: 13px; }
    .topbar a:hover { opacity: 1; }
    .topbar h1 { font-size: 16px; font-weight: 700; margin-right: auto; }

    .container { max-width: 1280px; margin: 0 auto; padding: 28px; }

    .page-head {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 16px; flex-wrap: wrap; margin-bottom: 24px;
    }
    .page-head h2 { font-size: 24px; font-weight: 800; }
    .page-head .sub { font-size: 13px; color: #64748b; margin-top: 4px; }

    .flash {
        background: #dcfce7; border: 1.5px solid #86efac; color: #166534;
        padding: 10px 14px; border-radius: 10px;
        font-size: 13px; margin-bottom: 16px;
    }

    /* ─── List view: company cards ─── */
    .companies-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
        margin-bottom: 28px;
    }
    .co-card {
        background: #fff; border-radius: 14px; padding: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        text-decoration: none; color: inherit;
        transition: transform 0.12s, box-shadow 0.12s;
        display: block;
        border-top: 4px solid #e2e8f0;
    }
    .co-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
    .co-card.has-outstanding { border-top-color: #dc2626; }
    .co-card.all-clear { border-top-color: #16a34a; }
    .co-card.others { border-top-color: #94a3b8; }
    .co-card.empty { opacity: 0.7; }

    .co-name { font-size: 15px; font-weight: 700; margin-bottom: 14px; }
    .co-row { display: flex; justify-content: space-between; font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .co-row strong { color: #0f172a; font-weight: 600; font-variant-numeric: tabular-nums; }
    .co-outstanding {
        margin-top: 12px; padding-top: 12px;
        border-top: 1px dashed #e2e8f0;
        display: flex; justify-content: space-between; align-items: baseline;
    }
    .co-outstanding-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
    .co-outstanding-value { font-size: 16px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .co-outstanding-value.zero { color: #16a34a; }
    .co-outstanding-value.due  { color: #dc2626; }
    .co-overdue-tag {
        display: inline-block; margin-top: 8px;
        background: #fee2e2; color: #991b1b;
        font-size: 10.5px; font-weight: 700;
        padding: 3px 8px; border-radius: 999px;
    }

    /* ─── Detail view: invoice list ─── */
    .breadcrumb {
        font-size: 13px; color: #64748b; margin-bottom: 14px;
    }
    .breadcrumb a { color: #475569; text-decoration: none; font-weight: 600; }
    .breadcrumb a:hover { color: #dc2626; }

    .detail-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 22px;
    }
    .detail-stat {
        background: #fff; padding: 16px 18px; border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .detail-stat-label {
        font-size: 11px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.05em;
        margin-bottom: 6px;
    }
    .detail-stat-value {
        font-size: 22px; font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .detail-stat-value.due { color: #dc2626; }
    .detail-stat-value.clear { color: #16a34a; }
    .detail-stat-sub { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }

    .toolbar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .pill-row { display: flex; gap: 6px; flex-wrap: wrap; }
    .pill {
        padding: 6px 14px; border-radius: 999px;
        font-size: 12.5px; font-weight: 600;
        background: #f1f5f9; color: #64748b;
        text-decoration: none;
        border: 1.5px solid transparent;
    }
    .pill:hover { background: #e2e8f0; color: #0f172a; }
    .pill.active { background: #dc2626; color: #fff; }

    select.year-select {
        padding: 7px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px;
        font-family: 'Sora', sans-serif; font-size: 13px; background: #fff;
        cursor: pointer;
    }

    .toolbar-right { margin-left: auto; display: flex; gap: 8px; }
    .btn {
        padding: 8px 14px; border-radius: 9px;
        font-size: 13px; font-weight: 600; font-family: 'Sora', sans-serif;
        text-decoration: none; border: 1.5px solid transparent;
        cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn.primary { background: #0f172a; color: #fff; }
    .btn.primary:hover { background: #1e293b; }
    .btn.ghost { background: #fff; color: #334155; border-color: #e2e8f0; }
    .btn.ghost:hover { border-color: #cbd5e1; }

    .invoices-table {
        background: #fff; border-radius: 14px; overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    table.inv-list { width: 100%; border-collapse: collapse; }
    table.inv-list th {
        background: #f8fafc; color: #475569;
        font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em;
        padding: 12px 14px; text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    table.inv-list td {
        padding: 12px 14px;
        font-size: 13px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    table.inv-list tr:last-child td { border-bottom: none; }
    table.inv-list tr:hover { background: #fafbfc; }
    .inv-num { font-weight: 700; color: #0f172a; }
    .inv-num a { color: #0f172a; text-decoration: none; }
    .inv-num a:hover { color: #dc2626; }
    .inv-meta { font-size: 11.5px; color: #94a3b8; }
    .amt { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
    .status-badge {
        display: inline-block;
        padding: 3px 10px; border-radius: 999px;
        font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    .status-paid    { background: #dcfce7; color: #166534; }
    .status-partial { background: #fef3c7; color: #92400e; }
    .status-unpaid  { background: #fee2e2; color: #991b1b; }
    .overdue-flag {
        margin-left: 6px;
        background: #dc2626; color: #fff;
        padding: 2px 7px; border-radius: 4px;
        font-size: 10px; font-weight: 700;
        letter-spacing: 0.04em;
    }
    .pay-action { margin-top: 7px; }
    .pay-btn {
        font-family: 'Sora', sans-serif; font-size: 11.5px; font-weight: 600;
        padding: 5px 11px; border-radius: 7px; border: 1.5px solid transparent;
        cursor: pointer; white-space: nowrap;
    }
    .pay-btn.to-paid { background: #16a34a; color: #fff; }
    .pay-btn.to-paid:hover { background: #15803d; }
    .pay-btn.to-unpaid { background: #fff; color: #b45309; border-color: #fed7aa; }
    .pay-btn.to-unpaid:hover { background: #fff7ed; }
    .pay-btn:disabled { opacity: 0.6; cursor: default; }
    .empty-state {
        padding: 40px 20px; text-align: center; color: #94a3b8;
        font-size: 14px;
    }

    /* ─── Manage Companies ─── */
    .manage-card {
        background: #fff; border-radius: 14px; padding: 22px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }
    .manage-card h3 {
        font-size: 14px; font-weight: 700; margin-bottom: 14px;
        color: #0f172a;
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    .manage-add { display: flex; gap: 8px; margin-bottom: 18px; max-width: 480px; }
    .manage-add input {
        flex: 1; padding: 9px 12px;
        border: 1.5px solid #e2e8f0; border-radius: 8px;
        font-family: 'Sora', sans-serif; font-size: 13px;
        outline: none;
    }
    .manage-add input:focus { border-color: #dc2626; }
    .manage-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
    .manage-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        font-size: 13px;
    }
    .manage-item.inactive { opacity: 0.55; }
    .manage-item form { display: inline; }
    .manage-item button {
        background: none; border: none;
        font-size: 12px; color: #64748b; cursor: pointer;
        font-family: 'Sora', sans-serif; font-weight: 600;
    }
    .manage-item button:hover { color: #dc2626; }
    .manage-item.inactive button:hover { color: #16a34a; }

    /* ─── Reconcile-imports banner ─── */
    .review-banner {
        display: flex; align-items: center; gap: 14px;
        background: #fff7ed; border: 1.5px solid #fed7aa;
        border-left: 4px solid #ea580c;
        border-radius: 12px; padding: 14px 18px; margin-bottom: 20px;
        text-decoration: none; color: inherit;
        transition: box-shadow .12s;
    }
    .review-banner:hover { box-shadow: 0 4px 14px rgba(234,88,12,.12); }
    .review-banner .rb-icon { font-size: 22px; line-height: 1; }
    .review-banner .rb-text { flex: 1; }
    .review-banner .rb-title { font-weight: 700; font-size: 14px; color: #9a3412; }
    .review-banner .rb-sub { font-size: 12.5px; color: #c2630e; margin-top: 2px; }
    .review-banner .rb-count {
        background: #ea580c; color: #fff; font-weight: 800;
        font-size: 13px; padding: 5px 12px; border-radius: 999px;
        font-variant-numeric: tabular-nums;
    }
    .review-banner .rb-go { font-size: 13px; font-weight: 700; color: #ea580c; white-space: nowrap; }

    @media (max-width: 700px) {
        .container { padding: 18px; }
        table.inv-list th:nth-child(3), table.inv-list td:nth-child(3) { display: none; }   /* hide claim ref on narrow */
    }
</style>
</head>
<body>

<div class="topbar">
    <a href="<?= $base ?>/admin/">← Admin</a>
    <h1>Statements</h1>
</div>

<div class="container">

<?php if ($flash): ?>
    <div class="flash"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php if ($detail_company): ?>
    <!-- ════════ DETAIL VIEW ════════ -->

    <div class="breadcrumb">
        <a href="<?= $base ?>/admin/statements/">← All statements</a>
    </div>

    <div class="page-head">
        <div>
            <h2><?= htmlspecialchars($detail_company['name']) ?></h2>
            <div class="sub">
                <?= $year ? $year . ' · ' : '' ?>
                <?= $detail_totals ? (int)$detail_totals['invoice_count'] : 0 ?> invoice<?= ($detail_totals['invoice_count'] ?? 0) === 1 ? '' : 's' ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($detail_company['id'] !== null && ($detail_totals['invoice_count'] ?? 0) > 0):
                // Build URL preserving current filters
                $stmt_url = $base . '/admin/statements/print.php?company=' . (int)$detail_company['id'];
                if ($year) $stmt_url .= '&year=' . $year;
                if ($status_filter !== 'all') $stmt_url .= '&status=' . urlencode($status_filter);
            ?>
                <a href="<?= htmlspecialchars($stmt_url) ?>" target="_blank" class="btn primary">
                    🖨 Generate Statement PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($detail_totals): ?>
        <div class="detail-stats">
            <div class="detail-stat">
                <div class="detail-stat-label">Outstanding</div>
                <div class="detail-stat-value <?= $detail_totals['outstanding'] > 0 ? 'due' : 'clear' ?>">
                    <?= $fmt($detail_totals['outstanding']) ?>
                </div>
                <div class="detail-stat-sub"><?= (int)$detail_totals['unpaid_count'] ?> unpaid invoice<?= $detail_totals['unpaid_count'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="detail-stat">
                <div class="detail-stat-label">Total Billed</div>
                <div class="detail-stat-value"><?= $fmt($detail_totals['total_billed']) ?></div>
                <div class="detail-stat-sub"><?= (int)$detail_totals['invoice_count'] ?> total</div>
            </div>
            <div class="detail-stat">
                <div class="detail-stat-label">Total Paid</div>
                <div class="detail-stat-value clear"><?= $fmt($detail_totals['total_paid']) ?></div>
                <div class="detail-stat-sub"><?= (int)$detail_totals['paid_count'] ?> fully paid</div>
            </div>
            <?php if ($detail_totals['overdue_count'] > 0): ?>
            <div class="detail-stat">
                <div class="detail-stat-label">Overdue (>21 days)</div>
                <div class="detail-stat-value due"><?= $fmt($detail_totals['overdue_amount']) ?></div>
                <div class="detail-stat-sub"><?= (int)$detail_totals['overdue_count'] ?> overdue invoice<?= $detail_totals['overdue_count'] === 1 ? '' : 's' ?></div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="toolbar">
        <div class="pill-row">
            <?php
            $qs_base = ['company' => $selected_company, 'year' => $year];
            $build_url = function($extra) use ($base, $qs_base) {
                $q = array_merge($qs_base, $extra);
                $q = array_filter($q, fn($v) => $v !== null && $v !== '');
                return $base . '/admin/statements/?' . http_build_query($q);
            };
            ?>
            <a href="<?= $build_url(['status'=>null]) ?>" class="pill <?= $status_filter==='all'?'active':'' ?>">All</a>
            <a href="<?= $build_url(['status'=>'unpaid']) ?>" class="pill <?= $status_filter==='unpaid'?'active':'' ?>">Unpaid only</a>
            <a href="<?= $build_url(['status'=>'paid']) ?>" class="pill <?= $status_filter==='paid'?'active':'' ?>">Paid only</a>
        </div>

        <form method="GET" style="display:inline">
            <input type="hidden" name="company" value="<?= htmlspecialchars($selected_company) ?>">
            <?php if ($status_filter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            <select name="year" class="year-select" onchange="this.form.submit()">
                <option value="">All years</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="invoices-table">
        <?php if (empty($detail_invoices)): ?>
            <div class="empty-state">
                No invoices found for this view.
                <?php if ($status_filter !== 'all' || $year): ?>
                    <br><a href="<?= $base ?>/admin/statements/?company=<?= htmlspecialchars($selected_company) ?>" style="color:#dc2626;font-weight:600;text-decoration:none">Clear filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="inv-list">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer / Vehicle</th>
                        <th>Claim Ref</th>
                        <th>Issued</th>
                        <th class="amt">Total</th>
                        <th class="amt">Paid</th>
                        <th class="amt">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detail_invoices as $inv):
                        $outstanding = (float)$inv['total'] - (float)$inv['amount_paid'];
                        $is_overdue = in_array($inv['payment_status'], ['unpaid','partial']) && (int)$inv['age_days'] > 21;
                    ?>
                    <tr>
                        <td>
                            <div class="inv-num">
                                <a href="<?= $base ?>/admin/invoices/print.php?id=<?= (int)$inv['id'] ?>" target="_blank">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                </a>
                            </div>
                            <div class="inv-meta">
                                <a href="<?= $base ?>/admin/invoices/edit.php?id=<?= (int)$inv['id'] ?>" style="color:#64748b;text-decoration:none">Edit</a>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($inv['customer_name'] ?: '—') ?></div>
                            <div class="inv-meta"><?= htmlspecialchars($inv['vehicle_info'] ?: '') ?></div>
                            <?php if (!empty($inv['registration'])): ?>
                                <div class="inv-meta" style="color:#475569;font-weight:600">Reg <?= htmlspecialchars($inv['registration']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($inv['claim_reference'] ?: '—') ?></td>
                        <td>
                            <?= $inv['issued_at'] ? date('d M Y', strtotime($inv['issued_at'])) : '—' ?>
                            <?php if ($is_overdue): ?>
                                <div class="inv-meta" style="color:#dc2626;font-weight:600"><?= (int)$inv['age_days'] ?>d old</div>
                            <?php endif; ?>
                        </td>
                        <td class="amt"><?= $fmt($inv['total']) ?></td>
                        <td class="amt"><?= $fmt($inv['amount_paid']) ?></td>
                        <td class="amt <?= $outstanding > 0 ? 'due' : '' ?>" style="<?= $outstanding > 0 ? 'color:#dc2626;font-weight:700' : 'color:#16a34a' ?>">
                            <?= $fmt($outstanding) ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($inv['payment_status']) ?>">
                                <?= htmlspecialchars($inv['payment_status']) ?>
                            </span>
                            <?php if ($is_overdue): ?><span class="overdue-flag">OVERDUE</span><?php endif; ?>
                            <div class="pay-action">
                                <?php if ($inv['payment_status'] === 'paid'): ?>
                                    <button type="button" class="pay-btn to-unpaid"
                                        data-id="<?= (int)$inv['id'] ?>" data-action="mark_unpaid"
                                        data-num="<?= htmlspecialchars($inv['invoice_number']) ?>" data-word="unpaid">↩ Mark unpaid</button>
                                <?php else: ?>
                                    <button type="button" class="pay-btn to-paid"
                                        data-id="<?= (int)$inv['id'] ?>" data-action="mark_paid"
                                        data-num="<?= htmlspecialchars($inv['invoice_number']) ?>" data-word="paid">✓ Mark paid</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ════════ LIST VIEW ════════ -->

    <div class="page-head">
        <div>
            <h2>Statements</h2>
            <div class="sub">Outstanding balances by insurance company. Click any card to see details.</div>
        </div>
        <form method="GET" style="display:inline">
            <select name="year" class="year-select" onchange="this.form.submit()">
                <option value="">All years</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($review_open > 0): ?>
        <a class="review-banner" href="<?= $base ?>/admin/statements/review.php">
            <span class="rb-icon">🧾</span>
            <span class="rb-text">
                <span class="rb-title">Imported statements need reconciling</span>
                <span class="rb-sub">Some lines came in with a missing invoice number, an estimated date, or a part-payment to confirm.</span>
            </span>
            <span class="rb-count"><?= (int)$review_open ?></span>
            <span class="rb-go">Reconcile →</span>
        </a>
    <?php endif; ?>

    <div class="companies-grid">
        <?php foreach ($summary as $co):
            $is_others = ($co['id'] === null);
            $card_id   = $is_others ? 'others' : (int)$co['id'];
            $card_class = $is_others ? 'others' : ($co['outstanding'] > 0 ? 'has-outstanding' : ($co['invoice_count'] > 0 ? 'all-clear' : 'empty'));
        ?>
        <a href="<?= $base ?>/admin/statements/?company=<?= $card_id ?><?= $year ? '&year='.$year : '' ?>"
           class="co-card <?= $card_class ?>">
            <div class="co-name"><?= htmlspecialchars($co['name']) ?></div>
            <div class="co-row"><span>Invoices</span> <strong><?= (int)$co['invoice_count'] ?></strong></div>
            <div class="co-row"><span>Paid</span> <strong><?= (int)$co['paid_count'] ?></strong></div>
            <div class="co-row"><span>Unpaid</span> <strong><?= (int)$co['unpaid_count'] ?></strong></div>
            <div class="co-row"><span>Total billed</span> <strong><?= $fmt($co['total_billed']) ?></strong></div>
            <div class="co-outstanding">
                <span class="co-outstanding-label">Outstanding</span>
                <span class="co-outstanding-value <?= $co['outstanding'] > 0 ? 'due' : 'zero' ?>">
                    <?= $fmt($co['outstanding']) ?>
                </span>
            </div>
            <?php if ($co['overdue_count'] > 0): ?>
                <div class="co-overdue-tag">⚠ <?= (int)$co['overdue_count'] ?> overdue · <?= $fmt($co['overdue_amount']) ?></div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Manage Companies ── -->
    <div class="manage-card">
        <h3>Manage Insurance Companies</h3>
        <form method="POST" class="manage-add">
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="New company name (e.g. Old Mutual)" required maxlength="100">
            <button type="submit" class="btn primary">+ Add Company</button>
        </form>
        <div class="manage-list">
            <?php foreach ($all_companies as $co): ?>
            <div class="manage-item <?= !$co['is_active'] ? 'inactive' : '' ?>">
                <span><?= htmlspecialchars($co['name']) ?><?= !$co['is_active'] ? ' (hidden)' : '' ?></span>
                <form method="POST" onsubmit="return confirm('<?= $co['is_active'] ? 'Hide' : 'Reactivate' ?> this company?')">
                    <input type="hidden" name="action" value="<?= $co['is_active'] ? 'deactivate' : 'reactivate' ?>">
                    <input type="hidden" name="company_id" value="<?= (int)$co['id'] ?>">
                    <button type="submit"><?= $co['is_active'] ? 'Hide' : 'Reactivate' ?></button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <p style="font-size:11.5px;color:#94a3b8;margin-top:14px;line-height:1.55">
            <strong>Note:</strong> hiding a company keeps existing invoices linked to it (so its historical statement stays intact),
            but removes it from the dropdown when creating new invoices. Use Reactivate to bring it back.
        </p>
    </div>

<?php endif; ?>

</div>

<script>
const BASE = <?= json_encode($base) ?>;
document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.pay-btn');
    if (!btn) return;
    const { id, action, num, word } = btn.dataset;
    if (!confirm('Mark invoice ' + num + ' as ' + word.toUpperCase() + '?')) return;
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = '…';
    try {
        const r = await fetch(BASE + '/admin/invoices/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action, id })
        });
        const res = await r.json();
        if (res.success) { location.reload(); }
        else { alert(res.message || 'Could not update the invoice.'); btn.disabled = false; btn.textContent = orig; }
    } catch (err) {
        alert('Network error — please try again.'); btn.disabled = false; btn.textContent = orig;
    }
});
</script>

</body>
</html>
