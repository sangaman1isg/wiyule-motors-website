<?php
/**
 * includes/email.php — Brevo (Sendinblue) email helper
 * -----------------------------------------------------
 * Sends transactional emails via Brevo's HTTP API.
 * No PHPMailer / SMTP setup needed — just curl + the API key.
 *
 * Usage:
 *   require_once __DIR__ . '/email.php';
 *   wm_send_verification_email('jane@example.com', 'Jane', '482913');
 */

require_once __DIR__ . '/config.php';

/**
 * Low-level: send any email via Brevo.
 * Returns ['ok'=>true] on success or ['ok'=>false, 'error'=>...] on failure.
 */
function wm_send_email($to_email, $to_name, $subject, $html_body, $text_body = '') {
    // Local dev (php -S): never hit Brevo. Capture the email to a file so it can
    // be inspected, and report success. Never runs in production (different SAPI).
    if (php_sapi_name() === 'cli-server') {
        $f = sys_get_temp_dir() . '/wm_dev_mail.html';
        @file_put_contents($f, "<!-- DEV MAIL (not sent) | TO: $to_email | SUBJECT: $subject -->\n" . $html_body);
        error_log("[DEV MAIL] to=$to_email subject=\"$subject\" written to $f (not sent)");
        return ['ok' => true, 'dev' => true];
    }

    if (!defined('BREVO_API_KEY') || BREVO_API_KEY === 'PASTE_YOUR_BREVO_API_KEY_HERE') {
        return ['ok' => false, 'error' => 'Brevo API key not configured'];
    }

    $payload = [
        'sender' => [
            'name'  => BREVO_SENDER_NAME,
            'email' => BREVO_SENDER_EMAIL,
        ],
        'to' => [[
            'email' => $to_email,
            'name'  => $to_name ?: $to_email,
        ]],
        'subject'     => $subject,
        'htmlContent' => $html_body,
    ];
    if ($text_body) $payload['textContent'] = $text_body;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) return ['ok' => true];

    error_log("Brevo email failure: HTTP $code, body=$response, curl_err=$err");
    return ['ok' => false, 'error' => "Email service returned HTTP $code"];
}

/**
 * Send a 6-digit verification code to a new signup.
 */
function wm_send_verification_email($to_email, $to_name, $code) {
    $subject = 'Your Wiyule & F.A Motors verification code: ' . $code;
    $safe_name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);

    $html = '
<div style="font-family: -apple-system, Segoe UI, Arial, sans-serif; background:#0b0f1a; padding:32px 16px; color:#f1f5f9;">
  <div style="max-width:520px; margin:0 auto; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:32px;">
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:22px; font-weight:800; margin:0; color:#fff;">Welcome to Wiyule & F.A Motors</h1>
      <p style="font-size:14px; color:#94a3b8; margin:6px 0 0;">Verify your email to activate your account</p>
    </div>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 18px;">Hi ' . $safe_name . ',</p>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 24px;">Enter this 6-digit code on the verification page to finish creating your account:</p>
    <div style="background:#0b0f1a; border:1px solid rgba(220,38,38,0.4); border-radius:14px; padding:24px; text-align:center; margin:0 0 24px;">
      <div style="font-size:38px; font-weight:800; letter-spacing:0.3em; color:#fff; font-family: SF Mono, Menlo, monospace;">' . $code . '</div>
    </div>
    <p style="font-size:13px; color:#94a3b8; margin:0 0 12px;">This code expires in <strong style="color:#f1f5f9;">15 minutes</strong>. If you didn\'t sign up, you can ignore this email.</p>
    <hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:24px 0;">
    <p style="font-size:12px; color:#64748b; margin:0; text-align:center;">Wiyule & F.A Motors · Blantyre, Malawi · +265 993 575 111</p>
  </div>
</div>';

    $text = "Hi $to_name,\n\nYour Wiyule & F.A Motors verification code is: $code\n\nIt expires in 15 minutes.\n\nIf you didn't sign up, ignore this email.\n\n— Wiyule & F.A Motors";

    return wm_send_email($to_email, $to_name, $subject, $html, $text);
}

/**
 * Send a 6-digit sign-in (2FA) code to an existing customer logging in.
 */
