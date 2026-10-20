<?php
/**
 * =====================================================================
 * StudentHub - Audit Trail & Compliance JSON API
 * =====================================================================
 * Fetches administrator activity logs with search and action filters.
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

$search = trim($_GET['search'] ?? '');
$action = trim($_GET['action'] ?? 'All');
$limit  = min(max((int)($_GET['limit'] ?? 50), 10), 200);

$whereClauses = ["1=1"];
$params = [];
$types  = "";

if (!empty($search)) {
    $whereClauses[] = "(a.user_name LIKE ? OR a.details LIKE ? OR a.action LIKE ? OR a.ip_address LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

if ($action !== 'All' && !empty($action)) {
    $whereClauses[] = "a.action = ?";
    $params[] = $action;
    $types .= "s";
}

$whereSql = implode(" AND ", $whereClauses);

$sql = "SELECT a.id, a.user_id, a.user_name, a.action, a.entity_type, a.entity_id, a.details, a.ip_address, a.created_at 
        FROM `audit_logs` a 
        WHERE $whereSql 
        ORDER BY a.id DESC 
        LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

// Distinct action types for UI filter dropdown
$actionsRes = $conn->query("SELECT DISTINCT action FROM `audit_logs` ORDER BY action ASC");
$actionTypes = [];
if ($actionsRes) {
    while ($arow = $actionsRes->fetch_assoc()) {
        $actionTypes[] = $arow['action'];
    }
}

echo json_encode([
    "success"      => true,
    "count"        => count($logs),
    "logs"         => $logs,
    "action_types" => $actionTypes
]);
exit;
