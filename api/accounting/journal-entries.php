<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/journal-entries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/journal-entries.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) {
    require_once __DIR__ . '/../core/date-helper.php';
} elseif (file_exists(__DIR__ . '/core/date-helper.php')) {
    require_once __DIR__ . '/core/date-helper.php';
}
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) {
        if (empty($s)) return null;
        $s = trim($s);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0];
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        $t = strtotime($s);
        return $t ? date('Y-m-d', $t) : null;
    }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) {
        if (empty($s) || $s === '0000-00-00') return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]);
        $t = strtotime($s);
        return $t ? date('m/d/Y', $t) : $s;
    }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) {
        if (!is_array($data)) return $data;
        $fields = $fields ?? ['date','entry_date','invoice_date','due_date','created_at','updated_at','transaction_date'];
        foreach ($data as $k => $v) {
            if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields);
            elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v);
        }
        return $data;
    }
}
require_once __DIR__ . '/core/erp-posting-controls.php';
require_once __DIR__ . '/core/audit-trail-helper.php';

header('Content-Type: application/json');

// Set up error handler to catch fatal errors and ensure JSON response
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'],
            'error' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
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
try {
    if ($method === 'GET') {
        enforceApiPermission('journal-entries', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('journal-entries', 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission('journal-entries', 'update');
    } elseif ($method === 'DELETE') {
        enforceApiPermission('journal-entries', 'delete');
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $entryId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $dateFrom = isset($_GET['date_from']) ? formatDateForDatabase($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? formatDateForDatabase($_GET['date_to']) : null;
    $accountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;
    $includeDraft = isset($_GET['include_draft']) && in_array(strtolower(strval($_GET['include_draft'])), ['1', 'true', 'yes'], true);

    // If requesting a single entry by ID (GET request only - PUT requests are handled separately)
    if ($entryId && $method === 'GET') {
        $entry = null;
        
        // First check journal_entries table
        $tableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
        if ($tableCheck->num_rows > 0) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'entry_type'");
            $hasEntryType = $columnCheck->num_rows > 0;
            $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'reference_number'");
            $hasReferenceNumber = $columnCheck->num_rows > 0;
            $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
            $hasCurrency = $columnCheck->num_rows > 0;
            
            $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'created_at'");
            $hasCreatedAt = $columnCheck->num_rows > 0;
            
            $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'updated_at'");
            $hasUpdatedAt = $columnCheck->num_rows > 0;
            
            $entryTypeField = $hasEntryType ? 'je.entry_type' : "'Manual'";
            $referenceField = $hasReferenceNumber ? 'je.reference_number' : "''";
            $currencyField = $hasCurrency ? 'je.currency' : "'SAR'";
            $createdAtField = $hasCreatedAt ? 'je.created_at' : 'NULL';
            $updatedAtField = $hasUpdatedAt ? 'je.updated_at' : 'NULL';
            
            $query = "
                SELECT 
                    je.id,
                    je.entry_number as entry_number,
                    je.entry_date as entry_date,
                    je.description,
                    ($entryTypeField) as entry_type,
                    je.total_debit,
                    je.total_credit,
                    je.status,
                    ($referenceField) as reference_number,
                    ($currencyField) as currency,
                    u.username as created_by_name,
                    ($createdAtField) as created_at,
                    ($updatedAtField) as updated_at,
                    'journal' as source
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.user_id
                WHERE je.id = ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $entryId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Check if entity columns exist in journal_entry_lines
                $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
                $hasEntityType = $entityTypeCheck->num_rows > 0;
                
                $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
                $hasEntityId = $entityIdCheck->num_rows > 0;
                
                // If columns don't exist, try to create them
                if (!$hasEntityType) {
                    try {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_type VARCHAR(50) NULL AFTER credit_amount");
                        $hasEntityType = true;
                    } catch (Exception $e) {
                        // Column creation failed, continue without it
                    }
                }
                
                if (!$hasEntityId) {
                    try {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_id INT(11) NULL AFTER entity_type");
                        $hasEntityId = true;
                    } catch (Exception $e) {
                        // Column creation failed, continue without it
                    }
                }
                
                // Build query based on available columns
                if ($hasEntityType && $hasEntityId) {
                    $lineQuery = "SELECT account_id, entity_type, entity_id FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1";
                } else {
                $lineQuery = "SELECT account_id FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1";
                }
                
                $lineStmt = $conn->prepare($lineQuery);
                $lineStmt->bind_param('i', $entryId);
                $lineStmt->execute();
                $lineResult = $lineStmt->get_result();
                $accountId = null;
                $accountName = null;
                $entityType = null;
                $entityId = null;
                if ($lineRow = $lineResult->fetch_assoc()) {
                    $accountId = $lineRow['account_id'];
                    if ($hasEntityType && $hasEntityId) {
                        $entityType = $lineRow['entity_type'] ?? null;
                        $entityId = isset($lineRow['entity_id']) ? intval($lineRow['entity_id']) : null;
                    }
                    // Get account name
                    if ($accountId) {
                        $accountQuery = "SELECT account_name, account_code FROM financial_accounts WHERE id = ? LIMIT 1";
                        $accountStmt = $conn->prepare($accountQuery);
                        $accountStmt->bind_param('i', $accountId);
                        $accountStmt->execute();
                        $accountResult = $accountStmt->get_result();
                        if ($accountRow = $accountResult->fetch_assoc()) {
                            $accountName = ($accountRow['account_code'] ? $accountRow['account_code'] . ' - ' : '') . $accountRow['account_name'];
                        }
                    }
                }
                
                    // Fetch entity name if entity_type and entity_id are available
                    $entityName = null;
                    if ($entityType && $entityId > 0) {
                        // Check if entity tables exist
                        $agentTableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
                        $hasAgentTable = $agentTableCheck->num_rows > 0;
                        $subagentTableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
                        $hasSubagentTable = $subagentTableCheck->num_rows > 0;
                        $workerTableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
                        $hasWorkerTable = $workerTableCheck->num_rows > 0;
                        $hrTableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
                        $hasHrTable = $hrTableCheck->num_rows > 0;
                        
                        if ($entityType === 'agent' && $hasAgentTable) {
                            $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_name'");
                            $agentNameCol = ($agentColCheck->num_rows > 0) ? 'agent_name' : 'name';
                            $nameQuery = "SELECT `{$agentNameCol}` as name FROM agents WHERE id = ? LIMIT 1";
                            $nameStmt = $conn->prepare($nameQuery);
                            $nameStmt->bind_param('i', $entityId);
                            $nameStmt->execute();
                            $nameResult = $nameStmt->get_result();
                            if ($nameRow = $nameResult->fetch_assoc()) {
                                $entityName = $nameRow['name'] ?? null;
                            }
                        } elseif ($entityType === 'subagent' && $hasSubagentTable) {
                            $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'subagent_name'");
                            $subagentNameCol = ($subagentColCheck->num_rows > 0) ? 'subagent_name' : 'name';
                            $nameQuery = "SELECT `{$subagentNameCol}` as name FROM subagents WHERE id = ? LIMIT 1";
                            $nameStmt = $conn->prepare($nameQuery);
                            $nameStmt->bind_param('i', $entityId);
                            $nameStmt->execute();
                            $nameResult = $nameStmt->get_result();
                            if ($nameRow = $nameResult->fetch_assoc()) {
                                $entityName = $nameRow['name'] ?? null;
                            }
                        } elseif ($entityType === 'worker' && $hasWorkerTable) {
                            $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'worker_name'");
                            $workerNameCol = ($workerColCheck->num_rows > 0) ? 'worker_name' : 'name';
                            $nameQuery = "SELECT `{$workerNameCol}` as name FROM workers WHERE id = ? LIMIT 1";
                            $nameStmt = $conn->prepare($nameQuery);
                            $nameStmt->bind_param('i', $entityId);
                            $nameStmt->execute();
                            $nameResult = $nameStmt->get_result();
                            if ($nameRow = $nameResult->fetch_assoc()) {
                                $entityName = $nameRow['name'] ?? null;
                            }
                        } elseif ($entityType === 'hr' && $hasHrTable) {
                            $hrColCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'name'");
                            $hrNameCol = ($hrColCheck->num_rows > 0) ? 'name' : 'full_name';
                            $nameQuery = "SELECT `{$hrNameCol}` as name FROM employees WHERE id = ? LIMIT 1";
                            $nameStmt = $conn->prepare($nameQuery);
                            $nameStmt->bind_param('i', $entityId);
                            $nameStmt->execute();
                            $nameResult = $nameStmt->get_result();
                            if ($nameRow = $nameResult->fetch_assoc()) {
                                $entityName = $nameRow['name'] ?? null;
                        }
                    }
                }
                
                // Normalize currency value - ensure it's always a valid string
                $currencyValue = $row['currency'] ?? null;
                if (empty($currencyValue) || $currencyValue === null) {
                    $currencyValue = 'SAR';
                } else {
                    // Extract currency code if format is "CODE - Name"
                    if (strpos($currencyValue, ' - ') !== false) {
                        $currencyValue = trim(explode(' - ', $currencyValue)[0]);
                    }
                    $currencyValue = strtoupper(trim($currencyValue));
                }
                
                // Normalize entry_type value
                $entryTypeValue = $row['entry_type'] ?? null;
                if (empty($entryTypeValue) || $entryTypeValue === null) {
                    $entryTypeValue = 'Manual';
                } else {
                    $entryTypeValue = trim($entryTypeValue);
                    // Capitalize first letter, rest lowercase for consistency
                    $entryTypeValue = ucfirst(strtolower($entryTypeValue));
                }
                
                // Ensure entry_date is in YYYY-MM-DD format
                $entryDate = $row['entry_date'] ?? null;
                if ($entryDate) {
                    // If it's a date object or string, ensure it's formatted correctly
                    if (is_string($entryDate)) {
                        // If already in YYYY-MM-DD format, use as-is
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                            // Try to convert to YYYY-MM-DD
                            $dateObj = date_create($entryDate);
                            if ($dateObj) {
                                $entryDate = date_format($dateObj, 'Y-m-d');
                            }
                        }
                    } else {
                        // If it's a DateTime object, format it
                        $entryDate = date_format($entryDate, 'Y-m-d');
                    }
                }
                
                $entry = [
                    'id' => $row['id'],
                    'entry_number' => $row['entry_number'],
                    'entry_date' => $entryDate ? formatDateForDisplay($entryDate) : '',
                    'description' => $row['description'],
                    'entry_type' => $entryTypeValue,
                    'total_debit' => floatval($row['total_debit'] ?? 0),
                    'total_credit' => floatval($row['total_credit'] ?? 0),
                    'status' => $row['status'],
                    'reference_number' => $row['reference_number'] ?? '',
                    'currency' => $currencyValue,
                    'account_id' => $accountId,
                    'account_name' => $accountName,
                        'entity_type' => $entityType,
                        'entity_id' => $entityId,
                        'entity_name' => $entityName,
                    'created_by_name' => $row['created_by_name'] ?? '',
                    'created_at' => $row['created_at'] ? formatDateForDisplay($row['created_at']) : null,
                    'updated_at' => $row['updated_at'] ? formatDateForDisplay($row['updated_at']) : null,
                    'source' => 'journal'
                ];
                // Format all dates in entry
                $entry = formatDatesInArray($entry);
            }
        }
        
        // If not found in journal_entries, check financial_transactions
        if (!$entry) {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
            if ($tableCheck->num_rows > 0) {
                $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
                $hasCurrency = $columnCheck->num_rows > 0;
                
                $currencyField = $hasCurrency ? 'ft.currency' : "'SAR'";
                
                $query = "
                    SELECT 
                        ft.id,
                        COALESCE(ft.reference_number, CONCAT('TXN-', ft.id)) as entry_number,
                        ft.transaction_date as entry_date,
                        ft.description,
                        ft.transaction_type as entry_type,
                        COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as total_debit,
                        COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as total_credit,
                        ft.status,
                        ft.reference_number,
                        ($currencyField) as currency,
                        u.username as created_by_name,
                        'transaction' as source
                    FROM financial_transactions ft
                    LEFT JOIN users u ON ft.created_by = u.user_id
                    WHERE ft.id = ?
                ";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $entryId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $entry = [
                        'id' => $row['id'],
                        'entry_number' => $row['entry_number'],
                        'entry_date' => $row['entry_date'],
                        'description' => $row['description'],
                        'entry_type' => $row['entry_type'],
                        'total_debit' => floatval($row['total_debit'] ?? 0),
                        'total_credit' => floatval($row['total_credit'] ?? 0),
                        'status' => $row['status'],
                        'reference_number' => $row['reference_number'] ?? '',
                        'currency' => $row['currency'] ?? 'SAR',
                        'account_id' => null,
                        'created_by_name' => $row['created_by_name'] ?? '',
                        'source' => 'transaction'
                    ];
                }
            }
        }
        
        // Load entry lines if requested
        $lines = [];
        if (isset($_GET['lines']) && $_GET['lines'] === 'true' && $entry) {
            // Build safe SELECT based on existing columns
            $hasCostCenterCol = false;
            $ccCol = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
            $hasCostCenterCol = $ccCol && $ccCol->num_rows > 0;
            if ($ccCol) $ccCol->free();

            $hasDescCol = false;
            $descCol = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'description'");
            $hasDescCol = $descCol && $descCol->num_rows > 0;
            if ($descCol) $descCol->free();

            $hasVatCol = false;
            $vatCol = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'vat_report'");
            $hasVatCol = $vatCol && $vatCol->num_rows > 0;
            if ($vatCol) $vatCol->free();

            $selectFields = [
                'account_id',
                'debit_amount',
                'credit_amount'
            ];
            $selectFields[] = $hasCostCenterCol ? 'cost_center_id' : 'NULL as cost_center_id';
            $selectFields[] = $hasDescCol ? 'description' : "'' as description";
            $selectFields[] = $hasVatCol ? 'vat_report' : "0 as vat_report";

            $linesQuery = "SELECT " . implode(', ', $selectFields) . "
                FROM journal_entry_lines
                WHERE journal_entry_id = ?
                ORDER BY id ASC";

            $linesStmt = $conn->prepare($linesQuery);
            if ($linesStmt) {
                $linesStmt->bind_param('i', $entryId);
                $linesStmt->execute();
                $linesResult = $linesStmt->get_result();
                while ($lineRow = $linesResult->fetch_assoc()) {
                    $lines[] = [
                        'account_id' => $lineRow['account_id'],
                        'debit_amount' => floatval($lineRow['debit_amount'] ?? 0),
                        'credit_amount' => floatval($lineRow['credit_amount'] ?? 0),
                        'cost_center_id' => $lineRow['cost_center_id'] ?? null,
                        'description' => $lineRow['description'] ?? '',
                        'vat_report' => !empty($lineRow['vat_report'])
                    ];
                }
                $linesStmt->close();
            }
        }
        
        if ($entry) {
            $response = [
                'success' => true,
                'entry' => $entry
            ];
            if (!empty($lines)) {
                $response['lines'] = $lines;
            }
            echo json_encode($response);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Journal entry not found'
            ]);
        }
        exit;
    }

    // Check if journal_entries table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
    $useNewTable = $tableCheck->num_rows > 0;
    
    // Check if journal_entry_lines table exists (needed for entity data)
    $linesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
    $hasLinesTable = $linesTableCheck->num_rows > 0;
    
    $entries = [];
    $accountFilterApplied = false; // Define outside the block so it's accessible later
    
    // First, get journal entries if table exists
    if ($useNewTable) {
        // Check if entry_type column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'entry_type'");
        $hasEntryType = $columnCheck->num_rows > 0;

        // Check if reference_number column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'reference_number'");
        $hasReferenceNumber = $columnCheck->num_rows > 0;
        
        // Check if currency column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
        $hasCurrency = $columnCheck->num_rows > 0;
        
        $entryTypeField = $hasEntryType ? 'je.entry_type' : "'Manual'";
        $referenceField = $hasReferenceNumber ? 'je.reference_number' : "''";
        $currencyField = $hasCurrency ? 'je.currency' : "'SAR'";
        
        $query = null;
        $conditions = [];
        $params = [];
        $types = '';

        // If account filter is requested, check if we can apply it
        if ($accountId) {
            if ($hasLinesTable) {
                // Check if account_id column exists in journal_entry_lines
                $linesColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'account_id'");
                $hasAccountIdCol = $linesColCheck->num_rows > 0;
                
                if ($hasAccountIdCol) {
                    // Build query with account filter
                    $query = "
                        SELECT DISTINCT
                            je.id,
                            je.entry_number as entry_number,
                            je.entry_date as entry_date,
                            je.description,
                            ($entryTypeField) as entry_type,
                            je.total_debit,
                            je.total_credit,
                            je.status,
                            ($referenceField) as reference_number,
                            ($currencyField) as currency,
                            u.username as created_by_name,
                            'journal' as source
                        FROM journal_entries je
                        INNER JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
                        LEFT JOIN users u ON je.created_by = u.user_id
                    ";
                    $conditions[] = "jel.account_id = ?";
                    $params[] = $accountId;
                    $types .= 'i';
                    $accountFilterApplied = true;
                }
            }
        }
        
        // If no account filter or account filter couldn't be applied, use normal query
        if (!$accountFilterApplied) {
            $query = "
                SELECT 
                    je.id,
                    je.entry_number as entry_number,
                    je.entry_date as entry_date,
                    je.description,
                    ($entryTypeField) as entry_type,
                    je.total_debit,
                    je.total_credit,
                    je.status,
                    ($referenceField) as reference_number,
                    ($currencyField) as currency,
                    u.username as created_by_name,
                    'journal' as source
                FROM journal_entries je
                LEFT JOIN users u ON je.created_by = u.user_id
            ";
        }

        // Add date filters
        if ($dateFrom) {
            $conditions[] = "je.entry_date >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo) {
            $conditions[] = "je.entry_date <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }

        // Default: show only Posted/Approved entries (General Ledger).
        // If include_draft=1, also include Draft entries so freshly created entries appear.
        $conditions[] = $includeDraft
            ? "je.status IN ('Posted', 'Approved', 'Draft')"
            : "je.status IN ('Posted', 'Approved')";

        // Execute query
        if ($query) {
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }

            // Order by ID DESC only - newest entries (highest IDs) appear first
            $query .= " ORDER BY je.id DESC LIMIT 100";

            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Check if entity columns exist in journal_entry_lines for list queries
            $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
            $hasEntityTypeCol = $entityTypeCheck->num_rows > 0;
            
            $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
            $hasEntityIdCol = $entityIdCheck->num_rows > 0;
            
            // If columns don't exist, try to create them (only once, before the loop)
            if ($hasLinesTable && (!$hasEntityTypeCol || !$hasEntityIdCol)) {
                try {
                    if (!$hasEntityTypeCol) {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_type VARCHAR(50) NULL AFTER credit_amount");
                        $hasEntityTypeCol = true;
                    }
                    if (!$hasEntityIdCol) {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_id INT(11) NULL AFTER entity_type");
                        $hasEntityIdCol = true;
                    }
                } catch (Exception $e) {
                    // Column creation failed, continue without it
                }
            }
            
            // Detect entity table column names BEFORE the loop (for efficiency)
            $agentNameCol = null;
            $subagentNameCol = null;
            $workerNameCol = null;
            $hrNameCol = null;
            
            $agentTableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
            if ($agentTableCheck->num_rows > 0) {
                $colCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_name'");
                if ($colCheck->num_rows > 0) {
                    $agentNameCol = 'agent_name';
                } else {
                    $colCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'full_name'");
                    if ($colCheck->num_rows > 0) {
                        $agentNameCol = 'full_name';
                    } else {
                        $colCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'name'");
                        $agentNameCol = $colCheck->num_rows > 0 ? 'name' : null;
                    }
                }
            }
            
            $subagentTableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
            if ($subagentTableCheck->num_rows > 0) {
                $colCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'subagent_name'");
                if ($colCheck->num_rows > 0) {
                    $subagentNameCol = 'subagent_name';
                } else {
                    $colCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'full_name'");
                    if ($colCheck->num_rows > 0) {
                        $subagentNameCol = 'full_name';
                    } else {
                        $colCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'name'");
                        $subagentNameCol = $colCheck->num_rows > 0 ? 'name' : null;
                    }
                }
            }
            
            $workerTableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
            if ($workerTableCheck->num_rows > 0) {
                $colCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'worker_name'");
                if ($colCheck->num_rows > 0) {
                    $workerNameCol = 'worker_name';
                } else {
                    $colCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'full_name'");
                    if ($colCheck->num_rows > 0) {
                        $workerNameCol = 'full_name';
                    } else {
                        $colCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'name'");
                        $workerNameCol = $colCheck->num_rows > 0 ? 'name' : null;
                    }
                }
            }
            
            $hrTableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
            if ($hrTableCheck->num_rows > 0) {
                $colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'name'");
                if ($colCheck->num_rows > 0) {
                    $hrNameCol = 'name';
                } else {
                    $colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'full_name'");
                    $hrNameCol = $colCheck->num_rows > 0 ? 'full_name' : null;
                }
            }
            
            while ($row = $result->fetch_assoc()) {
                // Normalize currency value - ensure it's always a valid string
                $currencyValue = $row['currency'] ?? null;
                if (empty($currencyValue) || $currencyValue === null) {
                    $currencyValue = 'SAR';
                } else {
                    // Extract currency code if format is "CODE - Name"
                    if (strpos($currencyValue, ' - ') !== false) {
                        $currencyValue = trim(explode(' - ', $currencyValue)[0]);
                    }
                    $currencyValue = strtoupper(trim($currencyValue));
                }
                
                // Normalize entry_type value
                $entryTypeValue = $row['entry_type'] ?? null;
                if (empty($entryTypeValue) || $entryTypeValue === null) {
                    $entryTypeValue = 'Manual';
                } else {
                    $entryTypeValue = trim($entryTypeValue);
                    // Capitalize first letter, rest lowercase for consistency
                    $entryTypeValue = ucfirst(strtolower($entryTypeValue));
                }
                
                // Add entity information if available
                $entry = $row;
                $entry['currency'] = $currencyValue;
                $entry['entry_type'] = $entryTypeValue;
                
                // Try to get entity info and account info from journal_entry_lines
                if ($hasLinesTable) {
                    // Check if account_id column exists
                    $lineColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'account_id'");
                    $hasAccountIdCol = $lineColCheck->num_rows > 0;
                    
                    // Get debit and credit side accounts
                    $debitAccountName = null;
                    $creditAccountName = null;
                    
                    if ($hasAccountIdCol) {
                        // Get first debit account
                        $debitQuery = "SELECT jel.account_id, fa.account_name, fa.account_code 
                                      FROM journal_entry_lines jel 
                                      LEFT JOIN financial_accounts fa ON jel.account_id = fa.id 
                                      WHERE jel.journal_entry_id = ? AND jel.debit_amount > 0 
                                      LIMIT 1";
                        $debitStmt = $conn->prepare($debitQuery);
                        $debitStmt->bind_param('i', $row['id']);
                        $debitStmt->execute();
                        $debitResult = $debitStmt->get_result();
                        if ($debitRow = $debitResult->fetch_assoc()) {
                            $debitAccountName = ($debitRow['account_code'] ? $debitRow['account_code'] . ' - ' : '') . $debitRow['account_name'];
                        }
                        
                        // Get first credit account
                        $creditQuery = "SELECT jel.account_id, fa.account_name, fa.account_code 
                                       FROM journal_entry_lines jel 
                                       LEFT JOIN financial_accounts fa ON jel.account_id = fa.id 
                                       WHERE jel.journal_entry_id = ? AND jel.credit_amount > 0 
                                       LIMIT 1";
                        $creditStmt = $conn->prepare($creditQuery);
                        $creditStmt->bind_param('i', $row['id']);
                        $creditStmt->execute();
                        $creditResult = $creditStmt->get_result();
                        if ($creditRow = $creditResult->fetch_assoc()) {
                            $creditAccountName = ($creditRow['account_code'] ? $creditRow['account_code'] . ' - ' : '') . $creditRow['account_name'];
                        }
                    }
                    
                    // Check for cost_center_id column
                    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
                    $hasCostCenterCol = $costCenterCheck->num_rows > 0;
                    if ($costCenterCheck) {
                        $costCenterCheck->free();
                    }
                    
                    // Build query based on available columns
                    $lineFields = [];
                    if ($hasAccountIdCol) $lineFields[] = 'account_id';
                    if ($hasEntityTypeCol) $lineFields[] = 'entity_type';
                    if ($hasEntityIdCol) $lineFields[] = 'entity_id';
                    if ($hasCostCenterCol) $lineFields[] = 'cost_center_id';
                    
                    if (!empty($lineFields)) {
                        $entityQuery = "SELECT " . implode(', ', $lineFields) . " FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1";
                    } else {
                        $entityQuery = null;
                    }
                    
                    if ($entityQuery) {
                        $entityStmt = $conn->prepare($entityQuery);
                        $entityStmt->bind_param('i', $row['id']);
                        $entityStmt->execute();
                        $entityResult = $entityStmt->get_result();
                        if ($entityRow = $entityResult->fetch_assoc()) {
                            // Get account information if account_id column exists
                            if ($hasAccountIdCol && isset($entityRow['account_id']) && $entityRow['account_id']) {
                                $lineAccountId = intval($entityRow['account_id']);
                                $accountQuery = "SELECT account_name, account_code FROM financial_accounts WHERE id = ? LIMIT 1";
                                $accountStmt = $conn->prepare($accountQuery);
                                $accountStmt->bind_param('i', $lineAccountId);
                                $accountStmt->execute();
                                $accountResult = $accountStmt->get_result();
                                if ($accountRow = $accountResult->fetch_assoc()) {
                                    $entry['account_id'] = $lineAccountId;
                                    $entry['account_name'] = ($accountRow['account_code'] ? $accountRow['account_code'] . ' - ' : '') . $accountRow['account_name'];
                                }
                                $accountResult->free();
                                $accountStmt->close();
                            }
                            
                            // Get cost center information if cost_center_id column exists
                            if ($hasCostCenterCol && isset($entityRow['cost_center_id']) && $entityRow['cost_center_id']) {
                                $costCenterId = intval($entityRow['cost_center_id']);
                                $entry['cost_center_id'] = $costCenterId;
                                
                                // Try to get cost center name
                                $costCenterTableCheck = $conn->query("SHOW TABLES LIKE 'cost_centers'");
                                if ($costCenterTableCheck && $costCenterTableCheck->num_rows > 0) {
                                    $costCenterQuery = "SELECT code, name FROM cost_centers WHERE id = ? LIMIT 1";
                                    $costCenterStmt = $conn->prepare($costCenterQuery);
                                    if ($costCenterStmt) {
                                        $costCenterStmt->bind_param('i', $costCenterId);
                                        $costCenterStmt->execute();
                                        $costCenterResult = $costCenterStmt->get_result();
                                        if ($costCenterRow = $costCenterResult->fetch_assoc()) {
                                            $entry['cost_center_name'] = ($costCenterRow['code'] ? $costCenterRow['code'] . ' - ' : '') . $costCenterRow['name'];
                                        }
                                        $costCenterResult->free();
                                        $costCenterStmt->close();
                                    }
                                }
                                if ($costCenterTableCheck) {
                                    $costCenterTableCheck->free();
                                }
                            }
                            
                            // Get entity information if entity columns exist
                            if ($hasEntityTypeCol && $hasEntityIdCol) {
                                $entry['entity_type'] = $entityRow['entity_type'] ?? null;
                                $entry['entity_id'] = isset($entityRow['entity_id']) ? intval($entityRow['entity_id']) : null;
                                
                                // Look up entity name
                                if ($entry['entity_type'] && $entry['entity_id']) {
                                    $entityName = null;
                                    if ($entry['entity_type'] === 'agent' && $agentNameCol) {
                                        $nameQuery = "SELECT `{$agentNameCol}` as name FROM agents WHERE id = ? LIMIT 1";
                                        $nameStmt = $conn->prepare($nameQuery);
                                        $nameStmt->bind_param('i', $entry['entity_id']);
                                        $nameStmt->execute();
                                        $nameResult = $nameStmt->get_result();
                                        if ($nameRow = $nameResult->fetch_assoc()) {
                                            $entityName = $nameRow['name'] ?? null;
                                        }
                                    } elseif ($entry['entity_type'] === 'subagent' && $subagentNameCol) {
                                        $nameQuery = "SELECT `{$subagentNameCol}` as name FROM subagents WHERE id = ? LIMIT 1";
                                        $nameStmt = $conn->prepare($nameQuery);
                                        $nameStmt->bind_param('i', $entry['entity_id']);
                                        $nameStmt->execute();
                                        $nameResult = $nameStmt->get_result();
                                        if ($nameRow = $nameResult->fetch_assoc()) {
                                            $entityName = $nameRow['name'] ?? null;
                                        }
                                    } elseif ($entry['entity_type'] === 'worker' && $workerNameCol) {
                                        $nameQuery = "SELECT `{$workerNameCol}` as name FROM workers WHERE id = ? LIMIT 1";
                                        $nameStmt = $conn->prepare($nameQuery);
                                        $nameStmt->bind_param('i', $entry['entity_id']);
                                        $nameStmt->execute();
                                        $nameResult = $nameStmt->get_result();
                                        if ($nameRow = $nameResult->fetch_assoc()) {
                                            $entityName = $nameRow['name'] ?? null;
                                        }
                                    } elseif (($entry['entity_type'] === 'hr' || $entry['entity_type'] === 'employee') && $hrNameCol) {
                                        $nameQuery = "SELECT `{$hrNameCol}` as name FROM employees WHERE id = ? LIMIT 1";
                                        $nameStmt = $conn->prepare($nameQuery);
                                        $nameStmt->bind_param('i', $entry['entity_id']);
                                        $nameStmt->execute();
                                        $nameResult = $nameStmt->get_result();
                                        if ($nameRow = $nameResult->fetch_assoc()) {
                                            $entityName = $nameRow['name'] ?? null;
                                        }
                                    } elseif ($entry['entity_type'] === 'accounting') {
                                        $nameStmt = $conn->prepare("SELECT username as name FROM users WHERE user_id = ? LIMIT 1");
                                        $nameStmt->bind_param('i', $entry['entity_id']);
                                        $nameStmt->execute();
                                        $nameResult = $nameStmt->get_result();
                                        if ($nameRow = $nameResult->fetch_assoc()) {
                                            $entityName = $nameRow['name'] ?? null;
                                        }
                                    }
                                    if ($entityName) {
                                        $entry['entity_name'] = $entityName;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Add debit and credit side account names
                    if ($debitAccountName) {
                        $entry['debit_account_name'] = $debitAccountName;
                    }
                    if ($creditAccountName) {
                        $entry['credit_account_name'] = $creditAccountName;
                    }
                    
                    // Get cost center information from journal_entry_lines
                    $costCenterId = null;
                    $costCenterName = null;
                    $costCenterColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
                    $hasCostCenterCol = $costCenterColCheck && $costCenterColCheck->num_rows > 0;
                    if ($costCenterColCheck) {
                        $costCenterColCheck->free();
                    }
                    
                    if ($hasCostCenterCol) {
                        $costCenterQuery = "SELECT cost_center_id FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1";
                        $costCenterStmt = $conn->prepare($costCenterQuery);
                        if ($costCenterStmt) {
                            $costCenterStmt->bind_param('i', $row['id']);
                            $costCenterStmt->execute();
                            $costCenterResult = $costCenterStmt->get_result();
                            if ($costCenterRow = $costCenterResult->fetch_assoc() && isset($costCenterRow['cost_center_id']) && $costCenterRow['cost_center_id']) {
                                $costCenterId = intval($costCenterRow['cost_center_id']);
                                
                                // Get cost center name
                                $costCentersTableCheck = $conn->query("SHOW TABLES LIKE 'cost_centers'");
                                if ($costCentersTableCheck && $costCentersTableCheck->num_rows > 0) {
                                    $ccNameQuery = "SELECT code, name FROM cost_centers WHERE id = ? LIMIT 1";
                                    $ccNameStmt = $conn->prepare($ccNameQuery);
                                    if ($ccNameStmt) {
                                        $ccNameStmt->bind_param('i', $costCenterId);
                                        $ccNameStmt->execute();
                                        $ccNameResult = $ccNameStmt->get_result();
                                        if ($ccNameRow = $ccNameResult->fetch_assoc()) {
                                            $costCenterName = ($ccNameRow['code'] ? $ccNameRow['code'] . ' - ' : '') . $ccNameRow['name'];
                                        }
                                        $ccNameResult->free();
                                        $ccNameStmt->close();
                                    }
                                }
                                if ($costCentersTableCheck) {
                                    $costCentersTableCheck->free();
                                }
                            }
                            $costCenterResult->free();
                            $costCenterStmt->close();
                        }
                    }
                    
                    $entry['cost_center_id'] = $costCenterId;
                    $entry['cost_center_name'] = $costCenterName;
                    
                    // Get approved_by_name from entry_approval table
                    $approvedByName = null;
                    $approvalTableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
                    if ($approvalTableCheck && $approvalTableCheck->num_rows > 0) {
                        $approvalQuery = "SELECT u2.username as approved_by_name 
                                         FROM entry_approval ea 
                                         LEFT JOIN users u2 ON ea.approved_by = u2.user_id 
                                         WHERE ea.journal_entry_id = ? AND ea.status = 'approved' 
                                         LIMIT 1";
                        $approvalStmt = $conn->prepare($approvalQuery);
                        if ($approvalStmt) {
                            $approvalStmt->bind_param('i', $row['id']);
                            $approvalStmt->execute();
                            $approvalResult = $approvalStmt->get_result();
                            if ($approvalRow = $approvalResult->fetch_assoc()) {
                                $approvedByName = $approvalRow['approved_by_name'];
                            }
                            $approvalResult->free();
                            $approvalStmt->close();
                        }
                    }
                    if ($approvalTableCheck) {
                        $approvalTableCheck->free();
                    }
                    
                    $entry['approved_by_name'] = $approvedByName;
                }
                
                $entries[] = $entry;
            }
        } elseif ($accountId && !$accountFilterApplied) {
            // Account filter requested but couldn't be applied - return empty
            // This means no entries match the selected account
            $entries = [];
        }
    }
    
    // Also get transactions from financial_transactions (from agents/subagents/workers/hr)
    // If account filter is active, try to filter financial_transactions via transaction_lines or entity_type
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if ($tableCheck->num_rows > 0) {
        // Check if account filter is active
        $shouldIncludeFinancialTransactions = true;
        $ftAccountFilter = null;
        $entityTypeFilter = null; // Filter by entity type if account name matches
        
        // Get account name if account filter is active
        if ($accountId) {
            $accountNameQuery = "SELECT account_name, account_code FROM financial_accounts WHERE id = ? LIMIT 1";
            $accountNameStmt = $conn->prepare($accountNameQuery);
            $accountNameStmt->bind_param('i', $accountId);
            $accountNameStmt->execute();
            $accountNameResult = $accountNameStmt->get_result();
            if ($accountNameRow = $accountNameResult->fetch_assoc()) {
                $accountName = strtolower($accountNameRow['account_name'] ?? '');
                // Check if account name contains entity type keywords
                if (strpos($accountName, 'worker') !== false) {
                    $entityTypeFilter = 'worker';
                } elseif (strpos($accountName, 'agent') !== false && strpos($accountName, 'subagent') === false) {
                    $entityTypeFilter = 'agent';
                } elseif (strpos($accountName, 'subagent') !== false) {
                    $entityTypeFilter = 'subagent';
                } elseif (strpos($accountName, 'hr') !== false || strpos($accountName, 'employee') !== false) {
                    $entityTypeFilter = 'hr';
                } elseif (strpos($accountName, 'accounting') !== false) {
                    $entityTypeFilter = 'accounting';
                }
            }
        }
        
        if ($accountId && $accountFilterApplied) {
            // Account filter is active for journal_entries
            // If account name matches entity type, filter by entity_type instead of account_id
            if ($entityTypeFilter) {
                $shouldIncludeFinancialTransactions = true;
                $ftAccountFilter = null; // Don't filter by account_id, filter by entity_type
            } else {
                // Check if we can also filter financial_transactions by account
                $transactionLinesCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
                if ($transactionLinesCheck->num_rows > 0) {
                    $tlAccountCheck = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'account_id'");
                    if ($tlAccountCheck->num_rows > 0) {
                        // Can filter financial_transactions by account via transaction_lines
                        $shouldIncludeFinancialTransactions = true;
                        $ftAccountFilter = $accountId;
                    } else {
                        // transaction_lines exists but no account_id - exclude financial_transactions
                        $shouldIncludeFinancialTransactions = false;
                    }
                } else {
                    // No transaction_lines table - exclude financial_transactions (they don't have account links)
                    $shouldIncludeFinancialTransactions = false;
                }
            }
        } elseif ($accountId && !$accountFilterApplied) {
            // Account filter requested but journal_entry_lines doesn't exist or has no account_id
            // If account name matches entity type, filter by entity_type
            if ($entityTypeFilter) {
                $shouldIncludeFinancialTransactions = true;
                $ftAccountFilter = null; // Don't filter by account_id, filter by entity_type
            } else {
                // Check if transaction_lines table exists (for financial_transactions account linking)
                $transactionLinesCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
                if ($transactionLinesCheck->num_rows > 0) {
                    $tlAccountCheck = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'account_id'");
                    if ($tlAccountCheck->num_rows > 0) {
                        // Can filter financial_transactions by account via transaction_lines
                        $shouldIncludeFinancialTransactions = true;
                        $ftAccountFilter = $accountId;
                    } else {
                        // transaction_lines exists but no account_id - can't filter
                        $shouldIncludeFinancialTransactions = false;
                    }
                } else {
                    // No transaction_lines table - can't filter financial_transactions by account
                    $shouldIncludeFinancialTransactions = false;
                }
            }
        }
        
        if ($shouldIncludeFinancialTransactions) {
        // Check if entity_transactions table exists to join
        $entityTableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
        $hasEntityTable = $entityTableCheck->num_rows > 0;
        
        // Check column names for each entity table
        $agentNameCol = 'agent_name';
        $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_name'");
        if ($agentColCheck->num_rows === 0) {
            $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'full_name'");
            if ($agentColCheck->num_rows > 0) {
                $agentNameCol = 'full_name';
            } else {
                $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'name'");
                if ($agentColCheck->num_rows > 0) {
                    $agentNameCol = 'name';
                }
            }
        }
        
        $subagentNameCol = 'subagent_name';
        $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'subagent_name'");
        if ($subagentColCheck->num_rows === 0) {
            $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'full_name'");
            if ($subagentColCheck->num_rows > 0) {
                $subagentNameCol = 'full_name';
            } else {
                $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'name'");
                if ($subagentColCheck->num_rows > 0) {
                    $subagentNameCol = 'name';
                }
            }
        }
        
        $workerNameCol = 'worker_name';
        $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'worker_name'");
        if ($workerColCheck->num_rows === 0) {
            $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'full_name'");
            if ($workerColCheck->num_rows > 0) {
                $workerNameCol = 'full_name';
            } else {
                $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'name'");
                if ($workerColCheck->num_rows > 0) {
                    $workerNameCol = 'name';
                }
            }
        }
        
        $hrNameCol = 'name';
        $hrTableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
        $hasHrTable = $hrTableCheck->num_rows > 0;
        if ($hasHrTable) {
            $hrColCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'name'");
            if ($hrColCheck->num_rows === 0) {
                $hrColCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'full_name'");
                if ($hrColCheck->num_rows > 0) {
                    $hrNameCol = 'full_name';
                }
            }
        }
        
        // Build query for financial_transactions
        $ftQuery = "
            SELECT 
                ft.id,
                COALESCE(ft.reference_number, CONCAT('TXN-', ft.id)) as entry_number,
                ft.transaction_date as entry_date,
                ft.description,
                ft.transaction_type as entry_type,
                COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as total_debit,
                COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as total_credit,
                ft.status,
                ft.reference_number,
                u.username as created_by_name,
                " . ($hasEntityTable ? "
                et.entity_type, 
                et.entity_id,
                CASE 
                    WHEN et.entity_type = 'agent' THEN COALESCE(a.`{$agentNameCol}`, '')
                    WHEN et.entity_type = 'subagent' THEN COALESCE(sa.`{$subagentNameCol}`, '')
                    WHEN et.entity_type = 'worker' THEN COALESCE(w.`{$workerNameCol}`, '')
                    WHEN et.entity_type = 'hr' AND {$hasHrTable} THEN COALESCE(emp.`{$hrNameCol}`, '')
                    WHEN et.entity_type = 'accounting' THEN COALESCE(u_ent.username, '')
                    ELSE ''
                END as entity_name
                " : "NULL as entity_type, NULL as entity_id, '' as entity_name") . ",
                'transaction' as source
            FROM financial_transactions ft
            LEFT JOIN users u ON ft.created_by = u.user_id
        ";
        
        // Add account filter via transaction_lines if needed (only if not filtering by entity type)
        if ($ftAccountFilter && (!isset($entityTypeFilter) || !$entityTypeFilter)) {
            $ftQuery = "
                SELECT DISTINCT
                    ft.id,
                    COALESCE(ft.reference_number, CONCAT('TXN-', ft.id)) as entry_number,
                    ft.transaction_date as entry_date,
                    ft.description,
                    ft.transaction_type as entry_type,
                COALESCE(ft.debit_amount, CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as total_debit,
                COALESCE(ft.credit_amount, CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as total_credit,
                    ft.status,
                    ft.reference_number,
                    u.username as created_by_name,
                    " . ($hasEntityTable ? "
                    et.entity_type, 
                    et.entity_id,
                    CASE 
                        WHEN et.entity_type = 'agent' THEN COALESCE(a.`{$agentNameCol}`, '')
                        WHEN et.entity_type = 'subagent' THEN COALESCE(sa.`{$subagentNameCol}`, '')
                        WHEN et.entity_type = 'worker' THEN COALESCE(w.`{$workerNameCol}`, '')
                        WHEN et.entity_type = 'hr' AND {$hasHrTable} THEN COALESCE(emp.`{$hrNameCol}`, '')
                        WHEN et.entity_type = 'accounting' THEN COALESCE(u_ent.username, '')
                        ELSE ''
                    END as entity_name
                    " : "NULL as entity_type, NULL as entity_id, '' as entity_name") . ",
                    'transaction' as source
                FROM financial_transactions ft
                INNER JOIN transaction_lines tl ON ft.id = tl.transaction_id
                LEFT JOIN users u ON ft.created_by = u.user_id
            ";
        }
        
        if ($hasEntityTable) {
            $ftQuery .= " 
                LEFT JOIN entity_transactions et ON ft.id = et.transaction_id
                LEFT JOIN agents a ON et.entity_type = 'agent' AND et.entity_id = a.id
                LEFT JOIN subagents sa ON et.entity_type = 'subagent' AND et.entity_id = sa.id
                LEFT JOIN workers w ON et.entity_type = 'worker' AND et.entity_id = w.id
                LEFT JOIN users u_ent ON et.entity_type = 'accounting' AND et.entity_id = u_ent.user_id
            ";
            if ($hasHrTable) {
                $ftQuery .= " LEFT JOIN employees emp ON et.entity_type = 'hr' AND et.entity_id = emp.id ";
            }
        }
        
        $ftConditions = [];
        $ftParams = [];
        $ftTypes = '';
        
        // ALWAYS filter by status - only show Posted/Approved transactions (exclude Draft)
        // Draft transactions must go through Entry Approval first
        $ftConditions[] = "ft.status IN ('Posted', 'Approved')";
        
        // Add entity type filter if account name matches entity type
        if (isset($entityTypeFilter) && $entityTypeFilter && $hasEntityTable) {
            $ftConditions[] = "et.entity_type = ?";
            $ftParams[] = $entityTypeFilter;
            $ftTypes .= 's';
        }
        
        // Add account filter condition if filtering by account (and not filtering by entity type)
        if ($ftAccountFilter && (!isset($entityTypeFilter) || !$entityTypeFilter)) {
            $ftConditions[] = "tl.account_id = ?";
            $ftParams[] = $ftAccountFilter;
            $ftTypes .= 'i';
        }
        
        if ($dateFrom) {
            $ftConditions[] = "ft.transaction_date >= ?";
            $ftParams[] = $dateFrom;
            $ftTypes .= 's';
        }
        
        if ($dateTo) {
            $ftConditions[] = "ft.transaction_date <= ?";
            $ftParams[] = $dateTo;
            $ftTypes .= 's';
        }
        
        if (!empty($ftConditions)) {
            $ftQuery .= " WHERE " . implode(' AND ', $ftConditions);
        }
        
        // Group by to avoid duplicates if multiple entity_transactions exist
        if ($hasEntityTable || $ftAccountFilter) {
            $ftQuery .= " GROUP BY ft.id ";
        }
        
        // Order by transaction_date DESC (newest first), then by ID DESC as tiebreaker
        $ftQuery .= " ORDER BY ft.transaction_date DESC, ft.id DESC LIMIT 100";
        
        $ftStmt = $conn->prepare($ftQuery);
        if (!empty($ftParams)) {
            $ftStmt->bind_param($ftTypes, ...$ftParams);
        }
        $ftStmt->execute();
        $ftResult = $ftStmt->get_result();

        // Optional: Attach debit/credit account names for transaction rows (for General Ledger "Debit Account / Credit Account" columns)
        $tlDebitStmt = null;
        $tlCreditStmt = null;
        try {
            $tlTableCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
            $faTableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
            $hasTlTable = $tlTableCheck && $tlTableCheck->num_rows > 0;
            $hasFaTable = $faTableCheck && $faTableCheck->num_rows > 0;
            if ($tlTableCheck) $tlTableCheck->free();
            if ($faTableCheck) $faTableCheck->free();

            if ($hasTlTable && $hasFaTable) {
                $colAccountId = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'account_id'");
                $colDebit = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'debit_amount'");
                $colCredit = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'credit_amount'");

                $hasAccountIdCol = $colAccountId && $colAccountId->num_rows > 0;
                $hasDebitCol = $colDebit && $colDebit->num_rows > 0;
                $hasCreditCol = $colCredit && $colCredit->num_rows > 0;

                if ($colAccountId) $colAccountId->free();
                if ($colDebit) $colDebit->free();
                if ($colCredit) $colCredit->free();

                if ($hasAccountIdCol && $hasDebitCol && $hasCreditCol) {
                    $tlDebitStmt = $conn->prepare("
                        SELECT fa.account_code, fa.account_name
                        FROM transaction_lines tl
                        LEFT JOIN financial_accounts fa ON tl.account_id = fa.id
                        WHERE tl.transaction_id = ? AND tl.debit_amount > 0
                        ORDER BY tl.debit_amount DESC, tl.id ASC
                        LIMIT 1
                    ");
                    $tlCreditStmt = $conn->prepare("
                        SELECT fa.account_code, fa.account_name
                        FROM transaction_lines tl
                        LEFT JOIN financial_accounts fa ON tl.account_id = fa.id
                        WHERE tl.transaction_id = ? AND tl.credit_amount > 0
                        ORDER BY tl.credit_amount DESC, tl.id ASC
                        LIMIT 1
                    ");
                }
            }
        } catch (Exception $e) {
            // Best-effort only: if anything fails, we just won't attach account names
            $tlDebitStmt = null;
            $tlCreditStmt = null;
        }
        
        while ($row = $ftResult->fetch_assoc()) {
            // Format the entry to match journal entry structure
            $entry = [
                'id' => $row['id'],
                'entry_number' => $row['entry_number'],
                'entry_date' => $row['entry_date'],
                'description' => $row['description'],
                'entry_type' => $row['entry_type'],
                'total_debit' => floatval($row['total_debit'] ?? 0),
                'total_credit' => floatval($row['total_credit'] ?? 0),
                'status' => $row['status'],
                'reference_number' => $row['reference_number'] ?? '',
                'created_by_name' => $row['created_by_name'] ?? '',
                'source' => 'transaction',
                'entity_type' => $row['entity_type'] ?? null,
                'entity_id' => $row['entity_id'] ?? null,
                'entity_name' => $row['entity_name'] ?? null
            ];

            // Attach first debit/credit account names when available
            if ($tlDebitStmt) {
                $tid = intval($row['id']);
                $tlDebitStmt->bind_param('i', $tid);
                if ($tlDebitStmt->execute()) {
                    $r = $tlDebitStmt->get_result();
                    if ($r && ($dr = $r->fetch_assoc())) {
                        $entry['debit_account_name'] = (($dr['account_code'] ? $dr['account_code'] . ' - ' : '') . ($dr['account_name'] ?? ''));
                    }
                }
            }
            if ($tlCreditStmt) {
                $tid = intval($row['id']);
                $tlCreditStmt->bind_param('i', $tid);
                if ($tlCreditStmt->execute()) {
                    $r = $tlCreditStmt->get_result();
                    if ($r && ($cr = $r->fetch_assoc())) {
                        $entry['credit_account_name'] = (($cr['account_code'] ? $cr['account_code'] . ' - ' : '') . ($cr['account_name'] ?? ''));
                    }
                }
            }

            $entries[] = $entry;
        }
        if ($tlDebitStmt) $tlDebitStmt->close();
        if ($tlCreditStmt) $tlCreditStmt->close();
        }
    }
    
    // Fetch entity names for journal entries that have entity_type and entity_id
    // Check if entity tables exist and get column names
    $agentTableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
    $hasAgentTable = $agentTableCheck->num_rows > 0;
    $subagentTableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
    $hasSubagentTable = $subagentTableCheck->num_rows > 0;
    $workerTableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
    $hasWorkerTable = $workerTableCheck->num_rows > 0;
    $hrTableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
    $hasHrTable = $hrTableCheck->num_rows > 0;
    
    // Get column names for entity tables
    $agentNameCol = 'agent_name';
    if ($hasAgentTable) {
        $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_name'");
        if ($agentColCheck->num_rows === 0) {
            $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'full_name'");
            if ($agentColCheck->num_rows > 0) {
                $agentNameCol = 'full_name';
            } else {
                $agentColCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'name'");
                if ($agentColCheck->num_rows > 0) {
                    $agentNameCol = 'name';
                }
            }
        }
    }
    
    $subagentNameCol = 'subagent_name';
    if ($hasSubagentTable) {
        $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'subagent_name'");
        if ($subagentColCheck->num_rows === 0) {
            $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'full_name'");
            if ($subagentColCheck->num_rows > 0) {
                $subagentNameCol = 'full_name';
            } else {
                $subagentColCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'name'");
                if ($subagentColCheck->num_rows > 0) {
                    $subagentNameCol = 'name';
                }
            }
        }
    }
    
    $workerNameCol = 'worker_name';
    if ($hasWorkerTable) {
        $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'worker_name'");
        if ($workerColCheck->num_rows === 0) {
            $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'full_name'");
            if ($workerColCheck->num_rows > 0) {
                $workerNameCol = 'full_name';
            } else {
                $workerColCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'name'");
                if ($workerColCheck->num_rows > 0) {
                    $workerNameCol = 'name';
                }
            }
        }
    }
    
    $hrNameCol = 'full_name';
    if ($hasHrTable) {
        $hrColCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'name'");
        if ($hrColCheck->num_rows === 0) {
            $hrColCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'full_name'");
            if ($hrColCheck->num_rows > 0) {
                $hrNameCol = 'full_name';
            }
        } else {
            $hrNameCol = 'name';
        }
    }
    
    foreach ($entries as &$entry) {
        if (isset($entry['entity_type']) && isset($entry['entity_id']) && $entry['entity_id'] > 0) {
            $entityType = $entry['entity_type'];
            $entityId = $entry['entity_id'];
            $entityName = '';
            
            if ($entityType === 'agent' && $hasAgentTable) {
                $nameQuery = "SELECT `{$agentNameCol}` as name FROM agents WHERE id = ? LIMIT 1";
                $nameStmt = $conn->prepare($nameQuery);
                $nameStmt->bind_param('i', $entityId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $entityName = $nameRow['name'] ?? '';
                }
            } elseif ($entityType === 'subagent' && $hasSubagentTable) {
                $nameQuery = "SELECT `{$subagentNameCol}` as name FROM subagents WHERE id = ? LIMIT 1";
                $nameStmt = $conn->prepare($nameQuery);
                $nameStmt->bind_param('i', $entityId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $entityName = $nameRow['name'] ?? '';
                }
            } elseif ($entityType === 'worker' && $hasWorkerTable) {
                $nameQuery = "SELECT `{$workerNameCol}` as name FROM workers WHERE id = ? LIMIT 1";
                $nameStmt = $conn->prepare($nameQuery);
                $nameStmt->bind_param('i', $entityId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $entityName = $nameRow['name'] ?? '';
                }
            } elseif ($entityType === 'hr' && $hasHrTable) {
                $nameQuery = "SELECT `{$hrNameCol}` as name FROM employees WHERE id = ? LIMIT 1";
                $nameStmt = $conn->prepare($nameQuery);
                $nameStmt->bind_param('i', $entityId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameRow = $nameResult->fetch_assoc()) {
                    $entityName = $nameRow['name'] ?? '';
                }
            }
            
            $entry['entity_name'] = $entityName;
        }
    }
    unset($entry); // Break reference
    
    // Deduplicate entries: Exclude ALL journal entries with entry_type = 'Entity Transaction'
    // These are automatically created duplicates when entity transactions are saved
    // We keep only the financial_transaction (REF-*) entries and exclude all JE-* entries with 'Entity Transaction' type
    $deduplicatedEntries = [];
    
    foreach ($entries as $entry) {
        $entryType = isset($entry['entry_type']) ? trim(strtolower($entry['entry_type'])) : '';
        
        // Skip ALL journal entries with 'Entity Transaction' type - they are duplicates of financial_transactions
        if ($entry['source'] === 'journal' && 
            ($entryType === 'entity transaction' || $entryType === 'entitytransaction')) {
            // This is a journal entry automatically created from an entity transaction
            // Skip it - we only want to show the original financial_transaction (REF-*)
            continue;
        }
        
        $deduplicatedEntries[] = $entry;
    }
    
    $entries = $deduplicatedEntries;
    
    // Sort all entries by entry_date DESC (newest entries first), then by ID DESC as tiebreaker
    usort($entries, function($a, $b) {
        $dateA = $a['entry_date'] ?? '';
        $dateB = $b['entry_date'] ?? '';
        
        // First sort by date (newest first)
        if ($dateA !== $dateB) {
            return strcmp($dateB, $dateA); // DESC order
        }
        
        // If dates are equal, sort by ID DESC (highest ID = newest = first)
        $idA = intval($a['id'] ?? 0);
        $idB = intval($b['id'] ?? 0);
        return $idB - $idA;
    });
    
    // Limit to 100 entries total
    $entries = array_slice($entries, 0, 100);

    // Handle different HTTP methods
    if ($method === 'GET') {
        // Format all dates in entries array for display
        $entries = formatDatesInArray($entries);
        echo json_encode([
            'success' => true,
            'entries' => $entries
        ]);
    } elseif ($method === 'POST') {
        // Create new journal entry
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        // Convert entry_date from MM/DD/YYYY to YYYY-MM-DD for database
        $entryDate = isset($data['entry_date']) ? formatDateForDatabase($data['entry_date']) : date('Y-m-d');
        $description = $data['description'] ?? '';
        $accountId = intval($data['account_id'] ?? 0);
        $debit = floatval($data['debit'] ?? 0);
        $credit = floatval($data['credit'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'SAR');
        // Extract currency code if format is "CODE - Name"
        if (strpos($currency, ' - ') !== false) {
            $currency = trim(explode(' - ', $currency)[0]);
        }
        $reference = $data['reference'] ?? null;

        // Support NEW Journal Entry form payload: debit_lines[] + credit_lines[]
        // while keeping backward compatibility with the legacy single-line payload.
        $rawDebitLines = $data['debit_lines'] ?? [];
        $rawCreditLines = $data['credit_lines'] ?? [];

        $debitLines = [];
        if (is_array($rawDebitLines)) {
            foreach ($rawDebitLines as $ln) {
                if (!is_array($ln)) continue;
                $lnAccountId = intval($ln['account_id'] ?? 0);
                $lnAmount = floatval($ln['amount'] ?? 0);
                if ($lnAccountId > 0 && $lnAmount > 0) {
                    $debitLines[] = [
                        'account_id' => $lnAccountId,
                        'amount' => $lnAmount,
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => trim(strval($ln['description'] ?? '')),
                        'vat_report' => !empty($ln['vat_report'])
                    ];
                }
            }
        }

        $creditLines = [];
        if (is_array($rawCreditLines)) {
            foreach ($rawCreditLines as $ln) {
                if (!is_array($ln)) continue;
                $lnAccountId = intval($ln['account_id'] ?? 0);
                $lnAmount = floatval($ln['amount'] ?? 0);
                if ($lnAccountId > 0 && $lnAmount > 0) {
                    $creditLines[] = [
                        'account_id' => $lnAccountId,
                        'amount' => $lnAmount,
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => trim(strval($ln['description'] ?? '')),
                        'vat_report' => !empty($ln['vat_report'])
                    ];
                }
            }
        }

        $isMultiLinePayload = (count($debitLines) + count($creditLines)) > 0;

        // Determine totals and validate
        if ($isMultiLinePayload) {
            $totalDebit = 0.0;
            foreach ($debitLines as $ln) $totalDebit += floatval($ln['amount']);
            $totalCredit = 0.0;
            foreach ($creditLines as $ln) $totalCredit += floatval($ln['amount']);

            if ($totalDebit <= 0 || $totalCredit <= 0) {
                throw new Exception('Please add at least one debit line and one credit line');
            }
            $diff = abs($totalDebit - $totalCredit);
            if ($diff > 0.01) {
                throw new Exception('Entry is not balanced');
            }

            // For compatibility: keep an account_id for the entry header if provided,
            // otherwise take the first debit/credit line account.
            if ($accountId <= 0) {
                $accountId = isset($debitLines[0]) ? intval($debitLines[0]['account_id']) : (isset($creditLines[0]) ? intval($creditLines[0]['account_id']) : 0);
            }
            if ($accountId <= 0) {
                throw new Exception('Account is required');
            }
        } else {
            // Legacy single-line payload validation
            if ($accountId <= 0) {
                throw new Exception('Account is required');
            }
            if ($debit <= 0 && $credit <= 0) {
                throw new Exception('Either debit or credit amount must be greater than 0');
            }
            if ($debit > 0 && $credit > 0) {
                throw new Exception('Cannot have both debit and credit amounts');
            }
            $totalDebit = $debit;
            $totalCredit = $credit;
        }
        
        $conn->begin_transaction();
        try {
            // Generate entry number
            $entryNumberStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(entry_number, 5) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
            $entryNumberStmt->execute();
            $entryNumberResult = $entryNumberStmt->get_result();
            $entryNumberData = $entryNumberResult->fetch_assoc();
            $entryNumberResult->free();
            $entryNumberStmt->close();
            $nextNum = ($entryNumberData['max_num'] ?? 0) + 1;
            $entryNumber = 'JE-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
            
            // Check if currency, reference_number, and entry_type columns exist
            $currencyCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
            $hasCurrency = $currencyCheck->num_rows > 0;
            $currencyCheck->free();
            
            $referenceCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'reference_number'");
            $hasReference = $referenceCheck->num_rows > 0;
            $referenceCheck->free();
            
            $entryTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'entry_type'");
            $hasEntryType = $entryTypeCheck->num_rows > 0;
            $entryTypeCheck->free();
            
            // Get entry_type from request data
            $entryType = $data['entry_type'] ?? null;
            if ($entryType) {
                $entryType = ucfirst(trim($entryType));
                // Validate entry_type
                $validEntryTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                if (!in_array($entryType, $validEntryTypes)) {
                    $entryType = 'Manual';
                }
            } else {
                $entryType = 'Manual'; // Default
            }
            
            // All new journal entries must go through approval - ALWAYS set status to Draft
            // Ignore any status sent from frontend - workflow requires approval first
                    $status = 'Draft';
            
            // Check if is_posted and is_locked columns exist
            $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
            $hasIsPosted = $isPostedCheck->num_rows > 0;
            $isPostedCheck->free();
            
            $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
            $hasIsLocked = $isLockedCheck->num_rows > 0;
            $isLockedCheck->free();
            
            // Build INSERT query dynamically based on available columns
            $insertFields = ['entry_number', 'entry_date', 'description', 'total_debit', 'total_credit', 'status'];
            $insertValues = ['?', '?', '?', '?', '?', '?'];
            $bindParams = [$entryNumber, $entryDate, $description, $totalDebit, $totalCredit, $status];
            $bindTypes = 'sssdds';
            
            // Add is_posted if column exists (default to FALSE for new entries)
            if ($hasIsPosted) {
                $insertFields[] = 'is_posted';
                $insertValues[] = '?';
                $bindParams[] = 0; // FALSE - not posted yet
                $bindTypes .= 'i';
            }
            
            // Add is_locked if column exists (default to FALSE for new entries)
            if ($hasIsLocked) {
                $insertFields[] = 'is_locked';
                $insertValues[] = '?';
                $bindParams[] = 0; // FALSE - not locked yet
                $bindTypes .= 'i';
            }
            
            if ($hasCurrency) {
                $insertFields[] = 'currency';
                $insertValues[] = '?';
                $bindParams[] = $currency;
                $bindTypes .= 's';
            }
            
            if ($hasReference) {
                $insertFields[] = 'reference_number';
                $insertValues[] = '?';
                $bindParams[] = $reference;
                $bindTypes .= 's';
            }
            
            if ($hasEntryType) {
                $insertFields[] = 'entry_type';
                $insertValues[] = '?';
                $bindParams[] = $entryType;
                $bindTypes .= 's';
            }

            // Optional branch_id support (if column exists)
            $branchId = intval($data['branch_id'] ?? 0);
            if ($branchId <= 0) $branchId = 1; // sensible default
            $branchCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'branch_id'");
            $hasBranch = $branchCheck && $branchCheck->num_rows > 0;
            if ($branchCheck) $branchCheck->free();
            if ($hasBranch) {
                $insertFields[] = 'branch_id';
                // Use NULLIF to allow 0->NULL if needed, but we default to 1
                $insertValues[] = 'NULLIF(?, 0)';
                $bindParams[] = $branchId;
                $bindTypes .= 'i';
            }
            
            $userId = $_SESSION['user_id'];
            $insertFields[] = 'created_by';
            $insertValues[] = '?';
            $bindParams[] = $userId;
            $bindTypes .= 'i';
            
            // ERP: Auto-populate fiscal_period_id
            $fiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'fiscal_period_id'");
            $hasFiscalPeriod = $fiscalPeriodCheck && $fiscalPeriodCheck->num_rows > 0;
            if ($fiscalPeriodCheck) $fiscalPeriodCheck->free();
            if ($hasFiscalPeriod && $entryDate) {
                require_once __DIR__ . '/core/fiscal-period-helper.php';
                $fiscalPeriodId = getFiscalPeriodId($conn, $entryDate);
                if ($fiscalPeriodId) {
                    $insertFields[] = 'fiscal_period_id';
                    $insertValues[] = '?';
                    $bindParams[] = $fiscalPeriodId;
                    $bindTypes .= 'i';
                }
            }
            
            // ERP: Set posting_status for new entries
            $postingStatusCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'posting_status'");
            $hasPostingStatus = $postingStatusCheck && $postingStatusCheck->num_rows > 0;
            if ($postingStatusCheck) $postingStatusCheck->free();
            if ($hasPostingStatus) {
                $insertFields[] = 'posting_status';
                $insertValues[] = "'draft'";
            }
            
            // Insert journal entry
            $sql = "INSERT INTO journal_entries (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare journal entry insert: ' . $conn->error);
            }
            $stmt->bind_param($bindTypes, ...$bindParams);
            if (!$stmt->execute()) {
                throw new Exception('Failed to execute journal entry insert: ' . $stmt->error);
            }
            $entryId = $conn->insert_id;
            if (!$entryId) {
                throw new Exception('Failed to get journal entry ID after insert');
            }
            
            // ERP: Log audit trail for creation
            logJournalEntryCreate($conn, $entryId, [
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'description' => $description,
                'entry_type' => $entryType,
                'status' => $status
            ]);
            
            // Insert journal entry lines (multi-line supported)
            // Check if entity columns exist
            $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
            $hasEntityType = $entityTypeCheck->num_rows > 0;
            
            $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
            $hasEntityId = $entityIdCheck->num_rows > 0;
            
            // Get entity data from request
            $entityType = isset($data['entity_type']) ? strtolower(trim($data['entity_type'])) : null;
            $entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
            
            // Check for cost_center_id column
            $hasCostCenter = false;
            $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
            if ($costCenterCheck && $costCenterCheck->num_rows > 0) {
                $hasCostCenter = true;
            }
            if ($costCenterCheck) {
                $costCenterCheck->free();
            }
            
            // If columns don't exist but entity data is provided, try to create them
            if ((!$hasEntityType || !$hasEntityId) && $entityType && $entityId > 0) {
                try {
                    if (!$hasEntityType) {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_type VARCHAR(50) NULL AFTER credit_amount");
                        $hasEntityType = true;
                    }
                    if (!$hasEntityId) {
                        $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_id INT(11) NULL AFTER entity_type");
                        $hasEntityId = true;
                    }
                } catch (Exception $e) {
                    // Column creation failed, continue without it
                }
            }
            
            // Try to add cost_center_id column if it doesn't exist
            if (!$hasCostCenter) {
                try {
                    $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN cost_center_id INT NULL AFTER entity_id");
                    $hasCostCenter = true;
                } catch (Exception $e) {
                    // Column creation failed, continue without it
                }
            }
            
            // Get cost_center_id from data if provided
            $costCenterId = isset($data['cost_center_id']) ? intval($data['cost_center_id']) : null;

            // Ensure description + vat_report columns exist on lines (so edit form can show them)
            $descColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'description'");
            $hasLineDescription = $descColCheck && $descColCheck->num_rows > 0;
            if ($descColCheck) $descColCheck->free();
            if (!$hasLineDescription) {
                try {
                    $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN description TEXT NULL AFTER cost_center_id");
                    $hasLineDescription = true;
                } catch (Exception $e) {
                    // ignore
                }
            }

            $vatColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'vat_report'");
            $hasVatReportCol = $vatColCheck && $vatColCheck->num_rows > 0;
            if ($vatColCheck) $vatColCheck->free();
            if (!$hasVatReportCol) {
                try {
                    $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN vat_report TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
                    $hasVatReportCol = true;
                } catch (Exception $e) {
                    // ignore
                }
            }

            // Build one INSERT statement that can accept NULL-ish values
            $lineCols = ['journal_entry_id', 'account_id', 'debit_amount', 'credit_amount'];
            $lineVals = ['?', '?', '?', '?'];
            $lineTypes = 'iidd';

            if ($hasEntityType) {
                $lineCols[] = 'entity_type';
                $lineVals[] = "NULLIF(?, '')";
                $lineTypes .= 's';
            }
            if ($hasEntityId) {
                $lineCols[] = 'entity_id';
                $lineVals[] = 'NULLIF(?, 0)';
                $lineTypes .= 'i';
            }
            if ($hasCostCenter) {
                $lineCols[] = 'cost_center_id';
                $lineVals[] = 'NULLIF(?, 0)';
                $lineTypes .= 'i';
            }
            if ($hasLineDescription) {
                $lineCols[] = 'description';
                $lineVals[] = "NULLIF(?, '')";
                $lineTypes .= 's';
            }
            if ($hasVatReportCol) {
                $lineCols[] = 'vat_report';
                $lineVals[] = 'NULLIF(?, 0)';
                $lineTypes .= 'i';
            }

            $lineSql = "INSERT INTO journal_entry_lines (" . implode(', ', $lineCols) . ") VALUES (" . implode(', ', $lineVals) . ")";
            $lineStmt = $conn->prepare($lineSql);
            if (!$lineStmt) {
                throw new Exception('Failed to prepare journal entry line insert: ' . $conn->error);
            }

            // Build lines list
            $linesToInsert = [];
            if ($isMultiLinePayload) {
                foreach ($debitLines as $ln) {
                    $linesToInsert[] = [
                        'account_id' => intval($ln['account_id']),
                        'debit_amount' => floatval($ln['amount']),
                        'credit_amount' => 0.0,
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => strval($ln['description'] ?? ''),
                        'vat_report' => !empty($ln['vat_report']) ? 1 : 0
                    ];
                }
                foreach ($creditLines as $ln) {
                    $linesToInsert[] = [
                        'account_id' => intval($ln['account_id']),
                        'debit_amount' => 0.0,
                        'credit_amount' => floatval($ln['amount']),
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => strval($ln['description'] ?? ''),
                        'vat_report' => !empty($ln['vat_report']) ? 1 : 0
                    ];
                }
            } else {
                // Legacy: single line
                $linesToInsert[] = [
                    'account_id' => $accountId,
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'cost_center_id' => intval($costCenterId ?? 0),
                    'description' => '',
                    'vat_report' => 0
                ];
            }

            // Bind & execute per line
            foreach ($linesToInsert as $ln) {
                $lnAccountId = intval($ln['account_id']);
                $lnDebit = floatval($ln['debit_amount']);
                $lnCredit = floatval($ln['credit_amount']);
                $lnEntityType = ($hasEntityType && $entityType) ? strval($entityType) : '';
                $lnEntityId = ($hasEntityId && $entityId) ? intval($entityId) : 0;
                $lnCostCenterId = ($hasCostCenter) ? intval($ln['cost_center_id'] ?? 0) : 0;
                $lnDesc = ($hasLineDescription) ? strval($ln['description'] ?? '') : '';
                $lnVat = ($hasVatReportCol) ? intval($ln['vat_report'] ?? 0) : 0;

                // Bind params in the same order as $lineCols/$lineVals
                // Build a param list dynamically to avoid losing new fields
                $bindList = [$entryId, $lnAccountId, $lnDebit, $lnCredit];
                if ($hasEntityType) $bindList[] = $lnEntityType;
                if ($hasEntityId) $bindList[] = $lnEntityId;
                if ($hasCostCenter) $bindList[] = $lnCostCenterId;
                if ($hasLineDescription) $bindList[] = $lnDesc;
                if ($hasVatReportCol) $bindList[] = $lnVat;
                $lineStmt->bind_param($lineTypes, ...$bindList);

                if (!$lineStmt->execute()) {
                    $lineStmt->close();
                    throw new Exception('Failed to execute journal entry line insert: ' . $lineStmt->error);
                }
            }
            $lineStmt->close();
            
            // Create entry approval record automatically
            $approvalTableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
            if ($approvalTableCheck && $approvalTableCheck->num_rows > 0) {
                // Check if linking columns exist
                $journalLinkCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'journal_entry_id'");
                $hasJournalLink = $journalLinkCheck->num_rows > 0;
                $journalLinkCheck->free();
                
                $approvalAmount = max($totalDebit, $totalCredit);
                $approvalEntryNumber = 'APP-' . $entryNumber;
                $userId = $_SESSION['user_id'];
                
                // Get entity linking from journal entry line if available
                $approvalEntityType = null;
                $approvalEntityId = null;
                if ($hasEntityType && $hasEntityId && $entityType && $entityId) {
                    $approvalEntityType = $entityType;
                    $approvalEntityId = $entityId;
                }
                
                // Check if entry_approval has entity columns
                $approvalEntityTypeCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_type'");
                $hasApprovalEntityType = $approvalEntityTypeCheck->num_rows > 0;
                $approvalEntityTypeCheck->free();
                
                $approvalEntityIdCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_id'");
                $hasApprovalEntityId = $approvalEntityIdCheck->num_rows > 0;
                $approvalEntityIdCheck->free();
                
                if ($hasJournalLink) {
                    if ($hasApprovalEntityType && $hasApprovalEntityId && $approvalEntityType && $approvalEntityId) {
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, journal_entry_id, entity_type, entity_id, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
                        ");
                        if (!$approvalStmt) {
                            throw new Exception('Failed to prepare entry approval insert (with entity and journal link): ' . $conn->error);
                        }
                        $approvalStmt->bind_param('sssdssiii', $approvalEntryNumber, $entryDate, $description, $approvalAmount, $currency, $entryId, $approvalEntityType, $approvalEntityId, $userId);
                    } else {
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, journal_entry_id, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
                        ");
                        if (!$approvalStmt) {
                            throw new Exception('Failed to prepare entry approval insert (with journal link): ' . $conn->error);
                        }
                        $approvalStmt->bind_param('sssdsii', $approvalEntryNumber, $entryDate, $description, $approvalAmount, $currency, $entryId, $userId);
                    }
                } else {
                    if ($hasApprovalEntityType && $hasApprovalEntityId && $approvalEntityType && $approvalEntityId) {
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, entity_type, entity_id, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                        ");
                        if (!$approvalStmt) {
                            throw new Exception('Failed to prepare entry approval insert (with entity): ' . $conn->error);
                        }
                        $approvalStmt->bind_param('sssdssii', $approvalEntryNumber, $entryDate, $description, $approvalAmount, $currency, $approvalEntityType, $approvalEntityId, $userId);
                    } else {
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?)
                        ");
                        if (!$approvalStmt) {
                            throw new Exception('Failed to prepare entry approval insert: ' . $conn->error);
                        }
                        $approvalStmt->bind_param('sssdsi', $approvalEntryNumber, $entryDate, $description, $approvalAmount, $currency, $userId);
                    }
                }
                if (!$approvalStmt->execute()) {
                    error_log("Failed to create entry approval: " . $approvalStmt->error);
                    // Don't fail the transaction, but log the error
                } else {
                    $approvalId = $conn->insert_id;
                    error_log("Entry approval created successfully - Approval ID: $approvalId, Journal Entry ID: $entryId");
                }
                $approvalStmt->close();
                $approvalTableCheck->free();
            } else {
                if ($approvalTableCheck) {
                    $approvalTableCheck->free();
                }
                error_log("Entry approval table does not exist - cannot create approval record");
            }
            
            $conn->commit();
            
            // Get created journal entry for history
            $fetchStmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
            $fetchStmt->bind_param('i', $entryId);
            $fetchStmt->execute();
            $fetchResult = $fetchStmt->get_result();
            $newEntry = $fetchResult->fetch_assoc();
            $fetchResult->free();
            $fetchStmt->close();
            
            // Log history (suppress any output from logging function)
            if ($newEntry) {
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    ob_start(); // Start output buffering to capture any unwanted output
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        @logGlobalHistory('journal_entries', $entryId, 'create', 'accounting', null, $newEntry);
                    }
                    ob_end_clean(); // Discard any output from logging
                }
            }
            
            // Send JSON response
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Journal entry created successfully and sent for approval',
                'entry_id' => $entryId,
                'requires_approval' => true
            ]);
            exit; // Ensure no other output after JSON
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Journal entry creation error in POST: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error creating journal entry: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
            exit;
        }
    } elseif ($method === 'PUT') {
        // Update journal entry
        $entryId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if ($entryId <= 0) {
            throw new Exception('Journal entry ID is required');
        }
        
        // Validate JSON decode
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data: ' . json_last_error_msg());
        }
        
        // Get old data for history (before update)
        $oldStmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
        $oldStmt->bind_param('i', $entryId);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();
        if ($oldResult->num_rows === 0) {
            $oldResult->free();
            $oldStmt->close();
            throw new Exception('Journal entry not found');
        }
        $oldEntry = $oldResult->fetch_assoc();
        $oldResult->free();
        $oldStmt->close();
        
        // ERP-GRADE POSTING CONTROLS: Use centralized validation
        $editCheck = canEditJournalEntry($conn, $entryId);
        if (!$editCheck['can_edit']) {
            throw new Exception($editCheck['reason']);
        }
        
        // Validate new entry_date if changed
        $oldEntryDate = $oldEntry['entry_date'] ?? null;
        $newEntryDate = isset($data['entry_date']) ? formatDateForDatabase($data['entry_date']) : null;
        if ($newEntryDate && $newEntryDate !== $oldEntryDate) {
            
            // Validate new date is not in closed period
            try {
                validatePostingDate($conn, $newEntryDate);
            } catch (Exception $e) {
                throw new Exception('Cannot change entry date: ' . $e->getMessage());
            }
        }
        
        // Entry existence already checked above, no need to check again
        
        // Store original data array IMMEDIATELY after parsing (before any processing)
        // This ensures we can return exactly what was sent, avoiding any variable modifications
        $originalData = $data;
        
        // Log incoming request data
        error_log("PUT Update - Entry ID: $entryId");
        error_log("PUT Update - Raw JSON (first 1000 chars): " . substr($rawInput, 0, 1000));
        if (isset($data['description'])) {
            $descLen = strlen($data['description']);
            error_log("PUT Update - Description in request: length=$descLen, value='" . substr($data['description'], 0, 200) . "'");
        } else {
            error_log("PUT Update - WARNING: description NOT in request data!");
        }
        if (isset($data['entity_type'])) {
            error_log("PUT Update - Entity type in request: '" . $data['entity_type'] . "'");
        }
        if (isset($data['entity_id'])) {
            error_log("PUT Update - Entity ID in request: " . $data['entity_id']);
        }
        if (isset($data['currency'])) {
            error_log("PUT Update - Currency in request: '" . $data['currency'] . "'");
        }
        if (isset($data['entry_type'])) {
            error_log("PUT Update - Entry type in request: '" . $data['entry_type'] . "'");
        }
        
        $conn->begin_transaction();
        try {
            // Convert entry_date from MM/DD/YYYY to YYYY-MM-DD for database
            $entryDate = isset($data['entry_date']) ? formatDateForDatabase($data['entry_date']) : ($oldEntry['entry_date'] ?? null);
            $description = $data['description'] ?? '';
            error_log("PUT Update - Extracted entry_date: '$entryDate', description: '" . substr($description, 0, 100) . "'");
            $accountId = intval($data['account_id'] ?? 0);
            $debit = floatval($data['debit'] ?? 0);
            $credit = floatval($data['credit'] ?? 0);

            // Support multi-line payload (debit_lines + credit_lines) from the new Journal Entry form
            $rawDebitLines = $data['debit_lines'] ?? [];
            $rawCreditLines = $data['credit_lines'] ?? [];
            $putDebitLines = [];
            $putCreditLines = [];

            if (is_array($rawDebitLines)) {
                foreach ($rawDebitLines as $ln) {
                    if (!is_array($ln)) continue;
                    $lnAccountId = intval($ln['account_id'] ?? 0);
                    $lnAmount = floatval($ln['amount'] ?? 0);
                    if ($lnAccountId > 0 && $lnAmount > 0) {
                        $putDebitLines[] = [
                            'account_id' => $lnAccountId,
                            'amount' => $lnAmount,
                            'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                            'description' => trim(strval($ln['description'] ?? '')),
                            'vat_report' => !empty($ln['vat_report'])
                        ];
                    }
                }
            }
            if (is_array($rawCreditLines)) {
                foreach ($rawCreditLines as $ln) {
                    if (!is_array($ln)) continue;
                    $lnAccountId = intval($ln['account_id'] ?? 0);
                    $lnAmount = floatval($ln['amount'] ?? 0);
                    if ($lnAccountId > 0 && $lnAmount > 0) {
                        $putCreditLines[] = [
                            'account_id' => $lnAccountId,
                            'amount' => $lnAmount,
                            'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                            'description' => trim(strval($ln['description'] ?? '')),
                            'vat_report' => !empty($ln['vat_report'])
                        ];
                    }
                }
            }

            $hasMultiLines = (count($putDebitLines) + count($putCreditLines)) > 0;
            $putLinesToInsert = [];
            if ($hasMultiLines) {
                $totalDebit = 0.0;
                foreach ($putDebitLines as $ln) $totalDebit += floatval($ln['amount']);
                $totalCredit = 0.0;
                foreach ($putCreditLines as $ln) $totalCredit += floatval($ln['amount']);

                // Validate balance
                if ($totalDebit <= 0 || $totalCredit <= 0) {
                    throw new Exception('Please add at least one debit line and one credit line');
                }
                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new Exception('Entry is not balanced');
                }

                // Update totals used by journal_entries UPDATE
                $debit = $totalDebit;
                $credit = $totalCredit;

                // Ensure header accountId exists (fallback to first line)
                if ($accountId <= 0) {
                    $accountId = isset($putDebitLines[0]) ? intval($putDebitLines[0]['account_id']) : (isset($putCreditLines[0]) ? intval($putCreditLines[0]['account_id']) : 0);
                }

                foreach ($putDebitLines as $ln) {
                    $putLinesToInsert[] = [
                        'account_id' => intval($ln['account_id']),
                        'debit_amount' => floatval($ln['amount']),
                        'credit_amount' => 0.0,
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => strval($ln['description'] ?? ''),
                        'vat_report' => !empty($ln['vat_report']) ? 1 : 0
                    ];
                }
                foreach ($putCreditLines as $ln) {
                    $putLinesToInsert[] = [
                        'account_id' => intval($ln['account_id']),
                        'debit_amount' => 0.0,
                        'credit_amount' => floatval($ln['amount']),
                        'cost_center_id' => intval($ln['cost_center_id'] ?? 0),
                        'description' => strval($ln['description'] ?? ''),
                        'vat_report' => !empty($ln['vat_report']) ? 1 : 0
                    ];
                }
            }
            $currency = strtoupper($data['currency'] ?? 'SAR');
            // Extract currency code if format is "CODE - Name"
            if (strpos($currency, ' - ') !== false) {
                $currency = trim(explode(' - ', $currency)[0]);
            }
            $reference = $data['reference'] ?? null;
            $entryType = isset($data['entry_type']) ? trim($data['entry_type']) : 'Manual';
            $status = isset($data['status']) ? trim($data['status']) : 'Draft';
            // Normalize status - capitalize first letter
            if (!empty($status)) {
                $status = ucfirst(strtolower($status));
            }
            
            // Check if posting (status changing to "Posted")
            $oldStatus = $oldEntry['status'] ?? 'Draft';
            $isPosting = ($oldStatus !== 'Posted' && $status === 'Posted');
            
            // ERP-GRADE POSTING CONTROLS: Validate posting date
            if ($isPosting) {
                // Validate posting date is not in closed period
                try {
                    validatePostingDate($conn, $entryDate);
                } catch (Exception $e) {
                    throw new Exception('Cannot post entry: ' . $e->getMessage());
                }
                
                // Require approval before posting - check if entry has been approved
                // Check if entry_approval table exists and if this entry has been approved
                $approvalTableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
                if ($approvalTableCheck && $approvalTableCheck->num_rows > 0) {
                    $approvalTableCheck->free();
                    $approvalCheck = $conn->prepare("SELECT status FROM entry_approval WHERE journal_entry_id = ? AND status = 'approved' LIMIT 1");
                    $approvalCheck->bind_param('i', $entryId);
                    $approvalCheck->execute();
                    $approvalResult = $approvalCheck->get_result();
                    $hasApproval = $approvalResult->num_rows > 0;
                    $approvalResult->free();
                    $approvalCheck->close();
                    
                    if (!$hasApproval) {
                        throw new Exception('Cannot post journal entry without approval. Entry must be approved through Entry Approval workflow first.');
                    }
                } else {
                    if ($approvalTableCheck) {
                        $approvalTableCheck->free();
                    }
                    // If approval table doesn't exist, log warning but allow (backward compatibility)
                    error_log("WARNING: Entry approval table not found. Allowing direct posting without approval check.");
                }
            }
            
            // If posting, validate balance
            if ($isPosting) {
                // Calculate totals from journal_entry_lines
                $balanceCheck = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(debit_amount), 0) as total_debit,
                        COALESCE(SUM(credit_amount), 0) as total_credit
                    FROM journal_entry_lines
                    WHERE journal_entry_id = ?
                ");
                $balanceCheck->bind_param('i', $entryId);
                $balanceCheck->execute();
                $balanceResult = $balanceCheck->get_result();
                $balanceData = $balanceResult->fetch_assoc();
                $balanceResult->free();
                $balanceCheck->close();
                
                $lineTotalDebit = floatval($balanceData['total_debit'] ?? 0);
                $lineTotalCredit = floatval($balanceData['total_credit'] ?? 0);
                
                // Validate that entry has lines (at least one non-zero amount)
                if ($lineTotalDebit == 0 && $lineTotalCredit == 0) {
                    throw new Exception("Cannot post entry with no lines or zero amounts");
                }
                
                // Validate balance (allow small rounding differences - 0.01)
                if (abs($lineTotalDebit - $lineTotalCredit) > 0.01) {
                    throw new Exception("Cannot post unbalanced entry. Debit total: {$lineTotalDebit}, Credit total: {$lineTotalCredit}");
                }
                
                // Update debit and credit to match calculated totals
                $debit = $lineTotalDebit;
                $credit = $lineTotalCredit;
                
                // Post to general ledger
                $ledgerHelperPath = __DIR__ . '/core/general-ledger-helper.php';
                if (file_exists($ledgerHelperPath)) {
                    require_once $ledgerHelperPath;
                    if (function_exists('postJournalEntryToLedger')) {
                        try {
                            $ledgerResult = postJournalEntryToLedger($conn, $entryId);
                            error_log("PUT Update - General ledger posting: " . $ledgerResult['message']);
                            
                            // ERP: Log audit trail for posting
                            logJournalEntryPost($conn, $entryId);
                        } catch (Exception $e) {
                            error_log("PUT Update - WARNING: Failed to post to general ledger: " . $e->getMessage());
                            // Don't fail the transaction, but log the error
                        }
                    }
                }
            }
            // Normalize entry_type - capitalize first letter, rest lowercase
            if (!empty($entryType)) {
                $entryType = ucfirst(strtolower($entryType));
            }
            
            // Check if currency, reference_number, and entry_type columns exist, create them if they don't
            $currencyCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
            $hasCurrency = $currencyCheck->num_rows > 0;
            $currencyCheck->free();
            if (!$hasCurrency) {
                try {
                    $conn->query("ALTER TABLE journal_entries ADD COLUMN currency VARCHAR(10) DEFAULT 'SAR' AFTER status");
                    $hasCurrency = true;
                    error_log("PUT Update - Created currency column in journal_entries table");
                } catch (Exception $e) {
                    error_log("PUT Update - Failed to create currency column: " . $e->getMessage());
                }
            }
            error_log("PUT Update - Currency column check: " . ($hasCurrency ? 'EXISTS' : 'NOT FOUND'));
            
            $referenceCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'reference_number'");
            $hasReference = $referenceCheck->num_rows > 0;
            $referenceCheck->free();
            
            $entryTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'entry_type'");
            $hasEntryType = $entryTypeCheck->num_rows > 0;
            $entryTypeCheck->free();
            if (!$hasEntryType) {
                try {
                    $conn->query("ALTER TABLE journal_entries ADD COLUMN entry_type VARCHAR(50) DEFAULT 'Manual' AFTER description");
                    $hasEntryType = true;
                    error_log("PUT Update - Created entry_type column in journal_entries table");
                } catch (Exception $e) {
                    error_log("PUT Update - Failed to create entry_type column: " . $e->getMessage());
                }
            }
            error_log("PUT Update - Entry_type column check: " . ($hasEntryType ? 'EXISTS' : 'NOT FOUND'));
            
            // After updating lines, recalculate total_debit and total_credit from lines
            // (This happens later in the code, but we calculate here if lines were updated)
            if (isset($data['account_id']) && isset($data['debit']) && isset($data['credit'])) {
                // Lines were updated, recalculate from lines
                $recalcStmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(debit_amount), 0) as total_debit,
                        COALESCE(SUM(credit_amount), 0) as total_credit
                    FROM journal_entry_lines
                    WHERE journal_entry_id = ?
                ");
                $recalcStmt->bind_param('i', $entryId);
                $recalcStmt->execute();
                $recalcResult = $recalcStmt->get_result();
                $recalcData = $recalcResult->fetch_assoc();
                $recalcResult->free();
                $recalcStmt->close();
                
                $calculatedDebit = floatval($recalcData['total_debit'] ?? 0);
                $calculatedCredit = floatval($recalcData['total_credit'] ?? 0);
                
                // Use calculated totals if lines were updated
                if ($calculatedDebit > 0 || $calculatedCredit > 0) {
                    $debit = $calculatedDebit;
                    $credit = $calculatedCredit;
                }
            }
            
            // Build UPDATE query dynamically based on available columns
            $updateFields = ['entry_date = ?', 'description = ?', 'total_debit = ?', 'total_credit = ?', 'status = ?'];
            $bindParams = [$entryDate, $description, $debit, $credit, $status];
            $bindTypes = 'ssdds';
            
            // Add is_posted if column exists (reset to FALSE when unposting)
            if ($hasIsPosted) {
                $oldIsPosted = isset($oldEntry['is_posted']) && ($oldEntry['is_posted'] == 1 || $oldEntry['is_posted'] === true);
                $isPosted = ($status === 'Posted' ? 1 : 0);
                // If unposting (was Posted, now Draft), reset is_posted
                if ($oldIsPosted && $status !== 'Posted') {
                    $isPosted = 0;
                }
                $updateFields[] = 'is_posted = ?';
                $bindParams[] = $isPosted;
                $bindTypes .= 'i';
            }
            
            // Add is_locked if column exists (reset to FALSE when unposting)
            if ($hasIsLocked) {
                $oldIsLocked = isset($oldEntry['is_locked']) && ($oldEntry['is_locked'] == 1 || $oldEntry['is_locked'] === true);
                $isLocked = ($status === 'Posted' ? 1 : 0);
                // If unposting (was Posted, now Draft), reset is_locked
                if ($oldIsLocked && $status !== 'Posted') {
                    $isLocked = 0;
                }
                $updateFields[] = 'is_locked = ?';
                $bindParams[] = $isLocked;
                $bindTypes .= 'i';
            }
            
            // ERP: Add locked_at timestamp when posting
            $lockedAtCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'locked_at'");
            $hasLockedAt = $lockedAtCheck && $lockedAtCheck->num_rows > 0;
            if ($lockedAtCheck) $lockedAtCheck->free();
            if ($hasLockedAt) {
                if ($isPosting) {
                    $updateFields[] = 'locked_at = NOW()';
                } elseif ($oldStatus === 'Posted' && $status !== 'Posted') {
                    // Unposting - clear locked_at
                    $updateFields[] = 'locked_at = NULL';
                }
            }
            
            // ERP: Add posting_status update when posting
            $postingStatusCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'posting_status'");
            $hasPostingStatus = $postingStatusCheck && $postingStatusCheck->num_rows > 0;
            if ($postingStatusCheck) $postingStatusCheck->free();
            if ($hasPostingStatus) {
                if ($isPosting) {
                    $updateFields[] = "posting_status = 'posted'";
                } elseif ($oldStatus === 'Posted' && $status !== 'Posted') {
                    // Unposting - reset to draft
                    $updateFields[] = "posting_status = 'draft'";
                }
            }
            
            if ($hasCurrency) {
                $updateFields[] = 'currency = ?';
                $bindParams[] = $currency;
                $bindTypes .= 's';
            }
            
            if ($hasEntryType) {
                $updateFields[] = 'entry_type = ?';
                $bindParams[] = $entryType;
                $bindTypes .= 's';
            }
            
            if ($hasReference) {
                $updateFields[] = 'reference_number = ?';
                $bindParams[] = $reference;
                $bindTypes .= 's';
            }
            
            error_log("PUT Update - Updating currency: '$currency', entry_type: '$entryType', status: '$status'");
            
            $bindParams[] = $entryId;
            $bindTypes .= 'i';
            
            // Update journal entry
            error_log("PUT Update - About to UPDATE: entryId=$entryId, entry_date='$entryDate', description='" . substr($description, 0, 50) . "', currency='$currency', entry_type='$entryType', hasCurrency=" . ($hasCurrency ? 'yes' : 'no') . ", hasEntryType=" . ($hasEntryType ? 'yes' : 'no') . ", hasReference=" . ($hasReference ? 'yes' : 'no'));
            $stmt = $conn->prepare("
                UPDATE journal_entries 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception('Failed to prepare UPDATE statement: ' . $conn->error);
            }
            $stmt->bind_param($bindTypes, ...$bindParams);
            error_log("PUT Update - Executing UPDATE with bindTypes: '$bindTypes', bindParams count: " . count($bindParams));
            if (!$stmt->execute()) {
                $stmt->close();
                error_log("PUT Update - ERROR: UPDATE failed: " . $stmt->error);
                throw new Exception('Failed to update journal entry: ' . $stmt->error);
            }
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            error_log("PUT Update - UPDATE affected rows: $affectedRows");
            if ($affectedRows === 0) {
                error_log("PUT Update - WARNING: UPDATE affected 0 rows! Entry ID: $entryId");
                error_log("PUT Update - This might mean the values are the same, or the entry doesn't exist");
            } else {
                error_log("PUT Update - SUCCESS: UPDATE affected $affectedRows row(s)");
            }
            
            // Update journal entry line (always process if accountId > 0, or if entity data is provided)
            $entityType = isset($data['entity_type']) ? $data['entity_type'] : null;
            $entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
            $hasEntityData = $entityType && $entityId > 0;
            
            if ($accountId > 0 || $hasEntityData || $hasMultiLines) {
                // Check if journal_entry_lines table exists
                $linesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
                $hasLinesTable = $linesTableCheck->num_rows > 0;
                $linesTableCheck->free();
                
                if (!$hasLinesTable) {
                    error_log("PUT Update - WARNING: journal_entry_lines table does not exist, skipping line update");
                } else {
                    // Check if entity columns exist
                    $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
                    $hasEntityType = $entityTypeCheck->num_rows > 0;
                    $entityTypeCheck->free();
                    
                    $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
                    $hasEntityId = $entityIdCheck->num_rows > 0;
                    $entityIdCheck->free();
                    
                    error_log("PUT Update - Full request data: " . json_encode($data));
                    error_log("PUT Update - Entity data received: entity_type=" . ($entityType ?? 'null') . ", entity_id=" . ($entityId ?? 'null'));
                    error_log("PUT Update - Entity columns exist: hasEntityType=" . ($hasEntityType ? 'yes' : 'no') . ", hasEntityId=" . ($hasEntityId ? 'yes' : 'no'));
                    
                    // If columns don't exist but entity data is provided, try to create them
                    if ((!$hasEntityType || !$hasEntityId) && $hasEntityData) {
                        try {
                            if (!$hasEntityType) {
                                $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_type VARCHAR(50) NULL AFTER credit_amount");
                                $hasEntityType = true;
                                error_log("PUT Update - Added entity_type column to journal_entry_lines table");
                            }
                            if (!$hasEntityId) {
                                $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN entity_id INT(11) NULL AFTER entity_type");
                                $hasEntityId = true;
                                error_log("PUT Update - Added entity_id column to journal_entry_lines table");
                            }
                        } catch (Exception $e) {
                            error_log("PUT Update - Failed to add entity columns: " . $e->getMessage());
                        }
                    }
                    
                    // Use DELETE + INSERT to ensure changes persist (UPDATE can fail silently)
                    error_log("PUT Update - Using DELETE + INSERT approach for journal_entry_lines");
                    
                    // Delete existing line(s) for this journal entry
                    $deleteStmt = $conn->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
                    if (!$deleteStmt) {
                        throw new Exception('Failed to prepare DELETE statement: ' . $conn->error);
                    }
                    $deleteStmt->bind_param('i', $entryId);
                    if (!$deleteStmt->execute()) {
                        $deleteStmt->close();
                        throw new Exception('Failed to delete journal entry lines: ' . $deleteStmt->error);
                    }
                    $deletedRows = $deleteStmt->affected_rows;
                    $deleteStmt->close();
                    error_log("PUT Update - Deleted $deletedRows existing line(s) for journal_entry_id: $entryId");
                    
                    // Check for cost_center_id column
                    $hasCostCenter = false;
                    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
                    if ($costCenterCheck && $costCenterCheck->num_rows > 0) {
                        $hasCostCenter = true;
                    }
                    if ($costCenterCheck) {
                        $costCenterCheck->free();
                    }
                    if (!$hasCostCenter) {
                        try {
                            $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN cost_center_id INT NULL AFTER entity_id");
                            $hasCostCenter = true;
                        } catch (Exception $e) {
                            // Column creation failed, continue without it
                        }
                    }
                    
                    // Get cost_center_id from data if provided
                    $costCenterId = isset($data['cost_center_id']) ? intval($data['cost_center_id']) : null;
                    
                    // Ensure description + vat_report columns exist (needed to show them on edit)
                    $descColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'description'");
                    $hasDescCol = $descColCheck && $descColCheck->num_rows > 0;
                    if ($descColCheck) $descColCheck->free();
                    if (!$hasDescCol) {
                        try {
                            $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN description TEXT NULL AFTER cost_center_id");
                            $hasDescCol = true;
                        } catch (Exception $e) {
                            // ignore
                        }
                    }

                    $vatColCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'vat_report'");
                    $hasVatCol = $vatColCheck && $vatColCheck->num_rows > 0;
                    if ($vatColCheck) $vatColCheck->free();
                    if (!$hasVatCol) {
                        try {
                            $conn->query("ALTER TABLE journal_entry_lines ADD COLUMN vat_report TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
                            $hasVatCol = true;
                        } catch (Exception $e) {
                            // ignore
                        }
                    }

                    // Prepare INSERT for one or many lines
                    $cols = ['journal_entry_id', 'account_id', 'debit_amount', 'credit_amount'];
                    $vals = ['?', '?', '?', '?'];
                    $types = 'iidd';

                    if ($hasEntityType && $hasEntityId && $hasEntityData) {
                        $cols[] = 'entity_type';
                        $vals[] = '?';
                        $types .= 's';
                        $cols[] = 'entity_id';
                        $vals[] = '?';
                        $types .= 'i';
                    }
                    if ($hasCostCenter) {
                        $cols[] = 'cost_center_id';
                        $vals[] = '?';
                        $types .= 'i';
                    }
                    if ($hasDescCol) {
                        $cols[] = 'description';
                        $vals[] = '?';
                        $types .= 's';
                    }
                    if ($hasVatCol) {
                        $cols[] = 'vat_report';
                        $vals[] = '?';
                        $types .= 'i';
                    }

                    $insertSql = "INSERT INTO journal_entry_lines (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
                    $insertStmt = $conn->prepare($insertSql);
                    if (!$insertStmt) {
                        throw new Exception('Failed to prepare INSERT statement: ' . $conn->error);
                    }

                    // Choose payload: multi-lines if provided, else legacy single line
                    $linesPayload = $hasMultiLines ? $putLinesToInsert : [[
                        'account_id' => $accountId,
                        'debit_amount' => $debit,
                        'credit_amount' => $credit,
                        'cost_center_id' => intval($costCenterId ?? 0),
                        'description' => '',
                        'vat_report' => 0
                    ]];

                    foreach ($linesPayload as $ln) {
                        $lnAccountId = intval($ln['account_id'] ?? 0);
                        if ($lnAccountId <= 0) continue;
                        $lnDebit = floatval($ln['debit_amount'] ?? 0);
                        $lnCredit = floatval($ln['credit_amount'] ?? 0);
                        $lnCostCenter = intval($ln['cost_center_id'] ?? 0);
                        $lnDesc = strval($ln['description'] ?? '');
                        $lnVat = intval($ln['vat_report'] ?? 0);

                        // Build params in same order as $cols
                        $params = [$entryId, $lnAccountId, $lnDebit, $lnCredit];
                        $paramTypes = $types;

                        if ($hasEntityType && $hasEntityId && $hasEntityData) {
                            $params[] = $entityType;
                            $params[] = $entityId;
                        }
                        if ($hasCostCenter) {
                            $params[] = $lnCostCenter;
                        }
                        if ($hasDescCol) {
                            $params[] = $lnDesc;
                        }
                        if ($hasVatCol) {
                            $params[] = $lnVat;
                        }

                        $insertStmt->bind_param($paramTypes, ...$params);
                        if (!$insertStmt->execute()) {
                            $insertStmt->close();
                            throw new Exception('Failed to insert journal entry line: ' . $insertStmt->error);
                        }
                    }
                    $insertStmt->close();
                    error_log("PUT Update - Journal entry line(s) inserted successfully");
                }
            }
            
            // After lines were updated, recalculate totals and update journal_entries if needed
            if (isset($data['account_id']) && ($accountId > 0 || $hasEntityData)) {
                $finalRecalcStmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(debit_amount), 0) as total_debit,
                        COALESCE(SUM(credit_amount), 0) as total_credit
                    FROM journal_entry_lines
                    WHERE journal_entry_id = ?
                ");
                $finalRecalcStmt->bind_param('i', $entryId);
                $finalRecalcStmt->execute();
                $finalRecalcResult = $finalRecalcStmt->get_result();
                $finalRecalcData = $finalRecalcResult->fetch_assoc();
                $finalRecalcResult->free();
                $finalRecalcStmt->close();
                
                $finalDebit = floatval($finalRecalcData['total_debit'] ?? 0);
                $finalCredit = floatval($finalRecalcData['total_credit'] ?? 0);
                
                // Update journal_entries with correct totals from lines
                $updateTotalsStmt = $conn->prepare("UPDATE journal_entries SET total_debit = ?, total_credit = ? WHERE id = ?");
                $updateTotalsStmt->bind_param('ddi', $finalDebit, $finalCredit, $entryId);
                $updateTotalsStmt->execute();
                $updateTotalsStmt->close();
                error_log("PUT Update - Updated total_debit={$finalDebit}, total_credit={$finalCredit} from lines");
            }
            
            $conn->commit();
            
            // Small delay to ensure commit is processed
            usleep(200000); // 200ms
            
            // Fetch updated entry to return in response (including currency, entry_type, and status to verify they were saved)
            $fetchFields = ['id', 'entry_number', 'entry_date', 'description', 'total_debit', 'total_credit', 'status'];
            if ($hasCurrency) {
                $fetchFields[] = 'currency';
            }
            if ($hasEntryType) {
                $fetchFields[] = 'entry_type';
            }
            $fetchStmt = $conn->prepare("SELECT " . implode(', ', $fetchFields) . " FROM journal_entries WHERE id = ?");
            $fetchStmt->bind_param('i', $entryId);
            $fetchStmt->execute();
            $fetchResult = $fetchStmt->get_result();
            $updatedEntry = $fetchResult->fetch_assoc();
            $fetchResult->free();
            $fetchStmt->close();
            
            // Log what was actually fetched from DB to verify the UPDATE worked
            if (isset($updatedEntry['entry_date'])) {
                error_log("PUT Update - Fetched entry_date from DB: '" . $updatedEntry['entry_date'] . "' (expected: '$entryDate')");
            }
            if ($hasCurrency && isset($updatedEntry['currency'])) {
                error_log("PUT Update - Fetched currency from DB: '" . $updatedEntry['currency'] . "' (expected: '$currency')");
            }
            if ($hasEntryType && isset($updatedEntry['entry_type'])) {
                error_log("PUT Update - Fetched entry_type from DB: '" . $updatedEntry['entry_type'] . "' (expected: '$entryType')");
            }
            if (isset($updatedEntry['status'])) {
                error_log("PUT Update - Fetched status from DB: '" . $updatedEntry['status'] . "' (expected: '$status')");
            }
            
            // Get full updated entry for history
            $fullFetchStmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
            $fullFetchStmt->bind_param('i', $entryId);
            $fullFetchStmt->execute();
            $fullFetchResult = $fullFetchStmt->get_result();
            $fullUpdatedEntry = $fullFetchResult->fetch_assoc();
            
            // ERP: Log audit trail for update
            if ($oldEntry && $fullUpdatedEntry) {
                logJournalEntryUpdate($conn, $entryId, $oldEntry, $fullUpdatedEntry);
                
                // Also log to global history (if exists)
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        @logGlobalHistory('journal_entries', $entryId, 'update', 'accounting', $oldEntry, $fullUpdatedEntry);
                    }
                }
            }
            
            // Get entity data from request - prioritize sent data since we just saved it
            $entityTypeSent = isset($data['entity_type']) ? $data['entity_type'] : null;
            $entityIdSent = isset($data['entity_id']) ? intval($data['entity_id']) : null;
            
            // Use sent data directly (we just saved it, so it's the most accurate)
            $entityTypeInDb = $entityTypeSent;
            $entityIdInDb = $entityIdSent;
            
            // Verify the data was saved correctly (for debugging)
            // Ensure $hasEntityType and $hasEntityId are defined
            if (!isset($hasEntityType) || !isset($hasEntityId)) {
                $linesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
                $hasLinesTable = $linesTableCheck->num_rows > 0;
                if ($hasLinesTable) {
                    $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
                    $hasEntityType = $entityTypeCheck->num_rows > 0;
                    $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
                    $hasEntityId = $entityIdCheck->num_rows > 0;
                } else {
                    $hasEntityType = false;
                    $hasEntityId = false;
                }
            }
            
            if ($hasEntityType && $hasEntityId && $entityTypeSent && $entityIdSent) {
                // Verify what was actually saved (for debugging only)
                $entityFetchStmt = $conn->prepare("SELECT entity_type, entity_id FROM journal_entry_lines WHERE journal_entry_id = ? LIMIT 1");
                $entityFetchStmt->bind_param('i', $entryId);
                $entityFetchStmt->execute();
                $entityFetchResult = $entityFetchStmt->get_result();
                if ($entityRow = $entityFetchResult->fetch_assoc()) {
                    $fetchedEntityType = $entityRow['entity_type'] ?? null;
                    $fetchedEntityId = isset($entityRow['entity_id']) ? intval($entityRow['entity_id']) : null;
                    error_log("PUT Update - Sent entity: $entityTypeSent:$entityIdSent, Fetched entity: " . ($fetchedEntityType ?? 'null') . ":" . ($fetchedEntityId ?? 'null'));
                    
                    // If fetched data differs from sent, log warning but still return sent data
                    if ($fetchedEntityType !== $entityTypeSent || $fetchedEntityId !== $entityIdSent) {
                        error_log("PUT Update - WARNING: Entity mismatch! Sent: $entityTypeSent:$entityIdSent, DB has: " . ($fetchedEntityType ?? 'null') . ":" . ($fetchedEntityId ?? 'null'));
                    }
                } else {
                    error_log("PUT Update - No entity row found in DB after save, but sent: $entityTypeSent:$entityIdSent");
                }
            }
            
            error_log("PUT Update - Returning entity: " . ($entityTypeInDb ?? 'null') . ":" . ($entityIdInDb ?? 'null'));
            
            // CRITICAL: Use $originalData array (stored before any processing) to ensure we return EXACTLY what was sent
            // This avoids any potential modifications to $data during processing
            
            // Log what's in $originalData right before constructing response
            error_log("PUT Update - Checking \$originalData before response:");
            error_log("PUT Update -   originalData['entry_date']: " . (isset($originalData['entry_date']) ? $originalData['entry_date'] : 'NOT SET'));
            error_log("PUT Update -   originalData['description']: '" . (isset($originalData['description']) ? substr($originalData['description'], 0, 100) : 'NOT SET') . "'");
            error_log("PUT Update -   originalData['entity_type']: " . (isset($originalData['entity_type']) ? $originalData['entity_type'] : 'NOT SET'));
            error_log("PUT Update -   originalData['entity_id']: " . (isset($originalData['entity_id']) ? $originalData['entity_id'] : 'NOT SET'));
            
            // Extract values directly from $originalData with explicit checks
            $responseDescription = isset($originalData['description']) ? (string)$originalData['description'] : '';
            // Use the normalized $entryDate (already converted to YYYY-MM-DD) and format for display
            $responseEntryDate = $entryDate ? formatDateForDisplay($entryDate) : null;
            $responseEntityType = isset($originalData['entity_type']) ? (string)$originalData['entity_type'] : null;
            $responseEntityId = isset($originalData['entity_id']) ? intval($originalData['entity_id']) : null;
            
            // Extract currency and entry_type from originalData
            $responseCurrency = isset($originalData['currency']) ? strtoupper(trim((string)$originalData['currency'])) : 'SAR';
            // Extract currency code if format is "CODE - Name"
            if (strpos($responseCurrency, ' - ') !== false) {
                $responseCurrency = trim(explode(' - ', $responseCurrency)[0]);
            }
            
            $responseEntryType = isset($originalData['entry_type']) ? trim((string)$originalData['entry_type']) : 'Manual';
            // Normalize entry_type - capitalize first letter, rest lowercase
            if (!empty($responseEntryType)) {
                $responseEntryType = ucfirst(strtolower($responseEntryType));
            }
            
            error_log("PUT Update - Extracted response values:");
            error_log("PUT Update -   responseEntryDate: " . ($responseEntryDate ?? 'null'));
            error_log("PUT Update -   responseDescription: '" . substr($responseDescription, 0, 100) . "'");
            error_log("PUT Update -   responseCurrency: " . ($responseCurrency ?? 'null'));
            error_log("PUT Update -   responseEntryType: " . ($responseEntryType ?? 'null'));
            error_log("PUT Update -   responseEntityType: " . ($responseEntityType ?? 'null'));
            error_log("PUT Update -   responseEntityId: " . ($responseEntityId ?? 'null'));
            
            $responseStatus = isset($originalData['status']) ? trim($originalData['status']) : ($updatedEntry['status'] ?? 'Draft');
            // Normalize status
            if (!empty($responseStatus)) {
                $responseStatus = ucfirst(strtolower($responseStatus));
            }
            
            $responseEntry = [
                'id' => $entryId,
                'entry_number' => $updatedEntry['entry_number'] ?? null,
                'description' => $responseDescription, // DIRECT from originalData
                'entry_date' => $responseEntryDate ? formatDateForDisplay($responseEntryDate) : null, // Format for display
                'currency' => $responseCurrency, // DIRECT from originalData
                'entry_type' => $responseEntryType, // DIRECT from originalData
                'status' => $responseStatus, // From originalData or updatedEntry
                'entity_type' => $responseEntityType, // DIRECT from originalData
                'entity_id' => $responseEntityId // DIRECT from originalData
            ];
            
            error_log("PUT Update - Final response entry being sent:");
            error_log("PUT Update -   description: '" . substr($responseEntry['description'], 0, 100) . "'");
            error_log("PUT Update -   currency: " . ($responseEntry['currency'] ?? 'null'));
            error_log("PUT Update -   entry_type: " . ($responseEntry['entry_type'] ?? 'null'));
            error_log("PUT Update -   entity_type: " . ($responseEntry['entity_type'] ?? 'null'));
            error_log("PUT Update -   entity_id: " . ($responseEntry['entity_id'] ?? 'null'));
            
            // Format dates in response entry
            $responseEntry = formatDatesInArray($responseEntry);
            
            $finalResponse = [
                'success' => true,
                'message' => 'Journal entry updated successfully',
                'entry' => $responseEntry
            ];
            
            error_log("PUT Update - Complete response JSON: " . json_encode($finalResponse));
            
            echo json_encode($finalResponse);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        // Delete journal entry or financial transaction
        $entryId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $source = isset($_GET['source']) ? $_GET['source'] : null; // 'journal' or 'transaction'
        error_log("DELETE - Request to delete entry ID: $entryId, source: " . ($source ?? 'not specified'));
        
        if ($entryId <= 0) {
            error_log("DELETE - ERROR: Invalid entry ID: $entryId");
            throw new Exception('Entry ID is required');
        }
        
        $conn->begin_transaction();
        try {
            $deletedRows = 0;
            $deletedLines = 0;
            $deletedSource = null;
            
            // First try journal_entries
            $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE id = ?");
            $checkStmt->bind_param('i', $entryId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $isJournalEntry = $checkResult->num_rows > 0;
            $checkResult->free();
            $checkStmt->close();
            
            if ($isJournalEntry) {
                // ERP-GRADE POSTING CONTROLS: Use centralized validation
                $deleteCheck = canDeleteJournalEntry($conn, $entryId);
                if (!$deleteCheck['can_delete']) {
                    $conn->rollback();
                    throw new Exception($deleteCheck['reason']);
                }
                
                // Period check already done in canDeleteJournalEntry() above
                
                error_log("DELETE - Entry ID $entryId found in journal_entries, deleting...");
                $deletedSource = 'journal';
                
                // ERP-GRADE POSTING CONTROLS: Use centralized validation
                $deleteCheck = canDeleteJournalEntry($conn, $entryId);
                if (!$deleteCheck['can_delete']) {
                    $conn->rollback();
                    throw new Exception($deleteCheck['reason']);
                }
                
                // Get deleted data for history (before deletion)
                $deleteFetchStmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
                $deleteFetchStmt->bind_param('i', $entryId);
                $deleteFetchStmt->execute();
                $deleteFetchResult = $deleteFetchStmt->get_result();
                $deletedEntry = $deleteFetchResult->fetch_assoc();
                $deleteFetchResult->free();
                $deleteFetchStmt->close();
                
            // Delete journal entry lines first
                error_log("DELETE - Deleting journal_entry_lines for journal_entry_id: $entryId");
            $lineStmt = $conn->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?");
                if (!$lineStmt) {
                    throw new Exception('Failed to prepare DELETE statement for lines: ' . $conn->error);
                }
            $lineStmt->bind_param('i', $entryId);
                if (!$lineStmt->execute()) {
                    $lineStmt->close();
                    throw new Exception('Failed to delete journal entry lines: ' . $lineStmt->error);
                }
                $deletedLines = $lineStmt->affected_rows;
                $lineStmt->close();
                error_log("DELETE - Deleted $deletedLines line(s) from journal_entry_lines");
            
            // Delete journal entry
                error_log("DELETE - Deleting journal entry ID: $entryId");
            $stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ?");
                if (!$stmt) {
                    throw new Exception('Failed to prepare DELETE statement: ' . $conn->error);
                }
            $stmt->bind_param('i', $entryId);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to delete journal entry: ' . $stmt->error);
                }
                $deletedRows = $stmt->affected_rows;
                error_log("DELETE - Journal entry affected rows: $deletedRows");
                
                // Log history
                if ($deletedEntry) {
                    $helperPath = __DIR__ . '/../core/global-history-helper.php';
                    if (file_exists($helperPath)) {
                        require_once $helperPath;
                        if (function_exists('logGlobalHistory')) {
                            @logGlobalHistory('journal_entries', $entryId, 'delete', 'accounting', $deletedEntry, null);
                        }
                    }
                }
            } else {
                // Try financial_transactions
                $ftTableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
                if ($ftTableCheck->num_rows > 0) {
                    $ftCheckStmt = $conn->prepare("SELECT id FROM financial_transactions WHERE id = ?");
                    $ftCheckStmt->bind_param('i', $entryId);
                    $ftCheckStmt->execute();
                    $ftCheckResult = $ftCheckStmt->get_result();
                    $isTransaction = $ftCheckResult->num_rows > 0;
                    $ftCheckResult->free();
                    $ftCheckStmt->close();
                    
                    if ($isTransaction) {
                        error_log("DELETE - Entry ID $entryId found in financial_transactions, deleting...");
                        $deletedSource = 'transaction';
                        
                        // Delete transaction lines first
                        $ftLineStmt = $conn->prepare("DELETE FROM transaction_lines WHERE transaction_id = ?");
                        if ($ftLineStmt) {
                            $ftLineStmt->bind_param('i', $entryId);
                            $ftLineStmt->execute();
                            $deletedLines = $ftLineStmt->affected_rows;
                            $ftLineStmt->close();
                            error_log("DELETE - Deleted $deletedLines line(s) from transaction_lines");
                        }
                        
                        // Delete entity_transactions if exists
                        $etStmt = $conn->prepare("DELETE FROM entity_transactions WHERE transaction_id = ?");
                        if ($etStmt) {
                            $etStmt->bind_param('i', $entryId);
                            $etStmt->execute();
                            $etStmt->close();
                            error_log("DELETE - Deleted entity_transactions for transaction_id: $entryId");
                        }
                        
                        // Delete financial transaction
                    $ftTableCheck->free();
                        $ftStmt = $conn->prepare("DELETE FROM financial_transactions WHERE id = ?");
                        if (!$ftStmt) {
                            throw new Exception('Failed to prepare DELETE statement for transaction: ' . $conn->error);
                        }
                        $ftStmt->bind_param('i', $entryId);
                        if (!$ftStmt->execute()) {
                            throw new Exception('Failed to delete financial transaction: ' . $ftStmt->error);
                        }
                        $deletedRows = $ftStmt->affected_rows;
                        error_log("DELETE - Financial transaction affected rows: $deletedRows");
                    }
                }
            }
            
            if ($deletedRows === 0) {
                error_log("DELETE - ERROR: Entry ID $entryId not found in journal_entries or financial_transactions");
                $conn->rollback();
                throw new Exception('Entry not found. Entry may not exist or may have already been deleted.');
            }
            
            $conn->commit();
            error_log("DELETE - SUCCESS: Entry ID $entryId deleted from $deletedSource table");
            
            // Small delay to ensure commit is fully processed
            usleep(100000); // 100ms
            
            echo json_encode([
                'success' => true,
                'message' => 'Entry deleted successfully',
                'deleted_id' => $entryId,
                'deleted_source' => $deletedSource,
                'deleted_lines' => $deletedLines
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            error_log("DELETE - ERROR: Failed to delete entry ID $entryId: " . $e->getMessage());
            throw $e;
        }
    }

} catch (Throwable $e) {
    error_log('Journal entries error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