function wm_send_login_code_email($to_email, $to_name, $code) {
    $subject = 'Your Wiyule & F.A Motors sign-in code: ' . $code;
    $safe_name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);

    $html = '
<div style="font-family: -apple-system, Segoe UI, Arial, sans-serif; background:#0b0f1a; padding:32px 16px; color:#f1f5f9;">
  <div style="max-width:520px; margin:0 auto; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:32px;">
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:22px; font-weight:800; margin:0; color:#fff;">Sign-in verification</h1>
      <p style="font-size:14px; color:#94a3b8; margin:6px 0 0;">Confirm it\'s really you</p>
    </div>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 18px;">Hi ' . $safe_name . ',</p>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 24px;">Use this 6-digit code to finish signing in to your Wiyule & F.A Motors account:</p>
    <div style="background:#0b0f1a; border:1px solid rgba(220,38,38,0.4); border-radius:14px; padding:24px; text-align:center; margin:0 0 24px;">
      <div style="font-size:38px; font-weight:800; letter-spacing:0.3em; color:#fff; font-family: SF Mono, Menlo, monospace;">' . $code . '</div>
    </div>
    <p style="font-size:13px; color:#94a3b8; margin:0 0 12px;">This code expires in <strong style="color:#f1f5f9;">10 minutes</strong>. If you didn\'t try to sign in, change your password and contact us.</p>
    <hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:24px 0;">
    <p style="font-size:12px; color:#64748b; margin:0; text-align:center;">Wiyule & F.A Motors · Blantyre, Malawi · +265 993 575 111</p>
  </div>
</div>';

    $text = "Hi $to_name,\n\nYour Wiyule & F.A Motors sign-in code is: $code\n\nIt expires in 10 minutes.\n\nIf you didn't try to sign in, change your password and contact us.\n\n— Wiyule & F.A Motors";

    return wm_send_email($to_email, $to_name, $subject, $html, $text);
}

/**
 * Send a password-reset link to a user who forgot their password.
 * $reset_url should be the full https:// URL with the token already appended.
 */
function wm_send_password_reset_email($to_email, $to_name, $reset_url) {
    $subject = 'Reset your Wiyule & F.A Motors password';
    $safe_name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);
    $safe_url  = htmlspecialchars($reset_url, ENT_QUOTES);

    $html = '
<div style="font-family: -apple-system, Segoe UI, Arial, sans-serif; background:#0b0f1a; padding:32px 16px; color:#f1f5f9;">
  <div style="max-width:520px; margin:0 auto; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:32px;">
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:22px; font-weight:800; margin:0; color:#fff;">Reset your password</h1>
      <p style="font-size:14px; color:#94a3b8; margin:6px 0 0;">Wiyule & F.A Motors account</p>
    </div>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 18px;">Hi ' . $safe_name . ',</p>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 24px;">We got a request to reset your password. Click the button below to choose a new one. This link expires in <strong style="color:#f1f5f9;">1 hour</strong> and can only be used once.</p>
    <div style="text-align:center; margin:0 0 24px;">
      <a href="' . $safe_url . '" style="display:inline-block; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; text-decoration:none; font-weight:700; font-size:15px; padding:14px 28px; border-radius:12px;">Reset my password</a>
    </div>
    <p style="font-size:12px; color:#94a3b8; margin:0 0 8px;">If the button doesn\'t work, copy &amp; paste this link in your browser:</p>
    <p style="font-size:12px; color:#94a3b8; margin:0 0 16px; word-break:break-all;"><a href="' . $safe_url . '" style="color:#f87171;">' . $safe_url . '</a></p>
    <p style="font-size:13px; color:#94a3b8; margin:0 0 12px;"><strong style="color:#f1f5f9;">Didn\'t request this?</strong> You can safely ignore this email — your password won\'t change unless you click the link above.</p>
    <hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:24px 0;">
    <p style="font-size:12px; color:#64748b; margin:0; text-align:center;">Wiyule & F.A Motors · Blantyre, Malawi · +265 993 575 111</p>
  </div>
</div>';

    $text = "Hi $to_name,\n\nReset your Wiyule & F.A Motors password by visiting:\n$reset_url\n\nThis link expires in 1 hour and can only be used once.\n\nIf you didn't request this, ignore this email.\n\n— Wiyule & F.A Motors";

    return wm_send_email($to_email, $to_name, $subject, $html, $text);
}

