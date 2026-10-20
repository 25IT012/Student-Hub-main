<?php
/**
 * =====================================================================
 * StudentHub - Student Management REST JSON API
 * =====================================================================
 * Supports AJAX / Fetch-based CRUD operations without page reload:
 *  - GET    : List students with live search, department/status/semester filters
 *  - GET?id : Fetch single student details with registered events list
 *  - POST   : Create a new student with input validation & bcrypt hashing
 *  - PUT/POST(action=update) : Update existing student details
 *  - DELETE/POST(action=delete): Remove student record safely
 * All database operations use secure MySQLi prepared statements.
 * Strict RBAC: Administrator access only.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/audit_helper.php';

// Strict Role-Based Access Control
$admin = requireAdminAuth(true);

$conn = getDbConnection();
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON input or standard POST data
$inputData = [];
if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $inputData = $json;
        }
    }
    // Merge standard $_POST if sent as FormData
    if (!empty($_POST)) {
        $inputData = array_merge($inputData, $_POST);
    }
}

$action = $_GET['action'] ?? $inputData['action'] ?? '';

// =====================================================================
// 1. GET: Retrieve Single Student or Filtered List
// =====================================================================
if ($method === 'GET') {
    // Single Student Fetch
    if (!empty($_GET['id'])) {
        $studentId = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT s.id, s.enrollment_number, s.username, s.name, s.email, s.mobile,
                                       s.course_name, s.semester, s.attendance, s.cgpa, s.gender, s.city, s.status, s.role, s.created_at,
                                       (SELECT COUNT(*) FROM `registrations` r WHERE r.student_id = s.id) AS registered_events_count,
                                       (SELECT GROUP_CONCAT(e.title SEPARATOR ' • ') FROM `registrations` r JOIN `events` e ON r.event_id = e.id WHERE r.student_id = s.id) AS registered_events_list
                                FROM `students` s 
                                WHERE s.id = ? LIMIT 1");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student) {
            echo json_encode(["success" => true, "student" => $student]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Student not found."]);
        }
        exit;
    }

    // List with Search and Filter
    $search     = trim($_GET['search'] ?? '');
    $department = trim($_GET['department'] ?? 'All');
    $semester   = trim($_GET['semester'] ?? 'All');
    $status     = trim($_GET['status'] ?? 'All');
    $sortBy     = trim($_GET['sort'] ?? 'id_desc');

    $whereClauses = ["s.role != 'admin'"];
    $params = [];
    $types  = "";

    if (!empty($search)) {
        $whereClauses[] = "(s.name LIKE ? OR s.enrollment_number LIKE ? OR s.email LIKE ? OR s.username LIKE ? OR s.mobile LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sssss";
    }

    if ($department !== 'All' && !empty($department)) {
        $whereClauses[] = "s.course_name = ?";
        $params[] = $department;
        $types .= "s";
    }

    if ($semester !== 'All' && is_numeric($semester)) {
        $whereClauses[] = "s.semester = ?";
        $params[] = (int)$semester;
        $types .= "i";
    }

    if ($status !== 'All' && !empty($status)) {
        $whereClauses[] = "s.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $whereSql = implode(" AND ", $whereClauses);

    $orderBy = "s.id DESC";
    if ($sortBy === 'name_asc')  $orderBy = "s.name ASC";
    if ($sortBy === 'name_desc') $orderBy = "s.name DESC";
    if ($sortBy === 'id_asc')    $orderBy = "s.id ASC";
    if ($sortBy === 'id_desc')   $orderBy = "s.id DESC";
    if ($sortBy === 'att_desc')  $orderBy = "s.attendance DESC";

    $sql = "SELECT s.id, s.enrollment_number, s.username, s.name, s.email, s.mobile,
                   s.course_name, s.semester, s.attendance, s.cgpa, s.gender, s.city, s.status, s.role, s.created_at,
                   (SELECT COUNT(*) FROM `registrations` r WHERE r.student_id = s.id) AS registered_events_count
            FROM `students` s 
            WHERE $whereSql 
            ORDER BY $orderBy";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "success" => true,
        "count"   => count($students),
        "students" => $students
    ]);
    exit;
}

// =====================================================================
// 2. POST (action=create or default POST without ID): Add Student
// =====================================================================
if (($method === 'POST' && ($action === 'create' || empty($action))) && empty($inputData['id'])) {
    $name       = trim($inputData['name'] ?? '');
    $enrollment = trim($inputData['enrollment_number'] ?? '');
    $username   = trim($inputData['username'] ?? '');
    $email      = trim($inputData['email'] ?? '');
    $password   = $inputData['password'] ?? '';
    $mobile     = trim($inputData['mobile'] ?? '');
    $course     = trim($inputData['course_name'] ?? '');
    $semester   = (int)($inputData['semester'] ?? 1);
    $attendance = (float)($inputData['attendance'] ?? 85.0);
    $cgpa       = (float)($inputData['cgpa'] ?? 8.5);
    $gender     = in_array($inputData['gender'] ?? '', ['Male', 'Female', 'Other']) ? $inputData['gender'] : 'Male';
    $city       = trim($inputData['city'] ?? 'Anand');
    $status     = in_array($inputData['status'] ?? '', ['Active', 'Pending', 'Inactive']) ? $inputData['status'] : 'Active';

    // Validation
    if (empty($name) || empty($enrollment) || empty($email) || empty($password) || empty($course)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Name, Enrollment Number, Email, Password, and Course are required fields."]);
        exit;
    }

    if (empty($username)) {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $email)[0]));
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter a valid email address."]);
        exit;
    }

    // Check duplicates
    $checkStmt = $conn->prepare("SELECT id, email, enrollment_number, username FROM `students` WHERE email = ? OR enrollment_number = ? OR username = ? LIMIT 1");
    $checkStmt->bind_param("sss", $email, $enrollment, $username);
    $checkStmt->execute();
    $dupRes = $checkStmt->get_result();
    if ($dup = $dupRes->fetch_assoc()) {
        $checkStmt->close();
        http_response_code(409);
        $reason = "Record already exists.";
        if (strcasecmp($dup['email'], $email) === 0) $reason = "Email '$email' is already registered.";
        elseif (strcasecmp($dup['enrollment_number'], $enrollment) === 0) $reason = "Enrollment Number '$enrollment' is already registered.";
        elseif (strcasecmp($dup['username'], $username) === 0) $reason = "Username '$username' is already taken.";
        echo json_encode(["success" => false, "message" => $reason]);
        exit;
    }
    $checkStmt->close();

    // Hash Password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $role = 'student';

    $insertStmt = $conn->prepare("INSERT INTO `students` (`name`, `enrollment_number`, `username`, `email`, `password`, `mobile`, `course_name`, `semester`, `attendance`, `cgpa`, `gender`, `city`, `status`, `role`) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertStmt->bind_param("sssssssidsssss", $name, $enrollment, $username, $email, $hashedPassword, $mobile, $course, $semester, $attendance, $cgpa, $gender, $city, $status, $role);

    if ($insertStmt->execute()) {
        $newId = $insertStmt->insert_id;
        $insertStmt->close();

        // Audit Log
        logAudit('STUDENT_CREATE', 'student', $newId, "Added new student: $name ($enrollment, $course)");

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Student '$name' has been successfully registered into the database.",
            "student_id" => $newId
        ]);
        exit;
    } else {
        $err = $insertStmt->error;
        $insertStmt->close();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to add student: " . $err]);
        exit;
    }
}

// =====================================================================
// 3. UPDATE: Edit Existing Student Details
// =====================================================================
if (($method === 'PUT') || ($method === 'POST' && ($action === 'update' || !empty($inputData['id'])))) {
    $id = (int)($inputData['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid student ID specified for update."]);
        exit;
    }

    // Verify existing student
    $checkExist = $conn->prepare("SELECT id, name, role FROM `students` WHERE id = ? LIMIT 1");
    $checkExist->bind_param("i", $id);
    $checkExist->execute();
    $existing = $checkExist->get_result()->fetch_assoc();
    $checkExist->close();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Student record not found."]);
        exit;
    }

    $name       = trim($inputData['name'] ?? '');
    $enrollment = trim($inputData['enrollment_number'] ?? '');
    $username   = trim($inputData['username'] ?? '');
    $email      = trim($inputData['email'] ?? '');
    $mobile     = trim($inputData['mobile'] ?? '');
    $course     = trim($inputData['course_name'] ?? '');
    $semester   = (int)($inputData['semester'] ?? 1);
    $attendance = (float)($inputData['attendance'] ?? 85.0);
    $cgpa       = (float)($inputData['cgpa'] ?? 8.5);
    $gender     = in_array($inputData['gender'] ?? '', ['Male', 'Female', 'Other']) ? $inputData['gender'] : 'Male';
    $city       = trim($inputData['city'] ?? 'Anand');
    $status     = in_array($inputData['status'] ?? '', ['Active', 'Pending', 'Inactive']) ? $inputData['status'] : 'Active';
    $password   = $inputData['password'] ?? '';

    if (empty($name) || empty($enrollment) || empty($email) || empty($course)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Name, Enrollment ID, Email, and Course cannot be empty."]);
        exit;
    }

    // Check duplicate email/enrollment on other accounts
    $checkDup = $conn->prepare("SELECT id FROM `students` WHERE (email = ? OR enrollment_number = ? OR username = ?) AND id != ? LIMIT 1");
    $checkDup->bind_param("sssi", $email, $enrollment, $username, $id);
    $checkDup->execute();
    if ($checkDup->get_result()->fetch_assoc()) {
        $checkDup->close();
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "Another student already exists with this Email, Enrollment ID, or Username."]);
        exit;
    }
    $checkDup->close();

    if (!empty($password)) {
        // Update with new password
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $updateStmt = $conn->prepare("UPDATE `students` SET `name` = ?, `enrollment_number` = ?, `username` = ?, `email` = ?, `mobile` = ?,
                                                          `course_name` = ?, `semester` = ?, `attendance` = ?, `cgpa` = ?, `gender` = ?,
                                                          `city` = ?, `status` = ?, `password` = ?
                                      WHERE `id` = ?");
        $updateStmt->bind_param("ssssssidsssssi", $name, $enrollment, $username, $email, $mobile, $course, $semester, $attendance, $cgpa, $gender, $city, $status, $hashed, $id);
    } else {
        // Update without altering password
        $updateStmt = $conn->prepare("UPDATE `students` SET `name` = ?, `enrollment_number` = ?, `username` = ?, `email` = ?, `mobile` = ?,
                                                          `course_name` = ?, `semester` = ?, `attendance` = ?, `cgpa` = ?, `gender` = ?,
                                                          `city` = ?, `status` = ?
                                      WHERE `id` = ?");
        $updateStmt->bind_param("ssssssidssssi", $name, $enrollment, $username, $email, $mobile, $course, $semester, $attendance, $cgpa, $gender, $city, $status, $id);
    }

    if ($updateStmt->execute()) {
        $updateStmt->close();
        logAudit('STUDENT_UPDATE', 'student', $id, "Updated details for student ID $id ($name, $course, Sem $semester)");

        echo json_encode([
            "success" => true,
            "message" => "Student '$name' has been successfully updated."
        ]);
        exit;
    } else {
        $err = $updateStmt->error;
        $updateStmt->close();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database update failed: " . $err]);
        exit;
    }
}

// =====================================================================
// 4. DELETE: Remove Student Record
// =====================================================================
if (($method === 'DELETE') || ($method === 'POST' && $action === 'delete')) {
    $id = (int)($inputData['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid student ID provided for deletion."]);
        exit;
    }

    // Check student existence
    $checkStmt = $conn->prepare("SELECT id, name, enrollment_number, role FROM `students` WHERE id = ? LIMIT 1");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $targetStudent = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$targetStudent) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Student record not found."]);
        exit;
    }

    if ($targetStudent['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Security Restriction: System Administrator accounts cannot be deleted."]);
        exit;
    }

    $delStmt = $conn->prepare("DELETE FROM `students` WHERE `id` = ?");
    $delStmt->bind_param("i", $id);

    if ($delStmt->execute()) {
        $delStmt->close();
        logAudit('STUDENT_DELETE', 'student', $id, "Deleted student: {$targetStudent['name']} ({$targetStudent['enrollment_number']})");

        echo json_encode([
            "success" => true,
            "message" => "Student record for '{$targetStudent['name']}' ({$targetStudent['enrollment_number']}) was deleted successfully."
        ]);
        exit;
    } else {
        $err = $delStmt->error;
        $delStmt->close();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to delete student: " . $err]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method or action not allowed."]);
exit;
