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

// The email Brevo will send FROM. The domain wiyulemotors.co.mw is authenticated
// in Brevo (DKIM/SPF/DMARC), so any address on it is a valid sender.
define('BREVO_SENDER_EMAIL', 'twandakayuni@wiyulemotors.co.mw');
define('BREVO_SENDER_NAME',  'Wiyule & F.A Motors');

// Public site URL (used in email links)
define('SITE_URL', 'https://www.wiyulemotors.co.mw');

// Where new-booking alert emails are sent (owner inbox). Defaults to the sender.
define('OWNER_EMAIL', 'wiyulemotors@gmail.com');
