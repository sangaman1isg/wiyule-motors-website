<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign in to your Wiyule Motors account — manage bookings, track services and more.">
    <meta name="robots" content="noindex">
    <title>Sign In — Wiyule Motors</title>

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
        .login-hero {
            background:
                linear-gradient(135deg, rgba(220,38,38,0.94) 0%, rgba(127,29,29,0.98) 100%),
                url('https://images.unsplash.com/photo-1619642751034-765dfdf7c58e?auto=format&fit=crop&w=1200&q=80')
                center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        .login-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 75%, rgba(255,255,255,0.07) 0%, transparent 55%),
                radial-gradient(circle at 85% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Animated grid overlay */
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 44px 44px;
            animation: gridDrift 22s linear infinite;
        }
        @keyframes gridDrift {
            from { transform: translateY(0); }
            to   { transform: translateY(44px); }
        }

        /* Floating orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(50px);
            pointer-events: none;
            animation: orbFloat 7s ease-in-out infinite;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-18px) scale(1.06); }
        }

        /* ── Input fields ──────────────────────────────────── */
        .field-wrap {
            position: relative;
            margin-bottom: 20px;
        }
        .field-wrap label {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            left: 14px; bottom: 15px;
            width: 20px; height: 20px;
            color: #9ca3af;
            pointer-events: none;
            transition: color 0.25s;
        }
        .field-input:focus ~ .field-icon  { color: #dc2626; }
        .field-input.valid  ~ .field-icon { color: #16a34a; }

        /* Eye toggle */
        .eye-btn {
            position: absolute;
            right: 14px; bottom: 14px;
            background: none; border: none;
            cursor: pointer; padding: 0;
            color: #9ca3af;
            transition: color 0.2s;
        }
        .eye-btn:hover { color: #374151; }

        /* Validation message */
        .field-msg {
            font-size: 12px; font-weight: 500;
            margin-top: 5px;
            min-height: 16px;
            display: flex; align-items: center; gap: 4px;
        }
        .field-msg.ok  { color: #16a34a; }
        .field-msg.err { color: #dc2626; }

        /* ── Remember me checkbox ──────────────────────────── */
        .custom-cb { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .custom-cb input[type="checkbox"] { display: none; }
        .cb-box {
            width: 20px; height: 20px; flex-shrink: 0;
            border: 2px solid #d1d5db;
            border-radius: 6px;
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

        /* ── Primary button ────────────────────────────────── */
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
        .btn-primary:active  { transform: translateY(0); }
        .btn-primary:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Alert banner ──────────────────────────────────── */
        .alert {
            display: none;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px; font-weight: 500;
            margin-bottom: 20px;
            align-items: center; gap: 10px;
        }
        .alert.show { display: flex; }
        .alert.err  { background: #fef2f2; border: 1.5px solid #fecaca; color: #dc2626; }
        .alert.ok   { background: #f0fdf4; border: 1.5px solid #bbf7d0; color: #15803d; }

        /* ── Stat cards on hero ────────────────────────────── */
        .stat-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 18px;
            padding: 18px 22px;
            backdrop-filter: blur(6px);
            flex: 1;
        }

        /* ── Service list ──────────────────────────────────── */
        .service-pill {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 12px;
            padding: 10px 14px;
            backdrop-filter: blur(4px);
            transition: background 0.2s;
        }
        .service-pill:hover { background: rgba(255,255,255,0.16); }

        /* ── Shake animation for wrong creds ───────────────── */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%       { transform: translateX(-8px); }
            40%       { transform: translateX(8px); }
            60%       { transform: translateX(-5px); }
            80%       { transform: translateX(5px); }
        }
        .shake { animation: shake 0.45s ease; }

        /* ── Forgot password modal ─────────────────────────── */
        .fp-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 100;
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .fp-overlay.active { display: flex; }
        .fp-modal {
            background: #fff;
            border-radius: 24px;
            padding: 40px 36px;
            max-width: 420px; width: 100%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.2);
            animation: modalPop 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        @keyframes modalPop {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }

        /* ── Spin for loading ──────────────────────────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .animate-spin { animation: spin 0.9s linear infinite; }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 1023px) {
            .login-hero { padding: 48px 24px 36px; }
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

    <!-- ── Navigation (identical to index.php) ── -->
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
                    <a href="/"           class="text-gray-700 hover:text-red-600 font-medium transition">Home</a>
                    <a href="/#services"  class="text-gray-700 hover:text-red-600 font-medium transition">Services</a>
                    <a href="/#booking"   class="text-gray-700 hover:text-red-600 font-medium transition">Book</a>
                    <a href="/#about"     class="text-gray-700 hover:text-red-600 font-medium transition">About</a>
                    <a href="/#contact"   class="text-gray-700 hover:text-red-600 font-medium transition">Contact</a>
                    <a href="/pages/login.php"
                       class="px-4 py-2 border border-red-600 text-red-600 rounded-lg hover:bg-red-50 transition">Login</a>
                    <a href="/pages/signup.php"
                       class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Sign Up</a>
                </div>
                <div class="md:hidden">
                    <button id="mobileMenuBtn" class="p-2 rounded-md text-gray-600 hover:bg-gray-100 focus:outline-none">☰</button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-4 py-4 space-y-3">
                <a href="/"          class="block text-gray-700 hover:text-red-600">Home</a>
                <a href="/#services" class="block text-gray-700 hover:text-red-600">Services</a>
                <a href="/#booking"  class="block text-gray-700 hover:text-red-600">Book</a>
                <a href="/#about"    class="block text-gray-700 hover:text-red-600">About</a>
                <a href="/#contact"  class="block text-gray-700 hover:text-red-600">Contact</a>
                <hr>
                <a href="/pages/login.php"  class="block text-center border border-red-600 text-red-600 py-2 rounded-lg">Login</a>
                <a href="/pages/signup.php" class="block text-center bg-red-600 text-white py-2 rounded-lg">Sign Up</a>
            </div>
        </div>
    </nav>

    <!-- ─────────────────────────────── Main layout ── -->
    <main class="min-h-screen flex flex-col lg:flex-row">

        <!-- LEFT — Hero Panel -->
        <div class="login-hero lg:w-5/12 xl:w-2/5 flex flex-col justify-center px-10 py-16 lg:py-24 lg:min-h-screen relative">
            <div class="hero-grid"></div>

            <!-- Floating orbs -->
            <div class="orb w-56 h-56 bg-white/10 -top-10 -right-16" style="animation-delay:0s"></div>
            <div class="orb w-36 h-36 bg-white/10  bottom-16 -left-8" style="animation-delay:3s"></div>

            <div class="relative z-10 max-w-sm mx-auto lg:mx-0" data-aos="fade-right">

                <!-- Welcome back badge -->
                <span class="inline-flex items-center gap-2 bg-white/15 border border-white/25 text-white text-xs font-bold px-4 py-2 rounded-full mb-8 backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Secure Member Portal · Wiyule Motors
                </span>

                <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight mb-5">
                    Welcome<br>
                    <span class="text-red-200">Back</span> 👋
                </h1>

                <p class="text-red-100 text-lg leading-relaxed mb-10">
                    Sign in to your account to manage bookings, track your vehicle's service history, and access exclusive member benefits.
                </p>

                <!-- Quick-access stats -->
                <div class="flex gap-3 mb-10">
                    <div class="stat-card text-center">
                        <p class="text-2xl font-extrabold text-white">1K+</p>
                        <p class="text-xs text-red-200 mt-0.5 font-medium">Active Members</p>
                    </div>
                    <div class="stat-card text-center">
                        <p class="text-2xl font-extrabold text-white">5★</p>
                        <p class="text-xs text-red-200 mt-0.5 font-medium">Avg. Rating</p>
                    </div>
                    <div class="stat-card text-center">
                        <p class="text-2xl font-extrabold text-white">9yr</p>
                        <p class="text-xs text-red-200 mt-0.5 font-medium">Trusted Service</p>
                    </div>
                </div>

                <!-- Member dashboard preview list -->
                <p class="text-xs text-red-300 font-semibold uppercase tracking-widest mb-4">Your dashboard includes</p>
                <div class="space-y-2.5">
                    <?php
                    $features = [
                        ['icon'=>'calendar',  'text'=>'View & manage all your bookings'],
                        ['icon'=>'clock',     'text'=>'Full vehicle service history'],
                        ['icon'=>'bell',      'text'=>'Maintenance reminders'],
                        ['icon'=>'tag',       'text'=>'Member-only discounts'],
                        ['icon'=>'settings',  'text'=>'Vehicle & account settings'],
                    ];
                    foreach ($features as $f): ?>
                    <div class="service-pill">
                        <div class="w-7 h-7 bg-white/15 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i data-feather="<?= $f['icon'] ?>" class="w-3.5 h-3.5 text-white"></i>
                        </div>
                        <span class="text-white text-sm font-medium"><?= htmlspecialchars($f['text']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Social proof avatars -->
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

                <!-- Don't have account -->
                <p class="text-right text-sm text-gray-500 mb-6">
                    Don't have an account?
                    <a href="/pages/signup.php" class="text-red-600 font-semibold hover:underline">Sign up free</a>
                </p>

                <!-- Header -->
                <div class="mb-8" data-aos="fade-up">
                    <div class="w-14 h-14 bg-gradient-to-br from-red-600 to-red-700 rounded-2xl flex items-center justify-center shadow-lg mb-5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    </div>
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-1">Sign in to your account</h2>
                    <p class="text-gray-500 text-sm">Enter your credentials to access your dashboard.</p>
                </div>

                <!-- ── Alert Banner ── -->
                <div class="alert" id="alertBanner" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span id="alertText"></span>
                </div>

                <!-- ── Login Form ── -->
                <form id="loginForm" novalidate data-aos="fade-up" data-aos-delay="100">

                    <!-- Email / Username -->
                    <div class="field-wrap">
                        <label for="email">
                            Email Address <span class="req">*</span>
                        </label>
                        <input type="email" id="email" name="email"
                               class="field-input"
                               placeholder="john@example.com"
                               autocomplete="email">
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                        <p class="field-msg" id="email-msg"></p>
                    </div>

                    <!-- Password -->
                    <div class="field-wrap">
                        <label for="password">
                            Password <span class="req">*</span>
                            <a href="#" class="text-red-600 text-xs font-semibold hover:underline" id="forgotLink">Forgot password?</a>
                        </label>
                        <input type="password" id="password" name="password"
                               class="field-input"
                               placeholder="Your password"
                               autocomplete="current-password">
                        <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <button type="button" class="eye-btn" id="pwd-toggle" aria-label="Toggle password">
                            <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                        <p class="field-msg" id="pwd-msg"></p>
                    </div>

                    <!-- Remember me + Forgot (mobile) -->
                    <div class="flex items-center justify-between mb-6">
                        <label class="custom-cb">
                            <input type="checkbox" id="remember" name="remember">
                            <div class="cb-box">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <span class="text-sm text-gray-600 font-medium">Remember me for 30 days</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign In
                    </button>

                    <!-- Failed attempts hint -->
                    <p class="text-xs text-center text-gray-400 mt-4" id="attemptsHint" style="display:none">
                        Too many failed attempts? <a href="#" class="text-red-600 font-semibold" id="resetLink2">Reset your password</a>
                    </p>

                </form>

                <!-- ── Divider ── -->
                <div class="flex items-center gap-4 mt-8 mb-5">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs text-gray-400 font-medium">OR CONTINUE WITH</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <!-- ── Social login ── -->
                <div class="flex gap-3 justify-center mb-8">
                    <button type="button" onclick="socialLogin('google')"
                            class="flex items-center gap-2 px-5 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:border-red-300 hover:bg-red-50 transition-all duration-200">
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <path fill="#EA4335" d="M5.27 9.77A7.24 7.24 0 0 1 12 4.8c1.7 0 3.27.62 4.49 1.63l3.36-3.36A12 12 0 0 0 0 12a12 12 0 0 0 .69 4.01l4.58-3.56a7.25 7.25 0 0 1 0-2.68z"/>
                            <path fill="#FBBC05" d="M12 24a12 12 0 0 0 8.32-3.33l-4.04-3.13A7.2 7.2 0 0 1 12 19.2a7.24 7.24 0 0 1-6.73-4.79L.69 18.01A12 12 0 0 0 12 24z"/>
                            <path fill="#4285F4" d="M23.76 12.27c0-.83-.07-1.63-.21-2.4H12v4.55h6.61a5.65 5.65 0 0 1-2.45 3.71l4.04 3.13C22.53 19.28 23.76 16 23.76 12.27z"/>
                            <path fill="#34A853" d="M5.27 14.41A7.25 7.25 0 0 1 4.8 12c0-.84.15-1.66.47-2.41L.69 5.99A12 12 0 0 0 0 12c0 2.11.54 4.1 1.5 5.83l3.77-3.42z"/>
                        </svg>
                        Google
                    </button>
                    <button type="button" onclick="socialLogin('facebook')"
                            class="flex items-center gap-2 px-5 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:border-blue-300 hover:bg-blue-50 transition-all duration-200">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2">
                            <path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/>
                        </svg>
                        Facebook
                    </button>
                    <button type="button" onclick="socialLogin('whatsapp')"
                            class="flex items-center gap-2 px-5 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:border-green-300 hover:bg-green-50 transition-all duration-200">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#25D366">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        WhatsApp
                    </button>
                </div>

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
                    <a href="/pages/signup.php"
                       class="inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-3 rounded-xl font-bold text-sm shadow-md hover:shadow-lg hover:-translate-y-0.5 transform transition-all duration-200">
                        Create Free Account
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                </div>

                <p class="text-xs text-center text-gray-400 mt-6">
                    Protected by industry-standard encryption.
                    <a href="/privacy" class="text-red-600 hover:underline">Privacy Policy</a>
                </p>

            </div>
        </div><!-- /right panel -->
    </main>

    <!-- ─── Footer strip ─── -->
    <footer class="bg-gray-900 text-gray-400 py-6 text-center text-sm">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-2">
            <span>© <?= date('Y') ?> Wiyule Motors. All rights reserved.</span>
            <div class="flex gap-5">
                <a href="/terms"    class="hover:text-white transition">Terms</a>
                <a href="/privacy"  class="hover:text-white transition">Privacy</a>
                <a href="/#contact" class="hover:text-white transition">Contact</a>
            </div>
        </div>
    </footer>

    <!-- ═══ Forgot Password Modal ═══ -->
    <div class="fp-overlay" id="fpOverlay">
        <div class="fp-modal">
            <!-- Default: enter email -->
            <div id="fp-email-view">
                <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3 class="text-xl font-extrabold text-gray-900 mb-1">Reset your password</h3>
                <p class="text-gray-500 text-sm mb-6">Enter your registered email and we'll send you a reset link.</p>

                <div class="field-wrap">
                    <label for="fp-email">Email Address <span class="req">*</span></label>
                    <input type="email" id="fp-email" class="field-input" placeholder="john@example.com">
                    <svg class="field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                    <p class="field-msg" id="fp-email-msg"></p>
                </div>

                <div class="flex gap-3 mt-6">
                    <button type="button" onclick="closeFP()"
                            class="flex-1 py-3 border-2 border-gray-200 rounded-xl text-sm font-semibold text-gray-600 hover:border-red-300 hover:text-red-600 transition">
                        Cancel
                    </button>
                    <button type="button" onclick="submitReset()"
                            class="flex-1 btn-primary" style="padding:12px 20px;font-size:14px" id="fpSubmitBtn">
                        Send Reset Link
                    </button>
                </div>
            </div>

            <!-- Success view -->
            <div id="fp-success-view" style="display:none;text-align:center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h3 class="text-xl font-extrabold text-gray-900 mb-2">Check your inbox!</h3>
                <p class="text-gray-500 text-sm mb-6">We've sent a password reset link to <strong id="fp-sent-email" class="text-gray-800"></strong>. Check your spam folder if you don't see it.</p>
                <button type="button" onclick="closeFP()"
                        class="btn-primary" style="padding:13px 24px;font-size:14px">
                    Back to Sign In
                </button>
            </div>
        </div>
    </div>

    <!-- ───────────────── Scripts ───────────────── -->
    <script>
    AOS.init({ duration: 600, once: true, offset: 60 });
    feather.replace();

    // ── Mobile nav ──
    document.getElementById('mobileMenuBtn').addEventListener('click', () => {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });

    // ── Eye toggle ──
    document.getElementById('pwd-toggle').addEventListener('click', function() {
        const input = document.getElementById('password');
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        document.getElementById('eye-open').style.display   =  isText ? '' : 'none';
        document.getElementById('eye-closed').style.display = isText ? 'none' : '';
    });

    // ── Helpers ──
    function setField(id, state) {
        const el = document.getElementById(id);
        el.classList.remove('valid','invalid');
        if (state) el.classList.add(state);
    }
    function showMsg(id, type, text) {
        const el = document.getElementById(id);
        el.className = 'field-msg ' + type;
        el.innerHTML = text;
    }
    function showAlert(type, text) {
        const b = document.getElementById('alertBanner');
        b.className = 'alert show ' + type;
        document.getElementById('alertText').textContent = text;
    }
    function hideAlert() {
        document.getElementById('alertBanner').className = 'alert';
    }

    // ── Inline validation ──
    document.getElementById('email').addEventListener('blur', function() {
        const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
        if (!this.value) { setField('email',''); showMsg('email-msg','',''); return; }
        if (ok) { setField('email','valid'); showMsg('email-msg','ok','✓ Looks good'); }
        else    { setField('email','invalid'); showMsg('email-msg','err','✗ Enter a valid email address'); }
    });

    document.getElementById('password').addEventListener('input', function() {
        if (!this.value) { setField('password',''); showMsg('pwd-msg','',''); return; }
        if (this.value.length < 6) { setField('password','invalid'); showMsg('pwd-msg','err','✗ Password too short'); }
        else { setField('password','valid'); showMsg('pwd-msg','ok','✓ Good'); }
    });

    // ── Failed attempts tracking ──
    let failCount = 0;

    // ── Form submit ──
    document.getElementById('loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        hideAlert();

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
        if (!ok) return;

        // Loading state
        const btn = document.getElementById('submitBtn');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Signing in…`;

        await new Promise(r => setTimeout(r, 1600));

        // ── Simulate auth (replace with real fetch to your PHP backend) ──
        // const res = await fetch('/api/login.php', { method:'POST', body: new FormData(this) });
        // const data = await res.json();
        // Simulated: wrong password demo
        const simulateSuccess = email.toLowerCase().includes('@') && password.length >= 8;

        btn.disabled = false;
        btn.innerHTML = originalHTML;

        if (simulateSuccess) {
            btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Signed in! Redirecting…`;
            btn.style.background = 'linear-gradient(135deg,#16a34a,#15803d)';
            showAlert('ok', '✓ Welcome back! Redirecting to your dashboard…');
            setTimeout(() => { window.location.href = '/dashboard'; }, 1800);
        } else {
            failCount++;
            // Shake the form card
            const form = document.getElementById('loginForm');
            form.classList.remove('shake');
            void form.offsetWidth; // reflow
            form.classList.add('shake');
            setTimeout(() => form.classList.remove('shake'), 500);

            setField('email','invalid');
            setField('password','invalid');
            showAlert('err', `Incorrect email or password. Please try again. (${failCount} failed attempt${failCount > 1 ? 's' : ''})`);

            if (failCount >= 3) {
                document.getElementById('attemptsHint').style.display = 'block';
            }
        }
    });

    // ── Forgot password modal ──
    function openFP() {
        document.getElementById('fpOverlay').classList.add('active');
        // Pre-fill with whatever is in the login email field
        const loginEmail = document.getElementById('email').value;
        if (loginEmail) document.getElementById('fp-email').value = loginEmail;
        feather.replace();
    }
    function closeFP() {
        document.getElementById('fpOverlay').classList.remove('active');
        // Reset modal back to email view
        document.getElementById('fp-email-view').style.display = '';
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
        const btn = document.getElementById('fpSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Sending…`;

        await new Promise(r => setTimeout(r, 1400));

        document.getElementById('fp-sent-email').textContent = email;
        document.getElementById('fp-email-view').style.display  = 'none';
        document.getElementById('fp-success-view').style.display = '';
        btn.disabled = false;
        btn.innerHTML = 'Send Reset Link';
    }

    // Wire up forgot password links
    document.getElementById('forgotLink').addEventListener('click', e => { e.preventDefault(); openFP(); });
    document.getElementById('resetLink2') && document.getElementById('resetLink2').addEventListener('click', e => { e.preventDefault(); openFP(); });

    // Close overlay on backdrop click
    document.getElementById('fpOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeFP();
    });

    // ── Social login placeholder ──
    function socialLogin(provider) {
        alert('Social sign-in with ' + provider + ' coming soon!');
    }
    </script>
</body>
</html>