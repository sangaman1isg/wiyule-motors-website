<?php
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Pricing — Wiyule Motors</title>
    <meta name="description" content="Transparent service pricing for car maintenance, repairs, and detailing in Blantyre, Malawi. Get a free quote via WhatsApp.">
    <link rel="icon" type="image/x-icon" href="<?= $base ?>/static/favicon_io/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Sora', sans-serif; }
        body { background: #f8fafc; color: #0f172a; }
        .price-row:hover { background: #fafafa; }
        .service-category { border-bottom: 2px solid #fecaca; }
    </style>
</head>
<body class="antialiased">

<!-- WhatsApp Float -->
<a href="https://wa.me/265993575111" target="_blank" rel="noopener noreferrer" style="position:fixed;bottom:24px;right:24px;background:#25d366;color:#fff;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(37,211,102,0.5);z-index:50;text-decoration:none" title="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
</a>

<!-- Navigation -->
<nav class="bg-white shadow-md sticky top-0 z-40">
    <div style="height:3px;background:linear-gradient(90deg,#dc2626,#b91c1c,#7f1d1d);"></div>
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex justify-between items-center h-20">
            <a href="<?= $base ?>/" class="flex items-center space-x-3" style="text-decoration:none">
                <img class="h-12 w-auto" src="<?= $base ?>/assets/images/logo.png" alt="Wiyule Motors Logo">
                <div>
                    <span class="text-xl font-bold text-gray-900 block leading-tight">Wiyule Motors</span>
                    <span class="text-xs text-gray-400 font-medium" style="letter-spacing:0.04em">Blantyre, Malawi</span>
                </div>
            </a>
            <div class="hidden md:flex items-center gap-7">
                <a href="<?= $base ?>/"          class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Home</a>
                <a href="<?= $base ?>/#services" class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Services</a>
                <a href="<?= $base ?>/pricing.php" class="text-red-600 font-semibold text-sm">Pricing</a>
                <a href="<?= $base ?>/#booking"  class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Book</a>
                <a href="<?= $base ?>/#contact"  class="text-gray-600 hover:text-red-600 font-medium text-sm transition">Contact</a>
            </div>
            <a href="<?= $base ?>/#booking" class="bg-red-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-red-700 transition shadow-lg">Book Now</a>
        </div>
    </div>
</nav>

<!-- Hero -->
<section style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);color:#fff;padding:60px 20px 50px">
    <div class="max-w-4xl mx-auto text-center">
        <span style="display:inline-block;background:rgba(220,38,38,0.2);color:#fca5a5;font-size:12px;font-weight:800;padding:6px 14px;border-radius:99px;letter-spacing:0.06em;margin-bottom:16px">TRANSPARENT PRICING</span>
        <h1 class="text-4xl md:text-5xl font-black mb-4">Honest Service Rates</h1>
        <p class="text-lg text-gray-300 max-w-2xl mx-auto">No hidden fees. All prices in Malawi Kwacha (MWK). Exact quote depends on vehicle and parts — WhatsApp us for a precise estimate.</p>
    </div>
</section>

<!-- Pricing Tables -->
<section class="py-16 px-4">
    <div class="max-w-4xl mx-auto">

        <!-- General Service -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">General Maintenance</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ([
                    ['Oil Change (Standard)', 'Regular mineral oil + filter', 'MWK 15,000 – 22,000'],
                    ['Oil Change (Synthetic)', 'Full synthetic oil + filter', 'MWK 25,000 – 40,000'],
                    ['Full Service', 'Oil, filters, inspection, top-ups', 'MWK 50,000 – 120,000'],
                    ['Major Service', 'Full service + transmission, coolant flush', 'MWK 120,000 – 250,000'],
                    ['Air Filter Replacement', 'Engine + cabin filters', 'MWK 8,000 – 25,000'],
                    ['Fluid Top-Up', 'Coolant, brake fluid, power steering', 'MWK 5,000 – 15,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Brakes & Suspension -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Brakes & Suspension</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ([
                    ['Brake Pads (Front)', 'Replacement + inspection', 'MWK 30,000 – 60,000'],
                    ['Brake Pads (Rear)', 'Replacement + inspection', 'MWK 25,000 – 55,000'],
                    ['Brake Discs/Rotors', 'Resurfacing or replacement', 'MWK 40,000 – 120,000'],
                    ['Brake Fluid Flush', 'Complete system flush', 'MWK 15,000 – 25,000'],
                    ['Shock Absorbers (pair)', 'Front or rear', 'MWK 80,000 – 200,000'],
                    ['Suspension Check', 'Full inspection & diagnosis', 'MWK 10,000 – 20,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Engine & Diagnostics -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Engine & Diagnostics</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ([
                    ['Computer Diagnostics', 'OBD2 scan + report', 'MWK 15,000 – 25,000'],
                    ['Spark Plugs Replacement', '4-cylinder', 'MWK 25,000 – 50,000'],
                    ['Timing Belt Replacement', 'Including tensioner', 'MWK 150,000 – 400,000'],
                    ['Radiator Flush', 'Cooling system service', 'MWK 25,000 – 45,000'],
                    ['Engine Mount Replacement', 'Per mount', 'MWK 40,000 – 90,000'],
                    ['Clutch Replacement', 'Kit + labor', 'MWK 200,000 – 500,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tyres & Wheels -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Tyres & Wheels</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ([
                    ['Tyre Rotation', '4 wheels', 'MWK 8,000 – 15,000'],
                    ['Wheel Balancing', 'Per wheel', 'MWK 5,000 – 10,000'],
                    ['Wheel Alignment', 'Computer alignment', 'MWK 25,000 – 50,000'],
                    ['Puncture Repair', 'Single tyre', 'MWK 3,000 – 8,000'],
                    ['New Tyre Fitting', 'Per tyre (price varies by size)', 'MWK 80,000 – 350,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AC, Electrical & Detailing -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8">
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">AC, Electrical & Detailing</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ([
                    ['AC Regas', 'Refrigerant recharge', 'MWK 30,000 – 55,000'],
                    ['AC System Repair', 'Compressor/condenser issues', 'MWK 80,000 – 250,000'],
                    ['Battery Replacement', 'New battery + fitting', 'MWK 70,000 – 180,000'],
                    ['Alternator Repair', 'Testing + rebuild', 'MWK 60,000 – 150,000'],
                    ['Interior Detailing', 'Deep clean, shampoo seats', 'MWK 30,000 – 60,000'],
                    ['Exterior Polish & Wax', 'Hand wash + polish + wax', 'MWK 25,000 – 50,000'],
                    ['Full Detail Package', 'Interior + exterior premium', 'MWK 80,000 – 150,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Vehicle Wrapping (Premium) -->
        <div class="bg-white rounded-3xl shadow-lg p-8 mb-8" style="border:2px solid #fecaca;background:linear-gradient(135deg,#fff 0%,#fef5f5 100%);position:relative">
            <div style="position:absolute;top:-12px;right:20px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-size:10px;font-weight:800;padding:4px 12px;border-radius:99px;letter-spacing:0.08em;text-transform:uppercase;box-shadow:0 4px 12px rgba(220,38,38,0.3)">Premium Service</div>
            <div class="flex items-center gap-3 mb-6 service-category pb-4">
                <div style="width:44px;height:44px;background:#fef2f2;border-radius:12px;display:flex;align-items:center;justify-content:center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900">Vehicle Wrapping</h2>
            </div>
            <p class="text-sm text-gray-600 mb-4">Transform your vehicle with premium vinyl wraps. All wraps use high-quality 3M or similar automotive-grade vinyl with full paint protection.</p>
            <div class="space-y-2">
                <?php foreach ([
                    ['Partial Wrap', 'Bonnet, roof, or side panels', 'MWK 1,500,000 – 2,000,000'],
                    ['Full Colour Change Wrap', 'Complete exterior (standard colours)', 'MWK 2,000,000 – 2,500,000'],
                    ['Premium Finish Wrap', 'Matte, satin, carbon fibre, chrome', 'MWK 2,500,000 – 3,000,000'],
                    ['Paint Protection Film (PPF)', 'Front end or full body clear protection', 'MWK 1,500,000 – 3,000,000'],
                    ['Wrap Removal', 'Safe removal of existing wrap', 'MWK 200,000 – 500,000'],
                ] as $row): ?>
                <div class="price-row flex items-center justify-between py-3 px-4 rounded-xl transition">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row[0]) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($row[1]) ?></p>
                    </div>
                    <p class="font-bold text-red-600 text-sm md:text-base whitespace-nowrap ml-4"><?= htmlspecialchars($row[2]) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px;padding:14px 18px;background:#fef2f2;border-radius:12px;font-size:13px;color:#991b1b">
                <strong>Note:</strong> Wrapping price depends on vehicle size, chosen material, and design complexity. Book a consultation via WhatsApp for an exact quote with colour samples.
            </div>
        </div>

        <!-- Pricing Info -->
        <div class="bg-blue-50 border-l-4 border-blue-500 rounded-2xl p-6 mb-8">
            <h3 class="text-lg font-bold text-blue-900 mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                Why the price ranges?
            </h3>
            <ul class="text-sm text-blue-900 space-y-2">
                <li>• <strong>Vehicle type</strong> — Larger vehicles (SUVs, pickups) use more oil, larger parts, and take longer</li>
                <li>• <strong>Parts quality</strong> — Genuine OEM parts cost more than aftermarket; we explain your options</li>
                <li>• <strong>Condition</strong> — Extra wear or damaged components add to labor time</li>
                <li>• <strong>Urgency</strong> — Same-day service may include a priority fee</li>
            </ul>
        </div>

        <!-- CTA -->
        <div style="background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:24px;padding:40px 32px;color:#fff;text-align:center">
            <h2 class="text-3xl md:text-4xl font-black mb-3">Get a Precise Quote</h2>
            <p class="text-lg text-red-100 mb-6 max-w-xl mx-auto">Tell us your vehicle and what it needs — we'll give you an exact price over WhatsApp in minutes.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="https://wa.me/265993575111?text=<?= urlencode('Hi Wiyule Motors, I need a quote. My vehicle is: ') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center bg-white text-red-600 px-8 py-4 rounded-xl font-bold text-base hover:bg-gray-100 transition-all shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mr-2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    WhatsApp Quote
                </a>
                <a href="<?= $base ?>/#booking" class="inline-flex items-center justify-center bg-gray-900 text-white px-8 py-4 rounded-xl font-bold text-base hover:bg-black transition-all shadow-lg">
                    Book a Service →
                </a>
            </div>
        </div>

    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 py-8 text-center text-sm">
    <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-2">
        <span>&copy; <?= date('Y') ?> Wiyule Motors. All rights reserved.</span>
        <div class="flex gap-5">
            <a href="<?= $base ?>/terms.php"   class="hover:text-white transition">Terms</a>
            <a href="<?= $base ?>/privacy.php" class="hover:text-white transition">Privacy</a>
            <a href="<?= $base ?>/#contact"    class="hover:text-white transition">Contact</a>
        </div>
    </div>
</footer>

</body>
</html>
