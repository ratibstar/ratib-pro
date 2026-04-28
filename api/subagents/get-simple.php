<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/get-simple.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/get-simple.php`.
 */
// Simplified subagents API for cases form
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: log only
ini_set('log_errors', 1);

header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../Utils/response.php';

    $db = new Database();
    $conn = $db->getConnection();

    // Get agent_id parameter if provided
    $agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;

    // Build query - use basic column names
    $query = "SELECT 
        s.id as subagent_id,
        s.id as formatted_id,
        s.subagent_name as full_name,
        s.agent_id,
        s.status,
        s.created_at
        FROM subagents s
        WHERE s.status = 'active'";

    $params = [];

    // Add agent filter if provided
    if ($agentId) {
        $query .= " AND s.agent_id = ?";
        $params[] = $agentId;
    }

    $query .= " ORDER BY s.id DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $subagents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'data' => $subagents
    ];

    sendResponse($response);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>