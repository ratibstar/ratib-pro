<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get-simple.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get-simple.php`.
 */
// Simplified workers API for cases form
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: log only
ini_set('log_errors', 1);

header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/ApiResponse.php';

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get status filter
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    // Build query - use basic column names
    $query = "SELECT 
        w.id,
        w.id as formatted_id,
        w.full_name,
        w.status,
        w.created_at
        FROM workers w
        WHERE 1=1";

    $params = [];

    // Add status filter if provided
    if ($status) {
        $query .= " AND w.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY COALESCE(w.id, w.worker_id) DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response to match what JavaScript expects
    $data = [
        'workers' => $workers
    ];

    echo ApiResponse::success($data);

} catch (Exception $e) {
    error_log("Workers get-simple API Error: " . $e->getMessage());
    echo ApiResponse::error($e->getMessage(), 500);
}
?>
