<?php
// admin/invoices/print.php — Branded printable invoice / quotation
// Designed to mirror Wiyule Motors' traditional letterhead, modernized.
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/invoices.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: " . $base . "/admin/invoices/"); exit; }

$data = wm_invoice_fetch($conn, $id);
if (!$data) { header("Location: " . $base . ($_SESSION['admin_id'] ?? false ? "/admin/invoices/" : "/pages/dashboard.php")); exit; }

$invoice  = $data['invoice'];
$items    = $data['items'];
$is_quote = ($invoice['type'] === 'quotation');
$groups   = wm_invoice_group_items($items);

// ── Access control: admins may view any document; a logged-in customer may view
//    ONLY their own invoice (via the dashboard). Everyone else is bounced. ──
$is_admin = isset($_SESSION['admin_id']);
$is_owner = !$is_admin && isset($_SESSION['user_id'])
         && wm_customer_owns_invoice($conn, $invoice, (int)$_SESSION['user_id'], $_SESSION['email'] ?? '');
if (!$is_admin && !$is_owner) {
    header("Location: " . $base . (isset($_SESSION['user_id']) ? "/pages/dashboard.php" : "/admin/login.php"));
    exit;
}

// Build WhatsApp text version
function build_whatsapp_text(array $invoice, array $items): string {
    $lines   = [];
    $is_q    = ($invoice['type'] === 'quotation');
    $lines[] = '*' . ($is_q ? 'QUOTATION' : 'INVOICE') . ' — Wiyule & F A Motors*';
    $lines[] = '_' . $invoice['invoice_number'] . '_';
    $lines[] = '';
    if (!empty($invoice['recipient_name'])) {
        $lines[] = 'To: ' . $invoice['recipient_name'];
        $lines[] = 'M/s: ' . $invoice['customer_name'];
    } else {
        $lines[] = 'Customer: ' . $invoice['customer_name'];
    }
    if (!empty($invoice['vehicle_info'])) $lines[] = 'Vehicle: ' . $invoice['vehicle_info'];
    if (!empty($invoice['registration'])) $lines[] = 'Reg No: ' . $invoice['registration'];
    if (!empty($invoice['claim_reference'])) $lines[] = 'Claim Ref: ' . $invoice['claim_reference'];
    $lines[] = 'Date: ' . date('d M Y', strtotime($invoice['issued_at']));
    $lines[] = '';

    $groups = wm_invoice_group_items($items);
    foreach ($groups as $section => $rows) {
        $lines[] = '*' . $section . ':*';
        foreach ($rows as $r) {
            $lines[] = '  • ' . $r['description'] . ' — ' . wm_invoice_format_mwk($r['line_total']);
        }
        $lines[] = '';
    }

    $lines[] = 'Subtotal: ' . wm_invoice_format_mwk($invoice['subtotal']);
    if ((float)$invoice['discount_amount'] > 0) $lines[] = 'Discount: − ' . wm_invoice_format_mwk($invoice['discount_amount']);
    if ($invoice['vat_enabled']) $lines[] = 'VAT (' . rtrim(rtrim(number_format((float)$invoice['vat_rate'], 2), '0'), '.') . '%): ' . wm_invoice_format_mwk($invoice['vat_amount']);
    if ((float)$invoice['less_excess'] > 0) {
        $lines[] = 'Subtotal (incl. VAT): ' . wm_invoice_format_mwk($invoice['subtotal'] - $invoice['discount_amount'] + $invoice['vat_amount']);
        $lines[] = 'Less Excess: − ' . wm_invoice_format_mwk($invoice['less_excess']);
    }
    $lines[] = '*' . ((float)$invoice['less_excess'] > 0 ? 'Net Payable' : 'Total') . ': ' . wm_invoice_format_mwk($invoice['total']) . '*';

    if (!$is_q) {
        $lines[] = '';
        $lines[] = 'Status: ' . ucfirst($invoice['payment_status']);
        if ($invoice['payment_status'] === 'partial') {
            $balance = (float)$invoice['total'] - (float)$invoice['amount_paid'];
            $lines[] = 'Paid: ' . wm_invoice_format_mwk($invoice['amount_paid']);
            $lines[] = 'Balance: ' . wm_invoice_format_mwk($balance);
        }
    }
    $lines[] = '';
    if ($is_q) {
        $lines[] = '_This quotation is valid for 14 days._';
        $lines[] = '_Additional damage identified post stripping will be quoted separately._';
    } else {
        $lines[] = '_Thank you for choosing Wiyule & F A Motors._';
        $lines[] = '_Payment within 21 days is appreciated. 5% late fee applies after this period._';
    }

    return implode("\n", $lines);
}

