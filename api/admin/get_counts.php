<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_counts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_counts.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Get counts for settings cards
    $counts = [
        'visa_types' => $conn->query("SELECT COUNT(*) as count FROM visa_types")->fetch_assoc()['count'] ?? 0,
        'recruitment_professions' => $conn->query("SELECT COUNT(*) as count FROM recruitment_professions")->fetch_assoc()['count'] ?? 0,
        'age_specifications' => $conn->query("SELECT COUNT(*) as count FROM age_specifications")->fetch_assoc()['count'] ?? 0,
        'status_specifications' => $conn->query("SELECT COUNT(*) as count FROM status_specifications")->fetch_assoc()['count'] ?? 0,
        'arrival_stations' => $conn->query("SELECT COUNT(*) as count FROM arrival_stations")->fetch_assoc()['count'] ?? 0,
        'worker_statuses' => $conn->query("SELECT COUNT(*) as count FROM worker_statuses")->fetch_assoc()['count'] ?? 0,
        'system_config' => $conn->query("SELECT COUNT(*) as count FROM system_config")->fetch_assoc()['count'] ?? 0
    ];
    
    echo json_encode([
        'success' => true,
        'counts' => $counts
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading counts: ' . $e->getMessage()
    ]);
}
?> 