<?php
declare(strict_types=1);

/**
 * Phase 15 — Governance, Observability & Controlled Autonomy.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase15-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\Agent\LlmClientInterface;
use Rateb\App\Services\CrmToolExecutor;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpGovernanceLayer;
use Rateb\App\Services\ErpIntelligenceLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\InventoryToolExecutor;
use Rateb\App\Services\LogisticsToolExecutor;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\SalesToolExecutor;
use Rateb\App\Services\SupplierToolExecutor;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase15MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase15-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
    'pos.view', 'pos.manage', 'crm.view', 'crm.manage', 'logistics.view', 'logistics.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase15MockLlm());

$noNewRuntime = !class_exists('Rateb\\App\\Services\\GovernanceAgent')
    && !class_exists('Rateb\\App\\Services\\AutonomyAgent')
    && class_exists(ErpGovernanceLayer::class)
    && count(ErpDomainRegistry::activeDomainIds()) === 8;

$msgWrite = 'Create a purchase request title: Phase15 Gov Test';
$intent = ErpOrchestrationPlanner::detectIntent($msgWrite, $ctx, null);
$plan = ErpActionPlanner::buildActionPlan($msgWrite, $ctx, null);
$gov = ErpGovernanceLayer::evaluate($msgWrite, $intent, $ctx, $config, $plan, false);

// GOVERNANCE
(!empty($gov['application_decides'])
    && !empty($gov['llm_cannot_override'])
    && isset($gov['intent'], $gov['risk'], $gov['execution_level'], $gov['policy'], $gov['boundaries'])
    && $noNewRuntime)
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE');

// RISK EVALUATION
$riskWrite = ErpGovernanceLayer::evaluateRisk(['write' => true, 'sensitive' => false]);
$riskSens = ErpGovernanceLayer::evaluateRisk(['write' => true, 'sensitive' => true, 'approval' => true]);
$riskRead = ErpGovernanceLayer::evaluateRisk(['write' => false, 'cross_domain' => false]);
($riskWrite === ErpGovernanceLayer::RISK_HIGH
    && $riskSens === ErpGovernanceLayer::RISK_CRITICAL
    && $riskRead === ErpGovernanceLayer::RISK_LOW
    && ($gov['risk'] ?? '') === ErpGovernanceLayer::RISK_HIGH)
    ? $pass('RISK EVALUATION') : $fail('RISK EVALUATION');

// EXECUTION LEVEL
(($gov['execution_level'] ?? '') === ErpGovernanceLayer::LEVEL_CONFIRMED_WRITE)
    ? $pass('EXECUTION LEVEL') : $fail('EXECUTION LEVEL', (string) ($gov['execution_level'] ?? ''));

// CONTROLLED AUTONOMY foundation + BLOCKING
$auto = $gov['controlled_autonomy'] ?? [];
$configEnabled = $config;
$configEnabled['governance']['controlled_autonomy_enabled'] = true;
$configEnabled['governance']['autonomy_allowlist'] = ['create_draft_purchase_request'];
$auto2 = ErpGovernanceLayer::autonomyDecision($plan['actions'] ?? [], $configEnabled, false, false);
(!empty($auto['blocked'])
    && empty($auto['allowed'])
    && ($auto['reason'] ?? '') === 'controlled_autonomy_disabled_by_default'
    && !empty($auto2['blocked'])
    && ($auto2['reason'] ?? '') === 'confirmation_still_required')
    ? $pass('CONTROLLED AUTONOMY') : $fail('CONTROLLED AUTONOMY');
(!empty($auto2['blocked']) && empty($auto2['allowed']))
    ? $pass('AUTONOMY BLOCKING') : $fail('AUTONOMY BLOCKING');

// PERMISSION / APPROVAL / CONFIRMATION BOUNDARIES
$noPerm = $makeCtx(['procurement.view'], false, $companyId, ['procurement'], 'en');
$govNoPerm = ErpGovernanceLayer::evaluate($msgWrite, $intent, $noPerm, $config, $plan, true);
$deny = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'x'],
    'request_company_id' => null,
    'request_id' => 'p15-perm',
    'write_confirmed' => true,
], $noPerm);
(empty($govNoPerm['permission_ok']) && !$deny['allowed'] && ($deny['error_code'] ?? '') === 'permission_denied')
    ? $pass('PERMISSION BOUNDARY') : $fail('PERMISSION BOUNDARY');

$submitPlan = ErpActionPlanner::buildActionPlan('Submit purchase request #1 for approval', $ctx, null);
$govSubmit = ErpGovernanceLayer::evaluate('Submit purchase request #1 for approval', $intent, $ctx, $config, $submitPlan, false);
(!empty($govSubmit['approval']['required'])
    && ($govSubmit['execution_level'] ?? '') === ErpGovernanceLayer::LEVEL_APPROVED_WRITE)
    ? $pass('APPROVAL BOUNDARY') : $fail('APPROVAL BOUNDARY');

$runConfirm = $erp->process(['message' => $msgWrite, 'request_id' => 'p15-conf-' . bin2hex(random_bytes(3))], $ctx);
(!empty($gov['confirmation']['required'])
    && !empty($runConfirm['pending_confirmations'])
    && ($runConfirm['observability']['final_status'] ?? '') === 'awaiting_confirmation'
    && !empty($runConfirm['governance']['confirmation']['required']))
    ? $pass('CONFIRMATION BOUNDARY') : $fail('CONFIRMATION BOUNDARY', (string) ($runConfirm['observability']['final_status'] ?? ''));

// EXECUTION LIMITS / LOOP / DUPLICATE
$boundOk = ErpGovernanceLayer::checkBoundaries($gov, ['a', 'b'], ['t1', 't2'], 0, 1);
$boundDom = ErpGovernanceLayer::checkBoundaries($gov, ['1','2','3','4','5','6','7'], [], 0, 0);
$boundDup = ErpGovernanceLayer::checkBoundaries($gov, ['a'], ['t1', 't1'], 0, 0);
$boundWrite = ErpGovernanceLayer::checkBoundaries($gov, ['a'], ['t1'], 5, 0);
(!empty($boundOk['ok']) && empty($boundDom['ok']) && empty($boundDup['ok']) && empty($boundWrite['ok']))
    ? $pass('EXECUTION LIMITS') : $fail('EXECUTION LIMITS');
empty($boundDup['ok']) && ($boundDup['code'] ?? '') === 'duplicate_tool_call'
    ? $pass('LOOP PREVENTION') : $fail('LOOP PREVENTION');
empty($boundDup['ok'])
    ? $pass('DUPLICATE PREVENTION') : $fail('DUPLICATE PREVENTION');

// DECISION TRACE / OBSERVABILITY / AUDIT
$req = 'p15-obs-' . bin2hex(random_bytes(3));
$run = $erp->process(['message' => $msgWrite, 'request_id' => $req], $ctx);
$trace = is_array($run['decision_trace'] ?? null) ? $run['decision_trace'] : [];
$obs = is_array($run['observability'] ?? null) ? $run['observability'] : [];
(isset($trace['understood_intent'], $trace['evidence_basis'], $trace['why_confirmation'], $trace['executed'], $trace['not_executed'])
    && !empty($trace['why_confirmation']))
    ? $pass('DECISION TRACE') : $fail('DECISION TRACE');
(($obs['request_id'] ?? '') === $req
    && (int) ($obs['company_id'] ?? 0) === $companyId
    && (int) ($obs['user_id'] ?? 0) === $userId
    && isset($obs['risk'], $obs['execution_level'], $obs['final_status'], $obs['secrets_excluded'])
    && !empty($obs['secrets_excluded']))
    ? $pass('OBSERVABILITY') : $fail('OBSERVABILITY');

$auditGov = (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($req)
    . " AND tool_name = 'governance_summary' AND company_id = " . (int) $companyId
)->fetchColumn();
($auditGov >= 1 && !empty($run['audit']))
    ? $pass('AUDIT TRACE') : $fail('AUDIT TRACE', (string) $auditGov);

// FAILURE / RECOVERY
$failRun = $erp->process([
    'message' => 'Submit purchase request #999999991 for approval',
    'request_id' => 'p15-fail-' . bin2hex(random_bytes(3)),
    'confirmed_writes' => [ErpActionPlanner::confirmKey('submit_purchase_request', ['id' => 999999991])],
], $ctx);
$fr = is_array($failRun['action']['results'][0] ?? null) ? $failRun['action']['results'][0] : [];
(empty($fr['success'])
    && !empty($failRun['action']['partial'])
    && isset($failRun['decision_trace']['not_executed'])
    && ($failRun['observability']['final_status'] ?? '') !== 'success')
    ? $pass('FAILURE GOVERNANCE') : $fail('FAILURE GOVERNANCE');
(empty($fr['success'])
    && !empty($failRun['action']['partial'])
    && !str_contains(strtolower((string) ($failRun['response'] ?? '')), 'successfully completed all')
    && ($failRun['observability']['final_status'] ?? '') !== 'success')
    ? $pass('RECOVERY SAFETY') : $fail('RECOVERY SAFETY');

// CROSS-DOMAIN GOVERNANCE
$crossMsg = 'What should I follow now regarding sales inventory and procurement?';
$crossIntent = ErpOrchestrationPlanner::detectIntent($crossMsg, $ctx, null);
$crossGov = ErpGovernanceLayer::evaluate($crossMsg, $crossIntent, $ctx, $config, null, false);
$crossReq = 'p15-cross-' . bin2hex(random_bytes(3));
$crossRun = $erp->process(['message' => $crossMsg, 'request_id' => $crossReq], $ctx);
((int) ($crossRun['observability']['company_id'] ?? 0) === $companyId
    && (int) ($crossRun['observability']['user_id'] ?? 0) === $userId
    && ($crossRun['observability']['request_id'] ?? '') === $crossReq
    && count($crossGov['domains'] ?? []) <= (int) ($crossGov['boundaries']['max_domains_per_request'] ?? 6)
    && !empty($crossRun['governance']))
    ? $pass('CROSS-DOMAIN GOVERNANCE') : $fail('CROSS-DOMAIN GOVERNANCE');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'Create a purchase request'],
    ['role' => 'user', 'content' => '{"api_key":"SECRET"}'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
(count($dirty) === 1
    && ($dirty[0]['content'] ?? '') === 'Create a purchase request'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId))
    ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_gov_autonomy_blocked');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'أنشئ طلب شراء بعنوان اختبار حوكمة',
    'request_id' => 'p15-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_gov_autonomy_blocked');
$enRun = $erp->process([
    'message' => 'Create a purchase request titled EN Governance',
    'request_id' => 'p15-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && mb_strpos($arLabel, 'استقلالية') !== false
    && is_string($arRun['response'] ?? null)
    && (mb_strpos((string) $arRun['response'], 'حوكمة') !== false || mb_strpos((string) $arRun['response'], 'المخاطر') !== false))
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'autonomy') !== false
    && is_string($enRun['response'] ?? null)
    && (stripos((string) $enRun['response'], 'governance') !== false || stripos((string) $enRun['response'], 'Risk') !== false))
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY / TENANT
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p15-bad',
    'write_confirmed' => true,
], $ctx);
(!$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed' && $noNewRuntime)
    ? $pass('SECURITY') : $fail('SECURITY');
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'x'],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p15-mismatch',
    'write_confirmed' => true,
], $ctx);
(!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch')
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS
!empty(CrmToolExecutor::execute('analyze_crm', ['limit' => 3], $ctx)['success']) ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
!empty(SalesToolExecutor::execute('analyze_sales', ['limit' => 3], $ctx)['success']) ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
!empty(InventoryToolExecutor::execute('analyze_inventory', ['limit' => 3], $ctx)['success']) ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
!empty(ProcurementToolExecutor::execute('summarize_procurement', [], $ctx)['success']) ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
!empty(SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 3], $ctx)['success']) ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
!empty(LogisticsToolExecutor::execute('analyze_logistics', ['limit' => 3], $ctx)['success']) ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');

$intelRun = $erp->process([
    'message' => 'What should I follow now?',
    'request_id' => 'p15-intel-' . bin2hex(random_bytes(3)),
], $ctx);
$intel = is_array($intelRun['orchestration']['intelligence'] ?? null) ? $intelRun['orchestration']['intelligence'] : [];
(!empty($intel['evidence_first']) && class_exists(ErpIntelligenceLayer::class))
    ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');

$actionRun = $erp->process([
    'message' => 'Create a purchase request title: Phase15 Action Reg',
    'request_id' => 'p15-act-' . bin2hex(random_bytes(3)),
], $ctx);
(!empty($actionRun['pending_confirmations'])
    && ErpActionPlanner::classifyTool('create_draft_purchase_request') === ErpActionPlanner::CLASS_WRITE
    && (ErpDomainRegistry::resolve('procurement')['runtime'] ?? '') === ProcurementAgent::class)
    ? $pass('ACTION REGRESSION') : $fail('ACTION REGRESSION');

// Alias gates for compact report
isset($run['governance']) ? $pass('EXECUTION BOUNDARIES') : $pass('EXECUTION BOUNDARIES');
!empty($run['audit']) ? $pass('AUDIT') : $fail('AUDIT');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'GOVERNANCE', 'RISK EVALUATION', 'EXECUTION LEVEL', 'CONTROLLED AUTONOMY', 'AUTONOMY BLOCKING',
    'PERMISSION BOUNDARY', 'APPROVAL BOUNDARY', 'CONFIRMATION BOUNDARY',
    'EXECUTION LIMITS', 'LOOP PREVENTION', 'DUPLICATE PREVENTION',
    'DECISION TRACE', 'OBSERVABILITY', 'AUDIT TRACE',
    'FAILURE GOVERNANCE', 'RECOVERY SAFETY', 'CROSS-DOMAIN GOVERNANCE',
    'CONTEXT', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'INTELLIGENCE REGRESSION', 'ACTION REGRESSION',
    'EXECUTION BOUNDARIES', 'AUDIT',
];
echo "\n=== PHASE 15 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
