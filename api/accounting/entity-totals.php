<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entity-totals.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entity-totals.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions
enforceApiPermission('journal-entries', 'view');

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_totals'");
    if ($tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'totals' => [],
            'message' => 'Entity totals table not found. Totals will be created automatically when transactions are added.'
        ]);
        exit;
    }
    
    $entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
    $entityId = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : 0;
    
    $query = "SELECT * FROM entity_totals";
    $conditions = [];
    $params = [];
    $types = '';
    
    if ($entityType) {
        $conditions[] = "entity_type = ?";
        $params[] = $entityType;
        $types .= 's';
    }
    
    if ($entityId > 0) {
        $conditions[] = "entity_id = ?";
        $params[] = $entityId;
        $types .= 'i';
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $query .= " ORDER BY entity_type, entity_id";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totals = [];
    while ($row = $result->fetch_assoc()) {
        $totals[] = [
            'id' => intval($row['id']),
            'entity_type' => $row['entity_type'],
            'entity_id' => intval($row['entity_id']),
            'total_debit' => floatval($row['total_debit'] ?? 0),
            'total_credit' => floatval($row['total_credit'] ?? 0),
            'total_income' => floatval($row['total_income'] ?? 0),
            'total_expenses' => floatval($row['total_expenses'] ?? 0),
            'net_balance' => floatval($row['net_balance'] ?? 0),
            'transaction_count' => intval($row['transaction_count'] ?? 0),
            'last_updated' => $row['last_updated'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'totals' => $totals
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

