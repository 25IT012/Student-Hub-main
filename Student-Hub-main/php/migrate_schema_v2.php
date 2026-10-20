<?php
/**
 * Schema Migration for StudentHub v2
 * Adds poster_image to events table
 * Creates audit_logs table
 */
require_once __DIR__ . '/db_connect.php';

echo "Running StudentHub v2 Schema Migration...\n";

// 1. Add poster_image to events if not exists
$colCheck = $conn->query("SHOW COLUMNS FROM `events` LIKE 'poster_image'");
if ($colCheck && $colCheck->num_rows === 0) {
    $alterSql = "ALTER TABLE `events` ADD COLUMN `poster_image` VARCHAR(255) DEFAULT NULL AFTER `registration_open`";
    if ($conn->query($alterSql)) {
        echo "[SUCCESS] Added 'poster_image' column to `events` table.\n";
    } else {
        echo "[ERROR] Failed to add poster_image: " . $conn->error . "\n";
    }
} else {
    echo "[INFO] 'poster_image' column already exists in `events`.\n";
}

// 2. Create audit_logs table
$auditSql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL COMMENT 'Admin user ID who executed the action',
  `user_name` VARCHAR(100) DEFAULT NULL COMMENT 'Name of admin user',
  `action` VARCHAR(50) NOT NULL COMMENT 'STUDENT_CREATE, EVENT_UPDATE, etc.',
  `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'student, event, registration',
  `entity_id` INT DEFAULT NULL COMMENT 'ID of affected record',
  `details` TEXT DEFAULT NULL COMMENT 'JSON or human-readable description of changes',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_action` (`action`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($auditSql)) {
    echo "[SUCCESS] `audit_logs` table verified/created.\n";
} else {
    echo "[ERROR] Failed to create audit_logs: " . $conn->error . "\n";
}

// 3. Create uploads/events directory
$uploadDir = __DIR__ . '/../uploads/events';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "[SUCCESS] Created directory: uploads/events\n";
    } else {
        echo "[ERROR] Could not create uploads/events directory.\n";
    }
} else {
    echo "[INFO] Directory uploads/events already exists.\n";
}

// Security: Add .htaccess in uploads directory to prevent executing arbitrary PHP files
$htaccessPath = __DIR__ . '/../uploads/.htaccess';
if (!file_exists($htaccessPath)) {
    $htaccessContent = "# Prevent script execution in upload directory\n<FilesMatch \"\.(php|php5|phtml|phar|sh|pl|cgi)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n";
    file_put_contents($htaccessPath, $htaccessContent);
    echo "[SUCCESS] Security .htaccess created in uploads/\n";
}

// Insert initial system audit log if table is empty
$countRes = $conn->query("SELECT COUNT(*) AS cnt FROM `audit_logs`");
if ($countRes && (int)$countRes->fetch_assoc()['cnt'] === 0) {
    $initLog = $conn->prepare("INSERT INTO `audit_logs` (`user_id`, `user_name`, `action`, `entity_type`, `details`, `ip_address`) VALUES (?, ?, ?, ?, ?, ?)");
    $uid = 11;
    $uname = "System Administrator";
    $act = "SYSTEM_INIT";
    $etype = "system";
    $det = "StudentHub database schema v2 migration completed. Audit logging initialized.";
    $ip = "127.0.0.1";
    $initLog->bind_param("isssss", $uid, $uname, $act, $etype, $det, $ip);
    $initLog->execute();
    $initLog->close();
    echo "[SUCCESS] Seeded initial audit log entry.\n";
}

echo "Migration finished.\n";
