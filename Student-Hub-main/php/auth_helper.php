<?php
/**
 * =====================================================================
 * StudentHub - Authentication, Session & Access Control Helper
 * =====================================================================
 * Features:
 *  - Secure Session Configuration (HttpOnly, SameSite, Lifetime)
 *  - Session Fixation Protection (session_regenerate_id)
 *  - Inactivity Session Timeout (15 minutes idle expiration)
 *  - Role-Based Access Control (Student vs Admin restriction)
 *  - Cryptographic "Remember-Me" Token System (OWASP Selector + Validator)
 */

if (defined('AUTH_HELPER_LOADED')) return;
define('AUTH_HELPER_LOADED', true);

require_once __DIR__ . '/db.php';

// Inactivity timeout: 15 minutes (900 seconds)
define('SESSION_TIMEOUT_SECONDS', 900);

// Remember-Me cookie name & duration (30 days)
define('REMEMBER_COOKIE_NAME', 'studenthub_remember');
define('REMEMBER_COOKIE_DAYS', 30);

/**
 * Initializes secure PHP session parameters and starts session
 */
function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce secure cookie settings
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        
        session_set_cookie_params([
            'lifetime' => 0, // Until browser closes
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true, // Guard against XSS
            'samesite' => 'Lax' // Guard against CSRF
        ]);

        session_name('STUDENTHUB_SESSID');
        session_start();
    }

    // Check for idle session timeout and remember-me auto-login
    processSessionLifecycle();
}

/**
 * Enforces idle session timeout and auto-login via Remember-Me token
 */
function processSessionLifecycle() {
    $now = time();

    // 1. If currently logged in, check for inactivity timeout
    if (isset($_SESSION['student_id'])) {
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
            // Inactivity duration exceeded -> Expire session
            $expiredUserId = $_SESSION['student_id'];
            clearRememberMeToken($expiredUserId);
            destroySession();
            $GLOBALS['SH_AUTH_TIMEOUT'] = true;
            return;
        }
        // Update last activity timestamp on current interaction
        $_SESSION['last_activity'] = $now;
        return;
    }

    // 2. If not logged in, attempt automatic login via Remember-Me cookie
    if (!isset($_SESSION['student_id']) && isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        validateRememberMeCookie();
    }
}

/**
 * Validates Remember-Me persistent cookie using split-token (selector:validator)
 */
function validateRememberMeCookie() {
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) return false;

    $cookieVal = $_COOKIE[REMEMBER_COOKIE_NAME];
    $parts = explode(':', $cookieVal, 2);
    if (count($parts) !== 2) {
        clearRememberMeCookie();
        return false;
    }

    list($selector, $validator) = $parts;
    $conn = getDbConnection();
    if (!$conn || $conn->connect_error) return false;

    // Look up selector in remember_tokens table
    $stmt = $conn->prepare("SELECT rt.id, rt.user_id, rt.token_hash, rt.expires_at, s.name, s.username, s.email, s.role, s.enrollment_number, s.course_name, s.semester 
                            FROM `remember_tokens` rt 
                            JOIN `students` s ON rt.user_id = s.id 
                            WHERE rt.selector = ? AND rt.expires_at > NOW() 
                            LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($tokenData = $res->fetch_assoc()) {
            // Verify validator against token_hash using constant-time hash_equals
            $calcHash = hash('sha256', $validator);
            if (hash_equals($tokenData['token_hash'], $calcHash)) {
                // Legitimate token -> Establish authenticated session
                session_regenerate_id(true);

                $_SESSION['student_id']         = (int)$tokenData['user_id'];
                $_SESSION['student_name']       = $tokenData['name'];
                $_SESSION['student_username']   = $tokenData['username'];
                $_SESSION['student_email']      = $tokenData['email'];
                $_SESSION['student_role']       = $tokenData['role'];
                $_SESSION['student_enrollment'] = $tokenData['enrollment_number'];
                $_SESSION['last_activity']      = time();

                // Rotate Remember-Me token for anti-replay defense
                rotateRememberToken($tokenData['id'], (int)$tokenData['user_id']);

                $stmt->close();
                return true;
            }
        }
        $stmt->close();
    }

    // Token invalid or expired -> Clear cookie
    clearRememberMeCookie();
    return false;
}

/**
 * Creates and sets a secure Remember-Me token
 */
