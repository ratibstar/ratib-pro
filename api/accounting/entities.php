<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entities.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entities.php`.
 */
require_once '../../includes/config.php';

/**
 * Build sections (Agent, SubAgent, Worker, HR) with each entity's GL account info.
 * Used when format=sections and when included by accounts.php for ensure_entity_accounts.
 */
function buildEntityAccountSections($conn, $includeInactive) {
    $faCols = $conn->query("SHOW COLUMNS FROM financial_accounts");
    $hasEntityType = false;
    $hasEntityId = false;
    if ($faCols) {
        while ($c = $faCols->fetch_assoc()) {
            if ($c['Field'] === 'entity_type') $hasEntityType = true;
            if ($c['Field'] === 'entity_id') $hasEntityId = true;
        }
    }
    $sections = [];
    $typeConfig = [
        'agent' => ['table' => 'agents', 'nameCol' => 'agent_name', 'title' => 'Agent', 'statusWhere' => $includeInactive ? '' : " AND status = 'active' "],
        'subagent' => ['table' => 'subagents', 'nameCol' => 'subagent_name', 'title' => 'SubAgent', 'statusWhere' => $includeInactive ? '' : " AND status = 'active' "],
        'worker' => ['table' => 'workers', 'nameCol' => 'worker_name', 'title' => 'Worker', 'statusWhere' => $includeInactive ? '' : " AND (status = 'active' OR status = 'approved' OR status = 'deployed') "],
        'hr' => ['table' => 'employees', 'nameCol' => 'name', 'title' => 'HR', 'statusWhere' => $includeInactive ? '' : " AND (UPPER(TRIM(COALESCE(status,''))) = 'ACTIVE' OR status IS NULL) "],
    ];
    $nameColCandidates = ['worker' => ['worker_name', 'full_name', 'name'], 'hr' => ['name', 'employee_name', 'full_name']];
    foreach ($typeConfig as $et => $cfg) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($cfg['table']) . "'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $sections[] = ['title' => $cfg['title'], 'entity_type' => $et, 'entities' => []];
            continue;
        }
        $nc = $cfg['nameCol'];
        $colCheck = $conn->query("SHOW COLUMNS FROM {$cfg['table']} LIKE '" . $conn->real_escape_string($nc) . "'");
        if (!$colCheck || $colCheck->num_rows === 0) {
            foreach (['full_name', 'name', 'employee_name'] as $alt) {
                $ac = $conn->query("SHOW COLUMNS FROM {$cfg['table']} LIKE '" . $conn->real_escape_string($alt) . "'");
                if ($ac && $ac->num_rows > 0) { $nc = $alt; break; }
            }
        }
        $entityIds = [];
        $nameColsToTry = isset($nameColCandidates[$et]) ? $nameColCandidates[$et] : [$nc];
        foreach ($nameColsToTry as $tryCol) {
            $tcCheck = $conn->query("SHOW COLUMNS FROM {$cfg['table']} LIKE '" . $conn->real_escape_string($tryCol) . "'");
            if (!$tcCheck || $tcCheck->num_rows === 0) continue;
            $q = "SELECT id, TRIM(COALESCE(`$tryCol`,'')) as name, status FROM {$cfg['table']} WHERE 1=1 {$cfg['statusWhere']} AND (COALESCE(`$tryCol`,'') != '' AND LOWER(TRIM(`$tryCol`)) NOT LIKE 'test%') ORDER BY `$tryCol` ASC";
            $res = $conn->query($q);
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $name = trim($row['name'] ?? '');
                    if ($name === '') continue;
                    $entityIds[] = ['id' => (int)$row['id'], 'name' => $name, 'status' => $row['status'] ?? '', 'account_id' => null, 'account_code' => null, 'account_name' => null];
                }
                $res->free();
                break;
            }
            if ($res) $res->free();
        }
        if ($hasEntityType && $hasEntityId && !empty($entityIds)) {
            $ids = array_column($entityIds, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT id, account_code, account_name, entity_id FROM financial_accounts WHERE entity_type = ? AND entity_id IN ($placeholders) AND is_active = 1");
            $types = 's' . str_repeat('i', count($ids));
            $params = array_merge([$et], $ids);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $accRes = $stmt->get_result();
            $accMap = [];
            while ($ar = $accRes->fetch_assoc()) {
                $accMap[(int)$ar['entity_id']] = ['account_id' => (int)$ar['id'], 'account_code' => $ar['account_code'], 'account_name' => $ar['account_name']];
            }
            $stmt->close();
            foreach ($entityIds as &$e) {
                if (isset($accMap[$e['id']])) {
                    $e['account_id'] = $accMap[$e['id']]['account_id'];
                    $e['account_code'] = $accMap[$e['id']]['account_code'];
                    $e['account_name'] = $accMap[$e['id']]['account_name'];
                }
            }
        }
        $sections[] = ['title' => $cfg['title'], 'entity_type' => $et, 'entities' => $entityIds];
    }
    // Accounting section: users with role "Accounting" (entity_id = user_id)
    $accountingEntities = [];
    $rolesCheck = $conn->query("SHOW TABLES LIKE 'roles'");
    $usersCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if ($rolesCheck && $rolesCheck->num_rows > 0 && $usersCheck && $usersCheck->num_rows > 0) {
        $accRes = $conn->query("SELECT u.user_id AS id, TRIM(COALESCE(u.username,'')) AS name FROM users u INNER JOIN roles r ON u.role_id = r.role_id WHERE LOWER(TRIM(r.role_name)) = 'accounting' AND COALESCE(u.username,'') != '' ORDER BY u.username ASC");
        if ($accRes && $accRes->num_rows > 0) {
            while ($row = $accRes->fetch_assoc()) {
                $name = trim($row['name'] ?? '');
                if ($name === '') continue;
                $accountingEntities[] = ['id' => (int)$row['id'], 'name' => $name, 'status' => '', 'account_id' => null, 'account_code' => null, 'account_name' => null];
            }
            $accRes->free();
            if ($hasEntityType && $hasEntityId && !empty($accountingEntities)) {
                $ids = array_column($accountingEntities, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("SELECT id, account_code, account_name, entity_id FROM financial_accounts WHERE entity_type = 'accounting' AND entity_id IN ($placeholders) AND is_active = 1");
                $types = str_repeat('i', count($ids));
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $accMapRes = $stmt->get_result();
                $accMap = [];
                while ($ar = $accMapRes->fetch_assoc()) {
                    $accMap[(int)$ar['entity_id']] = ['account_id' => (int)$ar['id'], 'account_code' => $ar['account_code'], 'account_name' => $ar['account_name']];
                }
                $stmt->close();
                foreach ($accountingEntities as &$e) {
                    if (isset($accMap[$e['id']])) {
                        $e['account_id'] = $accMap[$e['id']]['account_id'];
                        $e['account_code'] = $accMap[$e['id']]['account_code'];
                        $e['account_name'] = $accMap[$e['id']]['account_name'];
                    }
                }
            }
        }
    }
    $sections[] = ['title' => 'Accounting', 'entity_type' => 'accounting', 'entities' => $accountingEntities];
    return ['sections' => $sections, 'has_entity_columns' => $hasEntityType && $hasEntityId];
}

// When included by accounts.php (ENTITY_SECTIONS_ONLY), only define the function; do not run API.
if (defined('ENTITY_SECTIONS_ONLY')) {
    return;
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $format = isset($_GET['format']) ? trim($_GET['format']) : '';
    if ($format === 'sections') {
        $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1';
        $out = buildEntityAccountSections($conn, $includeInactive);
        echo json_encode(['success' => true, 'sections' => $out['sections'], 'has_entity_columns' => $out['has_entity_columns']]);
        exit;
    }

    $entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
    $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == '1';
    $onlyWithTransactions = isset($_GET['only_with_transactions']) && $_GET['only_with_transactions'] == '1';
    $agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
    $subagentId = isset($_GET['subagent_id']) ? intval($_GET['subagent_id']) : null;

    $entities = [];
    $entityKeys = []; // Track unique entities to prevent duplicates
    $seenKeys = []; // Use array for faster lookup
    $seenIds = []; // Track all IDs globally to prevent any duplicates
    
    // If only_with_transactions is set, get list of entity_type:entity_id pairs that have transactions
    $entitiesWithTransactions = [];
    if ($onlyWithTransactions) {
        $transCheckQuery = "SELECT DISTINCT LOWER(entity_type) as entity_type, entity_id FROM entity_transactions";
        $transResult = $conn->query($transCheckQuery);
        if ($transResult) {
            while ($row = $transResult->fetch_assoc()) {
                $key = strtolower($row['entity_type']) . ':' . intval($row['entity_id']);
                $entitiesWithTransactions[$key] = true;
            }
        }
    }

    // Get Agents
    if (!$entityType || $entityType === 'agent') {
        // Try to detect which column name exists (safely)
        $nameColumn = 'agent_name'; // Default
        $columnsCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'agent_name'");
        if ($columnsCheck->num_rows === 0) {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'full_name'");
            if ($columnsCheck->num_rows > 0) {
                $nameColumn = 'full_name';
            } else {
                $nameColumn = 'name';
            }
        }
        
        // Use backticks for column names to handle reserved words
        $nameColumnEscaped = "`{$nameColumn}`";
        
        // Check which contact column exists
        $contactColumn = 'contact_number';
        $contactCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'contact_number'");
        if ($contactCheck->num_rows === 0) {
            $contactCheck = $conn->query("SHOW COLUMNS FROM agents LIKE 'phone'");
            if ($contactCheck->num_rows > 0) {
                $contactColumn = 'phone';
            } else {
                $contactColumn = NULL;
            }
        }
        
        $contactSelect = $contactColumn ? ", `{$contactColumn}`" : "";
        $query = "SELECT id, {$nameColumnEscaped} as name, email{$contactSelect}, status, 'agent' as entity_type FROM agents";
        $whereConditions = [];
        if (!$includeInactive) {
            $whereConditions[] = "status = 'active'";
        }
        // Filter out empty or test entries
        $whereConditions[] = "({$nameColumnEscaped} IS NOT NULL AND {$nameColumnEscaped} != '' AND {$nameColumnEscaped} NOT LIKE 'test%' AND {$nameColumnEscaped} != 'Test')";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $query .= " GROUP BY id ORDER BY {$nameColumnEscaped} ASC";
        
        $result = $conn->query($query);
        if (!$result) {
            error_log("Agent query error: " . $conn->error);
            // Fallback to simple query - try without phone column
            $query = "SELECT id, agent_name as name, email, contact_number, status, 'agent' as entity_type FROM agents WHERE status = 'active' GROUP BY id ORDER BY agent_name ASC";
            $result = $conn->query($query);
            // Reset contact column for fallback
            if ($result) {
                $contactColumn = 'contact_number';
            }
        }
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $entityId = intval($row['id']);
                $entityKey = 'agent:' . $entityId;
                
                // Skip if only_with_transactions is set and this entity doesn't have transactions
                if ($onlyWithTransactions && !isset($entitiesWithTransactions[$entityKey])) {
                    continue;
                }
                
                // Check both entity key and global ID to prevent duplicates
                if (!isset($seenKeys[$entityKey]) && !isset($seenIds[$entityId]) && !empty($row['name'])) {
                    $seenKeys[$entityKey] = true;
                    $seenIds[$entityId] = true;
                    $entityKeys[] = $entityKey;
                    $contactValue = '';
                    if (isset($contactColumn) && $contactColumn) {
                        $contactValue = $row[$contactColumn] ?? '';
                    } else {
                        // Try both columns as fallback
                        $contactValue = $row['contact_number'] ?? $row['phone'] ?? '';
                    }
                    
                    $entities[] = [
                        'id' => intval($row['id']),
                        'name' => trim($row['name']),
                        'email' => $row['email'] ?? '',
                        'contact_number' => $contactValue,
                        'status' => $row['status'],
                        'entity_type' => 'agent',
                        'display_name' => trim($row['name']) . ' (Agent #' . $row['id'] . ')'
                    ];
                }
            }
        }
    }

    // Get Subagents
    if (!$entityType || $entityType === 'subagent') {
        // Try to detect which column name exists (safely)
        $nameColumn = 'subagent_name'; // Default
        $columnsCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'subagent_name'");
        if ($columnsCheck->num_rows === 0) {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'full_name'");
            if ($columnsCheck->num_rows > 0) {
                $nameColumn = 'full_name';
            } else {
                $nameColumn = 'name';
            }
        }
        
        // Use backticks for column names
        $nameColumnEscaped = "`{$nameColumn}`";
        
        // Check which contact column exists
        $contactColumn = 'contact_number';
        $contactCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'contact_number'");
        if ($contactCheck->num_rows === 0) {
            $contactCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'phone'");
            if ($contactCheck->num_rows > 0) {
                $contactColumn = 'phone';
            } else {
                $contactColumn = NULL;
            }
        }
        
        $contactSelect = $contactColumn ? ", `{$contactColumn}`" : "";
        $query = "SELECT id, {$nameColumnEscaped} as name, email{$contactSelect}, status, 'subagent' as entity_type";
        // Check if agent_id column exists
        $agentIdCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'agent_id'");
        if ($agentIdCheck && $agentIdCheck->num_rows > 0) {
            $query .= ", agent_id";
        }
        $query .= " FROM subagents";
        $whereConditions = [];
        if (!$includeInactive) {
            $whereConditions[] = "status = 'active'";
        }
        // Filter out empty or test entries
        $whereConditions[] = "({$nameColumnEscaped} IS NOT NULL AND {$nameColumnEscaped} != '' AND {$nameColumnEscaped} NOT LIKE 'test%' AND {$nameColumnEscaped} != 'Test')";
        // Filter by agent_id if provided
        if ($agentId !== null && $agentId > 0) {
            $agentIdCheck = $conn->query("SHOW COLUMNS FROM subagents LIKE 'agent_id'");
            if ($agentIdCheck && $agentIdCheck->num_rows > 0) {
                $whereConditions[] = "agent_id = " . intval($agentId);
            }
        }
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $query .= " GROUP BY id ORDER BY {$nameColumnEscaped} ASC";
        
        $result = $conn->query($query);
        if (!$result) {
            error_log("Subagent query error: " . $conn->error);
            // Fallback to simple query - try without phone column
            $query = "SELECT id, subagent_name as name, email, contact_number, status, 'subagent' as entity_type FROM subagents WHERE status = 'active' GROUP BY id ORDER BY subagent_name ASC";
            $result = $conn->query($query);
            // Reset contact column for fallback
            if ($result) {
                $contactColumn = 'contact_number';
            }
        }
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $entityId = intval($row['id']);
                $entityKey = 'subagent:' . $entityId;
                
                // Skip if only_with_transactions is set and this entity doesn't have transactions
                if ($onlyWithTransactions && !isset($entitiesWithTransactions[$entityKey])) {
                    continue;
                }
                
                // Only add if not already seen - stricter check
                if (!isset($seenKeys[$entityKey]) && !isset($seenIds[$entityId]) && !empty($row['name'])) {
                    $seenKeys[$entityKey] = true;
                    $seenIds[$entityId] = true; // Track globally by ID
                    $entityKeys[] = $entityKey;
                    $contactValue = '';
                    if (isset($contactColumn) && $contactColumn) {
                        $contactValue = $row[$contactColumn] ?? '';
                    } else {
                        // Try both columns as fallback
                        $contactValue = $row['contact_number'] ?? $row['phone'] ?? '';
                    }
                    
                    $entities[] = [
                        'id' => intval($row['id']),
                        'name' => trim($row['name']),
                        'email' => $row['email'] ?? '',
                        'contact_number' => $contactValue,
                        'status' => $row['status'],
                        'entity_type' => 'subagent',
                        'display_name' => trim($row['name']) . ' (Subagent #' . $row['id'] . ')'
                    ];
                }
            }
        }
    }

    // Get Workers
    if (!$entityType || $entityType === 'worker') {
        // Try to detect which column name exists (safely)
        $nameColumn = 'worker_name'; // Default
        $columnsCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'worker_name'");
        if ($columnsCheck->num_rows === 0) {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'full_name'");
            if ($columnsCheck->num_rows > 0) {
                $nameColumn = 'full_name';
            } else {
                $nameColumn = 'name';
            }
        }
        
        // Use backticks for column names
        $nameColumnEscaped = "`{$nameColumn}`";
        
        // Check which contact column exists
        $contactColumn = 'contact_number';
        $contactCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'contact_number'");
        if ($contactCheck->num_rows === 0) {
            $contactCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'phone'");
            if ($contactCheck->num_rows > 0) {
                $contactColumn = 'phone';
            } else {
                $contactColumn = NULL;
            }
        }
        
        $contactSelect = $contactColumn ? ", `{$contactColumn}`" : "";
        $query = "SELECT id, {$nameColumnEscaped} as name, email{$contactSelect}, status, 'worker' as entity_type";
        // Check if subagent_id column exists
        $subagentIdCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'subagent_id'");
        if ($subagentIdCheck && $subagentIdCheck->num_rows > 0) {
            $query .= ", subagent_id";
        }
        $query .= " FROM workers";
        $whereConditions = [];
        if (!$includeInactive) {
            // Workers can have different status values - include common active ones
            $whereConditions[] = "(status = 'active' OR status = 'approved' OR status = 'deployed')";
        }
        // Filter out empty or test entries
        $whereConditions[] = "({$nameColumnEscaped} IS NOT NULL AND {$nameColumnEscaped} != '' AND {$nameColumnEscaped} NOT LIKE 'test%' AND {$nameColumnEscaped} != 'Test')";
        // Filter by subagent_id if provided
        if ($subagentId !== null && $subagentId > 0) {
            $subagentIdCheck = $conn->query("SHOW COLUMNS FROM workers LIKE 'subagent_id'");
            if ($subagentIdCheck && $subagentIdCheck->num_rows > 0) {
                $whereConditions[] = "subagent_id = " . intval($subagentId);
            }
        }
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(' AND ', $whereConditions);
        }
        $query .= " GROUP BY id ORDER BY {$nameColumnEscaped} ASC";
        
        $result = $conn->query($query);
        if (!$result) {
            error_log("Worker query error: " . $conn->error);
            // Fallback to simple query - try without phone column
            $query = "SELECT id, worker_name as name, email, contact_number, status, 'worker' as entity_type FROM workers WHERE (status = 'active' OR status = 'approved' OR status = 'deployed') GROUP BY id ORDER BY worker_name ASC";
            $result = $conn->query($query);
            // Reset contact column for fallback
            if ($result) {
                $contactColumn = 'contact_number';
            }
        }
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $entityId = intval($row['id']);
                $entityKey = 'worker:' . $entityId;
                
                // Skip if only_with_transactions is set and this entity doesn't have transactions
                if ($onlyWithTransactions && !isset($entitiesWithTransactions[$entityKey])) {
                    continue;
                }
                
                // Check both entity key and global ID to prevent duplicates
                if (!isset($seenKeys[$entityKey]) && !isset($seenIds[$entityId]) && !empty($row['name'])) {
                    $seenKeys[$entityKey] = true;
                    $seenIds[$entityId] = true;
                    $entityKeys[] = $entityKey;
                    $contactValue = '';
                    if (isset($contactColumn) && $contactColumn) {
                        $contactValue = $row[$contactColumn] ?? '';
                    } else {
                        // Try both columns as fallback
                        $contactValue = $row['contact_number'] ?? $row['phone'] ?? '';
                    }
                    
                    $entities[] = [
                        'id' => intval($row['id']),
                        'name' => trim($row['name']),
                        'email' => $row['email'] ?? '',
                        'contact_number' => $contactValue,
                        'status' => $row['status'],
                        'entity_type' => 'worker',
                        'display_name' => trim($row['name']) . ' (Worker #' . $row['id'] . ')'
                    ];
                }
            }
        }
    }

    // Get HR Employees
    if (!$entityType || $entityType === 'hr') {
        // Check if employees table exists (HR uses 'employees' table)
        $tableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
        
        if ($tableCheck && $tableCheck->num_rows > 0) {
            // Use employees table with correct column names
            $query = "SELECT id, name, email, status, 'hr' as entity_type FROM employees";
            $whereConditions = [];
            if (!$includeInactive) {
                $whereConditions[] = "status = 'Active'";
            }
            // Filter out empty or test entries
            $whereConditions[] = "(name IS NOT NULL AND name != '' AND name NOT LIKE 'test%' AND name != 'Test')";
            
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(' AND ', $whereConditions);
            }
            $query .= " GROUP BY id ORDER BY name ASC";
            
            $result = $conn->query($query);
            if (!$result) {
                error_log("HR query error: " . $conn->error);
                // Fallback to simple query
                $query = "SELECT id, name, email, status, 'hr' as entity_type FROM employees WHERE status = 'Active' GROUP BY id ORDER BY name ASC";
                $result = $conn->query($query);
            }
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $entityId = intval($row['id']);
                    $entityKey = 'hr:' . $entityId;
                    
                    // Skip if only_with_transactions is set and this entity doesn't have transactions
                    if ($onlyWithTransactions && !isset($entitiesWithTransactions[$entityKey])) {
                        continue;
                    }
                    
                    // Check both entity key and global ID to prevent duplicates
                    if (!isset($seenKeys[$entityKey]) && !isset($seenIds[$entityId]) && !empty($row['name'])) {
                        $seenKeys[$entityKey] = true;
                        $seenIds[$entityId] = true;
                        $entityKeys[] = $entityKey;
                        $entities[] = [
                            'id' => intval($row['id']),
                            'name' => trim($row['name']),
                            'email' => $row['email'] ?? '',
                            'contact_number' => '',
                            'status' => $row['status'],
                            'entity_type' => 'hr',
                            'display_name' => trim($row['name']) . ' (HR #' . $row['id'] . ')'
                        ];
                    }
                }
            }
        }
    }

    // Final deduplication before returning (extra safety using associative array)
    // Only deduplicate by entity_type:ID combination - allow same names with different IDs
    $finalEntities = [];
    $finalKeys = [];
    
    foreach ($entities as $entity) {
        if (!isset($entity['id']) || !isset($entity['entity_type'])) {
            continue; // Skip invalid entities
        }
        
        $entityId = intval($entity['id']);
        $key = strtolower($entity['entity_type']) . ':' . $entityId;
        
        // Only skip if we've already seen this exact entity_type:ID combination
        if (!isset($finalKeys[$key])) {
            $finalKeys[$key] = true;
            $finalEntities[] = $entity;
        }
    }
    
    echo json_encode([
        'success' => true,
        'entities' => array_values($finalEntities), // Re-index array
        'count' => count($finalEntities)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Entities API Error: " . $e->getMessage() . " | Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching entities: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'entities' => []
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log("Entities API Fatal Error: " . $e->getMessage() . " | Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error fetching entities: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'entities' => []
    ]);
}
?>
