<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/clear_logs.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/clear_logs.php`.
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
    $logsDir = '../../logs/';
    $clearedFiles = [];
    $errors = [];

    // Get all log files
    $logFiles = glob($logsDir . '*.log');
    
    foreach ($logFiles as $logFile) {
        try {
            // Clear the log file content
            file_put_contents($logFile, '');
            $clearedFiles[] = basename($logFile);
        } catch (Exception $e) {
            $errors[] = "Failed to clear " . basename($logFile) . ": " . $e->getMessage();
        }
    }

    // Log the action
    $logMessage = "Logs cleared by user ID: {$_SESSION['user_id']}. Files cleared: " . implode(', ', $clearedFiles);
    error_log($logMessage, 3, $logsDir . 'admin_actions.log');

    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => 'Logs cleared successfully',
            'files_cleared' => count($clearedFiles),
            'files' => $clearedFiles
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Logs cleared with some errors',
            'files_cleared' => count($clearedFiles),
            'files' => $clearedFiles,
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Clear logs error: " . $e->getMessage(), 3, '../../logs/error.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear logs: ' . $e->getMessage()
    ]);
}
?> 