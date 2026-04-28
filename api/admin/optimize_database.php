<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/optimize_database.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/optimize_database.php`.
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
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    $optimizedTables = [];
    $errors = [];

    // Optimize each table
    foreach ($tables as $table) {
        try {
            $conn->query("OPTIMIZE TABLE `{$table}`");
            $optimizedTables[] = $table;
        } catch (Exception $e) {
            $errors[] = "Failed to optimize table {$table}: " . $e->getMessage();
        }
    }

    // Log the optimization
    $logMessage = "Database optimization completed by user ID: {$_SESSION['user_id']}. Tables optimized: " . count($optimizedTables);
    if (!empty($errors)) {
        $logMessage .= ". Errors: " . implode(', ', $errors);
    }
    error_log($logMessage, 3, '../../logs/database.log');

    if (empty($errors)) {
        echo json_encode([
            'success' => true,
            'message' => 'Database optimized successfully',
            'tables_optimized' => count($optimizedTables),
            'tables' => $optimizedTables
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Database optimization completed with some errors',
            'tables_optimized' => count($optimizedTables),
            'tables' => $optimizedTables,
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Database optimization error: " . $e->getMessage(), 3, '../../logs/error.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to optimize database: ' . $e->getMessage()
    ]);
}
?> 