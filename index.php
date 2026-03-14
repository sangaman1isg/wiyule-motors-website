<?php
// ── Session & auth — MUST be at the top before any HTML output ──
session_start();
$is_logged_in = isset($_SESSION['user_id']);
// Dev preview: add ?preview=1 to URL to see the logged-in nav
if (isset($_GET['preview'])) $is_logged_in = true;
$nav_user = $is_logged_in ? [
    'first_name'      => $_SESSION['first_name']    ?? 'Daniel',
    'last_name'       => $_SESSION['last_name']     ?? 'Kumwenda',
    'avatar_initials' => strtoupper(
                            substr($_SESSION['first_name'] ?? 'D', 0, 1) .
                            substr($_SESSION['last_name']  ?? 'K', 0, 1)
                         ),
    'unread_notifs'   => (int)($_SESSION['unread_notifs'] ?? 2),
] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Wiyule Motors offers premium automotive parts and services in Blantyre, Malawi. Shop high-quality parts and book expert vehicle maintenance today.">
    <meta name="keywords" content="automotive parts, vehicle maintenance, auto detailing, car repair, Wiyule Motors, Blantyre, Malawi, brake services, engine repair, oil change">
    <meta property="og:title" content="Wiyule Motors - Premium Automotive Parts &amp; Services">
    <meta property="og:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta property="og:image" content="https://wiyulemotors.com/images/og-image.jpg">
    <meta property="og:url" content="https://wiyulemotors.com">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Wiyule Motors - Premium Automotive Parts &amp; Services">
    <meta name="twitter:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#dc2626">
    <title>Wiyule Motors - Premium Automotive Parts &amp; Services | Blantyre, Malawi</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <link rel="canonical" href="https://wiyulemotors.com">
    
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HMM03HL39K"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-HMM03HL39K');
    </script> 
    
    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AutoRepair",
        "name": "Wiyule Motors",
        "image": "https://wiyulemotors.com/images/logo.jpg",
        "description": "Premium automotive parts and services in Blantyre, Malawi since 2016.",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Nyambadwe",
            "addressLocality": "Blantyre",
            "addressRegion": "Southern Region",
            "addressCountry": "MW"
        },
        "telephone": "+265993575111",
        "priceRange": "$$",
        "openingHoursSpecification": [
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens": "08:00",
                "closes": "17:00"
            },
            {
                "@type": "OpeningHoursSpecification",
                "dayOfWeek": "Saturday",
                "opens": "08:00",
                "closes": "14:00"
            }
        ],
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "5",
            "reviewCount": "120"
        }
    }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- ── Navbar & dropdown styles ──────────────────────── -->
    <style>
        .nav-link {
            position: relative; font-weight: 500; color: #374151;
            transition: color 0.2s; text-decoration: none;
            font-size: 14px; padding: 4px 0;
        }
        .nav-link::after {
            content: ''; position: absolute; bottom: -2px; left: 0;
            width: 0; height: 2px; background: #dc2626;
            border-radius: 99px; transition: width 0.25s ease;
        }
        .nav-link:hover, .nav-link.active           { color: #dc2626; }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }

        .nav-bell {
            position: relative; width: 40px; height: 40px; border-radius: 12px;
            border: 1.5px solid #e5e7eb; background: #fff;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #6b7280; transition: all 0.2s; text-decoration: none;
        }
        .nav-bell:hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
        .nav-bell-badge {
            position: absolute; top: 6px; right: 6px; width: 9px; height: 9px;
            background: #dc2626; border-radius: 50%; border: 2px solid #fff;
            animation: bellPulse 2.5s ease-in-out infinite;
        }
        @keyframes bellPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,0.4); }
            50%      { box-shadow: 0 0 0 4px rgba(220,38,38,0); }
        }
        .nav-dashboard-btn {
            display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #fff; border-radius: 12px; font-size: 13px; font-weight: 700;
            text-decoration: none; transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(15,23,42,0.25); white-space: nowrap;
        }
        .nav-dashboard-btn:hover {
            background: #dc2626; box-shadow: 0 4px 16px rgba(220,38,38,0.35);
            transform: translateY(-1px);
        }
        .nav-dashboard-btn svg { width: 14px; height: 14px; }

        .nav-avatar-wrap { position: relative; }
        .nav-avatar {
            display: flex; align-items: center; gap: 9px; padding: 5px 10px 5px 5px;
            border-radius: 40px; border: 1.5px solid #e5e7eb; background: #fff;
            cursor: pointer; transition: all 0.2s; user-select: none;
        }
        .nav-avatar:hover { border-color: #dc2626; background: #fef2f2; }
        .nav-avatar-circle {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff; font-size: 12px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .nav-avatar-name  { font-size: 13px; font-weight: 700; color: #1f2937; }
        .nav-avatar-caret { color: #9ca3af; transition: transform 0.2s; }
        .nav-avatar-wrap.open .nav-avatar-caret { transform: rotate(180deg); }
        .nav-avatar-wrap.open .nav-avatar { border-color: #dc2626; }

        .nav-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0; width: 240px;
            background: #fff; border: 1.5px solid #e5e7eb; border-radius: 18px;
            box-shadow: 0 16px 48px rgba(0,0,0,0.14); overflow: hidden;
            opacity: 0; transform: translateY(-8px) scale(0.97); pointer-events: none;
            transition: all 0.22s cubic-bezier(0.34,1.56,0.64,1); z-index: 200;
        }
        .nav-avatar-wrap.open .nav-dropdown {
            opacity: 1; transform: translateY(0) scale(1); pointer-events: all;
        }
        .dd-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            padding: 16px; display: flex; align-items: center; gap: 12px;
        }
        .dd-header-avatar {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff; font-size: 15px; font-weight: 800;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .dd-header-name  { font-size: 14px; font-weight: 800; color: #fff; }
        .dd-header-role  { font-size: 11px; color: #64748b; margin-top: 1px; }
        .dd-items { padding: 8px; }
        .dd-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border-radius: 10px; color: #374151; font-size: 13px; font-weight: 600;
            text-decoration: none; cursor: pointer; transition: all 0.15s;
        }
        .dd-item:hover         { background: #f8fafc; color: #0f172a; }
        .dd-item.danger        { color: #dc2626; }
        .dd-item.danger:hover  { background: #fef2f2; }
        .dd-item-icon {
            width: 32px; height: 32px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .dd-item-icon svg { width: 15px; height: 15px; }
        .dd-badge {
            margin-left: auto; background: #fef2f2; color: #dc2626;
            font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 99px;
        }
        .dd-divider { height: 1px; background: #f1f5f9; margin: 6px 8px; }

        .mob-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border-radius: 12px; color: #374151; font-size: 14px; font-weight: 500;
            text-decoration: none; transition: all 0.15s;
        }
        .mob-item:hover { background: #f8fafc; color: #0f172a; }
        .mob-item svg { width: 16px; height: 16px; flex-shrink: 0; }
        .mob-item.danger       { color: #dc2626; }
        .mob-item.danger:hover { background: #fef2f2; }

        .mobile-user-card {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border-radius: 14px; padding: 14px;
            display: flex; align-items: center; gap: 12px; margin-bottom: 8px;
        }
        .mobile-user-avatar {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff; font-size: 15px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
    </style>

</head>
<body class="font-[Inter] antialiased text-gray-800">
    <!-- WhatsApp Button -->
    <a href="https://wa.me/265993575111" class="fixed bottom-6 right-6 bg-green-500 text-white p-4 rounded-full shadow-lg hover:scale-110 transition z-50" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
        <i data-feather="message-circle"></i>
    </a>

    <!-- Navigation -->
<nav class="bg-white shadow-md sticky top-0 z-50">
    <!-- Thin red accent line at very top -->
    <div style="height:3px;background:linear-gradient(90deg,#dc2626,#b91c1c,#7f1d1d);"></div>

    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-20">

            <!-- Logo -->
            <a href="/" class="flex items-center space-x-3" style="text-decoration:none">
                <img
                    class="h-12 w-auto"
                    src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQiVbOHrYXKB55eoDs80oh_qeIFhGlcupYTQg&s"
                    alt="Wiyule Motors Logo"
                >
                <div>
                    <span class="text-xl font-bold text-gray-900 block leading-tight">Wiyule Motors</span>
                    <span class="text-xs text-gray-400 font-medium" style="letter-spacing:0.04em">Blantyre, Malawi</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <div class="hidden md:flex items-center" style="gap:28px">
                <a href="/"        class="nav-link active">Home</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#booking"  class="nav-link">Book</a>
                <a href="#about"    class="nav-link">About</a>
                <a href="#contact"  class="nav-link">Contact</a>
            </div>

            <!-- Desktop Auth / User area -->
            <div class="hidden md:flex items-center" style="gap:10px">

                <?php if ($is_logged_in && $nav_user): ?>

                    <!-- Notification bell -->
                    <a href="/pages/dashboard.php?panel=notifications" class="nav-bell" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($nav_user['unread_notifs'] > 0): ?>
                        <span class="nav-bell-badge"></span>
                        <?php endif; ?>
                    </a>

                    <!-- Dashboard shortcut -->
                    <a href="/pages/dashboard.php" class="nav-dashboard-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Dashboard
                    </a>

                    <!-- Avatar dropdown -->
                    <div class="nav-avatar-wrap" id="avatarWrap">
                        <div class="nav-avatar" onclick="toggleDropdown()" id="avatarBtn" aria-haspopup="true" aria-expanded="false">
                            <div class="nav-avatar-circle"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                            <span class="nav-avatar-name"><?= htmlspecialchars($nav_user['first_name']) ?></span>
                            <svg class="nav-avatar-caret" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>

                        <!-- Dropdown -->
                        <div class="nav-dropdown" id="navDropdown" role="menu">
                            <!-- Header -->
                            <div class="dd-header">
                                <div class="dd-header-avatar"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                                <div>
                                    <div class="dd-header-name"><?= htmlspecialchars($nav_user['first_name'].' '.$nav_user['last_name']) ?></div>
                                    <div class="dd-header-role">Individual Member</div>
                                </div>
                            </div>

                            <!-- Items -->
                            <div class="dd-items">
                                <a href="/pages/dashboard.php" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f1f5f9;color:#374151">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                                    </div>
                                    Overview
                                </a>
                                <a href="/pages/dashboard.php?panel=bookings" class="dd-item">
                                    <div class="dd-item-icon" style="background:#eff6ff;color:#2563eb">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    My Bookings
                                    <span class="dd-badge">2 upcoming</span>
                                </a>
                                <a href="/pages/dashboard.php?panel=vehicle" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f0fdf4;color:#15803d">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                    </div>
                                    My Vehicle
                                </a>
                                <a href="/pages/dashboard.php?panel=notifications" class="dd-item">
                                    <div class="dd-item-icon" style="background:#fef2f2;color:#dc2626">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                    </div>
                                    Notifications
                                    <?php if($nav_user['unread_notifs'] > 0): ?>
                                    <span class="dd-badge"><?= $nav_user['unread_notifs'] ?> new</span>
                                    <?php endif; ?>
                                </a>
                                <a href="/pages/dashboard.php?panel=settings" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f8fafc;color:#64748b">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                    </div>
                                    Settings
                                </a>

                                <div class="dd-divider"></div>

                                <a href="/pages/logout.php" class="dd-item danger">
                                    <div class="dd-item-icon" style="background:#fef2f2;color:#dc2626">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                    </div>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- Guest state: Login + Sign Up -->
                    <a href="/pages/login.php"
                       style="padding:8px 18px;border:1.5px solid #e5e7eb;color:#374151;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px"
                       onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626';this.style.background='#fef2f2'"
                       onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151';this.style.background='transparent'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign In
                    </a>

                    <a href="/pages/signup.php"
                       style="padding:8px 18px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border-radius:12px;font-size:13px;font-weight:700;text-decoration:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 10px rgba(220,38,38,0.3)"
                       onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(220,38,38,0.4)'"
                       onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 10px rgba(220,38,38,0.3)'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Create Account
                    </a>

                <?php endif; ?>
            </div>

            <!-- Mobile Toggle -->
            <div class="md:hidden flex items-center gap-3">
                <?php if ($is_logged_in && $nav_user): ?>
                <!-- Mobile notification bell -->
                <a href="/pages/dashboard.php?panel=notifications" class="nav-bell" style="width:36px;height:36px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($nav_user['unread_notifs'] > 0): ?><span class="nav-bell-badge"></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <button id="mobileMenuBtn"
                    class="p-2 rounded-xl text-gray-600 hover:bg-gray-100 focus:outline-none border border-gray-200"
                    aria-label="Open menu">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
        <div class="px-4 py-4 space-y-1">

            <?php if ($is_logged_in && $nav_user): ?>
            <!-- Logged-in user card -->
            <div class="mobile-user-card">
                <div class="mobile-user-avatar"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                <div>
                    <div style="font-size:14px;font-weight:800;color:#fff"><?= htmlspecialchars($nav_user['first_name'].' '.$nav_user['last_name']) ?></div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">Individual Member</div>
                </div>
            </div>
            <?php endif; ?>

            <a href="/"         class="mob-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Home
            </a>
            <a href="#services" class="mob-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                Services
            </a>
            <a href="#booking"  class="mob-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Book a Service
            </a>
            <a href="#about"    class="mob-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                About
            </a>
            <a href="#contact"  class="mob-item">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                Contact
            </a>

            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>

            <?php if ($is_logged_in && $nav_user): ?>
                <a href="/pages/dashboard.php" class="mob-item" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;font-weight:700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    My Dashboard
                </a>
                <a href="/pages/dashboard.php?panel=bookings" class="mob-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    My Bookings
                </a>
                <a href="/pages/dashboard.php?panel=vehicle" class="mob-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    My Vehicle
                </a>
                <a href="/pages/dashboard.php?panel=notifications" class="mob-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    Notifications
                    <?php if($nav_user['unread_notifs'] > 0): ?>
                    <span style="margin-left:auto;background:#fef2f2;color:#dc2626;font-size:10px;font-weight:800;padding:2px 8px;border-radius:99px"><?= $nav_user['unread_notifs'] ?> new</span>
                    <?php endif; ?>
                </a>
                <a href="/pages/logout.php" class="mob-item danger">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Sign Out
                </a>

            <?php else: ?>
                <a href="/pages/login.php" class="mob-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Sign In
                </a>
                <a href="/pages/signup.php" class="mob-item" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-weight:700;box-shadow:0 2px 10px rgba(220,38,38,0.3)">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Create Free Account
                </a>
            <?php endif; ?>

        </div>
    </div>

</nav>

<script>
// ── Avatar dropdown toggle ────────────────────────────────────
function toggleDropdown() {
    const wrap = document.getElementById('avatarWrap');
    if (!wrap) return;
    const isOpen = wrap.classList.toggle('open');
    document.getElementById('avatarBtn').setAttribute('aria-expanded', isOpen);
}
// Close on outside click
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('avatarWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const wrap = document.getElementById('avatarWrap');
        if (wrap) wrap.classList.remove('open');
    }
});
</script>
    <!-- Hero Section -->
    <div id="hero" class="hero-bg hero-bg-image relative overflow-hidden min-h-screen flex items-center">
        <!-- Animated Overlay Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0 hero-overlay-pattern"></div>
        </div>

        <div class="max-w-7xl mx-auto relative z-10 px-4 sm:px-6 lg:px-8 w-full">
            <!-- Floating Elements -->
            <div class="absolute top-20 left-10 w-24 h-24 bg-red-500/20 rounded-full blur-xl animate-pulse anim-delay-0"></div>
            <div class="absolute top-1/2 right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl animate-bounce-slow anim-delay-2s"></div>
            <div class="absolute bottom-20 left-1/4 w-20 h-20 bg-red-400/30 rounded-full blur-xl animate-ping anim-delay-1"></div>

            <div class="text-center" data-aos="fade-up">
               <h1 class="main-title">Welcome To Wiyule Motors</h1>
                <p class="text-xl md:text-2xl text-gray-100 mb-10 max-w-3xl mx-auto leading-relaxed text-shadow">
                    Your trusted partner for quality auto parts and professional vehicle services in Blantyre, Malawi
                </p>
                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <a href="#booking" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-red-600 to-red-700 text-white text-lg font-bold rounded-2xl shadow-2xl hover:shadow-3xl hover:-translate-y-2 transform transition-all duration-300">
                        <i data-feather="calendar" class="w-6 h-6 mr-3"></i>
                        Book a Service
                    </a>
                    <a href="#services" class="inline-flex items-center px-8 py-4 bg-white/10 backdrop-blur-md text-white text-lg font-bold rounded-2xl border-2 border-white/30 hover:bg-white/20 hover:-translate-y-2 transform transition-all duration-300">
                        <i data-feather="tool" class="w-6 h-6 mr-3"></i>
                        Our Services
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Scroll Down Indicator -->
<div id="scroll-indicator" class="scroll-indicator">
    <div class="car-icon">🚗</div>
    <span>Scroll Down</span>
    <div class="arrow">↓</div>
</div>
    
    <!-- Services Section -->
    <section id="services" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Our Services</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Professional automotive services tailored to your needs</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Service Card 1 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="tool" class="w-8 h-8 text-red-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">General Maintenance</h3>
                    <p class="text-gray-600 mb-6">Regular maintenance services including oil changes, filter replacements, and comprehensive vehicle inspections.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Book Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>

                <!-- Service Card 2 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="settings" class="w-8 h-8 text-blue-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Engine Repair</h3>
                    <p class="text-gray-600 mb-6">Expert engine diagnostics, repair, and performance optimization by certified technicians.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Book Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>

                <!-- Service Card 3 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="droplet" class="w-8 h-8 text-green-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Auto Detailing</h3>
                    <p class="text-gray-600 mb-6">Professional interior and exterior detailing to keep your vehicle looking brand new.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Book Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>

                <!-- Service Card 4 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="400">
                    <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="disc" class="w-8 h-8 text-yellow-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Brake Services</h3>
                    <p class="text-gray-600 mb-6">Complete brake inspection, pad replacement, and system maintenance for optimal safety.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Book Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>

                <!-- Service Card 5 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="500">
                    <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="wind" class="w-8 h-8 text-purple-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">AC Repair</h3>
                    <p class="text-gray-600 mb-6">Air conditioning system diagnostics, repair, and recharging services.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Book Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>

                <!-- Service Card 6 -->
                <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="600">
                    <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mb-6">
                        <i data-feather="package" class="w-8 h-8 text-indigo-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Parts Supply</h3>
                    <p class="text-gray-600 mb-6">Wide selection of genuine and aftermarket auto parts with expert guidance.</p>
                    <a href="#booking" class="text-red-600 font-semibold hover:text-red-700 inline-flex items-center">
                        Order Now <i data-feather="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section id="booking" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Book Your Service</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Select your service and schedule an appointment with our expert team</p>
            </div>

            <div class="bg-white rounded-3xl shadow-2xl p-8 md:p-12" data-aos="fade-up" data-aos-delay="200">
                <form id="bookingForm" class="space-y-6">
                    <!-- Personal Information -->
                    <div class="space-y-4">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Personal Information</h3>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="bookingName" class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                <input type="text" id="bookingName" name="name" placeholder="Daniel Kumwenda" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="bookingPhone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" id="bookingPhone" name="phone" placeholder="+265 993 575 111" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                        </div>

                        <div>
                            <label for="bookingEmail" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="bookingEmail" name="email" placeholder="john@example.com" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300">
                        </div>
                    </div>

                    <!-- Vehicle Information -->
                    <div class="space-y-4 pt-6 border-t border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Vehicle Information</h3>
                        
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label for="vehicleMake" class="block text-sm font-semibold text-gray-700 mb-2">Make *</label>
                                <input type="text" id="vehicleMake" name="make" placeholder="Toyota" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="vehicleModel" class="block text-sm font-semibold text-gray-700 mb-2">Model *</label>
                                <input type="text" id="vehicleModel" name="model" placeholder="Corolla" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="vehicleYear" class="block text-sm font-semibold text-gray-700 mb-2">Year *</label>
                                <input type="number" id="vehicleYear" name="year" placeholder="2020" min="1980" max="2026" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                        </div>

                        <div>
                            <label for="vehicleRegistration" class="block text-sm font-semibold text-gray-700 mb-2">Registration Number</label>
                            <input type="text" id="vehicleRegistration" name="registration" placeholder="BT 1234" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300">
                        </div>
                    </div>

                    <!-- Service Selection -->
                    <div class="space-y-4 pt-6 border-t border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Select Service(s) *</h3>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'General Maintenance')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="General Maintenance" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">General Maintenance</p>
                                        <p class="text-sm text-gray-600">Oil change, filters, inspection</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Engine Repair')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Engine Repair" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Engine Repair</p>
                                        <p class="text-sm text-gray-600">Diagnostics & repair</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Auto Detailing')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Auto Detailing" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Auto Detailing</p>
                                        <p class="text-sm text-gray-600">Interior & exterior cleaning</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Brake Services')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Brake Services" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Brake Services</p>
                                        <p class="text-sm text-gray-600">Pads, rotors, inspection</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'AC Repair')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="AC Repair" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">AC Repair</p>
                                        <p class="text-sm text-gray-600">Cooling system service</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Parts Supply')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Parts Supply" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Parts Supply</p>
                                        <p class="text-sm text-gray-600">Genuine & aftermarket parts</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Tire Services')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Tire Services" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Tire Services</p>
                                        <p class="text-sm text-gray-600">Rotation, balancing, replacement</p>
                                    </div>
                                </div>
                            </div>

                            <div class="service-option border-2 border-gray-200 rounded-xl p-4 hover:border-red-300" onclick="toggleService(this, 'Battery Services')">
                                <div class="flex items-center">
                                    <input type="checkbox" name="services" value="Battery Services" class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                                    <div class="ml-3">
                                        <p class="font-semibold text-gray-900">Battery Services</p>
                                        <p class="text-sm text-gray-600">Testing & replacement</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="serviceError" class="hidden text-red-600 text-sm mt-2 font-semibold">Please select at least one service</div>
                    </div>

                    <!-- Date and Time -->
                    <div class="space-y-4 pt-6 border-t border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Preferred Date & Time</h3>
                        
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label for="bookingDate" class="block text-sm font-semibold text-gray-700 mb-2">Preferred Date *</label>
                                <input type="date" id="bookingDate" name="date" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                            </div>
                            <div>
                                <label for="bookingTime" class="block text-sm font-semibold text-gray-700 mb-2">Preferred Time *</label>
                                <select id="bookingTime" name="time" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300" required>
                                    <option value="">Select time</option>
                                    <option value="08:00">08:00 AM</option>
                                    <option value="09:00">09:00 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="12:00">12:00 PM</option>
                                    <option value="13:00">01:00 PM</option>
                                    <option value="14:00">02:00 PM</option>
                                    <option value="15:00">03:00 PM</option>
                                    <option value="16:00">04:00 PM</option>
                                    <option value="17:00">05:00 PM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="pt-6 border-t border-gray-200">
                        <label for="bookingNotes" class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes</label>
                        <textarea id="bookingNotes" name="notes" rows="4" placeholder="Tell us about any specific issues or concerns with your vehicle..." class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 transition-all duration-300 resize-vertical"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-6">
                        <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-5 px-8 rounded-xl font-bold text-xl shadow-xl hover:shadow-2xl hover:-translate-y-1 transform transition-all duration-300">
                            <i data-feather="check-circle" class="w-6 h-6 inline-block mr-2"></i>
                            Confirm Booking
                        </button>
                    </div>
                </form>

                <div id="bookingMessage" class="mt-6 text-center hidden"></div>
            </div>

            <!-- Quick Contact Options -->
            <div class="mt-8 text-center" data-aos="fade-up" data-aos-delay="400">
                <p class="text-gray-600 mb-4">Prefer to book by phone?</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center bg-green-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-green-700 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                        <i data-feather="message-circle" class="w-5 h-5 mr-2"></i>
                        WhatsApp Us
                    </a>
                    <a href="tel:+265993575111" class="inline-flex items-center justify-center bg-red-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-red-700 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                        <i data-feather="phone" class="w-5 h-5 mr-2"></i>
                        Call +265 993 575 111
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="py-20 bg-gradient-to-br from-red-600 to-red-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">Why Choose Wiyule Motors?</h2>
                <p class="text-xl text-red-100 max-w-2xl mx-auto">We're committed to excellence in every aspect of our service</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-feather="award" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Certified Experts</h3>
                    <p class="text-red-100">Professionally trained and certified technicians</p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-feather="shield" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Quality Guarantee</h3>
                    <p class="text-red-100">All work backed by our satisfaction guarantee</p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-feather="dollar-sign" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Fair Pricing</h3>
                    <p class="text-red-100">Transparent pricing with no hidden fees</p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-feather="clock" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Fast Service</h3>
                    <p class="text-red-100">Quick turnaround without compromising quality</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div data-aos="fade-right">
                    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">About Wiyule Motors</h2>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        Since 2016, Wiyule Motors has been Blantyre's trusted destination for premium automotive parts and professional vehicle services. We combine expertise, quality, and customer care to keep your vehicle running smoothly.
                    </p>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        Our team of certified technicians uses state-of-the-art equipment and genuine parts to deliver exceptional service for all makes and models.
                    </p>
                    <div class="grid grid-cols-3 gap-6 mt-8">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-red-600 mb-2">10+</div>
                            <div class="text-sm text-gray-600">Years Experience</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-red-600 mb-2">1M+</div>
                            <div class="text-sm text-gray-600">Happy Clients</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-red-600 mb-2">99.9%</div>
                            <div class="text-sm text-gray-600">Satisfaction</div>
                        </div>
                    </div>
                </div>
                <div data-aos="fade-left">
                    <img src="/assets/images/navara.jpg" alt="Wiyule Motors Logo" class="rounded-3xl shadow-2xl">
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">What Our Clients Say</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Don't just take our word for it - hear from our satisfied customers</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="testimonial-card bg-white rounded-3xl p-8 shadow-xl" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"Excellent service! The team at Wiyule Motors fixed my engine issue quickly and professionally. Their prices are fair and they explained everything clearly. Highly recommended!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                            JM
                        </div>
                        <div class="ml-4">
                            <p class="font-bold text-gray-900">James Mwale</p>
                            <p class="text-sm text-gray-600">Business Owner</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="testimonial-card bg-white rounded-3xl p-8 shadow-xl" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"Best auto service in Blantyre! I've been bringing my Toyota here for 3 years now. Always reliable, honest, and great customer service. My go-to place for all car needs."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                            TK
                        </div>
                        <div class="ml-4">
                            <p class="font-bold text-gray-900">Thandie Kumwenda</p>
                            <p class="text-sm text-gray-600">Teacher</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="testimonial-card bg-white rounded-3xl p-8 shadow-xl" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400">
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                            <i data-feather="star" class="w-5 h-5 fill-current"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"Professional and trustworthy. They have genuine parts and their mechanics really know what they're doing. Fair prices and excellent work. I wouldn't go anywhere else!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                            PC
                        </div>
                        <div class="ml-4">
                            <p class="font-bold text-gray-900">Peter Chirwa</p>
                            <p class="text-sm text-gray-600">Accountant</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Our Facility</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">State-of-the-art equipment and a professional workspace</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="100">
                    <img src="/assets/images/servicing.jpeg" alt="Workshop" class="w-full h-64 object-cover hover:scale-110 transition-transform duration-500">
                </div>
                <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="200">
                    <img src="/assets/images/car alarm (1).jpeg" alt="Car Lift" class="w-full h-64 object-cover hover:scale-110 transition-transform duration-500">
                </div>
                <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="300">
                    <img src="/assets/images/polishing.jpeg" alt="Parts Inventory" class="w-full h-64 object-cover hover:scale-110 transition-transform duration-500">
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
                <p class="text-xl text-gray-600">Everything you need to know about our services</p>
            </div>

            <div class="space-y-4" data-aos="fade-up" data-aos-delay="200">
                <!-- FAQ 1 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">What are your operating hours?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            We're open Monday to Friday from 8:00 AM to 5:00 PM, and Saturdays from 8:00 AM to 2:00 PM. We're closed on Sundays and public holidays.
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">Do I need to book an appointment?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            While walk-ins are welcome, we recommend booking an appointment to ensure minimal wait times and that we can give your vehicle the attention it deserves. You can book online or call us at +265 993 575 111.
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">Do you use genuine parts?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            Yes! We stock both genuine OEM parts and high-quality aftermarket alternatives. We'll always discuss your options and help you choose the best solution for your vehicle and budget.
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">What payment methods do you accept?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            We accept cash and mobile money payments (Airtel Money, TNM Mpamba). We also accept bank transfers for larger transactions.
                        </div>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">Do you offer warranties on your work?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            Absolutely! All our services come with a warranty. The duration varies depending on the type of service or part, but we stand behind our work 100%. Ask us about specific warranty details for your service.
                        </div>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                    <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900">How long does a typical service take?</h3>
                            <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform"></i>
                        </div>
                    </button>
                    <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                        <div class="p-6 pt-0 text-gray-600">
                            Service times vary depending on the type of work needed. A basic oil change typically takes 30-45 minutes, while more extensive repairs may take several hours or days. We'll always give you an estimated timeframe when you book.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Get in Touch</h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">Have questions? We're here to help</p>
            </div>

            <div class="grid md:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div data-aos="fade-right" data-aos-delay="200">
                    <div class="space-y-6">
                        <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                            <div class="w-14 h-14 bg-red-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0 mt-1">
                                <i data-feather="phone" class="w-7 h-7"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg text-gray-900 mb-1">Phone</h4>
                                <p class="text-lg font-semibold text-gray-900">+265 993 575 111</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                            <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0 mt-1">
                                <i data-feather="map-pin" class="w-7 h-7"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg text-gray-900 mb-1">Location</h4>
                                <p class="text-lg font-semibold text-gray-900">Nyambadwe, Blantyre<br>Southern Region, Malawi</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                            <div class="w-14 h-14 bg-green-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0 mt-1">
                                <i data-feather="clock" class="w-7 h-7"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg text-gray-900 mb-1">Business Hours</h4>
                                <p class="text-gray-700">Mon - Fri: 8:00 AM - 5:00 PM</p>
                                <p class="text-gray-700">Sat: 8:00 AM - 2:00 PM</p>
                                <p class="text-gray-700">Sun: Closed</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid md:grid-cols-2 gap-4 mt-6">
                        <a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center bg-green-600 text-white py-4 px-6 rounded-2xl font-bold hover:bg-green-700 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                            <i data-feather="message-circle" class="w-5 h-5 mr-2"></i>
                            WhatsApp Now
                        </a>
                        <a href="tel:+265993575111" class="flex items-center justify-center bg-red-600 text-white py-4 px-6 rounded-2xl font-bold hover:bg-red-700 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                            <i data-feather="phone" class="w-5 h-5 mr-2"></i>
                            Call Now
                        </a>
                    </div>
                </div>
                
                <!-- Contact Form -->
                <div data-aos="fade-left" data-aos-delay="400">
                    <div class="bg-gray-50 rounded-3xl p-8 md:p-12 shadow-2xl">
                        <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Send us a Message</h3>
                        <form id="contactForm" class="space-y-6">
                            <div>
                                <input type="text" id="name" name="name" placeholder="Your Name" class="w-full p-5 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300 text-lg" required>
                            </div>
                            <div>
                                <input type="email" id="email" name="email" placeholder="Your Email" class="w-full p-5 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300 text-lg" required>
                            </div>
                            <div>
                                <input type="tel" id="phone" name="phone" placeholder="Phone Number" class="w-full p-5 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300 text-lg">
                            </div>
                            <div>
                                <textarea id="message" name="message" rows="5" placeholder="Tell us about your vehicle or service needed..." class="w-full p-5 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300 text-lg resize-vertical" required></textarea>
                            </div>
                            <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-5 px-8 rounded-2xl font-bold text-xl shadow-2xl hover:shadow-3xl hover:-translate-y-1 transform transition-all duration-300">
                                Send Message
                            </button>
                        </form>
                        <div id="formMessage" class="mt-4 text-center hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Confirmation Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-br from-red-600 to-red-700 p-8 rounded-t-2xl text-white text-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="check-circle" class="w-12 h-12 text-green-600"></i>
                </div>
                <h3 class="text-3xl font-bold mb-2">Booking Confirmed!</h3>
                <p class="text-red-100">Your appointment has been successfully scheduled</p>
            </div>
            <div class="p-8" id="modalContent">
                <!-- Content will be dynamically inserted -->
            </div>
            <div class="p-6 bg-gray-50 rounded-b-2xl">
                <button onclick="closeModal()" class="w-full bg-red-600 text-white py-4 px-6 rounded-xl font-bold text-lg hover:bg-red-700 transition-all duration-300">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Wiyule Motors</h3>
                    <p class="text-gray-400">Premium automotive parts and services in Blantyre, Malawi since 2016.</p>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#hero" class="text-gray-400 hover:text-white transition">Home</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                        <li><a href="#booking" class="text-gray-400 hover:text-white transition">Book Now</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white transition">About</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-xl font-bold mb-4">Contact</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li>Nyambadwe, Blantyre</li>
                        <li>+265 993 575 111</li>
                        <li>Southern Region, Malawi</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Wiyule Motors. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // Initialize Feather Icons & AOS
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });

            // Close mobile menu when clicking on a link
            const mobileLinks = mobileMenu.querySelectorAll('a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                });
            });
        }

        // Set minimum date for booking (today)
        const bookingDate = document.getElementById('bookingDate');
        if (bookingDate) {
            const today = new Date().toISOString().split('T')[0];
            bookingDate.setAttribute('min', today);
        }

        // Contact form submission with Formspree
        const contactForm = document.getElementById('contactForm');
        const formMessage = document.getElementById('formMessage');

        if (contactForm) {
            contactForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Show loading state
                const submitBtn = contactForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i data-feather="loader" class="w-5 h-5 inline-block animate-spin mr-2"></i>Sending...';
                submitBtn.disabled = true;

                // Get form data
                const formData = new FormData(contactForm);
                
                try {
                    // REPLACE 'YOUR_FORM_ID' with your actual Formspree form ID
                    const response = await fetch('https://formspree.io/f/maqdyrkl', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        formMessage.textContent = 'Thank you! We will get back to you soon.';
                        formMessage.className = 'mt-4 text-center text-green-600 font-semibold p-4 bg-green-50 rounded-xl';
                        formMessage.classList.remove('hidden');
                        contactForm.reset();
                    } else {
                        throw new Error('Form submission failed');
                    }
                } catch (error) {
                    formMessage.textContent = 'Oops! There was a problem. Please try again or call us directly.';
                    formMessage.className = 'mt-4 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                    formMessage.classList.remove('hidden');
                }
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                feather.replace();
                
                setTimeout(() => {
                    formMessage.classList.add('hidden');
                }, 5000);
            });
        }

        // Booking form submission with Formspree
        const bookingForm = document.getElementById('bookingForm');
        const bookingMessage = document.getElementById('bookingMessage');

        if (bookingForm) {
            bookingForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Validate at least one service is selected
                const selectedServices = bookingForm.querySelectorAll('input[name="services"]:checked');
                const serviceError = document.getElementById('serviceError');
                
                if (selectedServices.length === 0) {
                    serviceError.classList.remove('hidden');
                    serviceError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                
                serviceError.classList.add('hidden');
                
                // Collect form data
                const formData = new FormData(bookingForm);
                
                // Add services as a comma-separated string
                const services = Array.from(selectedServices).map(s => s.value).join(', ');
                formData.set('services', services);

                // Show loading state
                const submitBtn = bookingForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i data-feather="loader" class="w-6 h-6 inline-block animate-spin mr-2"></i>Processing...';
                submitBtn.disabled = true;

                try {
                    // REPLACE 'YOUR_BOOKING_FORM_ID' with your actual Formspree form ID
                    const response = await fetch('https://formspree.io/f/xgolegbw', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        // Collect data for modal
                        const bookingData = {
                            name: formData.get('name'),
                            phone: formData.get('phone'),
                            email: formData.get('email'),
                            vehicle: {
                                make: formData.get('make'),
                                model: formData.get('model'),
                                year: formData.get('year'),
                                registration: formData.get('registration')
                            },
                            services: services.split(', '),
                            date: formData.get('date'),
                            time: formData.get('time'),
                            notes: formData.get('notes')
                        };

                        // Show confirmation modal
                        showBookingModal(bookingData);
                        
                        // Reset form
                        bookingForm.reset();
                        
                        // Clear selected services styling
                        document.querySelectorAll('.service-option.selected').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                    } else {
                        throw new Error('Booking submission failed');
                    }
                } catch (error) {
                    bookingMessage.textContent = 'Oops! There was a problem. Please try again or call us at +265 993 575 111.';
                    bookingMessage.className = 'mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                    bookingMessage.classList.remove('hidden');
                    
                    setTimeout(() => {
                        bookingMessage.classList.add('hidden');
                    }, 5000);
                }
                
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                feather.replace();
            });
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && document.querySelector(href)) {
                    e.preventDefault();
                    document.querySelector(href).scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });

    // Toggle service selection styling
    function toggleService(element, serviceName) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        element.classList.toggle('selected');
        
        // Hide error if service is selected
        if (checkbox.checked) {
            document.getElementById('serviceError').classList.add('hidden');
        }
    }

    // Toggle FAQ
    function toggleFAQ(button) {
        const answer = button.nextElementSibling;
        const icon = button.querySelector('[data-feather="chevron-down"]');
        const isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
        
        // Close all other FAQs
        document.querySelectorAll('.faq-answer').forEach(item => {
            item.style.maxHeight = '0';
        });
        document.querySelectorAll('.faq-question [data-feather="chevron-down"]').forEach(item => {
            item.style.transform = 'rotate(0deg)';
        });
        
        // Toggle current FAQ
        if (!isOpen) {
            answer.style.maxHeight = answer.scrollHeight + 'px';
            icon.style.transform = 'rotate(180deg)';
        }
        
        feather.replace();
    }

    // Show booking confirmation modal
    function showBookingModal(data) {
        const modal = document.getElementById('bookingModal');
        const modalContent = document.getElementById('modalContent');
        
        // Format date
        const dateObj = new Date(data.date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        // Create modal content
        modalContent.innerHTML = `
            <div class="space-y-6">
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Customer Details</h4>
                    <p class="text-gray-900 font-semibold">${data.name}</p>
                    <p class="text-gray-600">${data.phone}</p>
                    ${data.email ? `<p class="text-gray-600">${data.email}</p>` : ''}
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Vehicle Information</h4>
                    <p class="text-gray-900 font-semibold">${data.vehicle.year} ${data.vehicle.make} ${data.vehicle.model}</p>
                    ${data.vehicle.registration ? `<p class="text-gray-600">Registration: ${data.vehicle.registration}</p>` : ''}
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Selected Services</h4>
                    <ul class="space-y-1">
                        ${data.services.map(service => `<li class="text-gray-900">• ${service}</li>`).join('')}
                    </ul>
                </div>
                
                <div class="border-b border-gray-200 pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Appointment Time</h4>
                    <p class="text-gray-900 font-semibold">${formattedDate}</p>
                    <p class="text-gray-900">at ${formatTime(data.time)}</p>
                </div>
                
                ${data.notes ? `
                <div class="pb-4">
                    <h4 class="font-semibold text-gray-700 mb-2">Additional Notes</h4>
                    <p class="text-gray-600">${data.notes}</p>
                </div>
                ` : ''}
                
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-sm text-blue-900">
                        <i data-feather="info" class="w-4 h-4 inline-block mr-1"></i>
                        We'll contact you shortly to confirm your appointment. You can also reach us directly at +265 993 575 111.
                    </p>
                </div>
            </div>
        `;
        
        modal.classList.add('active');
        feather.replace(); // Refresh feather icons in modal
    }

    // Close modal
    function closeModal() {
        const modal = document.getElementById('bookingModal');
        modal.classList.remove('active');
    }

    // Format time for display
    function formatTime(time) {
        const [hour, minute] = time.split(':');
        const hourNum = parseInt(hour);
        const ampm = hourNum >= 12 ? 'PM' : 'AM';
        const displayHour = hourNum > 12 ? hourNum - 12 : (hourNum === 0 ? 12 : hourNum);
        return `${displayHour}:${minute} ${ampm}`;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('bookingModal');
        if (event.target === modal) {
            closeModal();
        }
    }

    // Track scroll for analytics (optional)
    let scrollTracked = false;
    window.addEventListener('scroll', function() {
        if (!scrollTracked && window.scrollY > 500) {
            scrollTracked = true;
            // Track scroll event in analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'scroll', {
                    'event_category': 'engagement',
                    'event_label': 'scrolled_page'
                });
            }
        }
    });

    // Track booking button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('a[href="#booking"]')) {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    'event_category': 'conversion',
                    'event_label': 'booking_button_clicked'
                });
            }
        }
    });


</script>  
</body>
</html>