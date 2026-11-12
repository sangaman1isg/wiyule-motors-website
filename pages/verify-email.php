<?php
// pages/verify-email.php — user enters the 6-digit code to activate their account
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/email.php';
wm_secure_session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
require_once __DIR__ . '/../includes/db_connection.php';

// If user is already fully logged in, send them to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . $base . '/pages/dashboard.php');
    exit;
}

// Must have a pending verification in session (set during signup)
if (empty($_SESSION['pending_verify_id']) || empty($_SESSION['pending_verify_email'])) {
    header('Location: ' . $base . '/pages/signup.php');
    exit;
}

$user_id = (int)$_SESSION['pending_verify_id'];
$email   = $_SESSION['pending_verify_email'];

$error   = '';
$info    = '';

$is_verify_submit = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code']));
$is_resend        = (isset($_GET['resend']) && !$is_verify_submit);

// ── Handle code submission FIRST (so it can't be blocked by ?resend in URL) ──
if ($is_verify_submit) {
    $entered = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (strlen($entered) !== 6) {
        $error = 'Please enter the 6-digit code from your email.';
    } else {
        $stmt = $conn->prepare("SELECT verification_code, verification_expires_at, email_verified, full_name, phone FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'Account not found. Please sign up again.';
        } elseif ((int)$row['email_verified'] === 1) {
            // Already verified — just complete the login
            $verified_now = true;
        } elseif (!$row['verification_code'] || !$row['verification_expires_at']) {
            $error = 'No active code. Please request a new one.';
        } elseif (strtotime($row['verification_expires_at']) < time()) {
            $error = 'That code has expired. Please request a new one.';
        } elseif (!password_verify($entered, $row['verification_code'])) {
            $error = 'Incorrect code. Please double-check and try again.';
        } else {
            $verified_now = true;
        }

        if (!empty($verified_now)) {
            // Mark account verified and clear the code
            $up = $conn->prepare("UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires_at = NULL WHERE id = ?");
            $up->bind_param('i', $user_id);
            $up->execute();
            $up->close();

            // Save vehicle if signup carried one through
            if (!empty($_SESSION['pending_v_make'])) {
                $vstmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, registration, mileage) VALUES (?, ?, ?, ?, ?, ?)");
                $make = $_SESSION['pending_v_make'];
                $model = $_SESSION['pending_v_model'] ?? '';
                $year = $_SESSION['pending_v_year'] ?? '';
                $reg = $_SESSION['pending_v_reg'] ?? '';
                $mileage = (int)($_SESSION['pending_v_mileage'] ?? 0);
                $vstmt->bind_param('issssi', $user_id, $make, $model, $year, $reg, $mileage);
                $vstmt->execute();
                $vstmt->close();
            }

            // ── Promote pending session to a real logged-in session ──
            session_regenerate_id(true);
            $first = $_SESSION['pending_first_name'] ?? '';
            $last  = $_SESSION['pending_last_name'] ?? '';

            $_SESSION['user_id']     = $user_id;
            $_SESSION['user_name']   = trim($first . ' ' . $last) ?: ($row['full_name'] ?? '');
            $_SESSION['first_name']  = $first;
            $_SESSION['last_name']   = $last;
            $_SESSION['email']       = $email;
            $_SESSION['user_email']  = $email;
            $_SESSION['phone']       = $_SESSION['pending_phone'] ?? ($row['phone'] ?? '');
            $_SESSION['city']        = $_SESSION['pending_city'] ?? '';
            $_SESSION['user_type']   = $_SESSION['pending_type'] ?? 'individual';
            $_SESSION['user_created']         = time();
            $_SESSION['onboarding_completed'] = false;

            // Clear pending values
            foreach (['pending_verify_id','pending_verify_email','pending_first_name',
                      'pending_last_name','pending_phone','pending_city','pending_type',
                      'pending_v_make','pending_v_model','pending_v_year','pending_v_reg','pending_v_mileage'] as $k) {
                unset($_SESSION[$k]);
            }

            header('Location: ' . $base . '/pages/dashboard.php');
            exit;
        }
    }
}

