<?php
/**
 * includes/secrets.example.php — TEMPLATE (safe to commit).
 * ----------------------------------------------------------------
 * Copy this file to secrets.php and fill in the real values.
 * secrets.php is gitignored and must be uploaded to the server manually.
 */

// ── Database ──
define('WM_DB_HOST', 'localhost');
define('WM_DB_USER', 'your_db_user');
define('WM_DB_PASS', 'your_db_password');
define('WM_DB_NAME', 'your_db_name');

// ── Brevo (transactional email) ── https://app.brevo.com → SMTP & API → API Keys
define('BREVO_API_KEY', 'xkeysib-PASTE_YOUR_KEY_HERE');

// ── Private health-check endpoint key (health.php?key=...) ──
define('WM_HEALTH_KEY', 'change-me-to-a-long-random-string');

// ── Admin bootstrap/reset password (admin/setup.php) ──
define('WM_ADMIN_SETUP_PASSWORD', 'change-me');

// ── Dev/diagnostic tools kill-switch ──
// admin/setup.php and email-test.php refuse to run unless this is true.
// Keep it false in production; flip to true only while you need them.
define('WM_ALLOW_SETUP_TOOLS', false);

// ── Cron key for cron/run-reminders.php (scheduled reminders) ──
define('WM_CRON_KEY', 'change-me-to-a-long-random-string');

// ── Backup key for cron/db-backup.php (full DB dump; keep separate from the cron key) ──
define('WM_BACKUP_KEY', 'change-me-to-a-long-random-string');
