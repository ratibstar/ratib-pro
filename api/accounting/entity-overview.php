<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entity-overview.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entity-overview.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
    $entityId = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : null;

    $data = [];

    // Check if entity_transactions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
    $useEntityTransactions = $tableCheck->num_rows > 0;

    if ($useEntityTransactions) {
        // Get overview by entity type
        $query = "
            SELECT 
                et.entity_type,
                COUNT(DISTINCT et.entity_id) as entity_count,
                COUNT(et.id) as transaction_count,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Income' AND ft.status IN ('Approved', 'Posted') THEN ft.total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Expense' AND ft.status IN ('Approved', 'Posted') THEN ft.total_amount ELSE 0 END), 0) as total_expenses,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Income' AND ft.status IN ('Approved', 'Posted') AND ft.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ft.total_amount ELSE 0 END), 0) as revenue_30d,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Expense' AND ft.status IN ('Approved', 'Posted') AND ft.transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ft.total_amount ELSE 0 END), 0) as expenses_30d
            FROM entity_transactions et
            INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        ";

        $conditions = [];
        $params = [];
        $types = '';

        if ($entityType) {
            $conditions[] = "et.entity_type = ?";
            $params[] = $entityType;
            $types .= 's';
        }

        if ($entityId) {
            $conditions[] = "et.entity_id = ?";
            $params[] = $entityId;
            $types .= 'i';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " GROUP BY et.entity_type";

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $byEntityType = [];
        while ($row = $result->fetch_assoc()) {
            $byEntityType[$row['entity_type']] = [
                'entity_count' => intval($row['entity_count']),
                'transaction_count' => intval($row['transaction_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'total_expenses' => floatval($row['total_expenses']),
                'net_profit' => floatval($row['total_revenue']) - floatval($row['total_expenses']),
                'revenue_30d' => floatval($row['revenue_30d']),
                'expenses_30d' => floatval($row['expenses_30d']),
                'net_profit_30d' => floatval($row['revenue_30d']) - floatval($row['expenses_30d'])
            ];
        }

        // Get top entities by revenue with entity names
        $topEntitiesQuery = "
            SELECT 
                et.entity_type,
                et.entity_id,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Income' AND ft.status IN ('Approved', 'Posted') THEN ft.total_amount ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN ft.transaction_type = 'Expense' AND ft.status IN ('Approved', 'Posted') THEN ft.total_amount ELSE 0 END), 0) as total_expenses,
                CASE 
                    WHEN et.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = et.entity_id LIMIT 1)
                    WHEN et.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = et.entity_id LIMIT 1)
                    WHEN et.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = et.entity_id LIMIT 1)
                    WHEN et.entity_type = 'hr' THEN (SELECT employee_name FROM hr_employees WHERE id = et.entity_id LIMIT 1)
                    WHEN et.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = et.entity_id LIMIT 1)
                    ELSE NULL
                END as entity_name
            FROM entity_transactions et
            INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        ";

        if (!empty($conditions)) {
            $topEntitiesQuery .= " WHERE " . implode(' AND ', $conditions);
        }

        $topEntitiesQuery .= " GROUP BY et.entity_type, et.entity_id ORDER BY total_revenue DESC LIMIT 10";

        $stmt = $conn->prepare($topEntitiesQuery);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $topEntities = [];
        while ($row = $result->fetch_assoc()) {
            $topEntities[] = [
                'entity_type' => $row['entity_type'],
                'entity_id' => intval($row['entity_id']),
                'entity_name' => $row['entity_name'] ?? null, // Include entity name if available
                'total_revenue' => floatval($row['total_revenue']),
                'total_expenses' => floatval($row['total_expenses']),
                'net_profit' => floatval($row['total_revenue']) - floatval($row['total_expenses'])
            ];
        }

        $data['by_entity_type'] = $byEntityType;
        $data['top_entities'] = $topEntities;
    } else {
        // Return empty structure if table doesn't exist
        $data['by_entity_type'] = [];
        $data['top_entities'] = [];
        $data['message'] = 'Entity transactions table not found. Please run the database schema.';
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching entity overview: ' . $e->getMessage()
    ]);
}
?>
