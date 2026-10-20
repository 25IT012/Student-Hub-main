<?php
// ========================================================
// StudentHub - Auth Status Check & Session Verification API
// ========================================================
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . '/auth_helper.php';

initSecureSession();

// Check if user session expired due to timeout
if (!empty($_SESSION['timeout_expired'])) {
    unset($_SESSION['timeout_expired']);
    http_response_code(401);
    echo json_encode([
        "authenticated" => false,
        "reason" => "timeout",
        "message" => "Your session has expired due to 15 minutes of inactivity. Please sign in again.",
        "redirect" => "login.html?reason=timeout"
    ]);
    exit;
}

$user = getAuthUser();

if ($user) {
    echo json_encode([
        "authenticated" => true,
        "user" => $user
    ]);
} else {
    $reason = !empty($GLOBALS['SH_AUTH_TIMEOUT']) ? 'timeout' : 'unauthorized';
    http_response_code(401);
    echo json_encode([
        "authenticated" => false,
        "reason" => $reason,
        "message" => ($reason === 'timeout') ? "Your session has expired due to 15 minutes of inactivity. Please sign in again." : "No active session found. Please log in.",
        "redirect" => "login.html?reason=" . $reason
    ]);
}
exit;
