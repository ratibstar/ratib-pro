<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/get-simple.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/get-simple.php`.
 */
// Simplified agents API for cases form
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: log only
ini_set('log_errors', 1);

header("Content-Type: application/json; charset=utf-8");

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../Utils/response.php';

    // Business endpoint: must run under tenant context.
    if (!function_exists('getCurrentTenantId')) {
        require_once __DIR__ . '/../../includes/helpers/get_tenant_db.php';
    }
    $tenantId = getCurrentTenantId();
    $conn = getTenantDB();

    // Build query - use basic column names
    $query = "SELECT 
        a.id as agent_id,
        a.id as formatted_id,
        a.agent_name as full_name,
        a.status,
        a.created_at
        FROM agents a
        WHERE a.status = 'active' AND a.tenant_id = :tenant_id
        ORDER BY a.id DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([':tenant_id' => $tenantId]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'data' => $agents
    ];

    sendResponse($response);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>