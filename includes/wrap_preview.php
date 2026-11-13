<?php
/**
 * includes/wrap_preview.php
 *
 * "See it on YOUR car" — AI wrap preview via fal.ai (FLUX.1 Kontext, an
 * identity-preserving image-edit model). The customer uploads a photo of their
 * actual car; we ask the model to recolour ONLY the paint to the chosen wrap
 * colour + finish and leave everything else (shape, angle, plates, background)
 * untouched.
 *
 * Design mirrors includes/email.php: the API key lives in secrets.php, calls are
 * server-side curl, and local dev (php -S) never hits the paid API — it returns a
 * mock so the whole upload→submit→poll→display flow is testable for free.
 *
 * fal queue REST (submit-then-poll):
 *   submit : POST  {BASE}                         -> { request_id, ... }
 *   status : GET   {BASE}/requests/{id}/status    -> { status: IN_QUEUE|IN_PROGRESS|COMPLETED }
 *   result : GET   {BASE}/requests/{id}           -> { images: [ { url } ], ... }
 * Auth header on every call: "Authorization: Key <FAL_KEY>".
 */

if (!defined('WM_WRAP_FAL_BASE')) {
    define('WM_WRAP_FAL_BASE', 'https://queue.fal.run/fal-ai/flux-pro/kontext');

    // Cost guards (this is a public endpoint calling a paid API — bound the spend).
    define('WM_WRAP_MAX_PER_SESSION', 8);   // previews per browser session
    define('WM_WRAP_MAX_PER_IP_DAY',  40);  // previews per IP per day (file counter)
    define('WM_WRAP_MAX_UPLOAD_BYTES', 3 * 1024 * 1024); // 3 MB hard cap on the upload
}

