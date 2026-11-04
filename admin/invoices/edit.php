<?php
// admin/invoices/edit.php — Create or edit an invoice / quotation
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

// Load active insurance companies for the dropdown
$insurance_companies = wm_ins_companies_all($conn, true);

// ── Determine mode ──
$id           = isset($_GET['id'])         ? (int)$_GET['id']         : 0;
$booking_id   = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$is_new       = ($id === 0);
$invoice = null; $items = [];

if (!$is_new) {
    $data = wm_invoice_fetch($conn, $id);
    if (!$data) { header("Location: " . $base . "/admin/invoices/"); exit; }
    $invoice = $data['invoice'];
    $items   = $data['items'];
} elseif ($booking_id > 0) {
    // Pre-fill from a completed booking
    $stmt = $conn->prepare("
        SELECT b.id, b.full_name, b.phone, b.email, b.user_id,
               b.vehicle_make, b.vehicle_model, b.vehicle_year, b.registration,
               b.services, b.first_time_discount
        FROM bookings b WHERE b.id = ?");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $b = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($b) {
        $vehicle_info = trim(($b['vehicle_year'] ? $b['vehicle_year'].' ' : '') . $b['vehicle_make'] . ' ' . $b['vehicle_model']);
        if ($b['registration']) $vehicle_info .= ' (' . $b['registration'] . ')';
        $invoice = [
            'id' => 0, 'invoice_number' => '(will be generated on save)', 'type' => 'invoice',
            'booking_id' => $booking_id, 'user_id' => $b['user_id'],
            'recipient_name' => '', 'claim_reference' => '', 'insurance_company_id' => null,
            'customer_name' => $b['full_name'], 'customer_phone' => $b['phone'],
            'customer_email' => $b['email'], 'vehicle_info' => $vehicle_info,
            'subtotal' => 0, 'discount_type' => $b['first_time_discount'] ? 'percent' : 'none',
            'discount_value' => $b['first_time_discount'] ? 10 : 0, 'discount_amount' => 0,
            'vat_enabled' => 1, 'vat_rate' => 17.50, 'vat_amount' => 0, 'less_excess' => 0, 'total' => 0,
            'payment_status' => 'unpaid', 'amount_paid' => 0, 'payment_method' => '',
            'payment_date' => null, 'payment_notes' => '', 'notes' => '',
            'issued_at' => date('Y-m-d H:i:s'), 'due_date' => null,
        ];
        // Pre-fill line items from the services list (drop them in Labour by default — easiest to price up)
        $services_csv = $b['services'] ?? '';
        if ($services_csv) {
            foreach (explode(',', $services_csv) as $i => $svc) {
                $svc = trim($svc);
                if ($svc !== '') {
                    $items[] = [
                        'item_type' => 'labour',
                        'description' => $svc,
                        'quantity' => 1, 'unit_price' => 0, 'line_total' => 0,
                        'sort_order' => $i,
                    ];
                }
            }
        }
    }
}

// Default empty shell for brand-new invoice
if ($is_new && !$invoice) {
    $invoice = [
        'id' => 0, 'invoice_number' => '(will be generated on save)', 'type' => 'invoice',
        'booking_id' => null, 'user_id' => null,
        'recipient_name' => '', 'claim_reference' => '', 'insurance_company_id' => null,
        'customer_name' => '', 'customer_phone' => '', 'customer_email' => '', 'vehicle_info' => '',
        'subtotal' => 0, 'discount_type' => 'none', 'discount_value' => 0, 'discount_amount' => 0,
        'vat_enabled' => 1, 'vat_rate' => 17.50, 'vat_amount' => 0, 'less_excess' => 0, 'total' => 0,
        'payment_status' => 'unpaid', 'amount_paid' => 0, 'payment_method' => '',
        'payment_date' => null, 'payment_notes' => '', 'notes' => '',
        'issued_at' => date('Y-m-d H:i:s'), 'due_date' => null,
    ];
}

