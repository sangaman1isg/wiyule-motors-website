<?php
// ── MUST BE FIRST — session & processing before any HTML output ──
session_start();

$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

require_once __DIR__ . '/../includes/db_connection.php';

// Already logged in — go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . $base . "/pages/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']   ?? '');
    $last_name  = trim($_POST['last_name']    ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone      = trim($_POST['phone']        ?? '');
    $city       = trim($_POST['city']         ?? '');
    $type       = trim($_POST['account_type'] ?? 'individual');
    $password   = $_POST['password']          ?? '';
    $full_name  = trim($first_name . ' ' . $last_name);

    // ── Server-side validation ────────────────────────────────
    if (!$first_name || !$last_name || !$email || !$password) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "An account with that email already exists. <a href='" . $base . "/pages/login.php' class='underline font-bold'>Sign in instead?</a>";
        } else {
            $stmt->close();

            // Hash password and insert
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $full_name, $email, $hash, $phone);

            if ($stmt->execute()) {
                $new_id = $conn->insert_id;

                // ── Regenerate session to prevent fixation ────
                session_regenerate_id(true);

                $_SESSION['user_id']    = $new_id;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name']  = $last_name;
                $_SESSION['user_name']  = $full_name;
                $_SESSION['email']      = $email;
                $_SESSION['user_email'] = $email;
                $_SESSION['phone']      = $phone;
                $_SESSION['city']       = $city;
                $_SESSION['user_type']  = $type;

                // ── Save vehicle details from step 3 ─────────
                $_SESSION['vehicle_make']  = trim($_POST['v_make']  ?? '');
                $_SESSION['vehicle_model'] = trim($_POST['v_model'] ?? '');
                $_SESSION['vehicle_year']  = trim($_POST['v_year']  ?? '');
                $_SESSION['vehicle_reg']   = trim($_POST['v_reg']   ?? '');
                $_SESSION['vehicle_color'] = '';
                $_SESSION['vehicle_mileage']       = 0;
                $_SESSION['last_service_mileage']   = 0;
                $_SESSION['next_service_mileage']   = 5000;
                $_SESSION['vehicle_engine']         = '';
                $_SESSION['vehicle_transmission']   = '';

                // ── Also save vehicle to database ────────────
                if ($_SESSION['vehicle_make']) {
                    $vstmt = $conn->prepare("INSERT INTO vehicles (user_id, make, model, year, registration) VALUES (?, ?, ?, ?, ?)");
                    $vstmt->bind_param("issss", $new_id, $_SESSION['vehicle_make'], $_SESSION['vehicle_model'], $_SESSION['vehicle_year'], $_SESSION['vehicle_reg']);
                    $vstmt->execute();
                    $vstmt->close();
                }

                // Mark as new user for onboarding
                $_SESSION['user_created']           = time();
                $_SESSION['onboarding_completed']   = false;

                header('Location: ' . $base . '/pages/dashboard.php');
                exit;

            } else {
                error_log("Signup insert error: " . $conn->error);
                $error = "Something went wrong creating your account. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your Wiyule Motors account — manage bookings, track services, and get exclusive offers in Blantyre, Malawi.">
    <meta name="robots" content="noindex, nofollow">
    <title>Create Account — Wiyule Motors</title>

    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sora', sans-serif; background: #f8fafc; }

        /* ── Nav ─────────────────────────────────────────────── */
        .nav-accent { height: 3px; background: linear-gradient(90deg, #dc2626, #b91c1c, #7f1d1d); }

        /* ── Left hero panel ─────────────────────────────────── */
        .signup-hero {
            background:
                linear-gradient(135deg, rgba(220,38,38,0.92) 0%, rgba(153,27,27,0.97) 100%),
                url('https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=1200&q=80')
                center/cover no-repeat;
            position: relative; overflow: hidden;
        }
        .signup-hero::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: gridDrift 20s linear infinite;
        }
        @keyframes gridDrift { 0% { transform: translateY(0); } 100% { transform: translateY(40px); } }
        .orb { position: absolute; border-radius: 50%; filter: blur(40px); pointer-events: none; animation: orbFloat 6s ease-in-out infinite; }
        @keyframes orbFloat { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-16px) scale(1.05); } }

        /* ── Progress stepper ─────────────────────────────────── */
        .step-dot {
            width: 36px; height: 36px; border-radius: 50%;
            border: 2px solid #e5e7eb;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #9ca3af; background: #fff;
            transition: all 0.35s ease; position: relative; z-index: 1;
        }
        .step-dot.active { border-color: #dc2626; color: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,0.12); }
        .step-dot.done   { border-color: #dc2626; background: #dc2626; color: #fff; }
        .step-line { flex: 1; height: 2px; background: #e5e7eb; transition: background 0.45s ease; margin: 0 6px; }
        .step-line.done { background: #dc2626; }

        /* ── Form panels ──────────────────────────────────────── */
        .form-step { display: none; animation: stepIn 0.4s ease; }
        .form-step.active { display: block; }
        @keyframes stepIn { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: translateX(0); } }

        /* ── Input fields ─────────────────────────────────────── */
        .field-wrap { position: relative; margin-bottom: 20px; }
        .field-wrap label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; letter-spacing: 0.02em; }
        .field-wrap label .req { color: #dc2626; margin-left: 2px; }

        /* Input wrapper for proper icon alignment */
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

        /* Icon inside input - perfectly centered */
        .field-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; color: #9ca3af;
            pointer-events: none; transition: color 0.2s;
        }
        .field-input:focus ~ .field-icon { color: #dc2626; }
        .field-input.valid  ~ .field-icon { color: #16a34a; }
        .field-input.invalid ~ .field-icon { color: #dc2626; }

        /* Password toggle button */
        .eye-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 6px;
            color: #9ca3af; transition: color 0.2s; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .eye-btn:hover { color: #374151; background: #f1f5f9; }
        .eye-btn svg { width: 18px; height: 18px; }

        /* Password fields need room for eye button */
        input[type="password"].field-input,
        input[type="text"].field-input[data-pwd="1"] { padding-right: 48px; }

        .field-msg { font-size: 12px; font-weight: 500; margin-top: 6px; min-height: 16px; display: flex; align-items: center; gap: 4px; }
        .field-msg.ok  { color: #16a34a; }
        .field-msg.err { color: #dc2626; }

        /* ── Password strength ────────────────────────────────── */
        .strength-bar-wrap { display: flex; gap: 4px; margin-top: 8px; }
        .strength-seg { flex: 1; height: 4px; border-radius: 99px; background: #e5e7eb; transition: background 0.3s ease; }
        .strength-label { font-size: 12px; font-weight: 600; margin-top: 5px; color: #9ca3af; transition: color 0.3s; }

        /* ── Phone prefix ─────────────────────────────────────── */
        .phone-wrap { display: flex; gap: 8px; }
        .phone-prefix {
            width: 110px; flex-shrink: 0; height: 52px; padding: 0 10px;
            border: 1.5px solid #e5e7eb; border-radius: 12px;
            font-size: 14px; font-weight: 600; font-family: 'Sora', sans-serif;
            background: #fff; color: #374151; outline: none; transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .phone-prefix:focus { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
        .phone-wrap .field-input-wrap { flex: 1; }
        .phone-wrap .field-input-wrap .field-input { padding-left: 16px; }

        /* ── Account type cards ───────────────────────────────── */
        .account-card {
            border: 2px solid #e5e7eb; border-radius: 16px; padding: 18px 20px;
            cursor: pointer; transition: all 0.25s ease; background: #fff;
            display: flex; align-items: flex-start; gap: 14px;
        }
        .account-card:hover    { border-color: #fca5a5; background: #fff5f5; }
        .account-card.selected { border-color: #dc2626; background: #fff5f5; box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
        .account-card input[type="radio"] { display: none; }
        .account-card-icon { width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }

        /* ── Terms checkbox ───────────────────────────────────── */
        .custom-cb { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; }
        .custom-cb input[type="checkbox"] { display: none; }
        .cb-box {
            width: 20px; height: 20px; flex-shrink: 0;
            border: 2px solid #d1d5db; border-radius: 6px; margin-top: 1px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; background: #fff;
        }
        .custom-cb input:checked ~ .cb-box { background: #dc2626; border-color: #dc2626; }
        .custom-cb input:checked ~ .cb-box svg { display: block; }
        .cb-box svg { display: none; color: #fff; width: 12px; height: 12px; }

        /* ── Buttons ──────────────────────────────────────────── */
        .btn-primary {
            width: 100%; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #fff; border: none; border-radius: 14px; padding: 16px 24px;
            font-size: 16px; font-weight: 700; font-family: 'Sora', sans-serif;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s ease; box-shadow: 0 4px 20px rgba(220,38,38,0.3);
        }
        .btn-primary:hover    { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(220,38,38,0.4); }
        .btn-primary:active   { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }
        .btn-ghost {
            width: 100%; border: 2px solid #e5e7eb; background: transparent;
            color: #374151; border-radius: 14px; padding: 14px 24px;
            font-size: 15px; font-weight: 600; font-family: 'Sora', sans-serif;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.25s ease;
        }
        .btn-ghost:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

        /* ── Benefit pills ────────────────────────────────────── */
        .benefit-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
            border-radius: 99px; padding: 8px 16px; color: #fff;
            font-size: 13px; font-weight: 500; backdrop-filter: blur(4px);
        }

        /* ── Spinner ──────────────────────────────────────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 0.9s linear infinite; }

        /* ── WhatsApp ─────────────────────────────────────────── */
        .whatsapp-btn {
            position: fixed; bottom: 24px; right: 24px;
            background: #25d366; color: #fff;
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 20px rgba(37,211,102,0.5);
            transition: all 0.3s; z-index: 50; text-decoration: none;
        }
        .whatsapp-btn:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(37,211,102,0.6); }
        .whatsapp-btn svg { width: 26px; height: 26px; }
        .feedback-btn { position:fixed;bottom:24px;left:24px;display:flex;align-items:center;gap:8px;background:#fff;color:#374151;padding:10px 16px;border-radius:99px;box-shadow:0 4px 20px rgba(0,0,0,0.12);border:1.5px solid #e5e7eb;font-size:13px;font-weight:600;font-family:"Sora",sans-serif;cursor:pointer;text-decoration:none;transition:all 0.3s;z-index:50; }
        .feedback-btn:hover { border-color:#dc2626;color:#dc2626;transform:translateY(-2px); }
        .feedback-btn svg { width:16px;height:16px;flex-shrink:0; }
        @media(max-width:767px) { .feedback-btn span{display:none} .feedback-btn{padding:10px;border-radius:50%} }

        @media (max-width: 1023px) { .signup-hero { padding: 48px 24px 32px; } }
    </style>
</head>
<body class="antialiased">

<!-- WhatsApp Button (bottom-right) -->
<a href="https://wa.me/265993575111" class="whatsapp-btn" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</a>

<!-- Feedback Button (bottom-left) -->
<a href="https://wa.me/265993575111?text=Hi%20Wiyule%20Motors%2C%20I%20found%20an%20issue%20on%20your%20website%3A%20" class="feedback-btn" target="_blank" rel="noopener noreferrer" title="Report an issue">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span>Feedback</span>
</a>

<!-- ═══ Navigation ═══ -->
<nav class="bg-white shadow-md sticky top-0 z-50">
    <div class="nav-accent"></div>
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-20">
            <a href="<?= $base ?>/" class="flex items-center space-x-3" style="text-decoration:none">
                <img class="h-12 w-auto"
                     src="<?= $base ?>/assets/images/logo.png"
                     alt="Wiyule Motors Logo">
                <div>
                    <span class="text-xl font-bold text-gray-900 block leading-tight">Wiyule Motors</span>
                    <span class="text-xs text-gray-400 font-medium" style="letter-spacing:0.04em">Blantyre, Malawi</span>
                </div>
            </a>
            <div class="hidden md:flex items-center gap-7">
                <a href="<?= $base ?>/"          class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Home</a>
                <a href="<?= $base ?>/#services" class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Services</a>
                <a href="<?= $base ?>/#booking"  class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Book</a>
                <a href="<?= $base ?>/#about"    class="text-gray-600 hover:text-red-600 font-medium text-sm transition">About</a>
                <a href="<?= $base ?>/#contact"  class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Contact</a>
            </div>
            <div class="hidden md:flex items-center gap-3">
                <a href="<?= $base ?>/pages/login.php"
                   style="padding:8px 18px;border:1.5px solid #e5e7eb;color:#374151;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px"
                   onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626';this.style.background='#fef2f2'"
                   onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151';this.style.background='transparent'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Sign In
                </a>
                <a href="<?= $base ?>/pages/signup.php"
                   style="padding:8px 18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 10px rgba(220,38,38,0.3)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Create Account
                </a>
            </div>
            <button id="mobileMenuBtn" onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="md:hidden p-2 rounded-xl text-gray-600 hover:bg-gray-100 border border-gray-200" aria-label="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>
    </div>
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
        <div class="px-4 py-4 space-y-2">
            <a href="<?= $base ?>/"          class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Home</a>
            <a href="<?= $base ?>/#services" class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Services</a>
            <a href="<?= $base ?>/#booking"  class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Book a Service</a>
            <a href="<?= $base ?>/#about"    class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">About</a>
            <a href="<?= $base ?>/#contact"  class="block py-2 px-3 rounded-xl text-gray-700 hover:bg-gray-50 font-medium text-sm">Contact</a>
            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
            <a href="<?= $base ?>/pages/login.php" class="block text-center border border-red-600 text-red-600 py-3 rounded-xl font-bold text-sm">Sign In</a>
        </div>
    </div>
</nav>

<!-- ═══ Main Layout ═══ -->
<main class="min-h-screen flex flex-col lg:flex-row">

    <!-- LEFT — Hero Panel -->
    <div class="signup-hero lg:w-5/12 xl:w-2/5 flex flex-col justify-center px-10 py-16 lg:py-24 lg:min-h-screen relative">
        <div class="hero-grid"></div>
        <div class="orb w-48 h-48 bg-white/10 -top-12 -right-12" style="animation-delay:0s"></div>
        <div class="orb w-32 h-32 bg-white/10 bottom-20 -left-8"  style="animation-delay:2.5s"></div>

        <div class="relative z-10 max-w-sm mx-auto lg:mx-0" data-aos="fade-right">
            <?php if(isset($_GET['offer']) && $_GET['offer'] === 'first10'): ?>
            <div style="background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#78350f;padding:16px 18px;border-radius:14px;margin-bottom:20px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 24px rgba(0,0,0,0.15)">
                <span style="font-size:32px">🎉</span>
                <div>
                    <div style="font-size:14px;font-weight:900;color:#78350f">10% OFF Your First Service</div>
                    <div style="font-size:12px;color:#92400e;margin-top:2px">Create your free account to unlock this one-time offer</div>
                </div>
            </div>
            <?php endif; ?>
            <span class="inline-flex items-center gap-2 bg-white/15 border border-white/25 text-white text-xs font-bold px-4 py-2 rounded-full mb-8 backdrop-blur-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Free Member Account · Wiyule Motors
            </span>

            <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-5">
                Join Wiyule<br><span class="text-red-200">Motors Today</span> 🚗
            </h1>
            <p class="text-red-100 text-lg leading-relaxed mb-10">
                Create your free account and unlock the full Wiyule Motors experience. Book services, track your vehicle history and get exclusive member discounts.
            </p>

            <!-- Benefits -->
            <p class="text-xs text-red-300 font-semibold uppercase tracking-widest mb-5">What you get for free</p>
            <div class="flex flex-wrap gap-2 mb-10">
                <?php foreach([
                    ['🗓','Online Booking'],
                    ['🔧','Service History'],
                    ['🔔','Maintenance Reminders'],
                    ['🏷️','Member Discounts'],
                    ['🚗','Vehicle Tracking'],
                    ['📊','Health Reports'],
                ] as $b): ?>
                <span class="benefit-pill">
                    <span><?= $b[0] ?></span><?= $b[1] ?>
                </span>
                <?php endforeach; ?>
            </div>

            <!-- Social proof -->
            <div class="flex items-center gap-4">
                <div class="flex -space-x-3">
                    <?php foreach([
                        ['bg-blue-500','JM'],['bg-green-500','TK'],
                        ['bg-purple-500','PC'],['bg-yellow-500','SK'],
                    ] as $a): ?>
                    <div class="w-10 h-10 rounded-full <?= $a[0] ?> border-2 border-white/30 flex items-center justify-center text-white text-xs font-bold"><?= $a[1] ?></div>
                    <?php endforeach; ?>
                </div>
                <div>
                    <p class="text-white font-bold text-sm">1,000+ members already joined</p>
                    <div class="flex gap-0.5 mt-0.5">
                        <?php for($i=0;$i<5;$i++): ?>
                        <svg class="w-3.5 h-3.5 text-yellow-300 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT — Form Panel -->
    <div class="lg:w-7/12 xl:w-3/5 flex flex-col justify-center px-6 sm:px-10 py-12 bg-gray-50">
        <div class="max-w-lg mx-auto w-full">

            <p class="text-right text-sm text-gray-500 mb-6">
                Already have an account?
                <a href="<?= $base ?>/pages/login.php" class="text-red-600 font-semibold hover:underline">Sign in</a>
            </p>

            <!-- Header -->
            <div class="mb-8" data-aos="fade-up">
                <div class="w-14 h-14 bg-gradient-to-br from-red-600 to-red-700 rounded-2xl flex items-center justify-center shadow-lg mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-1">Create your free account</h2>
                <p class="text-gray-500 text-sm">Takes less than 2 minutes. No credit card required.</p>
            </div>

            <!-- PHP Error Banner -->
            <?php if ($error): ?>
            <div class="flex items-start gap-3 bg-red-50 border-2 border-red-200 text-red-700 p-4 rounded-2xl font-semibold text-sm mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <!-- Progress Stepper -->
            <div class="flex items-center mb-8">
                <div class="step-dot active" id="dot-1">1</div>
                <div class="step-line"       id="line-1"></div>
                <div class="step-dot"        id="dot-2">2</div>
                <div class="step-line"       id="line-2"></div>
                <div class="step-dot"        id="dot-3">3</div>
            </div>
            <div class="flex justify-between text-xs font-semibold text-gray-500 mb-8" style="margin-top:-24px">
                <span>Account</span>
                <span>Personal Info</span>
                <span>Vehicle</span>
            </div>

            <!-- ═══ Form ═══ -->
            <form id="signupForm" method="POST" action="signup.php" novalidate>

                <!-- STEP 1 — Account Setup -->
                <div class="form-step active" id="step-1">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="w-7 h-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-center text-sm font-bold">1</span>
                        Account Setup
                    </h3>

                    <div class="field-wrap">
                        <label for="email">Email Address <span class="req">*</span></label>
                        <div class="field-input-wrap">
                            <input type="email" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="field-input" placeholder="john@example.com" autocomplete="email">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                        </div>
                        <p class="field-msg" id="email-msg"></p>
                    </div>

                    <div class="field-wrap">
                        <label for="password">Password <span class="req">*</span></label>
                        <div class="field-input-wrap">
                            <input type="password" id="password" name="password" class="field-input" placeholder="Min. 8 characters" autocomplete="new-password">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <button type="button" class="eye-btn" id="pwd-toggle" aria-label="Toggle password">
                                <svg id="eye-open"   xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <div class="strength-bar-wrap" id="strength-bars">
                            <div class="strength-seg" id="s1"></div>
                            <div class="strength-seg" id="s2"></div>
                            <div class="strength-seg" id="s3"></div>
                            <div class="strength-seg" id="s4"></div>
                        </div>
                        <p class="strength-label" id="strength-label">Enter a password</p>
                    </div>

                    <div class="field-wrap">
                        <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                        <div class="field-input-wrap">
                            <input type="password" id="confirm_password" name="confirm_password" class="field-input" placeholder="Re-enter password" autocomplete="new-password">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <button type="button" class="eye-btn" id="cpwd-toggle" aria-label="Toggle confirm password">
                                <svg id="ceye-open"   xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="ceye-closed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                        <p class="field-msg" id="cpwd-msg"></p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Account Type <span class="req" style="color:#dc2626">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="account-card selected" onclick="selectCard(this)">
                                <input type="radio" name="account_type" value="individual" checked>
                                <div class="account-card-icon bg-red-100">🧑</div>
                                <div>
                                    <p class="font-bold text-gray-900 text-sm">Individual</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Personal vehicle owner</p>
                                </div>
                            </label>
                            <label class="account-card" onclick="selectCard(this)">
                                <input type="radio" name="account_type" value="business">
                                <div class="account-card-icon bg-blue-100">🏢</div>
                                <div>
                                    <p class="font-bold text-gray-900 text-sm">Business</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Fleet or company account</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button type="button" class="btn-primary" onclick="nextStep(1)">
                        Continue
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>

                <!-- STEP 2 — Personal Information -->
                <div class="form-step" id="step-2">
                    <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                        <span class="w-7 h-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-content text-sm font-bold flex items-center justify-center">2</span>
                        Personal Information
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field-wrap">
                            <label for="first_name">First Name <span class="req">*</span></label>
                            <div class="field-input-wrap">
                                <input type="text" id="first_name" name="first_name"
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                                       class="field-input" placeholder="Daniel" autocomplete="given-name">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <p class="field-msg" id="fn-msg"></p>
                        </div>
                        <div class="field-wrap">
                            <label for="last_name">Last Name <span class="req">*</span></label>
                            <div class="field-input-wrap">
                                <input type="text" id="last_name" name="last_name"
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                                       class="field-input" placeholder="Kumwenda" autocomplete="family-name">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <p class="field-msg" id="ln-msg"></p>
                        </div>
                    </div>

                    <div class="field-wrap">
                        <label for="phone">Phone Number <span class="req">*</span></label>
                        <div class="phone-wrap">
                            <select class="phone-prefix" id="phone_prefix" name="phone_prefix">
                                <option value="+265">🇲🇼 +265</option>
                                <option value="+260">🇿🇲 +260</option>
                                <option value="+263">🇿🇼 +263</option>
                                <option value="+255">🇹🇿 +255</option>
                                <option value="+254">🇰🇪 +254</option>
                                <option value="+27"> 🇿🇦 +27</option>
                                <option value="+44"> 🇬🇧 +44</option>
                                <option value="+1">  🇺🇸 +1</option>
                            </select>
                            <div class="field-input-wrap">
                                <input type="tel" id="phone" name="phone"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                       class="field-input" placeholder="993 575 111" autocomplete="tel" style="padding-left:16px">
                            </div>
                        </div>
                        <p class="field-msg" id="phone-msg"></p>
                    </div>

                    <div class="field-wrap">
                        <label for="city">City / Town <span class="req">*</span></label>
                        <div class="field-input-wrap">
                            <input type="text" id="city" name="city"
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                                   class="field-input" placeholder="Blantyre" autocomplete="address-level2">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <p class="field-msg" id="city-msg"></p>
                    </div>

                    <div class="field-wrap">
                        <label for="referral">How did you hear about us?</label>
                        <div class="field-input-wrap">
                            <select id="referral" name="referral" class="field-input">
                                <option value="">Select an option</option>
                                <option value="google">Google Search</option>
                                <option value="facebook">Facebook / Social Media</option>
                                <option value="friend">Friend or Family</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="walk-in">Saw the Workshop</option>
                                <option value="other">Other</option>
                            </select>
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" class="btn-ghost" onclick="prevStep(2)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" class="btn-primary" onclick="nextStep(2)">
                            Continue
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>

                <!-- STEP 3 — Vehicle & Preferences -->
                <div class="form-step" id="step-3">
                    <h3 class="text-xl font-bold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="w-7 h-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-center text-sm font-bold">3</span>
                        Vehicle & Preferences
                    </h3>
                    <p class="text-sm text-gray-500 mb-6">Optional — helps us serve you better.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field-wrap">
                            <label for="v_make">Vehicle Make</label>
                            <div class="field-input-wrap">
                                <input type="text" id="v_make" name="v_make" class="field-input" placeholder="Toyota">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            </div>
                        </div>
                        <div class="field-wrap">
                            <label for="v_model">Vehicle Model</label>
                            <div class="field-input-wrap">
                                <input type="text" id="v_model" name="v_model" class="field-input" placeholder="Corolla">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="field-wrap">
                            <label for="v_year">Year</label>
                            <div class="field-input-wrap">
                                <input type="number" id="v_year" name="v_year" class="field-input" placeholder="2020" min="1980" max="<?= date('Y') ?>">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                        </div>
                        <div class="field-wrap">
                            <label for="v_reg">Registration No.</label>
                            <div class="field-input-wrap">
                                <input type="text" id="v_reg" name="v_reg" class="field-input" placeholder="BT 1234">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="field-wrap">
                        <label for="pref_service">Primary Service Interest</label>
                        <div class="field-input-wrap">
                            <select id="pref_service" name="pref_service" class="field-input">
                                <option value="">Select service</option>
                                <option value="maintenance">General Maintenance</option>
                                <option value="engine">Engine Repair</option>
                                <option value="detailing">Auto Detailing</option>
                                <option value="brakes">Brake Services</option>
                                <option value="ac">AC Repair</option>
                                <option value="tyres">Tyre Services</option>
                                <option value="battery">Battery Services</option>
                                <option value="wrapping">Vehicle Wrapping</option>
                                <option value="parts">Parts Supply</option>
                            </select>
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">Notification Preferences</label>
                        <div class="space-y-2">
                            <?php foreach([
                                ['notif_sms',  'SMS reminders & booking confirmations', true],
                                ['notif_email','Email updates and service reports',      true],
                                ['notif_promo','Promotions and special offers',          false],
                            ] as $n): ?>
                            <label class="custom-cb">
                                <input type="checkbox" name="<?= $n[0] ?>" id="<?= $n[0] ?>" <?= $n[2] ? 'checked' : '' ?>>
                                <div class="cb-box"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                                <span class="text-sm text-gray-700"><?= $n[1] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-6 p-4 bg-gray-100 rounded-2xl">
                        <label class="custom-cb">
                            <input type="checkbox" id="terms" name="terms" required>
                            <div class="cb-box"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                            <span class="text-sm text-gray-700">
                                I agree to Wiyule Motors'
                                <a href="<?= $base ?>/terms.php"   class="text-red-600 font-semibold hover:underline">Terms of Service</a> and
                                <a href="<?= $base ?>/privacy.php" class="text-red-600 font-semibold hover:underline">Privacy Policy</a>.
                            </span>
                        </label>
                        <p class="field-msg" id="terms-msg" style="margin-top:8px;margin-left:32px"></p>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" class="btn-ghost" onclick="prevStep(3)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="submit" class="btn-primary" id="submitBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Create My Account
                        </button>
                    </div>
                </div>
            </form>

            <!-- Help section -->
            <div style="margin-top:24px;padding:16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;display:flex;align-items:center;gap:12px">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2" style="flex-shrink:0"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:700;color:#15803d">Need help signing up?</div>
                    <div style="font-size:12px;color:#166534;margin-top:2px">Chat with us on WhatsApp and we'll guide you through it</div>
                </div>
                <a href="https://wa.me/265993575111?text=<?= urlencode('Hi Wiyule Motors, I need help creating an account.') ?>" target="_blank" rel="noopener noreferrer" style="background:#15803d;color:#fff;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap">Chat Now</a>
            </div>

            <p class="text-xs text-gray-400 mt-6 text-center">
                By signing up you agree to our
                <a href="<?= $base ?>/terms.php"   class="text-red-600 hover:underline">Terms</a> &
                <a href="<?= $base ?>/privacy.php" class="text-red-600 hover:underline">Privacy Policy</a>.
            </p>
        </div>
    </div>
</main>

<!-- Footer -->
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

<script>
AOS.init({ duration:600, once:true, offset:60 });
feather.replace();

// Mobile menu handled by inline onclick
// (see button onclick above)


function selectCard(el) {
    document.querySelectorAll('.account-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type="radio"]').checked = true;
}

function makeEyeToggle(toggleId, inputId, openId, closedId) {
    document.getElementById(toggleId).addEventListener('click', function() {
        const input = document.getElementById(inputId);
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        document.getElementById(openId).style.display  =  isText ? ''     : 'none';
        document.getElementById(closedId).style.display = isText ? 'none' : '';
    });
}
makeEyeToggle('pwd-toggle',  'password',         'eye-open',  'eye-closed');
makeEyeToggle('cpwd-toggle', 'confirm_password', 'ceye-open', 'ceye-closed');

// ── Password strength ─────────────────────────────────────────
const strengthColors = ['#dc2626','#f59e0b','#3b82f6','#16a34a'];
const strengthLabels = ['Weak','Fair','Good','Strong'];
document.getElementById('password').addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    for (let i = 1; i <= 4; i++) {
        const seg = document.getElementById('s' + i);
        seg.style.background = i <= score ? strengthColors[score-1] : '#e5e7eb';
    }
    const lbl = document.getElementById('strength-label');
    if (v.length === 0) { lbl.textContent = 'Enter a password'; lbl.style.color = '#9ca3af'; }
    else { lbl.textContent = strengthLabels[score-1] || 'Too short'; lbl.style.color = strengthColors[score-1] || '#dc2626'; }
    checkConfirm();
});

function checkConfirm() {
    const p = document.getElementById('password').value;
    const c = document.getElementById('confirm_password').value;
    const msg = document.getElementById('cpwd-msg');
    if (!c) { msg.textContent = ''; return false; }
    if (p === c) { setField('confirm_password','valid');   msg.className='field-msg ok';  msg.innerHTML='✓ Passwords match';        return true; }
    else         { setField('confirm_password','invalid'); msg.className='field-msg err'; msg.innerHTML='✗ Passwords do not match'; return false; }
}
document.getElementById('confirm_password').addEventListener('input', checkConfirm);

document.getElementById('email').addEventListener('blur', function() {
    const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
    const msg = document.getElementById('email-msg');
    if (!this.value) { msg.textContent=''; setField('email',''); return; }
    if (ok) { setField('email','valid');   msg.className='field-msg ok';  msg.innerHTML='✓ Looks good'; }
    else    { setField('email','invalid'); msg.className='field-msg err'; msg.innerHTML='✗ Enter a valid email address'; }
});

function setField(id, state) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('valid','invalid');
    if (state) el.classList.add(state);
}
function showMsg(id, type, text) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'field-msg ' + type;
    el.innerHTML = text;
}
function clearMsg(id) {
    const el = document.getElementById(id);
    if (el) el.textContent = '';
}

// ── Stepper ───────────────────────────────────────────────────
let currentStep = 1;
function nextStep(from) { if (!validateStep(from)) return; goToStep(from + 1); }
function prevStep(from) { goToStep(from - 1); }
function goToStep(to) {
    document.getElementById('step-' + currentStep).classList.remove('active');
    currentStep = to;
    document.getElementById('step-' + to).classList.add('active');
    updateStepper();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function updateStepper() {
    for (let i = 1; i <= 3; i++) {
        const dot = document.getElementById('dot-' + i);
        dot.classList.remove('active','done');
        if (i < currentStep) dot.classList.add('done');
        else if (i === currentStep) dot.classList.add('active');
    }
    document.getElementById('line-1').classList.toggle('done', currentStep > 1);
    document.getElementById('line-2').classList.toggle('done', currentStep > 2);
}
function validateStep(step) {
    let ok = true;
    if (step === 1) {
        const email = document.getElementById('email').value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setField('email','invalid'); showMsg('email-msg','err','✗ Valid email required'); ok=false; }
        if (document.getElementById('password').value.length < 8) { setField('password','invalid'); ok=false; }
        if (!checkConfirm()) ok=false;
    }
    if (step === 2) {
        const fn = document.getElementById('first_name').value.trim();
        const ln = document.getElementById('last_name').value.trim();
        const ph = document.getElementById('phone').value.trim();
        const ct = document.getElementById('city').value.trim();
        if (!fn) { setField('first_name','invalid'); showMsg('fn-msg','err','✗ Required'); ok=false; } else { setField('first_name','valid'); clearMsg('fn-msg'); }
        if (!ln) { setField('last_name','invalid');  showMsg('ln-msg','err','✗ Required'); ok=false; } else { setField('last_name','valid');  clearMsg('ln-msg'); }
        if (!ph||ph.length<7) { setField('phone','invalid'); showMsg('phone-msg','err','✗ Enter a valid phone number'); ok=false; } else { setField('phone','valid'); clearMsg('phone-msg'); }
        if (!ct) { setField('city','invalid'); showMsg('city-msg','err','✗ Required'); ok=false; } else { setField('city','valid'); clearMsg('city-msg'); }
    }
    return ok;
}

// ── Form submit ───────────────────────────────────────────────
document.getElementById('signupForm').addEventListener('submit', function(e) {
    if (!document.getElementById('terms').checked) {
        e.preventDefault();
        showMsg('terms-msg','err','✗ You must accept the Terms & Privacy Policy');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Creating account…';
});
</script>
</body>
</html>