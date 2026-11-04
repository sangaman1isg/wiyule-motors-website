<?php
/**
 * email-test.php — Brevo diagnostic tool for Wiyule Motors
 * ---------------------------------------------------------
 * Upload to public_html/ (root), then visit:
 *   https://www.wiyulemotors.co.mw/email-test.php?key=SECRET&to=YOUR@EMAIL.com
 *
 * It tells you EXACTLY what's wrong (or sends a test email if everything works).
 * DELETE this file from the server when done.
 */

require_once __DIR__ . '/includes/secrets.php';
$SECRET = WM_HEALTH_KEY;   // defined in the untracked secrets.php

header('Content-Type: text/plain; charset=utf-8');

if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(404);
    exit('Not found');
}

$to = $_GET['to'] ?? '';

echo "═══════════════════════════════════════════\n";
echo " Wiyule Motors — Email Diagnostic\n";
echo "═══════════════════════════════════════════\n\n";

// CHECK 1: config.php exists and loads
echo "[1/5] Checking includes/config.php... ";
$config_path = __DIR__ . '/includes/config.php';
if (!file_exists($config_path)) {
    echo "❌ NOT FOUND\n";
    echo "    → Upload config.php to public_html/includes/\n";
    exit;
}
require_once $config_path;
echo "✓ found\n";

// CHECK 2: API key has been set
echo "[2/5] Checking BREVO_API_KEY... ";
if (!defined('BREVO_API_KEY') || BREVO_API_KEY === 'PASTE_YOUR_BREVO_API_KEY_HERE' || BREVO_API_KEY === '') {
    echo "❌ NOT SET\n";
    echo "    → Open includes/config.php\n";
    echo "    → Replace 'PASTE_YOUR_BREVO_API_KEY_HERE' with your real key\n";
    echo "    → Get it from: https://app.brevo.com → SMTP & API → API Keys\n";
    exit;
}
echo "✓ set (length: " . strlen(BREVO_API_KEY) . " chars, starts with: " . substr(BREVO_API_KEY, 0, 10) . "...)\n";
if (!str_starts_with(BREVO_API_KEY, 'xkeysib-')) {
    echo "    ⚠️  WARNING: Brevo v3 keys usually start with 'xkeysib-'. Yours doesn't.\n";
    echo "    Double-check you copied a v3 API key from Brevo.\n\n";
}

// CHECK 3: Sender email looks valid
echo "[3/5] Checking BREVO_SENDER_EMAIL... ";
echo BREVO_SENDER_EMAIL . "\n";
if (!filter_var(BREVO_SENDER_EMAIL, FILTER_VALIDATE_EMAIL)) {
    echo "    ❌ Invalid email format\n";
    exit;
}
echo "    ⚠️  IMPORTANT: This email MUST be verified in Brevo:\n";
echo "       Brevo → Senders, Domains & Dedicated IPs → Senders\n";
echo "       If it's not listed there as verified, sending will fail.\n";

// CHECK 4: Ask Brevo about this account (validates the API key)
echo "\n[4/5] Asking Brevo to verify the API key... ";
$ch = curl_init('https://api.brevo.com/v3/account');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
    CURLOPT_TIMEOUT        => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    $info = json_decode($resp, true);
    echo "✓ API key valid\n";
    echo "    Brevo account: " . ($info['email'] ?? '?') . "\n";
    if (isset($info['plan'][0]['credits'])) {
        echo "    Email credits remaining: " . $info['plan'][0]['credits'] . "\n";
    }
} elseif ($code === 401) {
    echo "❌ INVALID KEY\n";
    echo "    Brevo rejected the key. Generate a new one and update config.php.\n";
    echo "    Brevo's response: $resp\n";
    exit;
} else {
    echo "⚠️  HTTP $code\n    Response: $resp\n";
}

// CHECK 5: Try sending a test email (only if ?to= is provided)
echo "\n[5/5] Test send... ";
if (!$to) {
    echo "skipped (add ?to=YOUR@EMAIL.com to actually send a test)\n";
    exit;
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "❌ Invalid 'to' address: $to\n";
    exit;
}

echo "sending to $to ...\n";
$payload = [
    'sender'      => ['name' => BREVO_SENDER_NAME, 'email' => BREVO_SENDER_EMAIL],
    'to'          => [['email' => $to, 'name' => 'Test']],
    'subject'     => 'Wiyule Motors — email test ' . date('H:i:s'),
    'htmlContent' => '<h2>It works! 🎉</h2><p>If you got this, Brevo + your config are good.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>',
];
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
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    echo "    ✓ SENT! Check $to inbox (and SPAM folder!)\n";
    echo "    Brevo response: $resp\n\n";
    echo "If you don't see it within 2 minutes, check:\n";
    echo "  • Gmail/Outlook SPAM folder (very common on first send)\n";
    echo "  • Brevo dashboard → Statistics → Email to see if delivered/blocked\n";
} else {
    echo "    ❌ Send failed: HTTP $code\n";
    echo "    Brevo response: $resp\n\n";
    if (strpos($resp, 'sender_not_valid') !== false || strpos($resp, 'not allowed') !== false) {
        echo "    → This means the SENDER email ('" . BREVO_SENDER_EMAIL . "') is not verified at Brevo.\n";
        echo "    → Fix: Brevo → Senders → add and verify '" . BREVO_SENDER_EMAIL . "'\n";
        echo "    → Or use a different email that IS verified, and update config.php.\n";
    }
}

echo "\n═══════════════════════════════════════════\n";
echo "Done. DELETE this file when you're finished testing.\n";
