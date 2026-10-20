<?php
/**
 * =====================================================================
 * StudentHub - Modular Database Handler & MySQL Helper
 * =====================================================================
 */

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "studenthub";

/**
 * Returns an active MySQL connection instance using MySQL statements
 * @return mysqli|null
 */
function getDbConnection() {
    global $db_host, $db_user, $db_pass, $db_name;
    
    // Suppress unhandled fatal warnings for graceful offline fallback
    mysqli_report(MYSQLI_REPORT_OFF);

    // 1. Initial MySQL connection attempt
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);

    // 2. If database does not exist (Error 1049), attempt auto-creation
    if ($mysqli->connect_errno === 1049) {
        $serverConn = @new mysqli($db_host, $db_user, $db_pass);
        if (!$serverConn->connect_error) {
            $serverConn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $serverConn->close();
            // Reconnect to newly created database
            $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        }
    }

    if ($mysqli->connect_error) {
        return null;
    }

    $mysqli->set_charset("utf8mb4");
    return $mysqli;
}

// Global connection instance for easy inclusion
$conn = getDbConnection();
