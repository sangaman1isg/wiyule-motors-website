<?php
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require_once __DIR__ . '/../includes/db_connection.php';

// Already logged in as admin
if (isset($_SESSION['admin_id'])) {
    header("Location: " . $base . "/admin/");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // ── Rate limit check (5 attempts / 15 min per email or IP) ──
        $throttle = wm_login_throttle($email);
        if (!$throttle['ok']) {
            $error = $throttle['reason'];
        } else {
            $stmt = $conn->prepare("SELECT id, full_name, email, password, is_admin FROM users WHERE email = ? AND is_admin = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    wm_login_record_success($email);
                    session_regenerate_id(true);
                    $_SESSION['admin_id']   = $user['id'];
                    $_SESSION['admin_name'] = $user['full_name'];
                    $_SESSION['admin_email'] = $user['email'];
                    header("Location: " . $base . "/admin/");
                    exit;
                } else {
                    wm_login_record_failure($email);
                    $error = 'Invalid email or password.';
                }
            } else {
                wm_login_record_failure($email);
                $error = 'Invalid email or password.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/../includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login — Wiyule Motors</title>
    <link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sora', sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { background: #fff; border-radius: 24px; padding: 48px 40px; max-width: 420px; width: 100%; box-shadow: 0 32px 80px rgba(0,0,0,0.3); }
        .field { margin-bottom: 20px; }
        .field label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .field input { width: 100%; padding: 14px 16px; border: 2px solid #e5e7eb; border-radius: 12px; font-size: 15px; font-family: 'Sora', sans-serif; color: #111827; outline: none; transition: border-color 0.2s; }
        .field input:focus { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,0.08); }
        .btn { width: 100%; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; border: none; padding: 16px; border-radius: 14px; font-size: 16px; font-weight: 700; font-family: 'Sora', sans-serif; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 20px rgba(220,38,38,0.3); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(220,38,38,0.4); }
        .error { background: #fef2f2; border: 1.5px solid #fecaca; color: #dc2626; padding: 12px 16px; border-radius: 12px; font-size: 14px; font-weight: 500; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align:center;margin-bottom:32px">
            <div style="width:56px;height:56px;background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin-bottom:4px">Admin Panel</h1>
            <p style="font-size:14px;color:#64748b">Wiyule Motors Management</p>
        </div>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@wiyulemotors.com" required>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter admin password" required>
            </div>
            <button type="submit" class="btn">Sign In to Admin</button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:13px;color:#94a3b8">
            <a href="<?= $base ?>/" style="color:#dc2626;font-weight:600;text-decoration:none">← Back to website</a>
        </p>
    </div>
</body>
</html>
