<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/auto-record-transaction.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/auto-record-transaction.php`.
 */
/**
 * Automatic Transaction Recorder API
 * Automatically creates accounting transactions when financial events occur
 * Supports: agents, subagents, workers, hr
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

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    enforceApiPermission('journal-entries', 'create');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['entity_type']) || empty($data['entity_id'])) {
        throw new Exception('Entity type and ID are required');
    }
    
    if (empty($data['transaction_type']) || empty($data['amount']) || empty($data['description'])) {
        throw new Exception('Transaction type, amount, and description are required');
    }
    
    $entityType = $data['entity_type'];
    $entityId = intval($data['entity_id']);
    $transactionType = ucfirst($data['transaction_type']); // Income or Expense
    $amount = floatval($data['amount']);
    $description = $data['description'];
    $category = $data['category'] ?? 'other';
    $referenceNumber = $data['reference_number'] ?? null;
    $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'Posted';
    $userId = $_SESSION['user_id'] ?? 1;
    
    // Validate entity exists
    $entityTableMap = [
        'agent' => 'agents',
        'subagent' => 'subagents',
        'worker' => 'workers',
        'hr' => 'hr_employees'
    ];
    
    if (!isset($entityTableMap[$entityType])) {
        throw new Exception('Invalid entity type');
    }
    
    $tableName = $entityTableMap[$entityType];
    $stmt = $conn->prepare("SELECT id FROM {$tableName} WHERE id = ?");
    $stmt->bind_param('i', $entityId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("{$entityType} with ID {$entityId} not found");
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert into financial_transactions
        $stmt = $conn->prepare("
            INSERT INTO financial_transactions (
                transaction_date, description, reference_number, 
                total_amount, transaction_type, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('sssdssi',
            $transactionDate,
            $description,
            $referenceNumber,
            $amount,
            $transactionType,
            $status,
            $userId
        );
        $stmt->execute();
        $transactionId = $conn->insert_id;
        
        // Insert into entity_transactions (linking table)
        $stmt = $conn->prepare("
            INSERT INTO entity_transactions (
                transaction_id, entity_type, entity_id, category
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param('isis',
            $transactionId,
            $entityType,
            $entityId,
            $category
        );
        $stmt->execute();
        $entityTransactionId = $conn->insert_id;
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Transaction recorded successfully',
            'transaction_id' => $transactionId,
            'entity_transaction_id' => $entityTransactionId,
            'data' => [
                'transaction_date' => $transactionDate,
                'description' => $description,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'category' => $category
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

