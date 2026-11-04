<?php
/**
 * includes/security.php — Shared security helpers for Wiyule Motors
 * ------------------------------------------------------------------
 * Include this BEFORE session_start() on any login page:
 *     require_once __DIR__ . '/../includes/security.php';
 *     wm_secure_session_start();
 *
 * Provides:
 *  1. wm_secure_session_start()  — starts a session with secure cookie flags
 *  2. wm_login_throttle($email)  — checks if a login attempt is allowed,
 *                                  records failures, locks out after 5 fails
 *                                  for 15 minutes per email + IP
 */

/**
 * Start a session with hardened cookie flags so the session ID can't be
 * stolen via JavaScript (HttpOnly), can't leak over http (Secure on HTTPS),
 * and can't be sent on cross-site requests (SameSite=Lax = CSRF defence).
 */
function wm_secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Detect HTTPS (works behind proxies too)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
             || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    // Apply secure cookie params (PHP 7.3+)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,   // only sent over HTTPS in production
        'httponly' => true,        // JS can't read the cookie
        'samesite' => 'Lax',       // basic CSRF defence
    ]);

    // Strong session ID, no URL-based session IDs
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode',  '1');

    session_start();

    // Rotate the ID once per session to prevent fixation
    if (empty($_SESSION['_wm_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_wm_initiated'] = 1;
    }
}

/**
 * Login throttle — call before checking a password.
 * Returns ['ok' => true]                       → proceed with login check
 *         ['ok' => false, 'wait' => 423]       → locked out, seconds remaining
 *
 * After 5 failed attempts (counted per email + per IP, whichever is hit first)
 * within a 15-minute window, further attempts are blocked for 15 minutes.
 *
 * Call wm_login_record_failure() on a wrong password,
 * call wm_login_record_success() on a correct password (clears the counter).
 *
 * Storage: a single CSV file in /tmp (or system temp). No database changes needed.
 */
function _wm_attempts_file() {
    $dir = sys_get_temp_dir();
    return $dir . '/wm_login_attempts.log';
}

function _wm_attempts_load() {
    $f = _wm_attempts_file();
    if (!file_exists($f)) return [];
    $rows = [];
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $p = explode("\t", $line);
        if (count($p) === 3) $rows[] = ['key' => $p[0], 'ts' => (int)$p[1], 'ip' => $p[2]];
    }
    return $rows;
}

function _wm_attempts_save($rows) {
    $f = _wm_attempts_file();
    $cutoff = time() - 3600; // keep last hour only
    $out = '';
    foreach ($rows as $r) {
        if ($r['ts'] < $cutoff) continue;
        $out .= $r['key'] . "\t" . $r['ts'] . "\t" . $r['ip'] . "\n";
    }
    @file_put_contents($f, $out, LOCK_EX);
}

function _wm_client_ip() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function wm_login_throttle($email) {
    $email   = strtolower(trim($email));
    $ip      = _wm_client_ip();
    $window  = 15 * 60;      // 15 minutes
    $max     = 5;            // 5 attempts allowed in the window
    $now     = time();
    $cutoff  = $now - $window;

    $rows = _wm_attempts_load();
    $email_count = 0; $ip_count = 0; $latest_ts = 0;
    foreach ($rows as $r) {
        if ($r['ts'] < $cutoff) continue;
        if ($r['key'] === $email) { $email_count++; if ($r['ts'] > $latest_ts) $latest_ts = $r['ts']; }
        if ($r['ip']  === $ip)    { $ip_count++;    if ($r['ts'] > $latest_ts) $latest_ts = $r['ts']; }
    }
    if ($email_count >= $max || $ip_count >= $max) {
        $wait = ($latest_ts + $window) - $now;
        return ['ok' => false, 'wait' => max(60, $wait), 'reason' => 'Too many attempts. Please try again in '.ceil(max(60,$wait)/60).' minutes.'];
    }
    return ['ok' => true];
}

function wm_login_record_failure($email) {
    $email = strtolower(trim($email));
    $rows = _wm_attempts_load();
    $rows[] = ['key' => $email, 'ts' => time(), 'ip' => _wm_client_ip()];
    _wm_attempts_save($rows);
}

function wm_login_record_success($email) {
    // Clear failures for this email (success resets the counter)
    $email = strtolower(trim($email));
    $rows = _wm_attempts_load();
    $rows = array_filter($rows, fn($r) => $r['key'] !== $email);
    _wm_attempts_save($rows);
}
