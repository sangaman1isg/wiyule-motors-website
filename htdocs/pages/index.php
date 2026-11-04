<?php
// ── Session & auth ────────────────────────────────────────────
session_start();

// Base URL — auto-detects localhost vs live site
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

$is_logged_in = isset($_SESSION['user_id']);
// Preview only works on localhost
if (isset($_GET['preview']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    $is_logged_in = true;
}

$nav_user = $is_logged_in ? [
    'first_name'      => $_SESSION['first_name']    ?? 'User',
    'last_name'       => $_SESSION['last_name']     ?? '',
    'avatar_initials' => strtoupper(
                            substr($_SESSION['first_name'] ?? 'U', 0, 1) .
                            substr($_SESSION['last_name']  ?? '', 0, 1)
                         ),
    'unread_notifs'   => (int)($_SESSION['unread_notifs'] ?? 0),
] : null;

// Auto-fill booking form if logged in
$autofill = $is_logged_in ? [
    'name'  => htmlspecialchars(($nav_user['first_name'] ?? '') . ' ' . ($nav_user['last_name'] ?? '')),
    'phone' => htmlspecialchars($_SESSION['phone'] ?? ''),
    'email' => htmlspecialchars($_SESSION['email'] ?? ''),
] : ['name'=>'','phone'=>'','email'=>''];

// ── Check if current user is eligible for first-time offer ────
$offer_eligible = false;
if ($is_logged_in && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/db_connection.php';
    $uid = (int)$_SESSION['user_id'];
    $chk = $conn->prepare("SELECT first_offer_used FROM users WHERE id = ?");
    if ($chk) {
        $chk->bind_param("i", $uid);
        $chk->execute();
        $r = $chk->get_result()->fetch_assoc();
        $chk->close();
        $offer_eligible = $r && (int)$r['first_offer_used'] === 0;
    }
}
// Guests are always "eligible" (they'll see the signup prompt)
$show_offer_banner = !$is_logged_in || $offer_eligible;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Wiyule Motors offers premium automotive parts and services in Blantyre, Malawi. Shop high-quality parts and book expert vehicle maintenance today.">
    <meta name="keywords" content="automotive parts, vehicle maintenance, auto detailing, car repair, Wiyule Motors, Blantyre, Malawi, brake services, engine repair, oil change">
    <meta property="og:title" content="Wiyule Motors - Premium Automotive Parts &amp; Services">
    <meta property="og:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta property="og:image" content="https://wiyulemotors.com/assets/images/workshop_navara.jpg">
    <meta property="og:url" content="https://wiyulemotors.com">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Wiyule Motors - Premium Automotive Parts &amp; Services">
    <meta name="twitter:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#dc2626">
    <title>Wiyule Motors - Premium Automotive Parts &amp; Services | Blantyre, Malawi</title>
    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link rel="canonical" href="https://wiyulemotors.com">

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HMM03HL39K"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-HMM03HL39K');
    </script>

    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AutoRepair",
        "name": "Wiyule Motors",
        "image": "https://wiyulemotors.com/assets/images/workshop_navara.jpg",
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
            {"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday"],"opens":"08:00","closes":"17:00"},
            {"@type":"OpeningHoursSpecification","dayOfWeek":"Saturday","opens":"08:00","closes":"14:00"}
        ],
        "aggregateRating": {"@type":"AggregateRating","ratingValue":"5","reviewCount":"120"}
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

    <style>
        /* ── Nav ─────────────────────────────────────────────── */
        .nav-link {
            position:relative; font-weight:600; color:#374151;
            transition:color 0.2s; text-decoration:none;
            font-size:13px; padding:4px 0;
            letter-spacing:0.01em; text-transform:uppercase;
        }
        .nav-link::after {
            content:''; position:absolute; bottom:-2px; left:0;
            width:0; height:2px; background:#dc2626;
            border-radius:99px; transition:width 0.25s ease;
        }
        .nav-link:hover,.nav-link.active { color:#dc2626; }
        .nav-link:hover::after,.nav-link.active::after { width:100%; }

        .nav-bell {
            position:relative; width:40px; height:40px; border-radius:12px;
            border:1.5px solid #e5e7eb; background:#fff;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; color:#6b7280; transition:all 0.2s; text-decoration:none;
        }
        .nav-bell:hover { border-color:#dc2626; color:#dc2626; background:#fef2f2; }
        .nav-bell-badge {
            position:absolute; top:6px; right:6px; width:9px; height:9px;
            background:#dc2626; border-radius:50%; border:2px solid #fff;
            animation:bellPulse 2.5s ease-in-out infinite;
        }
        @keyframes bellPulse {
            0%,100% { box-shadow:0 0 0 0 rgba(220,38,38,0.4); }
            50%      { box-shadow:0 0 0 4px rgba(220,38,38,0); }
        }
        .nav-dashboard-btn {
            display:inline-flex; align-items:center; gap:7px; padding:8px 16px;
            background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);
            color:#fff; border-radius:12px; font-size:13px; font-weight:700;
            text-decoration:none; transition:all 0.25s ease;
            box-shadow:0 2px 8px rgba(15,23,42,0.25); white-space:nowrap;
        }
        .nav-dashboard-btn:hover { background:#dc2626; box-shadow:0 4px 16px rgba(220,38,38,0.35); transform:translateY(-1px); }
        .nav-dashboard-btn svg { width:14px; height:14px; }

        .nav-avatar-wrap { position:relative; }
        .nav-avatar {
            display:flex; align-items:center; gap:9px; padding:5px 10px 5px 5px;
            border-radius:40px; border:1.5px solid #e5e7eb; background:#fff;
            cursor:pointer; transition:all 0.2s; user-select:none;
        }
        .nav-avatar:hover { border-color:#dc2626; background:#fef2f2; }
        .nav-avatar-circle {
            width:32px; height:32px; border-radius:50%;
            background:linear-gradient(135deg,#dc2626,#b91c1c);
            color:#fff; font-size:12px; font-weight:800;
            display:flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .nav-avatar-name  { font-size:13px; font-weight:700; color:#1f2937; }
        .nav-avatar-caret { color:#9ca3af; transition:transform 0.2s; }
        .nav-avatar-wrap.open .nav-avatar-caret { transform:rotate(180deg); }
        .nav-avatar-wrap.open .nav-avatar { border-color:#dc2626; }

        .nav-dropdown {
            position:absolute; top:calc(100% + 10px); right:0; width:240px;
            background:#fff; border:1.5px solid #e5e7eb; border-radius:18px;
            box-shadow:0 16px 48px rgba(0,0,0,0.14); overflow:hidden;
            opacity:0; transform:translateY(-8px) scale(0.97); pointer-events:none;
            transition:all 0.22s cubic-bezier(0.34,1.56,0.64,1); z-index:200;
        }
        .nav-avatar-wrap.open .nav-dropdown { opacity:1; transform:translateY(0) scale(1); pointer-events:all; }
        .dd-header { background:linear-gradient(135deg,#0f172a,#1e293b); padding:16px; display:flex; align-items:center; gap:12px; }
        .dd-header-avatar { width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; font-size:15px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dd-header-name { font-size:14px; font-weight:800; color:#fff; }
        .dd-header-role { font-size:11px; color:#64748b; margin-top:1px; }
        .dd-items { padding:8px; }
        .dd-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:#374151; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; transition:all 0.15s; }
        .dd-item:hover { background:#f8fafc; color:#0f172a; }
        .dd-item.danger { color:#dc2626; }
        .dd-item.danger:hover { background:#fef2f2; }
        .dd-item-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .dd-item-icon svg { width:15px; height:15px; }
        .dd-badge { margin-left:auto; background:#fef2f2; color:#dc2626; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; }
        .dd-divider { height:1px; background:#f1f5f9; margin:6px 8px; }

        .mob-item { display:flex; align-items:center; gap:10px; padding:12px 14px; border-radius:12px; color:#374151; font-size:14px; font-weight:500; text-decoration:none; transition:all 0.15s; }
        .mob-item:hover { background:#f8fafc; color:#0f172a; }
        .mob-item svg { width:18px; height:18px; flex-shrink:0; }
        .mob-item.danger { color:#dc2626; }
        .mob-item.danger:hover { background:#fef2f2; }

        /* ── Mobile Menu Smooth Slide ────────────────────────── */
        #mobile-menu { max-height:0; overflow:hidden; transition:max-height 0.4s ease; border-top:1px solid transparent; }
        #mobile-menu.open { max-height:700px; border-top-color:#f1f5f9; }
        .mobile-user-card { background:linear-gradient(135deg,#0f172a,#1e293b); border-radius:14px; padding:14px; display:flex; align-items:center; gap:12px; margin-bottom:8px; }
        .mobile-user-avatar { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; font-size:15px; font-weight:800; display:flex; align-items:center; justify-content:center; }

        /* ── Hero ─────────────────────────────────────────────── */
        .hero-bg { background-color:#0f172a; color:#fff; }
        .hero-bg-image {
            background-image:
                linear-gradient(135deg,rgba(15,23,42,0.82) 0%,rgba(127,29,29,0.70) 50%,rgba(15,23,42,0.85) 100%),
                url('<?= $base ?>/assets/images/workshop_navara.jpg');
            background-size:cover;
            background-position:center;
            background-repeat:no-repeat;
            transform:translateZ(0);
        }
        .text-shadow { text-shadow:0 2px 12px rgba(0,0,0,0.5); }
        .main-title {
            font-size:clamp(2.2rem,6vw,4.5rem);
            font-weight:800; color:#ffffff; line-height:1.05;
            letter-spacing:-0.03em; margin-bottom:1.5rem;
            text-shadow:0 4px 24px rgba(0,0,0,0.5);
        }

        /* ── UPGRADE 1: Hero Stats Bar ──────────────────────── */
        .hero-stats-bar {
            display:grid; grid-template-columns:repeat(4, 1fr);
            background:rgba(255,255,255,0.06);
            backdrop-filter:blur(12px);
            border:1px solid rgba(255,255,255,0.12);
            border-radius:20px;
            padding:20px 24px;
            margin-top:48px;
            max-width:700px;
            margin-left:auto;
            margin-right:auto;
        }
        .hero-stat {
            text-align:center;
            padding:8px 12px;
            border-right:1px solid rgba(255,255,255,0.12);
        }
        .hero-stat:last-child { border-right:none; }
        .hero-stat-number {
            font-size:1.75rem; font-weight:900; color:#fff;
            line-height:1; margin-bottom:4px;
        }
        .hero-stat-number span { color:#f87171; }
        .hero-stat-label { font-size:11px; color:rgba(255,255,255,0.65); font-weight:500; text-transform:uppercase; letter-spacing:0.05em; }
        @media(max-width:640px) {
            .hero-stats-bar {
                grid-template-columns:repeat(2, 1fr);
                gap:0; padding:16px;
                border-radius:16px; margin-top:32px;
            }
            .hero-stat {
                padding:12px 8px;
            }
            .hero-stat:nth-child(2) { border-right:none; }
            .hero-stat:nth-child(1),
            .hero-stat:nth-child(2) { border-bottom:1px solid rgba(255,255,255,0.12); }
            .hero-stat-number { font-size:1.5rem; }
            .hero-stat-label { font-size:10px; }
        }

        /* ── Speed lines (static, no animation for performance) ── */
        .speed-lines {
            position:absolute; inset:0;
            background:repeating-linear-gradient(90deg,transparent,transparent 80px,rgba(255,255,255,0.04) 81px,rgba(255,255,255,0.04) 82px);
        }

        /* ── Hero lights (static, no animation for performance) ── */
        .hero-light { position:absolute; border-radius:50%; filter:blur(80px); opacity:0.3; }
        .light1 { width:300px; height:300px; background:red; top:10%; left:10%; }
        .light2 { width:250px; height:250px; background:white; bottom:20%; right:10%; }
        .light3 { width:200px; height:200px; background:crimson; top:40%; left:40%; }
        @media(max-width:767px) {
            .light1 { width:150px; height:150px; left:0; }
            .light2 { width:120px; height:120px; right:0; }
            .light3 { width:100px; height:100px; }
        }
        .hero-overlay-pattern {
            background-image:
                repeating-linear-gradient(0deg,transparent,transparent 40px,rgba(255,255,255,0.03) 40px,rgba(255,255,255,0.03) 41px),
                repeating-linear-gradient(90deg,transparent,transparent 40px,rgba(255,255,255,0.03) 40px,rgba(255,255,255,0.03) 41px);
        }

        /* ── UPGRADE 2: Service cards with pricing ────────────── */
        .service-card {
            transition:transform 0.3s ease, box-shadow 0.3s ease;
            position:relative; overflow:hidden;
        }
        .service-card:hover { transform:translateY(-6px); box-shadow:0 24px 48px rgba(0,0,0,0.12); }
        .service-price-tag {
            display:inline-flex; align-items:center;
            background:#fef2f2; color:#dc2626;
            font-size:12px; font-weight:700;
            padding:4px 10px; border-radius:99px;
            margin-bottom:12px;
        }

        /* ── UPGRADE 3: Booking Stepper ───────────────────────── */
        .stepper {
            display:flex; align-items:center; justify-content:center;
            margin-bottom:32px; gap:0;
        }
        .step {
            display:flex; flex-direction:column; align-items:center; gap:6px;
            flex:1; position:relative;
        }
        .step:not(:last-child)::after {
            content:''; position:absolute;
            top:18px; left:60%; width:80%; height:2px;
            background:#e5e7eb; z-index:0;
            transition:background 0.4s;
        }
        .step.completed:not(:last-child)::after { background:#dc2626; }
        .step-circle {
            width:36px; height:36px; border-radius:50%;
            border:2px solid #e5e7eb; background:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:13px; font-weight:800; color:#9ca3af;
            transition:all 0.3s; z-index:1; position:relative;
        }
        .step.active   .step-circle { border-color:#dc2626; background:#dc2626; color:#fff; }
        .step.completed .step-circle { border-color:#16a34a; background:#16a34a; color:#fff; }
        .step-label { font-size:11px; font-weight:600; color:#9ca3af; text-align:center; white-space:nowrap; }
        .step.active    .step-label { color:#dc2626; }
        .step.completed .step-label { color:#16a34a; }

        .booking-step-panel { display:none; animation:stepFadeIn 0.4s ease; }
        .booking-step-panel.active { display:block; }
        @keyframes stepFadeIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

        .step-nav-btn {
            padding:12px 28px; border-radius:12px; font-size:14px; font-weight:700;
            cursor:pointer; transition:all 0.2s; border:none;
            font-family:'Sora',sans-serif;
        }
        .step-nav-btn.prev { background:#f1f5f9; color:#374151; }
        .step-nav-btn.prev:hover { background:#e2e8f0; }
        .step-nav-btn.next { background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; box-shadow:0 4px 12px rgba(220,38,38,0.3); }
        .step-nav-btn.next:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(220,38,38,0.4); }
        @media(max-width:640px) {
            .step-nav-btn { padding:14px 20px; font-size:13px; flex:1; text-align:center; }
            .booking-step-panel .grid.md\:grid-cols-2 { grid-template-columns:1fr; }
            .booking-step-panel .grid.md\:grid-cols-3 { grid-template-columns:1fr; }
            .booking-step-panel h3 { font-size:1.25rem; }
            .booking-step-panel .flex.justify-between,
            .booking-step-panel .flex.justify-end { gap:10px; }
        }

        /* ── Service option selected ──────────────────────────── */
        .service-option { cursor:pointer; transition:border-color 0.2s, background 0.2s, transform 0.2s; }
        .service-option:hover { transform:translateY(-2px); }
        .service-option.selected { border-color:#dc2626 !important; background:#fef2f2; }
        .service-option:active { transform:scale(0.98); }
        @media(max-width:640px) {
            .service-option { padding:12px !important; }
            .service-option p.text-sm { font-size:13px; }
        }

        /* ── Testimonial hover ────────────────────────────────── */
        .testimonial-card { transition:transform 0.3s ease, box-shadow 0.3s ease; }
        .testimonial-card:hover { transform:translateY(-4px); box-shadow:0 20px 40px rgba(0,0,0,0.10); }

        /* ── UPGRADE 4: Testimonial Carousel ──────────────────── */
        .testimonial-carousel { position:relative; overflow:hidden; }
        .testimonial-track { display:flex; transition:transform 0.5s cubic-bezier(0.4,0,0.2,1); }
        .testimonial-slide { min-width:100%; padding:0 4px; box-sizing:border-box; }
        @media(min-width:768px) {
            .testimonial-slide { min-width:33.333%; }
        }
        .carousel-dot {
            width:8px; height:8px; border-radius:50%;
            background:#d1d5db; border:none; cursor:pointer;
            transition:all 0.3s; padding:0;
        }
        .carousel-dot.active { background:#dc2626; width:24px; border-radius:4px; }
        .carousel-arrow {
            width:40px; height:40px; border-radius:50%;
            background:#fff; border:1.5px solid #e5e7eb;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:all 0.2s; flex-shrink:0;
        }
        .carousel-arrow:hover { border-color:#dc2626; color:#dc2626; background:#fef2f2; }

        /* ── Booking / Contact modal ──────────────────────────── */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
        .modal.active { display:flex; }
        .modal-content { background:#fff; border-radius:1.25rem; max-width:560px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 32px 80px rgba(0,0,0,0.25); animation:modalSlide 0.3s ease; }
        @keyframes modalSlide { from { opacity:0; transform:translateY(20px) scale(0.95); } to { opacity:1; transform:translateY(0) scale(1); } }
        @media(max-width:640px) {
            .modal { padding:0.5rem; align-items:flex-end; }
            .modal-content { max-height:95vh; border-radius:1.25rem 1.25rem 0 0; }
        }

        /* ── Gallery ──────────────────────────────────────────── */
        .gallery-img { background:#f1f5f9; display:block; width:100%; height:16rem; object-fit:cover; }

        /* ── UPGRADE 5: Back to top button ────────────────────── */
        #backToTop {
            position:fixed; bottom:96px; right:24px;
            width:44px; height:44px; border-radius:12px;
            background:linear-gradient(135deg,#0f172a,#1e293b);
            color:#fff; border:none; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 16px rgba(0,0,0,0.25);
            opacity:0; transform:translateY(12px);
            transition:all 0.3s ease; z-index:49; pointer-events:none;
        }
        #backToTop.visible { opacity:1; transform:translateY(0); pointer-events:all; }
        #backToTop:hover { background:#dc2626; }

        /* ── UPGRADE 6: Sticky mobile booking bar ─────────────── */
        .mobile-sticky-bar {
            display:none;
            position:fixed; bottom:0; left:0; right:0;
            background:#fff; border-top:1px solid #e5e7eb;
            padding:12px 16px; z-index:48;
            box-shadow:0 -4px 24px rgba(0,0,0,0.10);
        }
        @media(max-width:767px) { .mobile-sticky-bar { display:flex; gap:10px; } }

        /* ── UPGRADE 7: Count-up animation (about stats) ─────── */
        .count-up { transition:all 0.1s; }

        /* ── WhatsApp button ──────────────────────────────────── */
        .whatsapp-btn {
            position:fixed; bottom:24px; right:24px;
            background:#25d366; color:#fff;
            width:52px; height:52px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 16px rgba(37,211,102,0.4);
            transition:all 0.3s; z-index:50; text-decoration:none;
        }
        .whatsapp-btn:hover { transform:scale(1.1); box-shadow:0 6px 24px rgba(37,211,102,0.5); }

        /* ── Feedback Button ─────────────────────────────────── */
        .feedback-btn {
            position:fixed; bottom:24px; left:24px;
            display:flex; align-items:center; gap:8px;
            background:#fff; color:#374151;
            padding:10px 16px; border-radius:99px;
            box-shadow:0 4px 20px rgba(0,0,0,0.12);
            border:1.5px solid #e5e7eb;
            font-size:13px; font-weight:600;
            font-family:'Sora',sans-serif;
            cursor:pointer; text-decoration:none;
            transition:all 0.3s; z-index:50;
        }
        .feedback-btn:hover { border-color:#dc2626; color:#dc2626; box-shadow:0 6px 24px rgba(0,0,0,0.16); transform:translateY(-2px); }
        .feedback-btn svg { width:16px; height:16px; flex-shrink:0; }
        @media(max-width:767px) {
            .feedback-btn span { display:none; }
            .feedback-btn { padding:10px; border-radius:50%; }
        }

        /* ── Comprehensive Mobile Fixes ──────────────────────── */
        @media(max-width:767px) {
            /* Hero section */
            .main-title { font-size:clamp(1.8rem, 8vw, 2.8rem) !important; margin-bottom:1rem; }
            .hero-bg-image { min-height:auto; }
            #hero .text-xl { font-size:1rem; }
            #hero .px-8 { padding-left:1.25rem; padding-right:1.25rem; }
            #hero .py-4 { padding-top:0.75rem; padding-bottom:0.75rem; }
            #hero .text-lg { font-size:0.9rem; }

            /* Hero CTA buttons - stack nicely */
            #hero .flex.flex-col.sm\:flex-row { gap:10px; }
            #hero .flex.flex-col.sm\:flex-row a {
                font-size:0.9rem; padding:12px 20px;
                width:100%; justify-content:center;
            }
            #hero .flex.flex-col.sm\:flex-row a i { width:18px; height:18px; margin-right:8px; }

            /* Section headings */
            .text-4xl { font-size:1.75rem !important; }
            .text-5xl { font-size:1.75rem !important; }
            .text-2xl { font-size:1.25rem !important; }

            /* Service cards */
            .service-card { padding:1.25rem; }

            /* About stats grid */
            #about .grid.grid-cols-3 {
                grid-template-columns:repeat(3, 1fr); gap:8px;
            }
            #about .grid.grid-cols-3 .text-4xl { font-size:1.5rem !important; }
            #about .grid.grid-cols-3 .text-sm { font-size:0.7rem; }
            #about .grid.grid-cols-3 .p-4 { padding:10px 6px; }

            /* Testimonial cards */
            .testimonial-card { padding:1.25rem; }
            .testimonial-card p { font-size:0.85rem; }

            /* Gallery images */
            .gallery-img { height:12rem; }

            /* FAQ */
            .faq-question { padding:1rem; }
            .faq-question h3 { font-size:0.95rem; }

            /* Contact section */
            #contact .grid.grid-cols-2 { grid-template-columns:1fr; }

            /* Booking stepper */
            .stepper { gap:0; }
            .step-circle { width:30px; height:30px; font-size:11px; }
            .step-label { font-size:9px; }
            .step:not(:last-child)::after { top:15px; }

            /* Floating buttons - move above sticky bar */
            .whatsapp-btn { bottom:76px; width:46px; height:46px; }
            #backToTop { bottom:130px; }
            .feedback-btn { bottom:76px; }

            /* Footer extra padding for sticky bar */
            footer { padding-bottom:100px !important; }
        }

        /* Small phones */
        @media(max-width:380px) {
            .hero-stats-bar { padding:12px; }
            .hero-stat-number { font-size:1.25rem; }
            .hero-stat-label { font-size:9px; letter-spacing:0.03em; }
            .main-title { font-size:1.6rem !important; }
            #hero .text-xl { font-size:0.9rem; }
            .nav-link { font-size:12px; }
        }
    </style>
</head>
<body class="font-[Sora] antialiased text-gray-800">

<?php if($show_offer_banner): ?>
<!-- ═══ First-Time Customer Offer Banner ═══ -->
<div id="offerBanner" style="background:linear-gradient(90deg,#dc2626,#b91c1c,#991b1b);color:#fff;padding:10px 16px;text-align:center;position:relative;z-index:60;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:16px">🎉</span>
    <span><strong>First-time customer?</strong> Get <strong>10% OFF</strong> your first service —</span>
    <?php if(!$is_logged_in): ?>
        <a href="<?= $base ?>/pages/signup.php?offer=first10" style="background:#fff;color:#b91c1c;padding:5px 14px;border-radius:99px;font-weight:800;text-decoration:none;font-size:12px">Claim Now →</a>
    <?php else: ?>
        <a href="#booking" onclick="document.getElementById('claimOfferCheckbox').checked=true" style="background:#fff;color:#b91c1c;padding:5px 14px;border-radius:99px;font-weight:800;text-decoration:none;font-size:12px">Book & Apply →</a>
    <?php endif; ?>
    <button onclick="dismissOfferBanner()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.15);border:none;color:#fff;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center" aria-label="Dismiss">✕</button>
</div>
<script>
if (sessionStorage.getItem('offerDismissed') === '1') {
    var b = document.getElementById('offerBanner');
    if (b) b.style.display = 'none';
}
function dismissOfferBanner(){
    var b = document.getElementById('offerBanner');
    if (b) b.style.display = 'none';
    sessionStorage.setItem('offerDismissed','1');
}
</script>
<?php endif; ?>

<!-- WhatsApp Button -->
<a href="https://wa.me/265993575111" class="whatsapp-btn" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
    <i data-feather="message-circle"></i>
</a>

<!-- Feedback Button -->
<a href="https://wa.me/265993575111?text=Hi%20Wiyule%20Motors%2C%20I%20found%20an%20issue%20on%20your%20website%3A%20" class="feedback-btn" target="_blank" rel="noopener noreferrer" title="Report an issue or give feedback">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    <span>Feedback</span>
</a>

<!-- UPGRADE 5: Back to Top Button -->
<button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<!-- UPGRADE 6: Mobile Sticky Booking Bar -->
<div class="mobile-sticky-bar">
    <a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer"
       class="flex-1 flex items-center justify-center gap-2 bg-green-500 text-white py-3 rounded-xl font-bold text-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        WhatsApp
    </a>
    <a href="#booking"
       class="flex-1 flex items-center justify-center gap-2 bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-xl font-bold text-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Book Now
    </a>
</div>

<!-- ═══ Navigation ═══ -->
<nav class="bg-white shadow-md sticky top-0 z-50">
    <div style="height:3px;background:linear-gradient(90deg,#dc2626,#b91c1c,#7f1d1d);"></div>
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-20">

            <!-- Logo -->
            <a href="<?= $base ?>/" class="flex items-center space-x-3" style="text-decoration:none">
                <img class="h-12 w-auto"
                     src="assets/images/logo.png"
                     alt="Wiyule Motors Logo">
                <div>
                    <span class="text-xl font-bold text-gray-900 block leading-tight">Wiyule Motors</span>
                    <span class="text-xs text-gray-400 font-medium" style="letter-spacing:0.04em">Blantyre, Malawi</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <div class="hidden md:flex items-center" style="gap:28px">
                <a href="<?= $base ?>/"        class="nav-link active">Home</a>
                <a href="#services"             class="nav-link">Services</a>
                <a href="<?= $base ?>/pricing.php" class="nav-link">Pricing</a>
                <a href="#booking"              class="nav-link">Book</a>
                <a href="#about"                class="nav-link">About</a>
                <a href="#contact"              class="nav-link">Contact</a>
            </div>

            <!-- Desktop Auth -->
            <div class="hidden md:flex items-center" style="gap:10px">
                <?php if ($is_logged_in && $nav_user): ?>
                    <a href="<?= $base ?>/pages/dashboard.php?panel=notifications" class="nav-bell" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($nav_user['unread_notifs'] > 0): ?><span class="nav-bell-badge"></span><?php endif; ?>
                    </a>
                    <a href="<?= $base ?>/pages/dashboard.php" class="nav-dashboard-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                        Dashboard
                    </a>
                    <div class="nav-avatar-wrap" id="avatarWrap">
                        <div class="nav-avatar" onclick="toggleDropdown()" id="avatarBtn" aria-haspopup="true" aria-expanded="false">
                            <div class="nav-avatar-circle"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                            <span class="nav-avatar-name"><?= htmlspecialchars($nav_user['first_name']) ?></span>
                            <svg class="nav-avatar-caret" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </div>
                        <div class="nav-dropdown" id="navDropdown" role="menu">
                            <div class="dd-header">
                                <div class="dd-header-avatar"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                                <div>
                                    <div class="dd-header-name"><?= htmlspecialchars($nav_user['first_name'].' '.$nav_user['last_name']) ?></div>
                                    <div class="dd-header-role">Individual Member</div>
                                </div>
                            </div>
                            <div class="dd-items">
                                <a href="<?= $base ?>/pages/dashboard.php" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f1f5f9;color:#374151"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
                                    Overview
                                </a>
                                <a href="<?= $base ?>/pages/dashboard.php?panel=bookings" class="dd-item">
                                    <div class="dd-item-icon" style="background:#eff6ff;color:#2563eb"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                    My Bookings
                                    <span class="dd-badge">upcoming</span>
                                </a>
                                <a href="<?= $base ?>/pages/dashboard.php?panel=vehicle" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f0fdf4;color:#15803d"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
                                    My Vehicle
                                </a>
                                <a href="<?= $base ?>/pages/dashboard.php?panel=notifications" class="dd-item">
                                    <div class="dd-item-icon" style="background:#fef2f2;color:#dc2626"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
                                    Notifications
                                    <?php if($nav_user['unread_notifs'] > 0): ?><span class="dd-badge"><?= $nav_user['unread_notifs'] ?> new</span><?php endif; ?>
                                </a>
                                <a href="<?= $base ?>/pages/dashboard.php?panel=settings" class="dd-item">
                                    <div class="dd-item-icon" style="background:#f8fafc;color:#64748b"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></div>
                                    Settings
                                </a>
                                <div class="dd-divider"></div>
                                <a href="<?= $base ?>/pages/logout.php" class="dd-item danger">
                                    <div class="dd-item-icon" style="background:#fef2f2;color:#dc2626"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= $base ?>/pages/login.php"
                       style="padding:8px 18px;border:1.5px solid #e5e7eb;color:#374151;border-radius:12px;font-size:13px;font-weight:600;text-decoration:none;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px"
                       onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626';this.style.background='#fef2f2'"
                       onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151';this.style.background='transparent'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign In
                    </a>
                    <a href="<?= $base ?>/pages/signup.php"
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
                <a href="<?= $base ?>/pages/dashboard.php?panel=notifications" class="nav-bell" style="width:36px;height:36px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($nav_user['unread_notifs'] > 0): ?><span class="nav-bell-badge"></span><?php endif; ?>
                </a>
                <?php endif; ?>
                <button id="mobileMenuBtn" onclick="toggleMobileMenu()" class="p-2 rounded-xl text-gray-600 hover:bg-gray-100 focus:outline-none border border-gray-200" aria-label="Toggle menu">
                    <svg id="menuIconOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    <svg id="menuIconClose" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu (smooth slide) -->
    <div id="mobile-menu" class="md:hidden bg-white">
        <div style="padding:16px;display:flex;flex-direction:column;gap:4px">
            <?php if ($is_logged_in && $nav_user): ?>
            <div class="mobile-user-card">
                <div class="mobile-user-avatar"><?= htmlspecialchars($nav_user['avatar_initials']) ?></div>
                <div>
                    <div style="font-size:14px;font-weight:800;color:#fff"><?= htmlspecialchars($nav_user['first_name'].' '.$nav_user['last_name']) ?></div>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">Individual Member</div>
                </div>
            </div>
            <?php endif; ?>

            <a href="<?= $base ?>/"   onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Home</a>
            <a href="#services"       onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>Services</a>
            <a href="<?= $base ?>/pricing.php" onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>Pricing</a>
            <a href="#booking"        onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Book a Service</a>
            <a href="#about"          onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>About</a>
            <a href="#contact"        onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Contact</a>

            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>

            <?php if ($is_logged_in && $nav_user): ?>
                <a href="<?= $base ?>/pages/dashboard.php" onclick="closeMobileMenu()" class="mob-item" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff;font-weight:700"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>My Dashboard</a>
                <a href="<?= $base ?>/pages/dashboard.php?panel=bookings" onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>My Bookings</a>
                <a href="<?= $base ?>/pages/dashboard.php?panel=vehicle" onclick="closeMobileMenu()" class="mob-item"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>My Vehicle</a>
                <a href="<?= $base ?>/pages/logout.php" onclick="closeMobileMenu()" class="mob-item danger"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out</a>
            <?php else: ?>
                <a href="<?= $base ?>/pages/login.php" onclick="closeMobileMenu()" class="mob-item" style="border:1.5px solid #e5e7eb;justify-content:center;font-weight:600"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>Sign In</a>
                <a href="<?= $base ?>/pages/signup.php" onclick="closeMobileMenu()" class="mob-item" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-weight:700;justify-content:center;box-shadow:0 2px 10px rgba(220,38,38,0.3)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>Create Free Account</a>
            <?php endif; ?>

            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
            <a href="https://wa.me/265993575111" target="_blank" onclick="closeMobileMenu()" class="mob-item" style="background:#f0fdf4;color:#15803d;font-weight:600;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>WhatsApp Us</a>
            <a href="tel:+265993575111" onclick="closeMobileMenu()" class="mob-item" style="background:#eff6ff;color:#2563eb;font-weight:600;justify-content:center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Call +265 993 575 111</a>
        </div>
    </div>
</nav>

<script>
function toggleMobileMenu(){
    var m=document.getElementById('mobile-menu');
    var o=document.getElementById('menuIconOpen');
    var c=document.getElementById('menuIconClose');
    var isOpen=m.classList.toggle('open');
    o.style.display=isOpen?'none':'block';
    c.style.display=isOpen?'block':'none';
}
function closeMobileMenu(){
    var m=document.getElementById('mobile-menu');
    m.classList.remove('open');
    document.getElementById('menuIconOpen').style.display='block';
    document.getElementById('menuIconClose').style.display='none';
}
</script>

<script>
function toggleDropdown() {
    const wrap = document.getElementById('avatarWrap');
    if (!wrap) return;
    const isOpen = wrap.classList.toggle('open');
    const btn = document.getElementById('avatarBtn');
    if (btn) btn.setAttribute('aria-expanded', isOpen);
}
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('avatarWrap');
    if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { const w = document.getElementById('avatarWrap'); if(w) w.classList.remove('open'); }
});
</script>

<!-- ═══ HERO ═══ -->
<div id="hero" class="hero-bg hero-bg-image relative overflow-hidden min-h-screen flex items-center">
    <div class="hero-light light1"></div>
    <div class="hero-light light2"></div>
    <div class="hero-light light3"></div>
    <div class="speed-lines"></div>
    <div class="absolute inset-0 opacity-10"><div class="absolute inset-0 hero-overlay-pattern"></div></div>

    <div class="max-w-7xl mx-auto relative z-10 px-4 sm:px-6 lg:px-8 w-full py-24">
        <div class="text-center" data-aos="fade-up">
            <!-- Badge -->
            <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md border border-white/20 rounded-full px-4 py-2 mb-6">
                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                <span class="text-white/90 text-sm font-semibold">Open Today · 8:00 AM – 5:00 PM</span>
            </div>

            <h1 class="main-title">Blantyre's Most Trusted<br><span style="color:#f87171">Auto Workshop</span></h1>
            <p class="text-xl md:text-2xl text-gray-100 mb-10 max-w-3xl mx-auto leading-relaxed text-shadow">
                Premium auto parts, expert repairs, and professional vehicle services — all in one place in Malawi.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="#booking" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-red-600 to-red-700 text-white text-lg font-bold rounded-2xl shadow-2xl hover:-translate-y-2 transform transition-all duration-300">
                    <i data-feather="calendar" class="w-6 h-6 mr-3"></i>
                    Book a Service
                </a>
                <a href="#services" class="inline-flex items-center px-8 py-4 bg-white/10 backdrop-blur-md text-white text-lg font-bold rounded-2xl border-2 border-white/30 hover:bg-white/20 hover:-translate-y-2 transform transition-all duration-300">
                    <i data-feather="tool" class="w-6 h-6 mr-3"></i>
                    Our Services
                </a>
                <a href="tel:+265993575111" class="inline-flex items-center px-8 py-4 bg-white/10 backdrop-blur-md text-white text-lg font-bold rounded-2xl border-2 border-white/30 hover:bg-white/20 hover:-translate-y-2 transform transition-all duration-300">
                    <i data-feather="phone" class="w-6 h-6 mr-3"></i>
                    Call Us Now
                </a>
            </div>

            <!-- UPGRADE 1: Animated Stats Bar -->
            <div class="hero-stats-bar" data-aos="fade-up" data-aos-delay="300">
                <div class="hero-stat">
                    <div class="hero-stat-number"><span class="count-up" data-target="10">0</span><span>+</span></div>
                    <div class="hero-stat-label">Years Experience</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-number"><span class="count-up" data-target="5000">0</span><span>+</span></div>
                    <div class="hero-stat-label">Vehicles Serviced</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-number"><span class="count-up" data-target="120">0</span><span>+</span></div>
                    <div class="hero-stat-label">5-Star Reviews</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-number"><span class="count-up" data-target="99">0</span><span>%</span></div>
                    <div class="hero-stat-label">Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ SERVICES ═══ -->
<section id="services" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">What We Do</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Our Services</h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto mb-5">Professional automotive services tailored to your needs</p>
            <a href="<?= $base ?>/pricing.php" class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-semibold text-sm hover:bg-black transition shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                View Full Price List
            </a>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="100">
                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="tool" class="w-8 h-8 text-red-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">General Maintenance</h3>
                <p class="text-gray-600 mb-6">Oil changes, filter replacements, and comprehensive vehicle inspections by certified technicians.</p>
                <a href="#booking" onclick="preselectService('General Maintenance')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="200">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="settings" class="w-8 h-8 text-blue-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Engine Repair</h3>
                <p class="text-gray-600 mb-6">Expert engine diagnostics, repair, and performance optimization by certified technicians.</p>
                <a href="#booking" onclick="preselectService('Engine Repair')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="300">
                <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="droplet" class="w-8 h-8 text-green-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Auto Detailing</h3>
                <p class="text-gray-600 mb-6">Professional interior and exterior detailing to keep your vehicle looking brand new.</p>
                <a href="#booking" onclick="preselectService('Auto Detailing')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="400">
                <div class="w-16 h-16 bg-yellow-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="disc" class="w-8 h-8 text-yellow-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Brake Services</h3>
                <p class="text-gray-600 mb-6">Complete brake inspection, pad replacement, and system maintenance for optimal safety.</p>
                <a href="#booking" onclick="preselectService('Brake Services')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="500">
                <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="wind" class="w-8 h-8 text-purple-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">AC Repair</h3>
                <p class="text-gray-600 mb-6">Air conditioning system diagnostics, repair, and recharging services.</p>
                <a href="#booking" onclick="preselectService('AC Repair')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border border-gray-100" data-aos="fade-up" data-aos-delay="600">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mb-4"><i data-feather="package" class="w-8 h-8 text-indigo-600"></i></div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Parts Supply</h3>
                <p class="text-gray-600 mb-6">Wide selection of genuine and aftermarket auto parts with expert guidance.</p>
                <a href="#booking" onclick="preselectService('Parts Supply')" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-indigo-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Get Quote <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
            <div class="service-card bg-white rounded-3xl shadow-xl p-8 border-2 border-red-200" data-aos="fade-up" data-aos-delay="700" style="background:linear-gradient(135deg,#fff 0%,#fef5f5 100%)">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
                    <span style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-size:10px;font-weight:800;padding:5px 12px;border-radius:99px;letter-spacing:0.08em;text-transform:uppercase;box-shadow:0 4px 12px rgba(220,38,38,0.25)">Premium</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Vehicle Wrapping</h3>
                <p class="text-gray-600 mb-6">Premium vinyl wraps — full colour changes, gloss, matte, carbon fibre finishes, and paint protection film.</p>
                <a href="#booking" onclick="preselectService('Vehicle Wrapping')" class="inline-flex items-center gap-2 bg-red-600 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:bg-red-700 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">Book Now <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
            </div>
        </div>
    </div>
</section>

<!-- ═══ BOOKING ═══ -->
<section id="booking" class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">Quick Booking</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Book in 30 Seconds</h2>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">Just 4 quick details — we'll call you to confirm and grab the rest.</p>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl p-6 md:p-10" data-aos="fade-up" data-aos-delay="150">
            <form id="bookingForm">
                <!-- Single step - all fields visible -->
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Your Name *</label>
                        <input type="text" id="bookingName" name="name" value="<?= $autofill['name'] ?>" placeholder="Daniel Kumwenda" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none transition-all" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                        <input type="tel" id="bookingPhone" name="phone" value="<?= $autofill['phone'] ?>" placeholder="+265 993 575 111" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none transition-all" required>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">What service do you need? *</label>
                    <select id="bookingService" name="services" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none transition-all bg-white" required>
                        <option value="">Choose a service</option>
                        <option>General Maintenance</option>
                        <option>Engine Repair</option>
                        <option>Brake Services</option>
                        <option>Auto Detailing</option>
                        <option>AC Repair</option>
                        <option>Tyre Services</option>
                        <option>Battery Services</option>
                        <option>Vehicle Wrapping</option>
                        <option>Parts Supply</option>
                        <option>Not sure — please advise</option>
                    </select>
                </div>

                <div class="grid md:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preferred Date *</label>
                        <input type="date" id="bookingDate" name="date" min="<?= date('Y-m-d') ?>" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none transition-all" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Preferred Time *</label>
                        <select id="bookingTime" name="time" class="w-full p-4 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none transition-all bg-white" required>
                            <option value="">Select time</option>
                            <option value="08:00">Morning (8AM-12PM)</option>
                            <option value="13:00">Afternoon (1PM-5PM)</option>
                        </select>
                    </div>
                </div>

                <!-- Optional vehicle/notes (collapsed by default) -->
                <div class="mb-5">
                    <button type="button" id="moreDetailsToggle" class="text-sm font-semibold text-red-600 hover:text-red-700 flex items-center gap-2" onclick="toggleMoreDetails()">
                        <span id="moreDetailsIcon">+</span> Add vehicle details (optional)
                    </button>
                    <div id="moreDetails" style="display:none;margin-top:16px">
                        <div class="grid md:grid-cols-3 gap-3 mb-3">
                            <input type="text" id="vehicleMake" name="make" placeholder="Make (Toyota)" class="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm">
                            <input type="text" id="vehicleModel" name="model" placeholder="Model (Hilux)" class="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm">
                            <input type="number" id="vehicleYear" name="year" placeholder="Year" min="1980" max="<?= date('Y') ?>" class="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm">
                        </div>
                        <input type="text" id="vehicleRegistration" name="registration" placeholder="Registration Number (optional)" class="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm mb-3">
                        <textarea id="bookingNotes" name="notes" rows="3" placeholder="Any specific issues or concerns..." class="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none text-sm resize-none"></textarea>
                    </div>
                </div>

                <!-- Hidden email for logged-in users -->
                <input type="hidden" id="bookingEmail" name="email" value="<?= $autofill['email'] ?>">

                <?php if($is_logged_in && $offer_eligible): ?>
                <!-- First-Time Customer Offer Toggle (only shown to eligible users) -->
                <div id="offerBlock" style="background:linear-gradient(135deg,#fef3c7 0%,#fef9e7 100%);border:2px solid #fbbf24;border-radius:14px;padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
                    <input type="checkbox" id="claimOfferCheckbox" name="claim_offer" value="1" style="width:22px;height:22px;accent-color:#dc2626;cursor:pointer;flex-shrink:0">
                    <label for="claimOfferCheckbox" style="cursor:pointer;flex:1;font-size:14px;color:#78350f">
                        <strong style="font-weight:800;color:#92400e">🎉 Apply my 10% first-time discount</strong><br>
                        <span style="font-size:12px;color:#a16207">One-time offer for new customers — can't be combined with other discounts.</span>
                    </label>
                </div>
                <?php endif; ?>

                <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-4 px-8 rounded-xl font-bold text-lg shadow-xl hover:shadow-2xl hover:-translate-y-0.5 transform transition-all duration-200 flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm Booking
                </button>

                <p class="text-xs text-gray-500 text-center mt-4">We'll WhatsApp you within an hour to confirm your appointment.</p>
            </form>
            <div id="bookingMessage" class="mt-6 text-center hidden"></div>
        </div>

        <!-- Quick Contact Options -->
        <div class="mt-8 text-center" data-aos="fade-up" data-aos-delay="300">
            <p class="text-gray-600 mb-4">Or reach us directly:</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="https://wa.me/265993575111?text=<?= urlencode('Hi, I need a quote for a service.') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center bg-green-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-green-700 shadow-lg transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    Get Free Quote
                </a>
                <a href="tel:+265993575111" class="inline-flex items-center justify-center bg-red-600 text-white py-3 px-6 rounded-xl font-semibold hover:bg-red-700 shadow-lg transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Call Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ═══ WHY CHOOSE US ═══ -->
<section class="py-20 bg-gradient-to-br from-red-600 to-red-700 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <h2 class="text-4xl md:text-5xl font-bold mb-4">Why Choose Wiyule Motors?</h2>
            <p class="text-xl text-red-100 max-w-2xl mx-auto">We're committed to excellence in every aspect of our service</p>
        </div>
        <div class="grid md:grid-cols-4 gap-8">
            <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4"><i data-feather="award" class="w-10 h-10"></i></div>
                <h3 class="text-xl font-bold mb-2">Certified Experts</h3>
                <p class="text-red-100">Professionally trained and certified technicians</p>
            </div>
            <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4"><i data-feather="shield" class="w-10 h-10"></i></div>
                <h3 class="text-xl font-bold mb-2">Quality Guarantee</h3>
                <p class="text-red-100">All work backed by our satisfaction guarantee</p>
            </div>
            <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4"><i data-feather="dollar-sign" class="w-10 h-10"></i></div>
                <h3 class="text-xl font-bold mb-2">Fair Pricing</h3>
                <p class="text-red-100">Transparent pricing with no hidden fees</p>
            </div>
            <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                <div class="w-20 h-20 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-4"><i data-feather="clock" class="w-10 h-10"></i></div>
                <h3 class="text-xl font-bold mb-2">Fast Service</h3>
                <p class="text-red-100">Quick turnaround without compromising quality</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══ ABOUT ═══ -->
<section id="about" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div data-aos="fade-right">
                <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">Our Story</span>
                <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">About Wiyule Motors</h2>
                <p class="text-lg text-gray-600 mb-6 leading-relaxed">Since 2016, Wiyule Motors has been Blantyre's trusted destination for premium automotive parts and professional vehicle services. We combine expertise, quality, and customer care to keep your vehicle running smoothly.</p>
                <p class="text-lg text-gray-600 mb-8 leading-relaxed">Our team of certified technicians uses state-of-the-art equipment and genuine parts to deliver exceptional service for all makes and models.</p>

                <!-- UPGRADE 7: Count-up stats -->
                <div class="grid grid-cols-3 gap-6">
                    <div class="text-center p-4 bg-gray-50 rounded-2xl">
                        <div class="text-4xl font-bold text-red-600 mb-1"><span class="count-up" data-target="10">0</span>+</div>
                        <div class="text-sm text-gray-600 font-medium">Years Experience</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-2xl">
                        <div class="text-4xl font-bold text-red-600 mb-1"><span class="count-up" data-target="5000">0</span>+</div>
                        <div class="text-sm text-gray-600 font-medium">Vehicles Served</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 rounded-2xl">
                        <div class="text-4xl font-bold text-red-600 mb-1"><span class="count-up" data-target="99">0</span>%</div>
                        <div class="text-sm text-gray-600 font-medium">Satisfaction</div>
                    </div>
                </div>
            </div>
            <div data-aos="fade-left">
                <img loading="lazy"
                     src="<?= $base ?>/assets/images/workshop_navara.jpg"
                     alt="Wiyule Motors workshop"
                     class="rounded-3xl shadow-2xl w-full object-cover">
            </div>
        </div>
    </div>
</section>

<!-- ═══ TESTIMONIALS ═══ -->
<section class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">120+ Reviews</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">What Our Clients Say</h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Don't just take our word for it — hear from our satisfied customers</p>
        </div>

        <!-- UPGRADE 4: Carousel -->
        <div class="relative" data-aos="fade-up" data-aos-delay="100">
            <div class="testimonial-carousel overflow-hidden">
                <div class="testimonial-track" id="testimonialTrack">
                    <?php
                    $testimonials = [
                        ['initials'=>'JM','color'=>'bg-red-600',  'name'=>'James Mwale',    'role'=>'Business Owner','text'=>'Excellent service! The team at Wiyule Motors fixed my engine issue quickly and professionally. Their prices are fair and they explained everything clearly. Highly recommended!'],
                        ['initials'=>'TK','color'=>'bg-blue-600', 'name'=>'Thandie Kumwenda','role'=>'Teacher',       'text'=>'Best auto service in Blantyre! I\'ve been bringing my Toyota here for 3 years. Always reliable, honest, and great customer service. My go-to place for all car needs.'],
                        ['initials'=>'PC','color'=>'bg-green-600','name'=>'Peter Chirwa',   'role'=>'Accountant',    'text'=>'Professional and trustworthy. They have genuine parts and their mechanics really know what they\'re doing. Fair prices and excellent work. I wouldn\'t go anywhere else!'],
                        ['initials'=>'AM','color'=>'bg-purple-600','name'=>'Alice Msowoya', 'role'=>'Nurse',         'text'=>'I was nervous about finding a trustworthy mechanic in Blantyre but Wiyule Motors exceeded my expectations. Fast, honest and affordable. Strongly recommend!'],
                        ['initials'=>'BN','color'=>'bg-orange-600','name'=>'Bright Nkosi',  'role'=>'Driver',        'text'=>'Fixed my brakes same day and even called to follow up after. Outstanding customer care — you can tell they genuinely care about their customers.'],
                    ];
                    foreach($testimonials as $t): ?>
                    <div class="testimonial-slide px-3">
                        <div class="testimonial-card bg-white rounded-3xl p-8 shadow-xl h-full">
                            <div class="flex text-yellow-400 mb-4">
                                <?php for($i=0;$i<5;$i++): ?>
                                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                <?php endfor; ?>
                            </div>
                            <p class="text-gray-600 mb-6 italic leading-relaxed">"<?= $t['text'] ?>"</p>
                            <div class="flex items-center">
                                <div class="w-12 h-12 <?= $t['color'] ?> rounded-full flex items-center justify-center text-white font-bold text-lg"><?= $t['initials'] ?></div>
                                <div class="ml-4">
                                    <p class="font-bold text-gray-900"><?= $t['name'] ?></p>
                                    <p class="text-sm text-gray-500"><?= $t['role'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Controls -->
            <div class="flex items-center justify-center gap-4 mt-8">
                <button class="carousel-arrow" onclick="carouselPrev()" aria-label="Previous">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="flex gap-2" id="carouselDots"></div>
                <button class="carousel-arrow" onclick="carouselNext()" aria-label="Next">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ═══ GALLERY ═══ -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">Our Workshop</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Our Facility</h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">State-of-the-art equipment and a professional workspace</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="100">
                <img loading="lazy" src="<?= $base ?>/assets/images/servicing.jpeg" alt="Vehicle servicing at Wiyule Motors"
                     class="gallery-img hover:scale-110 transition-transform duration-500"
                     onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80';">
            </div>
            <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="200">
                <img loading="lazy" src="<?= $base ?>/assets/images/car-alarm.jpeg" alt="Car electronics at Wiyule Motors"
                     class="gallery-img hover:scale-110 transition-transform duration-500"
                     onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?w=800&q=80';">
            </div>
            <div class="rounded-3xl overflow-hidden shadow-xl" data-aos="fade-up" data-aos-delay="300">
                <img loading="lazy" src="<?= $base ?>/assets/images/polishing.jpeg" alt="Car polishing at Wiyule Motors"
                     class="gallery-img hover:scale-110 transition-transform duration-500"
                     onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1607860108855-64acf2078ed9?w=800&q=80';">
            </div>
        </div>
    </div>
</section>

<!-- ═══ FAQ ═══ -->
<section class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">Got Questions?</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
            <p class="text-xl text-gray-600">Everything you need to know about our services</p>
        </div>
        <div class="space-y-4" data-aos="fade-up" data-aos-delay="200">
            <?php
            $faqs = [
                ['What are your operating hours?','We\'re open Monday to Friday from 8:00 AM to 5:00 PM, and Saturdays from 8:00 AM to 2:00 PM. We\'re closed on Sundays and public holidays.'],
                ['Do I need to book an appointment?','While walk-ins are welcome, we recommend booking an appointment to ensure minimal wait times. You can book online or call us at +265 993 575 111.'],
                ['Do you use genuine parts?','Yes! We stock both genuine OEM parts and high-quality aftermarket alternatives. We\'ll always discuss your options and help you choose the best solution for your vehicle and budget.'],
                ['What payment methods do you accept?','We accept cash and mobile money payments (Airtel Money, TNM Mpamba). We also accept bank transfers for larger transactions.'],
                ['Do you offer warranties on your work?','Absolutely! All our services come with a warranty. The duration varies depending on the type of service or part, but we stand behind our work 100%.'],
                ['How long does a typical service take?','A basic oil change takes 30–45 minutes, while more extensive repairs may take several hours or days. We\'ll always give you an estimated timeframe when you book.'],
            ];
            foreach($faqs as $faq): ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <button class="faq-question w-full text-left p-6 focus:outline-none hover:bg-gray-50 transition-colors" onclick="toggleFAQ(this)">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-900"><?= $faq[0] ?></h3>
                        <i data-feather="chevron-down" class="w-6 h-6 text-gray-600 transition-transform flex-shrink-0 ml-4"></i>
                    </div>
                </button>
                <div class="faq-answer max-h-0 overflow-hidden transition-all duration-300">
                    <div class="p-6 pt-0 text-gray-600"><?= $faq[1] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ CONTACT ═══ -->
<section id="contact" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="inline-block bg-red-100 text-red-600 text-sm font-bold px-4 py-1 rounded-full mb-4">Get In Touch</span>
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">We're Here to Help</h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">Have questions? We'd love to hear from you.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-12">
            <div data-aos="fade-right" data-aos-delay="200">
                <div class="space-y-6">
                    <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                        <div class="w-14 h-14 bg-red-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0"><i data-feather="phone" class="w-7 h-7"></i></div>
                        <div><h4 class="font-semibold text-lg text-gray-900 mb-1">Phone</h4><p class="text-lg font-semibold text-gray-900">+265 993 575 111</p></div>
                    </div>
                    <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                        <div class="w-14 h-14 bg-blue-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0"><i data-feather="map-pin" class="w-7 h-7"></i></div>
                        <div><h4 class="font-semibold text-lg text-gray-900 mb-1">Location</h4><p class="text-lg font-semibold text-gray-900">Nyambadwe, Blantyre<br>Southern Region, Malawi</p></div>
                    </div>
                    <div class="flex items-start space-x-4 p-6 bg-gray-50 rounded-2xl hover:bg-white hover:shadow-lg transition-all duration-300">
                        <div class="w-14 h-14 bg-green-600 text-white rounded-2xl flex items-center justify-center flex-shrink-0"><i data-feather="clock" class="w-7 h-7"></i></div>
                        <div>
                            <h4 class="font-semibold text-lg text-gray-900 mb-1">Business Hours</h4>
                            <p class="text-gray-700">Mon – Fri: 8:00 AM – 5:00 PM</p>
                            <p class="text-gray-700">Sat: 8:00 AM – 2:00 PM</p>
                            <p class="text-gray-700">Sun: Closed</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-6">
                    <a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center bg-green-600 text-white py-4 px-6 rounded-2xl font-bold hover:bg-green-700 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                        <i data-feather="message-circle" class="w-5 h-5 mr-2"></i>WhatsApp Now
                    </a>
                    <a href="tel:+265993575111" class="flex items-center justify-center bg-red-600 text-white py-4 px-6 rounded-2xl font-bold hover:bg-red-700 shadow-xl hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
                        <i data-feather="phone" class="w-5 h-5 mr-2"></i>Call Now
                    </a>
                </div>
            </div>

            <div data-aos="fade-left" data-aos-delay="400">
                <div class="bg-gray-50 rounded-3xl p-8 md:p-12 shadow-2xl">
                    <h3 class="text-2xl font-bold text-gray-900 mb-8 text-center">Send us a Message</h3>
                    <form id="contactForm" class="space-y-4">
                        <input type="text"  id="contactName"    name="name"    placeholder="Your Name"    class="w-full p-4 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300" required>
                        <input type="email" id="contactEmail"   name="email"   placeholder="Your Email"   class="w-full p-4 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300" required>
                        <input type="tel"   id="contactPhone"   name="phone"   placeholder="Phone Number" class="w-full p-4 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300">
                        <textarea id="contactMessage" name="message" rows="5" placeholder="Tell us about your vehicle or service needed..." class="w-full p-4 border-2 border-gray-200 rounded-2xl focus:border-red-500 transition-all duration-300 resize-vertical" required></textarea>
                        <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-5 px-8 rounded-2xl font-bold text-xl shadow-2xl hover:shadow-3xl hover:-translate-y-1 transform transition-all duration-300">Send Message</button>
                    </form>
                    <div id="formMessage" class="mt-4 text-center hidden"></div>
                </div>
            </div>
        </div>

        <!-- Google Maps -->
        <div style="margin-top:40px;border-radius:24px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.1)" data-aos="fade-up" data-aos-delay="500">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15349.2!2d35.0!3d-15.78!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x18d844e4dff7e0b5%3A0x7e5a5b0d0c0f0a0a!2sNyambadwe%2C%20Blantyre%2C%20Malawi!5e0!3m2!1sen!2smw!4v1700000000000!5m2!1sen!2smw" width="100%" height="300" style="border:0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="bg-gradient-to-br from-red-600 to-red-700 p-8 rounded-t-2xl text-white text-center">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-feather="check-circle" class="w-12 h-12 text-green-600"></i>
            </div>
            <h3 class="text-3xl font-bold mb-2">Booking Confirmed!</h3>
            <p class="text-red-100">Your appointment has been successfully scheduled</p>
        </div>
        <div class="p-8" id="modalContent"></div>
        <div class="p-6 bg-gray-50 rounded-b-2xl">
            <button onclick="closeModal()" class="w-full bg-red-600 text-white py-4 px-6 rounded-xl font-bold text-lg hover:bg-red-700 transition-all duration-300">Close</button>
        </div>
    </div>
</div>

<!-- ═══ Footer ═══ -->
<footer class="bg-gray-900 text-white py-12" style="padding-bottom:80px">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-4 gap-8 mb-8">
            <div>
                <h3 class="text-xl font-bold mb-4">Wiyule Motors</h3>
                <p class="text-gray-400 mb-4">Premium automotive parts and services in Blantyre, Malawi since 2016.</p>
                <div class="flex gap-3">
                    <a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-green-600 transition-all duration-200" title="WhatsApp">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    </a>
                    <a href="https://www.facebook.com/wiyulemotors" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-blue-600 transition-all duration-200" title="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/wiyulemotors" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-pink-600 transition-all duration-200" title="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/@wiyulemotors" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-gray-700 transition-all duration-200" title="TikTok">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                    </a>
                    <a href="tel:+265993575111" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-red-600 transition-all duration-200" title="Call Us">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="#hero"     class="text-gray-400 hover:text-white transition">Home</a></li>
                    <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                    <li><a href="<?= $base ?>/pricing.php" class="text-gray-400 hover:text-white transition">Pricing</a></li>
                    <li><a href="#booking"  class="text-gray-400 hover:text-white transition">Book Now</a></li>
                    <li><a href="#about"    class="text-gray-400 hover:text-white transition">About</a></li>
                    <li><a href="#contact"  class="text-gray-400 hover:text-white transition">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Contact</h3>
                <ul class="space-y-3 text-gray-400">
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>Nyambadwe, Blantyre</li>
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>+265 993 575 111</li>
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Mon–Fri: 8AM–5PM · Sat: 8AM–1PM</li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Legal</h3>
                <ul class="space-y-2">
                    <li><a href="<?= $base ?>/terms.php"   class="text-gray-400 hover:text-white transition">Terms of Service</a></li>
                    <li><a href="<?= $base ?>/privacy.php" class="text-gray-400 hover:text-white transition">Privacy Policy</a></li>
                </ul>
                <div style="margin-top:20px">
                    <h4 class="text-sm font-bold text-gray-500 mb-2">FIND US</h4>
                    <a href="https://maps.google.com/?q=Nyambadwe+Blantyre+Malawi" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-red-400 transition text-sm flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Open in Google Maps →
                    </a>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
            <p>&copy; <?= date('Y') ?> Wiyule Motors. All rights reserved. Built with care in Malawi.</p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
    AOS.init({ duration:1000, once:true, offset:100 });

    // Mobile menu now handled by inline onclick — no JS needed here

    // Booking min date
    const bookingDate = document.getElementById('bookingDate');
    if (bookingDate) bookingDate.setAttribute('min', new Date().toISOString().split('T')[0]);

    // Clear red borders when user starts typing
    document.querySelectorAll('#bookingForm input, #bookingForm select, #bookingForm textarea').forEach(function(input) {
        input.addEventListener('focus', function() {
            this.style.borderColor = '#dc2626';
            clearStepError();
        });
        input.addEventListener('blur', function() {
            if (!this.classList.contains('invalid')) this.style.borderColor = '#e5e7eb';
        });
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // Contact form
    const contactForm = document.getElementById('contactForm');
    const formMessage  = document.getElementById('formMessage');
    if (contactForm) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = contactForm.querySelector('button[type="submit"]');
            const orig = btn.innerHTML;
            btn.innerHTML = 'Sending…'; btn.disabled = true;
            try {
                const name = contactForm.querySelector('#contactName').value.trim();
                const email = contactForm.querySelector('#contactEmail').value.trim();
                const phone = contactForm.querySelector('#contactPhone').value.trim();
                const msg = contactForm.querySelector('#contactMessage').value.trim();
                if (!name || !msg) { formMessage.textContent = 'Please fill in your name and message.'; formMessage.className = 'mt-4 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl'; formMessage.classList.remove('hidden'); btn.innerHTML = orig; btn.disabled = false; return; }
                var waText = '📩 *New Message from Website*\n\n'
                    + '👤 *Name:* ' + name + '\n'
                    + (email ? '📧 *Email:* ' + email + '\n' : '')
                    + (phone ? '📞 *Phone:* ' + phone + '\n' : '')
                    + '\n💬 *Message:*\n' + msg;
                window.open('https://wa.me/265993575111?text=' + encodeURIComponent(waText), '_blank');
                formMessage.textContent = 'Opening WhatsApp to send your message...';
                formMessage.className = 'mt-4 text-center text-green-600 font-semibold p-4 bg-green-50 rounded-xl';
                formMessage.classList.remove('hidden');
                contactForm.reset();
            } catch {
                formMessage.textContent = 'Could not open WhatsApp. Please call us at +265 993 575 111.';
                formMessage.className = 'mt-4 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                formMessage.classList.remove('hidden');
            }
            btn.innerHTML = orig; btn.disabled = false;
            feather.replace();
            setTimeout(() => formMessage.classList.add('hidden'), 5000);
        });
    }

    // Booking form
    const bookingForm    = document.getElementById('bookingForm');
    const bookingMessage = document.getElementById('bookingMessage');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(bookingForm);
            // Make sure we have required fields
            if (!formData.get('name') || !formData.get('phone') || !formData.get('services') || !formData.get('date') || !formData.get('time')) {
                bookingMessage.textContent = 'Please fill in all required fields.';
                bookingMessage.className   = 'mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                bookingMessage.classList.remove('hidden');
                setTimeout(() => bookingMessage.classList.add('hidden'), 4000);
                return;
            }
            // Default missing vehicle fields so DB accepts
            if (!formData.get('make'))  formData.set('make', 'TBD');
            if (!formData.get('model')) formData.set('model', 'TBD');
            if (!formData.get('year'))  formData.set('year', new Date().getFullYear());

            const btn  = bookingForm.querySelector('button[type="submit"]');
            const orig = btn.innerHTML;
            btn.innerHTML = 'Processing…'; btn.disabled = true;
            try {
                const res    = await fetch('<?= $base ?>/pages/process-booking.php', { method:'POST', body:formData });
                const result = await res.json();
                if (result.success) {
                    showBookingModal(result.data);
                    bookingForm.reset();
                    document.getElementById('moreDetails').style.display = 'none';
                    document.getElementById('moreDetailsIcon').textContent = '+';
                } else {
                    bookingMessage.textContent = result.message || 'Something went wrong. Please call us at +265 993 575 111.';
                    bookingMessage.className   = 'mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                    bookingMessage.classList.remove('hidden');
                    setTimeout(() => bookingMessage.classList.add('hidden'), 6000);
                }
            } catch {
                bookingMessage.textContent = 'Could not connect. Please call us at +265 993 575 111.';
                bookingMessage.className   = 'mt-6 text-center text-red-600 font-semibold p-4 bg-red-50 rounded-xl';
                bookingMessage.classList.remove('hidden');
                setTimeout(() => bookingMessage.classList.add('hidden'), 6000);
            }
            btn.innerHTML = orig; btn.disabled = false;
            feather.replace();
        });
    }

    // Toggle optional vehicle details
    window.toggleMoreDetails = function() {
        var d = document.getElementById('moreDetails');
        var i = document.getElementById('moreDetailsIcon');
        var open = d.style.display !== 'none';
        d.style.display = open ? 'none' : 'block';
        i.textContent = open ? '+' : '−';
    };

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                document.querySelector(href).scrollIntoView({ behavior:'smooth', block:'start' });
            }
        });
    });

    // UPGRADE 4: Testimonial carousel init
    initCarousel();

    // UPGRADE 7: Count-up on scroll
    initCountUp();

    // Recalculate carousel on resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(initCarousel, 250);
    });
});

// ── Pre-select service from "Book Now" buttons ───────────────
function preselectService(serviceName) {
    setTimeout(function() {
        var select = document.getElementById('bookingService');
        if (select) {
            // Match option text
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].text === serviceName || select.options[i].value === serviceName) {
                    select.selectedIndex = i;
                    break;
                }
            }
        }
    }, 600);
}

// ── FAQ ───────────────────────────────────────────────────────
function toggleFAQ(button) {
    const answer = button.nextElementSibling;
    const icon   = button.querySelector('svg');
    const isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
    document.querySelectorAll('.faq-answer').forEach(i => i.style.maxHeight = '0');
    document.querySelectorAll('.faq-question svg').forEach(i => i.style.transform = 'rotate(0deg)');
    if (!isOpen) { answer.style.maxHeight = answer.scrollHeight + 'px'; icon.style.transform = 'rotate(180deg)'; }
}

// ── XSS protection ───────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#x27;');
}

// ── Booking modal ─────────────────────────────────────────────
function showBookingModal(data) {
    const dateObj       = new Date(data.date + 'T00:00:00');
    const formattedDate = dateObj.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });

    // Build WhatsApp message with booking details
    var waMsg = '🔧 *NEW BOOKING — Wiyule Motors*\n\n'
        + '👤 *Customer:* ' + data.name + '\n'
        + '📞 *Phone:* ' + data.phone + '\n'
        + (data.email ? '📧 *Email:* ' + data.email + '\n' : '')
        + '\n🚗 *Vehicle:* ' + data.vehicle.year + ' ' + data.vehicle.make + ' ' + data.vehicle.model + '\n'
        + (data.vehicle.registration ? '🔢 *Reg:* ' + data.vehicle.registration + '\n' : '')
        + '\n🛠 *Services:* ' + data.services.join(', ') + '\n'
        + '📅 *Date:* ' + formattedDate + '\n'
        + '🕐 *Time:* ' + formatTime(data.time) + '\n'
        + (data.notes ? '\n📝 *Notes:* ' + data.notes + '\n' : '')
        + (data.first_time_discount == 1 ? '\n🎉 *FIRST-TIME 10% DISCOUNT APPLIED*\n' : '')
        + '\n✅ Booked via wiyulemotors.wuaze.com';

    var waLink = 'https://wa.me/265993575111?text=' + encodeURIComponent(waMsg);

    document.getElementById('modalContent').innerHTML = `
        <div class="space-y-5">
            <div class="border-b border-gray-200 pb-4">
                <h4 class="font-semibold text-gray-700 mb-2">Customer Details</h4>
                <p class="font-semibold text-gray-900">${escHtml(data.name)}</p>
                <p class="text-gray-600">${escHtml(data.phone)}</p>
                ${data.email ? `<p class="text-gray-600">${escHtml(data.email)}</p>` : ''}
            </div>
            <div class="border-b border-gray-200 pb-4">
                <h4 class="font-semibold text-gray-700 mb-2">Vehicle</h4>
                <p class="font-semibold text-gray-900">${escHtml(data.vehicle.year)} ${escHtml(data.vehicle.make)} ${escHtml(data.vehicle.model)}</p>
                ${data.vehicle.registration ? `<p class="text-gray-600">Reg: ${escHtml(data.vehicle.registration)}</p>` : ''}
            </div>
            <div class="border-b border-gray-200 pb-4">
                <h4 class="font-semibold text-gray-700 mb-2">Services</h4>
                <ul>${data.services.map(s=>`<li class="text-gray-900">• ${escHtml(s)}</li>`).join('')}</ul>
            </div>
            <div class="border-b border-gray-200 pb-4">
                <h4 class="font-semibold text-gray-700 mb-2">Appointment</h4>
                <p class="font-semibold text-gray-900">${escHtml(formattedDate)}</p>
                <p class="text-gray-900">at ${escHtml(formatTime(data.time))}</p>
            </div>
            ${data.notes ? `<div class="border-b border-gray-200 pb-4"><h4 class="font-semibold text-gray-700 mb-2">Notes</h4><p class="text-gray-600">${escHtml(data.notes)}</p></div>` : ''}
            <a href="${waLink}" target="_blank" rel="noopener noreferrer" style="display:flex;align-items:center;justify-content:center;gap:10px;background:#25d366;color:#fff;font-weight:700;font-size:15px;padding:14px 20px;border-radius:14px;text-decoration:none;box-shadow:0 4px 16px rgba(37,211,102,0.35);transition:all 0.2s" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                Confirm via WhatsApp
            </a>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <p class="text-sm text-blue-900">&#9432; Tap the button above to send your booking details via WhatsApp. We'll confirm your appointment shortly.</p>
            </div>
        </div>`;
    document.getElementById('bookingModal').classList.add('active');
}
function closeModal() { document.getElementById('bookingModal').classList.remove('active'); }
function formatTime(t) {
    const [h,m] = t.split(':'); const hr = parseInt(h);
    return `${hr>12?hr-12:(hr===0?12:hr)}:${m} ${hr>=12?'PM':'AM'}`;
}
window.addEventListener('click', e => { const mo = document.getElementById('bookingModal'); if(mo && e.target===mo) closeModal(); });

// ── UPGRADE 4: Testimonial Carousel ──────────────────────────
function initCarousel() {
    const track  = document.getElementById('testimonialTrack');
    const dotsEl = document.getElementById('carouselDots');
    if (!track || !dotsEl) return;

    const slides = track.querySelectorAll('.testimonial-slide');
    const total  = slides.length;
    let visibleCount, maxIndex, idx = 0, timer;

    function setup() {
        visibleCount = window.innerWidth >= 768 ? 3 : 1;
        maxIndex = Math.max(0, total - visibleCount);
        idx = Math.min(idx, maxIndex);

        dotsEl.innerHTML = '';
        for (let i = 0; i <= maxIndex; i++) {
            const d = document.createElement('button');
            d.className = 'carousel-dot' + (i === idx ? ' active' : '');
            d.setAttribute('aria-label', 'Go to slide ' + (i+1));
            d.onclick = () => goTo(i);
            dotsEl.appendChild(d);
        }
        goTo(idx);
    }

    function goTo(n) {
        idx = Math.max(0, Math.min(n, maxIndex));
        const pct = (100 / visibleCount) * idx;
        track.style.transform = 'translateX(-' + pct + '%)';
        dotsEl.querySelectorAll('.carousel-dot').forEach((d,i) => d.classList.toggle('active', i===idx));
        resetTimer();
    }
    function resetTimer() { clearInterval(timer); timer = setInterval(() => goTo(idx >= maxIndex ? 0 : idx+1), 5000); }

    window.carouselPrev = () => goTo(idx - 1);
    window.carouselNext = () => goTo(idx + 1);

    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(setup, 250);
    });

    setup();
}

// ── UPGRADE 7: Count-up animation ────────────────────────────
function initCountUp() {
    const counters = document.querySelectorAll('.count-up');
    if (!counters.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting || entry.target.dataset.done) return;
            entry.target.dataset.done = '1';
            const target   = parseInt(entry.target.dataset.target);
            const duration = 1800;
            const step     = Math.ceil(target / (duration / 16));
            let current    = 0;
            const timer    = setInterval(() => {
                current = Math.min(current + step, target);
                entry.target.textContent = current.toLocaleString();
                if (current >= target) clearInterval(timer);
            }, 16);
        });
    }, { threshold: 0.5 });

    counters.forEach(c => observer.observe(c));
}

// ── Scroll effects ─────────────────────────────────────────────
let scrollTracked = false;
window.addEventListener('scroll', function() {

    // Back to top
    const btn = document.getElementById('backToTop');
    if (btn) btn.classList.toggle('visible', window.scrollY > 400);

    // GA scroll track
    if (!scrollTracked && window.scrollY > 500) {
        scrollTracked = true;
        if (typeof gtag !== 'undefined') gtag('event','scroll',{event_category:'engagement',event_label:'scrolled_page'});
    }
}, { passive:true });

document.addEventListener('click', function(e) {
    if (e.target.closest('a[href="#booking"]') && typeof gtag !== 'undefined') {
        gtag('event','click',{event_category:'conversion',event_label:'booking_button_clicked'});
    }
});

// ── Hero parallax (desktop only — causes jank on mobile) ─────
if (window.innerWidth > 768) {
    window.addEventListener('scroll', function() {
        const hero = document.getElementById('hero');
        if (!hero) return;
        const sy = window.pageYOffset;
        if (sy < hero.offsetHeight) hero.style.backgroundPositionY = (sy * 0.3) + 'px';
    }, { passive:true });
}
</script>
</body>
</html>