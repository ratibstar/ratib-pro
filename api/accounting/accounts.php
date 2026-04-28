<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/accounts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/accounts.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any previous output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'A fatal server error occurred: ' . $error['message'],
            'error_details' => [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]
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
        enforceApiPermission('accounts', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('accounts', 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission('accounts', 'update');
    } elseif ($method === 'DELETE') {
        enforceApiPermission('accounts', 'delete');
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    // Check if financial_accounts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
    if ($tableCheck->num_rows === 0) {
        // Try to get accounts from journal_entry_lines if it exists
        $journalLinesCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
        if ($journalLinesCheck->num_rows > 0) {
            // Check columns
            $columnsCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines");
            $hasAccountCode = false;
            $hasAccountName = false;
            $hasAccountId = false;

            if ($columnsCheck) {
                while ($col = $columnsCheck->fetch_assoc()) {
                    if ($col['Field'] === 'account_code') $hasAccountCode = true;
                    if ($col['Field'] === 'account_name') $hasAccountName = true;
                    if ($col['Field'] === 'account_id') $hasAccountId = true;
                }
            }

            if ($hasAccountCode || $hasAccountName || $hasAccountId) {
                $selectFields = [];
                if ($hasAccountId) {
                    $selectFields[] = "COALESCE(jel.account_id, 0) as account_id";
                } else {
                    $selectFields[] = "0 as account_id";
                }
                if ($hasAccountCode) {
                    $selectFields[] = "jel.account_code";
                } else {
                    $selectFields[] = "CONCAT('ACC-', COALESCE(jel.account_id, jel.id)) as account_code";
                }
                if ($hasAccountName) {
                    $selectFields[] = "jel.account_name";
                } else {
                    $selectFields[] = "CONCAT('Account #', COALESCE(jel.account_id, jel.id)) as account_name";
                }

                $whereClause = [];
                if ($hasAccountId) {
                    $whereClause[] = "jel.account_id IS NOT NULL AND jel.account_id != 0";
                }
                if ($hasAccountCode) {
                    $whereClause[] = "jel.account_code IS NOT NULL AND jel.account_code != ''";
                }

                $accountQuery = "SELECT DISTINCT " . implode(', ', $selectFields) . "
                                FROM journal_entry_lines jel";
                if (!empty($whereClause)) {
                    $accountQuery .= " WHERE " . implode(' OR ', $whereClause);
                } else {
                    $accountQuery .= " WHERE jel.id IS NOT NULL";
                }
                $accountQuery .= " ORDER BY jel.account_code, jel.account_id LIMIT 100";

                $accountResult = $conn->query($accountQuery);
                $accounts = [];

                if ($accountResult && $accountResult->num_rows > 0) {
                    $counter = 1;
                    while ($row = $accountResult->fetch_assoc()) {
                        $accountId = intval($row['account_id']);
                        if ($accountId === 0) {
                            $accountId = $counter++;
                        }
                        $accountCode = isset($row['account_code']) && $row['account_code'] ? $row['account_code'] : ('ACC-' . str_pad($accountId, 4, '0', STR_PAD_LEFT));
                        $accountName = isset($row['account_name']) && $row['account_name'] ? $row['account_name'] : ('Account #' . $accountId);

                        if (!$accountCode && !$accountName) continue;

                        $accounts[] = [
                            'id' => $accountId,
                            'account_code' => $accountCode,
                            'account_name' => $accountName,
                            'account_type' => 'ASSET',
                            'category' => null,
                            'parent_account_id' => null,
                            'opening_balance' => 0,
                            'current_balance' => 0,
                            'normal_balance' => 'DEBIT',
                            'is_active' => true,
                            'description' => null
                        ];
                    }
                }

                if (count($accounts) > 0) {
                    echo json_encode([
                        'success' => true,
                        'accounts' => $accounts
                    ]);
                    exit;
                }
            }
        }

        // Return empty - dropdown will show "All Accounts" only
        echo json_encode([
            'success' => true,
            'accounts' => []
        ]);
        exit;
    }

    $accountType = isset($_GET['account_type']) ? $_GET['account_type'] : null;
    $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : 1;

    // Check which columns exist in the table
    $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
    $hasCategory = false;
    $hasParentAccountId = false;
    $hasOpeningBalance = false;
    $hasCurrentBalance = false;
    $hasParentId = false;
    $hasNormalBalance = false;
    $hasCurrency = false;
    $hasEntityType = false;
    $hasEntityId = false;

    if ($columnsCheck) {
        while ($col = $columnsCheck->fetch_assoc()) {
            if ($col['Field'] === 'category') $hasCategory = true;
            if ($col['Field'] === 'parent_account_id') $hasParentAccountId = true;
            if ($col['Field'] === 'parent_id') $hasParentId = true;
            if ($col['Field'] === 'opening_balance') $hasOpeningBalance = true;
            if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
            if ($col['Field'] === 'normal_balance') $hasNormalBalance = true;
            if ($col['Field'] === 'currency') $hasCurrency = true;
            if ($col['Field'] === 'entity_type') $hasEntityType = true;
            if ($col['Field'] === 'entity_id') $hasEntityId = true;
        }
    }

    // Build SELECT fields based on what exists
    $selectFields = [
        'id',
        'account_code',
        'account_name',
        'account_type',
        'is_active',
        'description'
    ];
    if ($hasEntityType) {
        $selectFields[] = 'entity_type';
    } else {
        $selectFields[] = 'NULL as entity_type';
    }
    if ($hasEntityId) {
        $selectFields[] = 'entity_id';
    } else {
        $selectFields[] = 'NULL as entity_id';
    }

    if ($hasCategory) {
        $selectFields[] = 'category';
    } else {
        $selectFields[] = 'NULL as category';
    }

    if ($hasParentAccountId) {
        $selectFields[] = 'parent_account_id';
    } elseif ($hasParentId) {
        $selectFields[] = 'parent_id as parent_account_id';
    } else {
        $selectFields[] = 'NULL as parent_account_id';
    }

    if ($hasOpeningBalance) {
        $selectFields[] = 'opening_balance';
    } else {
        $selectFields[] = '0.00 as opening_balance';
    }

    if ($hasCurrentBalance) {
        $selectFields[] = 'current_balance';
    } else {
        $selectFields[] = '0.00 as current_balance';
    }

    if ($hasNormalBalance) {
        $selectFields[] = 'normal_balance';
    } else {
        $selectFields[] = "'DEBIT' as normal_balance";
    }

    if ($hasCurrency) {
        $selectFields[] = 'currency';
    } else {
        $selectFields[] = "'SAR' as currency";
    }

    $query = "SELECT " . implode(', ', $selectFields) . "
        FROM financial_accounts
        WHERE is_active = ?
    ";

    $params = [$isActive];
    $types = 'i';

    if ($accountType) {
        // Convert to uppercase for case-insensitive comparison with ENUM
        $accountType = strtoupper($accountType);
        $query .= " AND UPPER(account_type) = ?";
        $params[] = $accountType;
        $types .= 's';
    }

    $query .= " ORDER BY account_code, account_name";

    // Ensure every agent, subagent, worker, hr has a GL account. Uses entities.php (same logic as format=sections).
    $ensureEntityAccounts = isset($_GET['ensure_entity_accounts']) && $_GET['ensure_entity_accounts'] === '1';
    if ($method === 'GET' && !isset($_GET['id']) && $ensureEntityAccounts) {
        // Ensure columns exist first
        if (!$hasEntityType && $conn->query("ALTER TABLE financial_accounts ADD COLUMN entity_type VARCHAR(50) NULL DEFAULT NULL")) {
            $hasEntityType = true;
        }
        if (!$hasEntityId && $conn->query("ALTER TABLE financial_accounts ADD COLUMN entity_id INT NULL DEFAULT NULL")) {
            $hasEntityId = true;
        }
        
        if ($hasEntityType && $hasEntityId) {
            // First method: Use buildEntityAccountSections
            try {
                if (!defined('ENTITY_SECTIONS_ONLY')) define('ENTITY_SECTIONS_ONLY', 1);
                require_once __DIR__ . '/entities.php';
                $out = buildEntityAccountSections($conn, true);
                $prefixMap = ['agent' => '43', 'subagent' => '44', 'worker' => '45', 'hr' => '46', 'accounting' => '47'];
                foreach ($out['sections'] as $section) {
                    $et = $section['entity_type'] ?? '';
                    $entities = $section['entities'] ?? [];
                    foreach ($entities as $e) {
                        if (!empty($e['account_id'])) continue;
                        $eid = (int)($e['id'] ?? 0);
                        $name = trim($e['name'] ?? '');
                        if ($eid <= 0 || $name === '') continue;
                        $prefix = $prefixMap[$et] ?? '49';
                        $maxRes = $conn->query("SELECT MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)) AS mx FROM financial_accounts WHERE account_code LIKE '" . $conn->real_escape_string($prefix) . "%' AND LENGTH(account_code) >= 4");
                        $nextNum = 1;
                        if ($maxRes && $r = $maxRes->fetch_assoc() && isset($r['mx'])) $nextNum = (int)$r['mx'] + 1;
                        $acode = $prefix . str_pad((string)$nextNum, 2, '0', STR_PAD_LEFT);
                        $atype = in_array($et, ['agent', 'subagent']) ? 'Income' : 'Expense';
                        $nbal = ($atype === 'Income') ? 'Credit' : 'Debit';
                        $ins = $conn->prepare("INSERT INTO financial_accounts (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active, entity_type, entity_id) VALUES (?, ?, ?, ?, 0, 0, 1, ?, ?)");
                        if ($ins) {
                            $ins->bind_param('sssssi', $acode, $name, $atype, $nbal, $et, $eid);
                            if (!$ins->execute()) {
                                error_log("accounts.php ensure_entity_accounts: INSERT failed for entity_type=$et entity_id=$eid: " . $conn->error);
                            }
                            $ins->close();
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log("accounts.php ensure_entity_accounts exception in buildEntityAccountSections: " . $e->getMessage());
                error_log("accounts.php ensure_entity_accounts stack trace: " . $e->getTraceAsString());
            }
            
            // ALWAYS run direct fallback: create accounts for ALL entity types by querying tables directly (no filters)
            // This ensures ALL agents, subagents, workers, and HR have accounts, even if buildEntityAccountSections missed some
            // This runs even if buildEntityAccountSections failed
            try {
                foreach ([
                    'agent' => ['table' => 'agents', 'nameCols' => ['agent_name', 'full_name', 'name'], 'idCol' => 'id'],
                    'subagent' => ['table' => 'subagents', 'nameCols' => ['subagent_name', 'full_name', 'name'], 'idCol' => 'id'],
                    'worker' => ['table' => 'workers', 'nameCols' => ['worker_name', 'full_name', 'name'], 'idCol' => 'id'],
                    'hr' => ['table' => 'employees', 'nameCols' => ['name', 'employee_name', 'full_name'], 'idCol' => 'id'],
                    'accounting' => ['table' => 'users', 'nameCols' => ['username'], 'idCol' => 'user_id']
                ] as $et => $cfg) {
                    $t = $conn->real_escape_string($cfg['table']);
                    $tblCheck = $conn->query("SHOW TABLES LIKE '$t'");
                    if (!$tblCheck || $tblCheck->num_rows === 0) {
                        error_log("accounts.php direct fallback: Table $t does not exist");
                        continue;
                    }
                    $nameCol = null;
                    foreach ($cfg['nameCols'] as $c) {
                        $esc = $conn->real_escape_string($c);
                        $colCheck = $conn->query("SHOW COLUMNS FROM $t LIKE '$esc'");
                        if ($colCheck && $colCheck->num_rows > 0) { $nameCol = $c; break; }
                    }
                    if (!$nameCol) {
                        error_log("accounts.php direct fallback: No name column found in $t");
                        continue;
                    }
                    $idCol = isset($cfg['idCol']) ? $conn->real_escape_string($cfg['idCol']) : 'id';
                    $escCol = '`' . $conn->real_escape_string($nameCol) . '`';
                    // Accounting: only users with role Accounting
                    if ($et === 'accounting' && $t === 'users') {
                        $res = $conn->query("SELECT u.user_id AS id, TRIM(COALESCE(u.username,'')) AS disp_name FROM users u INNER JOIN roles r ON u.role_id = r.role_id WHERE LOWER(TRIM(r.role_name)) = 'accounting' AND COALESCE(u.username,'') != ''");
                    } else {
                        $res = $conn->query("SELECT `$idCol` AS id, TRIM(COALESCE($escCol,'')) AS disp_name FROM $t WHERE COALESCE($escCol,'') != ''");
                    }
                    if (!$res) {
                        error_log("accounts.php direct fallback: Query failed for $t: " . $conn->error);
                        continue;
                    }
                    $rowCount = $res->num_rows;
                    $prefixMap = ['agent' => '43', 'subagent' => '44', 'worker' => '45', 'hr' => '46', 'accounting' => '47'];
                    $prefix = $prefixMap[$et] ?? '49';
                    $createdCount = 0;
                    $skippedCount = 0;
                    while ($row = $res->fetch_assoc()) {
                        $eid = (int)$row['id'];
                        $name = trim($row['disp_name'] ?? '');
                        if ($eid <= 0 || $name === '') {
                            continue;
                        }
                        $chk = $conn->prepare("SELECT 1 FROM financial_accounts WHERE entity_type = ? AND entity_id = ? LIMIT 1");
                        if (!$chk) {
                            error_log("accounts.php direct fallback: Failed to prepare check query for $et entity_id=$eid");
                            continue;
                        }
                        $chk->bind_param('si', $et, $eid);
                        $chk->execute();
                        $chkResult = $chk->get_result();
                        if ($chkResult && $chkResult->num_rows > 0) {
                            $chk->close();
                            $skippedCount++;
                            continue;
                        }
                        $chk->close();
                        $maxRes = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)), 0) AS mx FROM financial_accounts WHERE account_code LIKE '$prefix%' AND LENGTH(account_code) >= 4");
                        $nextNum = 1;
                        if ($maxRes && ($mr = $maxRes->fetch_assoc()) && isset($mr['mx'])) $nextNum = (int)$mr['mx'] + 1;
                        $acode = $prefix . str_pad((string)$nextNum, 2, '0', STR_PAD_LEFT);
                        $atype = in_array($et, ['agent', 'subagent']) ? 'Income' : 'Expense';
                        $nbal = ($atype === 'Income') ? 'Credit' : 'Debit';
                        $ins = $conn->prepare("INSERT INTO financial_accounts (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active, entity_type, entity_id) VALUES (?, ?, ?, ?, 0, 0, 1, ?, ?)");
                        if ($ins) {
                            $ins->bind_param('sssssi', $acode, $name, $atype, $nbal, $et, $eid);
                            if ($ins->execute()) {
                                $createdCount++;
                            } else {
                                error_log("accounts.php direct fallback INSERT FAILED for entity_type=$et entity_id=$eid account_code=$acode name=$name: " . $conn->error . " | Error code: " . $conn->errno);
                            }
                            $ins->close();
                        } else {
                            error_log("accounts.php direct fallback: Failed to prepare INSERT for $et entity_id=$eid: " . $conn->error);
                        }
                    }
                    $res->free();
                    error_log("accounts.php direct fallback SUMMARY for $et: Processed $rowCount rows, Created $createdCount accounts, Skipped $skippedCount (already exist)");
                }
                
                // Rebuild query to include entity_type and entity_id (columns were added if needed)
                $selectFields = array_values(array_diff($selectFields, ['NULL as entity_type', 'NULL as entity_id']));
                if (!in_array('entity_type', $selectFields)) $selectFields[] = 'entity_type';
                if (!in_array('entity_id', $selectFields)) $selectFields[] = 'entity_id';
                $query = "SELECT " . implode(', ', $selectFields) . " FROM financial_accounts WHERE is_active = ?" . ($accountType ? " AND UPPER(account_type) = ?" : "") . " ORDER BY account_code, account_name";
                
                // Log summary of what was created
                $summaryRes = $conn->query("SELECT entity_type, COUNT(*) as cnt FROM financial_accounts WHERE entity_type IS NOT NULL GROUP BY entity_type");
                $createdSummary = [];
                if ($summaryRes) {
                    while ($srow = $summaryRes->fetch_assoc()) {
                        $createdSummary[$srow['entity_type']] = (int)$srow['cnt'];
                    }
                }
                error_log("accounts.php ensure_entity_accounts: Final Summary - " . json_encode($createdSummary));
            } catch (Throwable $e2) {
                error_log("accounts.php direct fallback exception: " . $e2->getMessage());
                error_log("accounts.php direct fallback stack trace: " . $e2->getTraceAsString());
            }
        }
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare accounts query. Check financial_accounts table.');
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Failed to execute accounts query: ' . $conn->error);
    }

    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $acc = [
            'id' => intval($row['id']),
            'account_code' => $row['account_code'],
            'account_name' => $row['account_name'],
            'account_type' => strtoupper($row['account_type']),
            'category' => isset($row['category']) ? $row['category'] : null,
            'parent_account_id' => isset($row['parent_account_id']) && $row['parent_account_id'] ? intval($row['parent_account_id']) : null,
            'normal_balance' => isset($row['normal_balance']) ? strtoupper($row['normal_balance']) : 'DEBIT',
            'opening_balance' => isset($row['opening_balance']) ? floatval($row['opening_balance']) : 0.00,
            'current_balance' => isset($row['current_balance']) ? floatval($row['current_balance']) : 0.00,
            'currency' => isset($row['currency']) ? $row['currency'] : 'SAR',
            'is_active' => boolval($row['is_active']),
            'description' => isset($row['description']) ? $row['description'] : null
        ];
        if ($hasEntityType && isset($row['entity_type'])) {
            $acc['entity_type'] = $row['entity_type'];
        }
        if ($hasEntityId && isset($row['entity_id'])) {
            $acc['entity_id'] = $row['entity_id'] ? intval($row['entity_id']) : null;
        }
        // Resolve entity_name for entity-linked accounts (Agent/SubAgent/Worker/HR connection)
        if ($hasEntityType && $hasEntityId && !empty($row['entity_type']) && !empty($row['entity_id'])) {
            $et = $row['entity_type'];
            $eid = (int)$row['entity_id'];
            $entityName = null;
            $tableMap = ['agent' => ['agents', ['agent_name'], 'id'], 'subagent' => ['subagents', ['subagent_name'], 'id'], 'worker' => ['workers', ['worker_name','full_name','name'], 'id'], 'hr' => ['employees', ['name','employee_name','full_name'], 'id'], 'accounting' => ['users', ['username'], 'user_id']];
            if (isset($tableMap[$et])) {
                list($tbl, $nameCols, $pkCol) = array_pad((array)$tableMap[$et], 3, 'id');
                $tblCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tbl) . "'");
                if ($tblCheck && $tblCheck->num_rows > 0) {
                    foreach ((array)$nameCols as $nameCol) {
                        $colCheck = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '" . $conn->real_escape_string($nameCol) . "'");
                        if ($colCheck && $colCheck->num_rows > 0) {
                            $stmt = $conn->prepare("SELECT `$nameCol` FROM `$tbl` WHERE `$pkCol` = ? LIMIT 1");
                            if ($stmt) {
                                $stmt->bind_param('i', $eid);
                                $stmt->execute();
                                $res = $stmt->get_result();
                                if ($res && $r = $res->fetch_assoc()) $entityName = trim($r[$nameCol] ?? '');
                                $stmt->close();
                            }
                            break;
                        }
                    }
                }
            }
            $acc['entity_name'] = $entityName ?: $row['account_name'];
        }
        $accounts[] = $acc;
    }
    $result->free();
    $stmt->close();

    // Handle different HTTP methods
    if ($method === 'GET') {
        // If requesting a single account by ID
        $accountId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if ($accountId) {
            $account = null;
            $stmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $account = [
                    'id' => intval($row['id']),
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'account_type' => strtoupper($row['account_type']),
                    'parent_id' => isset($row['parent_id']) && $row['parent_id'] ? intval($row['parent_id']) : null,
                    'normal_balance' => isset($row['normal_balance']) ? strtoupper($row['normal_balance']) : 'DEBIT',
                    'opening_balance' => isset($row['opening_balance']) ? floatval($row['opening_balance']) : 0.00,
                    'current_balance' => isset($row['current_balance']) ? floatval($row['current_balance']) : 0.00,
                    'is_active' => boolval($row['is_active']),
                    'description' => isset($row['description']) ? $row['description'] : null
                ];
            }
            $result->free();
            $stmt->close();

            if ($account) {
                echo json_encode([
                    'success' => true,
                    'account' => $account
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Account not found'
                ]);
            }
        } else {
            $entityCounts = ['agent' => 0, 'subagent' => 0, 'worker' => 0, 'hr' => 0, 'accounting' => 0];
            $entityAccountDetails = [];
            foreach ($accounts as $acc) {
                if (isset($acc['entity_type']) && isset($entityCounts[$acc['entity_type']])) {
                    $entityCounts[$acc['entity_type']]++;
                    if (!isset($entityAccountDetails[$acc['entity_type']])) {
                        $entityAccountDetails[$acc['entity_type']] = [];
                    }
                    $entityAccountDetails[$acc['entity_type']][] = [
                        'id' => $acc['id'],
                        'code' => $acc['account_code'],
                        'name' => $acc['account_name'],
                        'entity_id' => $acc['entity_id'] ?? null
                    ];
                }
            }
            
            // Diagnostic info
            $diagnostics = [];
            if ($ensureEntityAccounts) {
                // Check entity tables
                $entityTables = ['workers', 'employees', 'agents', 'subagents'];
                foreach ($entityTables as $tbl) {
                    $tblCheck = $conn->query("SHOW TABLES LIKE '$tbl'");
                    $exists = ($tblCheck && $tblCheck->num_rows > 0);
                    $rowCount = 0;
                    if ($exists) {
                        $cntRes = $conn->query("SELECT COUNT(*) as cnt FROM $tbl");
                        if ($cntRes) $rowCount = (int)$cntRes->fetch_assoc()['cnt'];
                    }
                    $diagnostics[$tbl] = ['exists' => $exists, 'row_count' => $rowCount];
                }
                
                // Check how many entity accounts exist in database
                if ($hasEntityType && $hasEntityId) {
                    $dbEntityRes = $conn->query("SELECT entity_type, COUNT(*) as cnt FROM financial_accounts WHERE entity_type IS NOT NULL AND is_active = 1 GROUP BY entity_type");
                    $dbEntityCounts = [];
                    if ($dbEntityRes) {
                        while ($er = $dbEntityRes->fetch_assoc()) {
                            $dbEntityCounts[$er['entity_type']] = (int)$er['cnt'];
                        }
                    }
                    $diagnostics['entity_accounts_in_db'] = $dbEntityCounts;
                }
            }
            
            echo json_encode([
                'success' => true,
                'accounts' => $accounts,
                'entity_accounts_count' => $entityCounts,
                'entity_accounts_details' => $entityAccountDetails,
                'total_accounts' => count($accounts),
                'diagnostics' => $diagnostics,
                'query_info' => [
                    'is_active_filter' => $isActive,
                    'account_type_filter' => $accountType,
                    'ensure_entity_accounts_called' => $ensureEntityAccounts,
                    'has_entity_columns' => $hasEntityType && $hasEntityId
                ]
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new account
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }

        $accountCode = trim($data['account_code'] ?? '');
        if (strtolower($accountCode) === 'auto-generated') $accountCode = '';
        $accountName = trim($data['account_name'] ?? '');
        $accountTypeRaw = $data['account_type'] ?? '';
        $parentId = isset($data['parent_id']) ? intval($data['parent_id']) : null;
        $normalBalanceRaw = $data['normal_balance'] ?? '';
        $openingBalance = floatval($data['opening_balance'] ?? 0);
        $description = $data['description'] ?? null;
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        $isSystemAccount = isset($data['is_system_account']) ? intval($data['is_system_account']) : 0;
        $entityType = isset($data['entity_type']) ? trim($data['entity_type']) : null;
        $entityId = isset($data['entity_id']) ? (intval($data['entity_id']) ?: null) : null;

        // When creating an account for an entity (Agent/SubAgent/Worker/HR/Accounting), allow auto-generate code/name
        if ($entityType && $entityId && in_array($entityType, ['agent', 'subagent', 'worker', 'hr', 'accounting'])) {
            $ec = $conn->query("SHOW COLUMNS FROM financial_accounts");
            $hasEt = $hasEid = false;
            if ($ec) while ($c = $ec->fetch_assoc()) { if ($c['Field'] === 'entity_type') $hasEt = true; if ($c['Field'] === 'entity_id') $hasEid = true; }
            if ($hasEt && $hasEid) {
                $ex = $conn->prepare("SELECT id FROM financial_accounts WHERE entity_type = ? AND entity_id = ?");
                $ex->bind_param('si', $entityType, $entityId);
                $ex->execute();
                if ($ex->get_result()->num_rows > 0) {
                    $ex->close();
                    throw new Exception('This entity already has an account assigned');
                }
                $ex->close();
                if (empty($accountCode)) {
                    $prefixMap = ['agent' => '43', 'subagent' => '44', 'worker' => '45', 'hr' => '46', 'accounting' => '47'];
                    $prefix = $prefixMap[$entityType] ?? '49';
                    $maxRes = $conn->query("SELECT MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)) AS mx FROM financial_accounts WHERE account_code LIKE '" . $conn->real_escape_string($prefix) . "%' AND LENGTH(account_code) >= 4");
                    $nextNum = 1;
                    if ($maxRes && $row = $maxRes->fetch_assoc() && isset($row['mx'])) $nextNum = (int)$row['mx'] + 1;
                    $accountCode = $prefix . str_pad((string)$nextNum, 2, '0', STR_PAD_LEFT);
                }
                if (empty($accountName)) {
                    $tables = ['agent' => 'agents', 'subagent' => 'subagents', 'worker' => 'workers', 'hr' => 'employees', 'accounting' => 'users'];
                    $nameCols = ['agent' => 'agent_name', 'subagent' => 'subagent_name', 'worker' => 'worker_name', 'hr' => 'name', 'accounting' => 'username'];
                    $pkCols = ['agent' => 'id', 'subagent' => 'id', 'worker' => 'id', 'hr' => 'id', 'accounting' => 'user_id'];
                    $t = $tables[$entityType];
                    $nc = $nameCols[$entityType];
                    $pk = $pkCols[$entityType];
                    $colCheck = $conn->query("SHOW COLUMNS FROM $t LIKE '$nc'");
                    if (!$colCheck || $colCheck->num_rows === 0) $nc = ($entityType === 'accounting') ? 'username' : 'name';
                    $nameRes = $conn->query("SELECT `$nc` as n FROM $t WHERE `$pk` = " . intval($entityId) . " LIMIT 1");
                    $accountName = ($nameRes && $r = $nameRes->fetch_assoc()) ? trim($r['n']) : ($entityType . ' #' . $entityId);
                }
            }
        }

        // Auto-generate account code when empty (based on account_type)
        if (empty($accountCode) && !empty($accountTypeRaw)) {
            $typePrefixMap = ['ASSET' => '1', 'LIABILITY' => '2', 'EQUITY' => '3', 'REVENUE' => '4', 'EXPENSE' => '5'];
            $prefix = $typePrefixMap[strtoupper($accountTypeRaw)] ?? '1';
            $escapedPrefix = $conn->real_escape_string($prefix);
            $maxRes = $conn->query("SELECT MAX(CAST(account_code AS UNSIGNED)) AS mx FROM financial_accounts WHERE account_code LIKE '{$escapedPrefix}%' AND account_code REGEXP '^[0-9]+$'");
            $nextNum = (int)($prefix . '001');
            if ($maxRes && $row = $maxRes->fetch_assoc() && isset($row['mx']) && $row['mx'] > 0) {
                $nextNum = (int)$row['mx'] + 1;
            }
            $accountCode = (string)$nextNum;
        }

        if (empty(trim($accountCode)) || empty(trim($accountName))) {
            throw new Exception('Account code and name are required');
        }
        
        // Determine account_type and normal_balance from account_code if not provided
        if (empty($accountTypeRaw) || empty($normalBalanceRaw)) {
            // Only try regex if account_code is not empty
            $matches = [];
            if (!empty($accountCode)) {
                preg_match('/^(\d)/', $accountCode, $matches);
            }
            if (isset($matches[1])) {
                $firstDigit = intval($matches[1]);
                switch ($firstDigit) {
                    case 1:
                        $accountType = 'ASSET';
                        $normalBalance = 'DEBIT';
                        break;
                    case 2:
                        $accountType = 'LIABILITY';
                        $normalBalance = 'CREDIT';
                        break;
                    case 3:
                        $accountType = 'EQUITY';
                        $normalBalance = 'CREDIT';
                        break;
                    case 4:
                        $accountType = 'REVENUE';
                        $normalBalance = 'CREDIT';
                        break;
                    case 5:
                        $accountType = 'EXPENSE';
                        $normalBalance = 'DEBIT';
                        break;
                    default:
                        $accountType = empty($accountTypeRaw) ? 'ASSET' : strtoupper($accountTypeRaw);
                        $normalBalance = empty($normalBalanceRaw) ? 'DEBIT' : strtoupper($normalBalanceRaw);
                        break;
                }
            } else {
                // No numeric prefix, use defaults or provided values
                $accountType = empty($accountTypeRaw) ? 'ASSET' : strtoupper($accountTypeRaw);
                $normalBalance = empty($normalBalanceRaw) ? 'DEBIT' : strtoupper($normalBalanceRaw);
            }
        } else {
            // Convert to uppercase to match ENUM values
            $accountType = strtoupper($accountTypeRaw);
            $normalBalance = strtoupper($normalBalanceRaw);
        }
        
        // Validate ENUM values
        $validAccountTypes = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'];
        $validNormalBalances = ['DEBIT', 'CREDIT'];
        
        if (!in_array($accountType, $validAccountTypes)) {
            throw new Exception('Invalid account_type. Must be one of: ' . implode(', ', $validAccountTypes));
        }
        
        if (!in_array($normalBalance, $validNormalBalances)) {
            throw new Exception('Invalid normal_balance. Must be one of: ' . implode(', ', $validNormalBalances));
        }
        
        // Validate account_type and normal_balance combination
        $validCombinations = [
            'ASSET' => 'DEBIT',
            'LIABILITY' => 'CREDIT',
            'EQUITY' => 'CREDIT',
            'REVENUE' => 'CREDIT',
            'EXPENSE' => 'DEBIT'
        ];
        
        if ($validCombinations[$accountType] !== $normalBalance) {
            throw new Exception("Invalid account configuration: {$accountType} must have normal_balance of {$validCombinations[$accountType]}, not {$normalBalance}");
        }

        // Check if account code already exists - if so, auto-generate new one (retry once)
        $maxRetries = 2;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $checkStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code = ?");
            $checkStmt->bind_param('s', $accountCode);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->num_rows === 0) {
                $checkResult->free();
                $checkStmt->close();
                break;
            }
            $checkResult->free();
            $checkStmt->close();
            if ($attempt >= $maxRetries - 1) {
                throw new Exception('Account code already exists');
            }
            // Auto-generate new code
            $typePrefixMap = ['ASSET' => '1', 'LIABILITY' => '2', 'EQUITY' => '3', 'REVENUE' => '4', 'EXPENSE' => '5'];
            $prefix = $typePrefixMap[strtoupper($accountType)] ?? '1';
            $escapedPrefix = $conn->real_escape_string($prefix);
            $maxRes = $conn->query("SELECT MAX(CAST(account_code AS UNSIGNED)) AS mx FROM financial_accounts WHERE account_code LIKE '{$escapedPrefix}%' AND account_code REGEXP '^[0-9]+$'");
            $nextNum = (int)($prefix . '001');
            if ($maxRes && $row = $maxRes->fetch_assoc() && isset($row['mx']) && $row['mx'] > 0) {
                $nextNum = (int)$row['mx'] + 1;
            }
            $accountCode = (string)$nextNum;
        }
        
        // Validate parent_id if provided
        if ($parentId !== null && $parentId > 0) {
            $parentCheck = $conn->prepare("SELECT id, is_active FROM financial_accounts WHERE id = ?");
            $parentCheck->bind_param('i', $parentId);
            $parentCheck->execute();
            $parentResult = $parentCheck->get_result();
            $parent = $parentResult->fetch_assoc();
            $parentResult->free();
            
            if (!$parent) {
                $parentCheck->close();
                throw new Exception('Parent account not found');
            }
            
            if (!$parent['is_active']) {
                $parentCheck->close();
                throw new Exception('Cannot set parent to an inactive account');
            }
            
            // Note: Self-reference check only needed during UPDATE, not CREATE
            
            $parentCheck->close();
        }

        $ec = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $existingColumnsPost = [];
        if ($ec) while ($c = $ec->fetch_assoc()) $existingColumnsPost[] = $c['Field'];
        $hasEntityCols = in_array('entity_type', $existingColumnsPost) && in_array('entity_id', $existingColumnsPost);
        $insertCols = "account_code, account_name, account_type, parent_id, normal_balance, opening_balance, current_balance, description, is_active, is_system_account";
        $insertPlaces = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
        $bindTypes = 'sssisddsii';
        $bindParams = [$accountCode, $accountName, $accountType, $parentId !== null ? $parentId : null, $normalBalance, $openingBalance, $openingBalance, $description, $isActive, $isSystemAccount];
        if ($hasEntityCols && ($entityType !== null || $entityId !== null)) {
            $insertCols .= ", entity_type, entity_id";
            $insertPlaces .= ", ?, ?";
            $bindTypes .= 'si';
            $bindParams[] = $entityType;
            $bindParams[] = $entityId;
        }
        $stmt = $conn->prepare("INSERT INTO financial_accounts ($insertCols) VALUES ($insertPlaces)");
        if (!$stmt) {
            throw new Exception('Failed to prepare insert statement: ' . $conn->error);
        }
        $stmt->bind_param($bindTypes, ...$bindParams);
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute insert statement: ' . $stmt->error);
        }
        $accountId = $conn->insert_id;

        // Get created account for history
        $fetchStmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $fetchStmt->bind_param('i', $accountId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $newAccount = $result->fetch_assoc();
        $result->free();
        $fetchStmt->close();

        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $newAccount) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('financial_accounts', $accountId, 'create', 'accounting', null, $newAccount);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully',
            'account_id' => $accountId
        ]);
    } elseif ($method === 'PUT') {
        // Update account
        $accountId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);

        if ($accountId <= 0) {
            throw new Exception('Account ID is required');
        }

        // Get old data for history
        $fetchStmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $fetchStmt->bind_param('i', $accountId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldAccount = $result->fetch_assoc();
        $result->free();
        $fetchStmt->close();

        if (!$oldAccount) {
            throw new Exception('Account not found');
        }

        // Normalize old account values to uppercase (handle legacy case)
        if (isset($oldAccount['account_type'])) {
            $oldAccount['account_type'] = strtoupper($oldAccount['account_type']);
        }
        if (isset($oldAccount['normal_balance'])) {
            $oldAccount['normal_balance'] = strtoupper($oldAccount['normal_balance']);
        }

        $accountCode = $data['account_code'] ?? '';
        $accountName = $data['account_name'] ?? '';
        $accountTypeRaw = $data['account_type'] ?? '';
        $parentId = isset($data['parent_id']) ? intval($data['parent_id']) : null;
        $normalBalanceRaw = $data['normal_balance'] ?? '';
        $description = $data['description'] ?? null;
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;

        if (empty($accountCode) || empty($accountName)) {
            throw new Exception('Account code and name are required');
        }
        
        // If account_type/normal_balance not provided, try to infer from account_code
        if (empty($accountTypeRaw) || empty($normalBalanceRaw)) {
            // Only try regex if account_code is not empty
            $matches = [];
            if (!empty($accountCode)) {
                preg_match('/^(\d)/', $accountCode, $matches);
            }
            if (isset($matches[1])) {
                $firstDigit = intval($matches[1]);
                switch ($firstDigit) {
                    case 1:
                        $accountType = 'ASSET';
                        $normalBalance = 'DEBIT';
                        break;
                    case 2:
                        $accountType = 'LIABILITY';
                        $normalBalance = 'CREDIT';
                        break;
                    case 3:
                        $accountType = 'EQUITY';
                        $normalBalance = 'CREDIT';
                        break;
                    case 4:
                        $accountType = 'REVENUE';
                        $normalBalance = 'CREDIT';
                        break;
                    case 5:
                        $accountType = 'EXPENSE';
                        $normalBalance = 'DEBIT';
                        break;
                    default:
                        // Keep existing values or use defaults
                        if (empty($accountTypeRaw)) {
                            $accountType = $oldAccount['account_type'] ?? 'ASSET';
                        } else {
                            $accountType = strtoupper($accountTypeRaw);
                        }
                        if (empty($normalBalanceRaw)) {
                            $normalBalance = $oldAccount['normal_balance'] ?? 'DEBIT';
                        } else {
                            $normalBalance = strtoupper($normalBalanceRaw);
                        }
                        break;
                }
            } else {
                // Keep existing values or use defaults
                $accountType = empty($accountTypeRaw) ? ($oldAccount['account_type'] ?? 'ASSET') : strtoupper($accountTypeRaw);
                $normalBalance = empty($normalBalanceRaw) ? ($oldAccount['normal_balance'] ?? 'DEBIT') : strtoupper($normalBalanceRaw);
            }
        } else {
            // Convert to uppercase to match ENUM values
            $accountType = strtoupper($accountTypeRaw);
            $normalBalance = strtoupper($normalBalanceRaw);
        }
        
        // Validate ENUM values
        $validAccountTypes = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'];
        $validNormalBalances = ['DEBIT', 'CREDIT'];
        
        if (!in_array($accountType, $validAccountTypes)) {
            throw new Exception('Invalid account_type. Must be one of: ' . implode(', ', $validAccountTypes));
        }
        
        if (!in_array($normalBalance, $validNormalBalances)) {
            throw new Exception('Invalid normal_balance. Must be one of: ' . implode(', ', $validNormalBalances));
        }
        
        // Validate account_type and normal_balance combination
        $validCombinations = [
            'ASSET' => 'DEBIT',
            'LIABILITY' => 'CREDIT',
            'EQUITY' => 'CREDIT',
            'REVENUE' => 'CREDIT',
            'EXPENSE' => 'DEBIT'
        ];
        
        if ($validCombinations[$accountType] !== $normalBalance) {
            throw new Exception("Invalid account configuration: {$accountType} must have normal_balance of {$validCombinations[$accountType]}, not {$normalBalance}");
        }

        // Check if account code already exists for another account
        $checkStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code = ? AND id != ?");
        $checkStmt->bind_param('si', $accountCode, $accountId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkResult->free();
            $checkStmt->close();
            throw new Exception('Account code already exists');
        }
        $checkResult->free();
        $checkStmt->close();
        
        // Validate parent_id if provided
        if ($parentId !== null && $parentId > 0) {
            $parentCheck = $conn->prepare("SELECT id, is_active FROM financial_accounts WHERE id = ?");
            $parentCheck->bind_param('i', $parentId);
            $parentCheck->execute();
            $parentResult = $parentCheck->get_result();
            $parent = $parentResult->fetch_assoc();
            $parentResult->free();
            
            if (!$parent) {
                $parentCheck->close();
                throw new Exception('Parent account not found');
            }
            
            if (!$parent['is_active']) {
                $parentCheck->close();
                throw new Exception('Cannot set parent to an inactive account');
            }
            
            // Prevent circular reference - check if parent_id would create a cycle
            // Check if parent's parent chain includes this account
            $visited = [];
            $currentId = $parentId;
            while ($currentId !== null && $currentId > 0 && !in_array($currentId, $visited)) {
                if ($currentId == $accountId) {
                    $parentCheck->close();
                    throw new Exception('Cannot set parent - would create circular reference');
                }
                $visited[] = $currentId;
                $chainCheck = $conn->prepare("SELECT parent_id FROM financial_accounts WHERE id = ?");
                $chainCheck->bind_param('i', $currentId);
                $chainCheck->execute();
                $chainResult = $chainCheck->get_result();
                $chainRow = $chainResult->fetch_assoc();
                $chainResult->free();
                $chainCheck->close();
                $currentId = $chainRow && $chainRow['parent_id'] ? intval($chainRow['parent_id']) : null;
                // Safety limit to prevent infinite loop
                if (count($visited) > 100) {
                    break;
                }
            }
            
            $parentCheck->close();
        }

        $stmt = $conn->prepare("
            UPDATE financial_accounts
            SET account_code = ?, account_name = ?, account_type = ?, parent_id = ?, normal_balance = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        // Type string: s(account_code), s(account_name), s(account_type), i(parent_id), s(normal_balance), s(description), i(is_active), i(accountId)
        $stmt->bind_param('sssissii', $accountCode, $accountName, $accountType, $parentId, $normalBalance, $description, $isActive, $accountId);
        $stmt->execute();

        // Get updated account for history
        $fetchStmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $fetchStmt->bind_param('i', $accountId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedAccount = $result->fetch_assoc();
        $result->free();
        $fetchStmt->close();

        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldAccount && $updatedAccount) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('financial_accounts', $accountId, 'update', 'accounting', $oldAccount, $updatedAccount);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Account updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete account (soft delete by setting is_active = 0)
        $accountId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($accountId <= 0) {
            throw new Exception('Account ID is required');
        }

        // Get old data for history (before deletion)
        $fetchStmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $fetchStmt->bind_param('i', $accountId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldAccount = $result->fetch_assoc();
        $result->free();
        $fetchStmt->close();

        if (!$oldAccount) {
            throw new Exception('Account not found');
        }

        // Check if account is a system account
        if ($oldAccount['is_system_account']) {
            throw new Exception('Cannot delete system accounts');
        }

        $stmt = $conn->prepare("UPDATE financial_accounts SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $accountId);
        $stmt->execute();

        // Get updated account for history (after soft delete)
        $fetchStmt = $conn->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $fetchStmt->bind_param('i', $accountId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $deletedAccount = $result->fetch_assoc();
        $result->free();
        $fetchStmt->close();

        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldAccount && $deletedAccount) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('financial_accounts', $accountId, 'update', 'accounting', $oldAccount, $deletedAccount);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }

} catch (Exception $e) {
    $msg = $e->getMessage();
    error_log('Accounts API error: ' . $msg);
    // Use 400/409 for validation/client errors, 500 for server errors
    $validationErrors = ['Account code already exists', 'Invalid account_type', 'Invalid normal_balance', 'Invalid account configuration', 'Parent account not found', 'Cannot set parent', 'Account name is required', 'Account code is required'];
    $isValidation = false;
    foreach ($validationErrors as $ve) {
        if (stripos($msg, $ve) !== false || stripos($msg, 'already exists') !== false) {
            $isValidation = true;
            break;
        }
    }
    http_response_code($isValidation ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $msg,
        'error' => $msg
    ]);
}
?>
