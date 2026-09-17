<?php
declare(strict_types=1);

/**
 * Phase 12 — Logistics Domain + End-to-End Operational Intelligence.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase12-tests.php
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
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\InventoryToolExecutor;
use Rateb\App\Services\LogisticsToolExecutor;
use Rateb\App\Services\LogisticsToolRegistry;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\SalesToolExecutor;
use Rateb\App\Services\SalesToolRegistry;
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

final class Phase12MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase12-test', $perms, $sa, $modules);
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
$erp = new ErpAgent($config, new Phase12MockLlm());

// LOGISTICS TOOLS
$logTools = LogisticsToolRegistry::getTools();
$need = [
    'list_logistics_shipments', 'get_logistics_shipment', 'list_logistics_delivery_orders',
    'list_logistics_trips', 'analyze_logistics', 'get_logistics_operational_guidance',
    'get_logistics_crm_links', 'get_logistics_sales_links', 'get_logistics_inventory_links',
    'get_logistics_procurement_links', 'get_logistics_supplier_links', 'analyze_logistics_end_to_end',
];
$missing = array_diff($need, array_keys($logTools));
$writes = array_keys(array_filter($logTools, static fn($t) => !empty($t['write'])));
$noLogisticsAgent = !class_exists('Rateb\\App\\Services\\LogisticsAgent');
$toolsOk = $missing === []
    && $writes === []
    && $noLogisticsAgent
    && ErpDomainRegistry::isActive('logistics')
    && !ErpDomainRegistry::isReservedFuture('logistics')
    && ErpToolRegistry::domainForTool('analyze_logistics') === 'logistics'
    && ErpToolRegistry::domainForTool('analyze_crm') === 'crm'
    && (ErpDomainRegistry::resolve('logistics')['runtime'] ?? '') === ProcurementAgent::class;
$toolsOk ? $pass('LOGISTICS TOOLS') : $fail('LOGISTICS TOOLS', 'missing=' . implode(',', $missing));

// LOGISTICS READ
$readPolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_logistics_shipments',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p12-read',
    'write_confirmed' => false,
], $ctx);
$readRes = LogisticsToolExecutor::execute('list_logistics_shipments', ['limit' => 5], $ctx);
$dos = LogisticsToolExecutor::execute('list_logistics_delivery_orders', ['limit' => 5], $ctx);
$trips = LogisticsToolExecutor::execute('list_logistics_trips', ['limit' => 5], $ctx);
$readOk = !empty($readPolicy['allowed'])
    && !empty($readRes['success']) && is_array($readRes['data'] ?? null)
    && !empty($dos['success']) && !empty($trips['success']);
if ($readOk && $readRes['data'] !== []) {
    $sid = (int) ($readRes['data'][0]['id'] ?? 0);
    if ($sid > 0) {
        $got = LogisticsToolExecutor::execute('get_logistics_shipment', ['id' => $sid], $ctx);
        $readOk = !empty($got['success']) && (int) ($got['data']['id'] ?? 0) === $sid;
    }
} else {
    $missingGet = LogisticsToolExecutor::execute('get_logistics_shipment', ['id' => 999999991], $ctx);
    $readOk = $readOk && empty($missingGet['success']);
}
$readOk ? $pass('LOGISTICS READ') : $fail('LOGISTICS READ');

// LOGISTICS ANALYSIS
$analysis = LogisticsToolExecutor::execute('analyze_logistics', ['limit' => 10], $ctx);
$guide = LogisticsToolExecutor::execute('get_logistics_operational_guidance', ['limit' => 5], $ctx);
$analysisOk = !empty($analysis['success'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant'
    && isset($analysis['data']['summary'], $analysis['data']['follow_up'])
    && !empty($guide['success'])
    && isset($guide['data']['actions']);
$analysisOk ? $pass('LOGISTICS ANALYSIS') : $fail('LOGISTICS ANALYSIS');

// LOGISTICS PERMISSIONS
$noPerm = $makeCtx([], false, $companyId, ['logistics'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_logistics_shipments',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p12-perm',
    'write_confirmed' => false,
], $noPerm);
$noMod = $makeCtx($fullPerms, true, $companyId, ['procurement'], 'en');
$modDenied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_logistics',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p12-mod',
    'write_confirmed' => false,
], $noMod);
(!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$modDenied['allowed'] && ($modDenied['error_code'] ?? '') === 'module_not_entitled')
    ? $pass('LOGISTICS PERMISSIONS') : $fail('LOGISTICS PERMISSIONS');

// LOGISTICS DATA ISOLATION
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$logIso = false;
if ($otherCompany > 0) {
    $foreign = 0;
    try {
        $foreign = (int) $pdo->query(
            'SELECT id FROM rateb_logistics_shipments WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
    } catch (Throwable $e) {
        $foreign = 0;
    }
    if ($foreign > 0) {
        $iso = LogisticsToolExecutor::execute('get_logistics_shipment', ['id' => $foreign], $ctx);
        $logIso = empty($iso['success']) && ($iso['error_code'] ?? '') === 'shipment_not_found';
    } else {
        $logIso = true;
    }
} else {
    $iso = LogisticsToolExecutor::execute('get_logistics_shipment', ['id' => 999999991], $ctx);
    $logIso = empty($iso['success']);
}
$logIso ? $pass('LOGISTICS DATA ISOLATION') : $fail('LOGISTICS DATA ISOLATION');

// Cross-domain links
$crmL = LogisticsToolExecutor::execute('get_logistics_crm_links', ['limit' => 10], $ctx);
$salesL = LogisticsToolExecutor::execute('get_logistics_sales_links', ['limit' => 10], $ctx);
$invL = LogisticsToolExecutor::execute('get_logistics_inventory_links', ['limit' => 10], $ctx);
$procL = LogisticsToolExecutor::execute('get_logistics_procurement_links', ['limit' => 10], $ctx);
$supL = LogisticsToolExecutor::execute('get_logistics_supplier_links', ['limit' => 10], $ctx);
(!empty($crmL['success']) && isset($crmL['data']['links']) && ($crmL['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('LOGISTICS ↔ CRM') : $fail('LOGISTICS ↔ CRM');
(!empty($salesL['success']) && isset($salesL['data']['links']))
    ? $pass('LOGISTICS ↔ SALES') : $fail('LOGISTICS ↔ SALES');
(!empty($invL['success']) && isset($invL['data']['links']))
    ? $pass('LOGISTICS ↔ INVENTORY') : $fail('LOGISTICS ↔ INVENTORY');
(!empty($procL['success']) && isset($procL['data']['links']))
    ? $pass('LOGISTICS ↔ PROCUREMENT') : $fail('LOGISTICS ↔ PROCUREMENT');
(!empty($supL['success']) && isset($supL['data']['links']))
    ? $pass('LOGISTICS ↔ SUPPLIER') : $fail('LOGISTICS ↔ SUPPLIER');

// END-TO-END + ORCHESTRATION + MULTI-TOOL
$crossMsg = 'ما المبيعات التي تواجه مشكلة في التسليم وما علاقتها بالمخزون والمشتريات والموردين والعملاء؟';
$intent = ErpOrchestrationPlanner::detectIntent($crossMsg, $ctx, null);
$reqCross = 'p12-cross-' . bin2hex(random_bytes(3));
$cross = $erp->process(['message' => $crossMsg, 'request_id' => $reqCross], $ctx);
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
$e2e = LogisticsToolExecutor::execute('analyze_logistics_end_to_end', ['limit' => 10], $ctx);

$e2eOk = ($intent['mode'] ?? '') === 'cross'
    && in_array('logistics', $intent['domains'] ?? [], true)
    && count($intent['domains'] ?? []) >= 2
    && ($cross['domain'] ?? '') === ErpAgent::MODE_CROSS
    && ($intel['data_source'] ?? '') === 'live_tenant'
    && !empty($e2e['success'])
    && isset($e2e['data']['chain'], $e2e['data']['domains'])
    && in_array('logistics', $e2e['data']['domains'] ?? [], true)
    && in_array('sales', $e2e['data']['domains'] ?? [], true)
    && in_array('crm', $e2e['data']['domains'] ?? [], true)
    && !in_array('accounting', ErpDomainRegistry::getReservedFutureDomains(), true)
    && ErpDomainRegistry::isActive('accounting')
    && !in_array('logistics', ErpDomainRegistry::getReservedFutureDomains(), true);
$e2eOk ? $pass('END-TO-END INTELLIGENCE') : $fail('END-TO-END INTELLIGENCE');

(($orch['mode'] ?? '') === 'cross' && count($plan) >= 2 && count($toolCalls) >= 2 && count($domainsUsed) >= 1)
    ? $pass('ORCHESTRATION') : $fail('ORCHESTRATION');
count($uniqueTools) >= 2 ? $pass('MULTI-TOOL EXECUTION') : $fail('MULTI-TOOL EXECUTION');

// WRITE CONFIRM
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase12 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p12-wc',
    'write_confirmed' => false,
], $ctx);
$writeInPlan = false;
foreach ($plan as $step) {
    $t = ErpToolRegistry::getTool((string) ($step['tool'] ?? ''));
    if (!empty($t['write'])) {
        $writeInPlan = true;
    }
}
$logWrites = array_filter(LogisticsToolRegistry::getTools(), static fn($t) => !empty($t['write']));
(!$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required'
    && !$writeInPlan && $logWrites === [])
    ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// APPROVALS
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx);
!empty($appr['success']) ? $pass('APPROVALS') : $fail('APPROVALS');

// AUDIT
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($reqCross) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
$auditPlan = (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqCross)
    . " AND tool_name = 'orchestration_plan' AND company_id = " . (int) $companyId
)->fetchColumn();
($auditRows >= 2 && $auditPlan >= 1 && !empty($cross['audit']))
    ? $pass('AUDIT') : $fail('AUDIT', (string) $auditRows);

// ERROR HANDLING
$partialCtx = $makeCtx($fullPerms, true, $companyId, ['procurement', 'suppliers', 'inventory', 'pos', 'crm'], 'en');
$errRun = $erp->process([
    'message' => $crossMsg,
    'request_id' => 'p12-err-' . bin2hex(random_bytes(3)),
], $partialCtx);
$errIntent = ErpOrchestrationPlanner::detectIntent($crossMsg, $partialCtx, null);
$deniedLog = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_logistics',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p12-err-deny',
    'write_confirmed' => false,
], $makeCtx(['procurement.view'], false, $companyId, ['logistics', 'procurement'], 'en'));
(!in_array('logistics', $errIntent['domains'] ?? [], true)
    && !$deniedLog['allowed']
    && ($errRun['agent'] ?? '') === ErpAgent::AGENT_ID)
    ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'شحنات وتسليم'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
(count($dirty) === 1 && $dirty[0]['content'] === 'شحنات وتسليم'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId)
    && $ctx->moduleEnabled('logistics'))
    ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_tool_analyze_logistics');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'حلل الشحنات والتسليم والمبيعات والمخزون.',
    'request_id' => 'p12-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_tool_analyze_logistics');
$enRun = $erp->process([
    'message' => 'Analyze delayed shipments, deliveries, sales and inventory issues.',
    'request_id' => 'p12-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && (mb_strpos($arLabel, 'لوجست') !== false || stripos($arLabel, 'Logistics') !== false)
    && is_string($arRun['response'] ?? null) && mb_strpos((string) $arRun['response'], 'ملخص') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'Logistics') !== false
    && is_string($enRun['response'] ?? null) && stripos((string) $enRun['response'], 'Operational') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p12-bad',
    'write_confirmed' => true,
], $ctx);
(!$denied['allowed'] && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed')
    ? $pass('SECURITY') : $fail('SECURITY');

// TENANT
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_logistics_shipments',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p12-mismatch',
    'write_confirmed' => false,
], $ctx);
($logIso && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch')
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS
!empty(CrmToolExecutor::execute('analyze_crm', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('crm')
    ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');

!empty(SalesToolExecutor::execute('analyze_sales', ['limit' => 5], $ctx)['success'])
    && SalesToolRegistry::isAllowed('analyze_sales')
    && ErpDomainRegistry::isActive('sales')
    ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');

!empty(ProcurementToolExecutor::execute('summarize_procurement', [], $ctx)['success'])
    && ErpDomainRegistry::isActive('procurement')
    ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

!empty(InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('inventory')
    ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');

!empty(SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 5], $ctx)['success'])
    && SupplierToolRegistry::isAllowed('analyze_suppliers')
    ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');

!empty(LogisticsToolExecutor::execute('analyze_logistics', ['limit' => 5], $ctx)['success'])
    && !empty(LogisticsToolExecutor::execute('analyze_logistics_end_to_end', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('logistics')
    && count(ErpDomainRegistry::activeDomainIds()) === 8
    && $noLogisticsAgent
    ? $pass('LOGISTICS REGRESSION') : $fail('LOGISTICS REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'LOGISTICS READ', 'LOGISTICS ANALYSIS', 'LOGISTICS TOOLS', 'LOGISTICS PERMISSIONS', 'LOGISTICS DATA ISOLATION',
    'LOGISTICS ↔ CRM', 'LOGISTICS ↔ SALES', 'LOGISTICS ↔ INVENTORY', 'LOGISTICS ↔ PROCUREMENT', 'LOGISTICS ↔ SUPPLIER',
    'END-TO-END INTELLIGENCE', 'ORCHESTRATION', 'MULTI-TOOL EXECUTION',
    'WRITE CONFIRM', 'APPROVALS', 'AUDIT', 'ERROR HANDLING', 'CONTEXT',
    'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'CRM REGRESSION', 'SALES REGRESSION', 'PROCUREMENT REGRESSION', 'INVENTORY REGRESSION',
    'SUPPLIER REGRESSION', 'LOGISTICS REGRESSION',
];
echo "\n=== PHASE 12 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
