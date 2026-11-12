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
    <title>Privacy Policy — Wiyule Motors</title>
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
    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Privacy Policy</h1>
    <p class="text-sm text-gray-500 mb-10">Last updated: <?= date('F Y') ?></p>

    <div class="prose prose-gray max-w-none space-y-6 text-gray-700 leading-relaxed">
        <h2 class="text-xl font-bold text-gray-900">1. Information We Collect</h2>
        <p>When you create an account or book a service, we collect your name, email address, phone number, vehicle details, and service preferences. We do not collect payment card information through this website.</p>

        <h2 class="text-xl font-bold text-gray-900">2. How We Use Your Information</h2>
        <p>We use your information to process bookings, send appointment confirmations and reminders, provide service history, improve our services, and send promotional offers if you opted in. We never sell your personal information to third parties.</p>

        <h2 class="text-xl font-bold text-gray-900">3. Data Storage</h2>
        <p>Your data is stored securely on our servers. Passwords are encrypted using industry-standard hashing. We retain your account data for as long as your account is active.</p>

        <h2 class="text-xl font-bold text-gray-900">4. Cookies</h2>
        <p>We use session cookies to keep you logged in and essential cookies for website functionality. We use Google Analytics to understand how visitors use our website. You can disable cookies in your browser settings.</p>

        <h2 class="text-xl font-bold text-gray-900">5. Third-Party Services</h2>
        <p>We use Formspree for contact form submissions and Google Analytics for website analytics. These services have their own privacy policies. We do not share your vehicle or service data with any third party.</p>

        <h2 class="text-xl font-bold text-gray-900">6. Your Rights</h2>
        <p>You have the right to access, correct, or delete your personal data at any time. You can update your information through your dashboard settings or by contacting us directly. To delete your account, contact us at +265 993 575 111.</p>

        <h2 class="text-xl font-bold text-gray-900">7. Contact</h2>
        <p>For privacy-related questions, contact us at <strong>+265 993 575 111</strong> or visit our workshop at Nyambadwe, Blantyre, Malawi.</p>
    </div>
</main>

<footer class="bg-gray-900 text-gray-400 py-6 text-center text-sm">
    <p>&copy; <?= date('Y') ?> Wiyule Motors. All rights reserved.</p>
</footer>
</body>
</html>
