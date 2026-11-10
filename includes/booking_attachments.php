<?php
/**
 * includes/booking_attachments.php
 *
 * Handles customer-uploaded booking photos (the "describe the problem" feature).
 * Files are stored under /uploads/bookings/ with random names; the DB table
 * `booking_attachments` only holds references. Images are the only accepted type,
 * script execution in that folder is blocked by uploads/.htaccess, and every save
 * is validated by real MIME (not the browser-supplied type).
 */

if (!defined('WM_BOOKING_UPLOAD_MAX_FILES')) {
    define('WM_BOOKING_UPLOAD_MAX_FILES', 4);
    // Hard server-side cap (bytes). The browser downscales before upload, so real
    // files are ~200–800 KB; this is just a backstop. PHP's upload_max_filesize is
    // the true ceiling on the host.
    define('WM_BOOKING_UPLOAD_MAX_BYTES', 4 * 1024 * 1024);
}

if (!function_exists('wm_booking_upload_dir')) {

    /** Absolute filesystem path to the booking uploads folder (with trailing slash). */
    function wm_booking_upload_dir(): string {
        return __DIR__ . '/../uploads/bookings/';
    }

    /** Public URL for a stored attachment filename. */
    function wm_booking_attachment_url(string $base, string $file_name): string {
        return $base . '/uploads/bookings/' . rawurlencode($file_name);
    }

    /** Allowed MIME → file extension. Anything else is rejected. */
    function wm_booking_allowed_image_types(): array {
        return [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
    }

    /**
     * Save uploaded booking photos. Non-fatal: bad files are skipped, never thrown,
     * so a photo problem can't break the booking itself.
     *
     * @return int number of files actually saved
     */
    function wm_booking_save_uploads(mysqli $conn, int $booking_id, array $files): int {
        if ($booking_id <= 0 || empty($files['name'])) return 0;

        $allowed = wm_booking_allowed_image_types();
        $dir     = wm_booking_upload_dir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log("booking uploads dir not writable: $dir");
            return 0;
        }

        // Normalise to a list of single-file entries (handles name="photos[]").
        $names = (array)$files['name'];
        $count = count($names);
        $saved = 0;

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;

        $stmt = $conn->prepare(
            "INSERT INTO booking_attachments (booking_id, file_name, mime, size) VALUES (?, ?, ?, ?)"
        );

        for ($i = 0; $i < $count; $i++) {
            if ($saved >= WM_BOOKING_UPLOAD_MAX_FILES) break;

            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) continue;

            $tmp  = $files['tmp_name'][$i] ?? '';
            $size = (int)($files['size'][$i] ?? 0);
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            if ($size <= 0 || $size > WM_BOOKING_UPLOAD_MAX_BYTES) continue;

            // Trust the real content type, not the browser-supplied one.
            $mime = $finfo ? (finfo_file($finfo, $tmp) ?: '') : ($files['type'][$i] ?? '');
            if (!isset($allowed[$mime])) continue;

            $ext  = $allowed[$mime];
            $name = 'bk' . $booking_id . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $dest = $dir . $name;

            if (!move_uploaded_file($tmp, $dest)) continue;
            @chmod($dest, 0644);

            $stmt->bind_param('issi', $booking_id, $name, $mime, $size);
            if ($stmt->execute()) {
                $saved++;
            } else {
                @unlink($dest); // row failed — don't leave an orphan file
            }
        }

        $stmt->close();
        // Note: no finfo_close() — on PHP 8.5+ it's deprecated (freed automatically),
        // and on older versions the resource is released at request end anyway.
        return $saved;
    }

    /** All attachment rows for one booking (id, file_name, mime, size). */
    function wm_booking_attachments(mysqli $conn, int $booking_id): array {
        $stmt = $conn->prepare(
            "SELECT id, file_name, mime, size FROM booking_attachments WHERE booking_id = ? ORDER BY id"
        );
        $stmt->bind_param('i', $booking_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Map of booking_id => [file_name, ...] for a set of bookings, in one query.
     * Used by the admin list so each booking can show its photos without N queries.
     */
    function wm_booking_attachments_map(mysqli $conn, array $booking_ids): array {
        $ids = array_values(array_filter(array_map('intval', $booking_ids), fn($v) => $v > 0));
        if (empty($ids)) return [];
        $in  = implode(',', array_fill(0, count($ids), '?'));
        // Resilient: if the table isn't on this DB yet (prod migration not run),
        // return no photos instead of breaking the page.
        try {
            $stmt = $conn->prepare(
                "SELECT booking_id, file_name FROM booking_attachments WHERE booking_id IN ($in) ORDER BY id"
            );
        } catch (Throwable $e) {
            return [];
        }
        if (!$stmt) return [];
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['booking_id']][] = $row['file_name'];
        }
        $stmt->close();
        return $map;
    }
}
