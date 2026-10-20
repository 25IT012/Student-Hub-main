<?php
/**
 * =====================================================================
 * StudentHub - Audit Logging Helper
 * =====================================================================
 * Records administrative actions into the `audit_logs` MySQL table for compliance,
 * traceability, and activity reporting.
 */

if (defined('AUDIT_HELPER_LOADED')) return;
define('AUDIT_HELPER_LOADED', true);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';

/**
 * Record an administrative activity in the audit trail.
 *
 * @param string $action      e.g. STUDENT_CREATE, STUDENT_UPDATE, STUDENT_DELETE, EVENT_CREATE, EVENT_DELETE
 * @param string|null $entityType e.g. student, event, registration, auth
 * @param int|null $entityId   ID of affected entity
 * @param string|array|null $details Description or array of changed fields
 * @return bool True if logged successfully
 */
function logAudit($action, $entityType = null, $entityId = null, $details = null) {
    global $conn;
    if (!$conn || $conn->connect_error) {
        $conn = getDbConnection();
    }
    if (!$conn || $conn->connect_error) {
        return false;
    }

    $user = getAuthUser();
    $userId   = $user['id'] ?? ($_SESSION['student_id'] ?? null);
    $userName = $user['name'] ?? ($_SESSION['student_name'] ?? 'System');

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    if (is_array($details) || is_object($details)) {
        $detailsStr = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        $detailsStr = (string)$details;
    }

    $stmt = $conn->prepare("INSERT INTO `audit_logs` (`user_id`, `user_name`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssiss", $userId, $userName, $action, $entityType, $entityId, $detailsStr, $ipAddress);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}