$whatsapp_text = build_whatsapp_text($invoice, $items);
$phone_intl    = preg_replace('/\D/', '', $invoice['customer_phone']);
if (strlen($phone_intl) > 0 && $phone_intl[0] === '0') $phone_intl = '265' . substr($phone_intl, 1);
$whatsapp_url  = 'https://wa.me/' . $phone_intl . '?text=' . urlencode($whatsapp_text);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
// Browser uses <title> as the default "Save as PDF" filename + print header — build it for easy filing/tracking
$pdf_title = ($is_quote ? 'Quotation ' : 'Invoice ') . $invoice['invoice_number'];
if (!empty($invoice['registration'])) $pdf_title .= ' - Reg ' . $invoice['registration'];
?>
<title><?= htmlspecialchars($pdf_title) ?></title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    html, body { overflow-x:hidden; }   /* never let the paper scroll/clip sideways on phones */
    body {
        font-family:'Sora',sans-serif;
        background:#eef1f5; color:#0f172a;
        min-height:100vh;
        padding:24px 16px 80px;
        -webkit-print-color-adjust:exact; print-color-adjust:exact;  /* keep branded colours in print/PDF */
    }

    /* ── Toolbar (hidden when printing) ── */
    .toolbar {
        max-width:820px; margin:0 auto 18px;
        display:flex; justify-content:space-between;
        flex-wrap:wrap; gap:10px; align-items:center;
    }
    .toolbar-left { font-size:13px; color:#64748b; }
    .toolbar-right { display:flex; gap:8px; flex-wrap:wrap; }
    .t-btn {
        display:inline-flex; align-items:center; gap:6px;
        padding:9px 16px; border-radius:10px;
        font-weight:700; font-size:13px; text-decoration:none;
        font-family:inherit; border:none; cursor:pointer;
        transition:transform 0.15s;
    }
    .t-btn:hover { transform:translateY(-1px); }
    .t-btn.ghost { background:#fff; color:#334155; border:1px solid #e2e8f0; }
    .t-btn.dark  { background:#0f172a; color:#fff; }
    /* Subtle text-link style for the WhatsApp helper — secondary, not primary */
    .t-btn-link {
        display:inline-flex; align-items:center; gap:6px;
        padding:9px 12px; border-radius:8px;
        font-weight:600; font-size:12.5px;
        color:#64748b; text-decoration:none;
        background:transparent;
        font-family:inherit;
        transition:color 0.15s, background 0.15s;
    }
    .t-btn-link:hover { color:#25d366; background:rgba(37,211,102,0.06); }
    .t-btn-link svg { opacity:0.7; }
    .t-btn-link:hover svg { opacity:1; }

    /* ── The "paper" ── */
    .paper {
        max-width:820px; margin:0 auto;
        background:#fff;
        border-radius:6px;
        box-shadow:0 14px 50px rgba(15,23,42,0.10);
        padding:32px 44px 36px;
        position:relative;
        overflow:hidden;
    }
    @media screen and (max-width: 680px) { .paper { padding:22px 22px 28px; } }

    .paper-inner { position:relative; z-index:2; }

    /* ─── HEADER LETTERHEAD ──────────────────────────────── */
    .letterhead {
        display:grid;
        /* Logo (bigger, wide) | Dealers | Address | Phones  (QR moved to footer) */
        grid-template-columns:150px minmax(0,1.2fr) minmax(0,1fr) minmax(0,1fr);
        gap:16px;
        align-items:center;
        padding-bottom:14px;
        border-bottom:3px solid #dc2626;
        margin-bottom:20px;
    }
    .paper.is-quote .letterhead { border-bottom-color:#2563eb; }
    @media screen and (max-width: 680px) {
        /* Horizontal on phones too: big logo on the left, contact details filling the right.
           Logo spans all three info rows so nothing sits in dead space under it. */
        .letterhead {
            grid-template-columns:auto minmax(0,1fr);
            column-gap:16px; row-gap:6px;
            align-items:center;
        }
        .lh-logo { grid-row:1 / span 3; align-self:center; }
    }
    .lh-logo { display:flex; align-items:center; }
    .lh-logo img { width:150px; max-width:100%; height:auto; object-fit:contain; }
    .lh-deal {
        font-size:9.5px; line-height:1.55;
        color:#334155; min-width:0; overflow:hidden;
    }
    .lh-deal-title { font-weight:700; color:#0f172a; margin-bottom:4px; font-size:10px; letter-spacing:0.01em; }
    .lh-deal .services { white-space:normal; }
    .lh-block { font-size:9.5px; line-height:1.6; color:#334155; min-width:0; white-space:nowrap; }
    .lh-block strong { color:#0f172a; }
    @media screen and (max-width: 680px) {
        .lh-deal, .lh-block { font-size:10px; line-height:1.5; }
        .lh-block { white-space:normal; }               /* contact lines wrap if ever needed */
        .lh-logo img { width:120px; max-width:34vw; }
    }

    /* ─── DOCUMENT FOOTER: QR (bottom-left, larger) + thank-you ─── */
    .doc-footer {
        display:flex; align-items:flex-end; justify-content:space-between;
        gap:24px; margin-top:28px; padding-top:18px; border-top:2px solid #e2e8f0;
    }
    .doc-footer-qr { display:flex; align-items:center; gap:14px; }
    .doc-footer-qr img { width:108px; height:108px; object-fit:contain; display:block; border:1px solid #eef2f7; border-radius:8px; padding:4px; }
    .doc-footer-qr .cap { font-size:11.5px; font-weight:700; color:#334155; max-width:130px; line-height:1.45; }
    .doc-footer-qr .cap span { color:#dc2626; }
    .doc-footer-thanks { text-align:right; font-size:12px; color:#64748b; line-height:1.5; }
    .doc-footer-thanks .big { font-size:15px; font-weight:800; color:#0f172a; margin-bottom:3px; }
    @media screen and (max-width: 560px) {
        .doc-footer { flex-direction:column; align-items:center; text-align:center; gap:18px; }
        .doc-footer-thanks { text-align:center; }
    }

    /* ─── Status stamp (creative, subtle) ─── */
    .stamp {
        position:absolute; top:150px; right:46px; z-index:3; pointer-events:none;
        font-size:44px; font-weight:900; letter-spacing:3px; text-transform:uppercase;
        padding:6px 22px; border-radius:12px; transform:rotate(-14deg);
    }
    .stamp.paid { color:rgba(22,163,74,0.20); border:5px solid rgba(22,163,74,0.20); }
    .stamp.void { color:rgba(100,116,139,0.22); border:5px solid rgba(100,116,139,0.22); }
    @media screen and (max-width: 560px) { .stamp { font-size:30px; top:120px; right:20px; } }

    /* ─── BIG TITLE ── */
    .doc-title-main {
        text-align:center;
        font-size:28px;
        font-weight:800;
        letter-spacing:-0.01em;
        color:#0f172a;
        margin-bottom:18px;
        text-transform:uppercase;
    }
    @media screen and (max-width: 680px) { .doc-title-main { font-size:22px; margin-bottom:14px; } }

    /* ─── DOCUMENT META ROW ─── */
    .meta-row {
        display:grid;
        grid-template-columns:1fr auto;
        gap:24px;
        margin-bottom:16px;
    }
    @media screen and (max-width: 680px) {
        .meta-row { grid-template-columns:1fr; }
        /* Once the layout narrows, let the items table scroll rather than ever clip */
        .items-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    }

    .att-block { font-size:12.5px; line-height:1.55; color:#334155; }
    .att-row { display:flex; gap:10px; align-items:flex-start; margin-bottom:5px; }
    .att-row .lbl { font-weight:700; color:#0f172a; min-width:32px; }
    .att-row .val {
        flex:1; padding:5px 11px; border:1.5px solid #cbd5e1; border-radius:4px;
        background:#f8fafc; min-height:26px; font-weight:600; color:#0f172a;
    }
    .att-row .val.plain { background:transparent; border:none; padding:5px 0; }
    .greeting { margin-top:6px; font-style:italic; color:#475569; font-size:12px; }

    .doc-meta { text-align:right; min-width:230px; }
    .doc-meta-row {
        display:flex; align-items:center; justify-content:flex-end;
        gap:10px; margin-bottom:6px; font-size:12.5px;
    }
    .doc-meta-row .lbl { font-weight:700; color:#0f172a; }
    .doc-meta-row .val {
        padding:4px 11px;
        border:1.5px solid #cbd5e1; border-radius:4px;
        background:#f8fafc;
        font-family:'SF Mono',Menlo,monospace;
        font-weight:700; min-width:140px; text-align:left;
        color:<?= $is_quote ? '#0f172a' : '#dc2626' ?>;
    }
    .doc-meta-row .val.plain { background:transparent; border:none; padding:5px 0; min-width:auto; color:#64748b; font-family:'Sora',sans-serif; }
    .vehicle-line { margin-top:6px; font-size:12.5px; color:#334155; font-weight:600; text-align:right; }

    /* ─── ITEMS TABLE ─── */
    table.items {
        width:100%; border-collapse:collapse;
        margin-bottom:6px;
        border:1.5px solid #cbd5e1;
    }
    table.items th {
        background:#0f172a; color:#fff;
        padding:7px 9px; text-align:left;
        font-size:10.5px; font-weight:700;
        text-transform:uppercase; letter-spacing:0.04em;
        border-right:1px solid #1e293b;
    }
    table.items th:last-child { border-right:none; }
    table.items th.text-right, table.items td.text-right { text-align:right; }
    table.items th.text-center, table.items td.text-center { text-align:center; }

    table.items td {
        padding:6px 9px;
        font-size:12px;
        border-bottom:1px solid #e2e8f0;
        border-right:1px solid #e2e8f0;
        vertical-align:middle;
        color:#0f172a;
    }
    table.items td:last-child { border-right:none; }

    /* Section header row */
    table.items tr.section-header td {
        background:#f1f5f9; color:#0f172a;
        font-weight:700;
        font-size:11.5px;
        text-transform:uppercase;
        letter-spacing:0.05em;
        padding:6px 9px;
    }

    table.items td.item-num { width:38px; text-align:center; color:#94a3b8; font-family:'SF Mono',Menlo,monospace; font-size:11px; }
    table.items td.qty   { width:50px;  text-align:right; font-variant-numeric:tabular-nums; }
    table.items td.unit,
    table.items td.line  { width:125px; text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    table.items td.line { font-weight:700; }

    /* Totals rows (still in same table for that classic look) */
    table.items tr.total-label td {
        background:#f8fafc;
        font-weight:600;
        text-align:right;
        padding:6px 11px;
        font-size:12px;
        border-right:1px solid #e2e8f0;
    }
    table.items tr.total-label td.value {
        font-variant-numeric:tabular-nums;
        font-weight:700;
        text-align:right;
        font-family:'SF Mono',Menlo,monospace;
        white-space:nowrap;
    }
    /* Per-section subtotal row — mirrors the real Wiyule quotation footer */
    table.items tr.section-total td {
        background:#fafbfc;
        font-weight:700;
        font-size:12px;
        padding:6px 9px;
        color:#0f172a;
        border-right:1px solid #e2e8f0;
    }
    table.items tr.section-total td.value {
        text-align:right;
        font-family:'SF Mono',Menlo,monospace;
        font-variant-numeric:tabular-nums;
        white-space:nowrap;
        color:#0f172a;
        border-right:none;
    }
    table.items tr.grand td {
        background:#0f172a;
        color:#fff;
        font-size:13px;
        font-weight:800;
        padding:9px 11px;
        text-transform:uppercase;
        letter-spacing:0.04em;
        border-right:1px solid #1e293b;
    }
    table.items tr.grand td:last-child { color:#fbbf24; border-right:none; white-space:nowrap; }

    /* ─── PAYMENT STATUS BANNER ─── */
    .status-banner {
        display:inline-flex; align-items:center; gap:8px;
        padding:9px 18px; border-radius:99px;
        color:#fff; font-weight:700; font-size:12.5px;
        margin:20px 0 0;
        text-transform:uppercase; letter-spacing:0.06em;
    }
    .status-banner.paid    { background:#16a34a; }
    .status-banner.partial { background:#f59e0b; }
    .status-banner.unpaid  { background:#dc2626; }
    .status-banner.void    { background:#94a3b8; }

    /* ─── PAYMENT DETAILS ─── */
    .payment-card {
        background:#f8fafc;
        border:1px solid #e2e8f0;
        border-radius:8px;
        padding:14px 16px;
        margin-top:18px;
        font-size:12.5px; color:#334155;
        line-height:1.5;
    }
    .payment-card strong { color:#0f172a; }

    /* ─── How-to-pay block (bottom of invoice) ─── */
    .pay-details {
        margin-top:16px;
        border:1px solid #cbd5e1;
        border-radius:8px;
        overflow:hidden;
    }
    .pay-title {
        background:#0f172a; color:#fff;
        font-size:10.5px; font-weight:700;
        text-transform:uppercase; letter-spacing:0.08em;
        padding:6px 13px;
    }
    .pay-grid {
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:0;
        background:#fff;
    }
    @media screen and (max-width:560px) { .pay-grid { grid-template-columns:1fr; } }
    .pay-method {
        padding:10px 14px;
        border-right:1px solid #e2e8f0;
    }
    .pay-method:last-child { border-right:none; }
    @media screen and (max-width:560px) {
        .pay-method { border-right:none; border-bottom:1px solid #e2e8f0; }
        .pay-method:last-child { border-bottom:none; }
    }
    .pay-method-label {
        font-size:9.5px; font-weight:700;
        color:#64748b;
        text-transform:uppercase; letter-spacing:0.06em;
        margin-bottom:4px;
    }
    .pay-method-body {
        font-size:11.5px; line-height:1.5; color:#334155;
    }
    .pay-method-body strong { color:#0f172a; font-weight:700; }
    .pay-method-body .mono {
        font-family:'SF Mono',Menlo,monospace;
        font-weight:600; color:#dc2626;
        letter-spacing:0.02em;
    }
    .pay-ref {
        padding:7px 14px;
        background:#fffbeb;
        border-top:1px solid #fde68a;
        font-size:11px; color:#78350f;
    }
    .pay-ref strong { color:#92400e; font-family:'SF Mono',Menlo,monospace; }

    /* ─── NOTES ─── */
    .notes-block {
        background:#fffbeb; border-left:3px solid #f59e0b;
        padding:10px 14px; border-radius:0 8px 8px 0;
        margin-top:12px;
        font-size:12px; color:#78350f; line-height:1.5;
    }
    .notes-block strong { color:#92400e; font-weight:700; }

    /* ─── Terms & Conditions ─── */
    .terms-block {
        margin-top:14px;
        padding:11px 14px;
        background:#fafbfc;
        border:1px solid #e2e8f0;
        border-radius:6px;
    }
    .terms-title {
        font-size:10px; font-weight:800;
        color:#0f172a;
        text-transform:uppercase;
        letter-spacing:0.08em;
        margin-bottom:6px;
    }
    .terms-list {
        list-style:none;
        margin:0; padding:0;
    }
    .terms-list li {
        font-size:10.5px;
        line-height:1.55;
        color:#475569;
        padding-left:14px;
        position:relative;
        margin-bottom:3px;
    }
    .terms-list li:last-child { margin-bottom:0; }
    .terms-list li::before {
        content:"·";
        position:absolute;
        left:4px; top:-1px;
        color:#dc2626;
        font-weight:700;
        font-size:14px;
    }
    .terms-list li strong {
        color:#0f172a;
        font-weight:700;
    }

    /* ─── MOBILE (screen only) — keep the whole paper inside the phone's width ─── */
    @media screen and (max-width: 560px) {
        body   { padding:14px 6px 56px; }
        .paper { padding:16px 10px 22px; }

        /* Document meta: left-align so the boxed values can't shoot off the screen */
        .doc-meta      { min-width:0; text-align:left; }
        .doc-meta-row  { justify-content:flex-start; flex-wrap:wrap; gap:6px 8px; }
        .doc-meta-row .val { min-width:0; }
        .vehicle-line  { text-align:left; }

        /* Items table: auto layout, columns size to content. The numeric columns stay
           nowrap so figures are NEVER clipped; descriptions wrap. For typical quotes the
           whole table fits the phone; unusually large figures scroll inside .items-scroll. */
        .items-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        table.items { table-layout:auto; width:100%; min-width:0; }
        table.items th { padding:5px 5px; font-size:8.5px; }
        table.items td { padding:5px 5px; font-size:9.5px; }
        table.items td:nth-child(2) { word-break:break-word; }   /* description wraps, not the figures */
        table.items td.item-num, table.items td.qty,
        table.items td.unit, table.items td.line { width:auto; }
        table.items td.unit, table.items td.line { font-size:9.5px; }
        table.items tr.section-header td,
        table.items tr.section-total td,
        table.items tr.total-label td { font-size:9.5px; }
        table.items tr.grand td { font-size:10px; padding:6px 5px; }
    }

    /* ─── PRINT STYLES — aggressively tight to keep typical docs on 1 page ─── */
    @media print {
        /* CRITICAL: force the print layout to a real A4 width. iOS Safari otherwise lays the
           print out at the phone's viewport width (~390px), which wraps the letterhead/table
           and spills onto a 2nd page. Pinning the width keeps the horizontal, one-page layout. */
        html, body { width:210mm; background:#fff; padding:0; margin:0; }
        .toolbar, .no-print { display:none !important; }
        @page { size:A4; margin:0; }

        .paper {
            box-shadow:none; border-radius:0;
            padding:5mm 10mm 3mm;     /* tight paper margins; page margin handles the rest */
            max-width:none;
            width:210mm;              /* A4 width — do NOT use % (collapses to phone width on iOS) */
            margin:0 auto;
        }

        /* Letterhead — compact */
        .letterhead {
            padding-bottom:4px;
            margin-bottom:5px;
            gap:10px;
        }
        .lh-deal, .lh-block { font-size:8.5px; line-height:1.32; }
        .lh-deal-title { font-size:9px; margin-bottom:2px; }
        .lh-logo img { width:140px; }
        .doc-footer { margin-top:5px; padding-top:5px; }
        .doc-footer-qr img { width:72px; height:72px; }
        .doc-footer-thanks { font-size:10px; }
        .doc-footer-thanks .big { font-size:12px; margin-bottom:1px; }
        .stamp { position:absolute; }

        /* Title — significantly smaller */
        .doc-title-main {
            font-size:18px;
            margin-bottom:6px;
            letter-spacing:0;
        }

        /* Meta row */
        .meta-row { margin-bottom:4px; gap:14px; }
        .att-block { font-size:11px; line-height:1.35; }
        .att-row { margin-bottom:2px; }
        .att-row .val { padding:2px 9px; min-height:18px; }
        .greeting { font-size:10.5px; margin-top:2px; }
        .doc-meta-row { margin-bottom:2px; font-size:11px; gap:8px; }
        .doc-meta-row .val { padding:3px 9px; min-width:120px; }
        .doc-meta { min-width:200px; }

        /* Items table */
        table.items th { padding:3px 7px; font-size:9.5px; }
        table.items td { padding:1.5px 7px; font-size:10.5px; }
        table.items tr.section-header td { padding:2px 7px; font-size:10px; }
        table.items tr.section-total td { padding:2px 7px; font-size:10.5px; }
        table.items tr.total-label td { padding:2px 9px; font-size:10.5px; }
        table.items tr.grand td { padding:4px 9px; font-size:11.5px; }
        table.items td.item-num { font-size:10px; }

        /* Status pill (invoices) — compact so invoices get the same one-page headroom */
        .status-banner { margin:7px 0 0; padding:4px 12px; font-size:10px; letter-spacing:0.04em; }

        /* Payment details */
        .pay-details { margin-top:5px; }
        .pay-title { padding:3px 11px; font-size:9.5px; }
        .pay-method { padding:3px 11px; }
        .pay-method-label { font-size:8.5px; margin-bottom:2px; }
        .pay-method-body { font-size:9.5px; line-height:1.3; }
        .pay-ref { padding:2px 11px; font-size:9.5px; }

        /* Notes */
        .notes-block { margin-top:6px; padding:6px 10px; font-size:10px; }

        /* Terms — extra compact since it sits at the bottom */
        .terms-block {
            margin-top:5px;
            padding:5px 10px;
        }
        .terms-title { font-size:8.5px; margin-bottom:3px; letter-spacing:0.06em; }
        .terms-list li {
            font-size:9px;
            line-height:1.35;
            margin-bottom:1px;
            padding-left:11px;
        }
        .terms-list li::before { font-size:11px; left:2px; top:-1px; }

        /* Page-break behaviour */
        table.items { page-break-inside:auto; }
        table.items tr { page-break-inside:avoid; page-break-after:auto; }
        table.items thead { display:table-header-group; }
        table.items tr.section-header,
        table.items tr.section-total,
        table.items tr.total-label,
        table.items tr.grand { page-break-inside:avoid; }
        .pay-details, .notes-block, .terms-block { page-break-inside:avoid; }
        .letterhead, .doc-title-main { page-break-inside:avoid; }
        /* Don't pin meta-row — small enough that browser handles it fine; pinning can force a break */
    }
</style>
</head>
<body>

<!-- Toolbar -->
<div class="toolbar no-print">
    <div class="toolbar-left">
        <?php if ($is_admin): ?>
            <a href="<?= $base ?>/admin/invoices/edit.php?id=<?= (int)$invoice['id'] ?>" style="color:#64748b;text-decoration:none">← Back to editor</a>
        <?php else: ?>
            <a href="<?= $base ?>/pages/dashboard.php" style="color:#64748b;text-decoration:none">← Back to dashboard</a>
        <?php endif; ?>
    </div>
    <div class="toolbar-right">
        <a href="<?= htmlspecialchars($whatsapp_url) ?>" target="_blank" class="t-btn-link" title="Open WhatsApp with a text summary of this document">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
            WhatsApp summary
        </a>
        <?php if ($is_admin && !empty($invoice['customer_email'])): ?>
            <button class="t-btn ghost" id="emailBtn" onclick="emailInvoice()" title="Email this document to <?= htmlspecialchars($invoice['customer_email']) ?>">✉ Email to customer</button>
        <?php endif; ?>
        <button class="t-btn dark" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
</div>

<!-- Paper -->
<div class="paper <?= $is_quote ? 'is-quote' : 'is-invoice' ?>">

    <?php if (!$is_quote && $invoice['payment_status'] === 'paid'): ?>
        <div class="stamp paid">Paid</div>
    <?php elseif ($invoice['payment_status'] === 'void'): ?>
        <div class="stamp void">Void</div>
    <?php endif; ?>

    <div class="paper-inner">

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
                <span style="color:#dc2626;font-weight:600">wiyulemotors@gmail.com</span><br>
                <span style="color:#dc2626;font-weight:600">wiyulemotors.co.mw</span>
            </div>
        </div>

        <!-- ─── BIG TITLE ─── -->
        <div class="doc-title-main">Wiyule &amp; F A Motors</div>

        <!-- ─── META ROW ─── -->
        <div class="meta-row">
            <div class="att-block">
                <div class="att-row">
                    <span class="lbl">Att:</span>
                    <span class="val"><?= htmlspecialchars($invoice['recipient_name'] ?: $invoice['customer_name']) ?></span>
                </div>
                <?php if (!empty($invoice['recipient_name'])): ?>
                    <!-- Insurance billing: customer goes on "M/s" line -->
                    <div class="att-row" style="margin-top:8px">
                        <span class="lbl">M/s:</span>
                        <span class="val plain"><?= htmlspecialchars($invoice['customer_name']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="greeting"><strong>Dear Client,</strong></div>
                <div class="greeting" style="margin-top:2px"><?= $is_quote
                    ? 'We have the pleasure in quoting the following:'
                    : 'We are pleased to invoice you for the following:' ?></div>
            </div>

            <div class="doc-meta">
                <div class="doc-meta-row">
                    <span class="lbl"><?= $is_quote ? 'Quotation #' : 'Invoice #' ?></span>
                    <span class="val"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                </div>
                <div class="doc-meta-row">
                    <span class="lbl">Date:</span>
                    <span class="val plain"><?= date('d-M-Y', strtotime($invoice['issued_at'])) ?></span>
                </div>
                <?php if (!empty($invoice['vehicle_info'])): ?>
                    <div class="doc-meta-row" style="margin-top:8px">
                        <span class="lbl">Vehicle:</span>
                        <span class="val plain" style="color:#0f172a;font-weight:700"><?= htmlspecialchars($invoice['vehicle_info']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['registration'])): ?>
                    <div class="doc-meta-row"<?= empty($invoice['vehicle_info']) ? ' style="margin-top:8px"' : '' ?>>
                        <span class="lbl">Reg No:</span>
                        <span class="val plain" style="color:#0f172a;font-weight:700"><?= htmlspecialchars($invoice['registration']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['claim_reference'])): ?>
                    <div class="vehicle-line">Claim Ref: <?= htmlspecialchars($invoice['claim_reference']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── ITEMS TABLE (grouped by section) ─── -->
        <div class="items-scroll">
        <table class="items">
            <thead>
                <tr>
                    <th class="text-center col-item">Item</th>
                    <th>Description</th>
                    <th class="text-right col-qty">Qty</th>
                    <th class="text-right col-unit">Unit Price</th>
                    <th class="text-right col-line">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $row_num = 0;
                foreach ($groups as $section_name => $section_items):
                    if (empty($section_items)) continue;

                    // Per-section subtotal (e.g. "Total cost of spares: MWK X")
                    $section_subtotal = array_sum(array_map(fn($i) => (float)$i['line_total'], $section_items));
                ?>
                    <tr class="section-header">
                        <td colspan="5"><?= htmlspecialchars($section_name) ?></td>
                    </tr>
                    <?php
                    $sect_num = 0;
                    foreach ($section_items as $it):
                        $sect_num++;
                        $row_num++;
                    ?>
                    <tr>
                        <td class="item-num"><?= $sect_num ?></td>
                        <td><?= htmlspecialchars($it['description']) ?></td>
                        <td class="qty"><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?></td>
                        <td class="unit"><?= wm_invoice_format_mwk($it['unit_price']) ?></td>
                        <td class="line"><?= wm_invoice_format_mwk($it['line_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="section-total">
                        <td colspan="4">Total cost of <?= strtolower(htmlspecialchars($section_name)) ?></td>
                        <td class="value"><?= wm_invoice_format_mwk($section_subtotal) ?></td>
                    </tr>
                <?php endforeach; ?>

                <!-- Totals (integrated into the table for the classic look) -->
                <tr class="total-label">
                    <td colspan="4">Subtotal</td>
                    <td class="value"><?= wm_invoice_format_mwk($invoice['subtotal']) ?></td>
                </tr>
                <?php if ((float)$invoice['discount_amount'] > 0): ?>
                <tr class="total-label">
                    <td colspan="4">Discount<?= $invoice['discount_type'] === 'percent' ? ' ('.rtrim(rtrim(number_format((float)$invoice['discount_value'], 2), '0'), '.').'%)' : '' ?></td>
                    <td class="value" style="color:#dc2626">– <?= wm_invoice_format_mwk($invoice['discount_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($invoice['vat_enabled']): ?>
                <tr class="total-label">
                    <td colspan="4">VAT (<?= rtrim(rtrim(number_format((float)$invoice['vat_rate'], 2), '0'), '.') ?>%)</td>
                    <td class="value"><?= wm_invoice_format_mwk($invoice['vat_amount']) ?></td>
                </tr>
                <?php endif; ?>
                <?php
                $has_excess = (float)$invoice['less_excess'] > 0;
                $sub_with_vat = (float)$invoice['subtotal'] - (float)$invoice['discount_amount'] + (float)$invoice['vat_amount'];
                if ($has_excess):
                ?>
                <tr class="total-label">
                    <td colspan="4">Subtotal (incl. VAT)</td>
                    <td class="value"><?= wm_invoice_format_mwk($sub_with_vat) ?></td>
                </tr>
                <tr class="total-label">
                    <td colspan="4">Less Excess</td>
                    <td class="value" style="color:#dc2626">– <?= wm_invoice_format_mwk($invoice['less_excess']) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="grand">
                    <td colspan="4"><?= $has_excess ? 'Net Payable' : ($is_quote ? 'Total Quotation Cost' : 'Total Repair Cost') ?></td>
                    <td class="value text-right"><?= wm_invoice_format_mwk($invoice['total']) ?></td>
                </tr>
            </tbody>
        </table>
        </div>

        <!-- ─── Status (invoices only, not quotations) ─── -->
        <?php if (!$is_quote): ?>
            <div class="status-banner <?= htmlspecialchars($invoice['payment_status']) ?>">
                <?php if ($invoice['payment_status'] === 'paid'): ?>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
                <?php endif; ?>
                <?= ucfirst($invoice['payment_status']) ?>
            </div>
        <?php endif; ?>

        <!-- ─── Payment Card ─── -->
        <?php if (!$is_quote && ($invoice['payment_status'] !== 'unpaid' || $invoice['payment_method'])): ?>
        <div class="payment-card">
            <strong>Payment Details:</strong>
            <?php if ($invoice['payment_method']): ?>
                <?= wm_invoice_payment_method_label($invoice['payment_method']) ?>
            <?php endif; ?>
            <?php if ($invoice['payment_date']): ?>
                · <?= date('d M Y', strtotime($invoice['payment_date'])) ?>
            <?php endif; ?>
            <?php if ($invoice['payment_status'] === 'partial'): ?>
                · Paid: <strong><?= wm_invoice_format_mwk($invoice['amount_paid']) ?></strong>
                · Balance: <strong style="color:#dc2626"><?= wm_invoice_format_mwk((float)$invoice['total'] - (float)$invoice['amount_paid']) ?></strong>
            <?php endif; ?>
            <?php if (!empty($invoice['payment_notes'])): ?>
                <div style="margin-top:6px;color:#64748b;font-size:11.5px">
                    <?= nl2br(htmlspecialchars($invoice['payment_notes'])) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ─── Payment Details — shown on quotations + unpaid invoices ─── -->
        <?php
        // Show on:
        //   - all quotations (signals to customer how they'll pay once approved)
        //   - invoices that are still unpaid or partial (not yet paid, not void)
        $show_payment_block = $is_quote
            || ($invoice['payment_status'] === 'unpaid' || $invoice['payment_status'] === 'partial');
        if ($show_payment_block):
        ?>
        <div class="pay-details">
            <div class="pay-title">
                <?= $is_quote ? 'Payment Details (on approval)' : 'How to Pay' ?>
            </div>
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
                <?php if ($is_quote): ?>
                    If you accept this quotation, please use reference <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong> when making payment.
                <?php else: ?>
                    Please use invoice number <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong> as your payment reference.
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ─── Notes ─── -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="notes-block">
            <strong>Notes:</strong> <?= nl2br(htmlspecialchars($invoice['notes'])) ?>
        </div>
        <?php endif; ?>

        <!-- ─── Terms & Conditions ─── -->
        <div class="terms-block">
            <div class="terms-title">Terms &amp; Conditions</div>
            <?php if ($is_quote): ?>
                <ul class="terms-list">
                    <li>This quotation is valid for <strong>14 days</strong> from the date of issue.</li>
                    <li>A <strong>50% deposit</strong> is required upfront before commencement of the job.</li>
                    <li>Additional damage identified post stripping will be quoted separately.</li>
                </ul>
            <?php else: ?>
                <ul class="terms-list">
                    <li>Thank you for choosing us! Payment within <strong>21 days</strong> is appreciated.</li>
                    <li>A <strong>5% late fee</strong> applies (one-time) to payments received after this period.</li>
                    <li>Ready for collection: <strong>7 days free storage</strong>.</li>
                    <li>A storage fee of <strong>MWK 15,000 per day</strong> applies after the 7-day free storage period.</li>
                </ul>
            <?php endif; ?>
        </div>

        <!-- ─── FOOTER: QR (bottom-left, larger) + thank-you ─── -->
        <div class="doc-footer">
            <div class="doc-footer-qr">
                <img src="<?= $base ?>/assets/images/wiyule_qr.png" alt="Scan to book online">
                <div class="cap">Scan to <span>book online</span></div>
            </div>
            <div class="doc-footer-thanks">
                <div class="big"><?= $is_quote ? 'We look forward to serving you.' : 'Thank you for your business!' ?></div>
                Wiyule &amp; F A Motors · Blantyre, Malawi<br>
                +265 993 575 111 · wiyulemotors@gmail.com
            </div>
        </div>

    </div>
</div>

<div id="toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);padding:13px 20px;border-radius:11px;font-weight:600;font-size:14px;color:#fff;box-shadow:0 8px 30px rgba(0,0,0,0.18);z-index:100;opacity:0;pointer-events:none;transition:opacity 0.2s;font-family:'Sora',sans-serif;"></div>

<script>
const BASE = <?= json_encode($base) ?>;
const INVOICE_ID = <?= (int)$invoice['id'] ?>;
function pToast(msg, ok) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = ok ? '#16a34a' : '#dc2626';
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 2800);
}
async function emailInvoice() {
    const btn = document.getElementById('emailBtn');
    if (!confirm('Email this document to the customer?')) return;
    if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
    const fd = new FormData();
    fd.append('action', 'email_invoice');
    fd.append('id', INVOICE_ID);
    try {
        const r = await fetch(BASE + '/admin/invoices/api.php', { method:'POST', body:fd, credentials:'same-origin' });
        const j = await r.json();
        pToast(j.message || (j.success ? 'Sent' : 'Failed'), !!j.success);
    } catch (e) {
        pToast('Network error — try again', false);
    }
    if (btn) { btn.disabled = false; btn.textContent = '✉ Email to customer'; }
}
</script>

</body>
</html>