$is_locked = ($invoice['payment_status'] === 'void');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_new ? 'New Invoice' : 'Edit '.htmlspecialchars($invoice['invoice_number']) ?> — Wiyule Admin</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Sora',sans-serif; background:#f8fafc; color:#0f172a; min-height:100vh; padding-bottom:80px; }

    .topbar { background:#fff; border-bottom:1px solid #e2e8f0; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:30; }
    .topbar-brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:#0f172a; }
    .topbar-brand img { width:34px; height:34px; }
    .topbar-brand strong { font-size:16px; font-weight:800; }
    .topbar-actions { display:flex; gap:10px; align-items:center; }
    .top-btn { padding:9px 16px; border-radius:10px; font-weight:700; font-size:13px; text-decoration:none; border:none; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:6px; }
    .top-btn.ghost { background:#f1f5f9; color:#334155; }
    .top-btn.red   { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; box-shadow:0 3px 10px rgba(220,38,38,0.3); }
    .top-btn.dark  { background:#0f172a; color:#fff; }
    .top-btn:hover { transform:translateY(-1px); }

    .page { max-width:980px; margin:0 auto; padding:28px 20px; }

    .page-head { margin-bottom:22px; }
    .page-head h1 { font-size:26px; font-weight:800; letter-spacing:-0.02em; }
    .page-head .num { font-family:'SF Mono',Menlo,monospace; color:#64748b; font-size:14px; margin-top:6px; }
    .void-banner { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 14px; border-radius:10px; margin-top:10px; font-size:13px; font-weight:600; }

    .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:22px; margin-bottom:16px; }
    .card h2 { font-size:14px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:#64748b; margin-bottom:14px; }

    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
    @media(max-width:720px) { .grid-2, .grid-3 { grid-template-columns:1fr; } }

    label { display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px; letter-spacing:0.02em; }
    label .req { color:#dc2626; }
    input, select, textarea {
        width:100%; padding:11px 13px; border:1.5px solid #e2e8f0; border-radius:10px;
        font-size:14px; font-family:'Sora',sans-serif; color:#0f172a; outline:none;
        background:#fff; transition:border-color 0.15s;
    }
    input:focus, select:focus, textarea:focus { border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,0.08); }
    textarea { resize:vertical; min-height:60px; }
    input[readonly] { background:#f8fafc; color:#64748b; }

    /* Line items table */
    .items-wrap { overflow-x:auto; }
    table.items { width:100%; border-collapse:collapse; min-width:560px; }
    table.items th { font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; font-weight:700; text-align:left; padding:8px 8px; border-bottom:1px solid #e2e8f0; }
    table.items td { padding:4px 4px; vertical-align:middle; }
    table.items td input { padding:8px 10px; font-size:13px; }
    table.items td .line-total { font-weight:700; color:#0f172a; padding:8px 10px; text-align:right; }
    table.items td .remove-btn { background:transparent; color:#cbd5e1; border:none; cursor:pointer; padding:6px 8px; border-radius:6px; font-weight:700; font-size:18px; line-height:1; opacity:0; transition:opacity 0.15s, background 0.15s, color 0.15s; }
    table.items tr:hover .remove-btn { opacity:1; }
    table.items td .remove-btn:hover { background:#fef2f2; color:#dc2626; }
    /* Empty rows look more muted so it's clear which are "fresh" */
    table.items tr.empty-row td input { background:#fafbfc; }
    table.items td.row-num { color:#94a3b8; font-size:12px; font-family:'SF Mono',Menlo,monospace; text-align:center; padding:8px 6px; }
    /* Keyboard hint chips in section headers */
    kbd {
        display:inline-block; padding:1px 6px; margin:0 1px;
        background:#f1f5f9; border:1px solid #cbd5e1;
        border-radius:4px;
        font-family:'SF Mono',Menlo,monospace;
        font-size:10.5px; color:#475569;
        box-shadow:0 1px 0 #cbd5e1;
    }

    /* Section blocks (Spares / Labour / Material) */
    .sec-block { margin-bottom:22px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
    .sec-block:last-child { margin-bottom:0; }
    .sec-head {
        background:#f1f5f9; color:#0f172a;
        font-weight:700; font-size:12px;
        text-transform:uppercase; letter-spacing:0.06em;
        padding:10px 14px;
        border-bottom:1px solid #e2e8f0;
    }
    .sec-block .items-wrap { padding:4px 8px 6px; }
    .sec-foot {
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 14px;
        background:#f8fafc;
        border-top:1px solid #e2e8f0;
        font-size:13px; font-weight:700; color:#334155;
    }
    .sec-foot .sec-total { font-family:'SF Mono',Menlo,monospace; color:#0f172a; font-size:14px; }
    .material-row { padding:14px 14px 16px; }
    .material-row label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:6px; }
    .material-row input { width:100%; max-width:280px; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:10px; font-size:14px; font-family:'Sora',sans-serif; outline:none; }
    .material-row input:focus { border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,0.08); }

    /* Totals box */
    .totals { background:#f8fafc; border-radius:12px; padding:18px 20px; }
    .totals .row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; color:#334155; }
    .totals .row .v { font-weight:600; font-family:'SF Mono',Menlo,monospace; }
    .totals .row.grand { padding-top:12px; margin-top:10px; border-top:1.5px solid #cbd5e1; font-size:17px; font-weight:800; color:#0f172a; }
    .totals .row.grand .v { color:#dc2626; font-size:18px; }

    .checkbox-row { display:flex; align-items:center; gap:9px; padding:11px 13px; background:#f8fafc; border-radius:10px; }
    .checkbox-row input { width:18px; height:18px; accent-color:#dc2626; cursor:pointer; flex-shrink:0; }
    .checkbox-row label { margin:0; cursor:pointer; font-size:13px; color:#334155; font-weight:600; }

    .toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); padding:14px 22px; border-radius:12px; font-weight:600; font-size:14px; box-shadow:0 8px 30px rgba(0,0,0,0.15); z-index:100; opacity:0; transition:opacity 0.2s; }
    .toast.show { opacity:1; }
    .toast.success { background:#16a34a; color:#fff; }
    .toast.error   { background:#dc2626; color:#fff; }
</style>
</head>
<body>

<div class="topbar">
    <a href="<?= $base ?>/admin/invoices/" class="topbar-brand">
        <img src="<?= $base ?>/assets/images/logo.png" alt="Wiyule">
        <strong>Invoices</strong>
    </a>
    <div class="topbar-actions">
        <a href="<?= $base ?>/admin/invoices/" class="top-btn ghost">← Back</a>
        <?php if (!$is_new): ?>
            <?php if ($invoice['type'] === 'quotation' && !$is_locked): ?>
                <button type="button" class="top-btn dark" onclick="invAction('convert_to_invoice','Convert this quotation into an invoice? It keeps the same number and line items.')">→ Convert to Invoice</button>
            <?php endif; ?>
            <button type="button" class="top-btn ghost" onclick="invAction('duplicate','Create a new draft copy of this document?')">⧉ Duplicate</button>
            <?php if (!$is_locked): ?>
                <a href="<?= $base ?>/admin/invoices/print.php?id=<?= (int)$invoice['id'] ?>" target="_blank" class="top-btn dark">🖨 Print / PDF</a>
            <?php endif; ?>
            <button type="button" class="top-btn ghost" style="color:#dc2626" onclick="invAction('archive','Archive this document? It will be hidden from the active list but kept on record (recoverable).')">🗄 Archive</button>
        <?php endif; ?>
        <button type="button" id="saveBtn" class="top-btn red" <?= $is_locked ? 'disabled style="opacity:0.5;cursor:not-allowed"' : '' ?>>💾 Save</button>
    </div>
</div>

<div class="page">

    <div class="page-head">
        <h1><?= $is_new ? 'New Invoice / Quotation' : 'Edit Invoice' ?></h1>
        <div class="num"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
        <?php if ($is_locked): ?>
            <div class="void-banner">This invoice is VOID and cannot be edited.</div>
        <?php endif; ?>
    </div>

    <form id="invoiceForm" onsubmit="return false">
        <input type="hidden" name="id"         value="<?= (int)$invoice['id'] ?>">
        <input type="hidden" name="booking_id" value="<?= (int)($invoice['booking_id'] ?? 0) ?>">
        <input type="hidden" name="user_id"    value="<?= (int)($invoice['user_id'] ?? 0) ?>">

        <!-- TYPE + DATES -->
        <div class="card">
            <h2>Document</h2>
            <div class="grid-3">
                <div>
                    <label>Type <span class="req">*</span></label>
                    <select name="type">
                        <option value="invoice"   <?= $invoice['type']==='invoice'?'selected':'' ?>>Invoice</option>
                        <option value="quotation" <?= $invoice['type']==='quotation'?'selected':'' ?>>Quotation</option>
                    </select>
                </div>
                <div>
                    <label>Issue Date</label>
                    <input type="date" name="issued_at" value="<?= date('Y-m-d', strtotime($invoice['issued_at'])) ?>">
                </div>
                <div>
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= $invoice['due_date'] ? htmlspecialchars($invoice['due_date']) : '' ?>">
                </div>
            </div>
        </div>

        <!-- CUSTOMER -->
        <div class="card">
            <h2>Customer (M/s)</h2>
            <div class="grid-2">
                <div>
                    <label>Name <span class="req">*</span></label>
                    <input type="text" name="customer_name" value="<?= htmlspecialchars($invoice['customer_name']) ?>" required>
                </div>
                <div>
                    <label>Phone <span class="req">*</span></label>
                    <input type="tel" name="customer_phone" value="<?= htmlspecialchars($invoice['customer_phone']) ?>" required>
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="customer_email" value="<?= htmlspecialchars($invoice['customer_email'] ?? '') ?>">
                </div>
                <div>
                    <label>Vehicle</label>
                    <input type="text" name="vehicle_info" value="<?= htmlspecialchars($invoice['vehicle_info'] ?? '') ?>" placeholder="e.g. 2018 BMW 320i (BT 1234)">
                </div>
            </div>
        </div>

        <!-- INSURANCE / RECIPIENT (optional — only for insurance jobs) -->
        <div class="card">
            <h2 style="display:flex;align-items:center;gap:10px">
                <span>Insurance Billing</span>
                <span style="font-size:11px;font-weight:500;color:#94a3b8;text-transform:none;letter-spacing:0">Optional — only for insurer-billed jobs</span>
            </h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:10px">
                <div>
                    <label>Insurance Company</label>
                    <select name="insurance_company_id" id="insuranceCompanySelect" onchange="onInsCompanyChange(this)">
                        <option value="">— Not an insurance job —</option>
                        <?php foreach ($insurance_companies as $co): ?>
                            <option value="<?= (int)$co['id'] ?>"
                                <?= ((int)($invoice['insurance_company_id'] ?? 0) === (int)$co['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($co['name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__add_new__" style="color:#dc2626;font-weight:600">+ Add new company…</option>
                    </select>
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px">
                        Links this invoice to the company's statement.
                    </div>
                </div>
                <div>
                    <label>Claim reference #</label>
                    <input type="text" name="claim_reference" value="<?= htmlspecialchars($invoice['claim_reference'] ?? '') ?>" placeholder="e.g. BT/MPRV/CLM/0033872">
                </div>
            </div>
            <div>
                <label>Bill to (Att:)</label>
                <input type="text" name="recipient_name" value="<?= htmlspecialchars($invoice['recipient_name'] ?? '') ?>" placeholder="e.g. Claims Manager, Britam Insurance Co.">
            </div>
        </div>

        <!-- LINE ITEMS — three section blocks: type straight down, no clicks needed -->
        <div class="card">
            <h2 style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span>Items</span>
                <span style="font-size:11px;font-weight:500;color:#94a3b8;text-transform:none;letter-spacing:0">
                    <kbd>Tab</kbd> between fields · <kbd>Enter</kbd> for next row · <kbd>Ctrl+S</kbd> to save · empty rows ignored
                </span>
            </h2>

            <!-- SPARES -->
            <div class="sec-block">
                <div class="sec-head">Spares</div>
                <div class="items-wrap">
                    <table class="items sec-table" data-section="part">
                        <thead>
                            <tr>
                                <th style="width:42px" class="text-center">#</th>
                                <th>Description</th>
                                <th style="width:70px" class="text-right">Qty</th>
                                <th style="width:140px" class="text-right">Unit Price (MWK)</th>
                                <th style="width:140px" class="text-right">Line Total</th>
                                <th style="width:38px"></th>
                            </tr>
                        </thead>
                        <tbody class="sec-body"></tbody>
                    </table>
                </div>
                <div class="sec-foot">
                    <span>Total cost of spares</span>
                    <span class="sec-total" data-sec-total>MWK 0.00</span>
                </div>
            </div>

            <!-- LABOUR -->
            <div class="sec-block">
                <div class="sec-head">Labour</div>
                <div class="items-wrap">
                    <table class="items sec-table" data-section="labour">
                        <thead>
                            <tr>
                                <th style="width:42px" class="text-center">#</th>
                                <th>Description</th>
                                <th style="width:70px" class="text-right">Qty</th>
                                <th style="width:140px" class="text-right">Unit Price (MWK)</th>
                                <th style="width:140px" class="text-right">Line Total</th>
                                <th style="width:38px"></th>
                            </tr>
                        </thead>
                        <tbody class="sec-body"></tbody>
                    </table>
                </div>
                <div class="sec-foot">
                    <span>Total cost of labour</span>
                    <span class="sec-total" data-sec-total>MWK 0.00</span>
                </div>
            </div>

            <!-- MATERIAL / CONSUMABLES (single lump-sum field) -->
            <div class="sec-block">
                <div class="sec-head">Material / Consumables</div>
                <div class="material-row">
                    <label for="materialAmount">Lump sum amount (MWK)</label>
                    <input type="number" step="0.01" min="0" id="materialAmount" placeholder="e.g. 100000" oninput="recalc()">
                </div>
            </div>
        </div>

        <!-- DISCOUNT + VAT + EXCESS -->
        <div class="card">
            <h2>Discount, VAT &amp; Excess</h2>
            <div class="grid-3">
                <div>
                    <label>Discount type</label>
                    <select name="discount_type" id="discountType" onchange="recalc()">
                        <option value="none"    <?= $invoice['discount_type']==='none'?'selected':'' ?>>None</option>
                        <option value="percent" <?= $invoice['discount_type']==='percent'?'selected':'' ?>>Percent (%)</option>
                        <option value="fixed"   <?= $invoice['discount_type']==='fixed'?'selected':'' ?>>Fixed (MWK)</option>
                    </select>
                </div>
                <div>
                    <label>Discount value</label>
                    <input type="number" step="0.01" min="0" name="discount_value" id="discountValue" value="<?= number_format((float)$invoice['discount_value'], 2, '.', '') ?>" oninput="recalc()">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <div class="checkbox-row">
                        <input type="checkbox" name="vat_enabled" id="vatEnabled" value="1" <?= $invoice['vat_enabled']?'checked':'' ?> onchange="recalc()">
                        <label for="vatEnabled">Apply VAT (17.5%)</label>
                    </div>
                </div>
                <div style="grid-column:1/-1">
                    <label>Less Excess (insurance jobs only — deducted from total)</label>
                    <input type="number" step="0.01" min="0" name="less_excess" id="lessExcess" value="<?= number_format((float)($invoice['less_excess'] ?? 0), 2, '.', '') ?>" oninput="recalc()" placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- TOTALS -->
        <div class="card">
            <h2>Totals</h2>
            <div class="totals">
                <div class="row"><span>Subtotal</span><span class="v" id="t_subtotal">MWK 0.00</span></div>
                <div class="row" id="row_discount" style="display:none"><span>Discount <span id="t_discount_label"></span></span><span class="v" id="t_discount">– MWK 0.00</span></div>
                <div class="row" id="row_vat" style="display:none"><span>VAT (17.5%)</span><span class="v" id="t_vat">MWK 0.00</span></div>
                <div class="row" id="row_subwithvat" style="display:none"><span>Subtotal (incl. VAT)</span><span class="v" id="t_subwithvat">MWK 0.00</span></div>
                <div class="row" id="row_excess" style="display:none"><span>Less Excess</span><span class="v" id="t_excess">– MWK 0.00</span></div>
                <div class="row grand"><span id="t_total_label">Total</span><span class="v" id="t_total">MWK 0.00</span></div>
            </div>
        </div>

        <!-- PAYMENT -->
        <div class="card">
            <h2>Payment</h2>
            <div class="grid-3">
                <div>
                    <label>Status</label>
                    <select name="payment_status" id="paymentStatus" onchange="syncPaymentFields()">
                        <option value="unpaid"  <?= $invoice['payment_status']==='unpaid'?'selected':'' ?>>Unpaid</option>
                        <option value="partial" <?= $invoice['payment_status']==='partial'?'selected':'' ?>>Partial</option>
                        <option value="paid"    <?= $invoice['payment_status']==='paid'?'selected':'' ?>>Paid</option>
                        <option value="void"    <?= $invoice['payment_status']==='void'?'selected':'' ?>>Void</option>
                    </select>
                </div>
                <div>
                    <label>Method</label>
                    <select name="payment_method">
                        <option value=""               <?= $invoice['payment_method']===''?'selected':'' ?>>—</option>
                        <option value="cash"           <?= $invoice['payment_method']==='cash'?'selected':'' ?>>Cash</option>
                        <option value="airtel_money"   <?= $invoice['payment_method']==='airtel_money'?'selected':'' ?>>Airtel Money</option>
                        <option value="tnm_mpamba"     <?= $invoice['payment_method']==='tnm_mpamba'?'selected':'' ?>>TNM Mpamba</option>
                        <option value="bank_transfer"  <?= $invoice['payment_method']==='bank_transfer'?'selected':'' ?>>Bank Transfer</option>
                        <option value="card"           <?= $invoice['payment_method']==='card'?'selected':'' ?>>Card</option>
                        <option value="other"          <?= $invoice['payment_method']==='other'?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label>Date paid</label>
                    <input type="date" name="payment_date" value="<?= $invoice['payment_date'] ? htmlspecialchars($invoice['payment_date']) : '' ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label>Amount paid (for partial payments)</label>
                    <input type="number" step="0.01" min="0" name="amount_paid" id="amountPaid" value="<?= number_format((float)$invoice['amount_paid'], 2, '.', '') ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label>Payment notes</label>
                    <textarea name="payment_notes" placeholder="e.g. Customer paid 50% upfront on 2 June, balance on collection"><?= htmlspecialchars($invoice['payment_notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- NOTES -->
        <div class="card">
            <h2>Notes (visible on PDF)</h2>
            <textarea name="notes" placeholder="e.g. Thank you for your business. Warranty: 30 days parts &amp; labour."><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
        </div>

    </form>
</div>

<div id="toast" class="toast"></div>

<!-- Pre-existing items rendered into JS array -->
<script>
const BASE = <?= json_encode($base) ?>;
const IS_NEW = <?= $is_new ? 'true' : 'false' ?>;
const INVOICE_ID = <?= (int)$invoice['id'] ?>;
const VAT_RATE = 17.5;

// ── Record actions: convert / duplicate / archive ──
async function invAction(action, confirmMsg) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', INVOICE_ID);
    try {
        const resp = await fetch(BASE + '/admin/invoices/api.php', { method:'POST', body:fd, credentials:'same-origin' });
        const j = await resp.json();
        if (!j.success) { showToast(j.message || 'Action failed', 'error'); return; }
        showToast(j.message || 'Done', 'success');
        setTimeout(() => {
            if (action === 'archive') {
                window.location = BASE + '/admin/invoices/';
            } else {
                // convert (same id) or duplicate (new id) → open the resulting document
                window.location = BASE + '/admin/invoices/edit.php?id=' + (j.invoice_id || INVOICE_ID);
            }
        }, 700);
    } catch (e) {
        showToast('Network error — try again', 'error');
    }
}

// ── Insurance company: handle "+ Add new company…" inline ──
function onInsCompanyChange(sel) {
    if (sel.value !== '__add_new__') return;

    const newName = prompt('New insurance company name:');
    if (!newName || !newName.trim()) {
        sel.value = '';   // user cancelled — revert to "Not an insurance job"
        return;
    }

    // POST to api.php to create the company, then refresh the dropdown.
    const fd = new FormData();
    fd.append('action', 'add_insurance_company');
    fd.append('name', newName.trim());

    fetch(BASE + '/admin/invoices/api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Could not add company: ' + (data.message || 'unknown error'));
                sel.value = '';
                return;
            }
            // Insert the new option BEFORE the "+ Add new company…" option, then select it
            const addOption = sel.querySelector('option[value="__add_new__"]');
            const opt = document.createElement('option');
            opt.value = data.id;
            opt.textContent = data.name;
            sel.insertBefore(opt, addOption);
            sel.value = data.id;
        })
        .catch(err => {
            alert('Network error adding company: ' + err);
            sel.value = '';
        });
}

const initialItems = <?= json_encode(array_map(function($it) {
    return [
        'item_type'   => $it['item_type'],
        'description' => $it['description'],
        'quantity'    => (float)$it['quantity'],
        'unit_price'  => (float)$it['unit_price'],
    ];
}, $items)) ?>;

// ── Items management — three independent section bodies ──
const sectionBodies = {
    part:   document.querySelector('table[data-section="part"]   .sec-body'),
    labour: document.querySelector('table[data-section="labour"] .sec-body'),
};
const materialInput = document.getElementById('materialAmount');

let rowCounter = 0;
function nextId() { return ++rowCounter; }

function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }

function rowHtml(rowId, it) {
    const qty   = (it && it.quantity != null)   ? it.quantity   : '';
    const price = (it && it.unit_price != null) ? it.unit_price : '';
    const desc  = (it && it.description)        ? escapeHtml(it.description) : '';
    return `
        <tr data-row-id="${rowId}" class="empty-row">
            <td class="row-num"></td>
            <td><input type="text"   class="f-desc"  value="${desc}"  placeholder="Description" oninput="onRowInput(this)" onkeydown="onRowKeydown(event)"></td>
            <td><input type="number" class="f-qty"   value="${qty}"   step="0.01" min="0" placeholder="1" oninput="onRowInput(this)" onkeydown="onRowKeydown(event)" inputmode="decimal"></td>
            <td><input type="number" class="f-price" value="${price}" step="0.01" min="0" placeholder="0.00" oninput="onRowInput(this)" onkeydown="onRowKeydown(event)" inputmode="decimal"></td>
            <td><div class="line-total" data-line-total>MWK 0.00</div></td>
            <td><button type="button" class="remove-btn" onclick="removeRow(this)" title="Remove row" tabindex="-1">×</button></td>
        </tr>
    `;
}

function addRow(sectionKey, prefill) {
    const tbody = sectionBodies[sectionKey];
    tbody.insertAdjacentHTML('beforeend', rowHtml(nextId(), prefill));
    renumberSection(sectionKey);
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    const tbody = tr.parentNode;
    tr.remove();
    // Find which section this was in
    for (const key in sectionBodies) {
        if (sectionBodies[key] === tbody) renumberSection(key);
    }
    ensureTrailingEmpty();
    recalc();
}

function renumberSection(sectionKey) {
    const tbody = sectionBodies[sectionKey];
    let n = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
        if (isRowFilled(tr)) n++;
        tr.querySelector('.row-num').textContent = isRowFilled(tr) ? n : '';
    });
}

function isRowFilled(tr) {
    const desc = tr.querySelector('.f-desc').value.trim();
    return desc !== '';
}

// Called on every keystroke inside a row input
function onRowInput(input) {
    const tr = input.closest('tr');
    // Toggle empty-row class
    if (isRowFilled(tr)) tr.classList.remove('empty-row');
    else tr.classList.add('empty-row');

    // Re-number visible items
    for (const key in sectionBodies) {
        if (sectionBodies[key].contains(tr)) renumberSection(key);
    }

    ensureTrailingEmpty();
    recalc();
}

// Enter key in a row: jump to same field in the NEXT row (creating one if needed).
// Shift+Enter: jump to PREVIOUS row's same field.
// In description: Enter moves to next row's description (not Qty) — fastest for typing a list.
function onRowKeydown(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const input = e.target;
    const tr = input.closest('tr');
    const tbody = tr.parentNode;
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const idx = allRows.indexOf(tr);

    // Make sure there's a next row available
    if (idx === allRows.length - 1) {
        ensureTrailingEmpty();
    }
    // Re-grab rows after potential add
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const nextRow = e.shiftKey ? rows[idx - 1] : rows[idx + 1];
    if (!nextRow) return;

    // Focus same field class in next row
    const cls = input.classList.contains('f-desc') ? '.f-desc'
              : input.classList.contains('f-qty')   ? '.f-qty'
              : '.f-price';
    const target = nextRow.querySelector(cls);
    if (target) { target.focus(); target.select(); }
}

// Ensure each section has exactly ONE trailing empty row (auto-grow)
function ensureTrailingEmpty() {
    for (const key in sectionBodies) {
        const tbody = sectionBodies[key];
        const rows = Array.from(tbody.querySelectorAll('tr'));
        // Remove any empty rows that are NOT the last
        rows.forEach((tr, idx) => {
            if (idx < rows.length - 1 && !isRowFilled(tr)) tr.remove();
        });
        // If the last row is filled (or no rows), add a fresh empty row
        const last = tbody.querySelector('tr:last-child');
        if (!last || isRowFilled(last)) addRow(key);
    }
}

// ── Live recalculation ──
function recalc() {
    let subtotal = 0;

    // Section totals
    for (const key in sectionBodies) {
        const tbody = sectionBodies[key];
        let sec = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const qty   = parseFloat(tr.querySelector('.f-qty').value)   || 0;
            const price = parseFloat(tr.querySelector('.f-price').value) || 0;
            const line  = qty * price;
            tr.querySelector('[data-line-total]').textContent = formatMwk(line);
            sec += line;
        });
        // Update section footer total
        const block = tbody.closest('.sec-block');
        if (block) {
            const totalEl = block.querySelector('[data-sec-total]');
            if (totalEl) totalEl.textContent = formatMwk(sec);
        }
        subtotal += sec;
    }

    // Material lump sum
    const material = parseFloat(materialInput.value) || 0;
    subtotal += material;

    // Discount / VAT / Excess (unchanged logic)
    const discType  = document.getElementById('discountType').value;
    const discVal   = parseFloat(document.getElementById('discountValue').value) || 0;
    const vatOn     = document.getElementById('vatEnabled').checked;
    const excessVal = parseFloat(document.getElementById('lessExcess').value) || 0;

    let discount = 0;
    if (discType === 'percent') discount = subtotal * (Math.max(0, Math.min(100, discVal)) / 100);
    else if (discType === 'fixed') discount = Math.min(subtotal, Math.max(0, discVal));

    const afterDisc  = subtotal - discount;
    const vat        = vatOn ? afterDisc * (VAT_RATE / 100) : 0;
    const subWithVat = afterDisc + vat;
    const excess     = Math.max(0, Math.min(subWithVat, excessVal));
    const total      = subWithVat - excess;

    document.getElementById('t_subtotal').textContent      = formatMwk(subtotal);
    document.getElementById('t_discount').textContent      = '– ' + formatMwk(discount);
    document.getElementById('t_discount_label').textContent = discType==='percent' && discVal>0 ? `(${discVal}%)` : '';
    document.getElementById('t_vat').textContent           = formatMwk(vat);
    document.getElementById('t_subwithvat').textContent    = formatMwk(subWithVat);
    document.getElementById('t_excess').textContent        = '– ' + formatMwk(excess);
    document.getElementById('t_total').textContent         = formatMwk(total);

    document.getElementById('row_discount').style.display    = (discType !== 'none' && discount > 0) ? '' : 'none';
    document.getElementById('row_vat').style.display         = vatOn ? '' : 'none';
    document.getElementById('row_excess').style.display      = excess > 0 ? '' : 'none';
    document.getElementById('row_subwithvat').style.display  = excess > 0 ? '' : 'none';
    document.getElementById('t_total_label').textContent     = excess > 0 ? 'Net Payable' : 'Total';
}

function formatMwk(n) {
    return 'MWK ' + (Math.round(n*100)/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
}

function syncPaymentFields() {
    const status = document.getElementById('paymentStatus').value;
    if (status === 'paid') {
        // Auto-fill amount_paid to total when marking Paid
        const totalText = document.getElementById('t_total').textContent.replace(/[^\d.]/g,'');
        document.getElementById('amountPaid').value = parseFloat(totalText) || 0;
    }
}

// ── Save handler ──
document.getElementById('saveBtn').addEventListener('click', async () => {
    const form = document.getElementById('invoiceForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    // Build payload — gather items from each section tbody
    const data = { items: [] };

    // Form fields (everything that has a name=)
    new FormData(form).forEach((v, k) => { data[k] = v; });
    data.vat_enabled = document.getElementById('vatEnabled').checked ? 1 : 0;

    // Items from Spares + Labour blocks
    for (const sectionKey in sectionBodies) {
        const tbody = sectionBodies[sectionKey];
        tbody.querySelectorAll('tr').forEach(tr => {
            const desc  = tr.querySelector('.f-desc').value.trim();
            if (!desc) return; // skip empty rows
            const qty   = parseFloat(tr.querySelector('.f-qty').value)   || 1;
            const price = parseFloat(tr.querySelector('.f-price').value) || 0;
            data.items.push({
                item_type:   sectionKey,
                description: desc,
                quantity:    qty,
                unit_price:  price,
            });
        });
    }

    // Material / Consumables lump sum — single 'other' line
    const material = parseFloat(materialInput.value) || 0;
    if (material > 0) {
        data.items.push({
            item_type:   'other',
            description: 'Material / Consumables',
            quantity:    1,
            unit_price:  material,
        });
    }

    if (data.items.length === 0) {
        return showToast('Add at least one line item.', 'error');
    }

    const btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
        const resp = await fetch(BASE + '/admin/invoices/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });
        const j = await resp.json();
        if (j.success) {
            showToast(IS_NEW ? `Saved as ${j.invoice_number}` : 'Updated', 'success');
            setTimeout(() => {
                window.location = BASE + '/admin/invoices/edit.php?id=' + j.invoice_id;
            }, 800);
        } else {
            showToast(j.message || 'Save failed', 'error');
            btn.disabled = false; btn.innerHTML = '💾 Save';
        }
    } catch (err) {
        showToast('Network error — try again', 'error');
        btn.disabled = false; btn.innerHTML = '💾 Save';
    }
});

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + type;
    setTimeout(() => t.className = 'toast', 3000);
}

// ── Init: load existing items into the right sections ──
(function init() {
    let materialTotal = 0;
    initialItems.forEach(it => {
        if (it.item_type === 'part') {
            addRow('part', it);
        } else if (it.item_type === 'labour') {
            addRow('labour', it);
        } else if (it.item_type === 'other') {
            // Material/Consumables — sum up amounts (qty × unit_price) into one lump
            materialTotal += (parseFloat(it.quantity) || 1) * (parseFloat(it.unit_price) || 0);
        } else {
            // Legacy 'service' items — slot into Labour (closest in spirit) so they show somewhere
            addRow('labour', it);
        }
    });
    if (materialTotal > 0) materialInput.value = materialTotal;

    // Ensure each section has at least one empty row ready
    ensureTrailingEmpty();
    recalc();

    // If new (or customer details still empty), focus the customer name first;
    // otherwise focus the first description field in Spares so brother can type immediately.
    if (IS_NEW) {
        const customerNameInput = document.querySelector('input[name="customer_name"]');
        if (customerNameInput && !customerNameInput.value) {
            customerNameInput.focus();
        } else {
            const firstDesc = sectionBodies.part.querySelector('.f-desc');
            if (firstDesc) firstDesc.focus();
        }
    } else {
        // Editing existing: focus first description of Spares (or Labour if no spares)
        const firstDesc = sectionBodies.part.querySelector('.f-desc');
        if (firstDesc) firstDesc.focus();
    }
})();

// Cmd/Ctrl+S shortcut to save anywhere on the page
document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        document.getElementById('saveBtn').click();
    }
});
</script>

</body>
</html>
