<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/upload-document.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/upload-document.php`.
 */
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/response.php';

// Enable error logging
error_log("====== UPLOAD-DOCUMENT.PHP START ======");

try {
    // Check if the required fields are provided
    if (empty($_POST['id']) || empty($_POST['document_type']) || empty($_FILES['file'])) {
        throw new Exception('Worker ID, document type and file are required');
    }

    $workerId = intval($_POST['id']);
    $documentType = $_POST['document_type'];
    $file = $_FILES['file'];

    error_log("Processing upload for id: $workerId, document_type: $documentType");

    // Validate document type
    $validDocTypes = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket'];
    if (!in_array($documentType, $validDocTypes)) {
        throw new Exception('Invalid document type');
    }

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code: ' . $file['error']);
    }

    // Check file size (limit to 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size exceeds the limit (10MB)');
    }

    // Validate file type
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $fileMimeType = mime_content_type($file['tmp_name']);
    
    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        throw new Exception('Invalid file type. Allowed types: PDF, JPEG, PNG');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = '../../uploads/documents/' . $documentType . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate a unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = $workerId . '_' . $documentType . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $newFileName;

    error_log("Generated filename: $newFileName");
    error_log("File path: $filePath");

    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }

    error_log("File moved successfully to: $filePath");

    // Connect to database
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get old data for history (before update)
    $oldStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
    $oldStmt->execute([$workerId]);
    $oldWorker = $oldStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldWorker) {
        unlink($filePath);
        throw new Exception("Worker $workerId not found in database");
    }

    // SIMPLIFIED APPROACH: Just try the update directly
    $sql = "UPDATE workers SET 
            {$documentType}_file = ?,
            {$documentType}_status = 'pending'
            WHERE id = ?";
    
    error_log("SQL Query: $sql");
    error_log("Parameters: filename=$newFileName, id=$workerId");
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$newFileName, $workerId]);

    if (!$result) {
        unlink($filePath);
        throw new Exception('Database update failed: ' . $stmt->errorInfo()[2]);
    }

    $rowCount = $stmt->rowCount();
    error_log("Database execute successful, rowCount: $rowCount");

    if ($rowCount === 0) {
        // If no rows affected, check if worker exists
        $checkSql = "SELECT id FROM workers WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->execute([$workerId]);
        
        if ($checkStmt->rowCount() === 0) {
            error_log("Worker $workerId not found in database");
            unlink($filePath);
            throw new Exception("Worker $workerId not found in database");
        } else {
            error_log("Worker $workerId exists but update failed - possible duplicate filename");
            // Maybe the filename already exists, try with a different timestamp
            $newFileName2 = $workerId . '_' . $documentType . '_' . (time() + 1) . '.' . $fileExtension;
            $filePath2 = $uploadDir . $newFileName2;
            
            // Rename the file
            if (rename($filePath, $filePath2)) {
                error_log("Renamed file to: $newFileName2");
                
                // Try update again with new filename
                $stmt2 = $conn->prepare($sql);
                $result2 = $stmt2->execute([$newFileName2, $workerId]);
                
                if ($result2 && $stmt2->rowCount() > 0) {
                    error_log("Second attempt successful with filename: $newFileName2");
                    $newFileName = $newFileName2;
                    $filePath = $filePath2;
                } else {
                    error_log("Second attempt also failed");
                    unlink($filePath2);
                    throw new Exception("Database update failed after retry");
                }
            } else {
                unlink($filePath);
                throw new Exception("Failed to rename file and retry update");
            }
        }
    }

    error_log("Database update successful - $newFileName saved for worker $workerId");
    
    // Get updated worker for history
    $newStmt = $conn->prepare("SELECT * FROM workers WHERE id = ?");
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

    // Return success response with file info
    sendResponse([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'data' => [
            'document_type' => $documentType,
            'file_name' => $newFileName,
            'file_path' => str_replace('../../', '/', $filePath)
        ]
    ]);

} catch (Exception $e) {
    error_log("Upload failed with error: " . $e->getMessage());
    error_log("====== UPLOAD-DOCUMENT.PHP END (WITH ERROR) ======");
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 