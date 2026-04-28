<?php
/**
 * EN: Handles API endpoint/business logic in `api/dashboard/activity-trends.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/dashboard/activity-trends.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_clean();
    echo ApiResponse::error('Unauthorized', 401);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get days parameter (default 30)
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    if ($days < 1 || $days > 365) {
        $days = 30; // Sanitize to valid range
    }
    
    $data = [];
    
    // Initialize merged data array for all days
    $mergedData = [];
    
    // Generate all dates in range first
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $mergedData[$date] = [
            'date' => $date,
            'activities' => 0,
            'reports' => 0
        ];
    }
    
    // Check if activity_logs table exists
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            // Get activity counts per day for the last N days
            $stmt = $conn->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as activities
                FROM activity_logs
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $activityResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update merged data with activity counts
            foreach ($activityResults as $row) {
                if (!isset($row['date']) || !isset($row['activities'])) {
                    continue; // Skip invalid rows
                }
                $date = $row['date'];
                $count = (int)($row['activities'] ?? 0);
                if (isset($mergedData[$date]) && $count >= 0) {
                    $mergedData[$date]['activities'] = $count;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Activity trends API - activity_logs error: " . $e->getMessage());
    }
    
    // Check if global_history table exists for reports
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            // Get report counts per day from global_history
            // Use more specific matching to avoid false positives (e.g., "reported" vs actual reports)
            $stmt = $conn->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as report_count
                FROM global_history
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    AND (
                        module = 'reports' 
                        OR (module LIKE '%report%' AND action IN ('generate', 'view', 'export', 'create'))
                        OR (description LIKE '%report%' AND action IN ('generate', 'view', 'export', 'create'))
                    )
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            $reportResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Update merged data with report counts
            foreach ($reportResults as $row) {
                if (!isset($row['date']) || !isset($row['report_count'])) {
                    continue; // Skip invalid rows
                }
                $date = $row['date'];
                $count = (int)($row['report_count'] ?? 0);
                if (isset($mergedData[$date]) && $count >= 0) {
                    $mergedData[$date]['reports'] = $count;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Activity trends API - global_history error: " . $e->getMessage());
    }
    
    // Convert to array maintaining date order
    $data = array_values($mergedData);
    
    ob_clean();
    echo ApiResponse::success($data);
    exit;
    
} catch (Exception $e) {
    error_log("Activity trends API error: " . $e->getMessage());
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
}
