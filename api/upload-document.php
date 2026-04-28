<?php
/**
 * EN: Handles API endpoint/business logic in `api/upload-document.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/upload-document.php`.
 */
header('Content-Type: application/json');

// Set upload directory
$uploadDir = __DIR__ . '/documents/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    if (!isset($_FILES['document'])) {
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['document'];
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'filename' => $filename
        ]);
    } else {
        throw new Exception('Failed to move uploaded file');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 