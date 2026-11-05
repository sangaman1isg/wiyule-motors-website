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

/**
 * Service-due reminders: vehicles whose last completed service was 80+ days ago,
 * or never serviced but on record 80+ days. Reminded at most once every 30 days.
 */
function wm_send_service_due_nudges(mysqli $conn, bool $dryrun = false): array {
    $sent = 0; $would = 0; $skipped_no_email = 0; $details = [];
    $sql = "SELECT v.id AS vehicle_id, v.make, v.model, v.registration, v.created_at AS vehicle_created,
                   u.full_name, u.email,
                   (SELECT MAX(b.booking_date) FROM bookings b WHERE b.user_id = u.id AND b.status='completed') AS last_service
            FROM vehicles v JOIN users u ON u.id = v.user_id
            WHERE u.is_admin = 0
              AND (v.last_service_reminder_at IS NULL OR v.last_service_reminder_at < DATE_SUB(NOW(), INTERVAL 30 DAY))";
    $res = $conn->query($sql);
    while ($res && ($r = $res->fetch_assoc())) {
        // Due logic (mirrors the dashboard): skip if not actually due yet
        $last = $r['last_service'];
        if ($last !== null) {
            if (floor((time() - strtotime($last)) / 86400) < 80) continue;
            $last_text = 'last serviced ' . date('M Y', strtotime($last));
        } else {
            $age = $r['vehicle_created'] ? floor((time() - strtotime($r['vehicle_created'])) / 86400) : 0;
            if ($age < 80) continue;
            $last_text = 'no service on record yet';
        }
        $email = trim($r['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped_no_email++; continue; }
        $veh = trim($r['make'] . ' ' . $r['model']) . ($r['registration'] ? ' (' . $r['registration'] . ')' : '');
        if ($dryrun) { $would++; $details[] = $veh . ' → ' . $email; continue; }
        $res2 = wm_send_service_due_email($email, $r['full_name'], $veh, $last_text);
        if (!empty($res2['ok'])) {
            $sent++; $details[] = $veh . ' → ' . $email;
            $st = $conn->prepare("UPDATE vehicles SET last_service_reminder_at = NOW() WHERE id = ?");
            $st->bind_param('i', $r['vehicle_id']); $st->execute(); $st->close();
        }
    }
    return ['sent' => $sent, 'would_send' => $would, 'skipped_no_email' => $skipped_no_email, 'details' => $details];
}

function wm_run_reminders(mysqli $conn, bool $dryrun = false): array {
    return [
        'dryrun'       => $dryrun,
        'payments'     => wm_send_payment_reminders($conn, $dryrun),
        'quotes'       => wm_send_quote_expiry_nudges($conn, $dryrun),
        'service_due'  => wm_send_service_due_nudges($conn, $dryrun),
    ];
}
