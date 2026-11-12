<?php
/**
 * pages/process-wrap-quote.php — Vehicle-wrapping quote request handler
 * ---------------------------------------------------------------------
 * Receives a JSON/POST quote request from /wrapping.php, validates it,
 * and emails the owner (OWNER_EMAIL) via the existing Brevo helper.
 * v1 is EMAIL-ONLY — it does not touch the database (no schema changes).
 */

session_start();
require_once __DIR__ . '/../includes/db_connection.php'; // mirrors process-booking.php (sets timezone, charset)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => ['Method not allowed.']]);
    exit;
}

header('Content-Type: application/json');

// ── Read + trim incoming fields ─────────────────────────────────────
$name         = trim($_POST['name']         ?? '');
$phone        = trim($_POST['phone']        ?? '');
$vehicle_type = trim($_POST['vehicle_type'] ?? '');
$colour_name  = trim($_POST['colour_name']  ?? '');
$colour_hex   = trim($_POST['colour_hex']   ?? '');
$finish       = trim($_POST['finish']       ?? '');
$notes        = trim($_POST['notes']        ?? '');

// ── Validate the genuine minimums ───────────────────────────────────
$errors = [];
if ($name === '')  $errors[] = 'Please tell us your name.';
if ($phone === '') $errors[] = 'A phone number is required so we can reach you.';

if (!empty($errors)) {
    // HTTP 200 + JSON (matches the booking pattern — the client reads success/errors)
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Normalise a couple of display fields
if ($vehicle_type === '') $vehicle_type = 'Not specified';
if ($colour_name  === '') $colour_name  = 'Not specified';
if ($finish       === '') $finish       = 'Not specified';
// Keep hex sane for display (expect #rrggbb); fall back to blank if odd
if ($colour_hex !== '' && !preg_match('/^#?[0-9a-fA-F]{3,8}$/', $colour_hex)) {
    $colour_hex = '';
}

// ── Email the owner using the existing Brevo infra ──────────────────
try {
    require_once __DIR__ . '/../includes/config.php'; // defines OWNER_EMAIL (guarded by require_once)
    require_once __DIR__ . '/../includes/email.php';

    $owner = defined('OWNER_EMAIL') ? OWNER_EMAIL
           : (defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : '');

    if ($owner === '') {
        error_log('Wrap quote: no owner email configured.');
        // Still report success to the customer — their request isn't their fault.
        echo json_encode(['success' => true]);
        exit;
    }

    $e_name   = htmlspecialchars($name,         ENT_QUOTES);
    $e_phone  = htmlspecialchars($phone,        ENT_QUOTES);
    $e_vtype  = htmlspecialchars($vehicle_type, ENT_QUOTES);
    $e_cname  = htmlspecialchars($colour_name,  ENT_QUOTES);
    $e_chex   = htmlspecialchars($colour_hex,   ENT_QUOTES);
    $e_finish = htmlspecialchars($finish,       ENT_QUOTES);
    $e_notes  = $notes !== '' ? nl2br(htmlspecialchars($notes, ENT_QUOTES)) : '<span style="color:#94a3b8;">—</span>';
    $when     = date('d M Y, H:i');

    // A little colour swatch chip in the email (safe: hex already validated)
    $swatch = $e_chex !== ''
        ? '<span style="display:inline-block;width:14px;height:14px;border-radius:50%;border:1px solid #cbd5e1;background:' . $e_chex . ';vertical-align:middle;margin-right:7px;"></span>'
        : '';

    $rows = [
        'Customer'     => $e_name,
        'Phone'        => '<a href="tel:' . $e_phone . '" style="color:#dc2626;text-decoration:none;">' . $e_phone . '</a>',
        'Vehicle type' => $e_vtype,
        'Wrap colour'  => $swatch . $e_cname . ($e_chex !== '' ? ' <span style="color:#94a3b8;font-size:12px;">' . $e_chex . '</span>' : ''),
        'Finish'       => $e_finish,
        'Requested'    => htmlspecialchars($when, ENT_QUOTES),
    ];
    $tbl = '';
    foreach ($rows as $k => $v) {
        $tbl .= '<tr>'
              . '<td style="padding:7px 0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;vertical-align:top;">' . $k . '</td>'
              . '<td style="padding:7px 0;font-size:14px;color:#0f172a;text-align:right;">' . $v . '</td>'
              . '</tr>';
    }

    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">A new vehicle-wrap quote request came in via the website configurator.</p>'
          . '<table style="width:100%;border-collapse:collapse;">' . $tbl . '</table>'
          . '<div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">'
          . '<div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Notes</div>'
          . '<div style="font-size:14px;color:#334155;line-height:1.6;">' . $e_notes . '</div>'
          . '</div>';

    // Reuse the shared branded shell if available; otherwise fall back to plain HTML.
    if (function_exists('_wm_email_shell')) {
        $html = _wm_email_shell('New wrap quote request', 'Vehicle Wrap', $body);
    } else {
        $html = '<div style="font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#eef1f5;padding:24px;color:#0f172a;">'
              . '<div style="max-width:540px;margin:0 auto;background:#fff;border-radius:14px;padding:24px;">'
              . '<h1 style="font-size:19px;margin:0 0 14px;">New wrap quote request</h1>' . $body . '</div></div>';
    }

    $text = "New vehicle-wrap quote request:\n"
          . "Customer: $name\n"
          . "Phone: $phone\n"
          . "Vehicle type: $vehicle_type\n"
          . "Wrap colour: $colour_name" . ($colour_hex !== '' ? " ($colour_hex)" : '') . "\n"
          . "Finish: $finish\n"
          . "Notes: " . ($notes !== '' ? $notes : '—') . "\n"
          . "Requested: $when\n\n— Wiyule & F.A Motors website";

    $send = wm_send_email($owner, 'Wiyule & F.A Motors', '🎨 New wrap quote request', $html, $text);

    if (!empty($send['ok'])) {
        echo json_encode(['success' => true]);
    } else {
        error_log('Wrap quote email failed: ' . ($send['error'] ?? 'unknown'));
        // The customer still gets WhatsApp as a reliable fallback, so surface a soft error.
        echo json_encode([
            'success' => false,
            'errors'  => ['We could not send your request right now. Please WhatsApp us or call +265 993 575 111.'],
        ]);
    }
} catch (Throwable $ex) {
    error_log('Wrap quote handler error: ' . $ex->getMessage());
    echo json_encode([
        'success' => false,
        'errors'  => ['Something went wrong. Please WhatsApp us or call +265 993 575 111.'],
    ]);
}
