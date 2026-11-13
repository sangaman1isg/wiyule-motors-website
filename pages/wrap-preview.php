<?php
/**
 * pages/wrap-preview.php
 *
 * JSON endpoint for the "see it on your car" AI wrap preview.
 *   POST (multipart): photo + colour_name + colour_hex + finish + consent
 *        -> { success:true, request_id, dev }
 *   GET  ?request_id=... (poll)
 *        -> { success:true, status:'IN_PROGRESS' }
 *           { success:true, status:'COMPLETED', image_url, dev }
 * Errors: { success:false, error:'...' } with an appropriate HTTP status.
 *
 * The customer's photo is sent to our AI preview provider (fal.ai) to generate
 * the image; the UI collects explicit consent before this endpoint is ever hit.
 * We don't persist the photo — it's passed through to the provider only.
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';       // pulls secrets.php (FAL_KEY)
require_once __DIR__ . '/../includes/wrap_preview.php';

function wp_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Poll: GET ?request_id=... ────────────────────────────────────────────────
if ($method === 'GET') {
    $rid = trim($_GET['request_id'] ?? '');
    if ($rid === '') wp_fail('Missing request id.');
    $st = wm_wrap_preview_status($rid);
    if (!$st['ok']) wp_fail($st['error'] ?? 'Preview failed.', 502);
    echo json_encode([
        'success'   => true,
        'status'    => $st['status'],
        'image_url' => $st['image_url'] ?? '',
        'dev'       => $st['dev'] ?? false,
    ]);
    exit;
}

if ($method !== 'POST') wp_fail('Method not allowed.', 405);

// ── Submit: POST (multipart) ─────────────────────────────────────────────────

// Feature must be switched on (real key) OR running under local dev (mock).
if (!wm_wrap_preview_enabled() && !wm_wrap_preview_is_dev()) {
    wp_fail('The live preview isn\'t switched on yet. Please use the 3D designer or request a quote.', 503);
}

// Explicit consent is required before we send the photo anywhere.
if (empty($_POST['consent'])) {
    wp_fail('Please tick the box to allow your photo to be used for the preview.');
}

// Cost guard.
if (!wm_wrap_preview_rate_ok()) {
    wp_fail('You\'ve reached today\'s preview limit. Please request a quote or try again later.', 429);
}

// Validate the uploaded photo (trust real MIME, not the browser-supplied type).
if (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    wp_fail('Please choose a photo of your car.');
}
$tmp  = $_FILES['photo']['tmp_name'] ?? '';
$size = (int)($_FILES['photo']['size'] ?? 0);
if ($tmp === '' || !is_uploaded_file($tmp))                 wp_fail('Upload failed. Please try again.');
if ($size <= 0 || $size > WM_WRAP_MAX_UPLOAD_BYTES)         wp_fail('That image is too large. Please use a photo under 3 MB.');

$allowed = ['image/jpeg' => 1, 'image/png' => 1, 'image/webp' => 1];
$mime    = function_exists('finfo_open')
    ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) ?: '')
    : ($_FILES['photo']['type'] ?? '');
if (!isset($allowed[$mime])) wp_fail('Please upload a JPG, PNG or WEBP image.');

// Sanitise the wrap selection (these feed the edit prompt).
$colour_name = substr(preg_replace('/[^A-Za-z0-9 \-\/]/', '', (string)($_POST['colour_name'] ?? '')), 0, 40);
$hex_raw     = (string)($_POST['colour_hex'] ?? '');
$hex         = preg_match('/^#?[0-9A-Fa-f]{3,8}$/', $hex_raw) ? ('#' . ltrim($hex_raw, '#')) : '#777777';
$finish_in   = strtolower((string)($_POST['finish'] ?? 'gloss'));
$finish      = in_array($finish_in, ['gloss','matte','satin','metallic','chrome','carbon'], true) ? $finish_in : 'gloss';

// Build a data URI and hand off to the model.
$bytes    = file_get_contents($tmp);
$data_uri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
$prompt   = wm_wrap_preview_prompt($colour_name, $hex, $finish);

$sub = wm_wrap_preview_submit($data_uri, $prompt);
if (!$sub['ok']) wp_fail($sub['error'] ?? 'Could not start the preview.', 502);

wm_wrap_preview_rate_hit();

echo json_encode([
    'success'    => true,
    'request_id' => $sub['request_id'],
    'dev'        => $sub['dev'] ?? false,
]);
