<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/documents/bulk-update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/documents/bulk-update.php`.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once '../../utils/response.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids']) || 
        empty($data['document_type']) || empty($data['status'])) {
        throw new Exception('Worker IDs, document type and status are required');
    }

    // Validate document type
    $validDocTypes = ['police', 'medical', 'visa', 'ticket', 'training_certificate', 'contract_signed', 'insurance', 'exit_permit'];
    if (!in_array($data['document_type'], $validDocTypes)) {
        throw new Exception('Invalid document type');
    }

    // Validate status
    $validStatuses = ['pending', 'approved', 'rejected', 'ok', 'not_ok'];
    if (!in_array($data['status'], $validStatuses)) {
        throw new Exception('Invalid status value');
    }

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

    // Convert to integers and validate
    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('No valid worker IDs provided');
    }

    // Get old data for history (before update)
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->execute($ids);
    $oldWorkers = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Update document statuses
    $sql = "UPDATE workers SET 
            {$data['document_type']}_status = ? 
            WHERE id IN ($placeholders)";
    
    $params = array_merge([$data['status']], $ids);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();
    
    // Log history for each updated worker
    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($ids as $workerId) {
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

    sendResponse([
        'success' => true,
        'message' => "$affected document status(es) updated successfully",
        'data' => [
            'updated_count' => $affected,
            'updated_ids' => $ids,
            'document_type' => $data['document_type'],
            'new_status' => $data['status']
        ]
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 