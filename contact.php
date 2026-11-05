<?php
// ── /contact.php — focused contact page ───────────────────────────
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php @include __DIR__ . '/includes/analytics.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — Wiyule Motors</title>
<meta name="description" content="Visit, call, or WhatsApp Wiyule Motors in Blantyre, Malawi. Hours, location, and contact details.">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { max-width: 100%; overflow-x: hidden; }
    body {
        font-family: 'Sora', sans-serif;
        background: #f8fafc;
        color: #0f172a; min-height: 100vh; padding-bottom: 60px;
    }

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

    .page-head { max-width: 760px; margin: 48px auto 36px; padding: 0 24px; text-align: center; }
    .eyebrow { display: inline-block; background: rgba(220,38,38,0.08); color: #dc2626;
               padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700;
               text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 14px; }
    .h1 { font-size: clamp(28px, 5vw, 42px); font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 12px; }
    .sub { font-size: 16px; color: #64748b; max-width: 540px; margin: 0 auto; line-height: 1.5; }

    .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

    .contact-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px;
        margin-bottom: 36px;
    }
    @media (max-width: 800px) { .contact-grid { grid-template-columns: 1fr; } }

    .contact-card {
        background: #fff; padding: 28px 22px; border-radius: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(15,23,42,0.04);
        text-align: center;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .contact-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(15,23,42,0.08); }
    .contact-icon {
        width: 52px; height: 52px; border-radius: 14px;
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #dc2626;
        display: inline-flex; align-items: center; justify-content: center;
        margin-bottom: 16px;
    }
    .contact-icon svg { width: 24px; height: 24px; }
    .contact-card.green .contact-icon { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #15803d; }
    .contact-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
    .contact-card .detail { font-size: 14px; color: #64748b; margin-bottom: 14px; line-height: 1.5; }
    .contact-card .action {
        display: inline-block; padding: 9px 18px; border-radius: 10px;
        font-size: 13px; font-weight: 700; text-decoration: none;
        transition: transform 0.15s;
    }
    .contact-card .action:hover { transform: translateY(-1px); }
    .action.red { background: #dc2626; color: #fff; }
    .action.green { background: #25d366; color: #fff; }
    .action.gray { background: #f1f5f9; color: #0f172a; }

    .hours-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
        padding: 28px 32px; margin-bottom: 36px;
        max-width: 540px; margin-left: auto; margin-right: auto;
    }
    .hours-card h3 { font-size: 17px; font-weight: 800; margin-bottom: 18px; text-align: center; }
    .hours-row {
        display: flex; justify-content: space-between;
        padding: 10px 0; border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
    }
    .hours-row:last-child { border-bottom: none; }
    .hours-row .day { color: #334155; font-weight: 600; }
    .hours-row .time { color: #64748b; }
    .hours-row.open .time { color: #15803d; font-weight: 700; }

    .map-wrap {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(15,23,42,0.04);
    }
    .map-placeholder {
        padding: 50px 24px; text-align: center;
        background: linear-gradient(180deg, #f8fafc, #eef2f7);
    }
    .map-placeholder svg { width: 48px; height: 48px; color: #94a3b8; margin-bottom: 14px; }
    .map-placeholder h4 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
    .map-placeholder p { color: #64748b; font-size: 14px; margin-bottom: 18px; max-width: 380px; margin-left: auto; margin-right: auto; line-height: 1.5; }
    .map-placeholder a {
        display: inline-block; padding: 11px 22px; background: #dc2626;
        color: #fff; border-radius: 11px; font-weight: 700; font-size: 14px;
        text-decoration: none;
    }
</style>
</head>
<body>

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

<div class="page-head">
    <span class="eyebrow">Get in touch</span>
    <h1 class="h1">We're here to help.</h1>
    <p class="sub">Call, message, or stop by — we'll always take care of you.</p>
</div>

<div class="container">

    <!-- 3 contact cards -->
    <div class="contact-grid">

        <div class="contact-card">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1 .3 2.1.5 3.2.5.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.5-1 1-1h3.5c.6 0 1 .4 1 1 0 1.1.2 2.2.5 3.2.1.4 0 .7-.2 1z"/></svg>
            </div>
            <h3>Call us</h3>
            <p class="detail">+265 993 575 111<br>+265 996 834 038</p>
            <a href="tel:+265993575111" class="action red">Call now</a>
        </div>

        <div class="contact-card green">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
            </div>
            <h3>WhatsApp</h3>
            <p class="detail">Fastest way to reach us.<br>We reply within an hour.</p>
            <a href="https://wa.me/265993575111" target="_blank" class="action green">Open chat</a>
        </div>

        <div class="contact-card">
            <div class="contact-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2zm-2 0l-8 5-8-5zM4 18V8l8 5 8-5v10z"/></svg>
            </div>
            <h3>Email</h3>
            <p class="detail">For quotes, parts enquiries,<br>or formal bookings.</p>
            <a href="mailto:wiyulemotors@gmail.com" class="action gray">Send email</a>
        </div>

    </div>

    <!-- Hours card -->
    <div class="hours-card">
        <h3>Opening Hours</h3>
        <?php
        $today = (int)date('N'); // 1=Mon ... 7=Sun
        $days = [
            ['day'=>'Monday',    'time'=>'8:30 AM – 5:00 PM',  'n'=>1],
            ['day'=>'Tuesday',   'time'=>'8:30 AM – 5:00 PM',  'n'=>2],
            ['day'=>'Wednesday', 'time'=>'8:30 AM – 5:00 PM',  'n'=>3],
            ['day'=>'Thursday',  'time'=>'8:30 AM – 5:00 PM',  'n'=>4],
            ['day'=>'Friday',    'time'=>'8:30 AM – 5:00 PM',  'n'=>5],
            ['day'=>'Saturday',  'time'=>'9:00 AM – 1:00 PM',  'n'=>6],
            ['day'=>'Sunday',    'time'=>'Closed',             'n'=>7],
        ];
        foreach ($days as $d):
            $is_today = ($d['n'] === $today);
        ?>
        <div class="hours-row <?= $is_today ? 'open' : '' ?>">
            <span class="day"><?= $d['day'] ?><?= $is_today ? ' · Today' : '' ?></span>
            <span class="time"><?= $d['time'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Map placeholder (becomes a real Google Maps embed once GBP is verified) -->
    <div class="map-wrap">
        <div class="map-placeholder">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C7.6 2 4 5.6 4 10c0 5.4 8 12 8 12s8-6.6 8-12c0-4.4-3.6-8-8-8z"/><circle cx="12" cy="10" r="3"/></svg>
            <h4>Find us in Blantyre</h4>
            <p>We're located in Blantyre, Malawi. Tap below to get directions on Google Maps.</p>
            <a href="https://www.google.com/maps/search/Wiyule+Motors+Blantyre+Malawi" target="_blank">Get directions →</a>
        </div>
    </div>

</div>

</body>
</html>
