<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/export_data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/export_data.php`.
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
    // Create export directory if it doesn't exist
    $exportDir = '../../exports/';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    // Generate export filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "ratibprogram_export_{$timestamp}.json";
    $filepath = $exportDir . $filename;

    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    $exportData = [
        'export_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_id'],
            'tables_count' => count($tables)
        ],
        'data' => []
    ];

    // Export data from each table
    foreach ($tables as $table) {
        try {
            $tableData = [];
            $tableResult = $conn->query("SELECT * FROM `{$table}`");
            
            while ($row = $tableResult->fetch_assoc()) {
                $tableData[] = $row;
            }
            
            $exportData['data'][$table] = $tableData;
        } catch (Exception $e) {
            $exportData['data'][$table] = ['error' => $e->getMessage()];
        }
    }

    // Write export data to file
    $jsonData = json_encode($exportData, JSON_PRETTY_PRINT);
    if (file_put_contents($filepath, $jsonData) === false) {
        throw new Exception('Failed to write export file');
    }

    // Log the export
    $logMessage = "Data export created: {$filename} by user ID: {$_SESSION['user_id']}";
    error_log($logMessage, 3, '../../logs/export.log');

    echo json_encode([
        'success' => true,
        'message' => 'Data exported successfully',
        'filename' => $filename,
        'size' => filesize($filepath),
        'tables_exported' => count($tables)
    ]);

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage(), 3, '../../logs/error.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to export data: ' . $e->getMessage()
    ]);
}
?> 