// ── Handle "Resend code" (only when NOT also submitting the code) ──────
if ($is_resend) {
    // Throttle resends: at most once every 60 seconds
    $stmt = $conn->prepare("SELECT last_verification_sent FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $last_sent = $row && $row['last_verification_sent'] ? strtotime($row['last_verification_sent']) : 0;
    if ($last_sent && (time() - $last_sent) < 60) {
        $wait = 60 - (time() - $last_sent);
        $error = "Please wait $wait seconds before requesting another code.";
    } else {
        $new_code   = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $code_hash  = password_hash($new_code, PASSWORD_BCRYPT);
        $expires_at = date('Y-m-d H:i:s', time() + 15 * 60);
        $now        = date('Y-m-d H:i:s');

        $up = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires_at = ?, last_verification_sent = ? WHERE id = ?");
        $up->bind_param('sssi', $code_hash, $expires_at, $now, $user_id);
        $up->execute();
        $up->close();

        $send = wm_send_verification_email($email, $_SESSION['pending_first_name'] ?? '', $new_code);
        if ($send['ok']) {
            $info = "A new code has been sent to $email.";
        } else {
            $error = "We couldn't send the email right now. Please try again in a minute.";
        }
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
<title>Verify your email — Wiyule Motors</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', sans-serif; background: #0b0f1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; color: #f1f5f9; }
    .card { background: #141925; border: 1px solid rgba(255,255,255,0.08); border-radius: 22px; padding: 40px 32px; max-width: 460px; width: 100%; box-shadow: 0 32px 80px rgba(0,0,0,0.5); }
    .badge { width: 56px; height: 56px; background: rgba(220,38,38,0.16); color: #f87171; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 6px; }
    .sub { font-size: 14px; color: #94a3b8; text-align: center; margin-bottom: 24px; line-height: 1.5; }
    .sub b { color: #f1f5f9; }
    .code-input { width: 100%; padding: 16px; background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 14px; color: #fff; font-size: 24px; font-weight: 700; letter-spacing: 0.4em; text-align: center; font-family: 'SF Mono', Menlo, monospace; outline: none; transition: border-color 0.2s; }
    .code-input:focus { border-color: #dc2626; }
    .btn { width: 100%; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; border: none; padding: 15px; border-radius: 13px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; margin-top: 14px; transition: transform 0.2s; }
    .btn:hover { transform: translateY(-1px); }
    .msg { padding: 12px 14px; border-radius: 11px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
    .msg.err { background: rgba(220,38,38,0.12); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; }
    .msg.ok  { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
    .resend { text-align: center; margin-top: 18px; font-size: 13px; color: #94a3b8; }
    .resend a { color: #f87171; font-weight: 600; text-decoration: none; }
    .resend a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <div class="badge">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2zm-2 0l-8 5-8-5zM4 18V8l8 5 8-5v10z"/></svg>
    </div>
    <h1>Check your email</h1>
    <p class="sub">We've sent a 6-digit code to<br><b><?= htmlspecialchars($email) ?></b><br>It expires in 15 minutes.</p>

    <?php if (!empty($_SESSION['verify_email_warning'])): ?>
        <div class="msg err" style="text-align:left">
            <strong>⚠ Heads up:</strong> the email couldn't be sent right now.<br>
            <span style="font-size:11px;color:#fca5a5;opacity:0.85">Reason: <?= htmlspecialchars($_SESSION['verify_email_warning']) ?></span><br><br>
            Please try <a href="?resend=1" style="color:#fca5a5;text-decoration:underline">resend</a>, or contact us on WhatsApp <a href="https://wa.me/265993575111" style="color:#fca5a5;text-decoration:underline">+265 993 575 111</a> to verify manually.
        </div>
        <?php unset($_SESSION['verify_email_warning']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($info): ?>
        <div class="msg ok"><?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/pages/verify-email.php">
        <input type="text" name="code" class="code-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus required>
        <button type="submit" class="btn">Verify &amp; Continue</button>
    </form>

    <div class="resend">
        Didn't get it? <a href="?resend=1">Resend code</a>
        &nbsp;·&nbsp;
        <a href="<?= $base ?>/pages/signup.php">Use a different email</a>
    </div>
</div>
</body>
</html>
