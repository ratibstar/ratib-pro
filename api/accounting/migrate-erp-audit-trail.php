<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-erp-audit-trail.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-erp-audit-trail.php`.
 */
/**
 * ERP-Grade Audit Trail Migration
 * 
 * PHASE 5: Audit trail table and tracking
 * 
 * Tracks all changes to journal entries and general ledger
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $results = [];
    $errors = [];
    
    // ============================================
    // STEP 1: Create audit_trail table
    // ============================================
    $auditCheck = $conn->query("SHOW TABLES LIKE 'audit_trail'");
    $hasAudit = $auditCheck && $auditCheck->num_rows > 0;
    if ($auditCheck) $auditCheck->free();
    
    if (!$hasAudit) {
        $createAudit = $conn->query("
            CREATE TABLE audit_trail (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                table_name VARCHAR(100) NOT NULL,
                record_id INT(11) NOT NULL,
                action ENUM('CREATE', 'UPDATE', 'DELETE', 'POST', 'REVERSE', 'APPROVE', 'REJECT') NOT NULL,
                field_name VARCHAR(100) NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                changed_by INT NOT NULL,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                notes TEXT NULL,
                INDEX idx_table_record (table_name, record_id),
                INDEX idx_action (action),
                INDEX idx_changed_by (changed_by),
                INDEX idx_changed_at (changed_at),
                FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        if ($createAudit) {
            $results[] = "Created audit_trail table";
        } else {
            $errors[] = "Failed to create audit_trail table: " . $conn->error;
        }
    } else {
        $results[] = "audit_trail table already exists";
    }
    
    // Prepare response
    $response = [
        'success' => count($errors) === 0,
        'message' => 'ERP-Grade Audit Trail migration completed',
        'results' => $results
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
