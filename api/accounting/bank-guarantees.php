<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/bank-guarantees.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/bank-guarantees.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

function normalizeDateForDb($val) {
    if (empty($val) || trim($val) === '') return null;
    $val = trim($val);
    if ($val === '0000-00-00' || strpos($val, '0000') === 0) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) return $val;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) {
        return $m[3] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    }
    $ts = strtotime($val);
    return $ts ? date('Y-m-d', $ts) : null;
}

function normalizeStatus($val) {
    $allowed = ['active', 'expired', 'cancelled'];
    $v = strtolower(trim($val ?? ''));
    return in_array($v, $allowed) ? $v : 'active';
}

header('Content-Type: application/json');

global $conn;

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions
$method = $_SERVER['REQUEST_METHOD'];
try {
    $MODULE_PERMISSIONS = require __DIR__ . '/../core/module-permissions.php';
    $module = isset($MODULE_PERMISSIONS['bank_guarantees']) ? 'bank_guarantees' : 'bank-accounts';
    if ($method === 'GET') {
        enforceApiPermission($module, 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission($module, 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission($module, 'update');
    } elseif ($method === 'DELETE') {
        enforceApiPermission($module, 'delete');
    }
} catch (Exception $permEx) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $permEx->getMessage()]);
    exit;
}

