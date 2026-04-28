<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/get-entity-options.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/get-entity-options.php`.
 */
/**
 * Get Entity Options API
 * Returns all available entities (agents, subagents, workers, HR) for dropdowns
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/unified-entity-linking.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $entityType = isset($_GET['type']) ? trim($_GET['type']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    $entities = getEntityOptions($conn, $entityType);
    
    // Apply search filter if provided
    if ($search) {
        $searchLower = strtolower($search);
        $entities = array_filter($entities, function($entity) use ($searchLower) {
            return stripos(strtolower($entity['name']), $searchLower) !== false ||
                   stripos(strtolower($entity['type_label']), $searchLower) !== false;
        });
        $entities = array_values($entities); // Re-index array
    }
    
    echo json_encode([
        'success' => true,
        'entities' => $entities,
        'count' => count($entities)
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

