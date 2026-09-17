<?php
declare(strict_types=1);

/**
 * Phase 21 — RATEB Agent Memory & Operational Context.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase21-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpControlTowerLayer;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpExecutiveIntelligenceLayer;
use Rateb\App\Services\ErpIntelligenceLayer;
use Rateb\App\Services\ErpOperationalLearningLayer;
use Rateb\App\Services\ErpOperationalMemoryLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpProactiveEarlyWarningLayer;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\ExecutiveToolExecutor;
use Rateb\App\Services\ExecutiveToolRegistry;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    $companyId2 = (int) $pdo->query(
        'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
    )->fetchColumn();
    $userId = (int) $pdo->query(
        "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
    if ($userId < 1) {
        $userId = (int) $pdo->query('SELECT id FROM rateb_users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }
    if ($companyId < 1 || $userId < 1) {
        throw new RuntimeException('no_company_or_user');
    }
} catch (Throwable $e) {
    echo "FAIL: db_bootstrap — {$e->getMessage()}\n";
    exit(1);
}

TenantContext::setCompanyId($companyId);
TenantContext::setSuperAdmin(true);
$_SESSION['rateb_user_id'] = $userId;
$_SESSION['rateb_is_super_admin'] = 1;
$_SESSION['rateb_company_id'] = $companyId;
Auth::bootstrapFromSession();

$ref = new ReflectionClass(ProcurementAgentContext::class);
$ctor = $ref->getConstructor();
$ctor->setAccessible(true);
$makeCtx = static function (array $perms, bool $sa, int $cid, array $modules, string $locale = 'en') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase21-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');

$noBad = !class_exists('Rateb\\App\\Services\\MemoryAgent')
    && !class_exists('Rateb\\App\\Services\\VectorMemoryService')
    && !class_exists('Rateb\\App\\Services\\RagPlatform')
    && !class_exists('Rateb\\App\\Services\\MlMemoryPlatform');

(class_exists(ErpOperationalMemoryLayer::class)
    && $noBad
    && ExecutiveToolRegistry::isAllowed('get_relevant_operational_context')
    && ExecutiveToolRegistry::isAllowed('get_operational_memory')
    && ErpToolRegistry::domainForTool('get_relevant_operational_context') === 'executive'
    && (ErpDomainRegistry::resolve('executive')['runtime'] ?? '') === ProcurementAgent::class
    && str_contains((string) file_get_contents(RATEB_ROOT . '/views/company/ai/control-tower.php'), 'data-ct-panel="memory"'))
    ? $pass('MEMORY DISCOVERY') : $fail('MEMORY DISCOVERY');

ErpOperationalMemoryLayer::clearMemo();
$rel = ErpOperationalMemoryLayer::buildRelevantContext($ctx, [
    'intent_kind' => 'executive',
    'proactive' => true,
    'domains' => ['procurement', 'inventory'],
], [], 12);
$items = is_array($rel['items'] ?? null) ? $rel['items'] : [];
$itemCount = count($items);
$maxOk = $itemCount <= 12;
$hasBand = true;
foreach ($items as $it) {
    if (!is_array($it)) {
        continue;
    }
    $band = (string) ($it['freshness_band'] ?? '');
    if (!in_array($band, ['CURRENT', 'RECENT', 'HISTORICAL'], true)) {
        $hasBand = false;
    }
    if (empty($it['evidence']) || empty($it['evidence']['source'])) {
        $hasBand = false;
    }
}
($maxOk && !empty($rel['current_priority']) && $hasBand && ($rel['data_source'] ?? '') === 'live_tenant')
    ? $pass('MEMORY RELEVANCE') : $fail('MEMORY RELEVANCE', 'count=' . $itemCount);

// Current context priority + historical labeling
$priorityOk = !empty($rel['hierarchy'][0]) && $rel['hierarchy'][0] === 'current_request'
    && !empty($rel['stale_protection']['historical_not_current_fact']);
$histLabeled = true;
foreach ($items as $it) {
    if (!is_array($it)) {
        continue;
    }
    if (($it['freshness_band'] ?? '') === 'HISTORICAL' && empty($it['is_historical'])) {
        $histLabeled = false;
    }
    if (($it['freshness_band'] ?? '') === 'HISTORICAL' && empty($it['stale_for_writes'])) {
        $histLabeled = false;
    }
}
$priorityOk && $histLabeled ? $pass('CURRENT CONTEXT PRIORITY') : $fail('CURRENT CONTEXT PRIORITY');
$histLabeled ? $pass('HISTORICAL CONTEXT') : $fail('HISTORICAL CONTEXT');

// Stale memory protection
$boundary = ErpOperationalMemoryLayer::assertLlmBoundary();
$staleOk = !empty($rel['stale_protection']['writes_require_live_state_validation'])
    && !empty($rel['stale_protection']['phase14_stale_state_protection'])
    && empty($boundary['llm_writes_memory'])
    && empty($boundary['vector_db']);
$staleOk ? $pass('STALE MEMORY PROTECTION') : $fail('STALE MEMORY PROTECTION');

// Conflict handling — inject resolved warning hint vs historical open warning
$conflictPack = ErpOperationalMemoryLayer::buildRelevantContext($ctx, ['intent_kind' => 'executive'], [
    'resolved_warning_ids' => ['synthetic_resolved_for_test'],
], 12);
$conflictHandlingOk = isset($conflictPack['conflicts']) && is_array($conflictPack['conflicts'])
    && !empty($conflictPack['current_priority']);
// Ensure no usable_as_current_fact on conflicting historical items
foreach (($conflictPack['items'] ?? []) as $it) {
    if (!is_array($it)) {
        continue;
    }
    if (!empty($it['conflict_with_current']) && !empty($it['usable_as_current_fact'])) {
        $conflictHandlingOk = false;
    }
}
$conflictHandlingOk ? $pass('CONFLICT HANDLING') : $fail('CONFLICT HANDLING');

// Evidence traceability
$traceOk = true;
foreach ($items as $it) {
    if (!is_array($it)) {
        continue;
    }
    $ev = is_array($it['evidence'] ?? null) ? $it['evidence'] : [];
    if (empty($ev['source']) || empty($it['domain']) || empty($it['timestamp'])) {
        $traceOk = false;
        break;
    }
}
$traceOk ? $pass('EVIDENCE TRACEABILITY') : $fail('EVIDENCE TRACEABILITY');

// Tenant isolation
$tenantOk = true;
if ($companyId2 > 0) {
    $ctx2 = $makeCtx($fullPerms, true, $companyId2, $modules, 'en');
    ErpOperationalMemoryLayer::clearMemo();
    $mem1 = ErpOperationalMemoryLayer::buildRelevantContext($ctx, ['intent_kind' => 'executive'], [], 10);
    ErpOperationalMemoryLayer::clearMemo();
    $mem2 = ErpOperationalMemoryLayer::buildRelevantContext($ctx2, ['intent_kind' => 'executive'], [], 10);
    if ((int) ($mem1['company_id'] ?? 0) !== $companyId || (int) ($mem2['company_id'] ?? 0) !== $companyId2) {
        $tenantOk = false;
    }
    foreach (($mem1['items'] ?? []) as $it) {
        if (is_array($it) && isset($it['evidence']['company_id']) && (int) $it['evidence']['company_id'] === $companyId2) {
            $tenantOk = false;
        }
    }
} else {
    // single-tenant env: still verify company_id stamped
    $tenantOk = (int) ($rel['company_id'] ?? 0) === $companyId;
}
$tenantOk ? $pass('TENANT MEMORY ISOLATION') : $fail('TENANT MEMORY ISOLATION');

// Authorization — limited user without dashboard/ai permissions denied
$limited = $makeCtx(['pos.view'], false, $companyId, $modules, 'en');
$authCheck = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_relevant_operational_context',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p21-perm',
    'write_confirmed' => false,
], $limited);
$authFull = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_relevant_operational_context',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p21-ok',
    'write_confirmed' => false,
], $ctx);
$deniedLimited = empty($authCheck['allowed']);
$allowedFull = !empty($authFull['allowed']);
($deniedLimited && $allowedFull) ? $pass('AUTHORIZATION') : $fail('AUTHORIZATION', json_encode([
    'limited' => $authCheck['error_code'] ?? $authCheck,
    'full' => $authFull['error_code'] ?? null,
]));

// Intelligence integration
$intel = ErpIntelligenceLayer::enrich(
    ['metrics' => [], 'risks' => [], 'notes' => [], 'priorities' => []],
    [['tool' => 'analyze_inventory', 'domain' => 'inventory', 'purpose' => 'inventory_intelligence', 'data' => [
        'data_source' => 'live_tenant',
        'low_stock_count' => 1,
    ]]],
    [],
    ['intent_kind' => 'analysis', 'domains' => ['inventory']],
    $ctx
);
(!empty($intel['operational_memory']) && ($intel['operational_memory']['formula'] ?? '') === 'CURRENT_DATA + RELEVANT_OPERATIONAL_MEMORY')
    ? $pass('INTELLIGENCE INTEGRATION') : $fail('INTELLIGENCE INTEGRATION');

// Executive integration
ErpExecutiveIntelligenceLayer::clearMemo();
$exec = ErpExecutiveIntelligenceLayer::build($ctx, ['intent_kind' => 'executive'], 10);
(!empty($exec['operational_memory']) && ($exec['data_source'] ?? '') === 'live_tenant')
    ? $pass('EXECUTIVE INTEGRATION') : $fail('EXECUTIVE INTEGRATION');

// Early warning integration — memory annotation helper exists; scan still requires current evidence
$ewSrc = (string) file_get_contents(RATEB_ROOT . '/app/services/ErpProactiveEarlyWarningLayer.php');
(str_contains($ewSrc, 'annotateRecurringFromMemory')
    && str_contains($ewSrc, 'recurring_pattern_with_current_evidence')
    && str_contains($ewSrc, 'Never creates a warning from memory alone'))
    ? $pass('EARLY WARNING INTEGRATION') : $fail('EARLY WARNING INTEGRATION');

// Learning integration — catalog uses learning outcomes/signals/patterns
$memSrc = (string) file_get_contents(RATEB_ROOT . '/app/services/ErpOperationalMemoryLayer.php');
(str_contains($memSrc, 'ErpOperationalLearningLayer::analyze')
    && str_contains($memSrc, 'learning_signal')
    && str_contains($memSrc, 'OUTCOME'))
    ? $pass('LEARNING INTEGRATION') : $fail('LEARNING INTEGRATION');

// Control Tower
ErpControlTowerLayer::clearMemo();
$snap = ErpControlTowerLayer::snapshot($ctx, 10);
$towerMem = is_array($snap['operational_memory'] ?? null) ? $snap['operational_memory'] : [];
(($towerMem['section'] ?? '') === 'operational_context_memory' && empty($towerMem['llm_can_write_memory']))
    ? $pass('CONTROL TOWER INTEGRATION') : $fail('CONTROL TOWER INTEGRATION');

// LLM boundary
$llmToolWrite = ExecutiveToolRegistry::getTool('get_relevant_operational_context');
$llmOk = empty($boundary['llm_writes_memory'])
    && empty($llmToolWrite['write'])
    && empty($rel['llm_boundary']['llm_can_write_memory'])
    && ErpActionPlanner::classifyTool('get_relevant_operational_context') === ErpActionPlanner::CLASS_ANALYSIS;
$llmOk ? $pass('LLM BOUNDARY') : $fail('LLM BOUNDARY');

// Performance — duration and item caps
$t0 = microtime(true);
ErpOperationalMemoryLayer::clearMemo();
$perf = ErpOperationalMemoryLayer::buildRelevantContext($ctx, ['intent_kind' => 'executive'], [], 12);
$ms = (int) round((microtime(true) - $t0) * 1000);
$perfOk = count($perf['items'] ?? []) <= 12
    && (($perf['observability']['duration_ms'] ?? $ms) < 15000)
    && $ms < 20000;
$perfOk ? $pass('PERFORMANCE') : $fail('PERFORMANCE', 'ms=' . $ms);

// AR / EN
$en = require RATEB_ROOT . '/config/lang/en.php';
$ar = require RATEB_ROOT . '/config/lang/ar.php';
$keys = [
    'ai_ct_tab_memory', 'ai_ct_mem_note', 'ai_ct_mem_decisions', 'ai_ct_mem_actions',
    'ai_ct_mem_outcomes', 'ai_ct_mem_patterns', 'ai_ct_mem_unresolved', 'ai_ct_mem_warnings',
    'ai_ct_mem_learning', 'ai_ct_mem_historical',
    'ai_tool_get_relevant_operational_context', 'ai_tool_get_operational_memory',
];
$arOk = true;
$enOk = true;
foreach ($keys as $k) {
    if (empty($en[$k])) {
        $enOk = false;
    }
    if (empty($ar[$k])) {
        $arOk = false;
    }
}
$enOk ? $pass('EN') : $fail('EN');
$arOk ? $pass('AR') : $fail('AR');

// Dark/Light — CSS uses variables (theme-agnostic)
$css = (string) file_get_contents(RATEB_ROOT . '/public/assets/css/rateb-ai-control-tower.css');
(str_contains($css, '--ct-') && str_contains($css, '.rateb-ct-subtitle'))
    ? $pass('DARK/LIGHT') : $fail('DARK/LIGHT');

// Orchestration memory intent
$memIntent = ErpOrchestrationPlanner::detectIntent('operational memory previous decisions recurring patterns', $ctx);
(!empty($memIntent['memory']) && !empty($memIntent['executive']))
    ? $pass('MEMORY INTENT') : $fail('MEMORY INTENT', json_encode($memIntent));

// Tool executor smoke
$execTool = ExecutiveToolExecutor::execute('get_relevant_operational_context', ['limit' => 8], $ctx);
(!empty($execTool['success']) && ($execTool['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('MEMORY TOOL EXECUTE') : $fail('MEMORY TOOL EXECUTE');

// Domain regressions (active packs still wired)
ErpDomainRegistry::isActive('crm') ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
ErpDomainRegistry::isActive('sales') ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
ErpDomainRegistry::isActive('inventory') ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
ErpDomainRegistry::isActive('procurement') ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
ErpDomainRegistry::isActive('suppliers') ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
ErpDomainRegistry::isActive('logistics') ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');
ErpDomainRegistry::isActive('accounting') ? $pass('ACCOUNTING REGRESSION') : $fail('ACCOUNTING REGRESSION');
isset($intel['evidence_first']) ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');
$act = ErpActionPlanner::buildActionPlan('Create a purchase request for missing stock', $ctx, null);
(!empty($act['requires_confirmation']) || (($act['actions'][0]['tool'] ?? '') === 'create_draft_purchase_request'))
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');
class_exists(\Rateb\App\Services\ErpGovernanceLayer::class)
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');
class_exists(ErpProactiveEarlyWarningLayer::class)
    ? $pass('PROACTIVE REGRESSION') : $fail('PROACTIVE REGRESSION');
class_exists(ErpOperationalLearningLayer::class)
    ? $pass('LEARNING REGRESSION') : $fail('LEARNING REGRESSION');
class_exists(ErpControlTowerLayer::class)
    ? $pass('CONTROL TOWER REGRESSION') : $fail('CONTROL TOWER REGRESSION');

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
$regressionNames = [
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'ACCOUNTING REGRESSION', 'INTELLIGENCE REGRESSION',
    'ACTION REGRESSION', 'GOVERNANCE REGRESSION', 'PROACTIVE REGRESSION', 'LEARNING REGRESSION',
    'CONTROL TOWER REGRESSION',
];
$regFailed = array_values(array_filter($failed, static fn($r) => in_array($r['name'], $regressionNames, true)));
$regFailed === [] ? $pass('ALL REGRESSIONS') : $fail('ALL REGRESSIONS', implode(',', array_column($regFailed, 'name')));

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED GATES: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "PHASE 21 CORE GATES PASS\n";
exit(0);
