<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/stats.php`.
 */
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../api/utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../Utils/response.php and ' . __DIR__ . '/../../api/utils/response.php');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error: response.php not found']);
    exit;
}
require_once $responsePath;

require_once __DIR__ . '/hr-connection.php';

/** PDO MySQL: rowCount() is unreliable for SHOW TABLES — use fetch() */
function hrStatsTableExists(PDO $conn, string $table): bool
{
    $st = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
    return ($st !== false && $st->fetch(PDO::FETCH_NUM) !== false);
}

// Helper function to safely get count from a table
function getTableCount($conn, $table, $where = '') {
    try {
        $query = "SELECT COUNT(*) as total FROM `{$table}`";
        if ($where) {
            $query .= " WHERE {$where}";
        }
        $stmt = $conn->query($query);
        if ($stmt === false) {
            return 0;
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting count from {$table}: " . $e->getMessage());
        return 0;
    }
}

try {
    $conn = hr_api_get_connection();
    
    $stats = [];
    
    // Get employees count - check if table exists first
    try {
        if (hrStatsTableExists($conn, 'employees')) {
            $stats["employees"] = getTableCount($conn, 'employees', "status != 'Terminated'");
        } else {
            $stats["employees"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking employees table: " . $e->getMessage());
        $stats["employees"] = 0;
    }
    
    // Get attendance count for current month
    try {
        if (hrStatsTableExists($conn, 'attendance')) {
            $stats["attendance"] = getTableCount($conn, 'attendance', "MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
        } else {
            $stats["attendance"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking attendance table: " . $e->getMessage());
        $stats["attendance"] = 0;
    }
    
    // Get pending advances count
    try {
        if (hrStatsTableExists($conn, 'advances')) {
            $stats["advances"] = getTableCount($conn, 'advances', "status = 'pending'");
        } else {
            $stats["advances"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking advances table: " . $e->getMessage());
        $stats["advances"] = 0;
    }
    
    // Get pending salaries count
    try {
        if (hrStatsTableExists($conn, 'salaries')) {
            $stats["salaries"] = getTableCount($conn, 'salaries', "status = 'pending'");
        } else {
            $stats["salaries"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking salaries table: " . $e->getMessage());
        $stats["salaries"] = 0;
    }
    
    // Get documents count
    try {
        if (hrStatsTableExists($conn, 'hr_documents')) {
            $stats["documents"] = getTableCount($conn, 'hr_documents', "status = 'active'");
        } else {
            $stats["documents"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking hr_documents table: " . $e->getMessage());
        $stats["documents"] = 0;
    }
    
    // Get cars count
    try {
        if (hrStatsTableExists($conn, 'cars')) {
            $stats["cars"] = getTableCount($conn, 'cars', "status != 'maintenance'");
        } else {
            $stats["cars"] = 0;
        }
    } catch (Exception $e) {
        error_log("Error checking cars table: " . $e->getMessage());
        $stats["cars"] = 0;
    }
    
    sendResponse([
        "success" => true,
        "data" => $stats
    ]);
    
} catch (Throwable $e) {
    error_log("HR Stats API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    sendResponse([
        "success" => false,
        "message" => "Failed to fetch HR stats: " . $e->getMessage(),
        "data" => [
            "employees" => 0,
            "attendance" => 0,
            "advances" => 0,
            "salaries" => 0,
            "documents" => 0,
            "cars" => 0
        ]
    ], 500);
}
?>
