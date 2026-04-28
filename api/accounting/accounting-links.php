<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/accounting-links.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/accounting-links.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $module = $_GET['module'] ?? '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (empty($module) || $id <= 0) {
        throw new Exception('Module and ID are required');
    }
    
    $links = [
        'journal_entries' => [],
        'entry_approvals' => [],
        'cost_centers' => [],
        'bank_guarantees' => [],
        'transactions' => []
    ];
    
    if ($module === 'cost_center') {
        // Get linked journal entries
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        if ($costCenterCheck && $costCenterCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT je.id, je.entry_number, je.entry_date, je.description, je.total_debit, je.total_credit, je.status
                FROM journal_entry_lines jel
                INNER JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.cost_center_id = ?
                ORDER BY je.entry_date DESC
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $links['journal_entries'][] = $row;
            }
        }
        
        // Get linked entry approvals
        $approvalCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'cost_center_id'");
        if ($approvalCheck && $approvalCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT id, entry_number, entry_date, description, amount, status
                FROM entry_approval
                WHERE cost_center_id = ?
                ORDER BY entry_date DESC
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $links['entry_approvals'][] = $row;
            }
        }
    } elseif ($module === 'bank_guarantee') {
        // Get linked entry approvals
        $approvalCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'bank_guarantee_id'");
        if ($approvalCheck && $approvalCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT id, entry_number, entry_date, description, amount, status
                FROM entry_approval
                WHERE bank_guarantee_id = ?
                ORDER BY entry_date DESC
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $links['entry_approvals'][] = $row;
            }
        }
    } elseif ($module === 'entry_approval') {
        // First get the entry approval record
        $entryStmt = $conn->prepare("SELECT journal_entry_id, cost_center_id, bank_guarantee_id FROM entry_approval WHERE id = ?");
        $entryStmt->bind_param('i', $id);
        $entryStmt->execute();
        $entryResult = $entryStmt->get_result();
        $entryRow = $entryResult->fetch_assoc();
        
        if ($entryRow) {
            // Get linked journal entry
            if ($entryRow['journal_entry_id']) {
                $stmt = $conn->prepare("
                    SELECT id, entry_number, entry_date, description, total_debit, total_credit, status
                    FROM journal_entries
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $entryRow['journal_entry_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $links['journal_entries'][] = $row;
                }
            }
            
            // Get linked cost center
            if ($entryRow['cost_center_id']) {
                $stmt = $conn->prepare("
                    SELECT id, code, name, description, status
                    FROM cost_centers
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $entryRow['cost_center_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $links['cost_centers'][] = $row;
                }
            }
            
            // Get linked bank guarantee
            if ($entryRow['bank_guarantee_id']) {
                $stmt = $conn->prepare("
                    SELECT id, reference_number, bank_name, amount, issue_date, expiry_date, status
                    FROM bank_guarantees
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $entryRow['bank_guarantee_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $links['bank_guarantees'][] = $row;
                }
            }
        }
    } elseif ($module === 'journal_entry') {
        // Get linked entry approvals
        $approvalCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'journal_entry_id'");
        if ($approvalCheck && $approvalCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT id, entry_number, entry_date, description, amount, status
                FROM entry_approval
                WHERE journal_entry_id = ?
                ORDER BY entry_date DESC
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $links['entry_approvals'][] = $row;
            }
        }
        
        // Get linked cost centers from journal entry lines
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        if ($costCenterCheck && $costCenterCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT DISTINCT cc.id, cc.code, cc.name, cc.description, cc.status
                FROM journal_entry_lines jel
                INNER JOIN cost_centers cc ON jel.cost_center_id = cc.id
                WHERE jel.journal_entry_id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $links['cost_centers'][] = $row;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'module' => $module,
        'id' => $id,
        'links' => $links
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

