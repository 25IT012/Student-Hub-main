<?php
/**
 * =====================================================================
 * StudentHub - Event Management REST JSON API with Poster Upload
 * =====================================================================
 * Supports:
 *  - GET    : List all campus events (with search & category filters & registration counts)
 *  - GET?id : Fetch single event details with registered attendees list
 *  - POST(action=create) : Add event with poster file upload & strict validation
 *  - POST(action=update) : Update event details with optional poster replacement
 *  - POST(action=delete) : Remove event and delete uploaded poster file
 * File Upload Validation:
 *  - File type validation (image/jpeg, image/png, image/webp) via finfo_file
 *  - Extension whitelist (.jpg, .jpeg, .png, .webp)
 *  - Size limit: 3MB (3,145,728 bytes)
 *  - Randomized safe filenames in uploads/events/
 * Strict RBAC: Administrator access for write operations.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/audit_helper.php';

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
    if (!empty($_POST)) {
        $inputData = array_merge($inputData, $_POST);
    }
}

$action = $_GET['action'] ?? $inputData['action'] ?? '';

// =====================================================================
// Helper: Validate & Save Event Poster File
// =====================================================================
function handlePosterUpload($fileInputName, &$errorMessage) {
    if (empty($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded, perfectly valid (optional)
    }

    $file = $_FILES[$fileInputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = "File upload failed with error code: " . $file['error'];
        return false;
    }

    // 1. File Size Validation: Max 3MB
    $maxBytes = 3 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        $errorMessage = "Event poster file size exceeds the 3MB limit (Current: " . round($file['size'] / 1024 / 1024, 2) . "MB).";
        return false;
    }

    // 2. Extension Whitelist
    $origName = $file['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        $errorMessage = "Invalid image extension '.$ext'. Allowed formats: JPG, JPEG, PNG, WEBP.";
        return false;
    }

    // 3. MIME Type Validation using PHP Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        $errorMessage = "Security Validation Failed: File content is '$mimeType'. Only valid JPEG, PNG, and WebP images are permitted.";
        return false;
    }

    // 4. Secure Target Path
    $uploadDir = __DIR__ . '/../uploads/events/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uniqueName = 'poster_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $uploadDir . $uniqueName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return relative web path
        return 'uploads/events/' . $uniqueName;
    } else {
        $errorMessage = "Server failed to save uploaded poster file.";
        return false;
    }
}

// =====================================================================
// 1. GET: Retrieve Events List or Single Event
// =====================================================================
if ($method === 'GET') {
    // Single Event Fetch
    if (!empty($_GET['id'])) {
        $eventId = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT e.*, 
                                       (SELECT COUNT(*) FROM `registrations` r WHERE r.event_id = e.id) AS registered_count 
                                FROM `events` e 
                                WHERE e.id = ? LIMIT 1");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($event) {
            // Also fetch attendee list if admin is requesting
            $attendees = [];
            $attStmt = $conn->prepare("SELECT s.id, s.name, s.enrollment_number, s.email, s.course_name, s.semester, r.status, r.registration_date 
                                       FROM `registrations` r 
                                       JOIN `students` s ON r.student_id = s.id 
                                       WHERE r.event_id = ? 
                                       ORDER BY r.registration_date DESC");
            $attStmt->bind_param("i", $eventId);
            $attStmt->execute();
            $attRes = $attStmt->get_result();
            while ($att = $attRes->fetch_assoc()) {
                $attendees[] = $att;
            }
            $attStmt->close();
            $event['attendees'] = $attendees;

            echo json_encode(["success" => true, "event" => $event]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Event not found."]);
        }
        exit;
    }

    // List Events with Search and Category Filter
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? 'All');
    $status   = trim($_GET['status'] ?? 'All'); // 'open', 'closed', 'All'

    $whereClauses = ["1=1"];
    $params = [];
    $types  = "";

    if (!empty($search)) {
        $whereClauses[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
        $searchParam = "%" . $search . "%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }

    if ($category !== 'All' && !empty($category)) {
        $whereClauses[] = "e.category = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($status === 'open') {
        $whereClauses[] = "e.registration_open = 1";
    } elseif ($status === 'closed') {
        $whereClauses[] = "e.registration_open = 0";
    }

    $whereSql = implode(" AND ", $whereClauses);

    $sql = "SELECT e.id, e.title, e.category, e.description, e.event_date, e.event_time, e.location, e.capacity,
                   e.registration_open, e.poster_image, e.created_at,
                   (SELECT COUNT(*) FROM `registrations` r WHERE r.event_id = e.id) AS registered_count
            FROM `events` e
            WHERE $whereSql
            ORDER BY e.event_date ASC, e.id DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "success" => true,
        "count"   => count($events),
        "events"  => $events
    ]);
    exit;
}

// =====================================================================
// Write Operations: Require Administrator Authentication
// =====================================================================
$admin = requireAdminAuth(true);

// =====================================================================
// 2. CREATE EVENT with Poster Upload (POST action=create or without action and without ID)
// =====================================================================
if (($method === 'POST' && ($action === 'create' || empty($action))) && empty($inputData['id'])) {
    $title       = trim($inputData['title'] ?? '');
    $category    = trim($inputData['category'] ?? 'Technical');
    $description = trim($inputData['description'] ?? '');
    $eventDate   = trim($inputData['event_date'] ?? '');
    $eventTime   = trim($inputData['event_time'] ?? '10:00 AM - 1:00 PM');
    $location    = trim($inputData['location'] ?? 'Main Auditorium');
    $capacity    = (int)($inputData['capacity'] ?? 100);
    $regOpen     = isset($inputData['registration_open']) ? (int)filter_var($inputData['registration_open'], FILTER_VALIDATE_BOOLEAN) : 1;

    // Validation
    if (empty($title) || empty($description) || empty($eventDate) || empty($location)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Event title, description, date, and location are required."]);
        exit;
    }

    // Handle Poster Upload with Validation
    $uploadError = null;
    $posterPath = handlePosterUpload('poster', $uploadError);
    if ($posterPath === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $uploadError]);
        exit;
    }

    $insertStmt = $conn->prepare("INSERT INTO `events` (`title`, `category`, `description`, `event_date`, `event_time`, `location`, `capacity`, `registration_open`, `poster_image`) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertStmt->bind_param("ssssssiis", $title, $category, $description, $eventDate, $eventTime, $location, $capacity, $regOpen, $posterPath);

    if ($insertStmt->execute()) {
        $newId = $insertStmt->insert_id;
        $insertStmt->close();

        // Audit Log
        logAudit('EVENT_CREATE', 'event', $newId, "Created event '$title' on $eventDate (" . ($posterPath ? 'with poster' : 'no poster') . ")");

        http_response_code(201);
        echo json_encode([
            "success"      => true,
            "message"      => "Event '$title' created successfully.",
            "event_id"     => $newId,
            "poster_image" => $posterPath
        ]);
        exit;
    } else {
        $err = $insertStmt->error;
        $insertStmt->close();
        if ($posterPath && file_exists(__DIR__ . '/../' . $posterPath)) {
            @unlink(__DIR__ . '/../' . $posterPath);
        }
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database insert failed: " . $err]);
        exit;
    }
}

// =====================================================================
// 3. UPDATE EVENT (PUT or POST action=update)
// =====================================================================
if (($method === 'PUT') || ($method === 'POST' && ($action === 'update' || !empty($inputData['id'])))) {
    $id = (int)($inputData['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid event ID provided for update."]);
        exit;
    }

    // Check existing event
    $checkStmt = $conn->prepare("SELECT id, title, poster_image FROM `events` WHERE id = ? LIMIT 1");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Event not found."]);
        exit;
    }

    $title       = trim($inputData['title'] ?? '');
    $category    = trim($inputData['category'] ?? 'Technical');
    $description = trim($inputData['description'] ?? '');
    $eventDate   = trim($inputData['event_date'] ?? '');
    $eventTime   = trim($inputData['event_time'] ?? '10:00 AM - 1:00 PM');
    $location    = trim($inputData['location'] ?? 'Main Auditorium');
    $capacity    = (int)($inputData['capacity'] ?? 100);
    $regOpen     = isset($inputData['registration_open']) ? (int)filter_var($inputData['registration_open'], FILTER_VALIDATE_BOOLEAN) : 1;

    if (empty($title) || empty($description) || empty($eventDate) || empty($location)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Event title, description, date, and location cannot be empty."]);
        exit;
    }

    // Check if new poster uploaded
    $uploadError = null;
    $newPosterPath = handlePosterUpload('poster', $uploadError);
    if ($newPosterPath === false) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $uploadError]);
        exit;
    }

    if ($newPosterPath !== null) {
        // Remove old poster if exists and differs
        if (!empty($existing['poster_image'])) {
            $oldFullPath = __DIR__ . '/../' . $existing['poster_image'];
            if (file_exists($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }
        $finalPoster = $newPosterPath;
        $upStmt = $conn->prepare("UPDATE `events` SET `title` = ?, `category` = ?, `description` = ?, `event_date` = ?,
                                                     `event_time` = ?, `location` = ?, `capacity` = ?, `registration_open` = ?, `poster_image` = ?
                                  WHERE `id` = ?");
        $upStmt->bind_param("ssssssiisi", $title, $category, $description, $eventDate, $eventTime, $location, $capacity, $regOpen, $finalPoster, $id);
    } else {
        // Keep existing poster
        $finalPoster = $existing['poster_image'];
        $upStmt = $conn->prepare("UPDATE `events` SET `title` = ?, `category` = ?, `description` = ?, `event_date` = ?,
                                                     `event_time` = ?, `location` = ?, `capacity` = ?, `registration_open` = ?
                                  WHERE `id` = ?");
        $upStmt->bind_param("ssssssiii", $title, $category, $description, $eventDate, $eventTime, $location, $capacity, $regOpen, $id);
    }

    if ($upStmt->execute()) {
        $upStmt->close();
        logAudit('EVENT_UPDATE', 'event', $id, "Updated event: '$title' (Capacity: $capacity, Open: $regOpen)");

        echo json_encode([
            "success"      => true,
            "message"      => "Event '$title' updated successfully.",
            "poster_image" => $finalPoster
        ]);
        exit;
    } else {
        $err = $upStmt->error;
        $upStmt->close();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to update event: " . $err]);
        exit;
    }
}

// =====================================================================
// 4. DELETE EVENT (DELETE or POST action=delete)
// =====================================================================
if (($method === 'DELETE') || ($method === 'POST' && $action === 'delete')) {
    $id = (int)($inputData['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid event ID provided for deletion."]);
        exit;
    }

    // Check existing
    $checkStmt = $conn->prepare("SELECT id, title, poster_image FROM `events` WHERE id = ? LIMIT 1");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $targetEvent = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$targetEvent) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Event not found."]);
        exit;
    }

    $delStmt = $conn->prepare("DELETE FROM `events` WHERE `id` = ?");
    $delStmt->bind_param("i", $id);

    if ($delStmt->execute()) {
        $delStmt->close();

        // Delete poster file from disk if present
        if (!empty($targetEvent['poster_image'])) {
            $fullPath = __DIR__ . '/../' . $targetEvent['poster_image'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        logAudit('EVENT_DELETE', 'event', $id, "Deleted event '{$targetEvent['title']}'");

        echo json_encode([
            "success" => true,
            "message" => "Event '{$targetEvent['title']}' deleted successfully."
        ]);
        exit;
    } else {
        $err = $delStmt->error;
        $delStmt->close();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database delete failed: " . $err]);
        exit;
    }
}

http_response_code(405);
echo json_encode(["success" => false, "message" => "Method not supported."]);
exit;
