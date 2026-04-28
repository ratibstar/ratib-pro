<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/transactions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/transactions.php`.
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
    // Ensure currency column exists in financial_transactions table
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if ($tableCheck->num_rows > 0) {
        $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
        if ($columnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
        }
    }
    
    // Check if requesting next reference number
    if (isset($_GET['action']) && $_GET['action'] === 'get_next_ref') {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if ($tableCheck->num_rows === 0) {
            // Return default if table doesn't exist
            echo json_encode([
                'success' => true,
                'next_reference' => 'REF-00000001'
            ]);
            exit;
        }
        
        // Get the highest reference number (not just the latest by ID, but by number value)
        $refStmt = $conn->prepare("SELECT reference_number FROM financial_transactions WHERE reference_number IS NOT NULL AND reference_number LIKE 'REF-%' ORDER BY CAST(SUBSTRING(reference_number, 5) AS UNSIGNED) DESC LIMIT 1");
        $refStmt->execute();
        $refResult = $refStmt->get_result();
        
        if ($refResult->num_rows > 0) {
            $lastRef = $refResult->fetch_assoc()['reference_number'];
            // Extract number from REF-XXXXXXXX format
            if (preg_match('/REF-(\d+)/', $lastRef, $matches)) {
                $nextNum = intval($matches[1]) + 1;
                $nextRef = 'REF-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
            } else {
                $nextRef = 'REF-00000001';
            }
        } else {
            $nextRef = 'REF-00000001';
        }
        
        echo json_encode([
            'success' => true,
            'next_reference' => $nextRef
        ]);
        exit;
    }
    
    // Check if financial_transactions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if ($tableCheck->num_rows === 0) {
            echo json_encode([
                'success' => true,
            'transactions' => [],
            'summary' => [
                'total_revenue' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'this_month' => 0,
                'transaction_count' => 0
            ],
            'message' => 'Financial transactions table not found. Please run database setup.'
        ]);
        exit;
    }
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $limit = max(1, min(10000, $limit)); // Clamp between 1 and 10000 to allow for large datasets
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $page = max(1, $page);
    $offset = ($page - 1) * $limit;
    $entityType = isset($_GET['entity_type']) ? trim(strtolower($_GET['entity_type'])) : null;
    $entityId = isset($_GET['entity_id']) ? intval($_GET['entity_id']) : null;
    
    // Normalize entity type - handle plural forms
    if ($entityType) {
        $entityType = strtolower($entityType);
        // Map plural forms to singular
        $entityTypeMap = [
            'agents' => 'agent',
            'subagents' => 'subagent',
            'workers' => 'worker',
            'hr' => 'hr'
        ];
        if (isset($entityTypeMap[$entityType])) {
            $entityType = $entityTypeMap[$entityType];
        }
    }

    // Check if entity_transactions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
    $hasEntityTable = $tableCheck->num_rows > 0;

    if ($hasEntityTable) {
        // Always join with entity_transactions to get entity data
        // Use GROUP BY to prevent duplicates if multiple entity_transactions exist
        if ($entityType || $entityId) {
            // When filters are applied, use INNER JOIN to get only matching transactions
            $query = "
                SELECT 
                    ft.id,
                    COALESCE(et.id, ft.id) as entity_transaction_id,
                    ft.transaction_date,
                    ft.description,
                    ft.transaction_type,
                    ft.total_amount,
                    COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as debit_amount,
                    COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as credit_amount,
                    COALESCE(ft.currency, 'SAR') as currency,
                    ft.status,
                    ft.reference_number,
                    u.username as created_by_name,
                    LOWER(et.entity_type) as entity_type,
                    et.entity_id,
                    et.category
                FROM financial_transactions ft
                INNER JOIN entity_transactions et ON ft.id = et.transaction_id
                LEFT JOIN users u ON ft.created_by = u.user_id
            ";

            $conditions = [];
            $params = [];
            $types = '';

            // Always filter by status - only show Posted/Approved transactions (exclude Draft)
            $conditions[] = "ft.status IN ('Posted', 'Approved')";

            if ($entityType) {
                // Use LOWER for case-insensitive matching
                $conditions[] = "LOWER(et.entity_type) = LOWER(?)";
                $params[] = $entityType;
                $types .= 's';
            }

            if ($entityId && $entityId > 0) {
                $conditions[] = "et.entity_id = ?";
                $params[] = $entityId;
                $types .= 'i';
            }

            $query .= " WHERE " . implode(' AND ', $conditions);
            $query .= " GROUP BY ft.id ORDER BY ft.transaction_date DESC, ft.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
        } else {
            // When no filters, show ALL transactions (including those without entity_transactions)
            $query = "
                SELECT 
                    ft.id,
                    COALESCE(et.id, ft.id) as entity_transaction_id,
                    ft.transaction_date,
                    ft.description,
                    ft.transaction_type,
                    ft.total_amount,
                    COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as debit_amount,
                    COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as credit_amount,
                    COALESCE(ft.currency, 'SAR') as currency,
                    ft.status,
                    ft.reference_number,
                    COALESCE(u.username, 'System') as created_by_name,
                    LOWER(COALESCE(et.entity_type, '')) as entity_type,
                    et.entity_id,
                    COALESCE(et.category, 'other') as category
                FROM financial_transactions ft
                LEFT JOIN entity_transactions et ON ft.id = et.transaction_id
                LEFT JOIN users u ON ft.created_by = u.user_id
                WHERE ft.status IN ('Posted', 'Approved')
                GROUP BY ft.id 
                ORDER BY ft.transaction_date DESC, ft.created_at DESC 
                LIMIT ? OFFSET ?
            ";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $conn->error);
            }
            $stmt->bind_param('ii', $limit, $offset);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Query execution failed: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Failed to get result set: ' . $conn->error);
        }
    } else {
        // Standard query without entity table (fallback)
        $query = "
            SELECT 
                ft.id,
                ft.id as entity_transaction_id,
                ft.transaction_date,
                ft.description,
                ft.transaction_type,
                ft.total_amount,
                COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as debit_amount,
                COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as credit_amount,
                COALESCE(ft.currency, 'SAR') as currency,
                ft.status,
                ft.reference_number,
                COALESCE(u.username, 'System') as created_by_name,
                NULL as entity_type,
                NULL as entity_id,
                'other' as category
            FROM financial_transactions ft
            LEFT JOIN users u ON ft.created_by = u.user_id
            ORDER BY ft.transaction_date DESC, ft.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Query preparation failed: ' . $conn->error);
        }
        $stmt->bind_param('ii', $limit, $offset);
        if (!$stmt->execute()) {
            throw new Exception('Query execution failed: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception('Failed to get result set: ' . $conn->error);
        }
    }
                
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure entity_type is lowercase for consistency
        if (isset($row['entity_type']) && $row['entity_type'] !== null && $row['entity_type'] !== '') {
            $row['entity_type'] = strtolower($row['entity_type']);
        } else {
            $row['entity_type'] = null;
        }
        // Ensure all required fields have defaults
        $row['entity_id'] = $row['entity_id'] ?? null;
        $row['category'] = $row['category'] ?? 'other';
        $transactions[] = $row;
    }
    
    // Log for debugging (remove in production)
    error_log("Transactions API: Returning " . count($transactions) . " transactions. HasEntityTable: " . ($hasEntityTable ? 'yes' : 'no') . ", Limit: $limit, EntityType: " . ($entityType ?? 'null') . ", EntityId: " . ($entityId ?? 'null'));
    if (count($transactions) === 0) {
        // Check if there are any transactions at all
        $countStmt = $conn->query("SELECT COUNT(*) as total FROM financial_transactions");
        if ($countStmt) {
            $countRow = $countStmt->fetch_assoc();
            error_log("Total transactions in database: " . $countRow['total']);
        }
        // Check if there are transactions for this entity type
        if ($entityType && $hasEntityTable) {
            $entityCountStmt = $conn->prepare("SELECT COUNT(*) as total FROM entity_transactions WHERE LOWER(entity_type) = LOWER(?)");
            $entityCountStmt->bind_param('s', $entityType);
            $entityCountStmt->execute();
            $entityCountResult = $entityCountStmt->get_result();
            if ($entityCountResult) {
                $entityCountRow = $entityCountResult->fetch_assoc();
                error_log("Transactions for entity_type '$entityType': " . $entityCountRow['total']);
            }
            // Check for specific entity if ID is provided
            if ($entityId && $entityId > 0) {
                $specificEntityStmt = $conn->prepare("SELECT COUNT(*) as total FROM entity_transactions WHERE LOWER(entity_type) = LOWER(?) AND entity_id = ?");
                $specificEntityStmt->bind_param('si', $entityType, $entityId);
                $specificEntityStmt->execute();
                $specificEntityResult = $specificEntityStmt->get_result();
                if ($specificEntityResult) {
                    $specificEntityRow = $specificEntityResult->fetch_assoc();
                    error_log("Transactions for entity_type '$entityType' and entity_id $entityId: " . $specificEntityRow['total']);
                }
            }
        }
    }
    
    $totalCount = 0;
    $countResult = $conn->query("SELECT COUNT(*) as total FROM financial_transactions");
    if ($countResult) {
        $totalCount = intval($countResult->fetch_assoc()['total']);
    }
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'count' => count($transactions),
        'hasEntityTable' => $hasEntityTable,
        'limit' => $limit,
        'page' => $page,
        'offset' => $offset,
        'total_count' => $totalCount,
        'filters' => [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ],
        'debug' => [
            'query_executed' => true,
            'result_rows' => $result->num_rows ?? 0
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transactions: ' . $e->getMessage()
    ]);
}
?>
