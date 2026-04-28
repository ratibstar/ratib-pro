<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/get.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/get.php`.
 */
// EN: Enable logging visibility while keeping production responses clean.
// AR: تفعيل التتبع في السجلات مع إبقاء استجابات الإنتاج نظيفة.
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set content type to JSON
header('Content-Type: application/json');

// EN: Query endpoint supports single-worker fetch and paginated list mode.
// AR: نقطة النهاية تدعم جلب عامل مفرد أو قائمة مرقمة حسب الفلاتر.
try {
    // Set headers to prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Check if required files exist
    if (!file_exists(__DIR__ . '/../../core/Database.php')) {
        throw new Exception('Database.php file not found');
    }
    
    // Load the correct Database class with getInstance() method
    require_once __DIR__ . '/../../core/Database.php';
    
    // Verify Database class has getInstance method
    if (!method_exists('Database', 'getInstance')) {
        throw new Exception('Database class does not have getInstance method');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // EN: Read and normalize pagination/search/filter query parameters.
    // AR: قراءة وتوحيد معاملات الترقيم والبحث والفلاتر من الطلب.
    // Get query parameters
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    // CRITICAL: Ensure limit is exactly what was requested, default to 10
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
    // If limit is explicitly 10, use 10 (don't let it be overridden)
    if (isset($_GET['limit']) && (int)$_GET['limit'] === 10) {
        $limit = 10;
    }
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    
    // EN: Fast path: when id is present, return one worker payload immediately.
    // AR: مسار سريع: عند وجود id يتم إرجاع سجل عامل واحد مباشرة.
    // If ID is provided, return single worker
    if ($id) {
        $query = "
            SELECT w.*, 
                   w.country,
                   a.agent_name,
                   s.subagent_name
            FROM workers w
            LEFT JOIN agents a ON w.agent_id = a.id
            LEFT JOIN subagents s ON w.subagent_id = s.id
            WHERE w.id = ? AND w.status != 'deleted'
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($worker) {
            try {
                require_once __DIR__ . '/../../../includes/government-labor.php';
                $alerts = ratib_government_worker_alerts_pdo($conn, $workerId);
                $worker['government_alerts'] = $alerts;
                $worker['government_deploy_blocked'] = ratib_government_deploy_block_reason_pdo($conn, $workerId) !== null;
            } catch (Throwable $e) {
                $worker['government_alerts'] = [];
                $worker['government_deploy_blocked'] = false;
            }
            echo json_encode([
                'success' => true,
                'data' => [
                    'workers' => [$worker]
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Worker not found'
            ]);
        }
            exit;
        }
        
        $offset = ($page - 1) * $limit;
        
        // EN: Build dynamic WHERE clause based on optional search/status filters.
        // AR: بناء شرط WHERE ديناميكي حسب فلاتر البحث والحالة الاختيارية.
        // Build WHERE conditions
        $whereConditions = ["w.status != 'deleted'"];
        $params = [];

        // Add search condition
        if ($search) {
            $whereConditions[] = "(w.worker_name LIKE ? OR w.id LIKE ? OR w.email LIKE ? OR w.passport_number LIKE ?)";
            array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
        }

        // Add status filter with mapping
        if ($status) {
            // Map user-friendly filter values to database values
            $statusMapping = [
                'active' => 'approved',    // 'active' filter maps to 'approved' in database
            'inactive' => 'inactive',  // 'inactive' filter maps to 'inactive' in database
            'pending' => 'pending',    // 'pending' filter maps to 'pending' in database
            'suspended' => 'suspended' // 'suspended' filter maps to 'suspended' in database
            ];
            
            $dbStatus = $statusMapping[$status] ?? $status;
            $whereConditions[] = "w.status = ?";
            $params[] = $dbStatus;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // EN: Count first to compute pagination metadata for frontend controls.
        // AR: حساب العدد الكلي أولاً لإرسال بيانات الترقيم للواجهة.
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM workers w WHERE $whereClause";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Build main query
        $query = "
            SELECT w.*, 
                   w.country,
                   a.agent_name,
                   s.subagent_name
            FROM workers w
            LEFT JOIN agents a ON w.agent_id = a.id
            LEFT JOIN subagents s ON w.subagent_id = s.id
            WHERE $whereClause
        ";

        // Add sorting and pagination
        $query .= " ORDER BY w.id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        // Get paginated data
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format response
        $response = [
            'success' => true,
            'data' => [
                'workers' => $workers,
                'pagination' => [
                    'total' => (int)$total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]
        ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Workers API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("Workers API Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Fatal error: " . $e->getMessage()
    ]);
}
?>