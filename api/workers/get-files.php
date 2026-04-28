<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get-files.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get-files.php`.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$documentType = $_GET['type'] ?? '';

if (empty($documentType)) {
    echo json_encode(['success' => false, 'message' => 'Document type required']);
    exit;
}

try {
    $files = [];
    
    // Check specific subdirectory for the document type
    $targetDir = __DIR__ . '/../../uploads/documents/' . $documentType . '/';
    if (is_dir($targetDir)) {
        $dirFiles = scandir($targetDir);
        foreach ($dirFiles as $file) {
            if ($file !== '.' && $file !== '..' && !is_dir($targetDir . $file)) {
                $files[] = [
                    'name' => $file,
                    'path' => $documentType . '/' . $file,
                    'type' => $documentType
                ];
            }
        }
    }
    
    // If no files found in specific directory, check main directory
    if (empty($files)) {
        $mainDir = __DIR__ . '/../../uploads/documents/';
        if (is_dir($mainDir)) {
            $mainFiles = scandir($mainDir);
            foreach ($mainFiles as $file) {
                if ($file !== '.' && $file !== '..' && !is_dir($mainDir . $file)) {
                    $files[] = [
                        'name' => $file,
                        'path' => $file,
                        'type' => 'main'
                    ];
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'files' => $files,
        'total' => count($files),
        'documentType' => $documentType
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
