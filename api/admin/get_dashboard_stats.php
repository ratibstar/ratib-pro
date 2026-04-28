<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_dashboard_stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_dashboard_stats.php`.
 */
// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/includes/config.php';

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
    // Get dashboard statistics
    $stats = [];
    
    // Count users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_users'] = $row['count'];
    } else {
        $stats['total_users'] = 0;
    }
    
    // Count agents
    $result = $conn->query("SELECT COUNT(*) as count FROM agents");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_agents'] = $row['count'];
    } else {
        $stats['total_agents'] = 0;
    }
    
    // Count workers
    $result = $conn->query("SELECT COUNT(*) as count FROM workers");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_workers'] = $row['count'];
    } else {
        $stats['total_workers'] = 0;
    }
    
    
    // Count HR employees
    $result = $conn->query("SELECT COUNT(*) as count FROM hr_employees");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_hr_employees'] = $row['count'];
    } else {
        $stats['total_hr_employees'] = 0;
    }
    
    // Count active users
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['active_users'] = $row['count'];
    } else {
        $stats['active_users'] = 0;
    }
    
    // Count pending workers
    $result = $conn->query("SELECT COUNT(*) as count FROM workers WHERE status = 'pending'");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['pending_workers'] = $row['count'];
    } else {
        $stats['pending_workers'] = 0;
    }
    
    
    // Get system settings counts
    $result = $conn->query("SELECT COUNT(*) as count FROM visa_types");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_visa_types'] = $row['count'];
    } else {
        $stats['total_visa_types'] = 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM recruitment_countries");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_countries'] = $row['count'];
    } else {
        $stats['total_countries'] = 0;
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM job_categories");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_job_categories'] = $row['count'];
    } else {
        $stats['total_job_categories'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving dashboard stats: ' . $e->getMessage()
    ]);
}
?> 