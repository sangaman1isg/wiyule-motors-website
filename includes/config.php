<?php
/**
 * includes/config.php — Wiyule Motors private configuration
 * ----------------------------------------------------------
 * Sensitive settings live here. Keep this file out of public_html
 * if possible, OR keep its permissions to 644 (readable by web server only).
 */

// ── Brevo (email service) ──
// BREVO_API_KEY is defined in the untracked secrets.php (see secrets.example.php).
require_once __DIR__ . '/secrets.php';

// The email Brevo will send FROM. Must be verified in Brevo first:
//   Senders, Domains & Dedicated IPs → Senders → Add a sender
// Then come back here and put the verified email below.
define('BREVO_SENDER_EMAIL', 'wiyulemotors@gmail.com');
define('BREVO_SENDER_NAME',  'Wiyule Motors');

// Public site URL (used in email links)
define('SITE_URL', 'https://www.wiyulemotors.co.mw');

// Where new-booking alert emails are sent (owner inbox). Defaults to the sender.
define('OWNER_EMAIL', 'wiyulemotors@gmail.com');
