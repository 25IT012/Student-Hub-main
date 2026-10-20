<?php
/**
 * =====================================================================
 * StudentHub - One-Click Database Setup & Migration Script
 * =====================================================================
 * Run via browser: http://localhost/Student-Hub/php/setup_database.php
 * Or via terminal: php php/setup_database.php
 */

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "studenthub";

$is_cli = (php_sapi_name() === 'cli');

function outputMsg($msg, $is_error = false) {
    global $is_cli;
    if ($is_cli) {
        echo ($is_error ? "[ERROR] " : "[OK] ") . strip_tags($msg) . "\n";
    } else {
        $color = $is_error ? "#ef4444" : "#10b981";
        echo "<div style='padding: 8px 12px; margin: 4px 0; border-radius: 6px; background: " . ($is_error ? "#fee2e2" : "#ecfdf5") . "; color: $color; font-family: monospace;'>$msg</div>";
    }
}

if (!$is_cli) {
    echo "<!DOCTYPE html><html><head><title>StudentHub DB Setup</title>";
    echo "<style>body{ font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; line-height: 1.6; background: #f8fafc; color: #1e293b; }</style></head><body>";
    echo "<h2>StudentHub — MySQL Database Initialization</h2>";
}

// 1. Connect to MySQL Server
outputMsg("Connecting to MySQL server at $host as '$user'...");
$conn = @new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    outputMsg("Could not connect to MySQL: " . $conn->connect_error, true);
    if (!$is_cli) {
        echo "<p>Please ensure that MySQL is running in your XAMPP Control Panel.</p></body></html>";
    }
    exit(1);
}
outputMsg("Successfully connected to MySQL server.");

// 2. Read schema file
$sqlFile = __DIR__ . '/../database/studenthub.sql';
if (!file_exists($sqlFile)) {
    outputMsg("Schema file not found at: $sqlFile", true);
    exit(1);
}

$sqlContent = file_get_contents($sqlFile);

// 3. Execute multi-query statements
outputMsg("Importing tables and seed data from studenthub.sql...");
if ($conn->multi_query($sqlContent)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->error) {
    outputMsg("Notice during query execution: " . $conn->error, true);
} else {
    outputMsg("Database `studenthub` and tables created successfully!");
}

// 4. Verify table counts
$conn->select_db($dbname);

$tables = ['students', 'events', 'registrations', 'courses'];
foreach ($tables as $table) {
    $countRes = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
    if ($countRes) {
        $row = $countRes->fetch_assoc();
        outputMsg("Table `<strong>$table</strong>`: " . $row['total'] . " records populated.");
        $countRes->free();
    }
}

$conn->close();

outputMsg("<strong>Database setup completed successfully!</strong>");
if (!$is_cli) {
    echo "<br><a href='../pages/admin.html' style='display:inline-block; padding:10px 18px; background:#2563eb; color:#fff; text-decoration:none; border-radius:6px; font-weight:600;'>Go to Admin Panel</a> ";
    echo "<a href='../pages/dashboard.html' style='display:inline-block; padding:10px 18px; background:#10b981; color:#fff; text-decoration:none; border-radius:6px; font-weight:600; margin-left:10px;'>Go to Student Dashboard</a>";
    echo "</body></html>";
}
