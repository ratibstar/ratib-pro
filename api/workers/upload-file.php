<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/upload-file.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/upload-file.php`.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit;
}

if (!isset($_FILES['file']) || !isset($_POST['documentType'])) {
    echo json_encode(['success' => false, 'message' => 'File and document type required']);
    exit;
}

$file = $_FILES['file'];
$documentType = $_POST['documentType'];

// Normalize document type for safe filesystem paths
$documentType = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$documentType);
if ($documentType === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid document type']);
    exit;
}

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error: ' . $file['error']]);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images and PDFs allowed']);
    exit;
}

// Validate file size (5MB max)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum 5MB allowed']);
    exit;
}

try {
    // Create upload directory if it doesn't exist
    $uploadRoot = realpath(__DIR__ . '/../../uploads');
    if ($uploadRoot === false) {
        $uploadRoot = __DIR__ . '/../../uploads';
        if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload root directory']);
            exit;
        }
    }
    $uploadDir = rtrim($uploadRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $documentType . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit;
        }
    }
    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . $documentType . '_' . uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath) || (@copy($file['tmp_name'], $filePath) && @unlink($file['tmp_name']))) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'fileName' => $fileName,
            'filePath' => $filePath,
            'documentType' => $documentType
        ]);
    } else {
        $lastError = error_get_last();
        $detail = is_array($lastError) && isset($lastError['message']) ? (' - ' . $lastError['message']) : '';
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file' . $detail]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
