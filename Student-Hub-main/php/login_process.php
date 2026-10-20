<?php
/**
 * =====================================================================
 * StudentHub - Secure Authentication & Login Processor
 * =====================================================================
 * Features:
 *  - Secure Session Initialization with HttpOnly / SameSite cookies
 *  - Password verification using password_verify()
 *  - Automatic password upgrading for legacy accounts
 *  - Session Fixation Prevention via session_regenerate_id(true)
 *  - Role-based redirection (Student -> dashboard.html, Admin -> admin.html)
 *  - Session inactivity timeout initialization
 *  - Optional secure Remember-Me persistent token (split-token with SHA-256)
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . '/auth_helper.php';

initSecureSession();

// Accept both form-encoded and JSON input
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents("php://input");
    if ($raw) {
        $input = json_decode($raw, true) ?: [];
    }
}

$username   = trim($input['username'] ?? $input['email'] ?? '');
$password   = $input['password'] ?? '';
$rememberMe = !empty($input['rememberMe']) && ($input['rememberMe'] === '1' || $input['rememberMe'] === 'true' || $input['rememberMe'] === 'on' || $input['rememberMe'] === true);

// 1. Validate Input Presence
if (empty($username) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Please enter your Username, Email, or Student ID and Password."
    ]);
    exit;
}

$conn = getDbConnection();

if ($conn && !$conn->connect_error) {
    // 2. Query User by Email, Username, or Enrollment ID via Prepared Statement
    $stmt = $conn->prepare("SELECT id, enrollment_number, username, name, email, password, course_name, semester, attendance, cgpa, role 
                            FROM `students` 
                            WHERE email = ? OR username = ? OR enrollment_number = ? 
                            LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("sss", $username, $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // 3. Verify Password using password_verify()
            $passwordMatches = password_verify($password, $user['password']);

            // Transparent legacy fallback: if stored password was plain text, rehash securely
            if (!$passwordMatches && ($password === $user['password'])) {
                $passwordMatches = true;
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare("UPDATE `students` SET `password` = ? WHERE `id` = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $newHash, $user['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }

            if ($passwordMatches) {
                // 4. Regenerate Session ID to defeat Session Fixation attacks
                session_regenerate_id(true);

                // 5. Populate Authenticated Session Variables
                $_SESSION['student_id']         = (int)$user['id'];
                $_SESSION['student_name']       = $user['name'];
                $_SESSION['student_username']   = $user['username'];
                $_SESSION['student_email']      = $user['email'];
                $_SESSION['student_role']       = $user['role'];
                $_SESSION['student_enrollment'] = $user['enrollment_number'];
                $_SESSION['last_activity']      = time(); // For idle timeout tracking

                // 6. Handle Optional Secure Remember-Me Token
                if ($rememberMe) {
                    createRememberMeToken((int)$user['id']);
                } else {
                    clearRememberMeToken((int)$user['id']);
                }

                // 7. Role-Based Redirection
                $redirectPage = ($user['role'] === 'admin') ? 'admin.html' : 'dashboard.html';

                echo json_encode([
                    "success"  => true,
                    "message"  => "Welcome back, " . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . "! Redirecting...",
                    "role"     => $user['role'],
                    "redirect" => $redirectPage,
                    "user"     => [
                        "id"                => (int)$user['id'],
                        "name"              => $user['name'],
                        "username"          => $user['username'],
                        "enrollment_number" => $user['enrollment_number'],
                        "department"        => $user['course_name'],
                        "semester"          => $user['semester'],
                        "role"              => $user['role']
                    ]
                ]);
                $stmt->close();
                exit;
            } else {
                http_response_code(401);
                echo json_encode([
                    "success" => false,
                    "message" => "Incorrect password. Please verify your credentials and try again."
                ]);
                $stmt->close();
                exit;
            }
        }
        $stmt->close();
    }
}

// 8. Admin fallback if database record is missing
if ((strtolower($username) === 'admin' || strtolower($username) === 'admin@studenthub.edu') && $password === 'admin123') {
    session_regenerate_id(true);

    $_SESSION['student_id']         = 11;
    $_SESSION['student_name']       = "System Administrator";
    $_SESSION['student_username']   = "admin";
    $_SESSION['student_email']      = "admin@studenthub.edu";
    $_SESSION['student_role']       = "admin";
    $_SESSION['student_enrollment'] = "ADM2026001";
    $_SESSION['last_activity']      = time();

    echo json_encode([
        "success"  => true,
        "message"  => "Admin authentication verified. Redirecting...",
        "role"     => "admin",
        "redirect" => "admin.html",
        "user"     => [
            "id"                => 11,
            "name"              => "System Administrator",
            "enrollment_number" => "ADM2026001",
            "role"              => "admin"
        ]
    ]);
    exit;
}

http_response_code(401);
echo json_encode([
    "success" => false,
    "message" => "No account found matching this Username, Email, or Student ID."
]);
exit;
