<?php
// ── Session & auth ────────────────────────────────────────────
require_once __DIR__ . '/../includes/security.php';
wm_secure_session_start();

// Base URL — auto-detects localhost vs live site
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require __DIR__ . '/../includes/db_connection.php';

// Already logged in — go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . $base . "/pages/dashboard.php");
    exit();
}

// ── Process login POST ────────────────────────────────────────
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Basic server-side validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } else {
        // ── Rate limit check (5 attempts / 15 min per email or IP) ──
        $throttle = wm_login_throttle($email);
        if (!$throttle['ok']) {
            $error = $throttle['reason'];
        } else {
        // ── Fetch all fields dashboard needs ─────────────────
        $stmt = $conn->prepare("SELECT id, full_name, email, password, phone, created_at, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // ── Email verification gate ──
                if (isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
                    // Don't fully log them in — send them to verify-email instead
                    $_SESSION['pending_verify_id']    = $user['id'];
                    $_SESSION['pending_verify_email'] = $user['email'];
                    $names = explode(' ', $user['full_name'], 2);
                    $_SESSION['pending_first_name']   = $names[0] ?? '';
                    $_SESSION['pending_last_name']    = $names[1] ?? '';
                    $_SESSION['pending_phone']        = $user['phone'] ?? '';
                    header('Location: ' . $base . '/pages/verify-email.php');
                    exit();
                }
                // ── Password OK + email verified → require a fresh 2FA code by email ──
                $uid      = (int)$user['id'];
                $uemail   = $user['email'];
                $ufirst   = explode(' ', $user['full_name'], 2)[0];
                $remember = !empty($_POST['remember']);
                $stmt->close();

                require_once __DIR__ . '/../includes/auth.php';
                wm_issue_login_code($conn, $uid, $uemail, $ufirst);

                // Hold identity in a PENDING slot — NOT logged in until the code is entered.
                $_SESSION['pending_2fa_id']       = $uid;
                $_SESSION['pending_2fa_email']    = $uemail;
                $_SESSION['pending_2fa_remember'] = $remember;
                unset($_SESSION['twofa_attempts']);

                header('Location: ' . $base . '/pages/login-verify.php');
                exit();

            } else {
                wm_login_record_failure($email);
                // ── Vague error on purpose — don't reveal if email exists ──
                $error = 'Incorrect email or password. Please try again.';
            }
        } else {
            wm_login_record_failure($email);
            $error = 'Incorrect email or password. Please try again.';
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
    <meta name="description" content="Sign in to your Wiyule Motors account — manage bookings, track services and more.">
    <meta name="robots" content="noindex, nofollow">
    <title>Sign In — Wiyule Motors</title>

    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        html, body { max-width: 100%; overflow-x: hidden; }
        img, video, svg { max-width: 100%; height: auto; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sora', sans-serif; background: #f8fafc; }

        /* ── Nav ─────────────────────────────────────────────── */
        .nav-accent { height: 3px; background: linear-gradient(90deg, #dc2626, #b91c1c, #7f1d1d); }

        /* ── Left hero panel ─────────────────────────────────── */
        .login-hero {
            background:
                linear-gradient(135deg, rgba(220,38,38,0.95) 0%, rgba(127,29,29,0.99) 100%),
                url('https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=1200&q=80')
                center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        .login-hero::before {
            content: '';
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 15% 75%, rgba(255,255,255,0.07) 0%, transparent 55%),
                radial-gradient(circle at 85% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 44px 44px;
            animation: gridDrift 22s linear infinite;
        }
        @keyframes gridDrift { from { transform: translateY(0); } to { transform: translateY(44px); } }
        .orb {
            position: absolute; border-radius: 50%;
            filter: blur(50px); pointer-events: none;
            animation: orbFloat 7s ease-in-out infinite;
        }
        @keyframes orbFloat { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-18px) scale(1.06); } }

        /* ── Stat cards ──────────────────────────────────────── */
        .stat-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px; padding: 16px 20px;
            backdrop-filter: blur(6px); flex: 1;
            text-align: center;
        }

        /* ── Feature pills ───────────────────────────────────── */
        .feature-pill {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 12px; padding: 10px 14px;
            backdrop-filter: blur(4px);
            transition: background 0.2s;
        }
        .feature-pill:hover { background: rgba(255,255,255,0.16); }

        /* ── Input fields ────────────────────────────────────── */
        .field-wrap { position: relative; margin-bottom: 20px; }
        .field-wrap label {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 13px; font-weight: 600; color: #374151;
            margin-bottom: 8px; letter-spacing: 0.02em;
        }
        .field-wrap label .req { color: #dc2626; margin-left: 2px; }

        /* Input wrapper — all icon positioning keys off this */
        .field-input-wrap { position: relative; }

        .field-input {
            width: 100%; height: 52px;
            padding: 0 16px 0 48px;
            border: 1.5px solid #e5e7eb; border-radius: 12px;
            font-size: 15px; font-family: 'Sora', sans-serif;
            color: #111827; background: #fff; outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }
        .field-input:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
        .field-input.valid   { border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,0.08); }
        .field-input.invalid { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }

        /* Icon inside input — vertically centered */
        .field-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; color: #9ca3af;
            pointer-events: none; transition: color 0.2s;
        }
        .field-input:focus ~ .field-icon { color: #dc2626; }
        .field-input.valid  ~ .field-icon { color: #16a34a; }
        .field-input.invalid ~ .field-icon { color: #dc2626; }

        /* Password toggle */
        .eye-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 6px;
            color: #9ca3af; transition: color 0.2s; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .eye-btn:hover { color: #374151; background: #f1f5f9; }
        .eye-btn svg { width: 18px; height: 18px; }

        /* Password field needs extra right padding for eye button */
        input[type="password"].field-input,
        input[type="text"]#password.field-input { padding-right: 48px; }

        .field-msg {
            font-size: 12px; font-weight: 500; margin-top: 6px;
            min-height: 16px; display: flex; align-items: center; gap: 4px;
        }
        .field-msg.ok  { color: #16a34a; }
        .field-msg.err { color: #dc2626; }

        /* ── Remember me ─────────────────────────────────────── */
        .custom-cb { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .custom-cb input[type="checkbox"] { display: none; }
        .cb-box {
            width: 20px; height: 20px; flex-shrink: 0;
            border: 2px solid #d1d5db; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; background: #fff;
        }
        .custom-cb input:checked ~ .cb-box { background: #dc2626; border-color: #dc2626; }
        .custom-cb input:checked ~ .cb-box svg { display: block; }
        .cb-box svg { display: none; color: #fff; width: 12px; height: 12px; }

        /* ── Submit button ───────────────────────────────────── */
        .btn-primary {
            position: relative; overflow: hidden;
            width: 100%;
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95));
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff; border: 1px solid rgba(255,255,255,0.25); border-radius: 16px;
            padding: 16px 24px; font-size: 16px; font-weight: 700;
            font-family: 'Sora', sans-serif; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.35);
            letter-spacing: 0.01em;
        }
        .btn-primary::before {
            content: ''; position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .btn-primary:hover::before { left: 120%; }
        .btn-primary:hover    { transform: translateY(-3px) scale(1.02); box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.45); }
        .btn-primary:active   { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }
        @media (prefers-reduced-motion: reduce) { .btn-primary { transition: none !important; } .btn-primary::before { display: none; } }

        /* ── Alert banner ────────────────────────────────────── */
        .alert {
            display: none; padding: 14px 18px; border-radius: 14px;
            font-size: 14px; font-weight: 500; margin-bottom: 20px;
            align-items: center; gap: 10px;
        }
        .alert.show { display: flex; }
        .alert.err  { background: #fef2f2; border: 1.5px solid #fecaca; color: #dc2626; }
        .alert.ok   { background: #f0fdf4; border: 1.5px solid #bbf7d0; color: #15803d; }

        /* ── Forgot password modal ───────────────────────────── */
        .fp-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 200; align-items: center; justify-content: center; padding: 20px;
        }
        .fp-overlay.active { display: flex; }
        .fp-modal {
            background: #fff; border-radius: 24px;
            padding: 40px 36px; max-width: 420px; width: 100%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.2);
            animation: modalPop 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes modalPop { from { transform: scale(0.85); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* ── Shake animation ─────────────────────────────────── */
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-8px); }
            40%      { transform: translateX(8px); }
            60%      { transform: translateX(-5px); }
            80%      { transform: translateX(5px); }
        }
        .shake { animation: shake 0.45s ease; }

        /* ── Spinner ─────────────────────────────────────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 0.9s linear infinite; }

        /* ── WhatsApp float ──────────────────────────────────── */
        .whatsapp-btn {
            position: fixed; bottom: 24px; right: 24px;
            background: #25d366; color: #fff;
            width: 52px; height: 52px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 16px rgba(37,211,102,0.4);
            transition: all 0.3s; z-index: 50; text-decoration: none;
        }
        .whatsapp-btn:hover { transform: scale(1.1); }
        .feedback-btn { position:fixed;bottom:24px;left:24px;display:flex;align-items:center;gap:8px;background:#fff;color:#374151;padding:10px 16px;border-radius:99px;box-shadow:0 4px 20px rgba(0,0,0,0.12);border:1.5px solid #e5e7eb;font-size:13px;font-weight:600;font-family:"Sora",sans-serif;cursor:pointer;text-decoration:none;transition:all 0.3s;z-index:50; }
        .feedback-btn:hover { border-color:#dc2626;color:#dc2626;transform:translateY(-2px); }
        .feedback-btn svg { width:16px;height:16px;flex-shrink:0; }
        @media(max-width:767px) { .feedback-btn span{display:none} .feedback-btn{padding:10px;border-radius:50%} }

        /* ── Shared with index.php: liquid glass nav + glass buttons ── */
        .glass-nav {
            background: rgba(255,255,255,0.72);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(255,255,255,0.5);
            box-shadow: 0 1px 0 rgba(0,0,0,0.04), 0 4px 24px rgba(0,0,0,0.04);
        }
        .gbtn {
            position: relative; overflow: hidden;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 28px; border-radius: 16px;
            font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700;
            text-decoration: none; cursor: pointer;
            border: 1px solid rgba(255,255,255,0.25);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
        }
        .gbtn::before {
            content: ''; position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .gbtn:hover::before { left: 120%; }
        .gbtn:hover { transform: translateY(-3px) scale(1.02); }
        .gbtn-primary {
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95));
            backdrop-filter: blur(12px) saturate(180%); -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.35);
        }
        .gbtn-primary:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.45); }
        .gbtn-dark {
            background: linear-gradient(135deg, rgba(45,45,47,0.95), rgba(15,15,17,0.95));
            backdrop-filter: blur(12px) saturate(160%); -webkit-backdrop-filter: blur(12px) saturate(160%);
            color: #fff; box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 10px 28px rgba(15,23,42,0.3);
        }
        .gbtn-dark:hover { box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), 0 16px 38px rgba(15,23,42,0.4); }

        /* ── Responsive ──────────────────────────────────────── */
        @media (max-width: 1023px) {
            .login-hero { padding: 48px 24px 36px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .gbtn { transition: none !important; }
            .gbtn::before { display: none; }
        }
    </style>
</head>
<body class="antialiased">

<!-- WhatsApp Floating Button -->
<a href="https://wa.me/265993575111" class="whatsapp-btn" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</a>

<!-- ═══ Navigation ═══ -->
<nav class="glass-nav sticky top-0 z-50">
    <div class="nav-accent"></div>
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-20">
            <!-- Logo -->
            <a href="<?= $base ?>/" class="flex items-center space-x-3" style="text-decoration:none">
                <img class="h-12 w-auto"
                     src="<?= $base ?>/assets/images/logo.png"
                     alt="Wiyule Motors Logo">
                <div>
                    <span class="text-xl font-bold text-gray-900 block leading-tight">Wiyule Motors</span>
                    <span class="text-xs text-gray-400 font-medium" style="letter-spacing:0.04em">Blantyre, Malawi</span>
                </div>
            </a>

            <!-- Desktop links -->
            <div class="hidden md:flex items-center gap-7">
                <a href="<?= $base ?>/"           class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Home</a>
                <a href="<?= $base ?>/#services"  class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Services</a>
                <a href="<?= $base ?>/#booking"   class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Book</a>
                <a href="<?= $base ?>/#about"     class="text-gray-600 hover:text-red-600 font-medium text-sm transition">About</a>
                <a href="<?= $base ?>/#contact"   class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Contact</a>
            </div>

            <!-- Desktop auth -->
            <div class="hidden md:flex items-center gap-3">
                <a href="<?= $base ?>/pages/login.php" class="gbtn gbtn-dark" style="padding:9px 18px;font-size:13px;border-radius:12px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Sign In
                </a>
                <a href="<?= $base ?>/pages/signup.php" class="gbtn gbtn-primary" style="padding:9px 18px;font-size:13px;border-radius:12px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Create Account
                </a>
            </div>

            <!-- Mobile toggle -->
            <button id="mobileMenuBtn" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="md:hidden p-2 rounded-xl text-gray-600 hover:bg-gray-100 border border-gray-200" aria-label="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
        <div class="px-4 py-4 space-y-2">
            <a href="<?= $base ?>/"           class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Home</a>
            <a href="<?= $base ?>/#services"  class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Services</a>
            <a href="<?= $base ?>/#booking"   class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Book a Service</a>
            <a href="<?= $base ?>/#about"     class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">About</a>
            <a href="<?= $base ?>/#contact"   class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Contact</a>
            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
            <a href="<?= $base ?>/pages/signup.php" class="gbtn gbtn-primary" style="width:100%">Create Free Account</a>
        </div>
    </div>
</nav>

<!-- ═══ Main Layout ═══ -->
<main class="min-h-screen flex flex-col lg:flex-row">

    <!-- LEFT — Hero Panel -->
    <div class="login-hero lg:w-5/12 xl:w-2/5 flex flex-col justify-center px-10 py-16 lg:py-24 lg:min-h-screen relative">
        <div class="hero-grid"></div>
        <div class="orb w-56 h-56 bg-white/10 -top-10 -right-16" style="animation-delay:0s"></div>
        <div class="orb w-36 h-36 bg-white/10 bottom-16 -left-8"  style="animation-delay:3s"></div>

        <div class="relative z-10 max-w-sm mx-auto lg:mx-0" data-aos="fade-right">

            <!-- Badge -->
            <span class="inline-flex items-center gap-2 bg-white/15 border border-white/25 text-white text-xs font-bold px-4 py-2 rounded-full mb-8 backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Secure Member Portal · Wiyule Motors
            </span>

            <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-5">
                Welcome<br><span class="text-red-200">Back</span> 👋
            </h1>
            <p class="text-red-100 text-lg leading-relaxed mb-10">
                Sign in to manage bookings, track your vehicle's service history, and access exclusive member benefits.
            </p>

            <!-- Stats -->
            <div class="flex gap-3 mb-10">
                <div class="stat-card">
                    <p class="text-2xl font-extrabold text-white">1K+</p>
                    <p class="text-xs text-red-200 mt-0.5 font-medium">Active Members</p>
                </div>
                <div class="stat-card">
                    <p class="text-2xl font-extrabold text-white">5★</p>
                    <p class="text-xs text-red-200 mt-0.5 font-medium">Avg. Rating</p>
                </div>
                <div class="stat-card">
                    <p class="text-2xl font-extrabold text-white">10yr</p>
                    <p class="text-xs text-red-200 mt-0.5 font-medium">Trusted Service</p>
                </div>
            </div>

            <!-- Features -->
            <p class="text-xs text-red-300 font-semibold uppercase tracking-widest mb-4">Your dashboard includes</p>
            <div class="space-y-2.5">
                <?php
                $features = [
                    ['svg'=>'<svg width="100%" height="100%" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M19 4h-1V3a1 1 0 0 0-2 0v1H8V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9h16z"/></svg>', 'text'=>'View & manage all your bookings'],
                    ['svg'=>'<svg width="100%" height="100%" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 1a11 11 0 1 0 0 22 11 11 0 0 0 0-22zm4 12h-4a1 1 0 0 1-1-1V6a1 1 0 0 1 2 0v5h3a1 1 0 0 1 0 2z"/></svg>', 'text'=>'Full vehicle service history'],
                    ['svg'=>'<svg width="100%" height="100%" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2a6 6 0 0 0-6 6c0 3.5-1.5 5.5-2.3 6.4A1 1 0 0 0 4.5 16h15a1 1 0 0 0 .8-1.6C19.5 13.5 18 11.5 18 8a6 6 0 0 0-6-6zm0 20a3 3 0 0 0 2.8-2H9.2a3 3 0 0 0 2.8 2z"/></svg>', 'text'=>'Maintenance reminders'],
                    ['svg'=>'<svg width="100%" height="100%" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12.2V5.5A1.5 1.5 0 0 1 4.5 4h6.7c.5 0 1 .2 1.4.6l8 8a2 2 0 0 1 0 2.8zM8 8a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>', 'text'=>'Member-only discounts'],
                    ['svg'=>'<svg width="100%" height="100%" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V19a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H1a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>', 'text'=>'Vehicle & account settings'],
                ];
                foreach ($features as $f): ?>
                <div class="feature-pill">
                    <div class="w-7 h-7 bg-white/15 rounded-lg flex items-center justify-center flex-shrink-0" style="padding:5px">
                        <?= $f['svg'] ?>
                    </div>
                    <span class="text-white text-sm font-medium"><?= htmlspecialchars($f['text']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Social proof -->
            <div class="flex items-center gap-4 mt-10">
                <div class="flex -space-x-3">
                    <?php
                    $avatars = [
                        ['color'=>'bg-blue-500',  'initials'=>'JM'],
                        ['color'=>'bg-green-500', 'initials'=>'TK'],
                        ['color'=>'bg-purple-500','initials'=>'PC'],
                        ['color'=>'bg-yellow-500','initials'=>'SK'],
                    ];
                    foreach ($avatars as $a): ?>
                    <div class="w-10 h-10 rounded-full <?= $a['color'] ?> border-2 border-white/30 flex items-center justify-center text-white text-xs font-bold">
                        <?= $a['initials'] ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <p class="text-white font-bold text-sm">Join 1,000+ happy customers</p>
                    <div class="flex gap-0.5 mt-0.5">
                        <?php for ($i=0;$i<5;$i++): ?>
                        <svg class="w-3.5 h-3.5 text-yellow-300 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT — Form Panel -->
    <div class="lg:w-7/12 xl:w-3/5 flex flex-col justify-center px-6 sm:px-10 py-12 bg-gray-50">
        <div class="max-w-md mx-auto w-full">

            <!-- Top link -->
            <p class="text-right text-sm text-gray-500 mb-6">
                Don't have an account?
                <a href="<?= $base ?>/pages/signup.php" class="text-red-600 font-semibold hover:underline">Sign up free</a>
            </p>

            <!-- Header -->
            <div class="mb-8" data-aos="fade-up">
                <div class="w-14 h-14 bg-gradient-to-br from-red-600 to-red-700 rounded-2xl flex items-center justify-center shadow-lg mb-5" style="box-shadow:0 8px 18px -4px rgba(220,38,38,0.5)">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M11 3a2 2 0 0 0-2 2v2a1 1 0 0 0 2 0V5h8v14h-8v-2a1 1 0 0 0-2 0v2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-1.3 5.3a1 1 0 0 0 0 1.4L11.6 11H4a1 1 0 0 0 0 2h7.6l-1.9 1.3a1 1 0 1 0 1.4 1.4l3-3a1 1 0 0 0 0-1.4l-3-3a1 1 0 0 0-1.4 0z"/></svg>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-1">Sign in to your account</h2>
                <p class="text-gray-500 text-sm">Enter your credentials to access your dashboard.</p>
            </div>

            <!-- ── PHP error / success banner ── -->
            <?php if ($error): ?>
            <div class="alert show err" id="alertBanner" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="alertText"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php else: ?>
            <div class="alert" id="alertBanner" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="alertText"></span>
            </div>
            <?php endif; ?>

            <!-- ── Login Form ── -->
            <form id="loginForm" method="POST" action="login.php" novalidate>

                <!-- Email -->
                <div class="field-wrap">
                    <label for="email">
                        Email Address <span class="req">*</span>
                    </label>
                    <div class="field-input-wrap">
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required class="field-input <?= $error ? 'invalid' : '' ?>"
                               placeholder="john@example.com"
                               autocomplete="email">
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                    </div>
                    <p class="field-msg <?= $error ? 'err' : '' ?>" id="email-msg"></p>
                </div>

                <!-- Password -->
                <div class="field-wrap">
                    <label for="password">
                        Password <span class="req">*</span>
                        <a href="<?= $base ?>/pages/forgot-password.php" class="text-red-600 text-xs font-semibold hover:underline">Forgot password?</a>
                    </label>
                    <div class="field-input-wrap">
                        <input type="password" id="password" name="password"
                               required class="field-input"
                               placeholder="Your password"
                               autocomplete="current-password">
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="eye-btn" id="pwd-toggle" aria-label="Toggle password visibility">
                            <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <p class="field-msg" id="pwd-msg"></p>
                </div>

                <!-- Remember me -->
                <div class="flex items-center justify-between mb-6">
                    <label class="custom-cb">
                        <input type="checkbox" id="remember" name="remember">
                        <div class="cb-box">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <span class="text-sm text-gray-600 font-medium">Remember me</span>
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-primary" id="submitBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Sign In
                </button>

                <!-- Failed attempts hint -->
                <p class="text-xs text-center text-gray-400 mt-4" id="attemptsHint" style="display:none">
                    Too many failed attempts?
                    <a href="#" class="text-red-600 font-semibold" id="resetLink2">Reset your password</a>
                </p>
            </form>

            <!-- WhatsApp Help Banner -->
            <a href="https://wa.me/265993575111?text=Hi%20Wiyule%20Motors%2C%20I%20need%20help%20with%20my%20account." target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-3 mt-6 mb-6 p-4 bg-green-50 border-2 border-green-200 rounded-2xl hover:bg-green-100 hover:border-green-300 transition-all duration-200 group" style="text-decoration:none">
                <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </div>
                <div class="flex-1">
                    <div class="text-sm font-bold text-green-900">Need help? Chat on WhatsApp</div>
                    <div class="text-xs text-green-700">We reply within an hour during business hours</div>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" class="group-hover:translate-x-1 transition-transform"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>

            <!-- Sign up nudge -->
            <div class="bg-white border-2 border-gray-100 rounded-2xl p-5 text-center shadow-sm">
                <p class="text-sm text-gray-600 mb-3">New to Wiyule Motors? Create a free account and get:</p>
                <div class="flex flex-wrap justify-center gap-2 mb-4">
                    <?php foreach(['Online Booking','Service History','Reminders','Member Discounts'] as $b): ?>
                    <span class="text-xs font-semibold bg-red-50 text-red-600 border border-red-100 px-3 py-1.5 rounded-full">
                        ✓ <?= htmlspecialchars($b) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <a href="<?= $base ?>/pages/signup.php" class="gbtn gbtn-primary">
                    Create Free Account
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>

            <p class="text-xs text-center text-gray-400 mt-6">
                Protected by industry-standard encryption.
                <a href="<?= $base ?>/privacy.php" class="text-red-600 hover:underline">Privacy Policy</a>
            </p>

        </div>
    </div>
</main>

<!-- ─── Footer ─── -->
<footer class="bg-gray-900 text-gray-400 py-6 text-center text-sm">
    <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-2">
        <span>&copy; <?= date('Y') ?> Wiyule Motors. All rights reserved.</span>
        <div class="flex gap-5">
            <a href="<?= $base ?>/terms.php"    class="hover:text-white transition">Terms</a>
            <a href="<?= $base ?>/privacy.php"  class="hover:text-white transition">Privacy</a>
            <a href="<?= $base ?>/#contact" class="hover:text-white transition">Contact</a>
        </div>
    </div>
</footer>

<!-- ═══ Forgot Password Modal ═══ -->
<div class="fp-overlay" id="fpOverlay">
    <div class="fp-modal">
        <div id="fp-email-view">
            <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h3 class="text-xl font-extrabold text-gray-900 mb-1">Reset your password</h3>
            <p class="text-gray-500 text-sm mb-6">Enter your email and we'll help you reset your password via WhatsApp.</p>
            <div class="field-wrap">
                <label for="fp-email">Email Address <span class="req">*</span></label>
                <div class="field-input-wrap">
                    <input type="email" id="fp-email" class="field-input" placeholder="john@example.com" autocomplete="email">
                    <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                </div>
                <p class="field-msg" id="fp-email-msg"></p>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeFP()"
                        class="flex-1 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:border-red-300 hover:text-red-600 transition">
                    Cancel
                </button>
                <button type="button" onclick="submitReset()"
                        class="flex-1 btn-primary" style="padding:12px 20px;font-size:14px" id="fpSubmitBtn">
                    Continue via WhatsApp
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-4 text-center">Or call us directly at <a href="tel:+265993575111" class="text-red-600 font-semibold">+265 993 575 111</a></p>
        </div>
    </div>
</div>

<script>
AOS.init({ duration:600, once:true, offset:60 });
if(window.feather)feather.replace();

// ── Mobile nav ───────────────────────────────────────────────
// Mobile menu handled by inline onclick
// (see button onclick above)


// ── Eye toggle ───────────────────────────────────────────────
document.getElementById('pwd-toggle').addEventListener('click', function() {
    const input  = document.getElementById('password');
    const isText = input.type === 'text';
    input.type   = isText ? 'password' : 'text';
    document.getElementById('eye-open').style.display   = isText ? ''     : 'none';
    document.getElementById('eye-closed').style.display = isText ? 'none' : '';
});

// ── Helpers ──────────────────────────────────────────────────
function setField(id, state) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('valid', 'invalid');
    if (state) el.classList.add(state);
}
function showMsg(id, type, text) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'field-msg ' + type;
    el.innerHTML = text;
}
function showAlert(type, text) {
    const b = document.getElementById('alertBanner');
    b.className = 'alert show ' + type;
    document.getElementById('alertText').textContent = text;
    // Shake the form on error
    if (type === 'err') {
        const form = document.getElementById('loginForm');
        form.classList.remove('shake');
        void form.offsetWidth; // reflow to restart animation
        form.classList.add('shake');
    }
}

// ── Inline validation ─────────────────────────────────────────
document.getElementById('email').addEventListener('blur', function() {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
    if (!this.value)  { setField('email','');        showMsg('email-msg','','');                         return; }
    if (ok)           { setField('email','valid');   showMsg('email-msg','ok','✓ Looks good');           }
    else              { setField('email','invalid'); showMsg('email-msg','err','✗ Enter a valid email'); }
});

document.getElementById('password').addEventListener('input', function() {
    if (!this.value)          { setField('password','');        showMsg('pwd-msg','','');              return; }
    if (this.value.length < 6){ setField('password','invalid'); showMsg('pwd-msg','err','✗ Too short'); }
    else                      { setField('password','valid');   showMsg('pwd-msg','ok','✓ Good');       }
});

// ── Failed attempts counter ───────────────────────────────────
let failedAttempts = 0;

// ── Form submit ───────────────────────────────────────────────
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    let ok = true;

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setField('email','invalid');
        showMsg('email-msg','err','✗ Valid email required');
        ok = false;
    }
    if (!password) {
        setField('password','invalid');
        showMsg('pwd-msg','err','✗ Password required');
        ok = false;
    }
    if (!ok) { e.preventDefault(); return; }

    // Loading state
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Signing in…`;
});

// ── Show PHP error as shake if present ───────────────────────
<?php if ($error): ?>
(function() {
    const form = document.getElementById('loginForm');
    form.classList.add('shake');
    setTimeout(() => form.classList.remove('shake'), 500);
    // Increment failed attempts hint
    const hint = document.getElementById('attemptsHint');
    if (hint) hint.style.display = 'block';
})();
<?php endif; ?>

// ── Forgot password modal ─────────────────────────────────────
function openFP() {
    document.getElementById('fpOverlay').classList.add('active');
    const loginEmail = document.getElementById('email').value;
    if (loginEmail) document.getElementById('fp-email').value = loginEmail;
}
function closeFP() {
    document.getElementById('fpOverlay').classList.remove('active');
    document.getElementById('fp-email-view').style.display   = '';
    document.getElementById('fp-success-view').style.display = 'none';
    document.getElementById('fp-email').value = '';
    document.getElementById('fp-email-msg').textContent = '';
    setField('fp-email','');
}
async function submitReset() {
    const email = document.getElementById('fp-email').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setField('fp-email','invalid');
        showMsg('fp-email-msg','err','✗ Enter a valid email address');
        return;
    }
    setField('fp-email','valid');
    const msg = encodeURIComponent('Hi Wiyule Motors, I need to reset my password. My email is: ' + email);
    window.open('https://wa.me/265993575111?text=' + msg, '_blank');
    closeFP();
}

document.getElementById('forgotLink').addEventListener('click', e => { e.preventDefault(); openFP(); });
const rl2 = document.getElementById('resetLink2');
if (rl2) rl2.addEventListener('click', e => { e.preventDefault(); openFP(); });
document.getElementById('fpOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeFP();
});

// ── Social login placeholder ──────────────────────────────────
function socialLogin(provider) {
    toast ? toast.show(provider.charAt(0).toUpperCase()+provider.slice(1)+' sign-in coming soon!','info') : alert(provider+' sign-in coming soon!');
}
</script>
<?php if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); } ?>
</body>
</html>