if (!function_exists('wm_wrap_preview_enabled')) {

    /** True only when a real fal key is configured. */
    function wm_wrap_preview_enabled(): bool {
        return defined('FAL_KEY') && FAL_KEY !== '' && FAL_KEY !== 'PASTE_YOUR_FAL_KEY_HERE';
    }

    /** True under the PHP built-in dev server (php -S) — never call the paid API there. */
    function wm_wrap_preview_is_dev(): bool {
        return php_sapi_name() === 'cli-server';
    }

    /** Human finish description fed into the edit prompt. */
    function wm_wrap_preview_finish_desc(string $finish): string {
        switch (strtolower($finish)) {
            case 'matte':    return 'flat matte';
            case 'satin':    return 'satin';
            case 'metallic': return 'metallic';
            case 'chrome':   return 'mirror chrome';
            case 'carbon':   return 'carbon-fibre textured';
            case 'gloss':
            default:         return 'glossy';
        }
    }

    /**
     * Build the image-edit instruction. Inputs are already sanitised by the caller
     * (colour name stripped to a safe charset, hex validated, finish whitelisted).
     */
    function wm_wrap_preview_prompt(string $colour_name, string $hex, string $finish): string {
        $fd = wm_wrap_preview_finish_desc($finish);
        $name = $colour_name !== '' ? $colour_name : 'custom';
        return "Change ONLY the body paint of this vehicle to a {$fd} {$name} vinyl wrap "
             . "(colour {$hex}). Keep the exact same vehicle, body shape, panels, wheels, "
             . "tyres, windows, badges, number plate, headlights, reflections, background, "
             . "lighting and camera angle — change nothing except the paint colour and its "
             . "finish. Photorealistic, high detail, no text, no watermark.";
    }

    // ── Rate limiting ────────────────────────────────────────────────────────
    /** Per-IP/day file counter path. */
    function wm_wrap_preview_counter_file(): string {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = hash('sha256', $ip . '|' . date('Y-m-d'));
        return sys_get_temp_dir() . '/wm_wrap_' . $key . '.cnt';
    }

    /** Check (don't increment) whether another preview is allowed. */
    function wm_wrap_preview_rate_ok(): bool {
        if (!isset($_SESSION)) { @session_start(); }
        $sess = (int)($_SESSION['wm_wrap_count'] ?? 0);
        if ($sess >= WM_WRAP_MAX_PER_SESSION) return false;

        $f = wm_wrap_preview_counter_file();
        $ip = is_file($f) ? (int)@file_get_contents($f) : 0;
        return $ip < WM_WRAP_MAX_PER_IP_DAY;
    }

    /** Record one successful submission against both counters. */
    function wm_wrap_preview_rate_hit(): void {
        if (!isset($_SESSION)) { @session_start(); }
        $_SESSION['wm_wrap_count'] = (int)($_SESSION['wm_wrap_count'] ?? 0) + 1;

        $f = wm_wrap_preview_counter_file();
        $ip = is_file($f) ? (int)@file_get_contents($f) : 0;
        @file_put_contents($f, (string)($ip + 1), LOCK_EX);
    }

    // ── fal calls ────────────────────────────────────────────────────────────
    /**
     * Submit an edit job. $data_uri is a base64 "data:image/...;base64,..." string.
     * Returns ['ok'=>bool, 'request_id'=>string, 'dev'=>bool, 'error'=>string].
     */
    function wm_wrap_preview_submit(string $data_uri, string $prompt): array {
        // Local dev: skip the paid API; hand back a mock request id the client
        // renders as a tinted stand-in.
        if (wm_wrap_preview_is_dev()) {
            return ['ok' => true, 'request_id' => 'dev-' . bin2hex(random_bytes(6)), 'dev' => true];
        }
        if (!wm_wrap_preview_enabled()) {
            return ['ok' => false, 'error' => 'Preview is not configured yet.'];
        }

        $payload = [
            'prompt'          => $prompt,
            'image_url'       => $data_uri,
            'guidance_scale'  => 3.5,
            'num_images'      => 1,
            'output_format'   => 'jpeg',
            'safety_tolerance'=> '2',
        ];

        $ch = curl_init(WM_WRAP_FAL_BASE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'authorization: Key ' . FAL_KEY,
            ],
            CURLOPT_TIMEOUT        => 25,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            error_log("wrap-preview submit failed: HTTP $code err=$err body=" . substr((string)$res, 0, 300));
            return ['ok' => false, 'error' => 'The preview service is busy. Please try again.'];
        }
        $data = json_decode((string)$res, true);
        $rid  = $data['request_id'] ?? '';
        if ($rid === '') return ['ok' => false, 'error' => 'Unexpected response from preview service.'];
        return ['ok' => true, 'request_id' => $rid, 'dev' => false];
    }

    /**
     * Poll a job. Returns:
     *   ['ok'=>true, 'status'=>'IN_PROGRESS']                        (still working)
     *   ['ok'=>true, 'status'=>'COMPLETED', 'image_url'=>'https..']  (done)
     *   ['ok'=>false, 'error'=>'...']                                (failed)
     */
    function wm_wrap_preview_status(string $request_id): array {
        if ($request_id === '' || !preg_match('/^[A-Za-z0-9\-]{1,80}$/', $request_id)) {
            return ['ok' => false, 'error' => 'Invalid request.'];
        }

        // Dev mock: report completion immediately; client renders the tinted stand-in.
        if (wm_wrap_preview_is_dev() || strncmp($request_id, 'dev-', 4) === 0) {
            return ['ok' => true, 'status' => 'COMPLETED', 'image_url' => '', 'dev' => true];
        }
        if (!wm_wrap_preview_enabled()) {
            return ['ok' => false, 'error' => 'Preview is not configured yet.'];
        }

        $base = WM_WRAP_FAL_BASE . '/requests/' . rawurlencode($request_id);

        $status = wm_wrap_preview_get_json($base . '/status');
        if (!$status['ok']) return ['ok' => false, 'error' => 'Could not check preview status.'];
        $st = strtoupper($status['data']['status'] ?? '');

        if ($st === 'COMPLETED') {
            $result = wm_wrap_preview_get_json($base);
            if (!$result['ok']) return ['ok' => false, 'error' => 'Could not fetch the finished preview.'];
            $url = $result['data']['images'][0]['url'] ?? '';
            if ($url === '') return ['ok' => false, 'error' => 'The preview finished but returned no image.'];
            return ['ok' => true, 'status' => 'COMPLETED', 'image_url' => $url, 'dev' => false];
        }
        if ($st === '' || $st === 'FAILED' || $st === 'ERROR') {
            return ['ok' => false, 'error' => 'The preview could not be generated. Try another photo.'];
        }
        return ['ok' => true, 'status' => 'IN_PROGRESS'];
    }

    /** GET helper that returns ['ok'=>bool, 'data'=>array]. */
    function wm_wrap_preview_get_json(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'authorization: Key ' . FAL_KEY,
            ],
            CURLOPT_TIMEOUT        => 25,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            error_log("wrap-preview poll failed: HTTP $code err=$err url=$url");
            return ['ok' => false, 'data' => []];
        }
        return ['ok' => true, 'data' => json_decode((string)$res, true) ?: []];
    }
}
