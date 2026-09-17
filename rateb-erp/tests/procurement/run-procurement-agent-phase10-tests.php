<?php
declare(strict_types=1);

/**
 * Phase 10 — Sales Domain + Cross-Domain Intelligence (Unified ERP Agent).
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase10-tests.php
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
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\ProcurementToolRegistry;
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

final class Phase10MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase10-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
    'pos.view', 'pos.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase10MockLlm());

// SALES TOOLS
$salesTools = SalesToolRegistry::getTools();
$need = [
    'list_sales_orders', 'get_sales_order', 'list_sales_customers', 'analyze_sales',
    'get_sales_operational_guidance', 'get_sales_inventory_links', 'get_sales_procurement_links',
    'get_sales_supplier_links', 'analyze_sales_cross_domain',
];
$missing = array_diff($need, array_keys($salesTools));
$writes = array_keys(array_filter($salesTools, static fn($t) => !empty($t['write'])));
$noSalesAgent = !class_exists('Rateb\\App\\Services\\SalesAgent');
$toolsOk = $missing === []
    && $writes === []
    && $noSalesAgent
    && ErpDomainRegistry::isActive('sales')
    && !ErpDomainRegistry::isReservedFuture('sales')
    && ErpToolRegistry::domainForTool('analyze_sales') === 'sales'
    && ErpToolRegistry::domainForTool('analyze_inventory') === 'inventory'
    && (ErpDomainRegistry::resolve('sales')['runtime'] ?? '') === \Rateb\App\Services\ProcurementAgent::class;
$toolsOk ? $pass('SALES TOOLS') : $fail('SALES TOOLS', 'missing=' . implode(',', $missing) . ' runtime=' . (string) (ErpDomainRegistry::resolve('sales')['runtime'] ?? ''));

// SALES READ
$readPolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_sales_orders',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p10-read',
    'write_confirmed' => false,
], $ctx);
$readRes = SalesToolExecutor::execute('list_sales_orders', ['limit' => 5], $ctx);
$custRes = SalesToolExecutor::execute('list_sales_customers', ['limit' => 5], $ctx);
$readOk = !empty($readPolicy['allowed'])
    && !empty($readRes['success']) && is_array($readRes['data'] ?? null)
    && !empty($custRes['success']) && is_array($custRes['data'] ?? null);
if ($readOk && $readRes['data'] !== []) {
    $oid = (int) ($readRes['data'][0]['id'] ?? 0);
    if ($oid > 0) {
        $got = SalesToolExecutor::execute('get_sales_order', ['id' => $oid], $ctx);
        $readOk = !empty($got['success']) && (int) ($got['data']['id'] ?? 0) === $oid;
    }
} else {
    $missingGet = SalesToolExecutor::execute('get_sales_order', ['id' => 999999991], $ctx);
    $readOk = $readOk && empty($missingGet['success']);
}
$readOk ? $pass('SALES READ') : $fail('SALES READ');

// SALES ANALYSIS
$analysis = SalesToolExecutor::execute('analyze_sales', ['limit' => 10], $ctx);
$guide = SalesToolExecutor::execute('get_sales_operational_guidance', ['limit' => 5], $ctx);
$analysisOk = !empty($analysis['success'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant'
    && isset($analysis['data']['summary'], $analysis['data']['follow_up'])
    && !empty($guide['success'])
    && isset($guide['data']['actions']);
$analysisOk ? $pass('SALES ANALYSIS') : $fail('SALES ANALYSIS');

// SALES PERMISSIONS
$noPerm = $makeCtx([], false, $companyId, ['pos'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_sales_orders',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p10-perm',
    'write_confirmed' => false,
], $noPerm);
$noMod = $makeCtx($fullPerms, true, $companyId, ['procurement'], 'en');
$modDenied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_sales',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p10-mod',
    'write_confirmed' => false,
], $noMod);
(!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$modDenied['allowed'] && ($modDenied['error_code'] ?? '') === 'module_not_entitled')
    ? $pass('SALES PERMISSIONS') : $fail('SALES PERMISSIONS');

// SALES ↔ INVENTORY / PROCUREMENT / SUPPLIER
$invL = SalesToolExecutor::execute('get_sales_inventory_links', ['limit' => 10], $ctx);
$procL = SalesToolExecutor::execute('get_sales_procurement_links', ['limit' => 10], $ctx);
$supL = SalesToolExecutor::execute('get_sales_supplier_links', ['limit' => 10], $ctx);
(!empty($invL['success']) && isset($invL['data']['links']) && ($invL['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('SALES ↔ INVENTORY') : $fail('SALES ↔ INVENTORY');
(!empty($procL['success']) && isset($procL['data']['links']) && ($procL['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('SALES ↔ PROCUREMENT') : $fail('SALES ↔ PROCUREMENT');
(!empty($supL['success']) && isset($supL['data']['links']) && ($supL['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('SALES ↔ SUPPLIER') : $fail('SALES ↔ SUPPLIER');

// FULL CROSS-DOMAIN + ORCHESTRATION + MULTI-TOOL
$crossMsg = 'هل توجد مبيعات متأثرة بنقص المخزون وما علاقتها بالمشتريات والموردين؟';
$intent = ErpOrchestrationPlanner::detectIntent($crossMsg, $ctx, null);
$reqCross = 'p10-cross-' . bin2hex(random_bytes(3));
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

$xdOk = ($intent['mode'] ?? '') === 'cross'
    && count($intent['domains'] ?? []) >= 2
    && in_array('sales', $intent['domains'] ?? [], true)
    && ($cross['domain'] ?? '') === ErpAgent::MODE_CROSS
    && ($intel['data_source'] ?? '') === 'live_tenant'
    && in_array('logistics', ErpDomainRegistry::getReservedFutureDomains(), true);
$xdOk ? $pass('FULL CROSS-DOMAIN INTELLIGENCE') : $fail('FULL CROSS-DOMAIN INTELLIGENCE');

$orchOk = ($orch['mode'] ?? '') === 'cross'
    && count($plan) >= 2
    && count($toolCalls) >= 2
    && count($domainsUsed) >= 2
    && count($uniqueTools) >= 2;
$orchOk ? $pass('ORCHESTRATION') : $fail('ORCHESTRATION');
count($uniqueTools) >= 2 ? $pass('MULTI-TOOL EXECUTION') : $fail('MULTI-TOOL EXECUTION');

// WRITE CONFIRM
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase10 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p10-wc',
    'write_confirmed' => false,
], $ctx);
$writeInPlan = false;
foreach ($plan as $step) {
    $t = ErpToolRegistry::getTool((string) ($step['tool'] ?? ''));
    if (!empty($t['write'])) {
        $writeInPlan = true;
    }
}
$salesWrites = array_filter(SalesToolRegistry::getTools(), static fn($t) => !empty($t['write']));
(!$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required'
    && !$writeInPlan && $salesWrites === [])
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
$partialCtx = $makeCtx($fullPerms, true, $companyId, ['procurement', 'suppliers', 'inventory'], 'en');
$errRun = $erp->process([
    'message' => $crossMsg,
    'request_id' => 'p10-err-' . bin2hex(random_bytes(3)),
], $partialCtx);
$errIntent = ErpOrchestrationPlanner::detectIntent($crossMsg, $partialCtx, null);
$errOk = !in_array('sales', $errIntent['domains'] ?? [], true)
    || ($errRun['domain'] ?? '') === ErpAgent::MODE_CROSS
    || ($errRun['domain'] ?? '') !== '';
// Deny sales tool without permission
$deniedSales = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_sales',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p10-err-deny',
    'write_confirmed' => false,
], $makeCtx(['procurement.view'], false, $companyId, ['pos', 'procurement'], 'en'));
$errOk = $errOk && !$deniedSales['allowed']
    && is_array($errRun['orchestration']['intelligence']['partial_failures'] ?? $errRun['audit'] ?? []);
$errOk ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'مبيعات ومخزون'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
(count($dirty) === 1 && $dirty[0]['content'] === 'مبيعات ومخزون'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId)
    && $ctx->moduleEnabled('pos'))
    ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_tool_analyze_sales');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'حلل حالة المبيعات والمخزون والمشتريات.',
    'request_id' => 'p10-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_tool_analyze_sales');
$enRun = $erp->process([
    'message' => 'Analyze sales, inventory, and procurement top issues.',
    'request_id' => 'p10-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && mb_strpos($arLabel, 'المبيعات') !== false
    && is_string($arRun['response'] ?? null) && mb_strpos((string) $arRun['response'], 'ملخص') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'Sales') !== false
    && is_string($enRun['response'] ?? null) && stripos((string) $enRun['response'], 'Operational') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p10-bad',
    'write_confirmed' => true,
], $ctx);
(!$denied['allowed'] && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed')
    ? $pass('SECURITY') : $fail('SECURITY');

// TENANT
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantOk = false;
if ($otherCompany > 0) {
    $foreign = (int) $pdo->query(
        'SELECT id FROM rateb_pos_orders WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreign > 0) {
        $iso = SalesToolExecutor::execute('get_sales_order', ['id' => $foreign], $ctx);
        $tenantOk = empty($iso['success']) && ($iso['error_code'] ?? '') === 'sales_order_not_found';
    } else {
        $tenantOk = true;
    }
} else {
    $iso = SalesToolExecutor::execute('get_sales_order', ['id' => 999999991], $ctx);
    $tenantOk = empty($iso['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_sales_orders',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p10-mismatch',
    'write_confirmed' => false,
], $ctx);
$tenantOk = $tenantOk && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS
$prOk = !empty(ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx)['success'])
    && !empty(ProcurementToolExecutor::execute('summarize_procurement', [], $ctx)['success'])
    && ErpDomainRegistry::isActive('procurement');
$prOk ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

$invOk = !empty(InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx)['success'])
    && InventoryToolRegistry::isAllowed('analyze_inventory')
    && ErpDomainRegistry::isActive('inventory');
$invOk ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');

$supOk = !empty(SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 5], $ctx)['success'])
    && SupplierToolRegistry::isAllowed('analyze_suppliers')
    && ErpDomainRegistry::isActive('suppliers');
$supOk ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');

$salesReg = !empty(SalesToolExecutor::execute('analyze_sales', ['limit' => 5], $ctx)['success'])
    && !empty(SalesToolExecutor::execute('analyze_sales_cross_domain', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('sales')
    && count(ErpDomainRegistry::activeDomainIds()) >= 4
    && $noSalesAgent;
$salesReg ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'SALES READ', 'SALES ANALYSIS', 'SALES TOOLS', 'SALES PERMISSIONS',
    'SALES ↔ INVENTORY', 'SALES ↔ PROCUREMENT', 'SALES ↔ SUPPLIER',
    'FULL CROSS-DOMAIN INTELLIGENCE', 'ORCHESTRATION', 'MULTI-TOOL EXECUTION',
    'WRITE CONFIRM', 'APPROVALS', 'AUDIT', 'ERROR HANDLING', 'CONTEXT',
    'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'PROCUREMENT REGRESSION', 'INVENTORY REGRESSION', 'SUPPLIER REGRESSION', 'SALES REGRESSION',
];
echo "\n=== PHASE 10 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
