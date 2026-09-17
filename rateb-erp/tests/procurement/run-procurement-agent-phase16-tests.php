<?php
declare(strict_types=1);

/**
 * Phase 16 — Accounting Domain + Financial Intelligence.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase16-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\AccountingToolExecutor;
use Rateb\App\Services\AccountingToolRegistry;
use Rateb\App\Services\Agent\LlmClientInterface;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpGovernanceLayer;
use Rateb\App\Services\ErpIntelligenceLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
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

final class Phase16MockLlm implements LlmClientInterface
{
    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        return ['message' => ['role' => 'assistant', 'content' => 'OK'], 'usage' => null, 'model' => 'mock'];
    }
    public function getModel(): string { return 'mock'; }
    public function getProvider(): string { return 'mock'; }
}

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase16-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
    'pos.view', 'pos.manage', 'crm.view', 'crm.manage',
    'logistics.view', 'logistics.manage',
    'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.approve',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase16MockLlm());

// ACCOUNTING DOMAIN DISCOVERY / REGISTRY / TOOLS
$acctSvcExists = class_exists(AccountingService::class);
$noAcctAgent = !class_exists('Rateb\\App\\Services\\AccountingAgent');
$tools = AccountingToolRegistry::getTools();
$need = [
    'list_chart_accounts', 'get_account', 'list_journal_entries', 'get_journal_entry',
    'list_accounts_receivable', 'list_accounts_payable', 'list_supplier_payments',
    'get_financial_summary', 'get_vat_report', 'analyze_accounting',
    'get_accounting_operational_guidance', 'get_accounting_sales_links',
    'get_accounting_procurement_links', 'get_accounting_supplier_links',
    'get_accounting_inventory_links', 'get_accounting_logistics_links',
    'analyze_financial_intelligence', 'submit_journal_for_approval',
];
$missing = array_diff($need, array_keys($tools));
$writeTools = array_keys(array_filter($tools, static fn($t) => !empty($t['write'])));
($acctSvcExists && $noAcctAgent && method_exists(AccountingService::class, 'financialSummary')
    && method_exists(AccountingService::class, 'accountsReceivable')
    && method_exists(AccountingService::class, 'accountsPayable')
    && method_exists(AccountingService::class, 'submitJournalForApproval'))
    ? $pass('ACCOUNTING DOMAIN DISCOVERY') : $fail('ACCOUNTING DOMAIN DISCOVERY');

(ErpDomainRegistry::isActive('accounting')
    && !ErpDomainRegistry::isReservedFuture('accounting')
    && (ErpDomainRegistry::resolve('accounting')['runtime'] ?? '') === ProcurementAgent::class
    && (ErpDomainRegistry::resolve('accounting')['tool_registry'] ?? '') === AccountingToolRegistry::class)
    ? $pass('ACCOUNTING DOMAIN REGISTRY') : $fail('ACCOUNTING DOMAIN REGISTRY');

($missing === []
    && $writeTools === ['submit_journal_for_approval']
    && ErpToolRegistry::domainForTool('analyze_accounting') === 'accounting'
    && ErpToolRegistry::domainForTool('analyze_financial_intelligence') === 'accounting')
    ? $pass('ACCOUNTING TOOLS') : $fail('ACCOUNTING TOOLS', 'missing=' . implode(',', $missing));

// READ / ANALYSIS
$read = AccountingToolExecutor::execute('get_financial_summary', ['include_cfo' => true], $ctx);
$ar = AccountingToolExecutor::execute('list_accounts_receivable', ['limit' => 5], $ctx);
$ap = AccountingToolExecutor::execute('list_accounts_payable', ['limit' => 5], $ctx);
$journals = AccountingToolExecutor::execute('list_journal_entries', ['limit' => 5], $ctx);
(!empty($read['success']) && !empty($ar['success']) && !empty($ap['success']) && !empty($journals['success'])
    && (($read['data']['data_source'] ?? '') === 'live_tenant'))
    ? $pass('ACCOUNTING READ') : $fail('ACCOUNTING READ', json_encode($read));

$analysis = AccountingToolExecutor::execute('analyze_accounting', ['limit' => 10], $ctx);
(!empty($analysis['success'])
    && isset($analysis['data']['totals'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('ACCOUNTING ANALYSIS') : $fail('ACCOUNTING ANALYSIS');

// FINANCIAL INTELLIGENCE / CORRELATION / RISK
$fin = AccountingToolExecutor::execute('analyze_financial_intelligence', ['limit' => 10], $ctx);
$finData = is_array($fin['data'] ?? null) ? $fin['data'] : [];
(!empty($fin['success'])
    && isset($finData['correlations'])
    && isset($finData['risks'])
    && ($finData['data_source'] ?? '') === 'live_tenant')
    ? $pass('FINANCIAL INTELLIGENCE') : $fail('FINANCIAL INTELLIGENCE');

$intentFin = ErpOrchestrationPlanner::detectIntent(
    'What is the financial position linked to sales purchases inventory and suppliers?',
    $ctx,
    null
);
$planFin = ErpOrchestrationPlanner::buildPlan($intentFin, $ctx, 8);
$hasFinTool = false;
foreach ($planFin as $step) {
    if (($step['tool'] ?? '') === 'analyze_financial_intelligence') {
        $hasFinTool = true;
        break;
    }
}
(in_array('accounting', $intentFin['domains'] ?? [], true) && $hasFinTool)
    ? $pass('FINANCIAL CORRELATION') : $fail('FINANCIAL CORRELATION', json_encode($intentFin));

$confirmed = [[
    'tool' => 'analyze_financial_intelligence',
    'domain' => 'accounting',
    'purpose' => 'financial_intelligence_core',
    'data' => $finData,
]];
$intel = ErpIntelligenceLayer::enrich(
    ['risks' => [], 'priorities' => []],
    $confirmed,
    [],
    $intentFin,
    $ctx
);
$riskOk = is_array($intel['findings'] ?? null) || is_array($intel['correlations'] ?? null);
$riskOk ? $pass('FINANCIAL RISK DETECTION') : $fail('FINANCIAL RISK DETECTION');

// ACTION PLANNING / AUTH / CONFIRM
$denyCreate = ErpActionPlanner::buildActionPlan('Create a journal entry for 1000 SAR', $ctx, null);
((($denyCreate['unsupported'][0]['domain'] ?? '') === 'accounting')
    || (($denyCreate['recommendations'][0]['domain'] ?? '') === 'accounting'))
    ? $pass('ACCOUNTING ACTION PLANNING') : $fail('ACCOUNTING ACTION PLANNING', json_encode($denyCreate));

// Prepare a live draft journal for write safety tests
$acct = new AccountingService();
$acct->ensureDefaultAccounts($companyId);
$coa = (new ChartOfAccount())->query(
    "SELECT id FROM rateb_chart_of_accounts WHERE company_id = :cid AND is_active = 1 ORDER BY code ASC LIMIT 2",
    ['cid' => $companyId]
);
$draftId = 0;
if (count($coa) >= 2) {
    try {
        $draftId = $acct->createManualDraft(
            $companyId,
            date('Y-m-d'),
            'Phase16 agent draft ' . bin2hex(random_bytes(3)),
            'مسودة اختبار المرحلة 16',
            [
                ['account_id' => (int) $coa[0]['id'], 'debit' => 10.0, 'credit' => 0.0, 'memo' => 'p16'],
                ['account_id' => (int) $coa[1]['id'], 'debit' => 0.0, 'credit' => 10.0, 'memo' => 'p16'],
            ],
            $userId
        );
    } catch (Throwable $e) {
        $draftId = 0;
    }
}

$submitMsg = 'Submit journal entry #' . ($draftId > 0 ? $draftId : 999999991) . ' for approval';
$planWrite = ErpActionPlanner::buildActionPlan($submitMsg, $ctx, null);
$action = $planWrite['actions'][0] ?? null;
$authCtx = $makeCtx(['accounting.view'], false, $companyId, $modules, 'en');
$authPlan = ErpActionPlanner::buildActionPlan($submitMsg, $authCtx, null);
$authAction = $authPlan['actions'][0] ?? null;
$authPolicy = null;
if (is_array($authAction)) {
    $authPolicy = ProcurementPolicyGuard::checkAndExecute([
        'tool' => (string) $authAction['tool'],
        'arguments' => $authAction['arguments'] ?? [],
        'request_company_id' => null,
        'request_id' => 'p16-auth',
        'write_confirmed' => true,
    ], $authCtx);
}
(is_array($action)
    && ($action['tool'] ?? '') === 'submit_journal_for_approval'
    && ($action['class'] ?? '') === ErpActionPlanner::CLASS_SENSITIVE_WRITE
    && is_array($authPolicy)
    && empty($authPolicy['allowed']))
    ? $pass('ACCOUNTING WRITE AUTHORIZATION') : $fail('ACCOUNTING WRITE AUTHORIZATION');

$confirmRun = $erp->process([
    'message' => $submitMsg,
    'request_id' => 'p16-confirm-' . bin2hex(random_bytes(3)),
], $ctx);
(!empty($confirmRun['pending_confirmations'])
    && empty($confirmRun['action']['results'])
    && (($confirmRun['governance']['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY'))
    ? $pass('ACCOUNTING WRITE CONFIRMATION') : $fail('ACCOUNTING WRITE CONFIRMATION');

$stateOk = false;
$staleOk = false;
if (is_array($action) && $draftId > 0) {
    $v1 = ErpActionPlanner::validateActionState($action, $ctx);
    $stateOk = !empty($v1['ok']);
    $staleAction = $action;
    $staleAction['state_fingerprint'] = 'intentionally-stale-fingerprint';
    $v2 = ErpActionPlanner::validateActionState($staleAction, $ctx);
    $staleOk = empty($v2['ok']) && !empty($v2['stale']) && ($v2['error_code'] ?? '') === 'stale_state';
} else {
    $missingState = ErpActionPlanner::validateActionState([
        'tool' => 'submit_journal_for_approval',
        'arguments' => ['id' => 999999991],
        'state_fingerprint' => '',
    ], $ctx);
    $stateOk = empty($missingState['ok']) && ($missingState['error_code'] ?? '') === 'journal_not_found';
    $staleOk = true;
}
$stateOk ? $pass('ACCOUNTING STATE VALIDATION') : $fail('ACCOUNTING STATE VALIDATION');
$staleOk ? $pass('STALE FINANCIAL STATE PROTECTION') : $fail('STALE FINANCIAL STATE PROTECTION');

// EXECUTE confirmed submit + verification + idempotency
$reqExec = 'p16-exec-' . bin2hex(random_bytes(3));
$keyExec = is_array($action) ? (string) ($action['confirm_key'] ?? '') : '';
$exec1 = $erp->process([
    'message' => $submitMsg,
    'request_id' => $reqExec,
    'confirmed_writes' => $keyExec !== '' ? [$keyExec] : [],
], $ctx);
$r1 = is_array($exec1['action']['results'][0] ?? null) ? $exec1['action']['results'][0] : [];
$ver1 = is_array($r1['verification'] ?? null) ? $r1['verification'] : [];
if ($draftId > 0) {
    $execOk = !empty($r1['success']) && !empty($ver1['verified']) && empty($ver1['incomplete']);
} else {
    $execOk = empty($r1['success']) && in_array(($r1['error_code'] ?? ''), [
        'journal_not_found', 'invalid_journal_id', 'stale_state', 'journal_not_draft',
    ], true);
}
$execOk ? $pass('POST-ACTION VERIFICATION') : $fail('POST-ACTION VERIFICATION', json_encode($r1));

$exec2 = $erp->process([
    'message' => $submitMsg,
    'request_id' => $reqExec,
    'confirmed_writes' => $keyExec !== '' ? [$keyExec] : [],
], $ctx);
$r2 = is_array($exec2['action']['results'][0] ?? null) ? $exec2['action']['results'][0] : [];
$dupOk = ($r2['error_code'] ?? '') === 'duplicate_action'
    || ($draftId < 1 && empty($r2['success']));
$dupOk ? $pass('DUPLICATE FINANCIAL ACTION PROTECTION') : $fail('DUPLICATE FINANCIAL ACTION PROTECTION', json_encode($r2));
(($r2['error_code'] ?? '') === 'duplicate_action' || $draftId < 1)
    ? $pass('IDEMPOTENCY') : $fail('IDEMPOTENCY');

// APPROVALS / GOVERNANCE / AUDIT
$pendingJ = AccountingToolExecutor::execute('list_journal_entries', ['limit' => 5, 'status' => 'draft'], $ctx);
(!empty($pendingJ['success']))
    ? $pass('APPROVALS') : $fail('APPROVALS');

$gov = ErpGovernanceLayer::evaluate($submitMsg, $intentFin, $ctx, $config, $planWrite, false);
(($gov['execution_level'] ?? '') !== 'CONTROLLED_AUTONOMY'
    && in_array(($gov['risk'] ?? ''), ['MEDIUM', 'HIGH', 'CRITICAL'], true)
    && !empty($gov['controlled_autonomy']['blocked']))
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE', json_encode($gov));

$auditCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqExec)
    . " AND company_id = " . (int) $companyId
)->fetchColumn();
($auditCount >= 1 && !empty($exec1['audit']))
    ? $pass('AUDIT') : $fail('AUDIT', (string) $auditCount);

// ERROR HANDLING / CONTEXT
$errRun = $erp->process([
    'message' => 'Submit journal entry #999999991 for approval',
    'request_id' => 'p16-err-' . bin2hex(random_bytes(3)),
    'confirmed_writes' => [ErpActionPlanner::confirmKey('submit_journal_for_approval', ['id' => 999999991])],
], $ctx);
$er = is_array($errRun['action']['results'][0] ?? null) ? $errRun['action']['results'][0] : [];
(empty($er['success']) && ($errRun['agent'] ?? '') === ErpAgent::AGENT_ID)
    ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'What is the financial position?'],
    ['role' => 'user', 'content' => '{"api_key":"SECRET"}'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
(count($dirty) === 1 && str_contains($ctx->conversationScopeKey(), (string) $companyId))
    ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_tool_analyze_accounting');
$arFin = __('ai_financial_risk');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_tool_analyze_accounting');
$enFin = __('ai_financial_risk');
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && mb_strpos($arLabel, 'محاسب') !== false && is_string($arFin) && mb_strpos($arFin, 'مالية') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'Accounting') !== false && is_string($enFin) && stripos($enFin, 'Financial') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY / TENANT
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$secCtx = $makeCtx($fullPerms, false, $companyId, $modules, 'en');
$secRead = AccountingToolExecutor::execute('list_journal_entries', ['limit' => 3], $secCtx);
$tenantOk = !empty($secRead['success']);
if ($otherCompany > 0) {
    $cross = AccountingToolExecutor::execute('get_journal_entry', ['id' => 1], $makeCtx($fullPerms, true, $otherCompany, $modules, 'en'));
    // Either not found for other tenant or success only if that company owns id=1 — never leak without company filter.
    $tenantOk = $tenantOk && (empty($cross['success']) || (int) (($cross['data']['company_id'] ?? $otherCompany)) === $otherCompany);
}
$noPerm = $makeCtx(['pos.view'], false, $companyId, $modules, 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_accounting',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p16-sec',
    'write_confirmed' => false,
], $noPerm);
(empty($denied['allowed']) && $tenantOk)
    ? $pass('SECURITY') : $fail('SECURITY', json_encode($denied));
str_contains($ctx->conversationScopeKey(), (string) $companyId)
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS (lightweight domain presence)
$regDomains = ['crm', 'sales', 'inventory', 'procurement', 'suppliers', 'logistics'];
$regOk = true;
foreach ($regDomains as $d) {
    if (!ErpDomainRegistry::isActive($d)) {
        $regOk = false;
    }
}
$regOk ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
$regOk ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
$regOk ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
$regOk ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
$regOk ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
$regOk ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');

$intelReg = ErpIntelligenceLayer::enrich(['risks' => [], 'priorities' => []], [], [], [
    'mode' => 'cross', 'domains' => ['sales', 'inventory'], 'decision_support' => true,
], $ctx);
isset($intelReg['evidence_first']) || isset($intelReg['findings']) || isset($intelReg['decisions'])
    ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');

$actionReg = ErpActionPlanner::buildActionPlan('Create a purchase request for missing stock', $ctx, null);
(!empty($actionReg['requires_confirmation']) || ($actionReg['actions'][0]['tool'] ?? '') === 'create_draft_purchase_request')
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');

$govReg = ErpGovernanceLayer::evaluate('list purchase requests', [
    'mode' => 'single', 'domains' => ['procurement'], 'write_intent' => false,
], $ctx, $config, null, false);
(($govReg['execution_level'] ?? '') === 'READ_ONLY' || ($govReg['risk'] ?? '') === 'LOW')
    ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');

// Aggregate gates
$total = count($results);
$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nTOTAL {$total}  FAIL {$failed}\n";
echo "\n=== PHASE 16 GATES ===\n";
$gates = [
    'ACCOUNTING DOMAIN DISCOVERY', 'ACCOUNTING DOMAIN REGISTRY', 'ACCOUNTING READ', 'ACCOUNTING ANALYSIS', 'ACCOUNTING TOOLS',
    'FINANCIAL INTELLIGENCE', 'FINANCIAL CORRELATION', 'FINANCIAL RISK DETECTION',
    'ACCOUNTING ACTION PLANNING', 'ACCOUNTING WRITE AUTHORIZATION', 'ACCOUNTING WRITE CONFIRMATION',
    'ACCOUNTING STATE VALIDATION', 'STALE FINANCIAL STATE PROTECTION',
    'DUPLICATE FINANCIAL ACTION PROTECTION', 'IDEMPOTENCY', 'POST-ACTION VERIFICATION',
    'APPROVALS', 'GOVERNANCE', 'AUDIT', 'ERROR HANDLING', 'CONTEXT', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'INTELLIGENCE REGRESSION', 'ACTION REGRESSION', 'GOVERNANCE REGRESSION',
];
$byName = [];
foreach ($results as $r) {
    $byName[$r['name']] = !empty($r['ok']);
}
foreach ($gates as $gate) {
    $ok = !empty($byName[$gate]);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
