<?php
/**
 * EN: Handles API endpoint/business logic in `api/reports/test-connection.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/reports/test-connection.php`.
 */
/**
 * Test Connection API
 * Simple endpoint to test database connection
 */

header('Content-Type: application/json');

session_start();

try {
    // Try using config database first
    require_once(__DIR__ . '/../../config/database.php');
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception('Failed to get database connection');
    }
    
    // Test with a simple query
    $stmt = $conn->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Query test failed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

