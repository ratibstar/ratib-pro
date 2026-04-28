<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/utils/search.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/utils/search.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    $query = isset($_GET['q']) ? $_GET['q'] : '';
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    if (empty($query)) {
        throw new Exception("Search query is required");
    }
    
    $query = "%$query%";
    
    $sql = "SELECT DISTINCT 
            CASE 
                WHEN formatted_id LIKE ? THEN formatted_id
                WHEN full_name LIKE ? THEN full_name
                WHEN identity_number LIKE ? THEN identity_number
                WHEN passport_number LIKE ? THEN passport_number
                WHEN nationality LIKE ? THEN nationality
            END as suggestion,
            'worker' as type
            FROM workers 
            WHERE formatted_id LIKE ? 
            OR full_name LIKE ? 
            OR identity_number LIKE ?
            OR passport_number LIKE ?
            OR nationality LIKE ?
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssssssss', 
        $query, $query, $query, $query, $query,
        $query, $query, $query, $query, $query
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    $suggestions = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $suggestions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 