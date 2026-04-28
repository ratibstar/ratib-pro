<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/utils/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/utils/stats.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    // Get current month stats
    $currentMonth = date('Y-m');
    
    $sql = "SELECT 
            COUNT(*) as total_workers,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_workers,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_workers,
            SUM(CASE WHEN police_status = 'ok' THEN 1 ELSE 0 END) as police_ok,
            SUM(CASE WHEN medical_status = 'ok' THEN 1 ELSE 0 END) as medical_ok,
            SUM(CASE WHEN visa_status = 'ok' THEN 1 ELSE 0 END) as visa_ok,
            SUM(CASE WHEN ticket_status = 'ok' THEN 1 ELSE 0 END) as ticket_ok,
            SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = ? THEN 1 ELSE 0 END) as new_this_month
            FROM workers";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Get trend data (last 6 months)
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
            FROM workers
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC";
            
    $result = $conn->query($sql);
    $trend = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'trend' => $trend
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