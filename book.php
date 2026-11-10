<?php
// ── /book.php — focused booking page ───────────────────────────────
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';

$is_logged_in = isset($_SESSION['user_id']);

// Auto-fill if logged in
$autofill = $is_logged_in ? [
    'name'  => htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')),
    'phone' => htmlspecialchars($_SESSION['phone'] ?? ''),
    'email' => htmlspecialchars($_SESSION['email'] ?? ''),
] : ['name'=>'','phone'=>'','email'=>''];

// Preselected service & first-time offer from query string
$preselected_service = isset($_GET['svc']) ? htmlspecialchars($_GET['svc']) : '';
$claim_offer         = isset($_GET['offer']) && $_GET['offer'] === 'first10';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php @include __DIR__ . '/includes/analytics.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book a Car Service in Blantyre — Wiyule Motors</title>
<meta name="description" content="Book your car service online with Wiyule Motors, Blantyre's trusted auto workshop. Quick 30-second form — we WhatsApp you within an hour to confirm.">
<meta name="keywords" content="book car service Blantyre, auto repair booking Malawi, car service appointment, mechanic Blantyre">
<link rel="canonical" href="https://www.wiyulemotors.co.mw/book.php">
<meta property="og:title" content="Book a Car Service — Wiyule Motors">
<meta property="og:description" content="Book your car service online in 30 seconds. Blantyre's trusted auto workshop.">
<meta property="og:image" content="https://www.wiyulemotors.co.mw/assets/images/logo.png">
<meta property="og:url" content="https://www.wiyulemotors.co.mw/book.php">
<meta property="og:type" content="website">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { max-width: 100%; overflow-x: hidden; }
    body {
        font-family: 'Sora', sans-serif;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        color: #0f172a; min-height: 100vh; padding-bottom: 60px;
    }

    /* Slim nav */
    .nav {
        background: #ffffff; border-bottom: 1px solid #e2e8f0;
        padding: 14px 24px; display: flex; align-items: center; justify-content: space-between;
        position: sticky; top: 0; z-index: 50;
    }
    .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #0f172a; }
    .nav-brand img { width: 36px; height: 36px; object-fit: contain; }
    .nav-brand-text { font-weight: 800; font-size: 17px; letter-spacing: -0.01em; }
    .nav-brand-sub { font-size: 11px; color: #64748b; margin-top: -2px; }
    .nav-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; }
    .nav-back:hover { color: #dc2626; }

    /* Page header */
    .page-head { max-width: 760px; margin: 48px auto 28px; padding: 0 24px; text-align: center; }
    .eyebrow { display: inline-block; background: rgba(220,38,38,0.08); color: #dc2626;
               padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700;
               text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 14px; }
    .h1 { font-size: clamp(28px, 5vw, 42px); font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 12px; }
    .sub { font-size: 16px; color: #64748b; max-width: 540px; margin: 0 auto; line-height: 1.5; }

    /* Offer ribbon (if first-time offer claimed via URL) */
    .offer-ribbon {
        max-width: 760px; margin: 0 auto 24px; padding: 14px 18px;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 1px solid #f59e0b;
        border-radius: 14px;
        font-size: 14px; font-weight: 600; color: #92400e;
        display: flex; align-items: center; gap: 10px;
    }

    /* Card */
    .form-card {
        max-width: 760px; margin: 0 auto; padding: 0 16px;
    }
    .form-inner {
        background: #ffffff; border-radius: 24px;
        box-shadow: 0 8px 40px rgba(15,23,42,0.06);
        padding: 32px 28px;
        border: 1px solid rgba(15,23,42,0.05);
    }
    @media (min-width: 720px) { .form-inner { padding: 40px; } }

    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    @media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } }
    .field { display: flex; flex-direction: column; }
    .field label {
        font-size: 13px; font-weight: 600; color: #334155;
        margin-bottom: 8px;
    }
    .field label .req { color: #dc2626; }
    .field input, .field select, .field textarea {
        width: 100%; padding: 13px 14px;
        background: #ffffff;
        border: 1.5px solid #e2e8f0; border-radius: 12px;
        font-size: 15px; font-family: 'Sora', sans-serif;
        color: #0f172a; outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .field input:focus, .field select:focus, .field textarea:focus {
        border-color: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,0.08);
    }
    .field textarea { resize: vertical; min-height: 90px; }

    .toggle-row {
        margin: 18px 0; padding: 14px; background: #f8fafc; border-radius: 12px;
        display: flex; align-items: center; gap: 10px;
    }
    .toggle-row input { width: 18px; height: 18px; accent-color: #dc2626; cursor: pointer; }
    .toggle-row label { font-size: 13.5px; color: #334155; cursor: pointer; }

    /* ── Section headers ─────────────────────────── */
    .section {
        margin-bottom: 22px;
        padding-top: 8px;
        border-top: 1px solid #e2e8f0;
    }
    .section:first-of-type { border-top: none; padding-top: 0; }
    .section-title {
        font-size: 12px; font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 14px;
        display: flex; align-items: baseline; gap: 8px;
    }
    .section-title .step {
        background: #f1f5f9; color: #475569;
        padding: 2px 8px; border-radius: 999px;
        font-size: 10px; font-weight: 700;
    }
    .section-sub {
        font-size: 12px; color: #94a3b8;
        font-weight: 400; text-transform: none; letter-spacing: 0;
    }

    /* ── Visual service picker ─────────────────────────── */
    .service-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        margin-bottom: 10px;
    }
    @media (min-width: 720px) { .service-grid { grid-template-columns: repeat(3, 1fr); } }
    .svc-card {
        background: #fff; border: 1.5px solid #e2e8f0;
        border-radius: 14px; padding: 14px 12px;
        cursor: pointer;
        display: flex; flex-direction: column; align-items: center;
        gap: 8px;
        transition: all 0.15s;
        text-align: center;
        position: relative;
        min-height: 96px;
        justify-content: center;
    }
    .svc-card:hover { border-color: #cbd5e1; transform: translateY(-1px); }
    .svc-card.active {
        border-color: #dc2626; background: #fef2f2;
        box-shadow: 0 0 0 3px rgba(220,38,38,0.08);
    }
    .svc-card.active::after {
        content: "✓"; position: absolute;
        top: 8px; right: 10px;
        background: #dc2626; color: #fff;
        width: 18px; height: 18px;
        border-radius: 50%; font-size: 11px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
    }
    .svc-card svg { width: 28px; height: 28px; color: #475569; }
    .svc-card.active svg { color: #dc2626; }
    .svc-card-label {
        font-size: 12.5px; font-weight: 600;
        color: #0f172a; line-height: 1.3;
    }
    .svc-other-wrap { display: none; margin-top: 8px; }
    .svc-other-wrap.show { display: block; }

    /* ── Time-slot pills ─────────────────────────── */
    .time-pills { display: flex; gap: 8px; flex-wrap: wrap; }
    .time-pill {
        flex: 1; min-width: 120px;
        padding: 11px 16px;
        background: #fff; border: 1.5px solid #e2e8f0;
        border-radius: 11px; cursor: pointer;
        font-size: 13.5px; font-weight: 600;
        color: #334155;
        transition: all 0.12s;
        text-align: center;
    }
    .time-pill:hover { border-color: #cbd5e1; }
    .time-pill.active {
        background: #fef2f2; border-color: #dc2626; color: #dc2626;
    }
    .time-pill small { display: block; font-size: 11px; color: #94a3b8; font-weight: 500; margin-top: 2px; }
    .time-pill.active small { color: #ef4444; }

    /* ── Field hint text ─────────────────────────── */
    .field-hint {
        font-size: 11.5px; color: #94a3b8;
        margin-top: 4px; font-weight: 500;
    }
    .field-opt {
        font-size: 11px; color: #94a3b8;
        font-weight: 500; margin-left: 6px;
    }

    /* ── Problem photos ─────────────────────────── */
    .photo-help { font-size: 12px; color: #64748b; margin-bottom: 8px; line-height: 1.4; }
    .photo-drop {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 4px; padding: 18px 12px;
        border: 1.5px dashed #cbd5e1; border-radius: 12px;
        background: #f8fafc; cursor: pointer; text-align: center;
        transition: border-color 0.15s, background 0.15s;
    }
    .photo-drop:hover { border-color: #dc2626; background: #fef2f2; }
    .photo-drop svg { width: 26px; height: 26px; color: #94a3b8; }
    .photo-drop span { font-size: 13.5px; font-weight: 600; color: #334155; }
    .photo-drop small { font-size: 11px; color: #94a3b8; }
    .photo-drop.is-full { opacity: 0.5; pointer-events: none; }
    .photo-previews { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .photo-thumb {
        position: relative; width: 72px; height: 72px;
        border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0;
        background: #f1f5f9;
    }
    .photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .photo-thumb .rm {
        position: absolute; top: 2px; right: 2px;
        width: 20px; height: 20px; border-radius: 50%;
        background: rgba(15,23,42,0.75); color: #fff; border: none;
        font-size: 13px; line-height: 1; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .voice-link {
        display: inline-block; margin-top: 10px;
        font-size: 12.5px; font-weight: 600; color: #16a34a; text-decoration: none;
    }
    .voice-link:hover { text-decoration: underline; }

    .submit-btn {
        width: 100%; padding: 16px;
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: #fff; border: none; border-radius: 14px;
        font-size: 16px; font-weight: 700;
        font-family: 'Sora', sans-serif;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(220,38,38,0.3);
        transition: transform 0.15s, box-shadow 0.15s;
        display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    }
    .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(220,38,38,0.4); }
    .submit-btn:disabled { opacity: 0.6; cursor: wait; }

    .hint {
        text-align: center; font-size: 13px; color: #64748b;
        margin-top: 14px;
    }

    /* Alt-contact section */
    .alt-contact {
        max-width: 760px; margin: 36px auto 0; padding: 0 24px; text-align: center;
    }
    .alt-contact .or { font-size: 13px; color: #94a3b8; margin-bottom: 14px;
        position: relative; display: inline-flex; align-items: center; gap: 14px; }
    .alt-contact .or::before, .alt-contact .or::after {
        content: ''; display: block; width: 60px; height: 1px; background: #e2e8f0;
    }
    .alt-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .alt-btn {
        display: inline-flex; align-items: center; gap: 9px;
        padding: 12px 22px; border-radius: 12px;
        font-size: 14.5px; font-weight: 700; text-decoration: none;
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .alt-btn:hover { transform: translateY(-2px); }
    .alt-btn.wa { background: #25d366; color: #fff; box-shadow: 0 6px 18px rgba(37,211,102,0.3); }
    .alt-btn.call { background: #fff; color: #0f172a; border: 1.5px solid #e2e8f0; }
    .alt-btn svg { width: 17px; height: 17px; }

    /* Success modal */
    .success-modal {
        position: fixed; inset: 0; background: rgba(15,23,42,0.6);
        display: none; align-items: center; justify-content: center;
        z-index: 100; padding: 20px; backdrop-filter: blur(6px);
    }
    .success-modal.open { display: flex; }
    .success-box {
        background: #fff; border-radius: 22px; padding: 36px 28px;
        max-width: 420px; width: 100%; text-align: center;
        box-shadow: 0 40px 80px rgba(0,0,0,0.25);
    }
    .success-icon {
        width: 64px; height: 64px;
        background: rgba(34,197,94,0.12);
        color: #22c55e; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 18px;
    }
    .success-icon svg { width: 32px; height: 32px; }
    .success-title { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
    .success-msg { color: #64748b; font-size: 14px; line-height: 1.5; margin-bottom: 22px; }
    .success-actions {
        display: flex; flex-direction: column; gap: 10px;
    }
    .success-actions a {
        display: block; padding: 14px 16px; border-radius: 11px; text-decoration: none;
        font-weight: 700; font-size: 15px; text-align: center;
        transition: transform 0.12s ease, box-shadow 0.12s ease;
    }
    /* Primary: Send via WhatsApp */
    .success-actions .wa {
        background: #25d366; color: #fff;
        box-shadow: 0 6px 18px rgba(37,211,102,0.32);
        order: -1;  /* push to top regardless of HTML order */
    }
    .success-actions .wa:active { transform: scale(0.98); }
    /* Secondary: Back to home */
    .success-actions .home {
        background: transparent; color: #64748b;
        font-weight: 600; font-size: 13.5px;
        padding: 10px 16px;
    }
    .success-actions .home:hover { color: #0f172a; }
</style>
</head>
<body>

<!-- Slim nav -->
<nav class="nav">
    <a href="<?= $base ?>/" class="nav-brand">
        <img src="<?= $base ?>/assets/images/logo.png" alt="Wiyule Motors">
        <div>
            <div class="nav-brand-text">Wiyule Motors</div>
            <div class="nav-brand-sub">Blantyre, Malawi</div>
        </div>
    </a>
    <a href="<?= $base ?>/" class="nav-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to home
    </a>
</nav>

<!-- Page header -->
<div class="page-head">
    <span class="eyebrow">Book a Service</span>
    <h1 class="h1">Book in 30 seconds.</h1>
    <p class="sub">Tell us a bit about your car. We'll WhatsApp you within an hour to confirm.</p>
</div>

<?php if ($claim_offer): ?>
<div class="offer-ribbon">
    <span style="font-size:18px">🎉</span>
    <span>First-time customer offer applied — <strong>10% off</strong> your first service.</span>
</div>
<?php endif; ?>

<!-- Form card -->
<div class="form-card">
    <div class="form-inner">
        <form id="bookingForm" novalidate>

            <!-- ════════ SECTION 1 — YOUR DETAILS ════════ -->
            <div class="section">
                <div class="section-title">
                    <span class="step">1</span> Your Details
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="bookingName">Your Name <span class="req">*</span></label>
                        <input type="text" id="bookingName" name="name" value="<?= $autofill['name'] ?>" placeholder="Daniel Kumwenda" required>
                    </div>
                    <div class="field">
                        <label for="bookingPhone">Phone Number <span class="req">*</span></label>
                        <input type="tel" id="bookingPhone" name="phone" value="<?= $autofill['phone'] ?>" placeholder="+265 993 575 111" required>
                    </div>
                </div>
            </div>

            <!-- ════════ SECTION 2 — VEHICLE ════════ -->
            <div class="section">
                <div class="section-title">
                    <span class="step">2</span> Your Vehicle
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="bookingMake">Make <span class="req">*</span></label>
                        <input type="text" id="bookingMake" name="make" list="makesList" placeholder="Toyota" required autocomplete="off">
                        <datalist id="makesList">
                            <option value="Toyota"><option value="Nissan"><option value="BMW">
                            <option value="Mercedes-Benz"><option value="Honda"><option value="Ford">
                            <option value="Mitsubishi"><option value="Mazda"><option value="Volkswagen">
                            <option value="Audi"><option value="Hyundai"><option value="Kia">
                            <option value="Suzuki"><option value="Land Rover"><option value="Isuzu">
                            <option value="Subaru"><option value="Lexus"><option value="Volvo">
                        </datalist>
                    </div>
                    <div class="field">
                        <label for="bookingModel">Model <span class="req">*</span></label>
                        <input type="text" id="bookingModel" name="model" placeholder="Corolla" required>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="bookingYear">Year <span class="req">*</span></label>
                        <input type="number" id="bookingYear" name="year" min="1980" max="<?= (int)date('Y') + 1 ?>" placeholder="<?= (int)date('Y') - 5 ?>" required inputmode="numeric">
                    </div>
                    <div class="field">
                        <label for="bookingReg">Registration <span class="req">*</span></label>
                        <input type="text" id="bookingReg" name="registration" placeholder="e.g. BT 1234" required style="text-transform:uppercase">
                        <div class="field-hint">Your number plate</div>
                    </div>
                </div>
            </div>

            <!-- ════════ SECTION 3 — SERVICE ════════ -->
            <div class="section">
                <div class="section-title">
                    <span class="step">3</span> What Do You Need?
                </div>

                <label style="font-size:13px;font-weight:600;color:#475569;margin-bottom:10px;display:block">
                    Service <span class="req">*</span>
                </label>

                <!-- Visual service cards: top 6 + Other -->
                <div class="service-grid">
                    <?php
                    $top_services = [
                        ['name' => 'General Maintenance', 'icon' => 'wrench'],
                        ['name' => 'Engine Repair',       'icon' => 'engine'],
                        ['name' => 'Spray Painting',      'icon' => 'paint'],
                        ['name' => 'Panel Beating',       'icon' => 'hammer'],
                        ['name' => 'Auto Detailing',      'icon' => 'sparkle'],
                        ['name' => 'Vehicle Wrapping',    'icon' => 'wrap'],
                    ];
                    $icons = [
                        'wrench'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
                        'engine'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="10" rx="1"/><path d="M8 8V5h8v3M2 12h2M20 12h2M9 18v3M15 18v3"/></svg>',
                        'paint'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 11h2v6a2 2 0 0 1-2 2h-4M3 11V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6H3z"/><circle cx="8" cy="17" r="3"/></svg>',
                        'hammer'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12l-8.5 8.5a2.12 2.12 0 0 1-3-3L12 9M17.64 15 22 10.64M20.91 11.7l-1.25-1.25a2.12 2.12 0 0 1 0-3l1.13-1.13L17.64 3 14.5 6.14a2.12 2.12 0 0 0 0 3l1.25 1.25"/></svg>',
                        'sparkle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.7L20 10l-5.1 1.9L13 18l-1.9-5.1L6 11l5.1-1.3L12 3zM5 17l.7 2.3L8 20l-2.3.7L5 23l-.7-2.3L2 20l2.3-.7L5 17zM19 4l.5 1.5L21 6l-1.5.5L19 8l-.5-1.5L17 6l1.5-.5L19 4z"/></svg>',
                        'wrap'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M2 14h20M8 6v12M16 6v12"/></svg>',
                    ];
                    foreach ($top_services as $svc):
                        $is_selected = ($preselected_service === $svc['name']);
                    ?>
                    <div class="svc-card <?= $is_selected ? 'active' : '' ?>" data-svc="<?= htmlspecialchars($svc['name']) ?>">
                        <?= $icons[$svc['icon']] ?>
                        <div class="svc-card-label"><?= htmlspecialchars($svc['name']) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Other... opens dropdown -->
                    <div class="svc-card" id="svcOtherCard" data-svc="__other__">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9 9a3 3 0 1 1 5.12 2.12L12 13M12 17h.01"/></svg>
                        <div class="svc-card-label">Other Service…</div>
                    </div>
                </div>

                <!-- Hidden field carries the selected service name -->
                <input type="hidden" id="bookingService" name="service" value="<?= htmlspecialchars($preselected_service) ?>" required>

                <!-- "Other" dropdown only shows when Other card is selected -->
                <div class="svc-other-wrap" id="svcOtherWrap">
                    <div class="field">
                        <label for="bookingServiceOther">Pick another service <span class="req">*</span></label>
                        <select id="bookingServiceOther">
                            <option value="">Select…</option>
                            <?php
                            $other_services = ['Brake Services', 'AC Repair', 'Polishing', 'Battery Services', 'Auto Body Work', 'Parts Supply', 'Diagnostics', 'Tinting', 'Towing'];
                            foreach ($other_services as $svc): ?>
                                <option value="<?= htmlspecialchars($svc) ?>"><?= htmlspecialchars($svc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field-row" style="margin-top:14px">
                    <div class="field">
                        <label for="bookingDate">Preferred Date <span class="req">*</span></label>
                        <input type="date" id="bookingDate" name="date" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="field">
                        <label>Preferred Time <span class="field-opt">(optional)</span></label>
                        <div class="time-pills">
                            <div class="time-pill" data-time="08:30:00">Morning <small>8:30am – 12pm</small></div>
                            <div class="time-pill" data-time="13:00:00">Afternoon <small>1pm – 5pm</small></div>
                        </div>
                        <input type="hidden" id="bookingTime" name="time" value="">
                    </div>
                </div>

                <div class="field" style="margin-top:14px">
                    <label for="bookingNotes">Notes <span class="field-opt">(optional)</span></label>
                    <textarea id="bookingNotes" name="notes" placeholder="Anything we should know about your car or the work you need?"></textarea>
                </div>

                <!-- Problem photos (optional). File input has NO name — we append downscaled
                     blobs as photos[] on submit so big phone photos don't blow the upload limit. -->
                <div class="field" style="margin-top:14px">
                    <label>Add photos <span class="field-opt">(optional)</span></label>
                    <div class="photo-help">A picture of the damage, warning light or the part helps us prepare and quote faster.</div>
                    <input type="file" id="bookingPhotos" accept="image/*" multiple capture="environment" style="display:none">
                    <div class="photo-drop" id="photoDrop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span>Tap to add photos</span>
                        <small>Up to 4 · JPG or PNG</small>
                    </div>
                    <div class="photo-previews" id="photoPreviews"></div>
                    <a href="https://wa.me/265993575111" target="_blank" rel="noopener" class="voice-link">🎤 Prefer a voice note? Send it to us on WhatsApp →</a>
                </div>
            </div>

            <!-- ════════ SECTION 4 — A FEW MORE QUESTIONS ════════ -->
            <div class="section">
                <div class="section-title">
                    <span class="step">4</span> A Few More Questions
                    <span class="section-sub">all optional</span>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="bookingEmail">Email <span class="field-opt">(for invoices)</span></label>
                        <input type="email" id="bookingEmail" name="email" placeholder="you@example.com">
                    </div>
                    <div class="field">
                        <label for="bookingSource">How did you find us?</label>
                        <select id="bookingSource" name="source">
                            <option value="">Choose one…</option>
                            <option value="Google">Google search</option>
                            <option value="Friend">Friend or family</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Walked past">Walked past the shop</option>
                            <option value="Returning customer">Returning customer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($claim_offer): ?>
                <input type="hidden" name="claim_offer" value="1">
            <?php endif; ?>

            <button type="submit" class="submit-btn" id="submitBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
                Confirm Booking
            </button>
            <p class="hint">We'll WhatsApp you within an hour to confirm your appointment.</p>

        </form>
    </div>
</div>

<!-- Alternative contact -->
<div class="alt-contact">
    <div class="or">Or reach us directly</div>
    <div class="alt-btns">
        <a href="https://wa.me/265993575111" target="_blank" class="alt-btn wa">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
            Chat on WhatsApp
        </a>
        <a href="tel:+265993575111" class="alt-btn call">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/></svg>
            Call Us
        </a>
    </div>
</div>

<!-- Success modal -->
<div class="success-modal" id="successModal" onclick="if(event.target===this)closeSuccess()">
    <div class="success-box">
        <div class="success-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
        </div>
        <div class="success-title">Booking received 🎉</div>
        <div class="success-msg">Thanks! Tap below to send us a quick WhatsApp so we can confirm faster.</div>
        <div class="success-actions">
            <a href="<?= $base ?>/" class="home">Back to home</a>
            <a href="https://wa.me/265993575111" target="_blank" rel="noopener" class="wa" id="successWaBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:-3px;margin-right:6px">
                    <path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/>
                </svg>
                Send via WhatsApp
            </a>
        </div>
    </div>
</div>

<script>
const form     = document.getElementById('bookingForm');
const submit   = document.getElementById('submitBtn');
const modal    = document.getElementById('successModal');

// ══════════════════════════════════════════════════════
// Visual service picker — card click sets hidden input
// ══════════════════════════════════════════════════════
const svcHidden    = document.getElementById('bookingService');
const svcOtherWrap = document.getElementById('svcOtherWrap');
const svcOtherSel  = document.getElementById('bookingServiceOther');
const svcCards     = document.querySelectorAll('.svc-card');

function selectServiceCard(card) {
    svcCards.forEach(c => c.classList.remove('active'));
    card.classList.add('active');
    const val = card.getAttribute('data-svc');
    if (val === '__other__') {
        svcOtherWrap.classList.add('show');
        svcHidden.value = svcOtherSel.value || '';   // empty until they pick from dropdown
    } else {
        svcOtherWrap.classList.remove('show');
        svcHidden.value = val;
    }
}
svcCards.forEach(card => card.addEventListener('click', () => selectServiceCard(card)));
svcOtherSel.addEventListener('change', () => {
    if (document.getElementById('svcOtherCard').classList.contains('active')) {
        svcHidden.value = svcOtherSel.value;
    }
});

// If a service was preselected via ?svc=... and matches a top card, it'll already be .active.
// If preselected service is NOT one of the top 6, switch to Other automatically.
(function () {
    const pre = svcHidden.value;
    if (!pre) return;
    const matched = Array.from(svcCards).some(c =>
        c.getAttribute('data-svc') === pre && c.classList.contains('active')
    );
    if (!matched) {
        const otherCard = document.getElementById('svcOtherCard');
        selectServiceCard(otherCard);
        svcOtherSel.value = pre;
        svcHidden.value   = pre;
    }
})();

// ══════════════════════════════════════════════════════
// Time-slot pills
// ══════════════════════════════════════════════════════
const timePills = document.querySelectorAll('.time-pill');
const timeInput = document.getElementById('bookingTime');
timePills.forEach(pill => {
    pill.addEventListener('click', () => {
        const wasActive = pill.classList.contains('active');
        timePills.forEach(p => p.classList.remove('active'));
        if (wasActive) {
            timeInput.value = '';   // tap again to clear
        } else {
            pill.classList.add('active');
            timeInput.value = pill.getAttribute('data-time');
        }
    });
});

// ══════════════════════════════════════════════════════
// Auto-uppercase registration plate as user types
// ══════════════════════════════════════════════════════
const regInput = document.getElementById('bookingReg');
regInput.addEventListener('input', () => {
    const pos = regInput.selectionStart;
    regInput.value = regInput.value.toUpperCase();
    regInput.setSelectionRange(pos, pos);
});

// ══════════════════════════════════════════════════════
// Problem photos — downscale client-side, preview, attach on submit
// (keeps big phone photos under the host's upload limit)
// ══════════════════════════════════════════════════════
const PHOTO_MAX     = 4;
const photoInput    = document.getElementById('bookingPhotos');
const photoDrop     = document.getElementById('photoDrop');
const photoPreviews = document.getElementById('photoPreviews');
let photoBlobs = [];   // [{ blob, url }]

photoDrop.addEventListener('click', () => photoInput.click());
photoInput.addEventListener('change', async () => {
    const files = Array.from(photoInput.files || []);
    for (const file of files) {
        if (photoBlobs.length >= PHOTO_MAX) break;
        if (!file.type.startsWith('image/')) continue;
        try {
            const blob = await downscaleImage(file, 1280, 0.8);
            photoBlobs.push({ blob, url: URL.createObjectURL(blob) });
        } catch (_) { /* skip unreadable image */ }
    }
    photoInput.value = '';   // allow re-picking the same file
    renderPhotos();
});

function renderPhotos() {
    photoPreviews.innerHTML = '';
    photoBlobs.forEach((p, idx) => {
        const d = document.createElement('div');
        d.className = 'photo-thumb';
        const img = document.createElement('img'); img.src = p.url; img.alt = '';
        const btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'rm'; btn.setAttribute('aria-label', 'Remove'); btn.innerHTML = '&times;';
        btn.addEventListener('click', () => {
            URL.revokeObjectURL(p.url);
            photoBlobs.splice(idx, 1);
            renderPhotos();
        });
        d.appendChild(img); d.appendChild(btn);
        photoPreviews.appendChild(d);
    });
    photoDrop.classList.toggle('is-full', photoBlobs.length >= PHOTO_MAX);
}

// Load an image, scale so its longest side <= maxDim, return a JPEG Blob.
function downscaleImage(file, maxDim, quality) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            let width = img.width, height = img.height;
            if (width > maxDim || height > maxDim) {
                if (width >= height) { height = Math.round(height * maxDim / width); width = maxDim; }
                else { width = Math.round(width * maxDim / height); height = maxDim; }
            }
            const canvas = document.createElement('canvas');
            canvas.width = width; canvas.height = height;
            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
            URL.revokeObjectURL(img.src);
            canvas.toBlob(b => b ? resolve(b) : reject(new Error('encode failed')), 'image/jpeg', quality);
        };
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
    });
}

// ══════════════════════════════════════════════════════
// Form submit
// ══════════════════════════════════════════════════════
form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Manual validation for the hidden service field (HTML5 won't show error on hidden input)
    if (!svcHidden.value) {
        alert('Please pick a service.');
        document.querySelector('.service-grid').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    submit.disabled = true;
    submit.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="12" cy="12" r="10" opacity="0.3"/><path d="M22 12a10 10 0 0 0-10-10"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg> Sending…';

    const fd = new FormData(form);
    photoBlobs.forEach((p, i) => fd.append('photos[]', p.blob, 'photo' + (i + 1) + '.jpg'));

    try {
        const resp = await fetch('<?= $base ?>/pages/process-booking.php', {
            method: 'POST', body: fd, credentials: 'same-origin'
        });
        const text = await resp.text();
        let data; try { data = JSON.parse(text); } catch(_) { data = null; }

        if (data && data.success) {
            // ── Build a friendly WhatsApp summary for the customer to send to Wiyule Motors ──
            const d = data.data || {};
            const bookingRef = data.booking_id ? '#' + data.booking_id : '';

            // ── Format phone to +265 XXX XXX XXX (Malawi standard) ─────────
            // Strips junk, handles common Malawi number variations.
            function formatPhoneMW(raw) {
                if (!raw) return '';
                let digits = String(raw).replace(/\D/g, '');           // keep numerics only
                if (digits.length === 9) {                              // e.g. 993575111
                    digits = '265' + digits;
                } else if (digits.length === 10 && digits.startsWith('0')) {  // e.g. 0993575111
                    digits = '265' + digits.substring(1);
                } else if (digits.length === 12 && digits.startsWith('265')) {
                    // already correct
                } else {
                    // Unknown shape — return as-is with leading + if it looks international
                    return raw.trim();
                }
                // digits is now 12 chars starting with 265
                return '+' + digits.substring(0,3) + ' ' +
                            digits.substring(3,6) + ' ' +
                            digits.substring(6,9) + ' ' +
                            digits.substring(9,12);
            }

            // Format date as "26 Jun 2026" if we have one, else keep raw
            let prettyDate = d.date || '';
            if (prettyDate) {
                const parts = prettyDate.split('-');
                if (parts.length === 3) {
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const mi = parseInt(parts[1], 10) - 1;
                    if (mi >= 0 && mi < 12) {
                        prettyDate = parseInt(parts[2],10) + ' ' + months[mi] + ' ' + parts[0];
                    }
                }
            }

            // Services may be an array or a comma-string depending on flow
            const svcStr   = Array.isArray(d.services) ? d.services.join(', ') : (d.services || '');
            const notes    = (d.notes && d.notes.trim() && d.notes.trim().toLowerCase() !== 'none')
                           ? d.notes.trim() : '';
            const prettyPh = formatPhoneMW(d.phone);

            // Emojis via Unicode escapes — bypasses source-encoding issues that mangle literal glyphs
            const E = {
                wave:    '\u{1F44B}',   // 👋
                car:     '\u{1F697}',   // 🚗
                person:  '\u{1F464}',   // 👤
                phone:   '\u{1F4DE}',   // 📞
                tool:    '\u{1F527}',   // 🔧
                cal:     '\u{1F4C5}',   // 📅
                note:    '\u{1F4DD}',   // 📝
                check:   '\u{2705}',    // ✅
                tag:     '\u{1F516}'    // 🔖
            };

            const lines = [];
            lines.push(E.wave + ' Hi Wiyule Motors! I just booked a service online.');
            lines.push('');
            lines.push('*Booking Details*');
            if (d.name)     lines.push(E.person + ' *Name:* ' + d.name);
            if (prettyPh)   lines.push(E.phone  + ' *Phone:* ' + prettyPh);

            // Vehicle line — composed from the nested vehicle object
            if (d.vehicle) {
                const v = d.vehicle;
                const vehicleParts = [];
                if (v.make)  vehicleParts.push(v.make);
                if (v.model) vehicleParts.push(v.model);
                if (v.year)  vehicleParts.push('(' + v.year + ')');
                const vehicleLine = vehicleParts.join(' ').trim();
                if (vehicleLine) {
                    let line = E.car + ' *Vehicle:* ' + vehicleLine;
                    if (v.registration) line += ' — ' + String(v.registration).toUpperCase();
                    lines.push(line);
                }
            }

            if (svcStr)     lines.push(E.tool   + ' *Service:* ' + svcStr);
            if (prettyDate) lines.push(E.cal    + ' *Date:* ' + prettyDate);
            // Optional time slot
            if (d.time && d.time !== '09:00:00') {
                const t = String(d.time).substring(0,5);  // HH:MM
                lines.push('\u{1F551} *Time:* ' + t);
            }
            if (notes)      lines.push(E.note   + ' *Notes:* ' + notes);
            if (bookingRef) {
                lines.push('');
                lines.push(E.tag + ' *Ref:* ' + bookingRef);
            }
            lines.push('');
            lines.push(E.check + ' Please confirm. Thanks!');

            const msg     = lines.join('\n');
            const waLink  = 'https://wa.me/265993575111?text=' + encodeURIComponent(msg);
            const waBtn   = document.getElementById('successWaBtn');
            if (waBtn) waBtn.href = waLink;

            modal.classList.add('open');
            form.reset();
            if (typeof gtag !== 'undefined') {
                gtag('event','book_service',{event_category:'conversion',event_label:'booking_confirmed'});
            }
        } else {
            alert((data && data.message) || 'Sorry — something went wrong. Please WhatsApp us instead.');
        }
    } catch(err) {
        alert('Network problem. Please WhatsApp us at +265 993 575 111.');
    } finally {
        submit.disabled = false;
        submit.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="#fff" stroke="none"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg> Confirm Booking';
    }
});

function closeSuccess() { modal.classList.remove('open'); }
</script>

</body>
</html>
