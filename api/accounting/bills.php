<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/bills.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/bills.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) { require_once __DIR__ . '/../core/date-helper.php'; }
elseif (file_exists(__DIR__ . '/core/date-helper.php')) { require_once __DIR__ . '/core/date-helper.php'; }
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) { if (empty($s) || $s === '0000-00-00') return ''; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]); $t = strtotime($s); return $t ? date('m/d/Y', $t) : $s; }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) { if (!is_array($data)) return $data; $fields = $fields ?? ['date','entry_date','invoice_date','bill_date','due_date','created_at','updated_at','transaction_date']; foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields); elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v); } return $data; }
}

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions based on method
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method === 'GET') {
    enforceApiPermission('bills', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('bills', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('bills', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('bills', 'delete');
}

try {
    $billId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

    // Check which table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_payable'");
    $useNewTable = $tableCheck->num_rows > 0;
    
    // Check if old accounting_bills table exists
    $oldTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bills'");
    $useOldTable = $oldTableCheck->num_rows > 0;
    
    // If no tables exist, CREATE the new table automatically
    if (!$useNewTable && !$useOldTable) {
        $conn->query("
                CREATE TABLE IF NOT EXISTS accounts_payable (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    bill_number VARCHAR(50) UNIQUE,
                    bill_date DATE NOT NULL,
                    due_date DATE NOT NULL,
                    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(20) DEFAULT 'Draft',
                    currency VARCHAR(3) DEFAULT 'SAR',
                    entity_type VARCHAR(50),
                    entity_id INT,
                    description TEXT,
                    vendor_id INT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_entity (entity_type, entity_id),
                    INDEX idx_status (status),
                    INDEX idx_bill_date (bill_date),
                    INDEX idx_vendor (vendor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $useNewTable = true;
        
        // Create accounting_vendors table if it doesn't exist
        $vendorsTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
        if ($vendorsTableCheck->num_rows === 0) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS accounting_vendors (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    vendor_name VARCHAR(100) NOT NULL,
                    contact_person VARCHAR(100),
                    email VARCHAR(100),
                    phone VARCHAR(20),
                    address TEXT,
                    payment_terms INT DEFAULT 30,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Backfill: Create bills from existing entity transactions
        $backfillQuery = "
            INSERT INTO accounts_payable (
                bill_number, bill_date, due_date,
                total_amount, paid_amount, balance_amount,
                status, currency, entity_type, entity_id, description, created_by
            )
            SELECT 
                CONCAT('BILL-', LPAD(ft.id, 8, '0')) as bill_number,
                ft.transaction_date as bill_date,
                ft.transaction_date as due_date,
                ft.total_amount,
                0 as paid_amount,
                ft.total_amount as balance_amount,
                'Posted' as status,
                COALESCE(ft.currency, 'SAR') as currency,
                et.entity_type,
                et.entity_id,
                ft.description,
                ft.created_by
            FROM financial_transactions ft
            INNER JOIN entity_transactions et ON ft.id = et.transaction_id
            WHERE ft.transaction_type = 'Expense'
            AND ft.status = 'Posted'
            AND NOT EXISTS (
                SELECT 1 FROM accounts_payable ap 
                WHERE ap.entity_type = et.entity_type 
                AND ap.entity_id = et.entity_id
                AND ap.bill_date = ft.transaction_date
                AND ABS(ap.total_amount - ft.total_amount) < 0.01
            )
        ";
        $conn->query($backfillQuery);
    }
    
    // If requesting a single bill by ID, handle it separately
    if ($method === 'GET' && $billId) {
        $bill = null;
        if ($useNewTable) {
            // Check if accounting_vendors table exists
            $vendorsTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
            $hasVendorsTable = $vendorsTableCheck->num_rows > 0;
            
            if ($hasVendorsTable) {
                $stmt = $conn->prepare("
                    SELECT 
                        ap.*,
                        COALESCE(av.vendor_name, 'N/A') as vendor_name,
                        ap.vendor_id
                    FROM accounts_payable ap
                    LEFT JOIN accounting_vendors av ON ap.vendor_id = av.id
                    WHERE ap.id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        ap.*,
                        'N/A' as vendor_name,
                        ap.vendor_id
                    FROM accounts_payable ap
                    WHERE ap.id = ?
                ");
            }
            $stmt->bind_param('i', $billId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $bill = $row;
            }
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    *,
                    COALESCE((SELECT vendor_name FROM accounting_vendors WHERE id = accounting_bills.vendor_id), 'N/A') as vendor_name,
                    vendor_id
                FROM accounting_bills
                WHERE id = ?
            ");
            $stmt->bind_param('i', $billId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $bill = $row;
            }
        }
        
        if ($bill) {
            // Format dates for display
            $bill = formatDatesInArray($bill);
            echo json_encode([
                'success' => true,
                'bill' => $bill
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Bill not found'
            ]);
        }
        exit;
    }

    if ($useNewTable) {
        // Use new accounts_payable table
        // Check if accounting_vendors table exists for JOIN
        $vendorsTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
        $hasVendorsTable = $vendorsTableCheck->num_rows > 0;
        
        // Check if debit_amount and credit_amount columns exist, add them if not
        $debitCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'debit_amount'");
        $hasDebit = $debitCheck->num_rows > 0;
        $creditCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'credit_amount'");
        $hasCredit = $creditCheck->num_rows > 0;
        
        if (!$hasDebit) {
            $conn->query("ALTER TABLE accounts_payable ADD COLUMN debit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER balance_amount");
        }
        if (!$hasCredit) {
            $conn->query("ALTER TABLE accounts_payable ADD COLUMN credit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER debit_amount");
        }
        
        // Update existing rows to calculate debit/credit
        // For bills: Debit = total_amount (payable), Credit = paid_amount (payment made)
        $conn->query("
            UPDATE accounts_payable 
            SET 
                debit_amount = COALESCE(total_amount, 0),
                credit_amount = COALESCE(paid_amount, 0)
            WHERE debit_amount IS NULL OR debit_amount = 0 OR credit_amount IS NULL OR credit_amount = 0
        ");
        
        if ($hasVendorsTable) {
            $query = "
                SELECT 
                    ap.id,
                    ap.bill_number,
                    ap.bill_date,
                    ap.due_date,
                    ap.total_amount,
                    ap.paid_amount,
                    ap.balance_amount,
                    COALESCE(ap.debit_amount, ap.total_amount, 0) as debit_amount,
                    COALESCE(ap.credit_amount, ap.paid_amount, 0) as credit_amount,
                    ap.status,
                    ap.currency,
                    COALESCE(
                        av.vendor_name,
                        CASE 
                            WHEN ap.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ap.entity_id LIMIT 1)
                            ELSE 'N/A'
                        END,
                        'N/A'
                    ) as vendor_name,
                    ap.entity_type,
                    ap.entity_id
                FROM accounts_payable ap
                LEFT JOIN accounting_vendors av ON ap.vendor_id = av.id
            ";
        } else {
            $query = "
                SELECT 
                    ap.id,
                    ap.bill_number,
                    ap.bill_date,
                    ap.due_date,
                    ap.total_amount,
                    ap.paid_amount,
                    ap.balance_amount,
                    COALESCE(ap.debit_amount, ap.total_amount, 0) as debit_amount,
                    COALESCE(ap.credit_amount, ap.paid_amount, 0) as credit_amount,
                    ap.status,
                    ap.currency,
                    COALESCE(
                        CASE 
                            WHEN ap.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ap.entity_id LIMIT 1)
                            WHEN ap.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ap.entity_id LIMIT 1)
                            ELSE 'N/A'
                        END,
                        'N/A'
                    ) as vendor_name,
                    ap.entity_type,
                    ap.entity_id
                FROM accounts_payable ap
            ";
        }
        
        $conditions = [];
        $params = [];
        $types = '';

        if ($status === 'pending') {
            $conditions[] = "ap.status IN ('Draft', 'Received')";
        } elseif ($status) {
            $conditions[] = "ap.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY ap.bill_date DESC, ap.created_at DESC";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // Fallback to old accounting_bills table
        $query = "
            SELECT 
                id,
                bill_number,
                bill_date,
                due_date,
                total_amount,
                COALESCE(paid_amount, 0) as paid_amount,
                (total_amount - COALESCE(paid_amount, 0)) as balance_amount,
                status,
                'SAR' as currency,
                COALESCE((SELECT vendor_name FROM accounting_vendors WHERE id = accounting_bills.vendor_id), 'N/A') as vendor_name,
                NULL as entity_type,
                NULL as entity_id
            FROM accounting_bills
        ";
        
        $conditions = [];
        $params = [];
        $types = '';

        if ($status === 'pending') {
            $conditions[] = "status IN ('Draft', 'Pending')";
        } elseif ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY bill_date DESC, created_at DESC";

        if ($limit) {
            $query .= " LIMIT ?";
            $params[] = $limit;
            $types .= 'i';
        }

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    }

    $bills = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for display
        $row = formatDatesInArray($row);
        $bills[] = $row;
    }

    // Calculate summary if not limited
    $summary = null;
    if (!$limit && !$status) {
        if ($useNewTable) {
            $summaryStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled', 'Voided') THEN balance_amount ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month
                FROM accounts_payable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
        } else {
            $summaryStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN (total_amount - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month
                FROM accounting_bills
                WHERE status NOT IN ('Paid', 'Cancelled')
            ");
        }
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result()->fetch_assoc();
        $summary = [
            'total_outstanding' => floatval($summaryResult['total_outstanding'] ?? 0),
            'overdue' => floatval($summaryResult['overdue'] ?? 0),
            'this_month' => floatval($summaryResult['this_month'] ?? 0)
        ];
    }

    // Handle different HTTP methods
    if ($method === 'GET') {
        // Format all dates in bills array for display
        $bills = formatDatesInArray($bills);
        // Return list of bills (single bill already handled above)
        echo json_encode([
            'success' => true,
            'bills' => $bills,
            'summary' => $summary
        ]);
    } elseif ($method === 'POST') {
        // Create new bill
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $billNumber = $data['bill_number'] ?? null;
        // Convert dates from MM/DD/YYYY to YYYY-MM-DD for database
        $billDate = formatDateForDatabase($data['bill_date'] ?? date('Y-m-d'));
        $dueDate = formatDateForDatabase($data['due_date'] ?? $billDate);
        $totalAmount = floatval($data['total_amount'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'SAR');
        $description = $data['description'] ?? '';
        $vendorId = isset($data['vendor_id']) && $data['vendor_id'] !== '' ? intval($data['vendor_id']) : null;
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        
        if ($totalAmount <= 0) {
            throw new Exception('Total amount must be greater than 0');
        }
        
        // Validate vendor_id if vendor_id column exists
        if ($useNewTable) {
            $colCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'vendor_id'");
            if ($colCheck->num_rows > 0 && !$vendorId) {
                // Vendor ID is optional for new table (can use entity_type/entity_id instead)
                // But if provided, validate it exists
                if (isset($data['vendor_id']) && $data['vendor_id'] !== '' && $data['vendor_id'] !== null) {
                    $vendorCheck = $conn->prepare("SELECT id FROM accounting_vendors WHERE id = ? AND is_active = 1");
                    $vendorCheck->bind_param('i', $vendorId);
                    $vendorCheck->execute();
                    if ($vendorCheck->get_result()->num_rows === 0) {
                        throw new Exception('Selected vendor not found or inactive');
                    }
                }
            }
        } elseif (!$useNewTable && !$vendorId) {
            throw new Exception('Vendor is required');
        }
        
        // Auto-generate bill number if not provided
        if (empty($billNumber)) {
            $refStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(bill_number, 6) AS UNSIGNED)) as max_num FROM accounts_payable WHERE bill_number LIKE 'BILL-%'");
            if ($refStmt) {
                $refStmt->execute();
                $refResult = $refStmt->get_result();
                if ($refRow = $refResult->fetch_assoc()) {
                    $nextNum = ($refRow['max_num'] ?? 0) + 1;
                    $billNumber = 'BILL-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
                } else {
                    $billNumber = 'BILL-00000001';
                }
                $refStmt->close();
            } else {
                $billNumber = 'BILL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                // Check if vendor_id column exists
                $colCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'vendor_id'");
                if ($colCheck->num_rows > 0) {
                    $stmt = $conn->prepare("
                        INSERT INTO accounts_payable 
                        (bill_number, bill_date, due_date, total_amount, paid_amount, balance_amount, currency, description, vendor_id, entity_type, entity_id, status, created_by)
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'Draft', ?)
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param('sssddsssiiiii', $billNumber, $billDate, $dueDate, $totalAmount, $totalAmount, $currency, $description, $vendorId, $entityType, $entityId, $userId);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO accounts_payable 
                        (bill_number, bill_date, due_date, total_amount, paid_amount, balance_amount, currency, description, entity_type, entity_id, status, created_by)
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'Draft', ?)
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param('sssddsssiii', $billNumber, $billDate, $dueDate, $totalAmount, $totalAmount, $currency, $description, $entityType, $entityId, $userId);
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO accounting_bills 
                    (bill_number, bill_date, due_date, total_amount, paid_amount, vendor_id, status, created_by)
                    VALUES (?, ?, ?, ?, 0, ?, 'Draft', ?)
                ");
                $userId = $_SESSION['user_id'];
                if ($vendorId) {
                    $stmt->bind_param('sssdi', $billNumber, $billDate, $dueDate, $totalAmount, $vendorId, $userId);
                } else {
                    // If no vendor_id provided, use a default or throw error
                    throw new Exception('Vendor ID is required');
                }
            }
            $stmt->execute();
            $billId = $conn->insert_id;
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bill created successfully',
                'bill_id' => $billId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        // Update bill
        $billId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($billId <= 0) {
            throw new Exception('Bill ID is required');
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                // Check if vendor_id column exists
                $colCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'vendor_id'");
                $vendorId = isset($data['vendor_id']) ? intval($data['vendor_id']) : null;
                if ($colCheck->num_rows > 0 && $vendorId) {
                    $stmt = $conn->prepare("
                        UPDATE accounts_payable 
                        SET bill_date = ?, due_date = ?, total_amount = ?, currency = ?, description = ?, vendor_id = ?, status = ?
                        WHERE id = ?
                    ");
                    $billDate = $data['bill_date'] ?? null;
                    $dueDate = $data['due_date'] ?? null;
                    $totalAmount = floatval($data['total_amount'] ?? 0);
                    $currency = strtoupper($data['currency'] ?? 'SAR');
                    $description = $data['description'] ?? '';
                    $status = $data['status'] ?? 'Draft';
                    $stmt->bind_param('ssdsssii', $billDate, $dueDate, $totalAmount, $currency, $description, $vendorId, $status, $billId);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE accounts_payable 
                        SET bill_date = ?, due_date = ?, total_amount = ?, currency = ?, description = ?, status = ?
                        WHERE id = ?
                    ");
                    $billDate = $data['bill_date'] ?? null;
                    $dueDate = $data['due_date'] ?? null;
                    $totalAmount = floatval($data['total_amount'] ?? 0);
                    $currency = strtoupper($data['currency'] ?? 'SAR');
                    $description = $data['description'] ?? '';
                    $status = $data['status'] ?? 'Draft';
                    $stmt->bind_param('ssdsssi', $billDate, $dueDate, $totalAmount, $currency, $description, $status, $billId);
                }
            } else {
                $vendorId = isset($data['vendor_id']) ? intval($data['vendor_id']) : null;
                $stmt = $conn->prepare("
                    UPDATE accounting_bills 
                    SET bill_date = ?, due_date = ?, total_amount = ?, vendor_id = ?, status = ?
                    WHERE id = ?
                ");
                $billDate = $data['bill_date'] ?? null;
                $dueDate = $data['due_date'] ?? null;
                $totalAmount = floatval($data['total_amount'] ?? 0);
                $status = $data['status'] ?? 'Draft';
                if ($vendorId) {
                    $stmt->bind_param('ssdsii', $billDate, $dueDate, $totalAmount, $vendorId, $status, $billId);
                } else {
                    $stmt->bind_param('ssdsi', $billDate, $dueDate, $totalAmount, $status, $billId);
                }
            }
            $stmt->execute();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bill updated successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        // Delete bill
        $billId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($billId <= 0) {
            throw new Exception('Bill ID is required');
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                $stmt = $conn->prepare("DELETE FROM accounts_payable WHERE id = ?");
            } else {
                $stmt = $conn->prepare("DELETE FROM accounting_bills WHERE id = ?");
            }
            $stmt->bind_param('i', $billId);
            $stmt->execute();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bill deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

} catch (Exception $e) {
    error_log("Bills API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
