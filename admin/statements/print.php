<?php
// admin/statements/print.php — Statement of Account PDF view
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

// ── Required: company ID (numeric — not "others") ──
$company_id = isset($_GET['company']) && is_numeric($_GET['company']) ? (int)$_GET['company'] : 0;
if ($company_id <= 0) {
    header("Location: " . $base . "/admin/statements/");
    exit;
}

$company = wm_ins_company_get($conn, $company_id);
if (!$company) {
    header("Location: " . $base . "/admin/statements/");
    exit;
}

// ── Filters carried over from the page ──
$year          = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : null;
$status_filter = in_array($_GET['status'] ?? '', ['unpaid', 'paid']) ? $_GET['status'] : 'all';

// ── Fetch data ──
$invoices = wm_ins_invoices_for_company($conn, $company_id, $year, $status_filter);
$summary_rows = wm_ins_statements_summary($conn, $year);
$totals = null;
foreach ($summary_rows as $row) {
    if ($row['id'] === $company_id) { $totals = $row; break; }
}
if (!$totals) {
    // Defensive fallback
    $totals = [
        'invoice_count' => 0, 'paid_count' => 0, 'unpaid_count' => 0, 'overdue_count' => 0,
        'total_billed' => 0, 'total_paid' => 0, 'outstanding' => 0, 'overdue_amount' => 0,
    ];
}

// Aging breakdown (always reflects current outstanding, applies year filter)
$aging = wm_ins_aging_breakdown($conn, $company_id, $year);

// Statement metadata
$statement_number = 'STMT-' . $company_id . '-' . date('Ymd');
$statement_date   = date('d M Y');
$period_label     = $year ? (string)$year : 'All time';

