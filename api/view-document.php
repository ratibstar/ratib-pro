<?php
/**
 * EN: Handles API endpoint/business logic in `api/view-document.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/view-document.php`.
 */
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/database.php';
require_once '../Utils/response.php';

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Document ID is required');
    }
    
    // Simple test to see if API is working
    if ($_GET['id'] === 'test') {
        sendResponse([
            'success' => true,
            'message' => 'API is working',
            'test' => true
        ]);
    }

    $db = new Database();
    $conn = $db->getConnection();

    $documentId = $_GET['id'];

    $query = "SELECT * FROM hr_documents WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$documentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception('Document not found');
    }
    
    // Debug: Log the document data
    error_log("Document data: " . json_encode($document));

    // Check if file exists
    $filePath = '../' . $document['file_path'];
    
    // Debug: Log the file path and check if it exists
    error_log("Looking for file at: " . $filePath);
    error_log("File exists: " . (file_exists($filePath) ? 'YES' : 'NO'));
    
    // Try different possible paths
    require_once __DIR__ . '/../includes/config.php';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    $possiblePaths = [
        $filePath,
        '../uploads/documents/' . $document['file_name'],
        '../uploads/' . $document['file_name'],
        '../' . $document['file_name'],
        $document['file_path'],
        $baseUrl . '/uploads/documents/' . $document['file_name']
    ];
    
    $actualFilePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $actualFilePath = $path;
            error_log("Found file at: " . $path);
            break;
        }
    }
    
    if (!$actualFilePath) {
        error_log("File not found in any of these paths:");
        foreach ($possiblePaths as $path) {
            error_log("  - " . $path);
        }
        throw new Exception('Document file not found at: ' . $filePath);
    }

    // Get file info
    $fileInfo = pathinfo($actualFilePath);
    $mimeType = mime_content_type($actualFilePath);

    // Clean up the file path for web access
    $webPath = str_replace('../', '', $actualFilePath);
    $webPath = str_replace('\\', '/', $webPath); // Fix Windows paths
    
    sendResponse([
        'success' => true,
        'document' => [
            'id' => $document['id'],
            'title' => $document['title'],
            'file_name' => $document['file_name'],
            'file_path' => $webPath,
            'document_type' => $document['document_type'],
            'department' => $document['department'],
            'issue_date' => $document['issue_date'],
            'expiry_date' => $document['expiry_date'],
            'document_number' => $document['document_number'],
            'status' => $document['status'],
            'description' => $document['description'],
            'employee_name' => $document['employee_name'],
            'mime_type' => $mimeType,
            'file_size' => filesize($actualFilePath),
            'created_at' => $document['created_at']
        ]
    ]);

} catch (Exception $e) {
    error_log("View document error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} catch (Error $e) {
    error_log("View document PHP error: " . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ], 500);
} 