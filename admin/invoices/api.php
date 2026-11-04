<?php
// admin/invoices/api.php — Save endpoint (JSON in, JSON out)
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/invoices.php';
require_once __DIR__ . '/../../includes/insurance.php';

// ── Special: handle "+ Add new company..." inline workflow ──
// This branch is used by the edit.php company dropdown via FormData POST.
if (($_POST['action'] ?? '') === 'add_insurance_company') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Company name is required']);
        exit;
    }
    if (mb_strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Company name too long (100 chars max)']);
        exit;
    }
    $new_id = wm_ins_company_add($conn, $name);
    if ($new_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Could not add company']);
        exit;
    }
    // Return the canonical stored name (handles case-insensitive existing-match)
    $co = wm_ins_company_get($conn, $new_id);
    echo json_encode([
        'success' => true,
        'id'      => $new_id,
        'name'    => $co['name'] ?? $name,
    ]);
    exit;
}

// ── Simple record actions (form-encoded POST with ?action=…) ──
// archive (soft-delete), restore, convert_to_invoice, duplicate, mark_paid.
$action = $_POST['action'] ?? '';
if (in_array($action, ['archive', 'restore', 'convert_to_invoice', 'duplicate', 'mark_paid', 'email_invoice'], true)) {
    $iid = (int)($_POST['id'] ?? 0);
    if ($iid <= 0) { echo json_encode(['success' => false, 'message' => 'Missing invoice id']); exit; }
    $cur = wm_invoice_fetch($conn, $iid);
    if (!$cur) { echo json_encode(['success' => false, 'message' => 'Invoice not found']); exit; }
    $inv = $cur['invoice'];

    if ($action === 'email_invoice') {
        require_once __DIR__ . '/../../includes/email.php';
        $res = wm_send_invoice_email($inv, $cur['items']);
        if (!empty($res['ok'])) {
            $msg = !empty($res['dev']) ? 'Email generated (dev mode — not actually sent)' : 'Emailed to ' . $inv['customer_email'];
            echo json_encode(['success' => true, 'message' => $msg]);
        } else {
            echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Could not send email']);
        }
        exit;
    }

    if ($action === 'archive') {
        $s = $conn->prepare("UPDATE invoices SET archived_at = NOW() WHERE id = ?");
        $s->bind_param('i', $iid); $s->execute(); $s->close();
        echo json_encode(['success' => true, 'message' => 'Moved to archive']); exit;
    }

    if ($action === 'restore') {
        $s = $conn->prepare("UPDATE invoices SET archived_at = NULL WHERE id = ?");
        $s->bind_param('i', $iid); $s->execute(); $s->close();
        echo json_encode(['success' => true, 'message' => 'Restored from archive']); exit;
    }

    if ($action === 'mark_paid') {
        if ($inv['payment_status'] === 'void') { echo json_encode(['success' => false, 'message' => 'Cannot mark a void invoice as paid']); exit; }
        if ($inv['type'] !== 'invoice')         { echo json_encode(['success' => false, 'message' => 'Only invoices can be marked paid']); exit; }
        $s = $conn->prepare("UPDATE invoices SET payment_status='paid', amount_paid=total, payment_date=CURDATE(), payment_method=IF(payment_method='', 'cash', payment_method) WHERE id = ?");
        $s->bind_param('i', $iid); $s->execute(); $s->close();
        echo json_encode(['success' => true, 'message' => 'Marked as paid']); exit;
    }

    if ($action === 'convert_to_invoice') {
        if ($inv['type'] !== 'quotation') { echo json_encode(['success' => false, 'message' => 'Only quotations can be converted']); exit; }
        // Convert in place: keeps the same WM number & line items, becomes an unpaid invoice dated now.
        $s = $conn->prepare("UPDATE invoices SET type='invoice', issued_at=NOW(), payment_status='unpaid', archived_at=NULL WHERE id = ?");
        $s->bind_param('i', $iid); $s->execute(); $s->close();
        echo json_encode(['success' => true, 'message' => 'Converted to invoice', 'invoice_id' => $iid, 'invoice_number' => $inv['invoice_number']]); exit;
    }

    if ($action === 'duplicate') {
        $conn->begin_transaction();
        try {
            $newNum = wm_invoice_next_number($conn);
            $cb = (int)$_SESSION['admin_id'];
            // Copy the header as a fresh UNPAID draft (same type), dated now.
            $s = $conn->prepare("
                INSERT INTO invoices
                  (invoice_number, type, booking_id, user_id, recipient_name, customer_name, customer_phone,
                   customer_email, vehicle_info, claim_reference, insurance_company_id, subtotal, discount_type,
                   discount_value, discount_amount, vat_enabled, vat_rate, vat_amount, less_excess, total,
                   payment_status, amount_paid, payment_method, payment_date, payment_notes, notes, issued_at, due_date, created_by)
                SELECT ?, type, booking_id, user_id, recipient_name, customer_name, customer_phone,
                   customer_email, vehicle_info, claim_reference, insurance_company_id, subtotal, discount_type,
                   discount_value, discount_amount, vat_enabled, vat_rate, vat_amount, less_excess, total,
                   'unpaid', 0, '', NULL, '', notes, NOW(), due_date, ?
                FROM invoices WHERE id = ?");
            $s->bind_param('sii', $newNum, $cb, $iid); $s->execute(); $newId = (int)$s->insert_id; $s->close();
            $s2 = $conn->prepare("
                INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, line_total, sort_order)
                SELECT ?, item_type, description, quantity, unit_price, line_total, sort_order FROM invoice_items WHERE invoice_id = ?");
            $s2->bind_param('ii', $newId, $iid); $s2->execute(); $s2->close();
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Duplicated', 'invoice_id' => $newId, 'invoice_number' => $newNum]); exit;
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
        }
    }
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// ── Inputs ──
$id         = (int)($data['id'] ?? 0);
$is_new     = ($id === 0);
$booking_id = !empty($data['booking_id']) ? (int)$data['booking_id'] : null;
$user_id    = !empty($data['user_id'])    ? (int)$data['user_id']    : null;
$type       = in_array($data['type'] ?? '', ['invoice','quotation']) ? $data['type'] : 'invoice';

$customer_name  = trim($data['customer_name']  ?? '');
$customer_phone = trim($data['customer_phone'] ?? '');
$customer_email = trim($data['customer_email'] ?? '');
$vehicle_info   = trim($data['vehicle_info']   ?? '');
$recipient_name = trim($data['recipient_name'] ?? '');   // For insurance "Att:" line
$claim_reference= trim($data['claim_reference']?? '');   // BT/MPRV/CLM/XXXXX

// Insurance company link — NULL if "Not an insurance job" was chosen
$insurance_company_id = isset($data['insurance_company_id']) && $data['insurance_company_id'] !== '' && $data['insurance_company_id'] !== '0'
    ? (int)$data['insurance_company_id']
    : null;
// Defensive: verify the company id exists (prevents bogus foreign keys)
if ($insurance_company_id !== null) {
    $co_check = $conn->prepare("SELECT 1 FROM insurance_companies WHERE id = ? LIMIT 1");
    $co_check->bind_param('i', $insurance_company_id);
    $co_check->execute();
    if (!$co_check->get_result()->fetch_row()) {
        $insurance_company_id = null;   // invalid — silently treat as Others
    }
    $co_check->close();
}

if ($customer_name === '' || $customer_phone === '') {
    echo json_encode(['success' => false, 'message' => 'Customer name and phone are required']);
    exit;
}

// ── Sanitize line items ──
$items = is_array($data['items'] ?? null) ? $data['items'] : [];
$clean_items = [];
$subtotal = 0.0;
foreach ($items as $idx => $it) {
    $desc = trim($it['description'] ?? '');
    if ($desc === '') continue;
    $qty   = max(0, (float)($it['quantity']   ?? 0));
    $price = max(0, (float)($it['unit_price'] ?? 0));
    $line  = round($qty * $price, 2);
    $clean_items[] = [
        'item_type'   => in_array($it['item_type'] ?? '', ['part','labour','service','other']) ? $it['item_type'] : 'service',
        'description' => $desc,
        'quantity'    => $qty,
        'unit_price'  => $price,
        'line_total'  => $line,
        'sort_order'  => (int)$idx,
    ];
    $subtotal += $line;
}
if (empty($clean_items)) {
    echo json_encode(['success' => false, 'message' => 'Add at least one line item with a description']);
    exit;
}

// ── Discount + VAT (compute server-side) ──
$discount_type  = in_array($data['discount_type'] ?? 'none', ['none','percent','fixed']) ? $data['discount_type'] : 'none';
$discount_value = max(0, (float)($data['discount_value'] ?? 0));
$vat_enabled    = !empty($data['vat_enabled']) ? 1 : 0;
$vat_rate       = WM_VAT_RATE;
$less_excess    = max(0, (float)($data['less_excess'] ?? 0));

$totals = wm_invoice_calc($subtotal, $discount_type, $discount_value, (bool)$vat_enabled, $vat_rate, $less_excess);

// ── Payment ──
$payment_status = in_array($data['payment_status'] ?? 'unpaid', ['unpaid','partial','paid','void']) ? $data['payment_status'] : 'unpaid';
$payment_method = in_array($data['payment_method'] ?? '', ['','cash','airtel_money','tnm_mpamba','bank_transfer','card','other']) ? $data['payment_method'] : '';
$payment_date   = !empty($data['payment_date']) ? $data['payment_date'] : null;
$payment_notes  = trim($data['payment_notes'] ?? '');
$amount_paid    = max(0, (float)($data['amount_paid'] ?? 0));

if ($payment_status === 'paid' && $amount_paid <= 0) {
    $amount_paid = $totals['total'];
}

$issued_at  = !empty($data['issued_at']) ? $data['issued_at'] . ' ' . date('H:i:s') : date('Y-m-d H:i:s');
$due_date   = !empty($data['due_date']) ? $data['due_date'] : null;
$notes      = trim($data['notes'] ?? '');
$created_by = (int)$_SESSION['admin_id'];

// Extract typed totals into local vars (so bind_param can reference them)
$subtotal_v        = $totals['subtotal'];
$discount_amount_v = $totals['discount_amount'];
$vat_amount_v      = $totals['vat_amount'];
$less_excess_v     = $totals['less_excess'];
$total_v           = $totals['total'];

$conn->begin_transaction();
try {

    if ($is_new) {
        $invoice_number = wm_invoice_next_number($conn);

        $stmt = $conn->prepare("
            INSERT INTO invoices (
                invoice_number, type, booking_id, user_id, recipient_name,
                customer_name, customer_phone, customer_email, vehicle_info, claim_reference, insurance_company_id,
                subtotal, discount_type, discount_value, discount_amount,
                vat_enabled, vat_rate, vat_amount, less_excess, total,
                payment_status, amount_paid, payment_method, payment_date, payment_notes,
                notes, issued_at, due_date, created_by
            ) VALUES (?,?,?,?,?, ?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)
        ");
        // Types breakdown:
        //  invoice_number, type, booking_id, user_id, recipient_name      → s s i i s     (5)
        //  customer_name, phone, email, vehicle_info, claim_ref, ins_co_id → s s s s s i  (6)
        //  subtotal, discount_type, discount_value, discount_amount       → d s d d       (4)
        //  vat_enabled, vat_rate, vat_amount, less_excess, total          → i d d d d     (5)
        //  payment_status, amount_paid, payment_method, payment_date,
        //  payment_notes                                                   → s d s s s    (5)
        //  notes, issued_at, due_date, created_by                          → s s s i      (4)
        //  Total: 5+6+4+5+5+4 = 29 chars, matching 29 placeholders
        $types = 'ssiis' . 'sssssi' . 'dsdd' . 'idddd' . 'sdsss' . 'sssi';
        $stmt->bind_param(
            $types,
            $invoice_number, $type, $booking_id, $user_id, $recipient_name,
            $customer_name, $customer_phone, $customer_email, $vehicle_info, $claim_reference, $insurance_company_id,
            $subtotal_v, $discount_type, $discount_value, $discount_amount_v,
            $vat_enabled, $vat_rate, $vat_amount_v, $less_excess_v, $total_v,
            $payment_status, $amount_paid, $payment_method, $payment_date, $payment_notes,
            $notes, $issued_at, $due_date, $created_by
        );
        if (!$stmt->execute()) throw new Exception('Insert failed: ' . $stmt->error);
        $invoice_id = $stmt->insert_id;
        $stmt->close();

    } else {
        // Load existing to check void status
        $sel = $conn->prepare("SELECT invoice_number, payment_status FROM invoices WHERE id = ?");
        $sel->bind_param('i', $id);
        $sel->execute();
        $existing = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!$existing) throw new Exception('Invoice not found');
        if ($existing['payment_status'] === 'void' && $payment_status !== 'void') {
            throw new Exception('Cannot un-void an invoice');
        }
        $invoice_number = $existing['invoice_number'];
        $invoice_id = $id;

        $stmt = $conn->prepare("
            UPDATE invoices SET
                type=?, booking_id=?, user_id=?, recipient_name=?,
                customer_name=?, customer_phone=?, customer_email=?, vehicle_info=?, claim_reference=?, insurance_company_id=?,
                subtotal=?, discount_type=?, discount_value=?, discount_amount=?,
                vat_enabled=?, vat_rate=?, vat_amount=?, less_excess=?, total=?,
                payment_status=?, amount_paid=?, payment_method=?, payment_date=?, payment_notes=?,
                notes=?, issued_at=?, due_date=?
            WHERE id=?
        ");
        // Types breakdown:
        //  type, booking_id, user_id, recipient_name                       → s i i s   (4)
        //  customer_name, phone, email, vehicle_info, claim_ref, ins_co_id → s s s s s i (6)
        //  subtotal, discount_type, discount_value, discount_amount        → d s d d   (4)
        //  vat_enabled, vat_rate, vat_amount, less_excess, total           → i d d d d (5)
        //  payment_status, amount_paid, payment_method, payment_date,
        //  payment_notes                                                   → s d s s s (5)
        //  notes, issued_at, due_date                                      → s s s     (3)
        //  id (WHERE)                                                      → i         (1)
        //  Total: 4+6+4+5+5+3+1 = 28
        $types = 'siis' . 'sssssi' . 'dsdd' . 'idddd' . 'sdsss' . 'sss' . 'i';
        $stmt->bind_param(
            $types,
            $type, $booking_id, $user_id, $recipient_name,
            $customer_name, $customer_phone, $customer_email, $vehicle_info, $claim_reference, $insurance_company_id,
            $subtotal_v, $discount_type, $discount_value, $discount_amount_v,
            $vat_enabled, $vat_rate, $vat_amount_v, $less_excess_v, $total_v,
            $payment_status, $amount_paid, $payment_method, $payment_date, $payment_notes,
            $notes, $issued_at, $due_date,
            $id
        );
        if (!$stmt->execute()) throw new Exception('Update failed: ' . $stmt->error);
        $stmt->close();

        // Wipe and re-insert line items
        $del = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
    }

    // Insert all line items
    $ins = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, line_total, sort_order) VALUES (?,?,?,?,?,?,?)");
    foreach ($clean_items as $it) {
        // Types: i s s d d d i
        $ins->bind_param(
            'issdddi',
            $invoice_id, $it['item_type'], $it['description'],
            $it['quantity'], $it['unit_price'], $it['line_total'], $it['sort_order']
        );
        if (!$ins->execute()) throw new Exception('Item insert failed: ' . $ins->error);
    }
    $ins->close();

    $conn->commit();

    echo json_encode([
        'success'        => true,
        'invoice_id'     => (int)$invoice_id,
        'invoice_number' => $invoice_number,
        'total'          => $totals['total'],
    ]);

} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
