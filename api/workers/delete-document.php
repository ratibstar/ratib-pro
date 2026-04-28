<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/delete-document.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/delete-document.php`.
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['fileName'])) {
    echo json_encode(['success' => false, 'message' => 'Missing file name']);
    exit;
}

$fileName = $input['fileName'];
$documentType = $input['documentType'] ?? '';

try {
    // Determine the correct file path
    $actualPath = '';
    $deleted = false;
    
    // Check main directory first
    $mainPath = __DIR__ . '/../../uploads/documents/' . $fileName;
    if (file_exists($mainPath)) {
        $actualPath = $mainPath;
        if (unlink($mainPath)) {
            $deleted = true;
        }
    }
    
    // If not found in main directory, check subdirectories
    if (!$deleted) {
        $subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket'];
        foreach ($subdirs as $subdir) {
            $subPath = __DIR__ . '/../../uploads/documents/' . $subdir . '/' . $fileName;
            if (file_exists($subPath)) {
                $actualPath = $subPath;
                if (unlink($subPath)) {
                    $deleted = true;
                    break;
                }
            }
        }
    }
    
    if ($deleted) {
        echo json_encode(['success' => true, 'message' => 'File deleted successfully', 'path' => $actualPath]);
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found or could not be deleted', 'searched' => $fileName, 'documentType' => $documentType]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
