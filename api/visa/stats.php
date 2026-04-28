<?php
/**
 * EN: Handles API endpoint/business logic in `api/visa/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/visa/stats.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../config/database.php';
if (!class_exists('ApiResponse')) {
    require_once __DIR__ . '/../core/ApiResponse.php';
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo ApiResponse::error('Unauthorized');
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ];
    
    if ($conn !== null) {
        // Get total applications
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM visa_applications");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total'] = (int)($result['total'] ?? 0);

        // Get pending applications
        $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM visa_applications WHERE status = 'pending'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending'] = (int)($result['pending'] ?? 0);

        // Get approved applications
        $stmt = $conn->prepare("SELECT COUNT(*) as approved FROM visa_applications WHERE status = 'approved'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['approved'] = (int)($result['approved'] ?? 0);

        // Get rejected applications
        $stmt = $conn->prepare("SELECT COUNT(*) as rejected FROM visa_applications WHERE status = 'rejected'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['rejected'] = (int)($result['rejected'] ?? 0);
    }
    
    ob_clean();
    echo ApiResponse::success($stats);
    exit;
    
} catch (Exception $e) {
    error_log("Visa stats error: " . $e->getMessage());
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
}

