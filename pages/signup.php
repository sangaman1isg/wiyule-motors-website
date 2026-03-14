<?php if (!empty($error)): ?>
<div style="background:#fef2f2;border:1.5px solid #fecaca;color:#dc2626;padding:14px 18px;border-radius:12px;font-size:14px;font-weight:600;margin-bottom:20px">
    ⚠ <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $first_name = trim($_POST['first_name']   ?? '');
    $last_name  = trim($_POST['last_name']    ?? '');
    $email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone      = trim($_POST['phone']        ?? '');
    $city       = trim($_POST['city']         ?? '');
    $type       = trim($_POST['account_type'] ?? 'individual');
    $password   = $_POST['password']          ?? '';

    // Server-side validation
    if (!$first_name || !$last_name || !$email || !$password) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "An account with that email already exists.";
        } else {

            // Hash and insert
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("
                INSERT INTO users (first_name, last_name, email, password, phone, city, account_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssss", $first_name, $last_name, $email, $hash, $phone, $city, $type);
            $stmt->execute();

            $new_id = $conn->insert_id;

            // Write session
            $_SESSION['user_id']    = $new_id;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $_SESSION['user_name']  = $first_name . ' ' . $last_name;
            $_SESSION['email']      = $email;
            $_SESSION['user_email'] = $email;
            $_SESSION['phone']      = $phone;
            $_SESSION['city']       = $city;
            $_SESSION['user_type']  = $type;

            header('Location: /pages/dashboard.php');
            exit;

        } // end: email doesn't exist

    } // end: validation passed

} // end: POST request
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create your Wiyule Motors account — manage bookings, track services, and get exclusive offers in Blantyre, Malawi.">
    <meta name="robots" content="noindex">
    <title>Create Account — Wiyule Motors</title>

    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">

    <style>
        /* ── Base ──────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f9fafb; }

        /* ── Hero panel (left) ─────────────────────────────── */
        .signup-hero {
            background:
                linear-gradient(135deg, rgba(220,38,38,0.92) 0%, rgba(153,27,27,0.97) 100%),
                url('https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?auto=format&fit=crop&w=1200&q=80')
                center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        .signup-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 50%);
            pointer-events: none;
        }
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: gridDrift 20s linear infinite;
        }
        @keyframes gridDrift {
            0%   { transform: translateY(0); }
            100% { transform: translateY(40px); }
        }

        /* ── Progress stepper ──────────────────────────────── */
        .step-dot {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: 2px solid #e5e7eb;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            color: #9ca3af;
            background: #fff;
            transition: all 0.35s ease;
            position: relative; z-index: 1;
        }
        .step-dot.active {
            border-color: #dc2626;
            color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220,38,38,0.12);
        }
        .step-dot.done {
            border-color: #dc2626;
            background: #dc2626;
            color: #fff;
        }
        .step-line {
            flex: 1; height: 2px;
            background: #e5e7eb;
            transition: background 0.45s ease;
            margin: 0 6px;
        }
        .step-line.done { background: #dc2626; }

        /* ── Form panels ───────────────────────────────────── */
        .form-step {
            display: none;
            animation: stepIn 0.4s ease;
        }
        .form-step.active { display: block; }
        @keyframes stepIn {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── Input fields ──────────────────────────────────── */
        .field-wrap {
            position: relative;
            margin-bottom: 20px;
        }
        .field-wrap label {
            display: block;
            font-size: 13px; font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            letter-spacing: 0.02em;
        }
        .field-wrap label .req { color: #dc2626; margin-left: 2px; }
        .field-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            color: #111827;
            background: #fff;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
            outline: none;
        }
        .field-input:focus {
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220,38,38,0.08);
        }
        .field-input.valid   { border-color: #16a34a; }
        .field-input.invalid { border-color: #dc2626; }
        .field-icon {
            position: absolute;
            left: 14px; bottom: 14px;
            width: 20px; height: 20px;
            color: #9ca3af;
            pointer-events: none;
            transition: color 0.25s;
        }
        .field-input:focus ~ .field-icon { color: #dc2626; }
        .field-input.valid   ~ .field-icon { color: #16a34a; }

        .eye-btn {
            position: absolute;
            right: 14px; bottom: 14px;
            background: none; border: none;
            cursor: pointer; padding: 0;
            color: #9ca3af;
            transition: color 0.2s;
        }
        .eye-btn:hover { color: #374151; }

        .field-msg {
            font-size: 12px; font-weight: 500;
            margin-top: 5px;
            min-height: 16px;
            display: flex; align-items: center; gap: 4px;
        }
        .field-msg.ok  { color: #16a34a; }
        .field-msg.err { color: #dc2626; }

        /* ── Password strength ─────────────────────────────── */
        .strength-bar-wrap {
            display: flex; gap: 4px;
            margin-top: 8px;
        }
        .strength-seg {
            flex: 1; height: 4px;
            border-radius: 99px;
            background: #e5e7eb;
            transition: background 0.3s ease;
        }
        .strength-label {
            font-size: 12px; font-weight: 600;
            margin-top: 5px;
            color: #9ca3af;
            transition: color 0.3s;
        }

        /* ── Phone prefix selector ─────────────────────────── */
        .phone-wrap { display: flex; gap: 8px; }
        .phone-prefix {
            width: 110px; flex-shrink: 0;
            padding: 14px 10px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-size: 14px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            background: #fff; color: #374151;
            outline: none;
            transition: border-color 0.25s;
        }
        .phone-prefix:focus { border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,0.08); }
        .phone-wrap .field-input { padding-left: 16px; }

        /* ── Account type cards ────────────────────────────── */
        .account-card {
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px 20px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #fff;
            display: flex; align-items: flex-start; gap: 14px;
        }
        .account-card:hover { border-color: #fca5a5; background: #fff5f5; }
        .account-card.selected {
            border-color: #dc2626;
            background: #fff5f5;
            box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
        }
        .account-card input[type="radio"] { display: none; }
        .account-card-icon {
            width: 44px; height: 44px; flex-shrink: 0;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }

        /* ── Terms checkbox ────────────────────────────────── */
        .custom-cb { display: flex; align-items: flex-start; gap: 12px; cursor: pointer; }
        .custom-cb input[type="checkbox"] { display: none; }
        .cb-box {
            width: 20px; height: 20px; flex-shrink: 0;
            border: 2px solid #d1d5db;
            border-radius: 6px; margin-top: 1px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
            background: #fff;
        }
        .custom-cb input:checked ~ .cb-box {
            background: #dc2626;
            border-color: #dc2626;
        }
        .custom-cb input:checked ~ .cb-box svg { display: block; }
        .cb-box svg { display: none; color: #fff; width: 12px; height: 12px; }

        /* ── Submit button ─────────────────────────────────── */
        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 16px 24px;
            font-size: 16px; font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(220,38,38,0.3);
            letter-spacing: 0.01em;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(220,38,38,0.4);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .btn-ghost {
            width: 100%;
            border: 2px solid #e5e7eb;
            background: transparent;
            color: #374151;
            border-radius: 14px;
            padding: 14px 24px;
            font-size: 15px; font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.25s ease;
        }
        .btn-ghost:hover { border-color: #dc2626; color: #dc2626; background: #fff5f5; }

        /* ── Benefit pills on hero ─────────────────────────── */
        .benefit-pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 99px;
            padding: 8px 16px;
            color: #fff;
            font-size: 13px; font-weight: 500;
            backdrop-filter: blur(4px);
        }

        /* ── Success screen ────────────────────────────────── */
        .success-screen { display: none; }
        .success-screen.active { display: flex; flex-direction: column; align-items: center; }
        .success-ring {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, #16a34a, #15803d);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 0 16px rgba(22,163,74,0.1);
            animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        @keyframes popIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        /* ── Floating orbs ─────────────────────────────────── */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            pointer-events: none;
            animation: orbFloat 6s ease-in-out infinite;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-16px) scale(1.05); }
        }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 1023px) {
            .signup-hero { padding: 48px 24px 32px; }
        }
    </style>
</head>
<body class="antialiased">

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/265993575111"
       class="fixed bottom-6 right-6 bg-green-500 text-white p-4 rounded-full shadow-lg hover:scale-110 transition z-50"
       target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
        <i data-feather="message-circle"></i>
    </a>

    <!-- Navigation -->
    <nav class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-3">
                    <img class="h-12 w-auto"
                         src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQiVbOHrYXKB55eoDs80oh_qeIFhGlcupYTQg&s"
                         alt="Wiyule Motors Logo">
                    <span class="text-xl font-bold text-gray-900">Wiyule Motors</span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-gray-700 hover:text-red-600 font-medium transition">Home</a>
                    <a href="/#services" class="text-gray-700 hover:text-red-600 font-medium transition">Services</a>
                    <a href="/#booking" class="text-gray-700 hover:text-red-600 font-medium transition">Book</a>
                    <a href="/#about" class="text-gray-700 hover:text-red-600 font-medium transition">About</a>
                    <a href="/#contact" class="text-gray-700 hover:text-red-600 font-medium transition">Contact</a>
                    <a href="/pages/login.php" class="px-4 py-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition">Login</a>
                    <a href="/pages/signup.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Sign Up</a>
                </div>
                <div class="md:hidden">
                    <button id="mobileMenuBtn" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 focus:outline-none">☰</button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-4 py-4 space-y-3">
                <a href="/" class="block text-gray-700 hover:text-red-600">Home</a>
                <a href="/#services" class="block text-gray-700 hover:text-red-600">Services</a>
                <a href="/#booking" class="block text-gray-700 hover:text-red-600">Book</a>
                <a href="/#about" class="block text-gray-700 hover:text-red-600">About</a>
                <a href="/#contact" class="block text-gray-700 hover:text-red-600">Contact</a>
                <hr>
                <a href="/pages/login.php" class="block text-center border border-red-600 text-red-600 py-2 rounded-lg">Login</a>
                <a href="/pages/signup.php" class="block text-center bg-red-600 text-white py-2 rounded-lg">Sign Up</a>
            </div>
        </div>
    </nav>

    <!-- Main layout -->
    <main class="min-h-screen flex flex-col lg:flex-row">

        <!-- LEFT — Hero Panel -->
        <div class="signup-hero lg:w-5/12 xl:w-2/5 flex flex-col justify-center px-10 py-16 lg:py-24 lg:min-h-screen relative">
            <div class="hero-grid"></div>
            <div class="orb w-48 h-48 bg-white/10 top-10 -left-10" style="animation-delay:0s"></div>
            <div class="orb w-32 h-32 bg-white/10 bottom-24 right-4" style="animation-delay:2.5s"></div>

            <div class="relative z-10 max-w-sm mx-auto lg:mx-0" data-aos="fade-right">
                <span class="inline-flex items-center gap-2 bg-white/15 border border-white/25 text-white text-xs font-bold px-4 py-2 rounded-full mb-8 backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Trusted since 2016 · Blantyre, Malawi
                </span>

                <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-5">
                    Join the<br>
                    <span class="text-red-200">Wiyule</span> Family
                </h1>

                <p class="text-red-100 text-lg leading-relaxed mb-10">
                    Create your account and unlock a smarter way to manage all your automotive needs — from a single dashboard.
                </p>

                <div class="space-y-3 mb-10">
                    <?php
                    $benefits = [
                        ['icon' => 'calendar', 'text' => 'Book & track services online'],
                        ['icon' => 'bell',     'text' => 'Maintenance reminders & alerts'],
                        ['icon' => 'clock',    'text' => 'View your full service history'],
                        ['icon' => 'tag',      'text' => 'Exclusive member discounts'],
                        ['icon' => 'star',     'text' => 'Priority booking slots'],
                    ];
                    foreach ($benefits as $b): ?>
                    <div class="benefit-pill">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <?= htmlspecialchars($b['text']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center gap-4 mt-8">
                    <div class="flex -space-x-3">
                        <?php $colors = ['bg-blue-500','bg-green-500','bg-purple-500','bg-yellow-500'];
                        $initials = ['JM','TK','PC','SK'];
                        foreach (array_combine($colors, $initials) as $c => $i): ?>
                        <div class="w-10 h-10 rounded-full <?= $c ?> border-2 border-white/30 flex items-center justify-center text-white text-xs font-bold"><?= $i ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <p class="text-white font-bold text-sm">1,000+ members</p>
                        <div class="flex items-center gap-1">
                            <?php for ($i=0;$i<5;$i++): ?>
                            <svg class="w-3.5 h-3.5 text-yellow-300 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                            <span class="text-red-200 text-xs ml-1">5.0 rating</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT — Form Panel -->
        <div class="lg:w-7/12 xl:w-3/5 flex flex-col justify-center px-6 sm:px-10 py-12 bg-gray-50">
            <div class="max-w-xl mx-auto w-full">

                <p class="text-right text-sm text-gray-500 mb-6">
                    Already have an account?
                    <a href="/pages/login.php" class="text-red-600 font-semibold hover:underline">Sign in</a>
                </p>

                <div class="mb-8" data-aos="fade-up">
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-1">Create your account</h2>
                    <p class="text-gray-500 text-sm">Fill in the details below — it only takes 2 minutes.</p>
                </div>

                <!-- Step Progress -->
                <div class="flex items-center mb-10" data-aos="fade-up" data-aos-delay="80">
                    <div class="flex flex-col items-center">
                        <div class="step-dot active" id="dot-1">1</div>
                        <span class="text-xs font-600 text-gray-500 mt-1.5 text-center w-16" id="label-1">Account</span>
                    </div>
                    <div class="step-line" id="line-1"></div>
                    <div class="flex flex-col items-center">
                        <div class="step-dot" id="dot-2">2</div>
                        <span class="text-xs text-gray-400 mt-1.5 text-center w-16" id="label-2">Personal</span>
                    </div>
                    <div class="step-line" id="line-2"></div>
                    <div class="flex flex-col items-center">
                        <div class="step-dot" id="dot-3">3</div>
                        <span class="text-xs text-gray-400 mt-1.5 text-center w-16" id="label-3">Vehicle</span>
                    </div>
                </div>

                <!-- ── CHANGE 2: Added method="POST" to the form tag ── -->
                <form id="signupForm" method="POST" action="" novalidate data-aos="fade-up" data-aos-delay="120">

                    <!-- STEP 1 — Account Details -->
                    <div class="form-step active" id="step-1">
                        <h3 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <span class="w-7 h-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-center text-sm font-bold">1</span>
                            Account Details
                        </h3>

                        <div class="field-wrap">
                            <label for="email">Email Address <span class="req">*</span></label>
                            <input type="email" id="email" name="email" class="field-input" placeholder="john@example.com" autocomplete="email">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                            <p class="field-msg" id="email-msg"></p>
                        </div>

                        <div class="field-wrap">
                            <label for="password">Password <span class="req">*</span></label>
                            <input type="password" id="password" name="password" class="field-input" placeholder="Min. 8 characters" autocomplete="new-password">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <button type="button" class="eye-btn" id="pwd-toggle" aria-label="Toggle password">
                                <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
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
                            <input type="password" id="confirm_password" name="confirm_password" class="field-input" placeholder="Re-enter password" autocomplete="new-password">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <button type="button" class="eye-btn" id="cpwd-toggle" aria-label="Toggle confirm password">
                                <svg id="ceye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="ceye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
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
                            <span class="w-7 h-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-center text-sm font-bold">2</span>
                            Personal Information
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-0">
                            <div class="field-wrap">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="field-input" placeholder="Daniel" autocomplete="given-name">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <p class="field-msg" id="fn-msg"></p>
                            </div>
                            <div class="field-wrap">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="field-input" placeholder="Kumwenda" autocomplete="family-name">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
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
                                <input type="tel" id="phone" name="phone" class="field-input" placeholder="993 575 111" autocomplete="tel">
                            </div>
                            <p class="field-msg" id="phone-msg"></p>
                        </div>

                        <div class="field-wrap">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="field-input" autocomplete="bday">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>

                        <div class="field-wrap">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender" class="field-input" style="padding-left:46px">
                                <option value="">Prefer not to say</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        </div>

                        <div class="field-wrap">
                            <label for="city">City / Town <span class="req">*</span></label>
                            <input type="text" id="city" name="city" class="field-input" placeholder="Blantyre" autocomplete="address-level2">
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <p class="field-msg" id="city-msg"></p>
                        </div>

                        <div class="field-wrap">
                            <label for="referral">How did you hear about us?</label>
                            <select id="referral" name="referral" class="field-input" style="padding-left:46px">
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
                        <p class="text-sm text-gray-500 mb-6">Optional — helps us serve you better and personalise your dashboard.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-0">
                            <div class="field-wrap">
                                <label for="v_make">Vehicle Make</label>
                                <input type="text" id="v_make" name="v_make" class="field-input" placeholder="Toyota">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            </div>
                            <div class="field-wrap">
                                <label for="v_model">Vehicle Model</label>
                                <input type="text" id="v_model" name="v_model" class="field-input" placeholder="Corolla">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-0">
                            <div class="field-wrap">
                                <label for="v_year">Year</label>
                                <input type="number" id="v_year" name="v_year" class="field-input" placeholder="2020" min="1980" max="2026">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="field-wrap">
                                <label for="v_reg">Registration No.</label>
                                <input type="text" id="v_reg" name="v_reg" class="field-input" placeholder="BT 1234">
                                <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                        </div>

                        <div class="field-wrap">
                            <label for="pref_service">Primary Service Interest</label>
                            <select id="pref_service" name="pref_service" class="field-input" style="padding-left:46px">
                                <option value="">Select service</option>
                                <option value="maintenance">General Maintenance</option>
                                <option value="engine">Engine Repair</option>
                                <option value="detailing">Auto Detailing</option>
                                <option value="brakes">Brake Services</option>
                                <option value="ac">AC Repair</option>
                                <option value="parts">Parts Supply</option>
                                <option value="tyres">Tyre Services</option>
                            </select>
                            <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Notification Preferences</label>
                            <div class="space-y-2">
                                <?php
                                $notifs = [
                                    ['id'=>'notif_sms','label'=>'SMS reminders & booking confirmations','checked'=>true],
                                    ['id'=>'notif_email','label'=>'Email updates and service reports','checked'=>true],
                                    ['id'=>'notif_promo','label'=>'Promotions and special offers','checked'=>false],
                                ];
                                foreach ($notifs as $n): ?>
                                <label class="custom-cb">
                                    <input type="checkbox" name="<?= $n['id'] ?>" id="<?= $n['id'] ?>" <?= $n['checked'] ? 'checked' : '' ?>>
                                    <div class="cb-box">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </div>
                                    <span class="text-sm text-gray-700"><?= htmlspecialchars($n['label']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-6 p-4 bg-gray-100 rounded-2xl">
                            <label class="custom-cb">
                                <input type="checkbox" id="terms" name="terms" required>
                                <div class="cb-box">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <span class="text-sm text-gray-700">
                                    I agree to Wiyule Motors'
                                    <a href="/terms" class="text-red-600 font-semibold hover:underline">Terms of Service</a>
                                    and
                                    <a href="/privacy" class="text-red-600 font-semibold hover:underline">Privacy Policy</a>.
                                    I understand my data will be used to manage my account and service bookings.
                                </span>
                            </label>
                            <p class="field-msg" id="terms-msg" style="margin-top:8px;margin-left:32px"></p>
                        </div>

                        <div class="flex gap-3">
                            <button type="button" class="btn-ghost" onclick="prevStep(3)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                Back
                            </button>
                            <!-- CHANGE 3: type="submit" — now actually POSTs the form -->
                            <button type="submit" class="btn-primary" id="submitBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Create My Account
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Divider + Social signup -->
                <div class="mt-8 text-center" id="socialSection">
                    <div class="flex items-center gap-4 mb-5">
                        <div class="flex-1 h-px bg-gray-200"></div>
                        <span class="text-xs text-gray-400 font-medium">OR CONTINUE WITH</span>
                        <div class="flex-1 h-px bg-gray-200"></div>
                    </div>
                    <div class="flex gap-3 justify-center">
                        <button type="button" onclick="socialSignup('google')"
                                class="flex items-center gap-2 px-5 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:border-red-300 hover:bg-red-50 transition-all duration-200">
                            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#EA4335" d="M5.27 9.77A7.24 7.24 0 0 1 12 4.8c1.7 0 3.27.62 4.49 1.63l3.36-3.36A12 12 0 0 0 0 12a12 12 0 0 0 .69 4.01l4.58-3.56a7.25 7.25 0 0 1 0-2.68z"/><path fill="#FBBC05" d="M12 24a12 12 0 0 0 8.32-3.33l-4.04-3.13A7.2 7.2 0 0 1 12 19.2a7.24 7.24 0 0 1-6.73-4.79L.69 18.01A12 12 0 0 0 12 24z"/><path fill="#4285F4" d="M23.76 12.27c0-.83-.07-1.63-.21-2.4H12v4.55h6.61a5.65 5.65 0 0 1-2.45 3.71l4.04 3.13C22.53 19.28 23.76 16 23.76 12.27z"/><path fill="#34A853" d="M5.27 14.41A7.25 7.25 0 0 1 4.8 12c0-.84.15-1.66.47-2.41L.69 5.99A12 12 0 0 0 0 12c0 2.11.54 4.1 1.5 5.83l3.77-3.42z"/></svg>
                            Google
                        </button>
                        <button type="button" onclick="socialSignup('facebook')"
                                class="flex items-center gap-2 px-5 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:border-blue-300 hover:bg-blue-50 transition-all duration-200">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                            Facebook
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-6">
                        By signing up you agree to our
                        <a href="/terms" class="text-red-600 hover:underline">Terms</a> &
                        <a href="/privacy" class="text-red-600 hover:underline">Privacy Policy</a>.
                    </p>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-6 text-center text-sm">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-2">
            <span>© <?= date('Y') ?> Wiyule Motors. All rights reserved.</span>
            <div class="flex gap-5">
                <a href="/terms"   class="hover:text-white transition">Terms</a>
                <a href="/privacy" class="hover:text-white transition">Privacy</a>
                <a href="/#contact" class="hover:text-white transition">Contact</a>
            </div>
        </div>
    </footer>

    <script>
    AOS.init({ duration: 600, once: true, offset: 60 });
    feather.replace();

    document.getElementById('mobileMenuBtn').addEventListener('click', () => {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });

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
            document.getElementById(openId).style.display  =  isText ? '' : 'none';
            document.getElementById(closedId).style.display = isText ? 'none' : '';
        });
    }
    makeEyeToggle('pwd-toggle',  'password',         'eye-open',  'eye-closed');
    makeEyeToggle('cpwd-toggle', 'confirm_password', 'ceye-open', 'ceye-closed');

    const strengthColors = ['#dc2626','#f59e0b','#3b82f6','#16a34a'];
    const strengthLabels = ['Weak','Fair','Good','Strong'];
    document.getElementById('password').addEventListener('input', function() {
        const v = this.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (/[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        for (let i = 1; i <= 4; i++) {
            const seg = document.getElementById('s' + i);
            seg.style.background = i <= score ? strengthColors[score - 1] : '#e5e7eb';
        }
        const lbl = document.getElementById('strength-label');
        if (v.length === 0) { lbl.textContent = 'Enter a password'; lbl.style.color = '#9ca3af'; }
        else { lbl.textContent = strengthLabels[score - 1] || 'Too short'; lbl.style.color = strengthColors[score - 1] || '#dc2626'; }
        checkConfirm();
    });

    function checkConfirm() {
        const p = document.getElementById('password').value;
        const c = document.getElementById('confirm_password').value;
        const msg = document.getElementById('cpwd-msg');
        if (!c) { msg.textContent = ''; return false; }
        if (p === c) { setField('confirm_password','valid'); msg.className='field-msg ok'; msg.innerHTML='✓ Passwords match'; return true; }
        else { setField('confirm_password','invalid'); msg.className='field-msg err'; msg.innerHTML='✗ Passwords do not match'; return false; }
    }
    document.getElementById('confirm_password').addEventListener('input', checkConfirm);

    document.getElementById('email').addEventListener('blur', function() {
        const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
        const msg = document.getElementById('email-msg');
        if (!this.value) { msg.textContent = ''; setField('email',''); return; }
        if (ok) { setField('email','valid'); msg.className='field-msg ok'; msg.innerHTML='✓ Looks good'; }
        else { setField('email','invalid'); msg.className='field-msg err'; msg.innerHTML='✗ Enter a valid email address'; }
    });

    function setField(id, state) {
        const el = document.getElementById(id);
        el.classList.remove('valid','invalid');
        if (state) el.classList.add(state);
    }
    function showMsg(id, type, text) { const el = document.getElementById(id); el.className='field-msg '+type; el.innerHTML=text; }
    function clearMsg(id) { document.getElementById(id).textContent = ''; }

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

    // ── CHANGE 3: JS submit now just validates terms then lets the real POST go through ──
    document.getElementById('signupForm').addEventListener('submit', function(e) {
        if (!document.getElementById('terms').checked) {
            e.preventDefault(); // only block if terms not accepted
            showMsg('terms-msg','err','✗ You must accept the Terms & Privacy Policy');
            return;
        }
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Creating account…';
        // Form submits normally to PHP handler above
    });

    function socialSignup(provider) {
        alert('Social sign-up with ' + provider + ' coming soon!');
    }
    </script>
</body>
</html>