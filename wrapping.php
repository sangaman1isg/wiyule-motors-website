<?php
// ── /wrapping.php — Interactive 3D Vehicle Wrapping configurator ──────
session_start();
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php @include __DIR__ . '/includes/analytics.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Wrapping — Wiyule Motors</title>
<meta name="description" content="Custom vehicle wrapping in Blantyre, Malawi. Change your car's colour in matte, gloss, satin, metallic, chrome or carbon fibre — protect your paint and brand your business. Design your wrap in 3D, then request a quote.">
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/logo-square.png?v=4">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Three.js (only external dependency — lazy-loaded on demand inside the module) -->
<script type="importmap">{ "imports": {
  "three": "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js",
  "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"
}}</script>

<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { max-width: 100%; overflow-x: hidden; }
    body { font-family: 'Sora', sans-serif; background: #f8fafc; color: #0f172a; min-height: 100vh; }

    /* ── Nav (matches about.php / contact.php) ── */
    .nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
    .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #0f172a; }
    .nav-brand img { height: 46px; width: auto; object-fit: contain; }
    .nav-back { display: inline-flex; align-items: center; gap: 6px; color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; }
    .nav-back:hover { color: #dc2626; }

    .container { max-width: 1100px; margin: 0 auto; padding: 0 20px; }

    /* ── Hero ── */
    .hero { text-align: center; max-width: 760px; margin: 52px auto 30px; padding: 0 24px; }
    .eyebrow { display: inline-block; background: rgba(220,38,38,0.08); color: #dc2626; padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 14px; }
    h1 { font-size: clamp(28px, 5vw, 42px); font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; margin-bottom: 14px; }
    .hero .lead { font-size: 17px; color: #475569; line-height: 1.6; max-width: 600px; margin: 0 auto; }

    /* ── Configurator card ── */
    .configurator { background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; box-shadow: 0 4px 20px rgba(15,23,42,0.04); overflow: hidden; margin-bottom: 30px; }

    .stage { position: relative; width: 100%; aspect-ratio: 16 / 11; max-height: 520px;
             background: radial-gradient(130% 110% at 50% 18%, #ffffff 0%, #eef2f7 48%, #dfe5ec 100%); }
    @media (max-width: 560px) { .stage { aspect-ratio: 4 / 3; } }
    #wrapCanvas { position: absolute; inset: 0; width: 100%; height: 100%; display: block; opacity: 0; transition: opacity .6s ease; touch-action: none; }
    #wrapCanvas.live { opacity: 1; }

    /* Poster / fallback layer (also the live preview when WebGL is unavailable) */
    .stage-poster { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 18px; text-align: center; }
    .stage-poster.hidden { display: none; }
    .svg-car { width: min(78%, 420px); height: auto; filter: drop-shadow(0 26px 24px rgba(15,23,42,0.18)); transition: filter .3s; }
    .svg-car .sheen { opacity: 0; transition: opacity .35s; }
    .poster-cta { margin-top: 10px; }
    .poster-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; border: none; border-radius: 12px; font-family: inherit; font-weight: 700; font-size: 14px; cursor: pointer; box-shadow: 0 6px 20px rgba(220,38,38,0.3); }
    .poster-btn:disabled { opacity: .75; cursor: default; }
    .poster-hint { margin-top: 10px; font-size: 12px; color: #64748b; }
    .spinner { width: 15px; height: 15px; border: 2px solid rgba(255,255,255,0.4); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Live selection badge over the stage */
    .stage-badge { position: absolute; left: 16px; bottom: 14px; display: inline-flex; align-items: center; gap: 9px; background: rgba(15,23,42,0.82); color: #fff; padding: 9px 15px; border-radius: 12px; font-size: 13px; font-weight: 600; backdrop-filter: blur(6px); max-width: calc(100% - 32px); }
    .stage-badge .dot { width: 15px; height: 15px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); flex-shrink: 0; }
    .stage-badge .sel-finish { color: #fca5a5; }
    .stage-hint { position: absolute; right: 14px; top: 14px; font-size: 11px; color: #64748b; background: rgba(255,255,255,0.72); padding: 5px 10px; border-radius: 99px; pointer-events: none; opacity: 0; transition: opacity .4s; }
    .stage-hint.show { opacity: 1; }

    /* ── Controls ── */
    .controls { padding: 22px; border-top: 1px solid #f1f5f9; }
    .ctrl-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-bottom: 11px; }

    .finish-row { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .finish-row::-webkit-scrollbar { display: none; }
    .finish-btn { flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border: 1.5px solid #e2e8f0; background: #fff; border-radius: 11px; font-family: inherit; font-size: 13px; font-weight: 600; color: #475569; cursor: pointer; transition: all .15s; white-space: nowrap; }
    .finish-btn:hover { border-color: #cbd5e1; }
    .finish-btn.active { border-color: #dc2626; background: #dc2626; color: #fff; box-shadow: 0 4px 14px rgba(220,38,38,0.25); }
    .finish-btn .fi { width: 8px; height: 8px; border-radius: 50%; background: currentColor; opacity: .55; }

    .swatches { display: flex; flex-wrap: wrap; gap: 11px; margin-top: 8px; }
    .swatch { width: 42px; height: 42px; border-radius: 50%; cursor: pointer; border: 2px solid rgba(15,23,42,0.08); position: relative; transition: transform .12s; box-shadow: inset 0 2px 5px rgba(255,255,255,0.35), 0 2px 6px rgba(15,23,42,0.08); }
    .swatch:hover { transform: scale(1.08); }
    .swatch.active { box-shadow: 0 0 0 3px #fff, 0 0 0 5px #dc2626; }
    .swatch[data-light="1"] { border-color: rgba(15,23,42,0.18); }

    .divider { height: 1px; background: #f1f5f9; margin: 20px 0; }

    /* ── Benefits ── */
    .section-head { text-align: center; max-width: 620px; margin: 56px auto 28px; }
    .section-head h2 { font-size: clamp(22px,4vw,30px); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 8px; }
    .section-head p { color: #64748b; font-size: 15px; line-height: 1.5; }
    .benefits { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 20px; }
    @media (max-width: 800px) { .benefits { grid-template-columns: 1fr; } }
    .benefit { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 4px 20px rgba(15,23,42,0.04); padding: 26px 22px; }
    .benefit .b-icon { width: 50px; height: 50px; border-radius: 14px; background: linear-gradient(135deg,#fee2e2,#fecaca); color: #dc2626; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 15px; }
    .benefit.green .b-icon { background: linear-gradient(135deg,#d1fae5,#a7f3d0); color: #15803d; }
    .benefit .b-icon svg { width: 24px; height: 24px; }
    .benefit h3 { font-size: 17px; font-weight: 700; margin-bottom: 7px; }
    .benefit p { font-size: 14px; color: #64748b; line-height: 1.6; }

    /* ── Quote section ── */
    .quote-grid { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 20px; margin-bottom: 50px; }
    @media (max-width: 800px) { .quote-grid { grid-template-columns: 1fr; } }

    .wa-card { background: linear-gradient(160deg,#ffffff,#f0fdf4); border: 1px solid #bbf7d0; border-radius: 20px; padding: 28px 24px; display: flex; flex-direction: column; }
    .wa-card .wa-badge { display: inline-flex; align-items: center; gap: 7px; align-self: flex-start; background: #dcfce7; color: #15803d; padding: 6px 13px; border-radius: 99px; font-size: 12px; font-weight: 700; margin-bottom: 14px; }
    .wa-card h3 { font-size: 20px; font-weight: 800; letter-spacing: -0.01em; margin-bottom: 8px; }
    .wa-card p { font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 20px; }
    .wa-summary { background: rgba(255,255,255,0.7); border: 1px solid #d1fae5; border-radius: 13px; padding: 13px 15px; font-size: 13px; color: #334155; margin-bottom: 20px; line-height: 1.7; }
    .wa-summary b { color: #0f172a; }
    .wa-btn { margin-top: auto; display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 15px 22px; background: #25d366; color: #fff; text-decoration: none; border-radius: 13px; font-weight: 700; font-size: 15px; box-shadow: 0 8px 22px rgba(37,211,102,0.3); transition: transform .15s; }
    .wa-btn:hover { transform: translateY(-2px); }

    .form-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 4px 20px rgba(15,23,42,0.04); padding: 28px 24px; }
    .form-card h3 { font-size: 20px; font-weight: 800; letter-spacing: -0.01em; margin-bottom: 4px; }
    .form-card .fsub { font-size: 13px; color: #64748b; margin-bottom: 20px; }
    .field { margin-bottom: 15px; }
    .field label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; }
    .field .req { color: #dc2626; }
    .field input, .field select, .field textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 11px; font-family: inherit; font-size: 14px; color: #0f172a; background: #fff; transition: border-color .15s; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: #dc2626; }
    .field textarea { resize: vertical; min-height: 78px; }
    .sel-readout { display: flex; gap: 9px; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 11px; padding: 11px 13px; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 15px; }
    .sel-readout .dot { width: 18px; height: 18px; border-radius: 50%; border: 1.5px solid rgba(15,23,42,0.12); flex-shrink: 0; }
    .submit-btn { width: 100%; padding: 15px; background: linear-gradient(135deg,#dc2626,#b91c1c); color: #fff; border: none; border-radius: 13px; font-family: inherit; font-weight: 700; font-size: 15px; cursor: pointer; box-shadow: 0 6px 20px rgba(220,38,38,0.3); display: inline-flex; align-items: center; justify-content: center; gap: 9px; transition: transform .15s; }
    .submit-btn:hover { transform: translateY(-2px); }
    .submit-btn:disabled { opacity: .7; cursor: default; transform: none; }
    .form-msg { margin-top: 14px; padding: 13px 15px; border-radius: 12px; font-size: 14px; font-weight: 600; display: none; }
    .form-msg.show { display: block; }
    .form-msg.ok { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .form-msg.err { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

    /* ── Footer (matches site footer.php style) ── */
    footer { background: #111827; color: #fff; padding: 46px 0 30px; }
    .foot-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 32px; margin-bottom: 30px; }
    @media (max-width: 700px) { .foot-grid { grid-template-columns: 1fr; gap: 24px; } }
    footer h4 { font-size: 18px; font-weight: 700; margin-bottom: 14px; }
    footer p, footer li { color: #9ca3af; line-height: 1.7; font-size: 14px; }
    footer ul { list-style: none; }
    footer a { color: #9ca3af; text-decoration: none; transition: color .15s; }
    footer a:hover { color: #fff; }
    .foot-bottom { border-top: 1px solid #1f2937; padding-top: 22px; text-align: center; color: #9ca3af; font-size: 13px; }
</style>
</head>
<body>

<nav class="nav">
    <a href="<?= $base ?>/" class="nav-brand">
        <img src="<?= $base ?>/assets/images/logo.png?v=4" alt="Wiyule & FA Motors">
    </a>
    <a href="<?= $base ?>/" class="nav-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Back to home
    </a>
</nav>

<!-- ── Hero ── -->
<div class="hero">
    <span class="eyebrow">3D Wrap Studio</span>
    <h1>Design your wrap in 3D.</h1>
    <p class="lead">Spin the car, pick a colour and a finish — matte, gloss, satin, metallic, chrome or carbon fibre — and see it come to life. When it looks right, send us the exact spec for a quote.</p>
</div>

<div class="container">

    <!-- ── Configurator ── -->
    <div class="configurator" id="configurator">
        <div class="stage" id="stage">
            <canvas id="wrapCanvas"></canvas>

            <!-- Poster / fallback (also the live preview if WebGL is blocked) -->
            <div class="stage-poster" id="poster">
                <svg class="svg-car" id="svgCar" viewBox="0 0 600 240" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <defs>
                        <linearGradient id="sheenGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#ffffff" stop-opacity="0.85"/>
                            <stop offset="0.5" stop-color="#ffffff" stop-opacity="0.12"/>
                            <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <!-- body -->
                    <path id="svgCarBody" d="M40,182 L40,166 Q40,150 72,146 L156,140 Q182,104 236,100 L360,100 Q416,104 452,140 L540,151 Q562,154 562,172 L562,181 Q562,191 549,191 L53,191 Q40,191 40,182 Z" fill="#dc2626"/>
                    <!-- glass -->
                    <path d="M176,138 Q188,112 236,109 L352,109 Q404,112 436,138 Z" fill="#141a24" opacity="0.92"/>
                    <!-- sheen overlay -->
                    <path class="sheen" id="svgSheen" d="M40,166 Q40,150 72,146 L156,140 Q182,104 236,100 L360,100 Q416,104 452,140 L540,151 Q562,154 562,168 L310,150 Z" fill="url(#sheenGrad)"/>
                    <!-- wheels -->
                    <g>
                        <circle cx="160" cy="191" r="34" fill="#15171c"/>
                        <circle cx="160" cy="191" r="16" fill="#c7ccd4"/>
                        <circle cx="160" cy="191" r="6" fill="#6b7280"/>
                        <circle cx="452" cy="191" r="34" fill="#15171c"/>
                        <circle cx="452" cy="191" r="16" fill="#c7ccd4"/>
                        <circle cx="452" cy="191" r="6" fill="#6b7280"/>
                    </g>
                    <!-- headlight / taillight -->
                    <rect x="544" y="158" width="16" height="10" rx="3" fill="#fff6e0"/>
                    <rect x="42" y="160" width="12" height="9" rx="3" fill="#b91c1c"/>
                </svg>

                <div class="poster-cta" id="posterCta">
                    <button class="poster-btn" id="bootBtn" type="button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></svg>
                        View in 3D
                    </button>
                    <div class="poster-hint" id="posterHint">Drag to rotate · pinch to zoom</div>
                </div>
            </div>

            <div class="stage-hint" id="stageHint">Drag to rotate</div>
            <div class="stage-badge">
                <span class="dot" id="badgeDot" style="background:#dc2626;"></span>
                <span><span id="badgeColour">Wiyule Red</span> · <span class="sel-finish" id="badgeFinish">Gloss</span></span>
            </div>
        </div>

        <!-- Controls -->
        <div class="controls">
            <div class="ctrl-label">Finish</div>
            <div class="finish-row" id="finishRow">
                <button class="finish-btn active" data-finish="gloss"   type="button"><span class="fi"></span>Gloss</button>
                <button class="finish-btn"        data-finish="matte"   type="button"><span class="fi"></span>Matte</button>
                <button class="finish-btn"        data-finish="satin"   type="button"><span class="fi"></span>Satin</button>
                <button class="finish-btn"        data-finish="metallic"type="button"><span class="fi"></span>Metallic</button>
                <button class="finish-btn"        data-finish="chrome"  type="button"><span class="fi"></span>Chrome</button>
                <button class="finish-btn"        data-finish="carbon"  type="button"><span class="fi"></span>Carbon</button>
            </div>

            <div class="divider"></div>

            <div class="ctrl-label">Colour · <span id="colourNameInline" style="color:#0f172a;">Wiyule Red</span></div>
            <div class="swatches" id="swatches"></div>
        </div>
    </div>

    <!-- ── Benefits ── -->
    <div class="section-head">
        <h2>Why wrap your vehicle?</h2>
        <p>A professional vinyl wrap transforms your car, protects what's underneath, and turns a work vehicle into a moving billboard.</p>
    </div>
    <div class="benefits">
        <div class="benefit">
            <div class="b-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 1.5 1.5 0 0 0 1-2.6 1.5 1.5 0 0 1 1-2.6h2a3 3 0 0 0 3-3A10 10 0 0 0 12 2z"/></svg></div>
            <h3>A whole new colour</h3>
            <p>Go matte military green, gloss black, chrome gold or carbon fibre — a reversible colour change that costs far less than a full respray.</p>
        </div>
        <div class="benefit green">
            <div class="b-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5l-8-3z"/><path d="m9 12 2 2 4-4"/></svg></div>
            <h3>Protects your paint</h3>
            <p>The vinyl shields your original factory paint from stone chips, sun and scratches — and peels off cleanly, keeping resale value high.</p>
        </div>
        <div class="benefit">
            <div class="b-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
            <h3>Brand your business</h3>
            <p>Turn fleet vehicles, bakkies and delivery vans into mobile adverts with your logo, colours and contact details across Blantyre.</p>
        </div>
    </div>

    <!-- ── Quote ── -->
    <div class="section-head">
        <h2>Happy with the look? Get a quote.</h2>
        <p>Send us your exact spec on WhatsApp (fastest), or leave your details and we'll come back to you with pricing.</p>
    </div>
    <div class="quote-grid">
        <!-- WhatsApp -->
        <div class="wa-card">
            <span class="wa-badge">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2z"/></svg>
                Fastest reply
            </span>
            <h3>Send it on WhatsApp</h3>
            <p>Your selection is already in the message — just hit send and we'll reply with a price, usually within the hour.</p>
            <div class="wa-summary">
                Vehicle: <b id="waVehicle">Not specified</b><br>
                Colour: <b id="waColour">Wiyule Red</b><br>
                Finish: <b id="waFinish">Gloss</b>
            </div>
            <a class="wa-btn" id="waBtn" href="#" target="_blank" rel="noopener">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.8.9.9-2.7-.2-.3A8 8 0 1 1 12 20zm4.5-5.9c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1-.7-.3-1.5-.6-2.4-1.6-.3-.4-.7-.9-.1-1.3.1-.1.2-.3.3-.4.1-.1.1-.2.2-.4 0-.1 0-.3 0-.4 0-.1-.5-1.3-.7-1.8-.2-.4-.4-.4-.5-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 2s.9 2.3 1 2.4c.1.2 1.7 2.7 4.2 3.7 2.1.8 2.5.7 3 .6.5-.1 1.4-.6 1.6-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.1-.4-.2z"/></svg>
                Send on WhatsApp
            </a>
        </div>

        <!-- Form -->
        <div class="form-card">
            <h3>Request a quote</h3>
            <div class="fsub">We'll get back to you with pricing for your wrap.</div>

            <div class="sel-readout">
                <span class="dot" id="readoutDot" style="background:#dc2626;"></span>
                <span><span id="readoutColour">Wiyule Red</span> — <span id="readoutFinish">Gloss</span> finish</span>
            </div>

            <form id="wrapForm" novalidate>
                <div class="field">
                    <label>Your name <span class="req">*</span></label>
                    <input type="text" name="name" id="fName" autocomplete="name" placeholder="e.g. Thoko Banda">
                </div>
                <div class="field">
                    <label>Phone number <span class="req">*</span></label>
                    <input type="tel" name="phone" id="fPhone" autocomplete="tel" placeholder="e.g. 0993 575 111">
                </div>
                <div class="field">
                    <label>Vehicle type</label>
                    <select name="vehicle_type" id="fVehicle">
                        <option value="">Select…</option>
                        <option>Sedan</option>
                        <option>SUV / 4x4</option>
                        <option>Pickup / Bakkie</option>
                        <option>Hatchback</option>
                        <option>Van / Minibus</option>
                        <option>Truck / Lorry</option>
                        <option>Motorbike</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="field">
                    <label>Notes (optional)</label>
                    <textarea name="notes" id="fNotes" placeholder="Partial wrap, logo &amp; lettering, registration number, timeline…"></textarea>
                </div>

                <!-- prefilled from the configurator -->
                <input type="hidden" name="colour_name" id="hColourName" value="Wiyule Red">
                <input type="hidden" name="colour_hex"  id="hColourHex"  value="#dc2626">
                <input type="hidden" name="finish"      id="hFinish"     value="Gloss">

                <button type="submit" class="submit-btn" id="submitBtn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                    Request my quote
                </button>
                <div class="form-msg" id="formMsg"></div>
            </form>
        </div>
    </div>

</div>

<!-- ── Footer ── -->
<footer>
    <div class="container">
        <div class="foot-grid">
            <div>
                <h4>Wiyule &amp; F.A Motors</h4>
                <p>Premium automotive services, bodywork and vehicle wrapping in Blantyre, Malawi since 2016.</p>
            </div>
            <div>
                <h4>Quick Links</h4>
                <ul style="display:flex;flex-direction:column;gap:8px;">
                    <li><a href="<?= $base ?>/">Home</a></li>
                    <li><a href="<?= $base ?>/wrapping.php">Vehicle Wrapping</a></li>
                    <li><a href="<?= $base ?>/book.php">Book a Service</a></li>
                    <li><a href="<?= $base ?>/about.php">About</a></li>
                    <li><a href="<?= $base ?>/contact.php">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul style="display:flex;flex-direction:column;gap:8px;">
                    <li>Nyambadwe, Blantyre</li>
                    <li><a href="tel:+265993575111">+265 993 575 111</a></li>
                    <li><a href="https://wa.me/265993575111" target="_blank" rel="noopener">WhatsApp us</a></li>
                </ul>
            </div>
        </div>
        <div class="foot-bottom">&copy; <?php echo date('Y'); ?> Wiyule &amp; F.A Motors. All rights reserved.</div>
    </div>
</footer>

<script>
/* ════════════════════════════════════════════════════════════════════
   Vehicle Wrapping configurator
   - Plain JS handles UI state (works with or without WebGL).
   - Three.js is lazy-imported inside boot() so nothing heavy loads up front.
   ════════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var WA_NUMBER = '265993575111';
  var ENDPOINT  = '<?= $base ?>/pages/process-wrap-quote.php';

  // Curated wrap palette. data-light flags pale swatches (for ring contrast).
  var COLOURS = [
    { name: 'Wiyule Red',      hex: '#dc2626' },
    { name: 'Deep Crimson',    hex: '#7f1d1d' },
    { name: 'Gloss Black',     hex: '#0b0f17' },
    { name: 'Pearl White',     hex: '#eef1f5', light: 1 },
    { name: 'Silver',          hex: '#c3c9d2', light: 1 },
    { name: 'Gunmetal',        hex: '#3b4049' },
    { name: 'Deep Blue',       hex: '#1e3a8a' },
    { name: 'Electric Blue',   hex: '#0ea5e9' },
    { name: 'Racing Green',    hex: '#14532d' },
    { name: 'Military Green',  hex: '#565e35' },
    { name: 'Gold',            hex: '#c9a227' },
    { name: 'Sunset Orange',   hex: '#ea580c' },
    { name: 'Bronze',          hex: '#8a6a3b' },
    { name: 'Royal Purple',    hex: '#6d28d9' }
  ];

  // Finish presets (the MeshPhysicalMaterial params live in boot()).
  var FINISH_LABELS = {
    gloss: 'Gloss', matte: 'Matte', satin: 'Satin',
    metallic: 'Metallic', chrome: 'Chrome', carbon: 'Carbon Fibre'
  };

  var state = { hex: '#dc2626', name: 'Wiyule Red', finish: 'gloss' };

  // ── DOM refs ──
  var $ = function (id) { return document.getElementById(id); };
  var swatchWrap = $('swatches');
  var finishRow  = $('finishRow');
  var canvas     = $('wrapCanvas');
  var poster     = $('poster');
  var bootBtn    = $('bootBtn');

  // ── Build swatches ──
  COLOURS.forEach(function (c, i) {
    var s = document.createElement('div');
    s.className = 'swatch' + (i === 0 ? ' active' : '');
    s.style.background = c.hex;
    if (c.light) s.setAttribute('data-light', '1');
    s.setAttribute('role', 'button');
    s.setAttribute('tabindex', '0');
    s.setAttribute('aria-label', c.name);
    s.title = c.name;
    s.addEventListener('click', function () { pickColour(c, s); });
    s.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pickColour(c, s); }
    });
    swatchWrap.appendChild(s);
  });

  function pickColour(c, el) {
    state.hex = c.hex; state.name = c.name;
    var all = swatchWrap.querySelectorAll('.swatch');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    el.classList.add('active');
    updateSelection();
  }

  // ── Finish buttons ──
  finishRow.addEventListener('click', function (e) {
    var btn = e.target.closest('.finish-btn');
    if (!btn) return;
    state.finish = btn.getAttribute('data-finish');
    var all = finishRow.querySelectorAll('.finish-btn');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('active');
    btn.classList.add('active');
    updateSelection();
  });

  // ── Central: push state into every surface (label, SVG, form, WhatsApp, 3D) ──
  var applyToScene = null; // set once 3D boots
  function finishLabel() { return FINISH_LABELS[state.finish] || state.finish; }

  function updateSelection() {
    var fl = finishLabel();
    // Stage badge
    $('badgeDot').style.background = state.hex;
    $('badgeColour').textContent = state.name;
    $('badgeFinish').textContent = fl;
    // Inline colour name
    $('colourNameInline').textContent = state.name;
    // Form readout + hidden fields
    $('readoutDot').style.background = state.hex;
    $('readoutColour').textContent = state.name;
    $('readoutFinish').textContent = fl;
    $('hColourName').value = state.name;
    $('hColourHex').value  = state.hex;
    $('hFinish').value     = fl;
    // WhatsApp summary card
    $('waColour').textContent = state.name;
    $('waFinish').textContent = fl;
    // SVG preview (poster / fallback)
    updateSvg();
    // WhatsApp link
    updateWhatsApp();
    // Live 3D
    if (applyToScene) applyToScene(state);
  }

  // ── SVG poster/fallback preview ──
  function updateSvg() {
    var body = $('svgCarBody');
    var sheen = $('svgSheen');
    if (!body) return;
    if (state.finish === 'chrome') {
      body.setAttribute('fill', mix(state.hex, '#ffffff', 0.45));
      body.style.filter = 'saturate(0.7)';
    } else if (state.finish === 'carbon') {
      body.setAttribute('fill', mix(state.hex, '#101216', 0.82));
      body.style.filter = 'none';
    } else {
      body.setAttribute('fill', state.hex);
      body.style.filter = 'none';
    }
    // Sheen strength by finish
    var sheenOpacity = { gloss: 0.9, chrome: 1, metallic: 0.65, satin: 0.4, matte: 0.12, carbon: 0.5 }[state.finish];
    if (sheen) sheen.style.opacity = sheenOpacity;
  }

  // ── WhatsApp link (reads latest form inputs + state) ──
  function updateWhatsApp() {
    var vt = ($('fVehicle') && $('fVehicle').value) ? $('fVehicle').value : 'Not specified';
    $('waVehicle').textContent = vt;
    var notes = ($('fNotes') && $('fNotes').value.trim()) ? $('fNotes').value.trim() : '—';
    var msg = "Hi Wiyule & F.A Motors, I'd like a wrap quote. "
            + "Vehicle: " + vt + ". "
            + "Colour: " + state.name + " (" + state.hex + "). "
            + "Finish: " + finishLabel() + ". "
            + "Notes: " + notes;
    $('waBtn').href = 'https://wa.me/' + WA_NUMBER + '?text=' + encodeURIComponent(msg);
  }

  // Keep WhatsApp message current as the user types / changes vehicle type
  ['fVehicle', 'fNotes'].forEach(function (id) {
    var el = $(id);
    if (el) el.addEventListener('input', updateWhatsApp);
    if (el) el.addEventListener('change', updateWhatsApp);
  });

  // ── Colour helpers ──
  function hexToRgb(h) {
    h = h.replace('#', '');
    if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    return { r: parseInt(h.substr(0, 2), 16), g: parseInt(h.substr(2, 2), 16), b: parseInt(h.substr(4, 2), 16) };
  }
  function mix(a, b, t) {
    var A = hexToRgb(a), B = hexToRgb(b);
    var r = Math.round(A.r + (B.r - A.r) * t);
    var g = Math.round(A.g + (B.g - A.g) * t);
    var bl = Math.round(A.b + (B.b - A.b) * t);
    return '#' + [r, g, bl].map(function (n) { return ('0' + n.toString(16)).slice(-2); }).join('');
  }

  /* ════════════════════════════════════════════════════════════════
     3D ENGINE — lazy loaded. Boots on scroll-into-view or button tap.
     ════════════════════════════════════════════════════════════════ */
  var booted = false, bootFailed = false;

  function webglOk() {
    try {
      var c = document.createElement('canvas');
      return !!(window.WebGLRenderingContext &&
        (c.getContext('webgl2') || c.getContext('webgl') || c.getContext('experimental-webgl')));
    } catch (e) { return false; }
  }

  function showFallback(msg) {
    bootFailed = true;
    // Keep the SVG poster as the permanent live preview; swap the CTA for a note.
    var cta = $('posterCta');
    if (cta) cta.innerHTML = '<div class="poster-hint" style="opacity:1;">' +
      (msg || 'Live 3D preview unavailable here — colour preview shown. Everything else works.') + '</div>';
  }

  function setBootLoading() {
    if (bootBtn) {
      bootBtn.disabled = true;
      bootBtn.innerHTML = '<span class="spinner"></span> Loading 3D…';
    }
  }

  async function boot() {
    if (booted || bootFailed) return;
    booted = true;

    if (!webglOk()) { showFallback(); return; }
    setBootLoading();

    var THREE, OrbitControls, RoomEnvironment, RoundedBoxGeometry;
    try {
      THREE = await import('three');
      OrbitControls      = (await import('three/addons/controls/OrbitControls.js')).OrbitControls;
      RoomEnvironment    = (await import('three/addons/environments/RoomEnvironment.js')).RoomEnvironment;
      RoundedBoxGeometry = (await import('three/addons/geometries/RoundedBoxGeometry.js')).RoundedBoxGeometry;
    } catch (err) {
      booted = false; // allow a manual retry is pointless offline; just fall back
      showFallback('Live 3D needs an internet connection to load — colour preview shown instead.');
      return;
    }

    try {
      buildScene(THREE, OrbitControls, RoomEnvironment, RoundedBoxGeometry);
    } catch (err) {
      showFallback();
    }
  }

  function buildScene(THREE, OrbitControls, RoomEnvironment, RoundedBoxGeometry) {
    var stage = $('stage');

    var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(stage.clientWidth, stage.clientHeight, false);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;

    var scene = new THREE.Scene();

    // Procedural studio reflections (no HDR download).
    var pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;

    var camera = new THREE.PerspectiveCamera(42, stage.clientWidth / stage.clientHeight, 0.1, 100);
    camera.position.set(4.9, 2.35, 5.7);

    // Lights (environment does most of the work; these add a key highlight)
    var hemi = new THREE.HemisphereLight(0xffffff, 0x3a3f4a, 0.35);
    scene.add(hemi);
    var key = new THREE.DirectionalLight(0xffffff, 1.0);
    key.position.set(5, 8, 6);
    scene.add(key);
    var rim = new THREE.DirectionalLight(0xdbe4ff, 0.4);
    rim.position.set(-6, 4, -5);
    scene.add(rim);

    // ── Materials ──
    var bodyMat = new THREE.MeshPhysicalMaterial({
      color: new THREE.Color(state.hex), metalness: 0.0, roughness: 0.12,
      clearcoat: 1.0, clearcoatRoughness: 0.03, envMapIntensity: 1.1
    });
    var glassMat = new THREE.MeshPhysicalMaterial({
      color: 0x0c1016, metalness: 0.1, roughness: 0.08,
      clearcoat: 1.0, clearcoatRoughness: 0.08, envMapIntensity: 1.4,
      transparent: true, opacity: 0.86
    });
    var tyreMat = new THREE.MeshStandardMaterial({ color: 0x0b0c0f, roughness: 0.9, metalness: 0.0 });
    var rimMat  = new THREE.MeshStandardMaterial({ color: 0xd2d7de, metalness: 1.0, roughness: 0.22, envMapIntensity: 1.2 });
    var hubMat  = new THREE.MeshStandardMaterial({ color: 0x4b5159, metalness: 0.9, roughness: 0.4 });
    var headMat = new THREE.MeshStandardMaterial({ color: 0xffffff, emissive: 0xfff2cc, emissiveIntensity: 0.55, roughness: 0.25, metalness: 0.1 });
    var tailMat = new THREE.MeshPhysicalMaterial({ color: 0x9a1419, emissive: 0x5a0000, emissiveIntensity: 0.35, roughness: 0.3, clearcoat: 1.0, transparent: true, opacity: 0.92 });
    var trimMat = new THREE.MeshStandardMaterial({ color: 0x15171c, metalness: 0.6, roughness: 0.4 });

    var carbonTex = null; // generated on demand

    // ── Car group ──
    var car = new THREE.Group();

    // Lower body
    var lower = new THREE.Mesh(new RoundedBoxGeometry(2.05, 0.82, 4.5, 6, 0.26), bodyMat);
    lower.position.y = 0.92;
    car.add(lower);

    // Bonnet/boot taper strip (subtle shoulder) — a thinner body-coloured box on top of lower
    var shoulder = new THREE.Mesh(new RoundedBoxGeometry(1.92, 0.34, 4.1, 5, 0.22), bodyMat);
    shoulder.position.y = 1.26;
    car.add(shoulder);

    // Cabin (painted roof + pillars)
    var cabin = new THREE.Mesh(new RoundedBoxGeometry(1.75, 0.66, 2.1, 5, 0.22), bodyMat);
    cabin.position.set(0, 1.56, -0.12);
    car.add(cabin);

    // Glass greenhouse (inset so painted pillars show)
    var glass = new THREE.Mesh(new RoundedBoxGeometry(1.56, 0.5, 1.78, 5, 0.16), glassMat);
    glass.position.set(0, 1.56, -0.12);
    car.add(glass);

    // Headlights
    [-0.72, 0.72].forEach(function (x) {
      var h = new THREE.Mesh(new RoundedBoxGeometry(0.34, 0.18, 0.1, 3, 0.05), headMat);
      h.position.set(x, 0.98, 2.24);
      car.add(h);
    });
    // Taillights
    [-0.74, 0.74].forEach(function (x) {
      var t = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.16, 0.09, 3, 0.05), tailMat);
      t.position.set(x, 1.02, -2.24);
      car.add(t);
    });
    // Front + rear bumper trim
    [2.26, -2.26].forEach(function (z) {
      var b = new THREE.Mesh(new RoundedBoxGeometry(1.86, 0.26, 0.14, 3, 0.07), trimMat);
      b.position.set(0, 0.62, z);
      car.add(b);
    });

    // Wheels
    var tyreGeo = new THREE.CylinderGeometry(0.52, 0.52, 0.34, 30);
    var rimGeo  = new THREE.CylinderGeometry(0.33, 0.33, 0.36, 24);
    var hubGeo  = new THREE.CylinderGeometry(0.12, 0.12, 0.38, 14);
    var spokeGeo = new RoundedBoxGeometry(0.075, 0.5, 0.14, 2, 0.03);
    var wheelPos = [[1.03, 1.45], [-1.03, 1.45], [1.03, -1.45], [-1.03, -1.45]];
    wheelPos.forEach(function (p) {
      var w = new THREE.Group();
      var tyre = new THREE.Mesh(tyreGeo, tyreMat); tyre.rotation.z = Math.PI / 2; w.add(tyre);
      var r = new THREE.Mesh(rimGeo, rimMat);      r.rotation.z = Math.PI / 2;    w.add(r);
      var hub = new THREE.Mesh(hubGeo, hubMat);    hub.rotation.z = Math.PI / 2;  w.add(hub);
      for (var k = 0; k < 5; k++) {
        var sp = new THREE.Mesh(spokeGeo, rimMat);
        sp.rotation.z = Math.PI / 2;
        sp.rotation.x = (k / 5) * Math.PI * 2;
        w.add(sp);
      }
      w.position.set(p[0], 0.52, p[1]);
      car.add(w);
    });

    scene.add(car);

    // ── Contact shadow (baked radial texture on a plane) ──
    var shTex = makeShadowTexture(THREE);
    var shadow = new THREE.Mesh(
      new THREE.PlaneGeometry(1, 1),
      new THREE.MeshBasicMaterial({ map: shTex, transparent: true, depthWrite: false })
    );
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.y = 0.012;
    shadow.scale.set(5.8, 3.6, 1);
    scene.add(shadow);

    // ── Controls ──
    var controls = new OrbitControls(camera, renderer.domElement);
    controls.target.set(0, 0.85, 0);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = false;
    controls.minDistance = 4.2;
    controls.maxDistance = 11;
    controls.minPolarAngle = 0.18 * Math.PI;
    controls.maxPolarAngle = 0.49 * Math.PI; // never below the floor
    controls.autoRotate = true;
    controls.autoRotateSpeed = 0.75;
    controls.update();

    // ── Finish application ──
    function applyFinish(f) {
      // reset shared params
      bodyMat.map = null;
      bodyMat.roughnessMap = null;
      bodyMat.metalnessMap = null;
      bodyMat.normalMap = null;

      if (f === 'gloss')    { bodyMat.roughness = 0.12; bodyMat.metalness = 0.0; bodyMat.clearcoat = 1.0; bodyMat.clearcoatRoughness = 0.03; }
      else if (f === 'matte')    { bodyMat.roughness = 0.85; bodyMat.metalness = 0.0; bodyMat.clearcoat = 0.0; }
      else if (f === 'satin')    { bodyMat.roughness = 0.45; bodyMat.metalness = 0.0; bodyMat.clearcoat = 0.4; bodyMat.clearcoatRoughness = 0.25; }
      else if (f === 'metallic') { bodyMat.roughness = 0.3;  bodyMat.metalness = 0.9; bodyMat.clearcoat = 0.3; bodyMat.clearcoatRoughness = 0.1; }
      else if (f === 'chrome')   { bodyMat.roughness = 0.05; bodyMat.metalness = 1.0; bodyMat.clearcoat = 0.0; }
      else if (f === 'carbon')   {
        if (!carbonTex) carbonTex = makeCarbonTexture(THREE);
        bodyMat.map = carbonTex;
        bodyMat.roughness = 0.5; bodyMat.metalness = 0.25; bodyMat.clearcoat = 0.6; bodyMat.clearcoatRoughness = 0.15;
      }
      bodyMat.needsUpdate = true;
    }

    function applyColour(f, hex) {
      if (f === 'carbon') {
        bodyMat.color.set(mix(hex, '#101216', 0.8)); // keep weave dark, hint the tint
      } else {
        bodyMat.color.set(hex); // chrome: metal tints its reflection → reads as tinted mirror
      }
    }

    // Public hook used by updateSelection()
    applyToScene = function (st) {
      applyFinish(st.finish);
      applyColour(st.finish, st.hex);
      dirty = true;
    };
    // sync current state immediately
    applyToScene(state);

    // ── Render loop (on-demand) ──
    var running = false, rafId = null, dirty = true, inView = true, visible = true;

    function frame() {
      if (!running) return;
      rafId = requestAnimationFrame(frame);
      var moved = controls.update(); // true while damping/auto-rotating
      if (moved || dirty) { renderer.render(scene, camera); dirty = false; }
    }
    function start() { if (!running && inView && visible) { running = true; dirty = true; frame(); } }
    function stop()  { running = false; if (rafId) cancelAnimationFrame(rafId); rafId = null; }

    // Pause auto-rotate on interaction, resume after idle
    var idleTimer = null;
    controls.addEventListener('start', function () {
      controls.autoRotate = false;
      if (idleTimer) clearTimeout(idleTimer);
      $('stageHint').classList.remove('show');
    });
    controls.addEventListener('end', function () {
      if (idleTimer) clearTimeout(idleTimer);
      idleTimer = setTimeout(function () { controls.autoRotate = true; dirty = true; start(); }, 3500);
    });
    controls.addEventListener('change', function () { dirty = true; });

    // Resize
    var ro = new ResizeObserver(function () {
      var w = stage.clientWidth, h = stage.clientHeight;
      if (w === 0 || h === 0) return;
      camera.aspect = w / h; camera.updateProjectionMatrix();
      renderer.setSize(w, h, false);
      dirty = true; start();
    });
    ro.observe(stage);

    // Pause when tab hidden
    document.addEventListener('visibilitychange', function () {
      visible = !document.hidden;
      if (visible) start(); else stop();
    });

    // Pause when scrolled out of view
    var vis = new IntersectionObserver(function (es) {
      inView = es[0].isIntersecting;
      if (inView) start(); else stop();
    }, { threshold: 0.01 });
    vis.observe(stage);

    // Reveal canvas, hide poster, show interaction hint briefly
    canvas.classList.add('live');
    poster.classList.add('hidden');
    var sh = $('stageHint'); sh.classList.add('show');
    setTimeout(function () { sh.classList.remove('show'); }, 2600);

    start();
  }

  // ── Procedural textures (no downloads) ──
  function makeShadowTexture(THREE) {
    var c = document.createElement('canvas'); c.width = c.height = 256;
    var x = c.getContext('2d');
    var g = x.createRadialGradient(128, 128, 8, 128, 128, 122);
    g.addColorStop(0, 'rgba(0,0,0,0.40)');
    g.addColorStop(0.55, 'rgba(0,0,0,0.16)');
    g.addColorStop(1, 'rgba(0,0,0,0)');
    x.fillStyle = g; x.fillRect(0, 0, 256, 256);
    var t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; return t;
  }
  function makeCarbonTexture(THREE) {
    var c = document.createElement('canvas'); c.width = c.height = 128;
    var x = c.getContext('2d');
    x.fillStyle = '#15171c'; x.fillRect(0, 0, 128, 128);
    var cell = 16;
    for (var gy = 0; gy < 128; gy += cell) {
      for (var gx = 0; gx < 128; gx += cell) {
        var dark = (((gx / cell) + (gy / cell)) % 2) === 0;
        var g = x.createLinearGradient(gx, gy, gx + cell, gy + cell);
        if (dark) { g.addColorStop(0, '#2b2e35'); g.addColorStop(1, '#0f1115'); }
        else      { g.addColorStop(0, '#0f1115'); g.addColorStop(1, '#2b2e35'); }
        x.fillStyle = g; x.fillRect(gx, gy, cell, cell);
      }
    }
    var t = new THREE.CanvasTexture(c);
    t.wrapS = t.wrapT = THREE.RepeatWrapping;
    t.repeat.set(5, 3);
    t.colorSpace = THREE.SRGBColorSpace;
    return t;
  }

  // ── Boot triggers: button tap + scroll-into-view (once) ──
  if (bootBtn) bootBtn.addEventListener('click', boot);
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      if (es[0].isIntersecting) { io.disconnect(); boot(); }
    }, { rootMargin: '120px' });
    io.observe($('configurator'));
  } else {
    // very old browser: leave the tap button as the entry point
  }

  /* ════════════════════════════════════════════════════════════════
     QUOTE FORM — fetch submit, inline success/error (no reload)
     ════════════════════════════════════════════════════════════════ */
  var form = $('wrapForm');
  var msg  = $('formMsg');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    msg.className = 'form-msg';

    var name  = $('fName').value.trim();
    var phone = $('fPhone').value.trim();
    if (!name || !phone) {
      msg.textContent = !phone ? 'Please enter a phone number so we can reach you.' : 'Please enter your name.';
      msg.className = 'form-msg err show';
      ($('fPhone').value.trim() ? $('fName') : $('fPhone')).focus();
      return;
    }

    var btn = $('submitBtn');
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Sending…';

    // URL-encoded body so the PHP endpoint reads $_POST (server responds JSON).
    var body = new URLSearchParams({
      name: name, phone: phone,
      vehicle_type: $('fVehicle').value || '',
      notes: $('fNotes').value.trim(),
      colour_name: state.name, colour_hex: state.hex, finish: finishLabel()
    });

    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
      body: body.toString()
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data && data.success) {
        msg.innerHTML = "Thank you, " + escapeHtml(name) + "! We've got your " + escapeHtml(state.name) +
          " / " + escapeHtml(finishLabel()) + " wrap request and will be in touch shortly.";
        msg.className = 'form-msg ok show';
        form.reset();
        updateWhatsApp();
      } else {
        var err = (data && data.errors && data.errors.length) ? data.errors.join(' ')
                 : 'Something went wrong. Please WhatsApp us or call +265 993 575 111.';
        msg.textContent = err;
        msg.className = 'form-msg err show';
      }
    })
    .catch(function () {
      msg.textContent = 'Network problem — please WhatsApp us or call +265 993 575 111.';
      msg.className = 'form-msg err show';
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = original;
    });
  });

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
    });
  }

  // ── Init ──
  updateSelection();
})();
</script>

</body>
</html>
