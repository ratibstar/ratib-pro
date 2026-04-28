<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/stats.php`.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/indonesia-compliance-helper.php';

// Enforce permission for viewing worker stats
enforceApiPermission('workers', 'stats');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratib_indonesia_compliance_ensure_schema($conn);
    
    // Single optimized query for all stats
    // Normalize status: handle NULL, empty string, and trim whitespace
    // Map all status values to the displayed categories:
    // Active: 'approved', 'active', 'deployed'
    // Inactive: 'inactive', 'rejected', 'returned'
    // Pending: 'pending', NULL, empty string, or any unmapped status (default)
    // Suspended: 'suspended'
    $stmt = $conn->query("SELECT 
        COUNT(*) as total, 
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(status), ''), 'pending') IN ('approved', 'active', 'deployed') THEN 1 
            ELSE 0 
        END) as active,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(status), ''), 'pending') IN ('inactive', 'rejected', 'returned') THEN 1 
            ELSE 0 
        END) as inactive,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(status), ''), 'pending') = 'pending' THEN 1
            WHEN COALESCE(NULLIF(TRIM(status), ''), 'pending') NOT IN ('approved', 'active', 'deployed', 'inactive', 'rejected', 'returned', 'suspended', 'deleted') THEN 1
            ELSE 0 
        END) as pending,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(status), ''), 'pending') = 'suspended' THEN 1 
            ELSE 0 
        END) as suspended,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(police_status), ''), '') = 'ok' THEN 1 
            ELSE 0 
        END) as police,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(medical_status), ''), '') IN ('ok', 'passed', 'approved') THEN 1 
            ELSE 0 
        END) as medical,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(visa_status), ''), '') = 'ok' THEN 1 
            ELSE 0 
        END) as visa,
        SUM(CASE 
            WHEN COALESCE(NULLIF(TRIM(ticket_status), ''), '') = 'ok' THEN 1 
            ELSE 0 
        END) as ticket
    FROM workers
    WHERE COALESCE(NULLIF(TRIM(status), ''), 'pending') != 'deleted'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure all values are integers and handle NULL
    $total = (int)($result['total'] ?? 0);
    $active = (int)($result['active'] ?? 0);
    $inactive = (int)($result['inactive'] ?? 0);
    $pending = (int)($result['pending'] ?? 0);
    $suspended = (int)($result['suspended'] ?? 0);
    
    // Verify sum equals total (for debugging - remove in production if desired)
    $sum = $active + $inactive + $pending + $suspended;
    if ($sum != $total && $total > 0) {
        error_log("Worker Stats Warning: Sum ($sum) does not equal total ($total). Difference: " . ($total - $sum));
        // Adjust pending to account for any discrepancy (unmapped statuses)
        $pending = $total - $active - $inactive - $suspended;
    }

    $data = [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
        'pending' => max(0, $pending), // Ensure non-negative
        'suspended' => $suspended,
        'documents' => [
            'police' => (int)($result['police'] ?? 0),
            'medical' => (int)($result['medical'] ?? 0),
            'visa' => (int)($result['visa'] ?? 0),
            'ticket' => (int)($result['ticket'] ?? 0)
        ]
    ];

    $programCountry = strtolower(trim((string)($_SESSION['country_name'] ?? (defined('COUNTRY_NAME') ? COUNTRY_NAME : ''))));
    $programCode = strtolower(trim((string)($_SESSION['country_code'] ?? (defined('COUNTRY_CODE') ? COUNTRY_CODE : ''))));
    $isIndonesiaProgram = strpos($programCountry, 'indonesia') !== false || in_array($programCode, ['id', 'idn', 'indonesia'], true);
    $indo = [];
    if ($isIndonesiaProgram) {
        $indoStmt = $conn->query("SELECT
            SUM(CASE WHEN status_stage = 'ready_to_depart' THEN 1 ELSE 0 END) AS ready_to_deploy,
            SUM(CASE WHEN COALESCE(NULLIF(TRIM(medical_status), ''), 'pending') IN ('pending', 'waiting', 'processing') THEN 1 ELSE 0 END) AS waiting_medical,
            SUM(CASE WHEN COALESCE(NULLIF(TRIM(gov_approval_status), ''), 'pending') = 'pending' THEN 1 ELSE 0 END) AS waiting_approval,
            SUM(CASE
                WHEN COALESCE(NULLIF(TRIM(medical_status), ''), '') IN ('failed', 'not_ok', 'rejected')
                  OR COALESCE(NULLIF(TRIM(gov_approval_status), ''), '') = 'rejected'
                  OR COALESCE(NULLIF(TRIM(passport_status), ''), '') IN ('rejected', 'not_ok')
                  OR COALESCE(NULLIF(TRIM(contract_signed_status), ''), '') IN ('rejected', 'not_ok')
                  OR COALESCE(NULLIF(TRIM(training_certificate_status), ''), '') IN ('rejected', 'not_ok')
                  OR COALESCE(NULLIF(TRIM(visa_status), ''), '') IN ('rejected', 'not_ok')
                  OR COALESCE(NULLIF(TRIM(insurance_status), ''), '') IN ('rejected', 'not_ok')
                  OR COALESCE(NULLIF(TRIM(exit_permit_status), ''), '') IN ('rejected', 'not_ok')
                THEN 1 ELSE 0 END) AS blocked_workers
            FROM workers
            WHERE COALESCE(NULLIF(TRIM(status), ''), 'pending') != 'deleted'");
        $indo = $indoStmt ? ($indoStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
    }
    $data['indonesia_compliance'] = [
        'ready_to_deploy' => (int)($indo['ready_to_deploy'] ?? 0),
        'waiting_medical' => (int)($indo['waiting_medical'] ?? 0),
        'waiting_approval' => (int)($indo['waiting_approval'] ?? 0),
        'blocked_workers' => (int)($indo['blocked_workers'] ?? 0),
    ];

    echo ApiResponse::success($data);

} catch (Exception $e) {
    error_log("Workers Stats API Error: " . $e->getMessage());
    echo ApiResponse::error($e->getMessage());
} catch (Error $e) {
    error_log("Workers Stats API Fatal Error: " . $e->getMessage());
    echo ApiResponse::error("Fatal error: " . $e->getMessage());
}
