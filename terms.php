<?php
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Terms of Service — Wiyule Motors</title>
    <link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-[Sora] antialiased bg-gray-50 text-gray-800">

<nav class="bg-white shadow-sm sticky top-0 z-50">
    <div style="height:3px;background:linear-gradient(90deg,#dc2626,#b91c1c,#7f1d1d)"></div>
    <div class="max-w-4xl mx-auto px-6 flex items-center justify-between h-16">
        <a href="<?= $base ?>/" class="flex items-center gap-2 text-lg font-bold text-gray-900" style="text-decoration:none">
            ← <span>Wiyule Motors</span>
        </a>
    </div>
</nav>

<main class="max-w-3xl mx-auto px-6 py-16">
    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Terms of Service</h1>
    <p class="text-sm text-gray-500 mb-10">Last updated: <?= date('F Y') ?></p>

    <div class="prose prose-gray max-w-none space-y-6 text-gray-700 leading-relaxed">
        <h2 class="text-xl font-bold text-gray-900">1. Services</h2>
        <p>Wiyule Motors provides automotive repair, maintenance, detailing, and parts supply services at our workshop located in Nyambadwe, Blantyre, Malawi. By using our website or booking a service, you agree to these terms.</p>

        <h2 class="text-xl font-bold text-gray-900">2. Online Bookings</h2>
        <p>Bookings made through our website are requests and are subject to confirmation by our team. We will contact you to confirm your appointment within 24 hours. We reserve the right to reschedule if necessary due to capacity or unforeseen circumstances.</p>

        <h2 class="text-xl font-bold text-gray-900">3. Pricing</h2>
        <p>All prices displayed on the website are estimates and starting prices. Final pricing will be confirmed after inspection of your vehicle. We will always communicate any additional costs before proceeding with work.</p>

        <h2 class="text-xl font-bold text-gray-900">4. Payment</h2>
        <p>We accept cash, mobile money (Airtel Money, TNM Mpamba), and bank transfers. Payment is due upon completion of service unless otherwise agreed in writing.</p>

        <h2 class="text-xl font-bold text-gray-900">5. Warranty</h2>
        <p>All services are backed by our workmanship guarantee. The warranty period varies by service type. Parts warranties are subject to manufacturer terms. Warranty is void if the vehicle is serviced by a third party for the same issue.</p>

        <h2 class="text-xl font-bold text-gray-900">6. User Accounts</h2>
        <p>You are responsible for maintaining the confidentiality of your account credentials. You must provide accurate information when creating an account. We reserve the right to suspend accounts that violate these terms.</p>

        <h2 class="text-xl font-bold text-gray-900">7. Limitation of Liability</h2>
        <p>Wiyule Motors shall not be liable for indirect, incidental, or consequential damages. Our total liability is limited to the amount paid for the specific service in question.</p>

        <h2 class="text-xl font-bold text-gray-900">8. Contact</h2>
        <p>For questions about these terms, contact us at <strong>+265 993 575 111</strong> or visit our workshop at Nyambadwe, Blantyre.</p>
    </div>
</main>

<footer class="bg-gray-900 text-gray-400 py-6 text-center text-sm">
    <p>&copy; <?= date('Y') ?> Wiyule Motors. All rights reserved.</p>
</footer>
</body>
</html>
