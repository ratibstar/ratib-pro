<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/backup_system.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/backup_system.php`.
 */
require_once '../../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Create backup directory if it doesn't exist
    $backupDir = '../../backups/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Generate backup filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "ratibprogram_backup_{$timestamp}.sql";
    $filepath = $backupDir . $filename;

    // Get database configuration
    $host = DB_HOST;
    $username = DB_USER;
    $password = DB_PASS;
    $database = DB_NAME;

    // Create backup using mysqldump
    $command = "mysqldump --host={$host} --user={$username} --password={$password} {$database} > {$filepath}";
    
    // Execute the command
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);

    if ($returnVar === 0 && file_exists($filepath)) {
        // Log the backup
        $logMessage = "System backup created: {$filename} by user ID: {$_SESSION['user_id']}";
        error_log($logMessage, 3, '../../logs/backup.log');
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'size' => filesize($filepath)
        ]);
    } else {
        throw new Exception('Failed to create backup file');
    }

} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage(), 3, '../../logs/error.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create backup: ' . $e->getMessage()
    ]);
}
?> 