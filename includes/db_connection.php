<?php
// Set timezone for all pages that include this file
date_default_timezone_set('Africa/Blantyre');

// Suppress raw mysqli warnings so we can handle errors cleanly
mysqli_report(MYSQLI_REPORT_OFF);

// ── Database Connection ──────────────────────────────────────────
// Credentials are loaded from the untracked secrets.php (see secrets.example.php)
require_once __DIR__ . '/secrets.php';

// Connection parameters (production values from secrets.php).
$wm_db = ['host' => WM_DB_HOST, 'user' => WM_DB_USER, 'pass' => WM_DB_PASS, 'name' => WM_DB_NAME, 'port' => null];

// Local-development override: ONLY when served by `php -S` (the built-in
// dev server reports SAPI 'cli-server') AND a gitignored db.local.php exists.
// This never runs in production (different SAPI, file absent, not deployed).
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . '/db.local.php')) {
    $wm_db = array_merge($wm_db, require __DIR__ . '/db.local.php');
}

$conn = @new mysqli($wm_db['host'], $wm_db['user'], $wm_db['pass'], $wm_db['name'], $wm_db['port']);

if ($conn->connect_error) {
    // Log the real error for debugging — never shown to users
    error_log("DB Connection failed: " . $conn->connect_error);

    // Show a branded Wiyule Motors maintenance page
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Unavailable — Wiyule Motors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Sora', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #2d1010 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 560px; width: 100%; text-align: center; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.3);
            color: #fca5a5;
            padding: 8px 16px; border-radius: 99px;
            font-size: 13px; font-weight: 600;
            margin-bottom: 28px; letter-spacing: 0.02em;
        }
        .badge-dot {
            width: 8px; height: 8px; background: #ef4444;
            border-radius: 50%; animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        h1 {
            font-size: 38px; font-weight: 900; margin-bottom: 16px;
            letter-spacing: -0.02em; line-height: 1.15;
        }
        h1 span {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        p.lead { font-size: 17px; color: #cbd5e1; margin-bottom: 32px; font-weight: 400; }
        .apology {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px; padding: 24px; margin-bottom: 28px; text-align: left;
        }
        .apology-title {
            font-size: 14px; font-weight: 700; color: #fca5a5;
            text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px;
        }
        .apology-text { color: #e2e8f0; font-size: 15px; line-height: 1.7; }
        .actions {
            display: flex; gap: 12px; justify-content: center;
            flex-wrap: wrap; margin-bottom: 24px;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 24px; border-radius: 12px;
            font-size: 14px; font-weight: 700;
            text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;
            font-family: 'Sora', sans-serif; cursor: pointer; border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff;
            box-shadow: 0 10px 30px -10px rgba(220, 38, 38, 0.5);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px -10px rgba(220, 38, 38, 0.6);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.06); color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.1); }
        .footer {
            font-size: 13px; color: #64748b;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 20px; margin-top: 20px;
        }
        .footer strong { color: #cbd5e1; }
        @media (max-width: 480px) {
            h1 { font-size: 30px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">
            <span class="badge-dot"></span>
            Temporary Service Interruption
        </div>

        <h1>We'll be back<br><span>shortly.</span></h1>

        <p class="lead">Our website is taking a quick break while we make things even better for you.</p>

        <div class="apology">
            <div class="apology-title">A note from Wiyule Motors</div>
            <p class="apology-text">
                We're sincerely sorry for the inconvenience. Our team is working to restore full service as quickly as possible. In the meantime, you can still reach us directly to book a service, ask questions, or get a quote — we're always happy to help.
            </p>
        </div>

        <div class="actions">
            <a href="https://wa.me/265993575111?text=Hi%20Wiyule%20Motors%2C%20I%20tried%20visiting%20your%20website%20but%20it%20seems%20unavailable." class="btn btn-primary" target="_blank" rel="noopener">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                Chat on WhatsApp
            </a>
            <a href="tel:+265993575111" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Call +265 993 575 111
            </a>
        </div>

        <div class="footer">
            <strong>Wiyule Motors</strong> · Blantyre, Malawi<br>
            Open Mon–Sat · 8:00 AM – 5:00 PM
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

$conn->set_charset("utf8mb4");
?>
