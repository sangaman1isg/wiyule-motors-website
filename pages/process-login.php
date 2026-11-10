<?php
// ── process-login.php ─────────────────────────────────────────
// DEPRECATED. Login is handled entirely by login.php, which now enforces
// email 2FA. This file used to log users in directly (no 2FA) — that would be
// a bypass — so it now just forwards to the real sign-in page. Do not restore
// direct-login logic here.
$base = ($_SERVER['HTTP_HOST'] === 'localhost') ? '/wiyule-motors-website' : '';
header('Location: ' . $base . '/pages/login.php');
exit;
