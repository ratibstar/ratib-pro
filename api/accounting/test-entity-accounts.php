<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/test-entity-accounts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/test-entity-accounts.php`.
 */
/**
 * Test endpoint to verify entity accounts are being created
 * Access: /api/accounting/test-entity-accounts.php
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$results = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'checks' => []
];

try {
    // Check entity tables and their accounts
    $entityConfig = [
        'agent' => ['table' => 'agents', 'nameCol' => 'agent_name'],
        'subagent' => ['table' => 'subagents', 'nameCol' => 'subagent_name'],
        'worker' => ['table' => 'workers', 'nameCol' => 'worker_name'],
        'hr' => ['table' => 'employees', 'nameCol' => 'name']
    ];
    
    foreach ($entityConfig as $et => $cfg) {
        $t = $conn->real_escape_string($cfg['table']);
        $tblCheck = $conn->query("SHOW TABLES LIKE '$t'");
        $exists = ($tblCheck && $tblCheck->num_rows > 0);
        
        $entityCount = 0;
        $accountCount = 0;
        $missingAccounts = [];
        
        if ($exists) {
            // Count entities
            $cntRes = $conn->query("SELECT COUNT(*) as cnt FROM $t");
            if ($cntRes) {
                $entityCount = (int)$cntRes->fetch_assoc()['cnt'];
            }
            
            // Count accounts for this entity type
            $accRes = $conn->query("SELECT COUNT(*) as cnt FROM financial_accounts WHERE entity_type = '$et' AND is_active = 1");
            if ($accRes) {
                $accountCount = (int)$accRes->fetch_assoc()['cnt'];
            }
            
            // Find entities without accounts
            $nameCol = $cfg['nameCol'];
            $colCheck = $conn->query("SHOW COLUMNS FROM $t LIKE '$nameCol'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $escCol = '`' . $conn->real_escape_string($nameCol) . '`';
                $entitiesRes = $conn->query("SELECT id, TRIM(COALESCE($escCol,'')) as name FROM $t WHERE COALESCE($escCol,'') != ''");
                if ($entitiesRes) {
                    while ($er = $entitiesRes->fetch_assoc()) {
                        $eid = (int)$er['id'];
                        $chk = $conn->prepare("SELECT 1 FROM financial_accounts WHERE entity_type = ? AND entity_id = ? LIMIT 1");
                        if ($chk) {
                            $chk->bind_param('si', $et, $eid);
                            $chk->execute();
                            if ($chk->get_result()->num_rows === 0) {
                                $missingAccounts[] = ['id' => $eid, 'name' => trim($er['name'] ?? '')];
                            }
                            $chk->close();
                        }
                    }
                }
            }
        }
        
        $results['checks'][$et] = [
            'table_exists' => $exists,
            'entities_in_table' => $entityCount,
            'accounts_in_chart' => $accountCount,
            'missing_accounts' => $missingAccounts,
            'all_have_accounts' => ($entityCount > 0 && $accountCount === $entityCount && empty($missingAccounts))
        ];
    }
    
    // Get all entity accounts from financial_accounts
    $allEntityAccounts = [];
    $accRes = $conn->query("SELECT id, account_code, account_name, entity_type, entity_id FROM financial_accounts WHERE entity_type IS NOT NULL AND is_active = 1 ORDER BY entity_type, account_code");
    if ($accRes) {
        while ($ar = $accRes->fetch_assoc()) {
            $allEntityAccounts[] = [
                'id' => (int)$ar['id'],
                'account_code' => $ar['account_code'],
                'account_name' => $ar['account_name'],
                'entity_type' => $ar['entity_type'],
                'entity_id' => (int)$ar['entity_id']
            ];
        }
    }
    $results['all_entity_accounts'] = $allEntityAccounts;
    $results['total_entity_accounts'] = count($allEntityAccounts);
    
} catch (Exception $e) {
    $results['success'] = false;
    $results['error'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT);
