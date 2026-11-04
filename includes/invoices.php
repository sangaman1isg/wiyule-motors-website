<?php
/**
 * includes/invoices.php — Shared invoice helpers for Wiyule Motors
 *
 * Functions:
 *   wm_invoice_next_number($conn)          → "WM-2026-001"
 *   wm_invoice_calc($subtotal, ...)        → computed totals (subtotal, discount, vat, total)
 *   wm_invoice_format_mwk($n)              → "MWK 45,000.00"
 *   wm_invoice_fetch($conn, $id)           → full invoice + items
 *   wm_invoice_status_label($status)       → human label + colour
 */

const WM_VAT_RATE = 17.50;
const WM_INVOICE_PREFIX = 'WM';

/**
 * Atomically generate the next invoice number for the current year.
 * Uses the invoice_counter table to prevent race conditions.
 *
 * Returns: "WM-2026-001"
 */
function wm_invoice_next_number(mysqli $conn): string {
    $year = (int)date('Y');

    $conn->begin_transaction();
    try {
        // Lock the row for this year (or insert if new year)
        $stmt = $conn->prepare("SELECT last_number FROM invoice_counter WHERE year = ? FOR UPDATE");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $next = (int)$row['last_number'] + 1;
            $up = $conn->prepare("UPDATE invoice_counter SET last_number = ? WHERE year = ?");
            $up->bind_param('ii', $next, $year);
            $up->execute();
            $up->close();
        } else {
            $next = 1;
            $ins = $conn->prepare("INSERT INTO invoice_counter (year, last_number) VALUES (?, ?)");
            $ins->bind_param('ii', $year, $next);
            $ins->execute();
            $ins->close();
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return sprintf('%s-%d-%03d', WM_INVOICE_PREFIX, $year, $next);
}

/**
 * Calculate totals from raw inputs.
 * Returns: ['subtotal', 'discount_amount', 'after_discount', 'vat_amount', 'subtotal_with_vat', 'less_excess', 'total']
 *
 * Order of operations matches Wiyule's invoice layout:
 *   Subtotal of items
 *   – Discount (if any)
 *   = After discount
 *   + VAT (if enabled)
 *   = Subtotal with VAT
 *   – Less Excess (insurance jobs)
 *   = Total / Net Payable
 */
function wm_invoice_calc(float $subtotal, string $discount_type, float $discount_value, bool $vat_enabled, float $vat_rate = WM_VAT_RATE, float $less_excess = 0.0): array {
    $subtotal = max(0, $subtotal);

    // Discount
    $discount_amount = 0.0;
    if ($discount_type === 'percent') {
        $discount_amount = $subtotal * (max(0, min(100, $discount_value)) / 100);
    } elseif ($discount_type === 'fixed') {
        $discount_amount = min($subtotal, max(0, $discount_value));
    }

    $after_discount = $subtotal - $discount_amount;

    // VAT
    $vat_amount = $vat_enabled ? $after_discount * ($vat_rate / 100) : 0.0;

    $subtotal_with_vat = $after_discount + $vat_amount;

    // Insurance excess (subtracted last)
    $less_excess = max(0, min($subtotal_with_vat, $less_excess));

    $total = $subtotal_with_vat - $less_excess;

    return [
        'subtotal'          => round($subtotal, 2),
        'discount_amount'   => round($discount_amount, 2),
        'after_discount'    => round($after_discount, 2),
        'vat_amount'        => round($vat_amount, 2),
        'subtotal_with_vat' => round($subtotal_with_vat, 2),
        'less_excess'       => round($less_excess, 2),
        'total'             => round($total, 2),
    ];
}

/**
 * Format an MWK amount: 45000 → "MWK 45,000.00"
 */
function wm_invoice_format_mwk($amount): string {
    return 'MWK ' . number_format((float)$amount, 2, '.', ',');
}

/**
 * Fetch a full invoice + its line items by ID.
 * Returns ['invoice' => ..., 'items' => [...]] or null if not found.
 */
function wm_invoice_fetch(mysqli $conn, int $id): ?array {
    $s = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $s->bind_param('i', $id);
    $s->execute();
    $invoice = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$invoice) return null;

    $s = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC");
    $s->bind_param('i', $id);
    $s->execute();
    $items = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    return ['invoice' => $invoice, 'items' => $items];
}

/**
 * Human-readable status with colour for UI.
 */
function wm_invoice_status_label(string $status): array {
    $map = [
        'unpaid'  => ['Unpaid',  '#dc2626'],
        'partial' => ['Partial', '#f59e0b'],
        'paid'    => ['Paid',    '#16a34a'],
        'void'    => ['Void',    '#94a3b8'],
    ];
    return $map[$status] ?? ['Unknown', '#64748b'];
}

/**
 * Same for type (quotation vs invoice).
 */
function wm_invoice_type_label(string $type): array {
    return $type === 'quotation'
        ? ['Quotation', '#3b82f6']
        : ['Invoice',   '#0f172a'];
}

/**
 * Human-readable payment method.
 */
function wm_invoice_payment_method_label(string $m): string {
    $map = [
        ''               => '—',
        'cash'           => 'Cash',
        'airtel_money'   => 'Airtel Money',
        'tnm_mpamba'     => 'TNM Mpamba',
        'bank_transfer'  => 'Bank Transfer',
        'card'           => 'Card',
        'other'          => 'Other',
    ];
    return $map[$m] ?? '—';
}

/**
 * Section name for grouping line items in print view.
 */
function wm_invoice_section_name(string $item_type): string {
    return [
        'part'    => 'Spares',
        'labour'  => 'Labour',
        'service' => 'Services',
        'other'   => 'Material / Consumables',
    ][$item_type] ?? 'Other';
}

/**
 * Group items by section for print view, preserving sort_order within each group.
 * Returns: ['Spares' => [...], 'Labour' => [...], ...]  in insertion order matching the invoice layout.
 */
function wm_invoice_group_items(array $items): array {
    // Preferred order matching Wiyule's traditional layout: Spares, Labour, Services, Material
    $order = ['part', 'labour', 'service', 'other'];
    $groups = [];
    foreach ($order as $type) {
        $section = wm_invoice_section_name($type);
        foreach ($items as $it) {
            if ($it['item_type'] === $type) {
                $groups[$section][] = $it;
            }
        }
    }
    return $groups;
}