/**
 * Email a formatted invoice / quotation to the customer.
 * $invoice is a row from `invoices`; $items are its invoice_items rows.
 * Returns ['ok'=>bool, ...] from wm_send_email.
 */
function wm_send_invoice_email(array $invoice, array $items) {
    require_once __DIR__ . '/invoices.php';

    $to_email = trim($invoice['customer_email'] ?? '');
    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Customer has no valid email address'];
    }

    $is_quote = ($invoice['type'] === 'quotation');
    $doc_word = $is_quote ? 'Quotation' : 'Invoice';
    $num      = htmlspecialchars($invoice['invoice_number'], ENT_QUOTES);
    $cust     = htmlspecialchars($invoice['customer_name'], ENT_QUOTES);
    $date     = date('d M Y', strtotime($invoice['issued_at']));
    $vehicle  = htmlspecialchars($invoice['vehicle_info'] ?? '', ENT_QUOTES);
    $reg      = htmlspecialchars($invoice['registration'] ?? '', ENT_QUOTES);
    // Combined vehicle label for the intro line (e.g. "Toyota Axio — BT 1234")
    $veh_label = $reg ? ($vehicle ? $vehicle . ' — ' . $reg : $reg) : $vehicle;
    $site     = defined('SITE_URL') ? SITE_URL : 'https://www.wiyulemotors.co.mw';

    // Line-item rows grouped by section
    $rows_html = '';
    foreach (wm_invoice_group_items($items) as $section => $rows) {
        $rows_html .= '<tr><td colspan="2" style="padding:14px 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#dc2626;">' . htmlspecialchars($section) . '</td></tr>';
        foreach ($rows as $r) {
            $desc = htmlspecialchars($r['description'], ENT_QUOTES);
            $qty  = rtrim(rtrim(number_format((float)$r['quantity'], 2), '0'), '.');
            $rows_html .= '<tr>'
                . '<td style="padding:5px 0;font-size:14px;color:#334155;border-bottom:1px solid #f1f5f9;">' . $desc . ' <span style="color:#94a3b8;font-size:12px;">×' . $qty . '</span></td>'
                . '<td style="padding:5px 0;font-size:14px;color:#0f172a;text-align:right;white-space:nowrap;border-bottom:1px solid #f1f5f9;">' . wm_invoice_format_mwk($r['line_total']) . '</td>'
                . '</tr>';
        }
    }

    // Totals rows
    $totals_html = '<tr><td style="padding:6px 0;font-size:14px;color:#64748b;">Subtotal</td><td style="padding:6px 0;font-size:14px;text-align:right;color:#334155;">' . wm_invoice_format_mwk($invoice['subtotal']) . '</td></tr>';
    if ((float)$invoice['discount_amount'] > 0) {
        $totals_html .= '<tr><td style="padding:6px 0;font-size:14px;color:#64748b;">Discount</td><td style="padding:6px 0;font-size:14px;text-align:right;color:#16a34a;">− ' . wm_invoice_format_mwk($invoice['discount_amount']) . '</td></tr>';
    }
    if ($invoice['vat_enabled']) {
        $totals_html .= '<tr><td style="padding:6px 0;font-size:14px;color:#64748b;">VAT (' . rtrim(rtrim(number_format((float)$invoice['vat_rate'], 2), '0'), '.') . '%)</td><td style="padding:6px 0;font-size:14px;text-align:right;color:#334155;">' . wm_invoice_format_mwk($invoice['vat_amount']) . '</td></tr>';
    }
    if ((float)$invoice['less_excess'] > 0) {
        $totals_html .= '<tr><td style="padding:6px 0;font-size:14px;color:#64748b;">Less excess</td><td style="padding:6px 0;font-size:14px;text-align:right;color:#16a34a;">− ' . wm_invoice_format_mwk($invoice['less_excess']) . '</td></tr>';
    }
    $total_label = ((float)$invoice['less_excess'] > 0) ? 'Net Payable' : 'Total';

    $note = $is_quote
        ? 'This quotation is valid for 14 days. Additional damage identified after stripping will be quoted separately.'
        : 'Thank you for choosing Wiyule & F.A Motors. Payment within 21 days is appreciated.';

    $subject = "$doc_word $num from Wiyule & F.A Motors";

    $html = '
