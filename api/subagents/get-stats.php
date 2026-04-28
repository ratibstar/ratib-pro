<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/get-stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/get-stats.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM subagents
    ";
    
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception("Failed to get statistics");
    }
    
    $stats = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$stats['total'],
            'active' => (int)$stats['active'],
            'inactive' => (int)$stats['inactive']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 