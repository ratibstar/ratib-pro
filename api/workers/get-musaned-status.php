<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/get-musaned-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/get-musaned-status.php`.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('workers', 'musaned');

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

try {
    if (empty($_GET['id'])) {
        throw new Exception("Worker ID is required");
    }
    
    $id = intval($_GET['id']);
    
    $sql = "SELECT 
            id,
            musaned_status, musaned_issues,
            contract_status, contract_issues,
            embassy_status, embassy_issues,
            epro_approval_status, epro_approval_issues,
            epro_approved_status, epro_approved_issues,
            fmol_approval_status, fmol_approval_issues,
            fmol_approved_status, fmol_approved_issues,
            saudi_embassy_status, saudi_embassy_issues,
            visa_issued_status, visa_issued_issues,
            arrived_ksa_status, arrived_ksa_issues,
            rejected_status, rejected_issues,
            canceled_status, canceled_issues,
            visa_canceled_status, visa_canceled_issues
            FROM workers 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    
    if (!$status) {
        throw new Exception("Worker not found");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 