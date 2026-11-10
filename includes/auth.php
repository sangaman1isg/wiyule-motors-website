<?php
/**
 * includes/auth.php — shared helpers for completing a customer login.
 *
 * Extracted so pages/login.php (password step) and pages/login-verify.php
 * (2FA code step) establish the exact same session state.
 */

if (!function_exists('wm_establish_user_session')) {

    /**
     * Fully log a verified user in: regenerate the session, populate all the
     * session keys the dashboard expects, load their most-recent vehicle, and
     * set the optional remember-me cookie. Call only AFTER identity is proven
     * (password + 2FA code).
     *
     * @param array $user rows: id, full_name, email, phone, created_at
     */
    function wm_establish_user_session(mysqli $conn, array $user, bool $remember = false): void {
        session_regenerate_id(true);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['email']        = $user['email'];
        $_SESSION['user_email']   = $user['email'];
        $_SESSION['user_name']    = $user['full_name'];
        $_SESSION['phone']        = $user['phone'] ?? '';
        $_SESSION['member_since'] = $user['created_at'] ?? date('Y-m-d');

        $name_parts             = explode(' ', $user['full_name'], 2);
        $_SESSION['first_name'] = $name_parts[0];
        $_SESSION['last_name']  = $name_parts[1] ?? '';

        // Load most-recent vehicle for the session (dashboard pulls the full list from DB).
        $vstmt = $conn->prepare("SELECT make, model, year, registration, color, mileage, engine, transmission FROM vehicles WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $vstmt->bind_param("i", $user['id']);
        $vstmt->execute();
        $vresult = $vstmt->get_result();
        if ($vresult->num_rows >= 1) {
            $v = $vresult->fetch_assoc();
            $_SESSION['vehicle_make']        = $v['make'];
            $_SESSION['vehicle_model']       = $v['model'];
            $_SESSION['vehicle_year']        = $v['year'];
            $_SESSION['vehicle_reg']         = $v['registration'];
            $_SESSION['vehicle_color']       = $v['color'];
            $_SESSION['vehicle_mileage']     = (int)$v['mileage'];
            $_SESSION['vehicle_engine']      = $v['engine'];
            $_SESSION['vehicle_transmission'] = $v['transmission'];
        }
        $vstmt->close();

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        }
    }

    /**
     * Generate a fresh 6-digit sign-in code, store its hash on the user with a
     * 10-minute expiry, and email it. Returns the send result (truthy on success).
     */
    function wm_issue_login_code(mysqli $conn, int $user_id, string $email, string $first_name) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $exp  = date('Y-m-d H:i:s', time() + 600);   // 10 minutes
        $stmt = $conn->prepare("UPDATE users SET verification_code = ?, verification_expires_at = ?, last_verification_sent = NOW() WHERE id = ?");
        $stmt->bind_param('ssi', $hash, $exp, $user_id);
        $stmt->execute();
        $stmt->close();

        require_once __DIR__ . '/email.php';
        return wm_send_login_code_email($email, $first_name, $code);
    }
}
