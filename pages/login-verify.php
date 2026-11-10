<?php
// pages/login-verify.php — second factor: enter the 6-digit code emailed at sign-in.
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/auth.php';

// Already fully logged in?
if (isset($_SESSION['user_id'])) { header('Location: ' . $base . '/pages/dashboard.php'); exit; }
// No pending login → back to the sign-in page.
if (empty($_SESSION['pending_2fa_id'])) { header('Location: ' . $base . '/pages/login.php'); exit; }

$uid = (int)$_SESSION['pending_2fa_id'];
$stmt = $conn->prepare("SELECT id, full_name, email, phone, created_at, verification_code, verification_expires_at, last_verification_sent FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    unset($_SESSION['pending_2fa_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_remember']);
    header('Location: ' . $base . '/pages/login.php');
    exit;
}

$error = ''; $notice = '';

// ── Resend (GET ?resend=1), throttled to once per 60s ──
if (($_GET['resend'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $last = $user['last_verification_sent'] ? strtotime($user['last_verification_sent']) : 0;
    if (time() - $last < 60) {
        $notice = 'Please wait a few seconds before requesting another code.';
    } else {
        wm_issue_login_code($conn, $uid, $user['email'], explode(' ', $user['full_name'], 2)[0]);
        $notice = 'A new code is on its way to your email.';
    }
}

// ── Verify the entered code ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempts = (int)($_SESSION['twofa_attempts'] ?? 0);
    if ($attempts >= 5) {
        unset($_SESSION['pending_2fa_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_remember'], $_SESSION['twofa_attempts']);
        header('Location: ' . $base . '/pages/login.php?error=too_many_attempts');
        exit;
    }
    $entered = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (strlen($entered) !== 6) {
        $error = 'Enter the 6-digit code from your email.';
    } elseif (!$user['verification_code'] || !$user['verification_expires_at']) {
        $error = 'No active code — please resend.';
    } elseif (strtotime($user['verification_expires_at']) < time()) {
        $error = 'That code has expired — please resend.';
    } elseif (!password_verify($entered, $user['verification_code'])) {
        $_SESSION['twofa_attempts'] = $attempts + 1;
        $error = 'Incorrect code. Please try again.';
    } else {
        // ✓ Correct — clear the code, record success, establish the session.
        $clr = $conn->prepare("UPDATE users SET verification_code = NULL, verification_expires_at = NULL WHERE id = ?");
        $clr->bind_param('i', $uid);
        $clr->execute();
        $clr->close();

        if (function_exists('wm_login_record_success')) wm_login_record_success($user['email']);
        wm_establish_user_session($conn, $user, !empty($_SESSION['pending_2fa_remember']));
        unset($_SESSION['pending_2fa_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_remember'], $_SESSION['twofa_attempts']);

        header('Location: ' . $base . '/pages/dashboard.php');
        exit;
    }
}

// Mask the email for display (e.g. da•••@gmail.com)
$em = (string)$user['email'];
$at = strpos($em, '@');
$masked = $at > 0
    ? substr($em, 0, min(2, $at)) . '•••' . substr($em, $at)
    : $em;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Verify sign-in — Wiyule Motors</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', -apple-system, system-ui, sans-serif; background: #0b0f1a; color: #f1f5f9;
           min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { width: 100%; max-width: 420px; background: #141925; border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px; padding: 32px 28px; }
    h1 { font-size: 22px; font-weight: 800; text-align: center; }
    .sub { font-size: 13.5px; color: #94a3b8; text-align: center; margin: 8px 0 22px; line-height: 1.5; }
    .sub strong { color: #e2e8f0; }
    label { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
    input[name="code"] { width: 100%; margin-top: 8px; padding: 14px; background: #0b0f1a;
            border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px; color: #fff;
            font-size: 26px; font-weight: 700; text-align: center; letter-spacing: 0.4em; font-family: 'SF Mono', Menlo, monospace; }
    input[name="code"]:focus { outline: none; border-color: #dc2626; }
    button { width: 100%; margin-top: 16px; padding: 14px; background: linear-gradient(135deg, #dc2626, #b91c1c);
             color: #fff; border: none; border-radius: 12px; font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer; }
    button:hover { filter: brightness(1.08); }
    .msg { padding: 10px 12px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; text-align: center; }
    .msg.err { background: rgba(220,38,38,0.12); color: #fca5a5; border: 1px solid rgba(220,38,38,0.3); }
    .msg.ok  { background: rgba(22,163,74,0.12); color: #86efac; border: 1px solid rgba(22,163,74,0.3); }
    .foot { text-align: center; font-size: 13px; color: #94a3b8; margin-top: 18px; }
    .foot a { color: #f87171; font-weight: 600; text-decoration: none; }
    .foot a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <h1>Verify it's you</h1>
    <div class="sub">We emailed a 6-digit sign-in code to <strong><?= htmlspecialchars($masked) ?></strong>. Enter it below to finish signing in.</div>

    <?php if ($error): ?><div class="msg err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="msg ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="code">Sign-in code</label>
        <input type="text" id="code" name="code" inputmode="numeric" maxlength="6" pattern="[0-9]*"
               placeholder="______" autofocus required>
        <button type="submit">Verify &amp; sign in</button>
    </form>

    <div class="foot">
        Didn't get it? <a href="?resend=1">Resend code</a><br>
        <span style="font-size:12px;color:#64748b">Code expires in 10 minutes · <a href="<?= $base ?>/pages/login.php" style="color:#64748b">Back to sign in</a></span>
    </div>
</div>
</body>
</html>