<div style="font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#eef1f5;padding:28px 14px;color:#0f172a;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);">
    <div style="background:#0f172a;padding:22px 28px;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:18px;font-weight:800;color:#fff;">Wiyule &amp; F A Motors</div>
      <div style="font-size:13px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:0.06em;">' . $doc_word . '</div>
    </div>
    <div style="padding:26px 28px;">
      <p style="font-size:15px;color:#334155;margin:0 0 4px;">Hi ' . $cust . ',</p>
      <p style="font-size:14px;color:#64748b;margin:0 0 20px;">Here is your ' . strtolower($doc_word) . ' <strong style="color:#0f172a;">' . $num . '</strong> dated ' . $date . ($veh_label ? ' for <strong style="color:#0f172a;">' . $veh_label . '</strong>' : '') . '.</p>
      <table style="width:100%;border-collapse:collapse;">' . $rows_html . '</table>
      <table style="width:100%;border-collapse:collapse;margin-top:14px;border-top:2px solid #e2e8f0;">' . $totals_html . '
        <tr><td style="padding:12px 0 0;font-size:17px;font-weight:800;color:#0f172a;">' . $total_label . '</td>
            <td style="padding:12px 0 0;font-size:18px;font-weight:800;text-align:right;color:#dc2626;">' . wm_invoice_format_mwk($invoice['total']) . '</td></tr>
      </table>
      <p style="font-size:13px;color:#94a3b8;margin:22px 0 0;line-height:1.6;">' . htmlspecialchars($note, ENT_QUOTES) . '</p>
    </div>
    <div style="background:#f8fafc;padding:16px 28px;border-top:1px solid #e2e8f0;text-align:center;">
      <p style="font-size:12px;color:#64748b;margin:0;">Wiyule & F.A Motors · Blantyre, Malawi · +265 993 575 111 · <a href="' . htmlspecialchars($site, ENT_QUOTES) . '" style="color:#dc2626;text-decoration:none;">wiyulemotors.co.mw</a></p>
    </div>
  </div>
</div>';

    $text = "$doc_word $num from Wiyule & F.A Motors\n\nHi $cust,\n\nYour $total_label is " . wm_invoice_format_mwk($invoice['total']) . ".\n\n$note\n\n— Wiyule & F.A Motors, Blantyre · +265 993 575 111";

    return wm_send_email($to_email, $invoice['customer_name'], $subject, $html, $text);
}

/** Shared wrapper for a simple branded Wiyule email card. */
function _wm_email_shell(string $heading, string $badge, string $body_html): string {
    return '
<div style="font-family:-apple-system,Segoe UI,Arial,sans-serif;background:#eef1f5;padding:28px 14px;color:#0f172a;">
  <div style="max-width:540px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);">
    <div style="background:#0f172a;padding:20px 26px;display:flex;align-items:center;justify-content:space-between;">
      <div style="font-size:17px;font-weight:800;color:#fff;">Wiyule &amp; F A Motors</div>
      <div style="font-size:12px;font-weight:700;color:#fca5a5;text-transform:uppercase;letter-spacing:0.06em;">' . htmlspecialchars($badge, ENT_QUOTES) . '</div>
    </div>
    <div style="padding:26px;">
      <h1 style="font-size:19px;font-weight:800;margin:0 0 14px;color:#0f172a;">' . htmlspecialchars($heading, ENT_QUOTES) . '</h1>
      ' . $body_html . '
    </div>
    <div style="background:#f8fafc;padding:14px 26px;border-top:1px solid #e2e8f0;text-align:center;">
      <p style="font-size:12px;color:#64748b;margin:0;">Wiyule & F.A Motors · Blantyre, Malawi · +265 993 575 111</p>
    </div>
  </div>
</div>';
}

