<?php
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php @include __DIR__ . '/includes/analytics.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — Wiyule Motors</title>
<meta name="description" content="Since 2016, Wiyule Motors has been Blantyre's trusted destination for premium auto services and parts.">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { max-width: 100%; overflow-x: hidden; }
    body { font-family: 'Sora', sans-serif; background: #f8fafc; color: #0f172a; min-height: 100vh; padding-bottom: 60px; }
    .nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
    .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #0f172a; }
    .nav-brand img { height: 46px; width: auto; object-fit: contain; }
    .nav-brand-text { font-weight: 800; font-size: 17px; }
    .nav-brand-sub { font-size: 11px; color: #64748b; margin-top: -2px; }
    .nav-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; }
    .nav-back:hover { color: #dc2626; }
    .container { max-width: 760px; margin: 56px auto; padding: 0 24px; }
    .eyebrow { display: inline-block; background: rgba(220,38,38,0.08); color: #dc2626; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 14px; }
    h1 { font-size: clamp(28px, 5vw, 42px); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 18px; line-height: 1.1; }
    .lead { font-size: 18px; color: #475569; line-height: 1.65; margin-bottom: 24px; }
    p { color: #334155; line-height: 1.7; margin-bottom: 18px; font-size: 16px; }
    .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin: 36px 0; padding: 22px; background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; }
    .stat { text-align: center; min-width: 0; }
    .stat-num { font-size: clamp(24px, 4vw, 36px); font-weight: 800; color: #dc2626; letter-spacing: -0.03em; white-space: nowrap; }
    .stat-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-top: 4px; }
    .cta { display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; text-decoration: none; border-radius: 12px; font-weight: 700; box-shadow: 0 6px 20px rgba(220,38,38,0.3); }
</style>
</head>
<body>

<nav class="nav">
    <a href="<?= $base ?>/" class="nav-brand">
        <img src="<?= $base ?>/assets/images/logo.png" alt="Wiyule & FA Motors">
    </a>
    <a href="<?= $base ?>/" class="nav-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to home
    </a>
</nav>

<div class="container">
    <span class="eyebrow">Our story</span>
    <h1>Built on trust since 2016.</h1>

    <p class="lead">Wiyule Motors started in Blantyre with a simple idea: car owners in Malawi deserve a workshop they can actually rely on — one that's honest about pricing, careful with their car, and modern in how it works.</p>

    <p>Almost a decade later, we've serviced thousands of vehicles for families, businesses, and fleet customers across Blantyre, Limbe, and beyond. Our certified technicians work on everything from routine maintenance and engine repairs to full body work, vehicle wrapping, and detailing.</p>

    <p>What's kept us going isn't just the work — it's the trust customers put in us. Every car that leaves our workshop carries our reputation with it, and we've built our business on never letting that down.</p>

    <div class="stats">
        <div class="stat"><div class="stat-num">10+</div><div class="stat-label">Years</div></div>
        <div class="stat"><div class="stat-num">5,000+</div><div class="stat-label">Vehicles</div></div>
        <div class="stat"><div class="stat-num">99%</div><div class="stat-label">Satisfaction</div></div>
    </div>

    <p>Whether your car needs a quick oil change or full bodywork after a knock, we'd love to take care of it for you.</p>

    <a href="<?= $base ?>/book.php" class="cta">Book a service →</a>
</div>

</body>
</html>
