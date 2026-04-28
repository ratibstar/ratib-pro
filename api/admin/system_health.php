<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/system_health.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/system_health.php`.
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
    $health = [];

    // Database health
    try {
        $result = $conn->query("SELECT 1");
        if ($result) {
            $health['database'] = 'Connected';
        } else {
            $health['database'] = 'Error';
        }
    } catch (Exception $e) {
        $health['database'] = 'Failed: ' . $e->getMessage();
    }

    // Storage health
    $diskFree = disk_free_space('/');
    $diskTotal = disk_total_space('/');
    $diskUsed = $diskTotal - $diskFree;
    $diskUsagePercent = round(($diskUsed / $diskTotal) * 100, 2);
    
    if ($diskUsagePercent < 80) {
        $health['storage'] = 'Good (' . $diskUsagePercent . '%)';
    } elseif ($diskUsagePercent < 90) {
        $health['storage'] = 'Warning (' . $diskUsagePercent . '%)';
    } else {
        $health['storage'] = 'Critical (' . $diskUsagePercent . '%)';
    }

    // Memory health
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    
    if ($memoryUsage < 50 * 1024 * 1024) { // Less than 50MB
        $health['memory'] = 'Good';
    } elseif ($memoryUsage < 100 * 1024 * 1024) { // Less than 100MB
        $health['memory'] = 'Moderate';
    } else {
        $health['memory'] = 'High';
    }

    // Performance health
    $loadTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    if ($loadTime < 1) {
        $health['performance'] = 'Excellent';
    } elseif ($loadTime < 3) {
        $health['performance'] = 'Good';
    } elseif ($loadTime < 5) {
        $health['performance'] = 'Moderate';
    } else {
        $health['performance'] = 'Slow';
    }

    // PHP version
    $health['php_version'] = PHP_VERSION;

    // MySQL version
    $mysqlVersion = $conn->query("SELECT VERSION() as version")->fetch_assoc()['version'];
    $health['mysql_version'] = $mysqlVersion;

    echo json_encode([
        'success' => true,
        'health' => $health,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("System health check error: " . $e->getMessage(), 3, '../../logs/error.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check system health: ' . $e->getMessage()
    ]);
}
?> 