/** Payment reminder for an overdue invoice. $invoice is an invoices row. */
function wm_send_payment_reminder_email(array $invoice) {
    require_once __DIR__ . '/invoices.php';
    $to = trim($invoice['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'error'=>'no valid email'];
    $bal  = (float)$invoice['total'] - (float)$invoice['amount_paid'];
    $num  = htmlspecialchars($invoice['invoice_number'], ENT_QUOTES);
    $cust = htmlspecialchars($invoice['customer_name'], ENT_QUOTES);
    $due  = $invoice['due_date'] ? date('d M Y', strtotime($invoice['due_date'])) : '';
    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">Hi ' . $cust . ', this is a friendly reminder that invoice <strong>' . $num . '</strong> has an outstanding balance' . ($due ? ' (due ' . $due . ')' : '') . '.</p>'
          . '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:16px;text-align:center;margin:0 0 16px;">'
          . '<div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.05em;">Balance due</div>'
          . '<div style="font-size:26px;font-weight:800;color:#dc2626;margin-top:4px;">' . wm_invoice_format_mwk($bal) . '</div></div>'
          . '<p style="font-size:13px;color:#64748b;margin:0;">Please settle at your earliest convenience, or reply/call us to arrange payment. Thank you!</p>';
    $html = _wm_email_shell('Payment reminder', 'Invoice ' . $invoice['invoice_number'], $body);
    $text = "Hi " . $invoice['customer_name'] . ",\n\nReminder: invoice " . $invoice['invoice_number'] . " has an outstanding balance of " . wm_invoice_format_mwk($bal) . ($due ? " (due $due)" : "") . ".\nPlease settle at your earliest convenience.\n\n— Wiyule & F.A Motors · +265 993 575 111";
    return wm_send_email($to, $invoice['customer_name'], 'Payment reminder — invoice ' . $invoice['invoice_number'], $html, $text);
}

/** Expiry nudge for a quotation approaching its 14-day validity. */
function wm_send_quote_expiry_email(array $invoice) {
    require_once __DIR__ . '/invoices.php';
    $to = trim($invoice['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok'=>false,'error'=>'no valid email'];
    $num  = htmlspecialchars($invoice['invoice_number'], ENT_QUOTES);
    $cust = htmlspecialchars($invoice['customer_name'], ENT_QUOTES);
    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">Hi ' . $cust . ', your quotation <strong>' . $num . '</strong> for ' . wm_invoice_format_mwk($invoice['total']) . ' is valid for 14 days and will expire soon.</p>'
          . '<p style="font-size:13px;color:#64748b;margin:0;">If you\'d like to go ahead, just reply or call us and we\'ll get your vehicle booked in. Prices may change after the quote expires.</p>';
    $html = _wm_email_shell('Your quotation expires soon', 'Quotation ' . $invoice['invoice_number'], $body);
    $text = "Hi " . $invoice['customer_name'] . ",\n\nYour quotation " . $invoice['invoice_number'] . " for " . wm_invoice_format_mwk($invoice['total']) . " is valid for 14 days and will expire soon. Reply or call to proceed.\n\n— Wiyule & F.A Motors · +265 993 575 111";
    return wm_send_email($to, $invoice['customer_name'], 'Your Wiyule & F.A Motors quotation expires soon', $html, $text);
}

/** Service-due reminder to a customer. */
function wm_send_service_due_email(string $to_email, string $to_name, string $vehicle_label, string $last_text = '') {
    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'no valid email'];
    $name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);
    $veh  = htmlspecialchars($vehicle_label, ENT_QUOTES);
    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">Hi ' . $name . ', our records show your <strong>' . $veh . '</strong> may be due for a service'
          . ($last_text !== '' ? ' (' . htmlspecialchars($last_text, ENT_QUOTES) . ')' : '') . '.</p>'
          . '<p style="font-size:13px;color:#64748b;margin:0 0 4px;">Regular servicing keeps your vehicle safe, reliable, and holding its value. Reply or call us and we\'ll book you a convenient slot.</p>';
    $html = _wm_email_shell('Time for a service?', 'Service reminder', $body);
    $text = "Hi $to_name,\n\nOur records show your $vehicle_label may be due for a service" . ($last_text !== '' ? " ($last_text)" : "") . ".\nReply or call us to book a slot.\n\n— Wiyule & F.A Motors · +265 993 575 111";
    return wm_send_email($to_email, $to_name, 'Your ' . $vehicle_label . ' may be due for a service', $html, $text);
}

/**
 * Renewal reminder: COF / road tax / insurance expiring soon (or overdue).
 * $items = [ ['type'=>'COF','date'=>'YYYY-MM-DD','days'=>N], ... ].
 */
function wm_send_renewal_reminder_email(string $to_email, string $to_name, string $vehicle_label, array $items) {
    if ($to_email === '' || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'no valid email'];
    if (empty($items)) return ['ok' => false, 'error' => 'nothing due'];
    $name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);
    $veh  = htmlspecialchars($vehicle_label, ENT_QUOTES);
    $rows = ''; $textlines = [];
    foreach ($items as $it) {
        $d    = date('d M Y', strtotime($it['date']));
        $days = (int)$it['days'];
        if ($days < 0)      { $when = 'expired ' . abs($days) . ' day' . (abs($days) !== 1 ? 's' : '') . ' ago'; $color = '#dc2626'; }
        elseif ($days === 0){ $when = 'expires today'; $color = '#dc2626'; }
        elseif ($days <= 7) { $when = 'due in ' . $days . ' day' . ($days !== 1 ? 's' : ''); $color = '#d97706'; }
        else                { $when = 'due in ' . $days . ' days'; $color = '#334155'; }
        $rows .= '<tr><td style="padding:8px 0;font-weight:700;color:#0f172a;">' . htmlspecialchars($it['type']) . '</td>'
              .  '<td style="padding:8px 0;color:#64748b;text-align:right;">' . $d . ' · <span style="color:' . $color . ';font-weight:700;">' . $when . '</span></td></tr>';
        $textlines[] = $it['type'] . ': ' . $d . ' (' . $when . ')';
    }
    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">Hi ' . $name . ', a reminder about upcoming renewals for your <strong>' . $veh . '</strong>:</p>'
          . '<table style="width:100%;border-collapse:collapse;border-top:1px solid #e2e8f0;">' . $rows . '</table>'
          . '<p style="font-size:13px;color:#64748b;margin:14px 0 4px;">Keep these current to stay road-legal. Need a hand with any of them? Reply or call us.</p>';
    $html = _wm_email_shell('Vehicle renewals due', 'Renewal reminder', $body);
    $text = "Hi $to_name,\n\nRenewal reminder for your $vehicle_label:\n" . implode("\n", $textlines) . "\n\nKeep these current to stay road-legal.\n\n— Wiyule & F.A Motors · +265 993 575 111";
    return wm_send_email($to_email, $to_name, 'Renewals due for your ' . $vehicle_label, $html, $text);
}