function createRememberMeToken($userId) {
    $conn = getDbConnection();
    if (!$conn || $conn->connect_error) return false;

    // Generate random 16-byte selector (32 hex) and 32-byte validator (64 hex)
    $selector  = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $validator);

    $expirySeconds = time() + (REMEMBER_COOKIE_DAYS * 86400);
    $expiresAt     = date('Y-m-d H:i:s', $expirySeconds);

    $stmt = $conn->prepare("INSERT INTO `remember_tokens` (`user_id`, `selector`, `token_hash`, `expires_at`) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $selector, $tokenHash, $expiresAt);
        if ($stmt->execute()) {
            $stmt->close();

            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            setcookie(REMEMBER_COOKIE_NAME, "$selector:$validator", [
                'expires'  => $expirySeconds,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            return true;
        }
        $stmt->close();
    }
    return false;
}

/**
 * Rotates an existing remember token
 */
function rotateRememberToken($tokenId, $userId) {
    $conn = getDbConnection();
    if ($conn && !$conn->connect_error) {
        $del = $conn->prepare("DELETE FROM `remember_tokens` WHERE `id` = ?");
        if ($del) {
            $del->bind_param("i", $tokenId);
            $del->execute();
            $del->close();
        }
    }
    createRememberMeToken($userId);
}

/**
 * Deletes remember tokens for a specific user from MySQL and removes the cookie
 */
function clearRememberMeToken($userId = null) {
    $conn = getDbConnection();
    if ($conn && !$conn->connect_error) {
        if ($userId) {
            $stmt = $conn->prepare("DELETE FROM `remember_tokens` WHERE `user_id` = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();
            }
        } elseif (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
            $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
            if (!empty($parts[0])) {
                $stmt = $conn->prepare("DELETE FROM `remember_tokens` WHERE `selector` = ?");
                if ($stmt) {
                    $stmt->bind_param("s", $parts[0]);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    clearRememberMeCookie();
}

/**
 * Removes the Remember-Me HTTP cookie
 */
function clearRememberMeCookie() {
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
    }
}

/**
 * Completely terminates the PHP session and removes session cookies
 */
function destroySession() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Returns the currently authenticated user session data, or null
 */
function getAuthUser() {
    initSecureSession();
    if (!empty($_SESSION['student_id'])) {
        return [
            'id'                => (int)$_SESSION['student_id'],
            'name'              => $_SESSION['student_name'] ?? 'Student',
            'username'          => $_SESSION['student_username'] ?? '',
            'email'             => $_SESSION['student_email'] ?? '',
            'role'              => $_SESSION['student_role'] ?? 'student',
            'enrollment_number' => $_SESSION['student_enrollment'] ?? ''
        ];
    }
    return null;
}

/**
 * Guard: Requires student (or admin) login. Halts or returns 401 if unauthenticated.
 */
function requireStudentAuth($jsonResponse = true) {
    initSecureSession();
    if (empty($_SESSION['student_id'])) {
        $reason = !empty($GLOBALS['SH_AUTH_TIMEOUT']) ? 'timeout' : 'unauthorized';
        $message = ($reason === 'timeout')
            ? "Session expired due to 15 minutes of inactivity. Please log in again."
            : "Access denied. Please log in to access your student dashboard.";

        if ($jsonResponse) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "authenticated" => false,
                "reason" => $reason,
                "message" => $message,
                "redirect" => "login.html?reason=" . $reason
            ]);
            exit;
        } else {
            header("Location: ../pages/login.html?reason=" . $reason);
            exit;
        }
    }
    return getAuthUser();
}

/**
 * Guard: Requires administrator login. Restricts non-admin students.
 */
function requireAdminAuth($jsonResponse = true) {
    initSecureSession();
    if (empty($_SESSION['student_id'])) {
        $reason = !empty($GLOBALS['SH_AUTH_TIMEOUT']) ? 'timeout' : 'unauthorized';
        $message = ($reason === 'timeout')
            ? "Session expired due to 15 minutes of inactivity. Please log in again."
            : "Authentication required. Please log in as an administrator.";

        if ($jsonResponse) {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "authenticated" => false,
                "reason" => $reason,
                "message" => $message,
                "redirect" => "login.html?reason=" . $reason
            ]);
            exit;
        } else {
            header("Location: ../pages/login.html?reason=" . $reason);
            exit;
        }
    }

    if (($_SESSION['student_role'] ?? '') !== 'admin') {
        if ($jsonResponse) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "authenticated" => true,
                "authorized" => false,
                "message" => "Forbidden. Administrator privileges required.",
                "redirect" => "dashboard.html"
            ]);
            exit;
        } else {
            header("Location: ../pages/dashboard.html?reason=forbidden");
            exit;
        }
    }

    return getAuthUser();
}
