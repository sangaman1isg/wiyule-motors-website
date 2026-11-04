<?php
/**
 * cron/run-reminders.php — scheduled customer reminders (payment + quote expiry).
 *
 * Trigger from a DirectAdmin cron job once a day, e.g.:
 *   curl -s "https://www.wiyulemotors.co.mw/cron/run-reminders.php?key=YOUR_KEY"
 *
 * Add &dryrun=1 to see what WOULD be sent without sending anything.
 * Gated by WM_CRON_KEY (falls back to WM_HEALTH_KEY) from secrets.php.
 */
require __DIR__ . '/../includes/secrets.php';

$valid = defined('WM_CRON_KEY') ? WM_CRON_KEY : (defined('WM_HEALTH_KEY') ? WM_HEALTH_KEY : '');
if ($valid === '' || ($_GET['key'] ?? '') !== $valid) {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/../includes/reminders.php';   // provides $conn + reminder functions

header('Content-Type: application/json; charset=utf-8');
$dry = isset($_GET['dryrun']) && $_GET['dryrun'] == '1';
echo json_encode(wm_run_reminders($conn, $dry), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
