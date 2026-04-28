<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/stats.php`.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'stats');
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get all counts in one efficient query
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM subagents";
    
    $stmt = $pdo->query($sql);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo ApiResponse::success([
        'total' => (int)$stats['total'],
        'active' => (int)$stats['active'],
        'inactive' => (int)$stats['inactive']
    ]);

} catch (Exception $e) {
    echo ApiResponse::error($e->getMessage());
} 