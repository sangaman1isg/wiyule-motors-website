<?php
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — Wiyule Motors</title>
    <link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Sora',sans-serif;background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
        .code{font-size:clamp(80px,20vw,160px);font-weight:900;background:linear-gradient(135deg,#dc2626,#f87171);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
        .msg{font-size:18px;color:#94a3b8;margin:16px 0 32px;max-width:400px}
        .btn{display:inline-flex;align-items:center;gap:8px;background:#dc2626;color:#fff;padding:14px 28px;border-radius:14px;text-decoration:none;font-weight:700;font-size:15px;transition:all 0.2s}
        .btn:hover{background:#b91c1c;transform:translateY(-2px)}
    </style>
</head>
<body>
    <div>
        <div class="code">404</div>
        <p class="msg">This page doesn't exist. It may have moved or you might have mistyped the URL.</p>
        <a href="<?= $base ?>/" class="btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Back to Home
        </a>
    </div>
</body>
</html>
