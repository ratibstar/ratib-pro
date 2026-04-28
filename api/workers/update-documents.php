<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/update-documents.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/update-documents.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('workers', 'documents');

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1); // Log errors to error log

// Start session
session_start();

// Set content type
header('Content-Type: application/json');

// Clear any previous output
if (ob_get_length()) ob_clean();

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get worker ID
$workerId = $_POST['worker_id'] ?? null;
if (!$workerId) {
    echo json_encode(['success' => false, 'message' => 'Worker ID is required']);
    exit;
}

try {
    // Load config
    require_once __DIR__ . '/../../includes/config.php';
    
    // Database connection using config constants
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare update data
    $updateFields = [];
    $updateValues = [];
    
    // Check which columns exist in the workers table
    $stmt = $pdo->prepare("DESCRIBE workers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // All possible document fields - ensure status fields are included
    $allDocumentFields = [
        'identity_number', 'identity_date', 'identity_status',
        'passport_number', 'passport_date', 'passport_status',
        'police_number', 'police_date', 'police_status',
        'medical_number', 'medical_date', 'medical_status',
        'visa_number', 'visa_date', 'visa_status',
        'ticket_number', 'ticket_date', 'ticket_status',
        'training_certificate_number', 'training_certificate_date', 'training_certificate_status',
        'contract_signed_number', 'contract_signed_status',
        'insurance_number', 'insurance_status',
        'exit_permit_number', 'exit_permit_status',
        'medical_center_name', 'gov_approval_status', 'approval_reference_id'
    ];
    
    // Force include status fields even if they don't exist in columns yet
    $statusFields = ['identity_status', 'passport_status', 'police_status', 'medical_status', 'visa_status', 'ticket_status', 'training_certificate_status', 'contract_signed_status', 'insurance_status', 'exit_permit_status'];
    foreach ($statusFields as $statusField) {
        if (!in_array($statusField, $allDocumentFields)) {
            $allDocumentFields[] = $statusField;
        }
    }
    
    // Check if date columns exist, if not create them
    $dateColumns = ['identity_date', 'passport_date', 'police_date', 'medical_date', 'visa_date', 'ticket_date', 'training_certificate_date'];
    foreach ($dateColumns as $dateColumn) {
        if (!in_array($dateColumn, $columns)) {
            try {
                $alterStmt = $pdo->prepare("ALTER TABLE workers ADD COLUMN $dateColumn DATE NULL");
                $alterStmt->execute();
                error_log("Added column: $dateColumn");
            } catch (PDOException $e) {
                error_log("Failed to add column $dateColumn: " . $e->getMessage());
            }
        }
    }
    
    // Check if status columns exist, if not create them
    $statusColumns = ['identity_status', 'passport_status', 'police_status', 'medical_status', 'visa_status', 'ticket_status', 'training_certificate_status'];
    foreach ($statusColumns as $statusColumn) {
        if (!in_array($statusColumn, $columns)) {
            try {
                $alterStmt = $pdo->prepare("ALTER TABLE workers ADD COLUMN $statusColumn VARCHAR(20) DEFAULT 'pending'");
                $alterStmt->execute();
                error_log("Added column: $statusColumn");
            } catch (PDOException $e) {
                error_log("Failed to add column $statusColumn: " . $e->getMessage());
            }
        }
    }

    // Check if training certificate document columns exist, if not create them
    $trainingColumns = [
        'training_certificate_number' => 'VARCHAR(100) NULL',
        'training_certificate_file' => 'VARCHAR(255) NULL',
        'contract_signed_number' => 'VARCHAR(100) NULL',
        'contract_signed_status' => "VARCHAR(20) DEFAULT 'pending'",
        'contract_signed_file' => 'VARCHAR(255) NULL',
        'insurance_number' => 'VARCHAR(100) NULL',
        'insurance_status' => "VARCHAR(20) DEFAULT 'pending'",
        'insurance_file' => 'VARCHAR(255) NULL',
        'exit_permit_number' => 'VARCHAR(100) NULL',
        'exit_permit_status' => "VARCHAR(20) DEFAULT 'pending'",
        'exit_permit_file' => 'VARCHAR(255) NULL',
        'medical_center_name' => 'VARCHAR(255) NULL',
        'gov_approval_status' => "VARCHAR(30) DEFAULT 'pending'",
        'approval_reference_id' => 'VARCHAR(100) NULL',
    ];
    foreach ($trainingColumns as $trainingColumn => $definition) {
        if (!in_array($trainingColumn, $columns)) {
            try {
                $alterStmt = $pdo->prepare("ALTER TABLE workers ADD COLUMN $trainingColumn $definition");
                $alterStmt->execute();
                error_log("Added column: $trainingColumn");
            } catch (PDOException $e) {
                error_log("Failed to add column $trainingColumn: " . $e->getMessage());
            }
        }
    }
    
    // Refresh columns list after potential additions
    $stmt = $pdo->prepare("DESCRIBE workers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Process all document fields that exist in POST data
    error_log("All document fields: " . print_r($allDocumentFields, true));
    error_log("Available columns: " . print_r($columns, true));
    error_log("POST data: " . print_r($_POST, true));
    
    // Check if we have any fields to update
    if (empty($updateFields)) {
        error_log("No fields to update - this might be the issue");
    }
    
    foreach ($allDocumentFields as $field) {
        if (isset($_POST[$field]) && in_array($field, $columns)) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $_POST[$field];
            error_log("Adding field to update: $field = " . $_POST[$field]);
        } else if (isset($_POST[$field])) {
            error_log("Field in POST but not in database: $field = " . $_POST[$field]);
        } else {
            error_log("Field not in POST: $field");
        }
    }
    
    // Force add identity_status if it's in POST
    if (isset($_POST['identity_status']) && !in_array('identity_status = ?', $updateFields)) {
        $updateFields[] = "identity_status = ?";
        $updateValues[] = $_POST['identity_status'];
        error_log("Force adding identity_status: " . $_POST['identity_status']);
    }
    
    // Handle file uploads
    $uploadDir = '../uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileFields = [
        'identity_file', 'passport_file', 'police_file',
        'medical_file', 'visa_file', 'ticket_file', 'training_certificate_file',
        'contract_signed_file', 'insurance_file', 'exit_permit_file'
    ];
    
    foreach ($fileFields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $fileName = time() . '_' . basename($file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $fileName;
            }
        }
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'No data to update']);
        exit;
    }
    
    // Get old data for history (before update)
    $oldStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
    $oldStmt->execute([$workerId]);
    $oldWorker = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldWorker) {
        echo json_encode(['success' => false, 'message' => 'Worker not found']);
        exit;
    }
    
    // Add worker ID to values
    $updateValues[] = $workerId;
    
    // Build and execute update query
    $sql = "UPDATE workers SET " . implode(', ', $updateFields) . " WHERE id = ?";
    error_log("Available columns: " . print_r($columns, true));
    error_log("Document fields to update: " . print_r($documentFields, true));
    error_log("POST data received: " . print_r($_POST, true));
    error_log("SQL Query: " . $sql);
    error_log("Update Values: " . print_r($updateValues, true));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($updateValues);
    
    if ($result) {
        // Get updated worker for history
        $newStmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
        $newStmt->execute([$workerId]);
        $updatedWorker = $newStmt->fetch(PDO::FETCH_ASSOC);
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldWorker && $updatedWorker) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $updatedWorker);
            }
        }
        
        // Verify the update by fetching the updated data
        $verifyStmt = $pdo->prepare("SELECT identity_status, identity_date, passport_status, passport_date FROM workers WHERE id = ?");
        $verifyStmt->execute([$workerId]);
        $updatedData = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Updated data verification: " . print_r($updatedData, true));
        
        echo json_encode([
            'success' => true,
            'message' => 'Documents updated successfully',
            'data' => [
                'worker_id' => $workerId,
                'updated_fields' => count($updateFields),
                'verification' => $updatedData
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update documents']);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Values: " . print_r($updateValues, true));
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating documents: ' . $e->getMessage()]);
}
?>
