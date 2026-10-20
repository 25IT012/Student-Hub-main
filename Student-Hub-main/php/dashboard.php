<?php
// ========================================================
// StudentHub - Student Dashboard API Endpoint (Protected)
// ========================================================
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once __DIR__ . '/auth_helper.php';

// 1. Restrict Dashboard Access: Requires valid student session (or halts with HTTP 401)
$authUser = requireStudentAuth(true);
$studentId = $authUser['id'];

$student = null;
$courses = [];
$registered_events = [];

$conn = getDbConnection();

if ($conn && !$conn->connect_error) {
    // 2. Fetch Student Details via MySQL Prepared Statement for authenticated user
    $stmt = $conn->prepare("SELECT id, name, email, course_name AS department, semester, enrollment_number, attendance, cgpa FROM `students` WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $student = $row;
            if (isset($_SESSION['academic_attendance'])) $student['attendance'] = $_SESSION['academic_attendance'];
            if (isset($_SESSION['academic_cgpa'])) $student['cgpa'] = $_SESSION['academic_cgpa'];
        }
        $stmt->close();
    }

    // 3. Fetch Active Courses via MySQL Statement
    $qCourses = $conn->query("SELECT course_code AS course_id, course_name FROM `courses` ORDER BY id ASC");
    if ($qCourses) {
        while ($c = $qCourses->fetch_assoc()) {
            $courses[] = $c;
        }
        $qCourses->free();
    }

    // 4. Fetch Registered Events for Logged-In Student via SQL JOIN statement
    $stmtEvents = $conn->prepare("SELECT e.title, e.location, e.event_date FROM `registrations` r JOIN `events` e ON r.event_id = e.id WHERE r.student_id = ? ORDER BY e.event_date ASC");
    if ($stmtEvents) {
        $stmtEvents->bind_param("i", $studentId);
        $stmtEvents->execute();
        $resEv = $stmtEvents->get_result();
        while ($ev = $resEv->fetch_assoc()) {
            $registered_events[] = $ev;
        }
        $stmtEvents->close();
    }
}

// Fallback if records were not found for this ID
if (!$student) {
    $student = [
        "name"              => $authUser['name'] ?? "Student",
        "department"        => "Computer Engineering",
        "semester"          => "Semester 2",
        "enrollment_number" => $authUser['enrollment_number'] ?? "STU2026001",
        "attendance"        => 85.00,
        "cgpa"              => 8.50
    ];
}

echo json_encode([
    "success"           => true,
    "student"           => $student,
    "courses"           => $courses,
    "registered_events" => $registered_events
]);
exit;
