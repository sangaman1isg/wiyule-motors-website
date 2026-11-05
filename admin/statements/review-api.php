<?php
/**
 * admin/statements/review-api.php
 * Quick-fix endpoint for the statement-import reconciliation queue.
 * Session-gated (SameSite=Lax cookie = CSRF defence, same as the rest of admin).
 * Actions (form-encoded POST):
 *   set_number   {invoice_id, invoice_number}  -> rename invoice (unique check) + resolve number faults
 *   set_date     {invoice_id, issued_at}        -> set issue date + resolve date faults
 *   resolve      {invoice_id}                    -> mark every open review on this invoice done
 *   resolve_item {review_id}                     -> mark one review item done
 * Returns JSON {success, message, open_count}.
 */
require_once __DIR__ . '/../../includes/security.php';
wm_secure_session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
require_once __DIR__ . '/../../includes/db_connection.php';

function scalar(mysqli $conn, string $sql, int $id = 0): int {
    if ($id) { $st = $conn->prepare($sql); $st->bind_param('i', $id); $st->execute();
               $v = (int)($st->get_result()->fetch_row()[0] ?? 0); $st->close(); return $v; }
    $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0;
}
function done(mysqli $conn, bool $ok, string $msg, int $invoice_id = 0): void {
    echo json_encode([
        'success'        => $ok,
        'message'        => $msg,
        'open_count'     => scalar($conn, "SELECT COUNT(*) FROM import_reviews WHERE resolved = 0"),
        'resolved_count' => scalar($conn, "SELECT COUNT(*) FROM import_reviews WHERE resolved = 1"),
        'invoice_open'   => $invoice_id ? scalar($conn, "SELECT COUNT(*) FROM import_reviews WHERE resolved = 0 AND invoice_id = ?", $invoice_id) : 0,
    ]);
    exit;
}

$action     = $_POST['action'] ?? '';
$invoice_id = (int)($_POST['invoice_id'] ?? 0);

try {
    if ($action === 'set_number') {
        $num = trim($_POST['invoice_number'] ?? '');
        if ($num === '')            done($conn, false, 'Enter an invoice number.');
        if (mb_strlen($num) > 20)   done($conn, false, 'Invoice number must be 20 characters or fewer.');
        if ($invoice_id <= 0)       done($conn, false, 'Missing invoice.');

        // Uniqueness (exclude this invoice)
        $chk = $conn->prepare("SELECT id FROM invoices WHERE invoice_number = ? AND id <> ? LIMIT 1");
        $chk->bind_param('si', $num, $invoice_id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $chk->close();
            done($conn, false, "Invoice number “{$num}” is already used by another invoice.");
        }
        $chk->close();

        $up = $conn->prepare("UPDATE invoices SET invoice_number = ? WHERE id = ?");
        $up->bind_param('si', $num, $invoice_id);
        $up->execute();
        if ($up->affected_rows < 0) { $up->close(); done($conn, false, 'Could not update the invoice.'); }
        $up->close();

        $res = $conn->prepare("UPDATE import_reviews SET resolved = 1, resolved_at = NOW()
                               WHERE invoice_id = ? AND resolved = 0
                               AND reason IN ('placeholder_invno','dup_suffixed')");
        $res->bind_param('i', $invoice_id);
        $res->execute();
        $res->close();
        done($conn, true, "Invoice number set to $num.", $invoice_id);
    }

    if ($action === 'set_date') {
        $raw = trim($_POST['issued_at'] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            done($conn, false, 'Enter a valid date.');
        }
        if ($invoice_id <= 0) done($conn, false, 'Missing invoice.');
        $dt = $raw . ' 00:00:00';
        $up = $conn->prepare("UPDATE invoices SET issued_at = ? WHERE id = ?");
        $up->bind_param('si', $dt, $invoice_id);
        $up->execute();
        $up->close();

        $res = $conn->prepare("UPDATE import_reviews SET resolved = 1, resolved_at = NOW()
                               WHERE invoice_id = ? AND resolved = 0 AND reason = 'date_fallback'");
        $res->bind_param('i', $invoice_id);
        $res->execute();
        $res->close();
        done($conn, true, "Invoice date set to $raw.", $invoice_id);
    }

    if ($action === 'resolve') {
        if ($invoice_id <= 0) done($conn, false, 'Missing invoice.');
        $res = $conn->prepare("UPDATE import_reviews SET resolved = 1, resolved_at = NOW()
                               WHERE invoice_id = ? AND resolved = 0");
        $res->bind_param('i', $invoice_id);
        $res->execute();
        $n = $res->affected_rows;
        $res->close();
        done($conn, true, "Marked $n item(s) reconciled.", $invoice_id);
    }

    if ($action === 'resolve_item') {
        $rid = (int)($_POST['review_id'] ?? 0);
        if ($rid <= 0) done($conn, false, 'Missing item.');
        $res = $conn->prepare("UPDATE import_reviews SET resolved = 1, resolved_at = NOW() WHERE id = ? AND resolved = 0");
        $res->bind_param('i', $rid);
        $res->execute();
        $res->close();
        done($conn, true, 'Item reconciled.');
    }

    done($conn, false, 'Unknown action.');
} catch (\Throwable $e) {
    done($conn, false, 'Error: ' . $e->getMessage());
}
