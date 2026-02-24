<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Wiyule Motors offers premium automotive parts and services in Blantyre, Malawi. Shop high-quality parts and book expert vehicle maintenance today.">
    <meta name="keywords" content="automotive parts, vehicle maintenance, auto detailing, car repair, Wiyule Motors, Blantyre, Malawi, brake services, engine repair, oil change">
    <meta property="og:title" content="Wiyule Motors - Premium Automotive Parts & Services">
    <meta property="og:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta property="og:image" content="https://wiyulemotors.com/images/og-image.jpg">
    <meta property="og:url" content="https://wiyulemotors.com">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Wiyule Motors - Premium Automotive Parts & Services">
    <meta name="twitter:description" content="Your one-stop shop for automotive parts and services in Blantyre, Malawi.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#dc2626">
    <title>Wiyule Motors - Premium Automotive Parts & Services | Blantyre, Malawi</title>
    <link rel="icon" type="image/x-icon" href="/static/favicon.ico">
    <link rel="canonical" href="https://wiyulemotors.com">
    
    <!-- Google Analytics - Replace G-XXXXXXXXXX with your actual Google Analytics ID -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-HMM03HL39K"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '523407771');
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
    
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    
    <style>
        .hero-bg { background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.5) 100%); }
        .hero-bg-image {
            background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.5) 100%),
                        url('https://i.pinimg.com/1200x/7b/04/79/7b047946a1670be5b3b5488402c87378.jpg') center/cover no-repeat fixed;
            background-size: cover;
        }

        .hero-overlay-pattern {
            background-image: radial-gradient(circle at 25% 25%, rgba(220,38,38,0.3) 0%, transparent 50%),
                              radial-gradient(circle at 75% 75%, rgba(220,38,38,0.2) 0%, transparent 50%);
            opacity: 1;
        }

        .anim-delay-0 { animation-delay: 0s; }
        .anim-delay-1 { animation-delay: 1s; }
        .anim-delay-2s { animation-delay: 2s; }

        .text-shadow { text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

        .service-card { transition: all 0.3s ease; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .part-card:hover img { transform: scale(1.05); }
        .part-card img { transition: transform 0.3s ease; }
        .testimonial-card { transition: all 0.3s ease; }
        .testimonial-card:hover { background-color: #f8fafc; }
        button, a { transition: all 0.25s ease-in-out; }
        input:focus, textarea:focus, select:focus { outline: none; box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.3); border-color: #dc2626; }
        input, textarea, select { border: 2px solid #d1d5db; }
        
        #mobile-menu { 
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        #mobile-menu.active { 
            max-height: 500px;
        }

        .btn-book-service {
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            display: inline-block;
        }

        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .animate-bounce-slow {
            animation: bounce-slow 3s infinite;
        }

        .service-option {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .service-option:hover {
            transform: scale(1.02);
        }
        
        .service-option.selected {
            border-color: #dc2626;
            background-color: #fef2f2;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="font-[Inter] antialiased text-gray-800">
    <!-- WhatsApp Button -->
    <a href="https://wa.me/265993575111" class="fixed bottom-6 right-6 bg-green-500 text-white p-4 rounded-full shadow-lg hover:scale-110 transition z-50" target="_blank" rel="noopener noreferrer" title="Chat on WhatsApp">
        <i data-feather="message-circle"></i>
    </a>