try {
    if (!isset($conn) || !$conn) {
        throw new Exception('Database connection not available');
    }

    // Check if bank_guarantees table exists, create if not
    $tableCheck = @$conn->query("SHOW TABLES LIKE 'bank_guarantees'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS bank_guarantees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reference_number VARCHAR(100) NOT NULL UNIQUE,
                bank_name VARCHAR(255) NOT NULL,
                amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) DEFAULT 'SAR',
                issue_date DATE NOT NULL,
                expiry_date DATE,
                status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT,
                INDEX idx_reference (reference_number),
                INDEX idx_status (status),
                INDEX idx_expiry (expiry_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        if ($id) {
            // Get single bank guarantee
            $stmt = $conn->prepare("SELECT * FROM bank_guarantees WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'bank_guarantee' => [
                        'id' => intval($row['id']),
                        'reference_number' => $row['reference_number'],
                        'bank_name' => $row['bank_name'],
                        'amount' => floatval($row['amount']),
                        'currency' => $row['currency'],
                        'issue_date' => $row['issue_date'],
                        'expiry_date' => $row['expiry_date'],
                        'status' => $row['status'],
                        'description' => $row['description'],
                        'linked_approvals' => 0,
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Bank guarantee not found']);
            }
        } else {
            // Get all bank guarantees
            $query = "SELECT * FROM bank_guarantees WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            $query .= " ORDER BY issue_date DESC, reference_number";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }

            if (!$result) {
                throw new Exception('Failed to fetch bank guarantees');
            }
            
            $bankGuarantees = [];
            while ($row = $result->fetch_assoc()) {
                $bankGuaranteeId = intval($row['id']);
                
                // Count linked entry approvals (table may not exist)
                $approvalCount = 0;
                try {
                    $approvalCheck = @$conn->query("SHOW COLUMNS FROM entry_approval LIKE 'bank_guarantee_id'");
                    if ($approvalCheck && $approvalCheck->num_rows > 0) {
                        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM entry_approval WHERE bank_guarantee_id = ?");
                        if ($countStmt) {
                            $countStmt->bind_param('i', $bankGuaranteeId);
                            $countStmt->execute();
                            $countResult = $countStmt->get_result();
                            if ($countRow = $countResult->fetch_assoc()) {
                                $approvalCount = intval($countRow['count']);
                            }
                        }
                    }
                } catch (Exception $e) { /* ignore */ }
                
                $bankGuarantees[] = [
                    'id' => $bankGuaranteeId,
                    'reference_number' => $row['reference_number'],
                    'bank_name' => $row['bank_name'],
                    'amount' => floatval($row['amount']),
                    'currency' => $row['currency'],
                    'issue_date' => $row['issue_date'],
                    'expiry_date' => $row['expiry_date'],
                    'status' => $row['status'],
                    'description' => $row['description'],
                    'linked_approvals' => $approvalCount,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'bank_guarantees' => $bankGuarantees,
                'count' => count($bankGuarantees)
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new bank guarantee
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $referenceNumber = trim($data['reference_number'] ?? '');
        $bankName = trim($data['bank_name'] ?? '');
        $amount = floatval($data['amount'] ?? 0);
        $currency = trim($data['currency'] ?? 'SAR') ?: 'SAR';
        $issueDate = normalizeDateForDb($data['issue_date'] ?? null) ?: date('Y-m-d');
        $expiryDateRaw = $data['expiry_date'] ?? null;
        $expiryDate = (empty($expiryDateRaw) || trim($expiryDateRaw) === '') ? null : normalizeDateForDb($expiryDateRaw);
        $status = normalizeStatus($data['status'] ?? 'active');
        $description = trim($data['description'] ?? '');
        $createdBy = intval($_SESSION['user_id'] ?? 0) ?: 0;
        
        if (empty($referenceNumber) || empty($bankName)) {
            throw new Exception('Reference number and bank name are required');
        }
        
        // Check if reference number already exists
        $checkStmt = $conn->prepare("SELECT id FROM bank_guarantees WHERE reference_number = ?");
        $checkStmt->bind_param('s', $referenceNumber);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception('Reference number already exists');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO bank_guarantees (reference_number, bank_name, amount, currency, issue_date, expiry_date, status, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssdsssssi', $referenceNumber, $bankName, $amount, $currency, $issueDate, $expiryDate, $status, $description, $createdBy);
        $stmt->execute();
        $bankGuaranteeId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank guarantee created successfully',
            'bank_guarantee_id' => $bankGuaranteeId
        ]);
    } elseif ($method === 'PUT') {
        // Update bank guarantee
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($id <= 0) {
            throw new Exception('Bank guarantee ID is required');
        }
        
        $referenceNumber = trim($data['reference_number'] ?? '');
        $bankName = trim($data['bank_name'] ?? '');
        $amount = floatval($data['amount'] ?? 0);
        $currency = trim($data['currency'] ?? 'SAR') ?: 'SAR';
        $issueDate = normalizeDateForDb($data['issue_date'] ?? null) ?: date('Y-m-d');
        $expiryDateRaw = $data['expiry_date'] ?? null;
        $expiryDate = (empty($expiryDateRaw) || trim($expiryDateRaw) === '') ? null : normalizeDateForDb($expiryDateRaw);
        $status = normalizeStatus($data['status'] ?? 'active');
        $description = trim($data['description'] ?? '');
        
        if (empty($referenceNumber) || empty($bankName)) {
            throw new Exception('Reference number and bank name are required');
        }
        
        // Check if reference number already exists for another bank guarantee
        $checkStmt = $conn->prepare("SELECT id FROM bank_guarantees WHERE reference_number = ? AND id != ?");
        $checkStmt->bind_param('si', $referenceNumber, $id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception('Reference number already exists');
        }
        
        $stmt = $conn->prepare("
            UPDATE bank_guarantees 
            SET reference_number = ?, bank_name = ?, amount = ?, currency = ?, issue_date = ?, expiry_date = ?, status = ?, description = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssdsssssi', $referenceNumber, $bankName, $amount, $currency, $issueDate, $expiryDate, $status, $description, $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank guarantee updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete bank guarantee - some servers don't pass query params for DELETE, so check body too
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id <= 0) {
            $body = json_decode(file_get_contents('php://input'), true);
            $id = isset($body['id']) ? intval($body['id']) : (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);
        }
        if ($id <= 0) {
            throw new Exception('Bank guarantee ID is required');
        }
        
        $stmt = $conn->prepare("DELETE FROM bank_guarantees WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank guarantee deleted successfully'
        ]);
    }
} catch (Exception $e) {
    $msg = $e->getMessage();
    $isClientError = (strpos($msg, 'required') !== false || strpos($msg, 'already exists') !== false || strpos($msg, 'ID is required') !== false);
    http_response_code($isClientError ? 400 : 500);
    if (!$isClientError) {
        error_log('bank-guarantees.php error: ' . $msg . ' | Trace: ' . $e->getTraceAsString());
    }
    echo json_encode([
        'success' => false,
        'message' => $msg
    ]);
}

