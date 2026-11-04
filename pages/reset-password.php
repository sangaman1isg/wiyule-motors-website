<?php
// pages/reset-password.php — set new password via emailed token
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
require_once __DIR__ . '/../includes/db_connection.php';

$token       = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error       = '';
$success     = false;
$token_valid = false;
$user_id     = null;
$user_email  = '';

// ── Validate the token ──
if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        "SELECT id, email, reset_token_expires_at
         FROM users
         WHERE reset_token = ?"
    );
    $stmt->bind_param('s', $token_hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $expires_ts = strtotime($row['reset_token_expires_at']);
        if ($expires_ts && $expires_ts > time()) {
            $token_valid = true;
            $user_id     = (int)$row['id'];
            $user_email  = $row['email'];
        } else {
            $error = 'This reset link has expired. Please request a new one.';
        }
    } else {
        $error = 'This reset link is invalid or has already been used.';
    }
} else {
    $error = 'No reset link provided, or the link looks malformed.';
}

// ── Handle new-password submission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['password']         ?? '';
    $confirm      = $_POST['password_confirm'] ?? '';

    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm) {
        $error = 'The two passwords don\'t match. Please try again.';
    } else {
        // All good — hash and update, then invalidate the token
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $up = $conn->prepare(
            "UPDATE users
             SET password = ?, reset_token = NULL, reset_token_expires_at = NULL
             WHERE id = ?"
        );
        $up->bind_param('si', $hash, $user_id);
        if ($up->execute()) {
            $success = true;
        } else {
            $error = 'Something went wrong saving your new password. Please try again.';
        }
        $up->close();
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
<title>Reset Password — Wiyule Motors</title>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', sans-serif; background: #0b0f1a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; color: #f1f5f9; }
    .card { background: #141925; border: 1px solid rgba(255,255,255,0.08); border-radius: 22px; padding: 40px 32px; max-width: 460px; width: 100%; box-shadow: 0 32px 80px rgba(0,0,0,0.5); }
    .badge { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
    .badge.red { background: rgba(220,38,38,0.16); color: #f87171; }
    .badge.green { background: rgba(34,197,94,0.16); color: #86efac; }
    h1 { font-size: 22px; font-weight: 800; text-align: center; margin-bottom: 6px; }
    .sub { font-size: 14px; color: #94a3b8; text-align: center; margin-bottom: 24px; line-height: 1.5; }
    .sub b { color: #f1f5f9; }
    label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; margin-top: 14px; }
    .input { width: 100%; padding: 14px 16px; background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px; color: #fff; font-size: 15px; font-family: 'Sora', sans-serif; outline: none; transition: border-color 0.2s; }
    .input:focus { border-color: #dc2626; }
    .hint { font-size: 11px; color: #64748b; margin-top: 6px; }
    .btn { width: 100%; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; border: none; padding: 15px; border-radius: 13px; font-size: 15px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; margin-top: 18px; transition: transform 0.2s; }
    .btn:hover { transform: translateY(-1px); }
    .btn.secondary { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); }
    .msg { padding: 12px 14px; border-radius: 11px; font-size: 13px; font-weight: 500; margin-bottom: 16px; line-height: 1.5; }
    .msg.err { background: rgba(220,38,38,0.12); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; }
    .links { text-align: center; margin-top: 18px; font-size: 13px; color: #94a3b8; }
    .links a { color: #f87171; font-weight: 600; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">

<?php if ($success): ?>
    <div class="badge green">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
    </div>
    <h1>Password reset!</h1>
    <p class="sub">Your password has been updated.<br>You can now sign in with the new one.</p>
    <a href="<?= $base ?>/pages/login.php" class="btn" style="display:block;text-align:center;text-decoration:none">Sign in</a>

<?php elseif (!$token_valid): ?>
    <div class="badge red">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-2h2zm0-4h-2V7h2z"/></svg>
    </div>
    <h1>Link not valid</h1>
    <p class="sub"><?= htmlspecialchars($error) ?></p>
    <a href="<?= $base ?>/pages/forgot-password.php" class="btn" style="display:block;text-align:center;text-decoration:none">Request a new link</a>
    <div class="links">
        <a href="<?= $base ?>/pages/login.php">Back to sign in</a>
    </div>

<?php else: ?>
    <div class="badge red">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1a5 5 0 0 0-5 5v4H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-2V6a5 5 0 0 0-5-5zm-3 9V6a3 3 0 0 1 6 0v4z"/></svg>
    </div>
    <h1>Choose a new password</h1>
    <p class="sub">For <b><?= htmlspecialchars($user_email) ?></b><br>Pick something you'll remember — at least 8 characters.</p>

    <?php if ($error): ?>
        <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= $base ?>/pages/reset-password.php">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <label for="password">New password</label>
        <input type="password" id="password" name="password" class="input" minlength="8" placeholder="At least 8 characters" required autofocus>
        <div class="hint">Mix letters, numbers and symbols for the strongest password.</div>

        <label for="password_confirm">Confirm new password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="input" minlength="8" placeholder="Repeat the password" required>

        <button type="submit" class="btn">Update password</button>
    </form>

    <div class="links">
        <a href="<?= $base ?>/pages/login.php">Back to sign in</a>
    </div>
<?php endif; ?>

</div>
</body>
</html>
