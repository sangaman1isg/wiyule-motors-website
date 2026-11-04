<?php
/**
 * includes/admin_alerts.php — Wiyule Motors admin notification system
 * --------------------------------------------------------------------
 * Watches for "unusual events" and pings the owner via WhatsApp (+ logs them
 * for display in the admin Alerts panel). Free, no extra service needed —
 * the owner gets a clickable WhatsApp link the first time they open admin
 * after a threshold is crossed, so they can react fast.
 *
 * How it's used:
 *   require_once __DIR__ . '/admin_alerts.php';
 *   $alerts = wm_check_alerts($conn);      // returns array of new alerts
 *
 * State is stored in a small JSON file in /tmp so we don't re-fire the same
 * alert on every admin page load — each alert type has a cooldown.
 */

require_once __DIR__ . '/config.php';   // for OWNER_PHONE if defined

// Owner WhatsApp number (international format, no +, no spaces)
if (!defined('OWNER_PHONE')) define('OWNER_PHONE', '265993575111');

/** Path to the alert state file (tracks when each alert last fired). */
function _wm_alert_state_file() {
    return sys_get_temp_dir() . '/wm_alert_state.json';
}

function _wm_alert_state_load() {
    $f = _wm_alert_state_file();
    if (!file_exists($f)) return [];
    $j = @json_decode(@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function _wm_alert_state_save($state) {
    @file_put_contents(_wm_alert_state_file(), json_encode($state), LOCK_EX);
}

/**
 * Detect unusual events and return alerts that crossed their threshold
 * since the last admin visit (cooldown-aware so we don't spam).
 *
 * Returns array of: ['key'=>..., 'level'=>'warn|info', 'title'=>..., 'detail'=>..., 'wa_link'=>...]
 */
function wm_check_alerts(mysqli $conn) {
    $state = _wm_alert_state_load();
    $now   = time();
    $fired = [];

    // Helper: should this alert fire? (one per 6 hours per type)
    $cooldown = 6 * 3600;
    $should_fire = function($key) use ($state, $now, $cooldown) {
        return empty($state[$key]) || ($now - (int)$state[$key]) > $cooldown;
    };

    // 1. Failed-login spike (≥20 in 24h)
    $throttle_file = sys_get_temp_dir() . '/wm_login_attempts.log';
    $failed_24h = 0; $unique_ips = [];
    if (file_exists($throttle_file)) {
        $day_cutoff = $now - 86400;
        foreach (file($throttle_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $p = explode("\t", $line);
            if (count($p) !== 3) continue;
            if ((int)$p[1] < $day_cutoff) continue;
            $failed_24h++;
            $unique_ips[$p[2]] = true;
        }
    }
    if ($failed_24h >= 20 && $should_fire('failed_login_spike')) {
        $msg = "🚨 Wiyule Motors security alert: $failed_24h failed logins from ".count($unique_ips)." IP(s) in the last 24h. Check the admin dashboard.";
        $fired[] = [
            'key'     => 'failed_login_spike',
            'level'   => 'warn',
            'title'   => 'Failed login spike',
            'detail'  => "$failed_24h failed login attempts in 24h from ".count($unique_ips)." IP(s)",
            'wa_link' => 'https://wa.me/'.OWNER_PHONE.'?text='.urlencode($msg),
        ];
        $state['failed_login_spike'] = $now;
    }

    // 2. Signup spike (≥10 in last 24h — could be bots)
    $r = $conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $signups_24h = $r ? (int)$r->fetch_assoc()['c'] : 0;
    if ($signups_24h >= 10 && $should_fire('signup_spike')) {
        $msg = "📈 Wiyule Motors: $signups_24h new signups in the last 24 hours. Quick check they're real customers, not bots.";
        $fired[] = [
            'key'     => 'signup_spike',
            'level'   => 'info',
            'title'   => 'Signup spike',
            'detail'  => "$signups_24h new accounts in last 24h — review for legitimacy",
            'wa_link' => 'https://wa.me/'.OWNER_PHONE.'?text='.urlencode($msg),
        ];
        $state['signup_spike'] = $now;
    }

    // 3. Low verification rate (≥15 unverified, gap suggests email delivery problem)
    $r = @$conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND email_verified=0");
    $pending = $r ? (int)$r->fetch_assoc()['c'] : 0;
    if ($pending >= 15 && $should_fire('email_delivery_issue')) {
        $msg = "✉️ Wiyule Motors: $pending accounts haven't verified their email. Check Brevo dashboard — emails may be landing in spam.";
        $fired[] = [
            'key'     => 'email_delivery_issue',
            'level'   => 'warn',
            'title'   => 'Email delivery concerns',
            'detail'  => "$pending unverified accounts — possible Brevo delivery issue",
            'wa_link' => 'https://wa.me/'.OWNER_PHONE.'?text='.urlencode($msg),
        ];
        $state['email_delivery_issue'] = $now;
    }

    // 4. Overdue bookings (still pending after 24h — you missed confirming them)
    $r = $conn->query("SELECT COUNT(*) c FROM bookings WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stale = $r ? (int)$r->fetch_assoc()['c'] : 0;
    if ($stale >= 3 && $should_fire('stale_bookings')) {
        $msg = "📅 Wiyule Motors: $stale bookings have been pending for over 24 hours. Confirm or follow up so customers aren't left waiting.";
        $fired[] = [
            'key'     => 'stale_bookings',
            'level'   => 'warn',
            'title'   => 'Bookings waiting too long',
            'detail'  => "$stale bookings still pending after 24+ hours — confirm them ASAP",
            'wa_link' => 'https://wa.me/'.OWNER_PHONE.'?text='.urlencode($msg),
        ];
        $state['stale_bookings'] = $now;
    }

    if (!empty($fired)) _wm_alert_state_save($state);
    return $fired;
}
