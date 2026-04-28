<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/bulk-update-documents.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/bulk-update-documents.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('workers', 'documents');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/response.php';

try {
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields (accept both 'ids' and 'worker_ids')
    $workerIdsRaw = $data['ids'] ?? $data['worker_ids'] ?? null;
    if (empty($workerIdsRaw) || !is_array($workerIdsRaw) || 
        empty($data['document_type']) || empty($data['status'])) {
        throw new Exception('Worker IDs, document type and status are required');
    }

    // Validate document type
    $validDocTypes = ['police', 'medical', 'visa', 'ticket', 'training_certificate', 'contract_signed', 'insurance', 'exit_permit'];
    if (!in_array($data['document_type'], $validDocTypes)) {
        throw new Exception('Invalid document type');
    }

    // Status mapping - use ok/not_ok to match workers table and stats (approved/rejected would not persist correctly)
    $statusMap = [
        'pending' => 'pending',
        'ok' => 'ok',
        'not_ok' => 'not_ok',
        'approved' => 'ok',
        'rejected' => 'not_ok'
    ];

    // Validate and map status
    if (!isset($statusMap[$data['status']])) {
        throw new Exception('Invalid status value');
    }
    $status = $statusMap[$data['status']];

    // Connect to database
    $db = Database::getInstance();
    $conn = $db->getConnection();

    if (in_array($data['document_type'], ['training_certificate', 'contract_signed', 'insurance', 'exit_permit'], true)) {
        $trainingColumns = [
            'training_certificate_status' => "VARCHAR(20) DEFAULT 'pending'",
            'training_certificate_number' => 'VARCHAR(100) NULL',
            'training_certificate_date' => 'DATE NULL',
            'training_certificate_file' => 'VARCHAR(255) NULL',
            'contract_signed_status' => "VARCHAR(20) DEFAULT 'pending'",
            'contract_signed_number' => 'VARCHAR(100) NULL',
            'contract_signed_file' => 'VARCHAR(255) NULL',
            'insurance_status' => "VARCHAR(20) DEFAULT 'pending'",
            'insurance_number' => 'VARCHAR(100) NULL',
            'insurance_file' => 'VARCHAR(255) NULL',
            'exit_permit_status' => "VARCHAR(20) DEFAULT 'pending'",
            'exit_permit_number' => 'VARCHAR(100) NULL',
            'exit_permit_file' => 'VARCHAR(255) NULL',
        ];
        foreach ($trainingColumns as $column => $definition) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM workers WHERE Field = " . $conn->quote($column));
                if (!$checkColumn || !$checkColumn->fetch(PDO::FETCH_ASSOC)) {
                    $conn->exec("ALTER TABLE workers ADD COLUMN {$column} {$definition}");
                }
            } catch (Exception $e) {
                error_log("Failed to ensure worker training certificate column {$column}: " . $e->getMessage());
            }
        }
    }

    // Clean worker IDs array (ensure they're integers)
    $workerIds = array_map('intval', $workerIdsRaw);
    $workerIds = array_filter($workerIds); // Remove any zero values
    
    if (empty($workerIds)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
    
    // Get old data for history (before update)
    $fetchPlaceholders = implode(',', array_fill(0, count($workerIds), '?'));
    $fetchSql = "SELECT * FROM workers WHERE id IN ($fetchPlaceholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($workerIds);
    $oldWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare and execute update query
    $sql = "UPDATE workers SET 
            {$data['document_type']}_status = ? 
            WHERE id IN ($placeholders)";
    
    // Prepare parameters (status first, then worker IDs)
    $params = array_merge([$status], $workerIds);
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $affected = $stmt->rowCount();
    
    // Log history for each updated worker
    $helperPath = __DIR__ . '/../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($workerIds as $workerId) {
                $oldWorker = null;
                foreach ($oldWorkers as $worker) {
                    if ($worker['id'] == $workerId) {
                        $oldWorker = $worker;
                        break;
                    }
                }
                
                $newStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
                $newStmt->execute([$workerId]);
                $newWorker = $newStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($oldWorker && $newWorker) {
                    @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $newWorker);
                }
            }
        }
    }

    // Map back the status for frontend
    $frontendStatusMap = [
        'pending' => 'pending',
        'approved' => 'ok',
        'rejected' => 'not_ok'
    ];

    $frontendStatus = $frontendStatusMap[$status] ?? $status;

    // Return success response
    sendResponse([
        'success' => true,
        'message' => "$affected document status(es) updated successfully",
        'data' => [
            'updated_count' => $affected,
            'document_type' => $data['document_type'],
            'status' => $frontendStatus
        ]
    ]);

} catch (Exception $e) {
    // Return error response
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 