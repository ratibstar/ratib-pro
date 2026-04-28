<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/diagnostic-accounts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/diagnostic-accounts.php`.
 */
/**
 * Diagnostic script to check accounting system status
 * Access: /api/accounting/diagnostic-accounts.php
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$diagnostics = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    // Check 1: Database connection
    $diagnostics['checks']['database_connection'] = [
        'status' => $conn ? 'ok' : 'failed',
        'message' => $conn ? 'Database connected' : 'Database connection failed'
    ];
    
    // Check 2: financial_accounts table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
    $diagnostics['checks']['financial_accounts_table'] = [
        'status' => ($tableCheck && $tableCheck->num_rows > 0) ? 'exists' : 'missing',
        'message' => ($tableCheck && $tableCheck->num_rows > 0) ? 'Table exists' : 'Table does not exist'
    ];
    
    // Check 3: Check columns
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $cols = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $columnNames = [];
        $hasEntityType = false;
        $hasEntityId = false;
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                $columnNames[] = $col['Field'];
                if ($col['Field'] === 'entity_type') $hasEntityType = true;
                if ($col['Field'] === 'entity_id') $hasEntityId = true;
            }
        }
        $diagnostics['checks']['financial_accounts_columns'] = [
            'status' => 'ok',
            'columns' => $columnNames,
            'has_entity_type' => $hasEntityType,
            'has_entity_id' => $hasEntityId
        ];
        
        // Check 4: Count total accounts
        $totalRes = $conn->query("SELECT COUNT(*) as cnt FROM financial_accounts");
        $totalCount = $totalRes ? (int)$totalRes->fetch_assoc()['cnt'] : 0;
        $activeRes = $conn->query("SELECT COUNT(*) as cnt FROM financial_accounts WHERE is_active = 1");
        $activeCount = $activeRes ? (int)$activeRes->fetch_assoc()['cnt'] : 0;
        
        $diagnostics['checks']['account_counts'] = [
            'total_accounts' => $totalCount,
            'active_accounts' => $activeCount,
            'inactive_accounts' => $totalCount - $activeCount
        ];
        
        // Check 5: Entity accounts
        if ($hasEntityType && $hasEntityId) {
            $entityRes = $conn->query("SELECT entity_type, COUNT(*) as cnt FROM financial_accounts WHERE entity_type IS NOT NULL GROUP BY entity_type");
            $entityCounts = [];
            if ($entityRes) {
                while ($row = $entityRes->fetch_assoc()) {
                    $entityCounts[$row['entity_type']] = (int)$row['cnt'];
                }
            }
            $diagnostics['checks']['entity_accounts'] = [
                'status' => 'ok',
                'counts' => $entityCounts
            ];
        }
    }
    
    // Check 6: Entity tables
    $entityTables = ['agents', 'subagents', 'workers', 'employees'];
    $entityTableStatus = [];
    foreach ($entityTables as $table) {
        $tblCheck = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = ($tblCheck && $tblCheck->num_rows > 0);
        $rowCount = 0;
        $nameColumn = null;
        
        if ($exists) {
            $countRes = $conn->query("SELECT COUNT(*) as cnt FROM $table");
            if ($countRes) {
                $rowCount = (int)$countRes->fetch_assoc()['cnt'];
            }
            
            // Find name column
            $nameCols = $table === 'workers' ? ['worker_name', 'full_name', 'name'] : 
                       ($table === 'employees' ? ['name', 'employee_name', 'full_name'] :
                       ($table === 'agents' ? ['agent_name', 'full_name', 'name'] : ['subagent_name', 'full_name', 'name']));
            
            foreach ($nameCols as $nc) {
                $colCheck = $conn->query("SHOW COLUMNS FROM $table LIKE '$nc'");
                if ($colCheck && $colCheck->num_rows > 0) {
                    $nameColumn = $nc;
                    break;
                }
            }
            
            // Count rows with non-empty names
            $nonEmptyCount = 0;
            if ($nameColumn) {
                $escCol = "`" . $conn->real_escape_string($nameColumn) . "`";
                $nameRes = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE COALESCE($escCol,'') != ''");
                if ($nameRes) {
                    $nonEmptyCount = (int)$nameRes->fetch_assoc()['cnt'];
                }
            }
        }
        
        $entityTableStatus[$table] = [
            'exists' => $exists,
            'row_count' => $rowCount,
            'name_column' => $nameColumn,
            'rows_with_names' => $nonEmptyCount
        ];
    }
    $diagnostics['checks']['entity_tables'] = $entityTableStatus;
    
    // Check 7: Test API endpoint
    $testUrl = '/api/accounting/accounts.php?is_active=1&ensure_entity_accounts=1';
    $diagnostics['checks']['api_endpoint'] = [
        'url' => $testUrl,
        'note' => 'Call this URL to test account creation'
    ];
    
} catch (Exception $e) {
    $diagnostics['success'] = false;
    $diagnostics['error'] = $e->getMessage();
    $diagnostics['checks']['exception'] = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
