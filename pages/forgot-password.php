<?php
// pages/forgot-password.php — request a password reset link
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/config.php';
wm_secure_session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
require_once __DIR__ . '/../includes/db_connection.php';

// Already logged in? Send to dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base . '/pages/dashboard.php');
    exit;
}

$error = '';
$info  = '';
$submitted_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $submitted_email = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up the account — but DON'T reveal whether the email exists
        // (so attackers can't enumerate which emails are registered).
        $stmt = $conn->prepare("SELECT id, full_name, reset_requests_count, reset_requests_window_start FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $now = time();
        $can_send = true;

        if ($user) {
            // ── Rate-limit: max 3 reset requests per email per hour ──
            $count = (int)($user['reset_requests_count'] ?? 0);
            $win_start_ts = $user['reset_requests_window_start']
                ? strtotime($user['reset_requests_window_start'])
                : 0;
            $hour_ago = $now - 3600;

            if ($win_start_ts && $win_start_ts > $hour_ago && $count >= 3) {
                // Already hit the limit this hour — silently skip sending.
                $can_send = false;
            } else {
                // If window expired, reset the counter
                if (!$win_start_ts || $win_start_ts <= $hour_ago) {
                    $count = 0;
                    $win_start = date('Y-m-d H:i:s', $now);
                } else {
                    $win_start = $user['reset_requests_window_start'];
                }

                $token       = bin2hex(random_bytes(32));   // 64 char URL-safe token
                $token_hash  = hash('sha256', $token);      // store the hash, not the token
                $expires_at  = date('Y-m-d H:i:s', $now + 3600); // 1 hour
                $new_count   = $count + 1;

                $up = $conn->prepare(
                    "UPDATE users
                     SET reset_token = ?, reset_token_expires_at = ?,
                         reset_requests_count = ?, reset_requests_window_start = ?
                     WHERE id = ?"
                );
                $up->bind_param('ssisi', $token_hash, $expires_at, $new_count, $win_start, $user['id']);
                $up->execute();
                $up->close();

                // Build the reset URL (use the canonical site URL from config)
                $site = defined('SITE_URL') ? rtrim(SITE_URL, '/') : 'https://www.wiyulemotors.co.mw';
                $reset_url = $site . '/pages/reset-password.php?token=' . $token;

                $first_name = explode(' ', $user['full_name'] ?? '')[0] ?? '';
                wm_send_password_reset_email($email, $first_name, $reset_url);
            }
        }

        // ALWAYS show the same message whether the email exists or not.
        // This is the standard "don't leak account info" pattern.
        $info = "If an account exists for $email, we've sent a password reset link. Please check your inbox (and spam folder). The link expires in 1 hour.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php @include __DIR__ . '/../includes/analytics.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Forgot Password — Wiyule Motors</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', sans-serif; background: #0b0f1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; color: #f1f5f9; }
    .card { background: #141925; border: 1px solid rgba(255,255,255,0.08); border-radius: 22px; padding: 40px 32px; max-width: 460px; width: 100%; box-shadow: 0 32px 80px rgba(0,0,0,0.5); }
    .badge { width: 56px; height: 56px; background: rgba(220,38,38,0.16); color: #f87171; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 6px; }
    .sub { font-size: 14px; color: #94a3b8; text-align: center; margin-bottom: 24px; line-height: 1.5; }
    label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; }
    .input { width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px; color: #fff; font-size: 15px; font-family: 'Sora', sans-serif; outline: none; transition: border-color 0.2s; }
    .input:focus { border-color: #dc2626; }
    .btn { width: 100%; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; border: none; padding: 15px; border-radius: 13px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; margin-top: 18px; transition: transform 0.2s; }
    .btn:hover { transform: translateY(-1px); }
    .msg { padding: 12px 14px; border-radius: 11px; font-size: 13px; font-weight: 500; margin-bottom: 16px; line-height: 1.5; }
    .msg.err { background: rgba(220,38,38,0.12); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; }
    .msg.ok  { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
    .links { text-align: center; margin-top: 18px; font-size: 13px; color: #94a3b8; }
    .links a { color: #f87171; font-weight: 600; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <div class="badge">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 0-5 5v4H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-2V6a5 5 0 0 0-5-5zm-3 9V6a3 3 0 0 1 6 0v4z"/></svg>
    </div>
    <h1>Forgot your password?</h1>
    <p class="sub">No problem. Enter your email and we'll send you a link to reset it.</p>

    <?php if ($error): ?>
        <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="msg ok"><?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <?php if (!$info): ?>
    <form method="POST" action="<?= $base ?>/pages/forgot-password.php">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" class="input" placeholder="you@example.com" value="<?= htmlspecialchars($submitted_email) ?>" required autofocus>
        <button type="submit" class="btn">Send reset link</button>
    </form>
    <?php endif; ?>

    <div class="links">
        Remembered it? <a href="<?= $base ?>/pages/login.php">Sign in</a>
        &nbsp;·&nbsp;
        <a href="<?= $base ?>/">Back to homepage</a>
    </div>
</div>
</body>
</html>
