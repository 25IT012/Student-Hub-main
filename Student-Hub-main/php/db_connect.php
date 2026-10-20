<?php
/**
 * =====================================================================
 * StudentHub - MySQL Database Connection File
 * =====================================================================
 * Connects to MySQL using standard MySQLi statements.
 * Compatible with XAMPP, WAMP, LAMP, and production MySQL servers.
 */

// Database Credentials
define('DB_SERVER',   'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'studenthub');

// 1. Establish MySQL Connection using MySQLi statement
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// 2. Validate Connection
if ($conn->connect_error) {
    error_log("StudentHub MySQL Connection Error: " . $conn->connect_error);
    // Don't crash abruptly in web API responses; flag error
    $db_connected = false;
    $db_error = $conn->connect_error;
} else {
    $db_connected = true;
    $db_error = null;
    // 3. Set character set to utf8mb4 for universal character and emoji support
    $conn->set_charset("utf8mb4");
}

/**
 * Procedural Alternative:
 * $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
 * if (!$conn) {
 *     die("Connection failed: " . mysqli_connect_error());
 * }
 * mysqli_set_charset($conn, "utf8mb4");
 */
