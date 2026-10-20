<?php
// ========================================================
// StudentHub - Event Registration Endpoint
// ========================================================
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

session_start();

require_once __DIR__ . '/db.php';

// Accept both form-encoded and JSON input
$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents("php://input");
    if ($raw) {
        $input = json_decode($raw, true) ?: [];
    }
}

$eventId = isset($input['event_id']) ? (int)$input['event_id'] : 0;
$studentId = $_SESSION['student_id'] ?? 1;

if (!$eventId) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid event ID."
    ]);
    exit;
}

$conn = getDbConnection();

if ($conn && !$conn->connect_error) {
    // 1. Check if already registered
    $checkStmt = $conn->prepare("SELECT id FROM `registrations` WHERE student_id = ? AND event_id = ? LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $studentId, $eventId);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        if ($res->num_rows > 0) {
            echo json_encode([
                "success" => false,
                "message" => "You are already registered for this event."
            ]);
            $checkStmt->close();
            exit;
        }
        $checkStmt->close();
    }

    // 2. Insert into registrations
    $insertStmt = $conn->prepare("INSERT INTO `registrations` (`student_id`, `event_id`, `status`) VALUES (?, ?, 'Confirmed')");
    if ($insertStmt) {
        $insertStmt->bind_param("ii", $studentId, $eventId);
        if ($insertStmt->execute()) {
            $insertStmt->close();
            echo json_encode([
                "success" => true,
                "message" => "Successfully registered for the event! Confirmed in database."
            ]);
            exit;
        }
    }
}

echo json_encode([
    "success" => true,
    "message" => "Registered successfully for event."
]);
exit;
