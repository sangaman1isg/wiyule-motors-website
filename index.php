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
<?php include __DIR__ . '/includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="description" content="Wiyule Motors offers premium auto services & parts and expert vehicle care in Blantyre, Malawi. Book professional servicing and shop quality parts today.">
    <meta name="keywords" content="auto services, car service Blantyre, vehicle maintenance, auto detailing, car repair, automotive parts, Wiyule Motors, Malawi, brake services, engine repair, oil change">
    <meta property="og:title" content="Wiyule Motors - Premium Auto Services &amp; Parts">
    <meta property="og:description" content="Blantyre's most trusted auto workshop. Premium services, expert repairs & genuine parts — book online today.">
    <meta property="og:image" content="https://wiyulemotors.co.mw/assets/images/logo.png">
    <meta property="og:image:width" content="512">
    <meta property="og:image:height" content="512">
    <meta property="og:url" content="https://wiyulemotors.co.mw">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Wiyule Motors">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Wiyule Motors - Premium Auto Services &amp; Parts">
    <meta name="twitter:description" content="Blantyre's most trusted auto workshop. Premium services, expert repairs & genuine parts — book online today.">
    <meta name="twitter:image" content="https://wiyulemotors.co.mw/assets/images/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#dc2626">
    <title>Wiyule Motors - Premium Auto Services &amp; Parts | Blantyre, Malawi</title>
    <link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png?v=2">
    <link rel="shortcut icon" type="image/png" href="<?= $base ?>/assets/images/logo.png?v=2">
    <link rel="apple-touch-icon" href="<?= $base ?>/assets/images/logo.png?v=2">
    <link rel="manifest" href="<?= $base ?>/static/favicon_io/site.webmanifest">
    <link rel="canonical" href="https://wiyulemotors.co.mw">

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
            {"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday"],"opens":"08:30","closes":"17:00"},
            {"@type":"OpeningHoursSpecification","dayOfWeek":["Saturday","Sunday"],"opens":"09:00","closes":"13:00"}
        ],
        "aggregateRating": {"@type":"AggregateRating","ratingValue":"5","reviewCount":"120"}
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

    <style>
        /* ── Global mobile safeguards (prevent sideways scroll & oversized media) ── */
        html, body { max-width: 100%; overflow-x: hidden; }
        img, video, svg, iframe { max-width: 100%; height: auto; }
        * { min-width: 0; }
        @media (max-width: 640px) {
            /* Stop any fixed-width element from forcing horizontal scroll */
            .container, section, .max-w-7xl, .max-w-6xl { max-width: 100% !important; }
        }

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

        .mob-item { display:flex; align-items:center; gap:14px; padding:10px 12px; border-radius:14px; color:#1d1d1f; font-size:15px; font-weight:600; text-decoration:none; transition:all 0.18s; }
        .mob-item:hover { background:#f5f5f7; }
        .mob-item:active { transform:scale(0.98); }
        /* Icon tile — colored rounded square, our brand take on the app-icon style */
        .mi-tile {
            width:40px; height:40px; flex-shrink:0;
            border-radius:12px;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 10px -2px rgba(0,0,0,0.18);
            transition:transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        .mob-item:hover .mi-tile { transform:translateY(-2px) rotate(-3deg); }
        .mi-tile svg { width:22px; height:22px; flex-shrink:0; display:block; }
        /* Brand tile colors */
        .mi-red   { background:#dc2626; }
        .mi-green { background:#16a34a; }
        .mi-dark  { background:#1d1d1f; }
        .mi-white { background:#f5f5f7; box-shadow:inset 0 0 0 1px #e5e7eb, 0 4px 10px -2px rgba(0,0,0,0.10); }
        .mob-item.danger { color:#dc2626; }
        .mob-item.danger:hover { background:#fef2f2; }

        /* ── Mobile Menu Smooth Slide ────────────────────────── */
        #mobile-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            border-top: 1px solid transparent;
        }
        #mobile-menu.open {
            /* Cap to the viewport below the 80px nav, and allow scrolling
               so long (logged-in) menus never get clipped */
            max-height: calc(100vh - 80px);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            border-top-color: #f1f5f9;
        }
        /* Slimmer scrollbar inside the menu */
        #mobile-menu.open::-webkit-scrollbar { width: 4px; }
        #mobile-menu.open::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 99px; }
        .mobile-user-card { background:linear-gradient(135deg,#0f172a,#1e293b); border-radius:14px; padding:14px; display:flex; align-items:center; gap:12px; margin-bottom:8px; }
        .mobile-user-avatar { width:44px; height:44px; border-radius:12px; background:linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; font-size:15px; font-weight:800; display:flex; align-items:center; justify-content:center; }

        /* ── Hero (refined: real photos, crossfade, left-aligned) ── */
        .hero-bg { background-color:#0b0f1a; color:#fff; }

        /* The hero wrapper holds the two crossfading background images */
        .hero-bg-image {
            background-color: #0b0f1a; /* fallback while images load (or if missing) */
            position: relative;
            isolation: isolate;
        }

        /* Each photo is a full-bleed absolute layer, ken-burns + crossfade */
        .hero-photo {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center right;
            will-change: opacity;
        }
        /* Layer 1 — BMW front — ALWAYS visible (base layer) */
        .hero-photo.layer-1 {
            background-image: url('<?= $base ?>/assets/images/hero-bmw.jpg');
            opacity: 1;
            z-index: 0;
            animation: heroKenBurns 20s ease-in-out infinite;
        }
        /* Layer 2 — taillight — periodically fades over the BMW for variety */
        .hero-photo.layer-2 {
            background-image: url('<?= $base ?>/assets/images/taillight.jpg');
            background-position: center;
            opacity: 0;
            z-index: 1;
            animation: heroFadeOverlay 20s ease-in-out infinite;
        }
        @media (max-width: 768px) {
            .hero-photo.layer-1 { background-position: 65% center; }
            .hero-photo.layer-2 { background-position: center; }
        }
        @keyframes heroKenBurns {
            0%, 100% { transform: scale(1.00); }
            50%      { transform: scale(1.04); }
        }
        @keyframes heroFadeOverlay {
            0%, 45%   { opacity: 0; }
            55%       { opacity: 0.7; }
            65%, 100% { opacity: 0; }
        }

        /* Dark gradient overlay so the text on the left stays crisp.
           Strong on the left, fades toward the right where the car face is.   */
        .hero-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                linear-gradient(105deg,
                    rgba(11,15,26,0.92) 0%,
                    rgba(11,15,26,0.75) 35%,
                    rgba(11,15,26,0.35) 65%,
                    rgba(11,15,26,0.55) 100%),
                linear-gradient(to top, rgba(11,15,26,0.7), transparent 35%);
            pointer-events: none;
        }
        @media (max-width: 768px) {
            /* On mobile, darken the whole overlay so text reads clearly over the centered car */
            .hero-overlay {
                background:
                    linear-gradient(180deg,
                        rgba(11,15,26,0.72) 0%,
                        rgba(11,15,26,0.55) 40%,
                        rgba(11,15,26,0.78) 100%);
            }
        }

        /* Soft moving light sweep — looks expensive, costs nothing */
        .hero-sweep {
            position: absolute;
            inset: 0;
            z-index: 2;
            background: linear-gradient(115deg,
                transparent 30%,
                rgba(255,255,255,0.04) 50%,
                transparent 70%);
            transform: translateX(-100%);
            animation: heroSweep 9s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes heroSweep {
            0%   { transform: translateX(-100%); }
            55%  { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }

        /* The status pill — inline above the headline (belongs to the text group) */
        .hero-status-pill {
            display: inline-flex; align-items: center; gap: 9px;
            background: rgba(11,15,26,0.55);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 99px;
            padding: 8px 16px 8px 12px;
            font-size: 12.5px; font-weight: 600;
            color: #fff;
            letter-spacing: 0.01em;
            margin-bottom: 22px;
        }
        .hero-status-pill .dot {
            width: 7px; height: 7px;
            background: #22c55e; border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(34,197,94,0.25);
            animation: pulseDot 2s ease-in-out infinite;
            flex-shrink: 0;
        }
        .hero-status-pill .sep {
            opacity: 0.45;
        }
        @keyframes pulseDot {
            0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.25); }
            50%      { box-shadow: 0 0 0 6px rgba(34,197,94,0.0); }
        }
        @media (max-width: 768px) {
            .hero-status-pill { font-size: 11px; padding: 7px 14px 7px 11px; margin-bottom: 18px; }
        }

        .text-shadow { text-shadow:0 2px 12px rgba(0,0,0,0.5); }
        .main-title {
            font-size:clamp(2rem, 5.2vw, 3.8rem);
            font-weight:800; color:#ffffff; line-height:1.05;
            letter-spacing:-0.03em; margin-bottom:1.2rem;
            text-shadow:0 4px 24px rgba(0,0,0,0.6);
        }

        /* ── Hero CTA buttons — equal height, single-line, consistent ── */
        .hero-cta-row {
            display: flex; flex-wrap: wrap; gap: 12px;
            align-items: stretch; /* equal heights */
        }
        .hero-btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 15px; font-weight: 700;
            font-family: 'Sora', sans-serif;
            text-decoration: none; color: #fff;
            white-space: nowrap;            /* no wrap onto two lines */
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            border: 1px solid transparent;
            min-height: 52px;
        }
        .hero-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
        .hero-btn:hover { transform: translateY(-2px); }
        .hero-btn-primary {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 6px 22px rgba(220,38,38,0.35);
        }
        .hero-btn-primary:hover { box-shadow: 0 10px 30px rgba(220,38,38,0.45); }
        .hero-btn-ghost {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .hero-btn-ghost:hover {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.28);
        }
        @media (max-width: 640px) {
            .hero-cta-row { gap: 10px; }
            .hero-btn { padding: 13px 20px; font-size: 14px; flex: 1 1 calc(50% - 6px); min-height: 50px; }
            .hero-btn-primary { flex-basis: 100%; }  /* primary takes full row on small phones */
        }

        /* ── UPGRADE 1: Hero Stats Bar ──────────────────────── */
        .hero-stats-bar {
            display:grid; grid-template-columns:repeat(4, 1fr);
            background:rgba(11,15,26,0.55);
            backdrop-filter:blur(14px);
            -webkit-backdrop-filter:blur(14px);
            border:1px solid rgba(255,255,255,0.14);
            border-radius:18px;
            padding:18px 20px;
            margin-top:36px;
            max-width:680px;
            margin-left:0;
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

        /* ── Back to top button (bottom-left, opposite side from WhatsApp) ─── */
        #backToTop {
            position:fixed; bottom:24px; left:24px;
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

        /* ── MOBILE: floating buttons positioned cleanly, no overlap ── */
        @media(max-width:1023px) {
            /* WhatsApp sits bottom-right, lifted above the sticky booking bar */
            .whatsapp-btn { bottom:84px !important; right:16px !important; width:48px; height:48px; z-index:60; }
            /* Back-to-top sits bottom-LEFT (opposite of WhatsApp), lifted above sticky bar */
            #backToTop { bottom:84px !important; left:16px !important; right:auto !important; width:44px; height:44px; z-index:60; }
            /* Give the page bottom breathing room so nothing hides behind the bar */
            footer { padding-bottom:90px !important; }
        }
        /* Very small phones: tighten spacing a touch more */
        @media(max-width:380px) {
            .whatsapp-btn { bottom:80px !important; }
            #backToTop    { bottom:80px !important; }
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
        /* ═══════════════════════════════════════════════════════
           ✨ LIQUID GLASS UPGRADES — Apple-inspired, premium
        ═══════════════════════════════════════════════════════ */

        /* ── Liquid glass navigation bar ── */
        .glass-nav {
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 1px 0 rgba(0,0,0,0.04), 0 4px 24px rgba(0,0,0,0.04);
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }
        /* When scrolled, make it slightly more opaque + deeper shadow */
        .glass-nav.scrolled {
            background: rgba(255, 255, 255, 0.85);
            box-shadow: 0 1px 0 rgba(0,0,0,0.06), 0 8px 32px rgba(0,0,0,0.08);
        }

        /* ── Liquid glass hero buttons ── */
        .glass-btn {
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.12) !important;
            backdrop-filter: blur(16px) saturate(160%);
            -webkit-backdrop-filter: blur(16px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), 0 8px 24px rgba(0,0,0,0.15);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s, background 0.3s;
        }
        .glass-btn::before {
            content: '';
            position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.35), transparent);
            transition: left 0.6s ease;
        }
        .glass-btn:hover::before { left: 120%; }
        .glass-btn:hover {
            background: rgba(255,255,255,0.2) !important;
            transform: translateY(-3px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 14px 32px rgba(0,0,0,0.22);
        }

        /* Primary red glass button (the main CTA) */
        .glass-btn-primary {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(239,68,68,0.92), rgba(185,28,28,0.92)) !important;
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.25) !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.4);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
        }
        .glass-btn-primary::before {
            content: '';
            position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .glass-btn-primary:hover::before { left: 120%; }
        .glass-btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.5);
        }

        /* ── "Our Work" auto-rotating slideshow ── */
        .work-slider {
            position: relative;
            max-width: 1000px;
            margin: 0 auto;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 24px 64px -16px rgba(15,23,42,0.35);
            background: #0a0a0a;
            aspect-ratio: 16 / 9;
        }
        .work-slide {
            position: absolute; inset: 0;
            opacity: 0;
            transform: scale(1.05);
            transition: opacity 1s cubic-bezier(0.4,0,0.2,1), transform 6s ease-out;
            pointer-events: none;
        }
        .work-slide.active {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }
        .work-slide img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        /* Gradient scrim + caption */
        .work-slide::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.55) 0%, rgba(0,0,0,0.1) 40%, transparent 70%);
        }
        .work-slide-caption {
            position: absolute; bottom: 0; left: 0; right: 0;
            padding: 32px 36px;
            color: #fff;
            z-index: 2;
        }
        .work-slide-caption .ws-tag {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 6px 14px; border-radius: 99px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.03em;
            margin-bottom: 10px;
        }
        .work-slide-caption .ws-title {
            font-size: 24px; font-weight: 800; letter-spacing: -0.01em;
        }
        /* Dots */
        .work-dots {
            display: flex; justify-content: center; gap: 10px;
            margin-top: 24px;
        }
        .work-dot {
            width: 10px; height: 10px; border-radius: 99px;
            background: #d1d5db; border: none; cursor: pointer; padding: 0;
            transition: width 0.4s ease, background 0.4s ease;
        }
        .work-dot.active { width: 32px; background: var(--red, #dc2626); }
        /* Arrows */
        .work-arrow {
            position: absolute; top: 50%; transform: translateY(-50%);
            z-index: 3;
            width: 44px; height: 44px; border-radius: 99px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, transform 0.2s;
            opacity: 0;
        }
        .work-slider:hover .work-arrow { opacity: 1; }
        .work-arrow:hover { background: rgba(255,255,255,0.32); }
        .work-arrow.prev { left: 16px; }
        .work-arrow.next { right: 16px; }
        @media (max-width: 768px) {
            .work-slider { border-radius: 20px; aspect-ratio: 4 / 3; }
            .work-slide-caption { padding: 20px 22px; }
            .work-slide-caption .ws-title { font-size: 18px; }
            .work-arrow { opacity: 1; width: 38px; height: 38px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .work-slide { transition: opacity 0.4s ease; transform: none; }
            .work-slide.active { transform: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .glass-btn, .glass-btn-primary, .work-slide,
            .work-slide-caption { transition: none !important; }
            .glass-btn::before, .glass-btn-primary::before { display: none; }
        }
        /* ═══════════════════════════════════════════════════════
           ✦ SERVICES — refined Apple aesthetic
        ═══════════════════════════════════════════════════════ */
        .svc-eyebrow {
            display: inline-block;
            font-size: 13px; font-weight: 600;
            color: #dc2626; letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .svc-h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700;
            color: #1d1d1f;
            letter-spacing: -0.03em;
            margin-bottom: 14px;
            line-height: 1.05;
        }
        .svc-sub {
            font-size: 19px; color: #6e6e73;
            max-width: 540px; margin: 0 auto 28px;
            font-weight: 400; line-height: 1.5;
        }
        .svc-pricelist {
            display: inline-flex; align-items: center; gap: 8px;
            background: #1d1d1f; color: #fff;
            padding: 12px 22px; border-radius: 980px;
            font-size: 14px; font-weight: 600;
            text-decoration: none;
            transition: transform 0.25s ease, background 0.25s ease;
        }
        .svc-pricelist:hover { background: #000; transform: translateY(-1px); }

        .svc-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 1024px) { .svc-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .svc-grid { grid-template-columns: 1fr; } }

        .svc-card {
            background: linear-gradient(180deg, #ffffff 0%, #fdf6f6 100%);
            border: 1px solid #f6dede;
            border-radius: 24px;
            padding: 34px 30px;
            transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), box-shadow 0.35s, border-color 0.35s;
            position: relative;
            overflow: hidden;
        }
        /* Soft red glow that appears on hover — premium Apple depth */
        .svc-card::before {
            content: '';
            position: absolute;
            top: -40%; right: -30%;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(220,38,38,0.10) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.45s ease;
            pointer-events: none;
        }
        .svc-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px -16px rgba(220,38,38,0.20);
            border-color: #f0c4c4;
        }
        .svc-card:hover::before { opacity: 1; }
        /* Bold solid-color icon tile (app-icon style), white icon, soft shadow */
        .svc-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: #dc2626;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 22px;
            box-shadow: 0 8px 18px -4px rgba(220,38,38,0.4);
            transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        .svc-icon i, .svc-icon svg {
            width: 26px; height: 26px;
            color: #fff;
            stroke-width: 2;
        }
        .svc-card:hover .svc-icon {
            transform: translateY(-3px) rotate(-4deg) scale(1.04);
        }
        /* Alternating brand tile colors — red & charcoal only */
        .svc-card:nth-child(even) .svc-icon { background: #1d1d1f; box-shadow: 0 8px 18px -4px rgba(29,29,31,0.4); }

        .svc-title {
            font-size: 20px; font-weight: 600;
            color: #1d1d1f; letter-spacing: -0.01em;
            margin-bottom: 10px;
        }
        .svc-desc {
            font-size: 15px; color: #6e6e73;
            line-height: 1.6; margin-bottom: 22px;
        }
        .svc-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: #dc2626; font-size: 15px; font-weight: 600;
            text-decoration: none;
            transition: gap 0.25s ease;
        }
        .svc-link svg { transition: transform 0.25s ease; }
        .svc-link:hover { gap: 10px; }
        .svc-link:hover svg { transform: translateX(2px); }

        /* Feature card (Vehicle Wrapping) — subtle red wash, red tile */
        .svc-card-feature {
            background: linear-gradient(180deg, #ffffff 0%, #fdf6f6 100%);
            border-color: #f6dede;
        }
        .svc-card-feature .svc-icon { background: #dc2626 !important; box-shadow: 0 8px 18px -4px rgba(220,38,38,0.4) !important; }
        .svc-card-feature:hover {
            box-shadow: 0 20px 50px -16px rgba(220,38,38,0.20);
            border-color: #f0c4c4;
        }
        .svc-card-feature::before {
            background: radial-gradient(circle, rgba(220,38,38,0.10) 0%, transparent 70%);
        }
        .svc-card-feature .svc-link { color: #dc2626; }

        @media (prefers-reduced-motion: reduce) {
            .svc-card, .svc-icon, .svc-link, .svc-link svg { transition: none !important; }
        }
        /* ═══════════════════════════════════════════════════════
           ✦ WHY CHOOSE US — light, brand colors (red + green hints)
        ═══════════════════════════════════════════════════════ */
        .why-section {
            padding: 96px 0;
            background:
                radial-gradient(circle at 15% 20%, rgba(220,38,38,0.05) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(22,163,74,0.05) 0%, transparent 40%),
                #ffffff;
            color: #1d1d1f;
            position: relative;
            overflow: hidden;
        }
        .why-eyebrow {
            display: inline-block;
            font-size: 13px; font-weight: 600;
            color: #dc2626; letter-spacing: 0.04em;
            text-transform: uppercase; margin-bottom: 16px;
        }
        .why-h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 700; letter-spacing: -0.03em;
            color: #1d1d1f; margin-bottom: 14px; line-height: 1.05;
        }
        .why-sub {
            font-size: 19px; color: #6e6e73;
            max-width: 520px; margin: 0 auto; font-weight: 400;
        }
        .why-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }
        @media (max-width: 900px) { .why-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 520px) { .why-grid { grid-template-columns: 1fr; } }

        /* Stat strip merged from old About section */
        .why-stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
            margin-top: 56px;
            padding: 28px 24px;
            background: #ffffff;
            border: 1px solid #ededed;
            border-radius: 22px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.04);
        }
        .why-stat { text-align: center; min-width: 0; }
        .why-stat-num {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 800;
            color: #dc2626;
            letter-spacing: -0.03em;
            line-height: 1;
            margin-bottom: 6px;
            white-space: nowrap;
        }
        .why-stat-label {
            font-size: clamp(11px, 2.5vw, 14px);
            color: #6b7280; font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        @media (max-width: 480px) {
            .why-stats { padding: 20px 12px; gap: 8px; margin-top: 40px; }
        }
        .why-card {
            background: #ffffff;
            border: 1px solid #ededed;
            border-radius: 22px;
            padding: 32px 26px;
            text-align: center;
            transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), box-shadow 0.35s, border-color 0.35s;
        }
        .why-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 44px -16px rgba(15,23,42,0.12);
            border-color: #f0c4c4;
        }
        .why-icon {
            width: 60px; height: 60px; margin: 0 auto 18px;
            border-radius: 16px;
            background: #dc2626;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 18px -4px rgba(220,38,38,0.4);
            transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
        }
        .why-icon i, .why-icon svg { width: 28px; height: 28px; color: #fff; stroke-width: 2; }
        .why-card:hover .why-icon { transform: translateY(-3px) rotate(-4deg) scale(1.05); }
        .why-title { font-size: 18px; font-weight: 600; color: #1d1d1f; margin-bottom: 8px; letter-spacing: -0.01em; }
        .why-desc  { font-size: 14px; color: #6e6e73; line-height: 1.55; }
        /* Alternate green tiles on 2nd & 4th cards for brand balance */
        .why-card:nth-child(2) .why-icon,
        .why-card:nth-child(4) .why-icon { background: #16a34a; box-shadow: 0 8px 18px -4px rgba(22,163,74,0.4); }
        .why-card:nth-child(2):hover,
        .why-card:nth-child(4):hover { border-color: #a7e0b8; }

        /* ── About stats — refined ── */
        .about-stat {
            text-align: center; padding: 18px 8px;
            background: #f5f5f7; border-radius: 18px;
            transition: transform 0.3s ease;
            min-width: 0;          /* allow shrinking inside a tight grid */
            overflow: hidden;       /* don't push neighbouring cells */
        }
        .about-stat:hover { transform: translateY(-3px); }
        .about-stat-num {
            font-size: clamp(20px, 5vw, 32px);   /* scales down on narrow screens */
            font-weight: 700; color: #dc2626;
            letter-spacing: -0.02em; margin-bottom: 4px;
            white-space: nowrap;
            line-height: 1.1;
        }
        .about-stat-label { font-size: clamp(11px, 2.5vw, 13px); color: #6e6e73; font-weight: 500; line-height: 1.3; }
        @media (max-width: 380px) {
            .about-stat { padding: 14px 6px; border-radius: 14px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .why-card, .why-icon, .about-stat { transition: none !important; }
        }
        /* ═══════════════════════════════════════════════════════
           ✦ UNIVERSAL GLASS BUTTONS (for light backgrounds)
           Matches the hero Book a Service / Call Us Now feel,
           but tuned to sit on white/light sections.
        ═══════════════════════════════════════════════════════ */
        .gbtn {
            position: relative;
            overflow: hidden;
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px;
            padding: 14px 28px;
            border-radius: 16px;
            font-family: 'Sora', sans-serif;
            font-size: 15px; font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.25);
            transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s;
        }
        .gbtn::before {
            content: '';
            position: absolute; top: 0; left: -120%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s ease;
        }
        .gbtn:hover::before { left: 120%; }
        .gbtn:hover { transform: translateY(-3px) scale(1.02); }

        /* Primary red glass (default action) */
        .gbtn-primary {
            background: linear-gradient(135deg, rgba(239,68,68,0.95), rgba(185,28,28,0.95));
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(220,38,38,0.35);
        }
        .gbtn-primary:hover {
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(220,38,38,0.45);
        }

        /* Dark glass (for things like 'View Price List') */
        .gbtn-dark {
            background: linear-gradient(135deg, rgba(45,45,47,0.95), rgba(15,15,17,0.95));
            backdrop-filter: blur(12px) saturate(160%);
            -webkit-backdrop-filter: blur(12px) saturate(160%);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.18), 0 10px 28px rgba(15,23,42,0.3);
        }
        .gbtn-dark:hover {
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), 0 16px 38px rgba(15,23,42,0.4);
        }

        /* Green glass (WhatsApp / quote actions) */
        .gbtn-green {
            background: linear-gradient(135deg, rgba(22,163,74,0.95), rgba(21,128,61,0.95));
            backdrop-filter: blur(12px) saturate(180%);
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3), 0 10px 30px rgba(22,163,74,0.35);
        }
        .gbtn-green:hover {
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.4), 0 16px 40px rgba(22,163,74,0.45);
        }

        @media (prefers-reduced-motion: reduce) {
            .gbtn { transition: none !important; }
            .gbtn::before { display: none; }
        }
    </style>
</head>
<body class="font-[Sora] antialiased text-gray-800">

<?php if($show_offer_banner): ?>
<!-- ═══ First-Time Customer Offer Banner ═══ -->
<div id="offerBanner" style="background:linear-gradient(90deg,#dc2626,#b91c1c,#991b1b);color:#fff;padding:10px 44px 10px 16px;text-align:center;position:relative;z-index:60;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap">
    <span style="font-size:16px">🎉</span>
    <span><strong>First-time customer?</strong> Get <strong>10% OFF</strong> your first service —</span>
    <?php if(!$is_logged_in): ?>
        <a href="<?= $base ?>/pages/signup.php?offer=first10" style="background:#fff;color:#b91c1c;padding:5px 14px;border-radius:99px;font-weight:800;text-decoration:none;font-size:12px">Claim Now →</a>
    <?php else: ?>
        <a href="<?= $base ?>/book.php?offer=first10" style="background:#fff;color:#b91c1c;padding:5px 14px;border-radius:99px;font-weight:800;text-decoration:none;font-size:12px">Book & Apply →</a>
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
    <svg viewBox="0 0 24 24" fill="#fff" stroke="none" width="28" height="28"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.2.2-.3.2-.5.1a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.4-1.7c-.1-.2 0-.4.1-.5l.4-.5.3-.5v-.5L9 7.7c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3c-.2.3-.9.9-.9 2.1s.9 2.5 1.1 2.6c.1.2 1.8 2.7 4.3 3.8.6.3 1.1.4 1.5.5.6.2 1.2.2 1.6.1.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2z"/></svg>
</a>

<!-- Back to Top Button -->
<button id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<!-- ═══ Navigation ═══ -->
<nav class="glass-nav sticky top-0 z-50" id="mainNav">
    <div style="height:3px;background:linear-gradient(90deg,#dc2626,#b91c1c,#7f1d1d);"></div>
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

            <!-- Desktop Nav Links -->
            <div class="hidden lg:flex items-center" style="gap:28px">
                <a href="<?= $base ?>/"        class="nav-link active">Home</a>
                <a href="#services"             class="nav-link">Services</a>
                <a href="#our-work"             class="nav-link">Our Work</a>
                <a href="<?= $base ?>/pricing.php" class="nav-link">Pricing</a>
                <a href="<?= $base ?>/book.php"              class="nav-link">Book</a>
                <a href="<?= $base ?>/about.php"                class="nav-link">About</a>
                <a href="<?= $base ?>/contact.php"              class="nav-link">Contact</a>
            </div>

            <!-- Desktop Auth -->
            <div class="hidden lg:flex items-center" style="gap:10px">
                <?php if ($is_logged_in && $nav_user): ?>
                    <a href="<?= $base ?>/pages/dashboard.php?panel=notifications" class="nav-bell" title="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($nav_user['unread_notifs'] > 0): ?><span class="nav-bell-badge"></span><?php endif; ?>
                    </a>
                    <a href="<?= $base ?>/pages/dashboard.php" class="gbtn gbtn-dark" style="padding:9px 18px;font-size:13px;border-radius:12px">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
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
                    <a href="<?= $base ?>/pages/login.php" class="gbtn gbtn-dark" style="padding:9px 18px;font-size:13px;border-radius:12px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Sign In
                    </a>
                    <a href="<?= $base ?>/pages/signup.php" class="gbtn gbtn-primary" style="padding:9px 18px;font-size:13px;border-radius:12px">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        Create Account
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Toggle -->
            <div class="lg:hidden flex items-center gap-3">
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
    <div id="mobile-menu" class="lg:hidden bg-white">
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

            <a href="<?= $base ?>/"   onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-red"><svg viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"><path d="M4 10.5L12 4l8 6.5V19a1 1 0 0 1-1 1h-4v-5h-6v5H5a1 1 0 0 1-1-1z"/></svg></span>
                Home
            </a>
            <a href="#services"       onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-dark"><svg viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"><path d="M12 3l2.3 5.9 6.2.4-4.8 4 1.6 6-5.3-3.4L6.7 19.3l1.6-6-4.8-4 6.2-.4z"/></svg></span>
                Services
            </a>
            <a href="#our-work"       onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-green"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5" fill="#fff" stroke="#fff"/><circle cx="8.5" cy="9.5" r="1.6" fill="#16a34a" stroke="none"/><path d="M20 14l-4.5-4L7 18.5" stroke="#16a34a"/></svg></span>
                Our Work
            </a>
            <a href="<?= $base ?>/pricing.php" onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-dark"><svg viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"><path d="M20.6 13.4L13.4 20.6a2 2 0 0 1-2.8 0l-7-7A2 2 0 0 1 3 12.2V5.5A1.5 1.5 0 0 1 4.5 4h6.7c.5 0 1 .2 1.4.6l8 8a2 2 0 0 1 0 2.8z"/><circle cx="8" cy="8" r="1.5" fill="#1d1d1f" stroke="none"/></svg></span>
                Pricing
            </a>
            <a href="<?= $base ?>/book.php"        onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-red"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="16" rx="2.5" fill="#fff" stroke="#fff"/><path d="M3.5 9.5h17" stroke="#dc2626"/><path d="M8 3v4M16 3v4" stroke="#fff"/><path d="M8.5 14.5l2.2 2.2 4.3-4.3" stroke="#dc2626"/></svg></span>
                Book a Service
            </a>
            <a href="<?= $base ?>/about.php"          onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-green"><svg viewBox="0 0 24 24" fill="#fff" stroke="#fff" stroke-width="1.5"><circle cx="12" cy="12" r="9" fill="#fff" stroke="#fff"/><circle cx="12" cy="8" r="1.3" fill="#16a34a" stroke="none"/><rect x="11" y="11" width="2" height="6" rx="1" fill="#16a34a" stroke="none"/></svg></span>
                About
            </a>
            <a href="<?= $base ?>/contact.php"        onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-dark"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5" fill="#fff" stroke="#fff"/><path d="M4 7l8 5.5L20 7" stroke="#1d1d1f"/></svg></span>
                Contact
            </a>

            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>

            <?php if ($is_logged_in && $nav_user): ?>
                <a href="<?= $base ?>/pages/dashboard.php" onclick="closeMobileMenu()" class="mob-item">
                    <span class="mi-tile mi-dark"><svg viewBox="0 0 24 24" fill="#fff" stroke="none"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg></span>
                    My Dashboard
                </a>
                <a href="<?= $base ?>/pages/logout.php" onclick="closeMobileMenu()" class="mob-item danger">
                    <span class="mi-tile mi-white"><svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
                    Sign Out
                </a>
            <?php else: ?>
                <a href="<?= $base ?>/pages/login.php" onclick="closeMobileMenu()" class="mob-item">
                    <span class="mi-tile mi-dark"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></span>
                    Sign In
                </a>
                <a href="<?= $base ?>/pages/signup.php" onclick="closeMobileMenu()" class="mob-item">
                    <span class="mi-tile mi-red"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></span>
                    Create Free Account
                </a>
            <?php endif; ?>

            <div style="height:1px;background:#f1f5f9;margin:8px 0"></div>
            <a href="https://wa.me/265993575111" target="_blank" onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-green"><svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20z"/><path d="M8.5 7.3c-.2 0-.5 0-.7.4-.2.4-.9.9-.9 2.1s.9 2.5 1 2.6c.1.2 1.7 2.8 4.3 3.8 2.1.8 2.6.7 3 .6.6-.1 1.8-.7 2-1.5.3-.7.3-1.4.2-1.5-.1-.1-.3-.2-.6-.4-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.1-.6.1l-.9 1.1c-.1.2-.3.2-.5.1-.7-.3-1.5-.6-2.4-1.6-.7-.7-1.1-1.5-1.3-1.8-.1-.2 0-.4.1-.5l.4-.5c.1-.1.2-.3.2-.4 0-.1.1-.3 0-.5l-.7-1.8c-.2-.5-.4-.5-.6-.5z"/></svg></span>
                WhatsApp Us
            </a>
            <a href="tel:+265993575111" onclick="closeMobileMenu()" class="mob-item">
                <span class="mi-tile mi-red"><svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/></svg></span>
                Call Us
            </a>
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

    <!-- ─── Real photo crossfade (your BMW + taillight) ─── -->
    <div class="hero-photo layer-1" aria-hidden="true"></div>
    <div class="hero-photo layer-2" aria-hidden="true"></div>

    <!-- Dark gradient so text stays crisp -->
    <div class="hero-overlay" aria-hidden="true"></div>

    <!-- Slow light-sweep animation -->
    <div class="hero-sweep" aria-hidden="true"></div>

    <!-- Ambient grid + speed lines kept, but softened -->
    <div class="speed-lines" style="opacity:0.4"></div>
    <div class="absolute inset-0 opacity-[0.06]" style="z-index:3"><div class="absolute inset-0 hero-overlay-pattern"></div></div>

    <div class="max-w-7xl mx-auto relative px-4 sm:px-6 lg:px-8 w-full py-24" style="z-index:5">
        <div data-aos="fade-up" style="max-width:680px">

            <!-- Status pill — inline, above the headline (Option 3) -->
            <div class="hero-status-pill">
                <span class="dot"></span>
                <span><strong style="font-weight:700">Open Today</strong></span>
                <span class="sep">·</span>
                <span>8:30 AM – 5:00 PM</span>
            </div>

            <h1 class="main-title" style="text-align:left">Blantyre's Most Trusted<br><span style="color:#f87171">Auto Workshop</span></h1>
            <p class="text-xl md:text-2xl text-gray-100 mb-10 max-w-2xl leading-relaxed text-shadow" style="text-align:left">
                Premium auto services &amp; parts, expert repairs, and professional vehicle care — all in one place in Malawi.
            </p>

            <div class="hero-cta-row">
                <a href="<?= $base ?>/book.php" class="hero-btn hero-btn-primary">
                    <svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M19 4h-1V3a1 1 0 0 0-2 0v1H8V3a1 1 0 0 0-2 0v1H5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm1 15a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9h16zm-3.3-7.7l-4.5 4.5a1 1 0 0 1-1.4 0l-2-2a1 1 0 0 1 1.4-1.4l1.3 1.3 3.8-3.8a1 1 0 0 1 1.4 1.4z"/></svg>
                    <span>Book a Service</span>
                </a>
                <a href="#services" class="hero-btn hero-btn-ghost">
                    <svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M21.7 7.3a1 1 0 0 0-1.6-.3l-2.5 2.5-2.1-2.1 2.5-2.5a1 1 0 0 0-.3-1.6 5.5 5.5 0 0 0-7.1 6.6l-7 7a2.5 2.5 0 0 0 3.5 3.5l7-7a5.5 5.5 0 0 0 6.6-7.1zM6 19.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>
                    <span>Our Services</span>
                </a>
                <a href="tel:+265993575111" class="hero-btn hero-btn-ghost">
                    <svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/></svg>
                    <span>Call Us</span>
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
<section id="services" class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="svc-eyebrow">What We Do</span>
            <h2 class="svc-h2">Our Services</h2>
            <p class="svc-sub">Professional automotive care, tailored to your vehicle.</p>
            <a href="<?= $base ?>/pricing.php" class="gbtn gbtn-dark">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                View Full Price List
            </a>
        </div>
        <div class="svc-grid">
            <?php
            $services = [
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M21.7 7.3a1 1 0 0 0-1.6-.3l-2.5 2.5-2.1-2.1 2.5-2.5a1 1 0 0 0-.3-1.6 5.5 5.5 0 0 0-7.1 6.6l-7 7a2.5 2.5 0 0 0 3.5 3.5l7-7a5.5 5.5 0 0 0 6.6-7.1zM6 19.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>', 'title'=>'General Maintenance', 'desc'=>'Oil changes, filter replacements, and full inspections by certified technicians.', 'svc'=>'General Maintenance', 'cta'=>'Book Now'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M19.4 13a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V19a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H1a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>', 'title'=>'Engine Repair',       'desc'=>'Expert engine diagnostics, repair, and performance optimization.', 'svc'=>'Engine Repair', 'cta'=>'Book Now'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M14 2.2c-.8 0-1.5.5-1.8 1.2L11 6.4 7.6 9.8a2 2 0 0 0 0 2.8l3.8 3.8a2 2 0 0 0 2.8 0l3.4-3.4 3-1.2a2 2 0 0 0 .6-3.2L17 3.2a2 2 0 0 0-1.4-.6zM6 13l-3.6 3.6a2 2 0 0 0 0 2.8l2.2 2.2a2 2 0 0 0 2.8 0L11 18l-5-5z"/></svg>', 'title'=>'Spray Painting',      'desc'=>'Full repaints and panel matching with premium automotive finishes.', 'svc'=>'Spray Painting', 'cta'=>'Book Now'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2.2S5 10 5 14.5a7 7 0 0 0 14 0C19 10 12 2.2 12 2.2zm0 16.3a4 4 0 0 1-4-4c0-.6.4-1 1-1s1 .4 1 1a2 2 0 0 0 2 2c.6 0 1 .4 1 1s-.4 1-1 1z"/></svg>', 'title'=>'Auto Detailing',      'desc'=>'Professional interior and exterior detailing to keep your vehicle looking brand new.', 'svc'=>'Auto Detailing', 'cta'=>'Book Now'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M3 14l4-4 3 3 6-6 5 5v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4zm17-7l-2-2-3 3 2 2zm-9 9l-3-3-4 4h12z"/></svg>', 'title'=>'Panel Beating',       'desc'=>'Dent removal, structural straightening, and full collision repairs.', 'svc'=>'Panel Beating', 'cta'=>'Book Now'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2l2.9 6.3 6.8.6a1 1 0 0 1 .6 1.8l-5.2 4.5 1.6 6.7a1 1 0 0 1-1.5 1.1L12 19.3 6.2 22.8a1 1 0 0 1-1.5-1.1l1.6-6.7L1.1 10.5a1 1 0 0 1 .6-1.8l6.8-.6z"/></svg>', 'title'=>'Vehicle Wrapping',    'desc'=>'Premium vinyl wraps — gloss, matte, carbon fibre finishes, and paint protection.', 'svc'=>'Vehicle Wrapping', 'cta'=>'Book Now', 'feature'=>true],
            ];
            foreach ($services as $i => $s):
            ?>
            <div class="svc-card<?= !empty($s['feature']) ? ' svc-card-feature' : '' ?>" data-aos="fade-up" data-aos-delay="<?= 60 + $i*40 ?>">
                <div class="svc-icon">
                    <?= $s['svg'] ?>
                </div>
                <h3 class="svc-title"><?= htmlspecialchars($s['title']) ?></h3>
                <p class="svc-desc"><?= htmlspecialchars($s['desc']) ?></p>
                <a href="<?= $base ?>/book.php?svc=<?= urlencode($s['svc']) ?>" class="svc-link">
                    <?= htmlspecialchars($s['cta']) ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══ WHY CHOOSE US ═══ -->
<section class="why-section">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="why-eyebrow">Why Wiyule</span>
            <h2 class="why-h2">Built on trust and craftsmanship</h2>
            <p class="why-sub">We're committed to excellence in every detail of our service.</p>
        </div>
        <div class="why-grid">
            <?php
            $why = [
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2a7 7 0 0 0-4 12.7V15l-1.6 5.3a1 1 0 0 0 1.4 1.2L12 19.7l4.2 1.8a1 1 0 0 0 1.4-1.2L16 15v-.3A7 7 0 0 0 12 2zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/></svg>', 'title'=>'Certified Experts',  'desc'=>'Professionally trained and certified technicians.'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 2L4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5l-8-3zm-1 13.4l-3.7-3.7 1.4-1.4L11 12.6l4.3-4.3 1.4 1.4z"/></svg>', 'title'=>'Quality Guarantee',  'desc'=>'All work backed by our satisfaction guarantee.'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 1a11 11 0 1 0 0 22 11 11 0 0 0 0-22zm1 16.9V19a1 1 0 0 1-2 0v-1.1a3.5 3.5 0 0 1-2.5-2.2 1 1 0 0 1 1.9-.7c.2.6.8 1 1.6 1 .9 0 1.5-.5 1.5-1.1 0-.5-.3-.9-1.7-1.3-1.3-.4-3.1-1-3.1-3 0-1.4 1-2.5 2.3-2.9V5a1 1 0 0 1 2 0v.8a3.4 3.4 0 0 1 2.3 2 1 1 0 0 1-1.8.8c-.2-.5-.7-.9-1.4-.9-.8 0-1.4.4-1.4 1 0 .5.5.7 1.8 1.1 1.3.4 3 1 3 3.2 0 1.5-1 2.6-2.3 3z"/></svg>', 'title'=>'Fair Pricing',       'desc'=>'Transparent pricing with no hidden fees.'],
                ['svg'=>'<svg viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M12 1a11 11 0 1 0 0 22 11 11 0 0 0 0-22zm4 12h-4a1 1 0 0 1-1-1V6a1 1 0 0 1 2 0v5h3a1 1 0 0 1 0 2z"/></svg>', 'title'=>'Fast Service',       'desc'=>'Quick turnaround without compromising quality.'],
            ];
            foreach ($why as $i => $w):
            ?>
            <div class="why-card" data-aos="fade-up" data-aos-delay="<?= 80 + $i*60 ?>">
                <div class="why-icon"><?= $w['svg'] ?></div>
                <h3 class="why-title"><?= htmlspecialchars($w['title']) ?></h3>
                <p class="why-desc"><?= htmlspecialchars($w['desc']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Stat strip (merged from old About section) -->
        <div class="why-stats" data-aos="fade-up" data-aos-delay="200">
            <div class="why-stat">
                <div class="why-stat-num"><span class="count-up" data-target="10">0</span>+</div>
                <div class="why-stat-label">Years Experience</div>
            </div>
            <div class="why-stat">
                <div class="why-stat-num"><span class="count-up" data-target="5000">0</span>+</div>
                <div class="why-stat-label">Vehicles Served</div>
            </div>
            <div class="why-stat">
                <div class="why-stat-num"><span class="count-up" data-target="99">0</span>%</div>
                <div class="why-stat-label">Satisfaction</div>
            </div>
        </div>
    </div>
</section>

<!-- ═══ OUR WORK ═══ -->
<section id="our-work" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14" data-aos="fade-up">
            <span class="svc-eyebrow">Our Work</span>
            <h2 class="svc-h2" style="margin-bottom:14px">Recently Out the Workshop</h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">A look at some of the vehicles we've serviced and detailed. Your car could be next.</p>
        </div>

        <div class="work-slider" id="workSlider" data-aos="fade-up" data-aos-delay="100">
            <?php
            // Slideshow images — uploaded to assets/images/ as work-1.jpg ... work-6.jpg
            $work_images = [
                ['file' => 'work-1.jpg', 'caption' => 'Full detail & polish'],
                ['file' => 'work-2.jpg', 'caption' => 'Finished & ready'],
                ['file' => 'work-3.jpg', 'caption' => 'Quality workmanship'],
                ['file' => 'work-4.jpg', 'caption' => 'Showroom shine'],
                ['file' => 'work-5.jpg', 'caption' => 'Expert service'],
                ['file' => 'work-6.jpg', 'caption' => 'Premium care'],
            ];
            foreach ($work_images as $idx => $img):
            ?>
            <div class="work-slide<?= $idx === 0 ? ' active' : '' ?>" data-slide="<?= $idx ?>">
                <img loading="lazy"
                     src="<?= $base ?>/assets/images/<?= htmlspecialchars($img['file']) ?>"
                     alt="<?= htmlspecialchars($img['caption']) ?> — Wiyule Motors"
                     onerror="this.closest('.work-slide').setAttribute('data-missing','1');">
                <div class="work-slide-caption">
                    <span class="ws-tag">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Our Work
                    </span>
                    <div class="ws-title"><?= htmlspecialchars($img['caption']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <button class="work-arrow prev" id="workPrev" aria-label="Previous">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <button class="work-arrow next" id="workNext" aria-label="Next">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
        </div>

        <div class="work-dots" id="workDots"></div>

        <div class="text-center mt-12" data-aos="fade-up">
            <a href="<?= $base ?>/book.php" class="gbtn gbtn-primary" style="padding:16px 32px;font-size:17px">
                Book Your Service
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
        </div>
    </div>
</section>

<!-- ═══ TESTIMONIALS ═══ -->
<section class="py-20 bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16" data-aos="fade-up">
            <span class="svc-eyebrow">120+ Reviews</span>
            <h2 class="svc-h2" style="margin-bottom:14px">What Our Clients Say</h2>
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

<!-- ═══ Footer ═══ -->
<footer class="bg-gray-900 text-white py-12">
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
                    <li><a href="<?= $base ?>/book.php"  class="text-gray-400 hover:text-white transition">Book Now</a></li>
                    <li><a href="<?= $base ?>/about.php"    class="text-gray-400 hover:text-white transition">About</a></li>
                    <li><a href="<?= $base ?>/contact.php"  class="text-gray-400 hover:text-white transition">Contact</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Contact</h3>
                <ul class="space-y-3 text-gray-400">
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>Nyambadwe, Blantyre</li>
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>+265 993 575 111</li>
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.56 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>+265 996 834 038</li>
                    <li class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Mon–Fri: 8:30AM–5PM · Sat–Sun: 9AM–1PM</li>
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
    if(window.feather)feather.replace();
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
            if(window.feather)feather.replace();
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
            if(window.feather)feather.replace();
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
        + (data.first_time_discount == 1
            ? '\n━━━━━━━━━━━━━━━━━━━\n🎉 *FIRST-TIME CUSTOMER*\n💰 *APPLY 10% DISCOUNT*\n━━━━━━━━━━━━━━━━━━━\n'
            : (data.is_first_time
                ? '\n⭐ *First-time customer* (10% offer available but not claimed)\n'
                : ''))
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
    if (e.target.closest('a[href="<?= $base ?>/book.php"]') && typeof gtag !== 'undefined') {
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

// Nav glass effect — gets more opaque as you scroll
(function() {
    var nav = document.getElementById('mainNav');
    if (nav) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 20) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }, { passive: true });
    }
})();

// "Our Work" auto-rotating slideshow
(function() {
    var slider = document.getElementById('workSlider');
    if (!slider) return;

    // Only count slides whose image actually loaded (skip missing ones)
    var allSlides = Array.prototype.slice.call(slider.querySelectorAll('.work-slide'));
    // Wait a tick for onerror handlers to mark missing slides
    setTimeout(function() {
        var slides = allSlides.filter(function(s){ return !s.getAttribute('data-missing'); });
        if (slides.length === 0) {
            // No images uploaded yet — hide the whole section gracefully
            var section = document.getElementById('our-work');
            if (section) section.style.display = 'none';
            return;
        }

        // Re-index visible slides and build dots
        var dotsWrap = document.getElementById('workDots');
        slides.forEach(function(s, i) {
            s.classList.remove('active');
            if (i === 0) s.classList.add('active');
            if (dotsWrap) {
                var dot = document.createElement('button');
                dot.className = 'work-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('aria-label', 'Go to slide ' + (i+1));
                dot.addEventListener('click', function(){ go(i); });
                dotsWrap.appendChild(dot);
            }
        });

        var current = 0;
        var timer = null;
        var dots = dotsWrap ? Array.prototype.slice.call(dotsWrap.children) : [];

        function show(n) {
            slides[current].classList.remove('active');
            if (dots[current]) dots[current].classList.remove('active');
            current = (n + slides.length) % slides.length;
            slides[current].classList.add('active');
            if (dots[current]) dots[current].classList.add('active');
        }
        function go(n) { show(n); restart(); }
        function next() { show(current + 1); }
        function prev() { show(current - 1); }
        function restart() {
            if (timer) clearInterval(timer);
            timer = setInterval(next, 5000); // auto-advance every 5s
        }

        // Hide arrows/dots if only one image
        if (slides.length <= 1) {
            var pv = document.getElementById('workPrev'), nx = document.getElementById('workNext');
            if (pv) pv.style.display = 'none';
            if (nx) nx.style.display = 'none';
            if (dotsWrap) dotsWrap.style.display = 'none';
            return;
        }

        var nextBtn = document.getElementById('workNext');
        var prevBtn = document.getElementById('workPrev');
        if (nextBtn) nextBtn.addEventListener('click', function(){ next(); restart(); });
        if (prevBtn) prevBtn.addEventListener('click', function(){ prev(); restart(); });

        // Pause on hover (desktop)
        slider.addEventListener('mouseenter', function(){ if (timer) clearInterval(timer); });
        slider.addEventListener('mouseleave', restart);

        restart();
    }, 600);
})();
</script>

<?php if (isset($conn) && $conn instanceof mysqli) { @$conn->close(); } ?>
</body>
</html>