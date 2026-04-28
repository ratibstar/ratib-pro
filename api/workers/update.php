<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/update.php`.
 */
require_once __DIR__ . '/../../core/Database.php';
require_once '../../utils/response.php';

try {
    if (empty($_FILES['document']) || empty($_POST['id']) || empty($_POST['document_type'])) {
        throw new Exception('Document file, worker ID and document type are required');
    }

    // Validate document type
    $validDocTypes = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket'];
    if (!in_array($_POST['document_type'], $validDocTypes)) {
        throw new Exception('Invalid document type');
    }

    $file = $_FILES['document'];
    $workerId = (int)$_POST['id'];
    $docType = $_POST['document_type'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG and PDF allowed');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = "../../uploads/workers/{$workerId}/documents/{$docType}/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload file');
    }

    // Update database
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get old data for history (before update)
    $fetchStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
    $fetchStmt->execute([$workerId]);
    $oldWorker = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldWorker) {
        throw new Exception('Worker not found');
    }

    $sql = "UPDATE workers SET 
            {$docType}_file = ?,
            {$docType}_status = 'pending'
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$filename, $workerId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Worker not found');
    }
    
    // Get updated worker for history
    $fetchStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
    $fetchStmt->execute([$workerId]);
    $updatedWorker = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
    // Log history
    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (file_exists($helperPath) && $oldWorker && $updatedWorker) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $updatedWorker);
        }
    }

    sendResponse([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'data' => [
            'filename' => $filename,
            'path' => "/uploads/workers/{$workerId}/documents/{$docType}/{$filename}"
        ]
    ]);

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 