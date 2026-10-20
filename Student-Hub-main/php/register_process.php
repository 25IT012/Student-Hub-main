<?php
/**
 * =====================================================================
 * StudentHub - Secure User Registration Processor
 * =====================================================================
 * Features:
 *  - Backend input validation and sanitation
 *  - Duplicate email and username checks using MySQLi prepared statements
 *  - Password hashing with password_hash(..., PASSWORD_BCRYPT)
 *  - Secure parameterized insertion with MySQLi statements
 *  - Informative, appropriate response messages
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

session_start();

require_once __DIR__ . '/db.php';

// Accept both form-encoded (POST) and JSON payloads
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents("php://input");
    if ($raw) {
        $input = json_decode($raw, true) ?: [];
    }
}

// ---------------------------------------------------------------------
// 1. Sanitize Inputs
// ---------------------------------------------------------------------
$fullName        = trim($input['fullName'] ?? $input['name'] ?? '');
$username        = strtolower(trim($input['username'] ?? ''));
$email           = strtolower(trim($input['email'] ?? ''));
$mobile          = trim($input['mobile'] ?? '');
$password        = $input['password'] ?? '';
$confirmPassword = $input['confirmPassword'] ?? '';
$course          = trim($input['course'] ?? 'Computer Engineering');
$year            = trim($input['year'] ?? '1st Year');
$gender          = trim($input['gender'] ?? 'Male');
$city            = trim($input['city'] ?? 'Anand');

// Auto-derive username from email if left blank
if (empty($username) && !empty($email)) {
    $parts = explode('@', $email);
    $username = preg_replace('/[^a-zA-Z0-9._]/', '', strtolower($parts[0]));
}

// ---------------------------------------------------------------------
// 2. Strict Backend Validations
// ---------------------------------------------------------------------

// A. Required field presence
if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill in all mandatory fields (Full Name, Email, Password, and Confirm Password)."
    ]);
    exit;
}

// B. Full Name validation (2 to 100 characters)
if (strlen($fullName) < 2 || strlen($fullName) > 100) {
    echo json_encode([
        "success" => false,
        "message" => "Full name must be between 2 and 100 characters."
    ]);
    exit;
}

// C. Username format validation (alphanumeric, dots, underscores, 3-30 chars)
if (!preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
    echo json_encode([
        "success" => false,
        "message" => "Username must be 3 to 30 characters long and contain only lowercase letters, numbers, dots, or underscores."
    ]);
    exit;
}

// D. Email format validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "The email address '$email' is invalid. Please provide a valid email."
    ]);
    exit;
}

// E. Mobile number validation (exactly 10 digits)
if (!empty($mobile) && !preg_match('/^[0-9]{10}$/', $mobile)) {
    echo json_encode([
        "success" => false,
        "message" => "Mobile number must be exactly 10 digits."
    ]);
    exit;
}

// F. Password length validation (minimum 6 characters)
if (strlen($password) < 6) {
    echo json_encode([
        "success" => false,
        "message" => "Password must be at least 6 characters long."
    ]);
    exit;
}

// G. Password confirmation match
if ($password !== $confirmPassword) {
    echo json_encode([
        "success" => false,
        "message" => "Passwords do not match. Please re-enter identical passwords."
    ]);
    exit;
}

// H. Map Year of Study to Semester number
$semester = 2;
if (strpos($year, '2') !== false) $semester = 4;
elseif (strpos($year, '3') !== false) $semester = 6;
elseif (strpos($year, '4') !== false) $semester = 8;

// ---------------------------------------------------------------------
// 3. MySQLi Database Connection
// ---------------------------------------------------------------------
$conn = getDbConnection();

if (!$conn || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database server connection unavailable. Please ensure MySQL is running."
    ]);
    exit;
}

// ---------------------------------------------------------------------
// 4. Duplicate Email and Username Check (MySQLi Prepared Statement)
// ---------------------------------------------------------------------
$checkSql = "SELECT `email`, `username` FROM `students` WHERE `email` = ? OR `username` = ? LIMIT 1";
$checkStmt = $conn->prepare($checkSql);

if ($checkStmt) {
    $checkStmt->bind_param("ss", $email, $username);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($existing = $checkResult->fetch_assoc()) {
        if (strtolower($existing['email']) === $email) {
            $msg = "The email address '$email' is already registered. Please sign in or use another email.";
        } else {
            $msg = "The username '$username' is already taken. Please choose a different username.";
        }
        $checkStmt->close();
        echo json_encode([
            "success" => false,
            "message" => $msg
        ]);
        exit;
    }
    $checkStmt->close();
} else {
    echo json_encode([
        "success" => false,
        "message" => "Query preparation failed: " . $conn->error
    ]);
    exit;
}

// ---------------------------------------------------------------------
// 5. Password Hashing with password_hash()
// ---------------------------------------------------------------------
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

if ($hashedPassword === false) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to securely hash password."
    ]);
    exit;
}

// ---------------------------------------------------------------------
// 6. Generate Unique Student Enrollment Number
// ---------------------------------------------------------------------
$nextNum = 1;
$maxIdResult = $conn->query("SELECT MAX(id) AS max_id FROM `students`");
if ($maxIdResult && ($row = $maxIdResult->fetch_assoc())) {
    $nextNum = ((int)$row['max_id']) + 1;
}
$enrollmentNumber = "STU2026" . str_pad($nextNum, 3, "0", STR_PAD_LEFT);

// ---------------------------------------------------------------------
// 7. Secure Parameterized Insert via MySQLi Prepared Statement
// ---------------------------------------------------------------------
$insertSql = "INSERT INTO `students` 
    (`enrollment_number`, `username`, `name`, `email`, `password`, `mobile`, `course_name`, `semester`, `attendance`, `cgpa`, `gender`, `city`, `status`, `role`) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 85.00, 8.50, ?, ?, 'Active', 'student')";

$insertStmt = $conn->prepare($insertSql);

if (!$insertStmt) {
    echo json_encode([
        "success" => false,
        "message" => "Insert statement preparation failed: " . $conn->error
    ]);
    exit;
}

// Types: s=string, i=integer (7 strings, 1 int, 2 strings = 10 parameters)
$insertStmt->bind_param(
    "sssssssiss",
    $enrollmentNumber,
    $username,
    $fullName,
    $email,
    $hashedPassword,
    $mobile,
    $course,
    $semester,
    $gender,
    $city
);

if ($insertStmt->execute()) {
    $newStudentId = $insertStmt->insert_id;
    $insertStmt->close();

    // Set authenticated session for newly registered user
    $_SESSION['student_id']         = $newStudentId;
    $_SESSION['student_name']       = $fullName;
    $_SESSION['student_username']   = $username;
    $_SESSION['student_email']      = $email;
    $_SESSION['student_enrollment'] = $enrollmentNumber;
    $_SESSION['student_role']       = 'student';

    echo json_encode([
        "success" => true,
        "message" => "Registration successful! Your Student ID is $enrollmentNumber and Username is '$username'.",
        "enrollment_number" => $enrollmentNumber,
        "username"          => $username,
        "student_id"        => $newStudentId
    ]);
    exit;
} else {
    $err = $insertStmt->error;
    $insertStmt->close();
    echo json_encode([
        "success" => false,
        "message" => "Database insertion error: " . $err
    ]);
    exit;
}