$fmt = function($n) { return wm_invoice_format_mwk($n); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statement — <?= htmlspecialchars($company['name']) ?> — <?= $statement_number ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { overflow-x: hidden; }   /* no sideways scroll/clip on phones */
    body {
        font-family: 'Sora', -apple-system, system-ui, sans-serif;
        background: #f1f5f9;
        color: #0f172a;
        line-height: 1.5;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap');

    /* ── Top toolbar (screen only) ── */
    .toolbar {
        background: #fff;
        padding: 16px 24px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px;
        border-bottom: 1px solid #e2e8f0;
        position: sticky; top: 0; z-index: 10;
    }
    .toolbar-left a { color: #64748b; text-decoration: none; font-size: 13.5px; font-weight: 600; }
    .toolbar-left a:hover { color: #0f172a; }
    .toolbar-right { display: flex; gap: 10px; }
    .t-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 10px 16px; border-radius: 10px;
        font-weight: 700; font-size: 13.5px; font-family: 'Sora', sans-serif;
        text-decoration: none; cursor: pointer; border: none;
    }
    .t-btn.dark { background: #0f172a; color: #fff; }
    .t-btn.dark:hover { background: #1e293b; }
    .t-btn-link {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 12px; border-radius: 8px;
        font-weight: 600; font-size: 12.5px;
        color: #64748b; text-decoration: none;
    }
    .t-btn-link:hover { color: #0f172a; background: #f8fafc; }

    /* ── Paper ── */
    .paper {
        max-width: 820px;
        margin: 0 auto;
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 14px 50px rgba(15,23,42,0.10);
        padding: 32px 44px 36px;
        margin-top: 16px;
        margin-bottom: 30px;
    }
    @media screen and (max-width: 680px) { .paper { padding: 22px 22px 28px; } }

    /* ── Letterhead (mirrors invoice print view) ── */
    .letterhead {
        display: grid;
        grid-template-columns: 120px 180px 150px 1fr 64px;
        gap: 14px;
        align-items: center;
        padding-bottom: 10px;
        border-bottom: 2.5px solid #dc2626;
        margin-bottom: 18px;
    }
    @media screen and (max-width: 680px) {
        /* Horizontal on phones: big logo left, contact details filling the right (logo spans rows) */
        .letterhead { grid-template-columns: auto minmax(0,1fr); column-gap: 16px; row-gap: 6px; align-items: center; }
        .letterhead .lh-truck { display: none; }
        .lh-logo { grid-row: 1 / span 3; align-self: center; }
    }
    .lh-logo { display: flex; align-items: center; }
    .lh-logo img { width: 115px; max-width: 100%; height: auto; object-fit: contain; }
    @media screen and (max-width: 680px) {
        .lh-logo img { width: 120px; max-width: 34vw; }
        .lh-deal, .lh-block { font-size: 10px; line-height: 1.5; white-space: normal; }
    }
    .lh-deal { font-size: 9.5px; line-height: 1.55; color: #334155; min-width: 0; overflow: hidden; }
    .lh-deal-title { font-weight: 700; color: #0f172a; margin-bottom: 4px; font-size: 10px; letter-spacing: 0.01em; }
    .lh-block { font-size: 9.5px; line-height: 1.55; color: #334155; min-width: 0; white-space: nowrap; }
    .lh-block strong { color: #0f172a; }
    .lh-truck { display: flex; flex-direction: column; justify-content: flex-start; align-items: center; gap: 2px; }
    .lh-qr { width: 62px; height: 62px; display: block; object-fit: contain; }
    .lh-qr-caption { font-size: 7.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; text-align: center; line-height: 1; }

    /* ── Big title ── */
    .doc-title-main {
        text-align: center;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: #0f172a;
        margin-bottom: 4px;
        text-transform: uppercase;
    }
    .doc-title-sub {
        text-align: center;
        font-size: 16px;
        font-weight: 700;
        color: #dc2626;
        margin-bottom: 18px;
        letter-spacing: 0.01em;
    }

    /* ── Meta row (statement #, date, period) ── */
    .meta-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 16px;
        font-size: 12px;
    }
    @media screen and (max-width: 600px) { .meta-row { grid-template-columns: 1fr; } }
    .meta-block { color: #334155; }
    .meta-block strong { color: #0f172a; display: inline-block; min-width: 100px; }
    .meta-block.right { text-align: right; }
    .meta-block.right strong { min-width: auto; margin-right: 6px; }
    .greeting { font-style: italic; color: #475569; font-size: 12px; margin-bottom: 14px; }

    /* ── Summary metrics box ── */
    .summary-box {
        border: 1.5px solid #0f172a;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 18px;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        background: #fff;
    }
    @media screen and (max-width: 600px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
    .summary-cell {
        padding: 10px 12px;
        border-right: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }
    .summary-cell:nth-child(4n) { border-right: none; }
    .summary-cell:nth-last-child(-n+4) { border-bottom: none; }
    @media screen and (max-width: 600px) {
        .summary-cell:nth-child(2n) { border-right: none; }
        .summary-cell:nth-child(4n) { border-right: 1px solid #e2e8f0; }
        .summary-cell { border-bottom: 1px solid #e2e8f0; }
    }
    .summary-label {
        font-size: 10px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.05em;
        margin-bottom: 4px;
    }
    .summary-value {
        font-size: 14px; font-weight: 700; color: #0f172a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .summary-value.due { color: #dc2626; font-weight: 800; }
    .summary-value.paid { color: #16a34a; }

    /* ── Invoices table ── */
    table.inv-list {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        border: 1.5px solid #cbd5e1;
        font-size: 11px;
        table-layout: fixed;            /* enforce column widths so browser stops auto-sizing */
    }
    table.inv-list th {
        background: #0f172a; color: #fff;
        padding: 7px 8px; text-align: left;
        font-size: 9.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.04em;
        border-right: 1px solid #1e293b;
        white-space: nowrap;
    }
    table.inv-list th:last-child { border-right: none; }
    table.inv-list th.amt, table.inv-list td.amt { text-align: right; }
    table.inv-list td {
        padding: 6px 8px;
        font-size: 11px;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #e2e8f0;
        vertical-align: middle;
        overflow: hidden;               /* belt-and-braces: no cell shall escape */
    }
    table.inv-list td:last-child { border-right: none; }
    table.inv-list tbody tr:last-child td { border-bottom: none; }

    /* Column widths — PERCENTAGES that sum to exactly 100%.
       Fixed pixel widths kept fighting the paper. Percentages don't. */
    table.inv-list .col-num     { width: 12%; white-space: nowrap; }
    table.inv-list .col-date    { width: 10%; white-space: nowrap; }
    table.inv-list .col-claim   { width: 13%; word-break: break-all; font-size: 10px; }
    table.inv-list .col-cust    { width: 30%; }
    table.inv-list .col-totpaid { width: 17%; white-space: nowrap; }
    table.inv-list .col-out     { width: 18%; white-space: nowrap; }
    /* Total: 100% exactly */

    /* Stacked Paid sub-line inside Total column */
    .paid-sub {
        font-size: 9.5px;
        color: #64748b;
        margin-top: 2px;
        font-weight: 500;
    }
    .paid-sub-amt { color: #16a34a; font-weight: 600; }
    .paid-sub-grand {
        font-size: 10px;
        color: #cbd5e1;
        font-weight: 500;
        margin-top: 2px;
    }

    /* Outstanding column — visual emphasis, this is the answer */
    table.inv-list th.col-out,
    table.inv-list td.col-out {
        background: #fef2f2;
        border-left: 2px solid #fecaca;
    }
    table.inv-list th.col-out {
        background: #7f1d1d;
        color: #fff;
        border-left: 2px solid #7f1d1d;
    }
    table.inv-list td.col-out {
        font-weight: 700;
        color: #dc2626;
    }
    table.inv-list td.col-out.due {
        color: #dc2626;
    }
    /* Grand-row outstanding stays gold-on-dark */
    table.inv-list .grand-row td.col-out,
    table.inv-list tr.grand-row td:last-child {
        background: #0f172a !important;
        color: #fbbf24 !important;
        border-left: 2px solid #1e293b;
    }

    .inv-num { font-weight: 700; color: #0f172a; font-size: 10.5px; line-height: 1.25; }
    .inv-cust { font-weight: 600; font-size: 11px; }
    .inv-veh  { font-size: 10px; color: #64748b; }
    .inv-reg  { font-size: 10px; color: #475569; font-weight: 600; letter-spacing: 0.02em; }
    .inv-reg::before { content: "Reg "; color: #94a3b8; font-weight: 500; }
    .amt { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .amt.due { color: #dc2626; font-weight: 700; }
    .amt.paid-cell { color: #16a34a; }
    .status-tag {
        display: inline-block;
        padding: 1px 5px; border-radius: 3px;
        font-size: 8px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.03em;
        margin-top: 2px;
    }
    .status-paid    { background: #dcfce7; color: #166534; }
    .status-partial { background: #fef3c7; color: #92400e; }
    .status-unpaid  { background: #fee2e2; color: #991b1b; }
    .age-overdue { color: #dc2626; font-weight: 700; font-size: 10px; }

    .grand-row td {
        background: #0f172a !important;
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        padding: 9px 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-right: 1px solid #1e293b;
    }
    .grand-row td:last-child {
        color: #fbbf24;
        white-space: nowrap;
        border-right: none;
    }

    /* ── Aging breakdown ── */
    .aging-block {
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    .aging-title {
        background: #f8fafc;
        padding: 8px 14px;
        font-size: 10.5px;
        font-weight: 700;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        border-bottom: 1px solid #cbd5e1;
    }
    .aging-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        background: #fff;
    }
    @media screen and (max-width: 600px) { .aging-grid { grid-template-columns: repeat(2, 1fr); } }
    .aging-cell {
        padding: 10px 12px;
        border-right: 1px solid #e2e8f0;
        text-align: center;
    }
    .aging-cell:last-child { border-right: none; background: #fef2f2; }
    .aging-cell-label {
        font-size: 9.5px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.04em;
        margin-bottom: 4px;
    }
    .aging-cell-amount {
        font-size: 13px; font-weight: 700; color: #0f172a;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .aging-cell:last-child .aging-cell-amount { color: #dc2626; font-weight: 800; }
    .aging-cell-count {
        font-size: 10px; color: #94a3b8;
        margin-top: 2px;
    }

    /* ── Chaser message ── */
    .chaser-block {
        background: #fffbeb;
        border-left: 3px solid #f59e0b;
        padding: 11px 14px;
        border-radius: 0 8px 8px 0;
        margin-bottom: 14px;
        font-size: 12px;
        color: #78350f;
        line-height: 1.55;
    }
    .chaser-block strong { color: #92400e; font-weight: 700; }

    /* ── Payment details (reused from invoice view) ── */
    .pay-details {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 14px;
    }
    .pay-title {
        background: #0f172a; color: #fff;
        font-size: 10.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.08em;
        padding: 6px 13px;
    }
    .pay-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        background: #fff;
    }
    @media screen and (max-width: 560px) { .pay-grid { grid-template-columns: 1fr; } }
    .pay-method {
        padding: 10px 14px;
        border-right: 1px solid #e2e8f0;
    }
    .pay-method:last-child { border-right: none; }
    @media screen and (max-width: 560px) {
        .pay-method { border-right: none; border-bottom: 1px solid #e2e8f0; }
        .pay-method:last-child { border-bottom: none; }
    }
    .pay-method-label {
        font-size: 9.5px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.06em;
        margin-bottom: 4px;
    }
    .pay-method-body { font-size: 11.5px; line-height: 1.5; color: #334155; }
    .pay-method-body strong { color: #0f172a; font-weight: 700; }
    .pay-method-body .mono {
        font-family: 'SF Mono', Menlo, monospace;
        font-weight: 600; color: #dc2626;
        letter-spacing: 0.02em;
    }
    .pay-ref {
        padding: 7px 14px;
        background: #fffbeb;
        border-top: 1px solid #fde68a;
        font-size: 11px; color: #78350f;
    }
    .pay-ref strong { color: #92400e; font-family: 'SF Mono', Menlo, monospace; }

    .empty-state {
        padding: 30px 20px; text-align: center; color: #94a3b8;
        font-size: 13px;
    }

    /* ── MOBILE (screen only) — the 6-column statement table is inherently wide,
       so let it scroll inside its own container rather than clip the figures. ── */
    @media screen and (max-width: 680px) {
        body { padding: 0; }
        .inv-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table.inv-list { table-layout: auto; min-width: 620px; }
        table.inv-list td { overflow: visible; }   /* auto layout sizes to content; nothing to clip */
    }

    /* ─── PRINT STYLES ─── */
    @media print {
        /* CRITICAL: pin the print layout to a real A4 width. Otherwise iOS Safari lays the
           print out at the phone viewport width (~390px), wrapping the letterhead/table tall. */
        html, body { width: 210mm; background: #fff; padding: 0; margin: 0; }
        .toolbar, .no-print { display: none !important; }
        @page { size: A4 portrait; margin: 0; }

        .paper {
            box-shadow: none; border-radius: 0;
            padding: 7mm 9mm 5mm;
            max-width: none;
            width: 210mm;              /* A4 width — never % (collapses to phone width on iOS) */
            margin: 0 auto;
        }

        .letterhead { padding-bottom: 4px; margin-bottom: 6px; gap: 8px; }
        .lh-deal, .lh-block { font-size: 8px; line-height: 1.35; }
        .lh-deal-title { font-size: 8.5px; margin-bottom: 2px; }
        .lh-logo img { width: 95px; }
        .lh-qr { width: 48px; height: 48px; }
        .lh-qr-caption { font-size: 6px; }

        .doc-title-main { font-size: 15px; margin-bottom: 2px; }
        .doc-title-sub { font-size: 12px; margin-bottom: 6px; }

        .meta-row { margin-bottom: 5px; font-size: 9.5px; gap: 14px; }
        .greeting { font-size: 9.5px; margin-bottom: 5px; line-height: 1.35; }

        .summary-box { margin-bottom: 6px; }
        .summary-cell { padding: 5px 8px; }
        .summary-label { font-size: 8px; margin-bottom: 2px; }
        .summary-value { font-size: 10.5px; }

        .aging-title { padding: 3px 10px; font-size: 8.5px; }
        .aging-cell { padding: 5px 7px; }
        .aging-cell-label { font-size: 7.5px; margin-bottom: 2px; }
        .aging-cell-amount { font-size: 10px; }
        .aging-cell-count { font-size: 8px; margin-top: 1px; }
        .aging-block { margin-bottom: 6px; }

        /* Table — portrait, 6 columns, readable sizes.
           Column widths use % defined outside @media print; they scale automatically. */
        table.inv-list { font-size: 10px; margin-bottom: 6px; }
        table.inv-list th { padding: 4px 6px; font-size: 8.5px; }
        table.inv-list td { padding: 3px 6px; font-size: 9.5px; }
        table.inv-list .col-claim   { font-size: 8.5px; }
        table.inv-list .inv-num     { font-size: 9.5px; }
        table.inv-list .inv-cust    { font-size: 9.5px; }
        table.inv-list .inv-veh     { font-size: 8.5px; }
        table.inv-list .inv-reg     { font-size: 8.5px; }
        .paid-sub                   { font-size: 8px; margin-top: 1px; }
        .paid-sub-grand             { font-size: 8.5px; margin-top: 1px; }
        .status-tag { font-size: 7px; padding: 1px 4px; margin-top: 1px; }
        .age-overdue { font-size: 8.5px; }
        .grand-row td { padding: 5px 8px; font-size: 11px; }

        .chaser-block { padding: 6px 10px; font-size: 9.5px; margin-bottom: 5px; line-height: 1.4; }

        .pay-details { margin-bottom: 0; }
        .pay-title { padding: 3px 10px; font-size: 8.5px; }
        .pay-method { padding: 5px 10px; }
        .pay-method-label { font-size: 7.5px; margin-bottom: 2px; }
        .pay-method-body { font-size: 9px; line-height: 1.35; }
        .pay-ref { padding: 3px 10px; font-size: 8.5px; }

        /* Keep groups together — no awkward page breaks */
        table.inv-list { page-break-inside: auto; }
        table.inv-list tr { page-break-inside: avoid; page-break-after: auto; }
        table.inv-list thead { display: table-header-group; }
        table.inv-list tfoot { display: table-footer-group; }
        .grand-row { page-break-inside: avoid; }
        .letterhead, .doc-title-main, .doc-title-sub, .meta-row,
        .summary-box, .aging-block, .chaser-block, .pay-details {
            page-break-inside: avoid;
        }
    }
</style>
</head>
<body>

<!-- Toolbar (screen only) -->
<div class="toolbar no-print">
    <div class="toolbar-left">
        <a href="<?= $base ?>/admin/statements/?company=<?= (int)$company_id ?><?= $year ? '&year='.$year : '' ?><?= $status_filter!=='all' ? '&status='.$status_filter : '' ?>">← Back to statement</a>
    </div>
    <div class="toolbar-right">
        <button type="button" class="t-btn dark" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
</div>

<!-- Paper -->
<div class="paper">

    <!-- ─── LETTERHEAD ─── -->
    <div class="letterhead">
        <div class="lh-logo">
            <img src="<?= $base ?>/assets/images/logo-letterhead.png" alt="Wiyule & F A Motors">
        </div>
        <div class="lh-deal">
            <div class="lh-deal-title">Dealers in:</div>
            <div class="services">
                Panel Beating &amp; Spray Painting<br>
                Towing &amp; Vehicle Recovery<br>
                Vehicle Mechanics<br>
                Wrapping<br>
                Tinting
            </div>
        </div>
        <div class="lh-block">
            <strong>Plot No: 424</strong><br>
            Jackson Road<br>
            Nyambadwe<br>
            P.O. Box 31727<br>
            Chichiri, Blantyre 3
        </div>
        <div class="lh-block">
            +265 996 834 038<br>
            +265 993 575 111<br>
            <strong style="color:#dc2626">wiyulemotors@gmail.com</strong><br>
            <strong style="color:#dc2626">wiyulemotors.co.mw</strong>
        </div>
        <div class="lh-truck">
            <img src="<?= $base ?>/assets/images/wiyule_qr.png" alt="Scan to visit wiyulemotors.co.mw" class="lh-qr">
            <div class="lh-qr-caption">Scan to visit</div>
        </div>
    </div>

    <!-- ─── TITLE ─── -->
    <div class="doc-title-main">Statement of Account</div>
    <div class="doc-title-sub"><?= htmlspecialchars($company['name']) ?></div>

    <!-- ─── META ─── -->
    <div class="meta-row">
        <div class="meta-block">
            <strong>Statement #:</strong> <?= htmlspecialchars($statement_number) ?><br>
            <strong>Period:</strong> <?= htmlspecialchars($period_label) ?>
            <?php if ($status_filter !== 'all'): ?>
                <br><strong>Showing:</strong> <?= ucfirst($status_filter) ?> invoices only
            <?php endif; ?>
        </div>
        <div class="meta-block right">
            <strong>Date issued:</strong> <?= htmlspecialchars($statement_date) ?>
        </div>
    </div>

    <div class="greeting">
        Dear Accounts Team,<br>
        Please find below a statement of the account between Wiyule &amp; F A Motors and <?= htmlspecialchars($company['name']) ?>.
    </div>

    <!-- ─── SUMMARY METRICS ─── -->
    <div class="summary-box">
        <div class="summary-grid">
            <div class="summary-cell">
                <div class="summary-label">Invoices Issued</div>
                <div class="summary-value"><?= (int)$totals['invoice_count'] ?></div>
            </div>
            <div class="summary-cell">
                <div class="summary-label">Amount Billed</div>
                <div class="summary-value"><?= $fmt($totals['total_billed']) ?></div>
            </div>
            <div class="summary-cell">
                <div class="summary-label">Amount Paid</div>
                <div class="summary-value paid"><?= $fmt($totals['total_paid']) ?></div>
            </div>
            <div class="summary-cell">
                <div class="summary-label">Balance Outstanding</div>
                <div class="summary-value due"><?= $fmt($totals['outstanding']) ?></div>
            </div>
        </div>
    </div>

    <!-- ─── AGING BREAKDOWN (only if there's anything outstanding) ─── -->
    <?php if ($aging['total']['count'] > 0): ?>
    <div class="aging-block">
        <div class="aging-title">Outstanding Balance — Aging Breakdown</div>
        <div class="aging-grid">
            <div class="aging-cell">
                <div class="aging-cell-label">Current (0–30 days)</div>
                <div class="aging-cell-amount"><?= $fmt($aging['current']['amount']) ?></div>
                <div class="aging-cell-count"><?= (int)$aging['current']['count'] ?> invoice<?= $aging['current']['count'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="aging-cell">
                <div class="aging-cell-label">31–60 days</div>
                <div class="aging-cell-amount"><?= $fmt($aging['days_31_60']['amount']) ?></div>
                <div class="aging-cell-count"><?= (int)$aging['days_31_60']['count'] ?> invoice<?= $aging['days_31_60']['count'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="aging-cell">
                <div class="aging-cell-label">61–90 days</div>
                <div class="aging-cell-amount"><?= $fmt($aging['days_61_90']['amount']) ?></div>
                <div class="aging-cell-count"><?= (int)$aging['days_61_90']['count'] ?> invoice<?= $aging['days_61_90']['count'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="aging-cell">
                <div class="aging-cell-label">Over 90 days</div>
                <div class="aging-cell-amount"><?= $fmt($aging['over_90']['amount']) ?></div>
                <div class="aging-cell-count"><?= (int)$aging['over_90']['count'] ?> invoice<?= $aging['over_90']['count'] === 1 ? '' : 's' ?></div>
            </div>
            <div class="aging-cell">
                <div class="aging-cell-label">Total Due</div>
                <div class="aging-cell-amount"><?= $fmt($aging['total']['amount']) ?></div>
                <div class="aging-cell-count"><?= (int)$aging['total']['count'] ?> invoice<?= $aging['total']['count'] === 1 ? '' : 's' ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── INVOICE TABLE ─── -->
    <?php if (empty($invoices)): ?>
        <div class="empty-state">No invoices in this period for the selected filter.</div>
    <?php else: ?>
        <div class="inv-scroll">
        <table class="inv-list">
            <thead>
                <tr>
                    <th class="col-num">Invoice #</th>
                    <th class="col-date">Date</th>
                    <th class="col-claim">Claim Ref</th>
                    <th class="col-cust">Customer / Vehicle</th>
                    <th class="amt col-totpaid">Total <span style="opacity:0.65;font-weight:500">/ Paid</span></th>
                    <th class="amt col-out">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $filtered_total = 0;
                $filtered_paid = 0;
                $filtered_out = 0;
                foreach ($invoices as $inv):
                    $outstanding = (float)$inv['total'] - (float)$inv['amount_paid'];
                    $is_overdue = in_array($inv['payment_status'], ['unpaid','partial']) && (int)$inv['age_days'] > 21;
                    $filtered_total += (float)$inv['total'];
                    $filtered_paid  += (float)$inv['amount_paid'];
                    $filtered_out   += $outstanding;
                ?>
                <tr>
                    <td class="col-num">
                        <div class="inv-num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                        <span class="status-tag status-<?= htmlspecialchars($inv['payment_status']) ?>"><?= htmlspecialchars($inv['payment_status']) ?></span>
                    </td>
                    <td class="col-date">
                        <?= $inv['issued_at'] ? date('d M Y', strtotime($inv['issued_at'])) : '—' ?>
                        <?php if ($is_overdue): ?>
                            <div class="age-overdue"><?= (int)$inv['age_days'] ?>d old</div>
                        <?php endif; ?>
                    </td>
                    <td class="col-claim"><?= htmlspecialchars($inv['claim_reference'] ?: '—') ?></td>
                    <td class="col-cust">
                        <div class="inv-cust"><?= htmlspecialchars($inv['customer_name'] ?: '—') ?></div>
                        <?php if (!empty($inv['vehicle_info'])): ?>
                            <div class="inv-veh"><?= htmlspecialchars($inv['vehicle_info']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($inv['registration'])): ?>
                            <div class="inv-reg"><?= htmlspecialchars($inv['registration']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="amt col-totpaid">
                        <div><?= $fmt($inv['total']) ?></div>
                        <div class="paid-sub">Paid: <span class="paid-sub-amt"><?= $fmt($inv['amount_paid']) ?></span></div>
                    </td>
                    <td class="amt col-out <?= $outstanding > 0 ? 'due' : '' ?>"><?= $fmt($outstanding) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="grand-row">
                    <td colspan="4">Totals (<?= count($invoices) ?> invoice<?= count($invoices)===1?'':'s' ?>)</td>
                    <td class="amt col-totpaid">
                        <div><?= $fmt($filtered_total) ?></div>
                        <div class="paid-sub-grand">Paid: <?= $fmt($filtered_paid) ?></div>
                    </td>
                    <td class="amt col-out"><?= $fmt($filtered_out) ?></td>
                </tr>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <!-- ─── CHASER MESSAGE ─── -->
    <?php if ($totals['outstanding'] > 0): ?>
    <div class="chaser-block">
        <strong>Kindly settle the outstanding balance of <?= $fmt($totals['outstanding']) ?> at your earliest convenience.</strong>
        Our standard payment terms are 21 days from the date of invoice; a 5% late fee may apply to amounts unpaid beyond this period.
        Please use the invoice numbers above as your payment reference. We are happy to discuss any queries you may have on
        +265 993 575 111 or wiyulemotors@gmail.com.
    </div>
    <?php else: ?>
    <div class="chaser-block" style="background:#dcfce7;border-left-color:#16a34a;color:#166534">
        <strong>Your account is fully up to date.</strong> Thank you for your continued partnership with Wiyule &amp; F A Motors.
    </div>
    <?php endif; ?>

    <!-- ─── PAYMENT DETAILS ─── -->
    <?php if ($totals['outstanding'] > 0): ?>
    <div class="pay-details">
        <div class="pay-title">Payment Details</div>
        <div class="pay-grid">
            <div class="pay-method">
                <div class="pay-method-label">Bank Transfer</div>
                <div class="pay-method-body">
                    <strong>National Bank of Malawi</strong><br>
                    Account: <span class="mono">1003301911</span><br>
                    Account name: Wiyule Motors
                </div>
            </div>
            <div class="pay-method">
                <div class="pay-method-label">Airtel Money</div>
                <div class="pay-method-body">
                    <span class="mono">+265 993 575 111</span><br>
                    Name: Wiyule Motors
                </div>
            </div>
        </div>
        <div class="pay-ref">
            Please use the invoice number(s) listed above as your payment reference so payments are correctly allocated.
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
