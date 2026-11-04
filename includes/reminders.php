<?php
/**
 * includes/reminders.php — automated customer reminders for Wiyule Motors.
 *
 *   wm_send_payment_reminders($conn, $dryrun)   overdue invoices → payment reminder email
 *   wm_send_quote_expiry_nudges($conn, $dryrun)  quotations near 14-day expiry → nudge email
 *   wm_run_reminders($conn, $dryrun)             runs both, returns a summary array
 *
 * Uses invoices.last_reminder_at so the same customer isn't emailed repeatedly.
 * In dry-run mode nothing is sent and last_reminder_at is not touched.
 */

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/email.php';

/** Overdue invoices: remind at most once every 7 days. */
function wm_send_payment_reminders(mysqli $conn, bool $dryrun = false): array {
    $sent = 0; $would = 0; $skipped_no_email = 0; $details = [];
    $sql = "SELECT * FROM invoices
            WHERE type='invoice' AND payment_status IN ('unpaid','partial') AND archived_at IS NULL
              AND due_date IS NOT NULL AND due_date < CURDATE()
              AND (last_reminder_at IS NULL OR last_reminder_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
            ORDER BY due_date ASC";
    $res = $conn->query($sql);
    while ($res && ($inv = $res->fetch_assoc())) {
        $email = trim($inv['customer_email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped_no_email++; continue; }
        if ($dryrun) { $would++; $details[] = $inv['invoice_number'] . ' → ' . $email; continue; }
        $r = wm_send_payment_reminder_email($inv);
        if (!empty($r['ok'])) {
            $sent++; $details[] = $inv['invoice_number'] . ' → ' . $email;
            $st = $conn->prepare("UPDATE invoices SET last_reminder_at = NOW() WHERE id = ?");
            $st->bind_param('i', $inv['id']); $st->execute(); $st->close();
        }
    }
    return ['sent' => $sent, 'would_send' => $would, 'skipped_no_email' => $skipped_no_email, 'details' => $details];
}

/** Quotations 11–14 days old (approaching 14-day expiry): nudge once. */
function wm_send_quote_expiry_nudges(mysqli $conn, bool $dryrun = false): array {
    $sent = 0; $would = 0; $skipped_no_email = 0; $details = [];
    $sql = "SELECT * FROM invoices
            WHERE type='quotation' AND archived_at IS NULL
              AND DATEDIFF(CURDATE(), DATE(issued_at)) BETWEEN 11 AND 14
              AND last_reminder_at IS NULL
            ORDER BY issued_at ASC";
    $res = $conn->query($sql);
    while ($res && ($inv = $res->fetch_assoc())) {
        $email = trim($inv['customer_email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped_no_email++; continue; }
        if ($dryrun) { $would++; $details[] = $inv['invoice_number'] . ' → ' . $email; continue; }
        $r = wm_send_quote_expiry_email($inv);
        if (!empty($r['ok'])) {
            $sent++; $details[] = $inv['invoice_number'] . ' → ' . $email;
            $st = $conn->prepare("UPDATE invoices SET last_reminder_at = NOW() WHERE id = ?");
            $st->bind_param('i', $inv['id']); $st->execute(); $st->close();
        }
    }
    return ['sent' => $sent, 'would_send' => $would, 'skipped_no_email' => $skipped_no_email, 'details' => $details];
}

function wm_run_reminders(mysqli $conn, bool $dryrun = false): array {
    return [
        'dryrun'   => $dryrun,
        'payments' => wm_send_payment_reminders($conn, $dryrun),
        'quotes'   => wm_send_quote_expiry_nudges($conn, $dryrun),
    ];
}
