<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/invoices.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/invoices.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) {
    require_once __DIR__ . '/../core/date-helper.php';
} elseif (file_exists(__DIR__ . '/core/date-helper.php')) {
    require_once __DIR__ . '/core/date-helper.php';
}
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) { if (empty($s) || $s === '0000-00-00') return ''; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]); $t = strtotime($s); return $t ? date('m/d/Y', $t) : $s; }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) { if (!is_array($data)) return $data; $fields = $fields ?? ['date','entry_date','invoice_date','due_date','created_at','updated_at','transaction_date']; foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields); elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v); } return $data; }
}
require_once __DIR__ . '/core/invoice-payment-automation.php';

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $error['message'],
            'file' => basename($error['file'] ?? ''),
            'line' => $error['line'] ?? 0
        ]);
        exit;
    }
});

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
    enforceApiPermission('invoices', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('invoices', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('invoices', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('invoices', 'delete');
}

try {
    $invoiceId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

    // Check which table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_receivable'");
    $useNewTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->free();
    }
    
    // Check if old accounting_invoices table exists
    $oldTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_invoices'");
    $useOldTable = $oldTableCheck && $oldTableCheck->num_rows > 0;
    if ($oldTableCheck) {
        $oldTableCheck->free();
    }
    
    // If no tables exist, CREATE the new table automatically
    if (!$useNewTable && !$useOldTable) {
        $conn->query("
                CREATE TABLE IF NOT EXISTS accounts_receivable (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    invoice_number VARCHAR(50) UNIQUE,
                    invoice_date DATE NOT NULL,
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
                    customer_id INT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_entity (entity_type, entity_id),
                    INDEX idx_status (status),
                    INDEX idx_invoice_date (invoice_date),
                    INDEX idx_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $useNewTable = true;
        
        // Create accounting_customers table if it doesn't exist
        $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
        if ($customersTableCheck->num_rows === 0) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS accounting_customers (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    customer_name VARCHAR(100) NOT NULL,
                    contact_person VARCHAR(100),
                    email VARCHAR(100),
                    phone VARCHAR(20),
                    address TEXT,
                    credit_limit DECIMAL(15,2) DEFAULT 0.00,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Backfill: Create invoices from existing entity transactions (only if source tables exist)
        $ftCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        $etCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
        if ($ftCheck && $ftCheck->num_rows > 0 && $etCheck && $etCheck->num_rows > 0) {
            $ftCheck->free();
            $etCheck->free();
            $backfillQuery = "
                    INSERT INTO accounts_receivable (
                        invoice_number, invoice_date, due_date,
                        total_amount, paid_amount, balance_amount,
                        status, currency, entity_type, entity_id, description, created_by
                    )
                    SELECT 
                        CONCAT('INV-', LPAD(ft.id, 8, '0')) as invoice_number,
                        ft.transaction_date as invoice_date,
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
                    WHERE ft.transaction_type = 'Income'
                    AND ft.status = 'Posted'
                    AND NOT EXISTS (
                        SELECT 1 FROM accounts_receivable ar 
                        WHERE ar.entity_type = et.entity_type 
                        AND ar.entity_id = et.entity_id
                        AND ar.invoice_date = ft.transaction_date
                        AND ABS(ar.total_amount - ft.total_amount) < 0.01
                    )
                ";
            @$conn->query($backfillQuery);
        } else {
            if ($ftCheck) $ftCheck->free();
            if ($etCheck) $etCheck->free();
        }
    }
    
    // If requesting a single invoice by ID, handle it separately
    if ($method === 'GET' && $invoiceId) {
        $invoice = null;
        if ($useNewTable) {
            // Check if accounting_customers table exists
            $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
            $hasCustomersTable = $customersTableCheck->num_rows > 0;
            
            if ($hasCustomersTable) {
                $stmt = $conn->prepare("
                    SELECT 
                        ar.*,
                        COALESCE(ac.customer_name, 'N/A') as customer_name,
                        ar.customer_id
                    FROM accounts_receivable ar
                    LEFT JOIN accounting_customers ac ON ar.customer_id = ac.id
                    WHERE ar.id = ?
                ");
            } else {
                $stmt = $conn->prepare("
                    SELECT 
                        ar.*,
                        'N/A' as customer_name,
                        ar.customer_id
                    FROM accounts_receivable ar
                    WHERE ar.id = ?
                ");
            }
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $invoice = $row;
            }
        } else {
            $stmt = $conn->prepare("
                SELECT 
                    *,
                    COALESCE((SELECT customer_name FROM accounting_customers WHERE id = accounting_invoices.customer_id), 'N/A') as customer_name,
                    customer_id
                FROM accounting_invoices
                WHERE id = ?
            ");
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $invoice = $row;
            }
        }
        
        if ($invoice) {
            // Format dates for display
            $invoice = formatDatesInArray($invoice);
            echo json_encode([
                'success' => true,
                'invoice' => $invoice
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Invoice not found'
            ]);
        }
        exit;
    }

    if ($useNewTable) {
        // Use new accounts_receivable table
        // Check if accounting_customers table exists for JOIN
        $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
        $hasCustomersTable = $customersTableCheck->num_rows > 0;
        
        // Check if debit_amount and credit_amount columns exist, add them if not
        $debitCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'debit_amount'");
        $hasDebit = $debitCheck->num_rows > 0;
        $creditCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'credit_amount'");
        $hasCredit = $creditCheck->num_rows > 0;
        
        if (!$hasDebit) {
            $conn->query("ALTER TABLE accounts_receivable ADD COLUMN debit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER balance_amount");
        }
        if (!$hasCredit) {
            $conn->query("ALTER TABLE accounts_receivable ADD COLUMN credit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER debit_amount");
        }
        
        // Check if payment_voucher and vat_report columns exist, add them if not
        $paymentVoucherCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'payment_voucher'");
        $hasPaymentVoucher = $paymentVoucherCheck && $paymentVoucherCheck->num_rows > 0;
        if ($paymentVoucherCheck) {
            $paymentVoucherCheck->free();
        }
        
        $vatReportCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'vat_report'");
        $hasVatReport = $vatReportCheck && $vatReportCheck->num_rows > 0;
        if ($vatReportCheck) {
            $vatReportCheck->free();
        }
        
        if (!$hasPaymentVoucher) {
            $conn->query("ALTER TABLE accounts_receivable ADD COLUMN payment_voucher VARCHAR(100) NULL AFTER credit_amount");
        }
        if (!$hasVatReport) {
            $conn->query("ALTER TABLE accounts_receivable ADD COLUMN vat_report VARCHAR(100) NULL AFTER payment_voucher");
        }
        
        // Update existing rows to calculate debit/credit
        // For invoices: Credit = total_amount (receivable), Debit = paid_amount (payment received)
        $conn->query("
            UPDATE accounts_receivable 
            SET 
                debit_amount = COALESCE(paid_amount, 0),
                credit_amount = COALESCE(total_amount, 0)
            WHERE debit_amount IS NULL OR debit_amount = 0 OR credit_amount IS NULL OR credit_amount = 0
        ");
        
        if ($hasCustomersTable) {
            $query = "
                SELECT 
                    ar.id,
                    ar.invoice_number,
                    ar.invoice_date,
                    ar.due_date,
                    ar.total_amount,
                    ar.paid_amount,
                    ar.balance_amount,
                    COALESCE(ar.debit_amount, ar.paid_amount, 0) as debit_amount,
                    COALESCE(ar.credit_amount, ar.total_amount, 0) as credit_amount,
                    ar.status,
                    ar.currency,
                    ar.payment_voucher,
                    ar.vat_report,
                    ar.created_at,
                    COALESCE(
                        ac.customer_name,
                        CASE 
                            WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                            ELSE 'N/A'
                        END,
                        'N/A'
                    ) as customer_name,
                    ar.entity_type,
                    ar.entity_id
                FROM accounts_receivable ar
                LEFT JOIN accounting_customers ac ON ar.customer_id = ac.id
            ";
        } else {
            $query = "
                SELECT 
                    ar.id,
                    ar.invoice_number,
                    ar.invoice_date,
                    ar.due_date,
                    ar.total_amount,
                    ar.paid_amount,
                    ar.balance_amount,
                    COALESCE(ar.debit_amount, ar.paid_amount, 0) as debit_amount,
                    COALESCE(ar.credit_amount, ar.total_amount, 0) as credit_amount,
                    ar.status,
                    ar.currency,
                    ar.payment_voucher,
                    ar.vat_report,
                    ar.created_at,
                    COALESCE(
                        CASE 
                            WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                            ELSE 'N/A'
                        END,
                        'N/A'
                    ) as customer_name,
                    ar.entity_type,
                    ar.entity_id
                FROM accounts_receivable ar
            ";
        }
        
        $conditions = [];
        $params = [];
        $types = '';

        if ($status === 'outstanding') {
            $conditions[] = "ar.status NOT IN ('Paid', 'Cancelled', 'Voided')";
        } elseif ($status) {
            $conditions[] = "ar.status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY ar.id DESC";

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
        // Fallback to old accounting_invoices table
        $query = "
            SELECT 
                id,
                invoice_number,
                invoice_date,
                due_date,
                total_amount,
                COALESCE(paid_amount, 0) as paid_amount,
                (total_amount - COALESCE(paid_amount, 0)) as balance_amount,
                status,
                'SAR' as currency,
                COALESCE((SELECT customer_name FROM accounting_customers WHERE id = accounting_invoices.customer_id), 'N/A') as customer_name,
                NULL as entity_type,
                NULL as entity_id
            FROM accounting_invoices
        ";
        
        $conditions = [];
        $params = [];
        $types = '';

        if ($status === 'outstanding') {
            $conditions[] = "status NOT IN ('Paid', 'Cancelled')";
        } elseif ($status) {
            $conditions[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }

        $query .= " ORDER BY id DESC";

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

    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        // Format dates for display
        $row = formatDatesInArray($row);
        $invoices[] = $row;
    }

    // Calculate summary if not limited
    $summary = null;
    if (!$limit && !$status) {
        if ($useNewTable) {
            $summaryStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled', 'Voided') THEN balance_amount ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month
                FROM accounts_receivable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
        } else {
            $summaryStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN (total_amount - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month
                FROM accounting_invoices
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
        // Format all dates in invoices array for display
        $invoices = formatDatesInArray($invoices);
        // Return list of invoices (single invoice already handled above)
        echo json_encode([
            'success' => true,
            'invoices' => $invoices,
            'summary' => $summary
        ]);
    } elseif ($method === 'POST') {
        // Create new invoice
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $invoiceNumber = $data['invoice_number'] ?? null;
        // Convert dates from MM/DD/YYYY to YYYY-MM-DD for database
        $invoiceDate = formatDateForDatabase($data['invoice_date'] ?? date('Y-m-d'));
        $dueDate = formatDateForDatabase($data['due_date'] ?? $invoiceDate);
        $totalAmount = floatval($data['total_amount'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'SAR');
        $description = $data['description'] ?? '';
        $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null ? intval($data['customer_id']) : null;
        $entityType = isset($data['entity_type']) && $data['entity_type'] !== '' ? $data['entity_type'] : null;
        $entityId = isset($data['entity_id']) && $data['entity_id'] !== '' && $data['entity_id'] !== null ? intval($data['entity_id']) : null;
        
        // Payment voucher: auto-generated when empty (PV-00000001 format)
        $paymentVoucher = isset($data['payment_voucher']) && $data['payment_voucher'] !== '' ? trim($data['payment_voucher']) : null;
        
        // Tax (formerly VAT Report): from checkbox tax_included -> store as "tax_included" or "tax_not_included"
        $taxIncluded = isset($data['tax_included']) && ($data['tax_included'] === true || $data['tax_included'] === '1' || $data['tax_included'] === 'true');
        $vatReport = $taxIncluded ? 'tax_included' : 'tax_not_included';
        
        // Handle debit_account_id and credit_account_id - these are for journal entries, not invoices
        // For invoices, we calculate debit_amount and credit_amount from total_amount
        // debit_amount = 0 (no payment received yet)
        // credit_amount = total_amount (amount receivable)
        // IMPORTANT: These must be set BEFORE the INSERT to avoid NULL constraint errors
        $debitAmount = 0.00; // No payment received initially - must be 0.00 not null
        $creditAmount = floatval($totalAmount); // Full amount is receivable - ensure it's a float
        
        // Debug logging
        error_log("Invoice creation data: invoiceNumber=" . ($invoiceNumber ?? 'null') . ", invoiceDate=$invoiceDate, dueDate=$dueDate, totalAmount=$totalAmount, currency=$currency, customerId=" . ($customerId ?? 'null') . ", entityType=" . ($entityType ?? 'null') . ", entityId=" . ($entityId ?? 'null') . ", debitAmount=$debitAmount, creditAmount=$creditAmount");
        
        if ($totalAmount <= 0) {
            throw new Exception('Total amount must be greater than 0');
        }
        
        // Validate customer_id if customer_id column exists
        if ($useNewTable) {
            $colCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'customer_id'");
            if ($colCheck->num_rows > 0 && !$customerId) {
                // Customer ID is optional for new table (can use entity_type/entity_id instead)
                // But if provided, validate it exists
                if (isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null) {
                    $customerCheck = $conn->prepare("SELECT id FROM accounting_customers WHERE id = ? AND is_active = 1");
                    $customerCheck->bind_param('i', $customerId);
                    $customerCheck->execute();
                    if ($customerCheck->get_result()->num_rows === 0) {
                        throw new Exception('Selected customer not found or inactive');
                    }
                }
            }
        } elseif (!$useNewTable && !$customerId) {
            throw new Exception('Customer is required');
        }
        
        // Auto-generate invoice number if not provided
        if (empty($invoiceNumber)) {
            $refStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)) as max_num FROM accounts_receivable WHERE invoice_number LIKE 'INV-%'");
            if ($refStmt) {
                $refStmt->execute();
                $refResult = $refStmt->get_result();
                if ($refRow = $refResult->fetch_assoc()) {
                    $nextNum = ($refRow['max_num'] ?? 0) + 1;
                    $invoiceNumber = 'INV-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
                } else {
                    $invoiceNumber = 'INV-00000001';
                }
                $refStmt->close();
            } else {
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        }
        
        // Auto-generate payment voucher if not provided (PV-00000001 format)
        if (empty($paymentVoucher)) {
            $pvStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(payment_voucher, 4) AS UNSIGNED)) as max_num FROM accounts_receivable WHERE payment_voucher LIKE 'PV-%'");
            if ($pvStmt) {
                $pvStmt->execute();
                $pvResult = $pvStmt->get_result();
                $pvRow = $pvResult->fetch_assoc();
                if ($pvRow && isset($pvRow['max_num']) && $pvRow['max_num'] !== null) {
                    $nextNum = intval($pvRow['max_num']) + 1;
                    $paymentVoucher = 'PV-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
                } else {
                    $paymentVoucher = 'PV-00000001';
                }
                $pvStmt->close();
            } else {
                $paymentVoucher = 'PV-00000001';
            }
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                // Check if customer_id column exists
                $colCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'customer_id'");
                if ($colCheck->num_rows > 0) {
                    // Prepare statement with proper handling of null values
                    // Table columns: invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_amount, debit_amount, credit_amount, payment_voucher, vat_report, currency, description, customer_id, entity_type, entity_id, status, created_by
                    // We're inserting: invoice_number, invoice_date, due_date, total_amount, paid_amount (0), balance_amount, debit_amount, credit_amount, payment_voucher, vat_report, currency, description, customer_id, entity_type, entity_id, status ('Draft'), created_by
                    $stmt = $conn->prepare("
                        INSERT INTO accounts_receivable 
                        (invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_amount, debit_amount, credit_amount, payment_voucher, vat_report, currency, description, customer_id, entity_type, entity_id, status, created_by)
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
                    ");
                    
                    if (!$stmt) {
                        error_log("Failed to prepare statement: " . $conn->error);
                        throw new Exception('Failed to prepare statement: ' . $conn->error);
                    }
                    
                    $userId = $_SESSION['user_id'];
                    
                    // Ensure null values are properly set (not empty strings or false)
                    if ($customerId === '' || $customerId === false || $customerId === 0) {
                        $customerId = null;
                    }
                    if ($entityType === '' || $entityType === false) {
                        $entityType = null;
                    }
                    if ($entityId === '' || $entityId === false || $entityId === 0) {
                        $entityId = null;
                    }
                    
                    // For balance_amount, use the same value as total_amount initially
                    $balanceAmount = floatval($totalAmount);
                    
                    // Ensure debit_amount and credit_amount are set (they should be set earlier, but double-check here)
                    if (!isset($debitAmount) || $debitAmount === null) {
                        $debitAmount = 0.00;
                    }
                    if (!isset($creditAmount) || $creditAmount === null) {
                        $creditAmount = floatval($totalAmount);
                    }
                    
                    // Ensure all decimal values are properly formatted
                    $debitAmount = floatval($debitAmount);
                    $creditAmount = floatval($creditAmount);
                    $balanceAmount = floatval($balanceAmount);
                    $totalAmount = floatval($totalAmount);
                    
                    // Convert null integers to 0 for bind_param (mysqli doesn't handle null integers well)
                    // We'll use NULL in SQL instead
                    $customerIdParam = ($customerId === null) ? null : intval($customerId);
                    $entityIdParam = ($entityId === null) ? null : intval($entityId);
                    
                    // Build bind_param arguments array with references
                    // SQL: VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)
                    // That's 13 placeholders (excluding hardcoded 0 and 'Draft'):
                    // 1. invoice_number (s), 2. invoice_date (s), 3. due_date (s), 4. total_amount (d),
                    // 5. paid_amount (0 - hardcoded), 6. balance_amount (d), 7. debit_amount (d), 8. credit_amount (d),
                    // 9. payment_voucher (s), 10. vat_report (s), 11. currency (s), 12. description (s), 
                    // 13. customer_id (i), 14. entity_type (s), 15. entity_id (i), 16. created_by (i)
                    // Format: sssddddssssissi = 15 characters for 15 parameters
                    // 1-3: sss (invoice_number, invoice_date, due_date)
                    // 4-7: dddd (total_amount, balance_amount, debit_amount, credit_amount)
                    // 8-11: ssss (payment_voucher, vat_report, currency, description)
                    // 12: i (customer_id)
                    // 13: s (entity_type)
                    // 14: i (entity_id)
                    // 15: i (created_by)
                    $types = 'sssddddssssissi';
                    
                    $bindArgs = array($types);
                    $bindArgs[] = &$invoiceNumber;      // 1. s - string
                    $bindArgs[] = &$invoiceDate;        // 2. s - string  
                    $bindArgs[] = &$dueDate;            // 3. s - string
                    $bindArgs[] = &$totalAmount;        // 4. d - decimal (total_amount)
                    // 5. paid_amount = 0 (hardcoded in SQL)
                    $bindArgs[] = &$balanceAmount;      // 6. d - decimal (balance_amount)
                    $bindArgs[] = &$debitAmount;        // 7. d - decimal (debit_amount)
                    $bindArgs[] = &$creditAmount;        // 8. d - decimal (credit_amount)
                    $bindArgs[] = &$paymentVoucher;     // 9. s - string (can be null)
                    $bindArgs[] = &$vatReport;          // 10. s - string (can be null)
                    $bindArgs[] = &$currency;           // 11. s - string
                    $bindArgs[] = &$description;        // 12. s - string
                    $bindArgs[] = &$customerIdParam;    // 13. i - integer (can be null)
                    $bindArgs[] = &$entityType;         // 14. s - string (can be null)
                    $bindArgs[] = &$entityIdParam;      // 15. i - integer (can be null)
                    $bindArgs[] = &$userId;              // 16. i - integer
                    
                    // Verify counts match
                    $typeCount = strlen($types);
                    $paramCount = count($bindArgs) - 1; // Subtract 1 for the types string
                    if ($typeCount !== $paramCount) {
                        error_log("MISMATCH! Type string has $typeCount characters, but we have $paramCount parameters");
                        error_log("Type string: '$types'");
                        throw new Exception("Parameter count mismatch: Type string has $typeCount characters, but we have $paramCount parameters");
                    }
                    
                    // Use call_user_func_array with references
                    $bindResult = call_user_func_array(array($stmt, 'bind_param'), $bindArgs);
                    
                    if (!$bindResult) {
                        $stmtError = $stmt->error ?: 'Unknown bind_param error';
                        $connError = $conn->error ?: 'No connection error';
                        $errno = $stmt->errno ?: 'N/A';
                        error_log("bind_param failed: stmt_error='$stmtError' | stmt_errno='$errno' | conn_error='$connError'");
                        error_log("Parameters: invoiceNumber=" . var_export($invoiceNumber, true) . ", invoiceDate=" . var_export($invoiceDate, true) . ", totalAmount=" . var_export($totalAmount, true) . ", debitAmount=" . var_export($debitAmount, true) . ", creditAmount=" . var_export($creditAmount, true));
                        throw new Exception('Failed to bind parameters: ' . $stmtError . ' (Error #' . $errno . ')');
                    }
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO accounts_receivable 
                        (invoice_number, invoice_date, due_date, total_amount, paid_amount, balance_amount, currency, description, entity_type, entity_id, status, created_by)
                        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'Draft', ?)
                    ");
                    $userId = $_SESSION['user_id'];
                    $stmt->bind_param('sssddsssiii', $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $totalAmount, $currency, $description, $entityType, $entityId, $userId);
                }
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO accounting_invoices 
                    (invoice_number, invoice_date, due_date, total_amount, paid_amount, customer_id, status, created_by)
                    VALUES (?, ?, ?, ?, 0, ?, 'Draft', ?)
                ");
                $userId = $_SESSION['user_id'];
                if ($customerId) {
                    $stmt->bind_param('sssdi', $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $customerId, $userId);
                } else {
                    // If no customer_id provided, use a default or throw error
                    throw new Exception('Customer ID is required');
                }
            }
            
            // Execute the statement
            if (!$stmt->execute()) {
                $stmtError = $stmt->error ?: 'Unknown execute error';
                $connError = $conn->error ?: 'No connection error';
                $errno = $stmt->errno ?: 'N/A';
                error_log("Statement execute failed: stmt_error='$stmtError' | stmt_errno='$errno' | conn_error='$connError'");
                throw new Exception('Failed to execute statement: ' . $stmtError . ' (Error #' . $errno . ')');
            }
            
            $invoiceId = $conn->insert_id;
            
            // Auto-create journal entry if status is Posted
            $status = $data['status'] ?? 'Draft';
            $journalResult = null;
            if ($status === 'Posted') {
                try {
                    $vatAmount = isset($data['vat_amount']) ? floatval($data['vat_amount']) : null;
                    $vatRate = isset($data['vat_rate']) ? floatval($data['vat_rate']) : 15;
                    $costCenterId = isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
                    $journalResult = createInvoiceJournalEntry(
                        $conn,
                        $invoiceId,
                        $invoiceNumber,
                        $invoiceDate,
                        $totalAmount,
                        $vatAmount,
                        $vatRate,
                        $costCenterId,
                        $description
                    );
                    if (!$journalResult['success']) {
                        error_log("WARNING: Failed to create journal entry for invoice {$invoiceId}: " . $journalResult['message']);
                    }
                } catch (Exception $je) {
                    error_log("WARNING: Journal entry creation failed for invoice {$invoiceId}: " . $je->getMessage());
                    // Don't fail the invoice creation if journal entry fails
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice_id' => $invoiceId,
                'journal_entry' => $journalResult
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        // Update invoice
        $invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($invoiceId <= 0) {
            throw new Exception('Invoice ID is required');
        }
        
        // Get current invoice data to check status change
        $oldInvoice = null;
        if ($useNewTable) {
            $oldInvoiceStmt = $conn->prepare("SELECT invoice_number, invoice_date, total_amount, status FROM accounts_receivable WHERE id = ?");
        } else {
            $oldInvoiceStmt = $conn->prepare("SELECT invoice_number, invoice_date, total_amount, status FROM accounting_invoices WHERE id = ?");
        }
        if ($oldInvoiceStmt) {
            $oldInvoiceStmt->bind_param('i', $invoiceId);
            $oldInvoiceStmt->execute();
            $oldInvoiceResult = $oldInvoiceStmt->get_result();
            $oldInvoice = $oldInvoiceResult->fetch_assoc();
            $oldInvoiceResult->free();
            $oldInvoiceStmt->close();
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                // Check if customer_id column exists
                $colCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'customer_id'");
                $customerId = isset($data['customer_id']) ? intval($data['customer_id']) : null;
                if ($colCheck->num_rows > 0 && $customerId) {
                    $stmt = $conn->prepare("
                        UPDATE accounts_receivable 
                        SET invoice_date = ?, due_date = ?, total_amount = ?, currency = ?, description = ?, customer_id = ?, status = ?
                        WHERE id = ?
                    ");
                    $invoiceDate = $data['invoice_date'] ?? null;
                    $dueDate = $data['due_date'] ?? null;
                    $totalAmount = floatval($data['total_amount'] ?? 0);
                    $currency = strtoupper($data['currency'] ?? 'SAR');
                    $description = $data['description'] ?? '';
                    $status = $data['status'] ?? 'Draft';
                    $stmt->bind_param('ssdsssii', $invoiceDate, $dueDate, $totalAmount, $currency, $description, $customerId, $status, $invoiceId);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE accounts_receivable 
                        SET invoice_date = ?, due_date = ?, total_amount = ?, currency = ?, description = ?, status = ?
                        WHERE id = ?
                    ");
                    $invoiceDate = $data['invoice_date'] ?? null;
                    $dueDate = $data['due_date'] ?? null;
                    $totalAmount = floatval($data['total_amount'] ?? 0);
                    $currency = strtoupper($data['currency'] ?? 'SAR');
                    $description = $data['description'] ?? '';
                    $status = $data['status'] ?? 'Draft';
                    $stmt->bind_param('ssdsssi', $invoiceDate, $dueDate, $totalAmount, $currency, $description, $status, $invoiceId);
                }
            } else {
                $customerId = isset($data['customer_id']) ? intval($data['customer_id']) : null;
                $stmt = $conn->prepare("
                    UPDATE accounting_invoices 
                    SET invoice_date = ?, due_date = ?, total_amount = ?, customer_id = ?, status = ?
                    WHERE id = ?
                ");
                $invoiceDate = $data['invoice_date'] ?? null;
                $dueDate = $data['due_date'] ?? null;
                $totalAmount = floatval($data['total_amount'] ?? 0);
                $status = $data['status'] ?? 'Draft';
                if ($customerId) {
                    $stmt->bind_param('ssdsii', $invoiceDate, $dueDate, $totalAmount, $customerId, $status, $invoiceId);
                } else {
                    $stmt->bind_param('ssdsi', $invoiceDate, $dueDate, $totalAmount, $status, $invoiceId);
                }
            }
            $stmt->execute();
            
            // Auto-create journal entry if status changed to Posted
            $newStatus = $data['status'] ?? ($oldInvoice['status'] ?? 'Draft');
            $oldStatus = $oldInvoice['status'] ?? 'Draft';
            $journalResult = null;
            if ($newStatus === 'Posted' && $oldStatus !== 'Posted') {
                try {
                    // Get invoice number and date
                    $invoiceNumber = $oldInvoice['invoice_number'] ?? '';
                    $invoiceDate = $data['invoice_date'] ?? ($oldInvoice['invoice_date'] ?? date('Y-m-d'));
                    $totalAmount = floatval($data['total_amount'] ?? ($oldInvoice['total_amount'] ?? 0));
                    $vatAmount = isset($data['vat_amount']) ? floatval($data['vat_amount']) : null;
                    $vatRate = isset($data['vat_rate']) ? floatval($data['vat_rate']) : 15;
                    $costCenterId = isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
                    $description = $data['description'] ?? '';
                    
                    $journalResult = createInvoiceJournalEntry(
                        $conn,
                        $invoiceId,
                        $invoiceNumber,
                        $invoiceDate,
                        $totalAmount,
                        $vatAmount,
                        $vatRate,
                        $costCenterId,
                        $description
                    );
                    if (!$journalResult['success']) {
                        error_log("WARNING: Failed to create journal entry for invoice {$invoiceId}: " . $journalResult['message']);
                    }
                } catch (Exception $je) {
                    error_log("WARNING: Journal entry creation failed for invoice {$invoiceId}: " . $je->getMessage());
                    // Don't fail the invoice update if journal entry fails
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'journal_entry' => $journalResult
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        // Delete invoice
        $invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($invoiceId <= 0) {
            throw new Exception('Invoice ID is required');
        }
        
        $conn->begin_transaction();
        try {
            if ($useNewTable) {
                $stmt = $conn->prepare("DELETE FROM accounts_receivable WHERE id = ?");
            } else {
                $stmt = $conn->prepare("DELETE FROM accounting_invoices WHERE id = ?");
            }
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Invoice deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
