<?php
declare(strict_types=1);

/**
 * Phase 11 — CRM / Customer Domain + Full Commercial Intelligence.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase11-tests.php
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
use Rateb\App\Services\CrmToolRegistry;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpOrchestrationPlanner;
use Rateb\App\Services\ErpToolRegistry;
use Rateb\App\Services\InventoryToolExecutor;
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

final class Phase11MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase11-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
    'pos.view', 'pos.manage', 'crm.view', 'crm.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$erp = new ErpAgent($config, new Phase11MockLlm());

// CRM TOOLS
$crmTools = CrmToolRegistry::getTools();
$need = [
    'list_crm_customers', 'get_crm_customer', 'list_crm_leads', 'list_crm_opportunities',
    'list_crm_followups', 'analyze_crm', 'get_crm_operational_guidance',
    'get_crm_sales_links', 'get_crm_inventory_links', 'get_crm_procurement_links',
    'get_crm_supplier_links', 'analyze_crm_commercial_intelligence',
];
$missing = array_diff($need, array_keys($crmTools));
$writes = array_keys(array_filter($crmTools, static fn($t) => !empty($t['write'])));
$noCrmAgent = !class_exists('Rateb\\App\\Services\\CrmAgent') && !class_exists('Rateb\\App\\Services\\CustomerAgent');
$toolsOk = $missing === []
    && $writes === []
    && $noCrmAgent
    && ErpDomainRegistry::isActive('crm')
    && !ErpDomainRegistry::isReservedFuture('crm')
    && ErpToolRegistry::domainForTool('analyze_crm') === 'crm'
    && ErpToolRegistry::domainForTool('analyze_sales') === 'sales'
    && (ErpDomainRegistry::resolve('crm')['runtime'] ?? '') === ProcurementAgent::class;
$toolsOk ? $pass('CRM TOOLS') : $fail('CRM TOOLS', 'missing=' . implode(',', $missing));

// CRM READ
$readPolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_crm_customers',
    'arguments' => ['limit' => 5],
    'request_company_id' => null,
    'request_id' => 'p11-read',
    'write_confirmed' => false,
], $ctx);
$readRes = CrmToolExecutor::execute('list_crm_customers', ['limit' => 5], $ctx);
$leads = CrmToolExecutor::execute('list_crm_leads', ['limit' => 5], $ctx);
$opps = CrmToolExecutor::execute('list_crm_opportunities', ['limit' => 5], $ctx);
$fu = CrmToolExecutor::execute('list_crm_followups', ['limit' => 5], $ctx);
$readOk = !empty($readPolicy['allowed'])
    && !empty($readRes['success']) && is_array($readRes['data'] ?? null)
    && !empty($leads['success']) && !empty($opps['success']) && !empty($fu['success']);
if ($readOk && $readRes['data'] !== []) {
    $cid = (int) ($readRes['data'][0]['id'] ?? 0);
    if ($cid > 0) {
        $got = CrmToolExecutor::execute('get_crm_customer', ['id' => $cid], $ctx);
        $readOk = !empty($got['success']) && (int) ($got['data']['id'] ?? 0) === $cid;
    }
} else {
    $missingGet = CrmToolExecutor::execute('get_crm_customer', ['id' => 999999991], $ctx);
    $readOk = $readOk && empty($missingGet['success']);
}
$readOk ? $pass('CRM READ') : $fail('CRM READ');

// CRM ANALYSIS
$analysis = CrmToolExecutor::execute('analyze_crm', ['limit' => 10], $ctx);
$guide = CrmToolExecutor::execute('get_crm_operational_guidance', ['limit' => 5], $ctx);
$analysisOk = !empty($analysis['success'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant'
    && isset($analysis['data']['summary'], $analysis['data']['follow_up'])
    && !empty($guide['success'])
    && isset($guide['data']['actions']);
$analysisOk ? $pass('CRM ANALYSIS') : $fail('CRM ANALYSIS');

// CRM PERMISSIONS
$noPerm = $makeCtx([], false, $companyId, ['crm'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_crm_customers',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p11-perm',
    'write_confirmed' => false,
], $noPerm);
$noMod = $makeCtx($fullPerms, true, $companyId, ['procurement'], 'en');
$modDenied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_crm',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p11-mod',
    'write_confirmed' => false,
], $noMod);
(!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$modDenied['allowed'] && ($modDenied['error_code'] ?? '') === 'module_not_entitled')
    ? $pass('CRM PERMISSIONS') : $fail('CRM PERMISSIONS');

// CUSTOMER DATA ISOLATION (alias checked with TENANT later; keep explicit)
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$custIso = false;
if ($otherCompany > 0) {
    $foreign = (int) $pdo->query(
        'SELECT id FROM rateb_customers WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreign > 0) {
        $iso = CrmToolExecutor::execute('get_crm_customer', ['id' => $foreign], $ctx);
        $custIso = empty($iso['success']) && ($iso['error_code'] ?? '') === 'customer_not_found';
    } else {
        $custIso = true;
    }
} else {
    $iso = CrmToolExecutor::execute('get_crm_customer', ['id' => 999999991], $ctx);
    $custIso = empty($iso['success']);
}
$custIso ? $pass('CUSTOMER DATA ISOLATION') : $fail('CUSTOMER DATA ISOLATION');

// CRM links
$salesL = CrmToolExecutor::execute('get_crm_sales_links', ['limit' => 10], $ctx);
$invL = CrmToolExecutor::execute('get_crm_inventory_links', ['limit' => 10], $ctx);
$procL = CrmToolExecutor::execute('get_crm_procurement_links', ['limit' => 10], $ctx);
$supL = CrmToolExecutor::execute('get_crm_supplier_links', ['limit' => 10], $ctx);
(!empty($salesL['success']) && isset($salesL['data']['links']) && ($salesL['data']['data_source'] ?? '') === 'live_tenant')
    ? $pass('CRM ↔ SALES') : $fail('CRM ↔ SALES');
(!empty($invL['success']) && isset($invL['data']['links']))
    ? $pass('CRM ↔ INVENTORY') : $fail('CRM ↔ INVENTORY');
(!empty($procL['success']) && isset($procL['data']['links']))
    ? $pass('CRM ↔ PROCUREMENT') : $fail('CRM ↔ PROCUREMENT');
(!empty($supL['success']) && isset($supL['data']['links']))
    ? $pass('CRM ↔ SUPPLIER') : $fail('CRM ↔ SUPPLIER');

// FULL COMMERCIAL + ORCHESTRATION + MULTI-TOOL
$crossMsg = 'حلل العملاء الذين لديهم طلبات بيع ومشاكل في توفر المخزون وعلاقتها بالمشتريات والموردين.';
$intent = ErpOrchestrationPlanner::detectIntent($crossMsg, $ctx, null);
$reqCross = 'p11-cross-' . bin2hex(random_bytes(3));
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
$commercial = CrmToolExecutor::execute('analyze_crm_commercial_intelligence', ['limit' => 10], $ctx);

$fullOk = ($intent['mode'] ?? '') === 'cross'
    && in_array('crm', $intent['domains'] ?? [], true)
    && count($intent['domains'] ?? []) >= 2
    && ($cross['domain'] ?? '') === ErpAgent::MODE_CROSS
    && ($intel['data_source'] ?? '') === 'live_tenant'
    && !empty($commercial['success'])
    && isset($commercial['data']['chain'], $commercial['data']['domains'])
    && in_array('crm', $commercial['data']['domains'] ?? [], true)
    && in_array('sales', $commercial['data']['domains'] ?? [], true)
    && in_array('logistics', ErpDomainRegistry::getReservedFutureDomains(), true);
$fullOk ? $pass('FULL COMMERCIAL INTELLIGENCE') : $fail('FULL COMMERCIAL INTELLIGENCE');

(($orch['mode'] ?? '') === 'cross' && count($plan) >= 2 && count($toolCalls) >= 2 && count($domainsUsed) >= 2)
    ? $pass('ORCHESTRATION') : $fail('ORCHESTRATION');
count($uniqueTools) >= 2 ? $pass('MULTI-TOOL EXECUTION') : $fail('MULTI-TOOL EXECUTION');

// WRITE CONFIRM
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase11 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p11-wc',
    'write_confirmed' => false,
], $ctx);
$writeInPlan = false;
foreach ($plan as $step) {
    $t = ErpToolRegistry::getTool((string) ($step['tool'] ?? ''));
    if (!empty($t['write'])) {
        $writeInPlan = true;
    }
}
$crmWrites = array_filter(CrmToolRegistry::getTools(), static fn($t) => !empty($t['write']));
(!$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required'
    && !$writeInPlan && $crmWrites === [])
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
$partialCtx = $makeCtx($fullPerms, true, $companyId, ['procurement', 'suppliers', 'inventory', 'pos'], 'en');
$errRun = $erp->process([
    'message' => $crossMsg,
    'request_id' => 'p11-err-' . bin2hex(random_bytes(3)),
], $partialCtx);
$errIntent = ErpOrchestrationPlanner::detectIntent($crossMsg, $partialCtx, null);
$deniedCrm = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_crm',
    'arguments' => ['limit' => 3],
    'request_company_id' => null,
    'request_id' => 'p11-err-deny',
    'write_confirmed' => false,
], $makeCtx(['procurement.view'], false, $companyId, ['crm', 'procurement'], 'en'));
(!in_array('crm', $errIntent['domains'] ?? [], true)
    && !$deniedCrm['allowed']
    && ($errRun['agent'] ?? '') === ErpAgent::AGENT_ID)
    ? $pass('ERROR HANDLING') : $fail('ERROR HANDLING');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'عملاء ومبيعات'],
    ['role' => 'user', 'content' => '{"company_id":9}'],
]);
(count($dirty) === 1 && $dirty[0]['content'] === 'عملاء ومبيعات'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId)
    && $ctx->moduleEnabled('crm'))
    ? $pass('CONTEXT') : $fail('CONTEXT');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arLabel = __('ai_tool_analyze_crm');
$ctxAr = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$arRun = $erp->process([
    'message' => 'حلل العملاء والمبيعات والمخزون والمشتريات.',
    'request_id' => 'p11-ar-' . bin2hex(random_bytes(3)),
], $ctxAr);
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enLabel = __('ai_tool_analyze_crm');
$enRun = $erp->process([
    'message' => 'Analyze customers, sales, inventory and procurement issues.',
    'request_id' => 'p11-en-' . bin2hex(random_bytes(3)),
], $ctx);
$_SESSION['rateb_locale'] = $prev;
(is_string($arLabel) && stripos($arLabel, 'CRM') !== false
    && is_string($arRun['response'] ?? null) && mb_strpos((string) $arRun['response'], 'ملخص') !== false)
    ? $pass('AR') : $fail('AR', (string) $arLabel);
(is_string($enLabel) && stripos($enLabel, 'CRM') !== false
    && is_string($enRun['response'] ?? null) && stripos((string) $enRun['response'], 'Operational') !== false)
    ? $pass('EN') : $fail('EN', (string) $enLabel);

// SECURITY
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p11-bad',
    'write_confirmed' => true,
], $ctx);
(!$denied['allowed'] && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed')
    ? $pass('SECURITY') : $fail('SECURITY');

// TENANT
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_crm_customers',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p11-mismatch',
    'write_confirmed' => false,
], $ctx);
($custIso && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch')
    ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// REGRESSIONS
!empty(ProcurementToolExecutor::execute('summarize_procurement', [], $ctx)['success'])
    && ErpDomainRegistry::isActive('procurement')
    ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

!empty(InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('inventory')
    ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');

!empty(SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 5], $ctx)['success'])
    && SupplierToolRegistry::isAllowed('analyze_suppliers')
    ? $pass('SUPPLIER REGRESSION') : $fail('SUPPLIER REGRESSION');

!empty(SalesToolExecutor::execute('analyze_sales', ['limit' => 5], $ctx)['success'])
    && SalesToolRegistry::isAllowed('analyze_sales')
    && ErpDomainRegistry::isActive('sales')
    ? $pass('SALES REGRESSION') : $fail('SALES REGRESSION');

!empty(CrmToolExecutor::execute('analyze_crm', ['limit' => 5], $ctx)['success'])
    && !empty(CrmToolExecutor::execute('analyze_crm_commercial_intelligence', ['limit' => 5], $ctx)['success'])
    && ErpDomainRegistry::isActive('crm')
    && count(ErpDomainRegistry::activeDomainIds()) === 5
    && $noCrmAgent
    ? $pass('CRM REGRESSION') : $fail('CRM REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'CRM READ', 'CRM ANALYSIS', 'CRM TOOLS', 'CRM PERMISSIONS', 'CUSTOMER DATA ISOLATION',
    'CRM ↔ SALES', 'CRM ↔ INVENTORY', 'CRM ↔ PROCUREMENT', 'CRM ↔ SUPPLIER',
    'FULL COMMERCIAL INTELLIGENCE', 'ORCHESTRATION', 'MULTI-TOOL EXECUTION',
    'WRITE CONFIRM', 'APPROVALS', 'AUDIT', 'ERROR HANDLING', 'CONTEXT',
    'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'PROCUREMENT REGRESSION', 'INVENTORY REGRESSION', 'SUPPLIER REGRESSION',
    'SALES REGRESSION', 'CRM REGRESSION',
];
echo "\n=== PHASE 11 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
