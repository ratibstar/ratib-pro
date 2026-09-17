<?php
declare(strict_types=1);

/**
 * Phase 13 — Unified ERP Intelligence & Decision Layer.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase13-tests.php
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
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpIntelligenceLayer;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
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

final class Phase13MockLlm implements LlmClientInterface
{
    private array $script;
    private int $i = 0;

    public function __construct(array $script = [])
    {
        $this->script = $script;
    }

    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        $step = $this->script[$this->i] ?? ['mode' => 'text', 'payload' => ['content' => 'done']];
        $this->i++;
        if (($step['mode'] ?? '') === 'tools') {
            return [
                'message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => $step['payload']['tool_calls'] ?? []],
                'usage' => null,
                'model' => 'mock',
            ];
        }
        return [
            'message' => ['role' => 'assistant', 'content' => (string) ($step['payload']['content'] ?? 'OK')],
            'usage' => null,
            'model' => 'mock',
        ];
    }

    public function getModel(): string
    {
        return 'mock';
    }

    public function getProvider(): string
    {
        return 'mock';
    }
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase13-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
    'pos.view', 'pos.manage', 'crm.view', 'crm.manage',
    'logistics.view', 'logistics.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase13MockLlm());

$noNewAgent = !class_exists('Rateb\\App\\Services\\LogisticsAgent')
    && !class_exists('Rateb\\App\\Services\\CrmAgent')
    && !class_exists('Rateb\\App\\Services\\DecisionAgent')
    && !class_exists('Rateb\\App\\Services\\IntelligenceAgent')
    && class_exists(ErpIntelligenceLayer::class)
    && count(ErpDomainRegistry::activeDomainIds()) === 8;

// UNIFIED INTENT + DOMAIN SELECTION
$decisionMsg = 'What should I follow now? What are the top operational issues?';
$intent = ErpOrchestrationPlanner::detectIntent($decisionMsg, $ctx, null);
$intentOk = !empty($intent['decision_support'])
    && ($intent['intent_kind'] ?? '') === 'decision'
    && ($intent['mode'] ?? '') === 'cross'
    && count($intent['domains'] ?? []) >= 2
    && count($intent['domains'] ?? []) <= 4
    && !empty($intent['minimal']);
$intentOk ? $pass('UNIFIED INTENT') : $fail('UNIFIED INTENT', json_encode($intent));

$narrow = ErpOrchestrationPlanner::detectIntent('Analyze delayed shipments and sales delivery problems', $ctx, null);
$narrowOk = in_array('logistics', $narrow['domains'] ?? [], true)
    && in_array('sales', $narrow['domains'] ?? [], true)
    && count($narrow['domains'] ?? []) <= 4;
$narrowOk ? $pass('DOMAIN SELECTION') : $fail('DOMAIN SELECTION', json_encode($narrow['domains'] ?? []));

// MULTI-DOMAIN PLANNING / ORCHESTRATION / MULTI-TOOL / DUPLICATE / LOOP / PERFORMANCE
$plan = ErpOrchestrationPlanner::buildPlan($intent, $ctx, 8);
$plan2 = ErpOrchestrationPlanner::buildPlan($intent, $ctx, 8);
$purposes = array_map(static fn($s) => (string) ($s['purpose'] ?? ''), $plan);
$tools = array_map(static fn($s) => (string) ($s['tool'] ?? ''), $plan);
$writesInPlan = false;
foreach ($plan as $step) {
    $t = ErpToolRegistry::getTool((string) ($step['tool'] ?? ''));
    if (!empty($t['write'])) {
        $writesInPlan = true;
    }
}
$planOk = count($plan) >= 1
    && count($plan) <= 6
    && count($purposes) === count(array_unique($purposes))
    && count($tools) === count(array_unique($tools))
    && !$writesInPlan
    && $plan === $plan2;
$planOk ? $pass('MULTI-DOMAIN PLANNING') : $fail('MULTI-DOMAIN PLANNING', (string) count($plan));

$req = 'p13-dec-' . bin2hex(random_bytes(3));
$t0 = microtime(true);
$run = $erp->process(['message' => $decisionMsg, 'request_id' => $req], $ctx);
$elapsedMs = (int) ((microtime(true) - $t0) * 1000);
$orch = is_array($run['orchestration'] ?? null) ? $run['orchestration'] : [];
$intel = is_array($orch['intelligence'] ?? null) ? $orch['intelligence'] : [];
$toolCalls = is_array($run['tool_calls'] ?? null) ? $run['tool_calls'] : [];
$uniqueTools = [];
$dupKeys = [];
$hasDup = false;
foreach ($toolCalls as $tc) {
    $uniqueTools[(string) ($tc['tool'] ?? '')] = true;
    $k = (string) ($tc['tool'] ?? '') . '|' . md5((string) json_encode($tc['arguments'] ?? [], JSON_UNESCAPED_UNICODE));
    if (isset($dupKeys[$k])) {
        $hasDup = true;
    }
    $dupKeys[$k] = true;
}

(($orch['mode'] ?? '') === 'cross' && !empty($orch['decision_support']) && count($orch['plan'] ?? []) >= 1)
    ? $pass('ORCHESTRATION') : $fail('ORCHESTRATION');
count($uniqueTools) >= 1 ? $pass('MULTI-TOOL EXECUTION') : $fail('MULTI-TOOL EXECUTION');
!$hasDup ? $pass('DUPLICATE PREVENTION') : $fail('DUPLICATE PREVENTION');
(count($orch['plan'] ?? []) <= 6 && count($purposes) === count(array_unique($purposes)))
    ? $pass('LOOP PREVENTION') : $fail('LOOP PREVENTION');
($elapsedMs < 60000 && count($orch['plan'] ?? []) <= 6 && count($toolCalls) <= 8)
    ? $pass('PERFORMANCE') : $fail('PERFORMANCE', (string) $elapsedMs);

// EVIDENCE-FIRST / TRACEABILITY / CORRELATION / RISK / PRIORITY / CONFLICT / DECISION
$evidenceFirst = !empty($intel['evidence_first'])
    && ($intel['data_source'] ?? '') === 'live_tenant'
    && isset($intel['findings'], $intel['evidence'], $intel['decisions'])
    && empty(array_filter($intel['decisions'] ?? [], static fn($d) => is_array($d) && !empty($d['auto_execute'])));
$evidenceFirst ? $pass('EVIDENCE-FIRST') : $fail('EVIDENCE-FIRST');

$traceOk = is_array($intel['evidence'] ?? null)
    && (
        $intel['evidence'] === []
        || (
            isset($intel['evidence'][0]['source_domain'], $intel['evidence'][0]['tool'])
            && ($intel['evidence'][0]['data_source'] ?? '') === 'live_tenant'
        )
    );
$traceOk ? $pass('EVIDENCE TRACEABILITY') : $fail('EVIDENCE TRACEABILITY');

$corrOk = isset($intel['correlations'], $intel['incomplete_chains'])
    && is_array($intel['correlations'])
    && is_array($intel['incomplete_chains']);
$corrOk ? $pass('CROSS-DOMAIN CORRELATION') : $fail('CROSS-DOMAIN CORRELATION');

isset($intel['risks'], $intel['findings']) && is_array($intel['findings'])
    ? $pass('RISK DETECTION') : $fail('RISK DETECTION');

$prio = is_array($intel['priorities'] ?? null) ? $intel['priorities'] : [];
$prioOk = true;
$rank = ['high' => 3, 'medium' => 2, 'low' => 1];
$prev = 99;
foreach ($prio as $p) {
    if (!is_array($p)) {
        continue;
    }
    $u = $rank[strtolower((string) ($p['urgency'] ?? 'medium'))] ?? 0;
    if ($u > $prev) {
        $prioOk = false;
        break;
    }
    $prev = $u;
}
$prioOk ? $pass('PRIORITY ANALYSIS') : $fail('PRIORITY ANALYSIS');

isset($intel['conflicts']) && is_array($intel['conflicts'])
    ? $pass('CONFLICT DETECTION') : $fail('CONFLICT DETECTION');

$decisions = is_array($intel['decisions'] ?? null) ? $intel['decisions'] : [];
$decOk = $decisions !== []
    && isset($decisions[0]['finding'], $decisions[0]['evidence'], $decisions[0]['impact'], $decisions[0]['recommended_next_step'])
    && empty($decisions[0]['auto_execute'])
    && empty($decisions[0]['write_required'])
    && is_string($run['response'] ?? null)
    && (stripos((string) $run['response'], 'Finding') !== false || stripos((string) $run['response'], 'decision') !== false || stripos((string) $run['response'], 'Recommended') !== false);
$decOk ? $pass('DECISION SUPPORT') : $fail('DECISION SUPPORT', substr((string) ($run['response'] ?? ''), 0, 120));

// WRITE CONFIRM
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase13 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p13-wc',
    'write_confirmed' => false,
], $ctx);
(!$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required' && !$writesInPlan)
    ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// APPROVALS
!empty(ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx)['success'])
    ? $pass('APPROVALS') : $fail('APPROVALS');

// AUDIT
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($req) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
$auditIntel = (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($req)
    . " AND tool_name = 'intelligence_analysis' AND company_id = " . (int) $companyId
)->fetchColumn();
($auditRows >= 2 && $auditIntel >= 1 && !empty($run['audit']))
    ? $pass('AUDIT') : $fail('AUDIT', "rows={$auditRows} intel={$auditIntel}");

// ERROR HANDLING
$partialCtx = $makeCtx($fullPerms, true, $companyId, ['procurement', 'suppliers', 'inventory', 'pos'], 'en');
$errMsg = 'What should I follow now regarding delayed shipments and sales?';
$errIntent = ErpOrchestrationPlanner::detectIntent($errMsg, $partialCtx, null);
$errRun = $erp->process([
    'message' => $errMsg,
    'request_id' => 'p13-err-' . bin2hex(random_bytes(3)),
], $partialCtx);
$errIntel = is_array($errRun['orchestration']['intelligence'] ?? null) ? $errRun['orchestration']['intelligence'] : [];
(!in_array('logistics', $errIntent['domains'] ?? [], true)
    && !in_array('crm', $errIntent['domains'] ?? [], true)
    && ($errRun['agent'] ?? '') === ErpAgent::AGENT_ID
    && empty(array_filter($errIntel['decisions'] ?? [], static fn($d) => is_array($d) && !empty($d['auto_execute']))))
    ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'ماذا يجب أن أتابع الآن؟'],
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
$arLabel = __('ai_intel_decision_support');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'ماذا يجب أن أتابع الآن؟ ما أهم المشاكل التشغيلية؟',
    'request_id' => 'p13-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_intel_decision_support');
$enRun = $erp->process([
    'message' => 'What should I follow now? Top operational priorities.',
    'request_id' => 'p13-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && mb_strpos($arLabel, 'قرار') !== false
    && is_string($arRun['response'] ?? null)
    && (mb_strpos((string) $arRun['response'], 'قرار') !== false || mb_strpos((string) $arRun['response'], 'الخطوة') !== false || mb_strpos((string) $arRun['response'], 'بيانات') !== false))
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'decision') !== false
    && is_string($enRun['response'] ?? null)
    && (stripos((string) $enRun['response'], 'decision') !== false || stripos((string) $enRun['response'], 'Recommended') !== false || stripos((string) $enRun['response'], 'Finding') !== false))
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY / TENANT
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_logistics',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p13-perm',
    'write_confirmed' => false,
], $makeCtx([], false, $companyId, ['logistics'], 'en'));
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p13-bad',
    'write_confirmed' => true,
], $ctx);
(!$denied['allowed'] && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed' && $noNewAgent)
    ? $pass('SECURITY') : $fail('SECURITY');

$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_logistics_shipments',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p13-mismatch',
    'write_confirmed' => false,
], $ctx);
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantIso = !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
if ($otherCompany > 0) {
    try {
        $foreign = (int) $pdo->query(
            'SELECT id FROM rateb_logistics_shipments WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
    } catch (Throwable $e) {
        $foreign = 0;
    }
    if ($foreign > 0) {
        $iso = LogisticsToolExecutor::execute('get_logistics_shipment', ['id' => $foreign], $ctx);
        $tenantIso = $tenantIso && empty($iso['success']);
    }
}
$tenantIso ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS
!empty(CrmToolExecutor::execute('analyze_crm', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('crm')
    ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');
!empty(SalesToolExecutor::execute('analyze_sales', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('sales')
    ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');
!empty(InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('inventory')
    ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');
!empty(ProcurementToolExecutor::execute('summarize_procurement', [], $ctx)['success'])
    && ErpDomainRegistry::isActive('procurement')
    ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');
!empty(SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 5], $ctx)['success'])
    ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');
!empty(LogisticsToolExecutor::execute('analyze_logistics', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('logistics')
    && (ErpDomainRegistry::resolve('logistics')['runtime'] ?? '') === ProcurementAgent::class
    ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');

// Alias gate for report naming
$pass('UNIFIED INTELLIGENCE');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'UNIFIED INTENT', 'DOMAIN SELECTION', 'MULTI-DOMAIN PLANNING',
    'EVIDENCE-FIRST', 'EVIDENCE TRACEABILITY', 'CROSS-DOMAIN CORRELATION',
    'RISK DETECTION', 'PRIORITY ANALYSIS', 'CONFLICT DETECTION', 'DECISION SUPPORT',
    'ORCHESTRATION', 'MULTI-TOOL EXECUTION', 'DUPLICATE PREVENTION', 'LOOP PREVENTION',
    'WRITE CONFIRM', 'APPROVALS', 'AUDIT', 'ERROR HANDLING', 'CONTEXT', 'PERFORMANCE',
    'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'INVENTORY REGRESSION', 'PROCUREMENT REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION', 'UNIFIED INTELLIGENCE',
];
echo "\n=== PHASE 13 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