/** Notify the owner that a new booking just came in. $b is an associative array of booking fields. */
function wm_send_new_booking_alert(array $b) {
    $owner = defined('OWNER_EMAIL') ? OWNER_EMAIL : (defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : '');
    if ($owner === '') return ['ok'=>false,'error'=>'no owner email'];
    $name = htmlspecialchars($b['full_name'] ?? '', ENT_QUOTES);
    $veh  = htmlspecialchars(trim(($b['vehicle_year'] ?? '') . ' ' . ($b['vehicle_make'] ?? '') . ' ' . ($b['vehicle_model'] ?? '')), ENT_QUOTES);
    $rows = [
        'Customer' => $name,
        'Phone'    => htmlspecialchars($b['phone'] ?? '', ENT_QUOTES),
        'Vehicle'  => $veh,
        'Service'  => htmlspecialchars($b['services'] ?? '', ENT_QUOTES),
        'Date'     => htmlspecialchars(trim(($b['booking_date'] ?? '') . ' ' . ($b['booking_time'] ?? '')), ENT_QUOTES),
        'Source'   => htmlspecialchars($b['source'] ?? '—', ENT_QUOTES),
    ];
    $tbl = '';
    foreach ($rows as $k => $v) {
        if ($v === '') continue;
        $tbl .= '<tr><td style="padding:6px 0;font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;">' . $k . '</td>'
              . '<td style="padding:6px 0;font-size:14px;color:#0f172a;text-align:right;">' . $v . '</td></tr>';
    }
    $body = '<p style="font-size:14px;color:#334155;margin:0 0 14px;">A new booking just came in via the website.</p>'
          . '<table style="width:100%;border-collapse:collapse;">' . $tbl . '</table>';
    $html = _wm_email_shell('New booking received', 'Booking', $body);
    $text = "New booking:\n" . implode("\n", array_map(fn($k,$v)=>"$k: $v", array_keys($rows), array_values($rows)));
    return wm_send_email($owner, 'Wiyule & F.A Motors', '🔔 New booking: ' . ($b['full_name'] ?? 'customer'), $html, $text);
}
