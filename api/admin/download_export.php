<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/download_export.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/download_export.php`.
 */
require_once '../../includes/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file specified']);
    exit;
}

$filename = $_GET['file'];
$filepath = '../../exports/' . $filename;

// Security check - only allow .json files
if (!preg_match('/^ratibprogram_export_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid filename']);
    exit;
}

if (!file_exists($filepath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Export file not found']);
    exit;
}

// Set headers for file download
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output file content
readfile($filepath);
exit;
?> 