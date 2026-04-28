<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_stats.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Check if tables exist first
    $tables = ['users', 'agents', 'workers'];
    $stats = [];
    
    foreach ($tables as $table) {
        $table_exists = $conn->query("SHOW TABLES LIKE '$table'");
        if ($table_exists && $table_exists->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($result) {
                $count = $result->fetch_assoc()['count'] ?? 0;
                $stats['total_' . $table] = (int)$count;
            } else {
                $stats['total_' . $table] = 0;
            }
        } else {
            $stats['total_' . $table] = 0;
        }
    }
    
    // Ensure all required stats are present
    $required_stats = [
        'total_users' => 0,
        'total_agents' => 0,
        'total_workers' => 0,
    ];
    
    $stats = array_merge($required_stats, $stats);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'debug' => [
            'tables_checked' => $tables,
            'connection_status' => $conn ? 'connected' : 'not connected'
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading statistics: ' . $e->getMessage(),
        'debug' => [
            'error_type' => get_class($e),
            'error_line' => $e->getLine()
        ]
    ]);
}
?> 