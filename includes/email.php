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
    $subject = 'Your Wiyule Motors verification code: ' . $code;
    $safe_name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);

    $html = '
<div style="font-family: -apple-system, Segoe UI, Arial, sans-serif; background:#0b0f1a; padding:32px 16px; color:#f1f5f9;">
  <div style="max-width:520px; margin:0 auto; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:32px;">
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:22px; font-weight:800; margin:0; color:#fff;">Welcome to Wiyule Motors</h1>
      <p style="font-size:14px; color:#94a3b8; margin:6px 0 0;">Verify your email to activate your account</p>
    </div>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 18px;">Hi ' . $safe_name . ',</p>
    <p style="font-size:15px; color:#cbd5e1; margin:0 0 24px;">Enter this 6-digit code on the verification page to finish creating your account:</p>
    <div style="background:#0b0f1a; border:1px solid rgba(220,38,38,0.4); border-radius:14px; padding:24px; text-align:center; margin:0 0 24px;">
      <div style="font-size:38px; font-weight:800; letter-spacing:0.3em; color:#fff; font-family: SF Mono, Menlo, monospace;">' . $code . '</div>
    </div>
    <p style="font-size:13px; color:#94a3b8; margin:0 0 12px;">This code expires in <strong style="color:#f1f5f9;">15 minutes</strong>. If you didn\'t sign up, you can ignore this email.</p>
    <hr style="border:none; border-top:1px solid rgba(255,255,255,0.08); margin:24px 0;">
    <p style="font-size:12px; color:#64748b; margin:0; text-align:center;">Wiyule Motors · Blantyre, Malawi · +265 993 575 111</p>
  </div>
</div>';

    $text = "Hi $to_name,\n\nYour Wiyule Motors verification code is: $code\n\nIt expires in 15 minutes.\n\nIf you didn't sign up, ignore this email.\n\n— Wiyule Motors";

    return wm_send_email($to_email, $to_name, $subject, $html, $text);
}

/**
 * Send a password-reset link to a user who forgot their password.
 * $reset_url should be the full https:// URL with the token already appended.
 */
function wm_send_password_reset_email($to_email, $to_name, $reset_url) {
    $subject = 'Reset your Wiyule Motors password';
    $safe_name = htmlspecialchars($to_name ?: 'there', ENT_QUOTES);
    $safe_url  = htmlspecialchars($reset_url, ENT_QUOTES);

    $html = '
<div style="font-family: -apple-system, Segoe UI, Arial, sans-serif; background:#0b0f1a; padding:32px 16px; color:#f1f5f9;">
  <div style="max-width:520px; margin:0 auto; background:#141925; border:1px solid rgba(255,255,255,0.08); border-radius:16px; padding:32px;">
    <div style="text-align:center; margin-bottom:24px;">
      <h1 style="font-size:22px; font-weight:800; margin:0; color:#fff;">Reset your password</h1>
      <p style="font-size:14px; color:#94a3b8; margin:6px 0 0;">Wiyule Motors account</p>
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
    <p style="font-size:12px; color:#64748b; margin:0; text-align:center;">Wiyule Motors · Blantyre, Malawi · +265 993 575 111</p>
  </div>
</div>';

    $text = "Hi $to_name,\n\nReset your Wiyule Motors password by visiting:\n$reset_url\n\nThis link expires in 1 hour and can only be used once.\n\nIf you didn't request this, ignore this email.\n\n— Wiyule Motors";

    return wm_send_email($to_email, $to_name, $subject, $html, $text);
}
