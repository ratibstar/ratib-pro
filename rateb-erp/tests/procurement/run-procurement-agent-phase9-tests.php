<?php
declare(strict_types=1);

/**
 * Phase 9 — ERP Agent Orchestration & Cross-Domain Intelligence.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase9-tests.php
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
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\InventoryToolExecutor;
use Rateb\App\Services\InventoryToolRegistry;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\ProcurementToolRegistry;
use Rateb\App\Services\SupplierToolExecutor;
use Rateb\App\Services\SupplierToolRegistry;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase9MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase9-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
];
$modules = ['procurement', 'suppliers', 'inventory'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase9MockLlm());

// SINGLE DOMAIN REGRESSION
$singleIntent = $erp->detectIntent(['message' => 'list purchase requests', 'domain' => 'procurement'], $ctx);
$singleRun = $erp->process([
    'message' => 'summarize procurement',
    'request_id' => 'p9-single-' . bin2hex(random_bytes(3)),
    'domain' => 'procurement',
], $ctx);
$singleOk = ($singleIntent['mode'] ?? '') === 'single'
    && ($singleRun['domain'] ?? '') === 'procurement'
    && ($singleRun['agent'] ?? '') === ErpAgent::AGENT_ID
    && empty($singleRun['orchestration']['mode'] ?? null);
$singleOk ? $pass('SINGLE DOMAIN REGRESSION') : $fail('SINGLE DOMAIN REGRESSION');

// CROSS-DOMAIN INTENT
$examples = [
    'ما الموردين المرتبطين بأوامر شراء متأخرة وما تأثيرها على المخزون؟',
    'هل عندنا نقص مخزون مرتبط بتأخر مورد أو طلب شراء؟',
    'حلل حالة الموردين والمشتريات والمخزون وحدد أهم المشاكل.',
    'ما الطلبات التي تحتاج متابعة بسبب انخفاض المخزون؟',
];
$intentOk = true;
foreach ($examples as $msg) {
    $det = ErpOrchestrationPlanner::detectIntent($msg, $ctx, null);
    if (($det['mode'] ?? '') !== 'cross' || count($det['domains'] ?? []) < 2) {
        $intentOk = false;
        break;
    }
}
$intentOk ? $pass('CROSS-DOMAIN INTENT') : $fail('CROSS-DOMAIN INTENT');

// CROSS-DOMAIN READ / ANALYSIS / INTELLIGENCE / ORCHESTRATION / MULTI-TOOL
$crossMsg = 'حلل حالة الموردين والمشتريات والمخزون وحدد أهم المشاكل.';
$reqCross = 'p9-cross-' . bin2hex(random_bytes(3));
$cross = $erp->process([
    'message' => $crossMsg,
    'request_id' => $reqCross,
], $ctx);

$orch = is_array($cross['orchestration'] ?? null) ? $cross['orchestration'] : [];
$plan = is_array($orch['plan'] ?? null) ? $orch['plan'] : [];
$toolCalls = is_array($cross['tool_calls'] ?? null) ? $cross['tool_calls'] : [];
$intel = is_array($orch['intelligence'] ?? null) ? $orch['intelligence'] : [];
$domainsUsed = [];
foreach ($toolCalls as $tc) {
    if (!empty($tc['domain'])) {
        $domainsUsed[(string) $tc['domain']] = true;
    }
}
$uniqueTools = [];
foreach ($toolCalls as $tc) {
    $uniqueTools[(string) ($tc['tool'] ?? '')] = true;
}

$readOk = ($cross['domain'] ?? '') === ErpAgent::MODE_CROSS
    && ($cross['agent'] ?? '') === ErpAgent::AGENT_ID
    && $plan !== []
    && $toolCalls !== []
    && ($intel['data_source'] ?? '') === 'live_tenant';
$readOk ? $pass('CROSS-DOMAIN READ') : $fail('CROSS-DOMAIN READ');

$analysisOk = isset($intel['metrics'], $intel['summary'], $intel['follow_up'])
    && is_array($intel['priorities'] ?? null)
    && is_array($intel['risks'] ?? null)
    && is_array($intel['recommendations'] ?? null);
$analysisOk ? $pass('CROSS-DOMAIN ANALYSIS') : $fail('CROSS-DOMAIN ANALYSIS');

$intelOk = $analysisOk
    && count($orch['domains'] ?? []) >= 2
    && str_contains((string) ($cross['response'] ?? ''), 'live data')
    && !ErpDomainRegistry::isActive('sales')
    && in_array('sales', ErpDomainRegistry::getReservedFutureDomains(), true);
$intelOk ? $pass('CROSS-DOMAIN INTELLIGENCE') : $fail('CROSS-DOMAIN INTELLIGENCE');

$orchOk = ($orch['mode'] ?? '') === 'cross'
    && count($plan) >= 2
    && count($toolCalls) >= 2
    && count($uniqueTools) >= 2
    && count($domainsUsed) >= 2;
$orchOk ? $pass('ORCHESTRATION') : $fail('ORCHESTRATION', 'tools=' . count($toolCalls) . ' domains=' . count($domainsUsed));

$multiOk = count($toolCalls) >= 2 && count($uniqueTools) >= 2;
$multiOk ? $pass('MULTI-TOOL EXECUTION') : $fail('MULTI-TOOL EXECUTION');

// WRITE CONFIRM
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase9 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p9-wc',
    'write_confirmed' => false,
], $ctx);
$writeInPlan = false;
foreach ($plan as $step) {
    $t = ErpToolRegistry::getTool((string) ($step['tool'] ?? ''));
    if (!empty($t['write'])) {
        $writeInPlan = true;
    }
}
$writeOk = !$block['allowed']
    && ($block['error_code'] ?? '') === 'write_confirmation_required'
    && !$writeInPlan;
$writeOk ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// APPROVALS
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx);
$apprOk = !empty($appr['success']) && is_array($appr['data'] ?? null);
$apprOk ? $pass('APPROVALS') : $fail('APPROVALS');

// AUDIT
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($reqCross) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
$auditPlan = (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqCross)
    . " AND tool_name = 'orchestration_plan' AND company_id = " . (int) $companyId
)->fetchColumn();
$auditOk = $auditRows >= 2 && $auditPlan >= 1 && !empty($cross['audit']);
$auditOk ? $pass('AUDIT') : $fail('AUDIT', (string) $auditRows);

// ERROR HANDLING — deny inventory module mid-context should still return confirmed parts only
$partialCtx = $makeCtx($fullPerms, true, $companyId, ['procurement', 'suppliers'], 'en');
$partialIntent = ErpOrchestrationPlanner::detectIntent($crossMsg, $partialCtx, null);
$partialPlan = ErpOrchestrationPlanner::buildPlan($partialIntent, $partialCtx, 8);
$hasInvTool = false;
foreach ($partialPlan as $step) {
    if (($step['domain'] ?? '') === 'inventory') {
        $hasInvTool = true;
    }
}
$errRun = $erp->process([
    'message' => $crossMsg,
    'request_id' => 'p9-err-' . bin2hex(random_bytes(3)),
], $partialCtx);
$errOrch = is_array($errRun['orchestration'] ?? null) ? $errRun['orchestration'] : [];
$errOk = !$hasInvTool
    && ($errRun['domain'] ?? '') === ErpAgent::MODE_CROSS
    && is_array($errOrch['intelligence']['partial_failures'] ?? null)
    && str_contains((string) ($errRun['response'] ?? ''), 'live data');
// Force a denied tool path via no-permission context on one domain tool
$noInvPerm = $makeCtx(
    ['procurement.view', 'procurement.manage', 'suppliers.manage'],
    false,
    $companyId,
    ['procurement', 'suppliers', 'inventory'],
    'en'
);
$deniedInv = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_inventory',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p9-err-deny',
    'write_confirmed' => false,
], $noInvPerm);
$errOk = $errOk && !$deniedInv['allowed'];
$errOk ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'موردين ومخزون'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
$ctxOk = count($dirty) === 1
    && $dirty[0]['content'] === 'موردين ومخزون'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId);
$ctxOk ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'حلل حالة الموردين والمشتريات والمخزون وحدد أهم المشاكل.',
    'request_id' => 'p9-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enRun = $erp->process([
    'message' => 'Analyze suppliers, procurement, and inventory and list top issues.',
    'request_id' => 'p9-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arRun['response'] ?? null) && mb_strpos($arRun['response'], 'ملخص') !== false)
    ? $pass('AR') : $fail('AR', mb_substr((string) ($arRun['response'] ?? ''), 0, 80));
(is_string($enRun['response'] ?? null) && stripos($enRun['response'], 'Operational') !== false)
    ? $pass('EN') : $fail('EN', mb_substr((string) ($enRun['response'] ?? ''), 0, 80));

// SECURITY
$noPerm = $makeCtx([], false, $companyId, ['procurement', 'inventory', 'suppliers'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_supplier_cross_domain',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p9-sec',
    'write_confirmed' => false,
], $noPerm);
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p9-bad',
    'write_confirmed' => true,
], $ctx);
(!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed')
    ? $pass('SECURITY') : $fail('SECURITY');

// TENANT
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantOk = false;
if ($otherCompany > 0) {
    $foreignSup = (int) $pdo->query(
        'SELECT id FROM rateb_suppliers WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreignSup > 0) {
        $iso = SupplierToolExecutor::execute('get_supplier', ['id' => $foreignSup], $ctx);
        $tenantOk = empty($iso['success']) && ($iso['error_code'] ?? '') === 'supplier_not_found';
    } else {
        $tenantOk = true;
    }
} else {
    $iso = SupplierToolExecutor::execute('get_supplier', ['id' => 999999991], $ctx);
    $tenantOk = empty($iso['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_inventory',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p9-mismatch',
    'write_confirmed' => false,
], $ctx);
$tenantOk = $tenantOk && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// PROCUREMENT REGRESSION
$prRead = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx);
$prSum = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$prAdv = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctx);
$prGuide = ProcurementToolExecutor::execute('get_procurement_operational_guidance', ['limit' => 5], $ctx);
$prBlock = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'submit_purchase_request',
    'arguments' => ['id' => 1],
    'request_company_id' => null,
    'request_id' => 'p9-pr-block',
    'write_confirmed' => false,
], $ctx);
$prOk = !empty($prRead['success']) && !empty($prSum['success']) && !empty($prAdv['success'])
    && !empty($prGuide['success']) && !$prBlock['allowed']
    && ErpDomainRegistry::isActive('procurement');
$prOk ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

// INVENTORY REGRESSION
$invRead = InventoryToolExecutor::execute('list_inventory_items', ['limit' => 3], $ctx);
$invAn = InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx);
$invOk = !empty($invRead['success']) && !empty($invAn['success'])
    && InventoryToolRegistry::isAllowed('analyze_inventory')
    && ErpDomainRegistry::isActive('inventory');
$invOk ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');

// SUPPLIER REGRESSION
$supRead = SupplierToolExecutor::execute('list_suppliers', ['limit' => 3], $ctx);
$supAn = SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 5], $ctx);
$supCross = SupplierToolExecutor::execute('analyze_supplier_cross_domain', ['limit' => 5], $ctx);
$supOk = !empty($supRead['success']) && !empty($supAn['success']) && !empty($supCross['success'])
    && SupplierToolRegistry::isAllowed('analyze_suppliers')
    && ErpDomainRegistry::isActive('suppliers')
    && count(ErpDomainRegistry::activeDomainIds()) === 3;
$supOk ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'SINGLE DOMAIN REGRESSION',
    'CROSS-DOMAIN INTENT',
    'CROSS-DOMAIN READ',
    'CROSS-DOMAIN ANALYSIS',
    'CROSS-DOMAIN INTELLIGENCE',
    'ORCHESTRATION',
    'MULTI-TOOL EXECUTION',
    'WRITE CONFIRM',
    'APPROVALS',
    'AUDIT',
    'ERROR HANDLING',
    'CONTEXT',
    'AR',
    'EN',
    'SECURITY',
    'TENANT ISOLATION',
    'PROCUREMENT REGRESSION',
    'INVENTORY REGRESSION',
    'SUPPLIER REGRESSION',
];
echo "\n=== PHASE 9 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
