<?php
// ========================================================
// StudentHub - Save Academic Details Endpoint
// ========================================================
header("Content-Type: application/json; charset=UTF-8");

session_start();

require_once __DIR__ . '/db.php';

$attendance = isset($_POST['attendance']) ? floatval($_POST['attendance']) : null;
$cgpa = isset($_POST['cgpa']) ? floatval($_POST['cgpa']) : null;
$studentId = $_SESSION['student_id'] ?? 1;

if ($attendance !== null && $cgpa !== null) {
    $_SESSION['academic_attendance'] = $attendance;
    $_SESSION['academic_cgpa'] = $cgpa;

    $conn = getDbConnection();
    if ($conn && !$conn->connect_error) {
        $stmt = $conn->prepare("UPDATE `students` SET attendance = ?, cgpa = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ddi", $attendance, $cgpa, $studentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Academic details updated successfully in database."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid attendance or CGPA values."
    ]);
}
exit;
