<?php
// ========================================================
// StudentHub - Secure Logout Endpoint
// ========================================================
require_once __DIR__ . '/auth_helper.php';

initSecureSession();

// Remove Remember-Me persistent tokens from database & browser
$userId = $_SESSION['student_id'] ?? null;
clearRememberMeToken($userId);

// Terminate PHP session and clear session cookie
destroySession();

// Respond according to request type (JSON or Redirect)
$isJson = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          (!empty($_GET['format']) && $_GET['format'] === 'json');

if ($isJson) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "success" => true,
        "message" => "You have been logged out successfully.",
        "redirect" => "login.html"
    ]);
    exit;
}

header("Location: ../pages/login.html?logout=success");
exit;
