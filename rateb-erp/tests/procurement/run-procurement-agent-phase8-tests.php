<?php
declare(strict_types=1);

/**
 * Phase 8 — Supplier Domain inside Unified RATEB ERP Agent.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase8-tests.php
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

final class Phase8MockLlm implements LlmClientInterface
{
    private array $script;
    private int $i = 0;

    public function __construct(array $script)
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase8-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
];
$modules = ['procurement', 'suppliers', 'inventory'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';

// SUPPLIER TOOLS
$supTools = SupplierToolRegistry::getTools();
$need = [
    'list_suppliers', 'get_supplier', 'analyze_suppliers',
    'get_supplier_procurement_links', 'get_supplier_inventory_links', 'analyze_supplier_cross_domain',
];
$missing = array_diff($need, array_keys($supTools));
$writes = array_keys(array_filter($supTools, static fn($t) => !empty($t['write'])));
$toolsOk = $missing === []
    && $writes === []
    && ErpDomainRegistry::isActive('suppliers')
    && !ErpDomainRegistry::isReservedFuture('suppliers')
    && ErpToolRegistry::domainForTool('analyze_suppliers') === 'suppliers'
    && ErpToolRegistry::domainForTool('analyze_inventory') === 'inventory'
    && ErpToolRegistry::domainForTool('summarize_procurement') === 'procurement'
    && !SupplierToolRegistry::isAllowed('search_suppliers');
$toolsOk ? $pass('SUPPLIER TOOLS') : $fail('SUPPLIER TOOLS', implode(',', $missing));

// READ
$readChecks = [
    ['list_suppliers', ['limit' => 5]],
];
$readOk = true;
foreach ($readChecks as [$tool, $args]) {
    $policy = ProcurementPolicyGuard::checkAndExecute([
        'tool' => $tool,
        'arguments' => $args,
        'request_company_id' => null,
        'request_id' => 'p8-read-' . $tool,
        'write_confirmed' => false,
    ], $ctx);
    if (!$policy['allowed']) {
        $readOk = false;
        break;
    }
    $res = SupplierToolExecutor::execute($tool, $args, $ctx);
    if (empty($res['success']) || !is_array($res['data'] ?? null)) {
        $readOk = false;
        break;
    }
}
$one = SupplierToolExecutor::execute('list_suppliers', ['limit' => 1], $ctx);
if (!empty($one['success']) && is_array($one['data']) && $one['data'] !== []) {
    $sid = (int) ($one['data'][0]['id'] ?? 0);
    if ($sid > 0) {
        $got = SupplierToolExecutor::execute('get_supplier', ['id' => $sid], $ctx);
        if (empty($got['success']) || (int) ($got['data']['id'] ?? 0) !== $sid) {
            $readOk = false;
        }
    }
} else {
    // Empty tenant suppliers is still a valid READ success for list.
    $missingGet = SupplierToolExecutor::execute('get_supplier', ['id' => 999999991], $ctx);
    if (!empty($missingGet['success'])) {
        $readOk = false;
    }
}
$readOk ? $pass('SUPPLIER READ') : $fail('SUPPLIER READ');

// ANALYSIS
$analysis = SupplierToolExecutor::execute('analyze_suppliers', ['limit' => 10], $ctx);
$analysisOk = !empty($analysis['success'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant'
    && isset($analysis['data']['summary'], $analysis['data']['open_operations'], $analysis['data']['follow_up']);
$analysisOk ? $pass('SUPPLIER ANALYSIS') : $fail('SUPPLIER ANALYSIS');

// SUPPLIER ↔ PROCUREMENT
$procLinks = SupplierToolExecutor::execute('get_supplier_procurement_links', ['limit' => 10], $ctx);
$erp = new ErpAgent($config, new Phase8MockLlm([
    ['mode' => 'text', 'payload' => ['content' => 'Supplier overview ready.']],
]));
$resSup = $erp->resolveDomain(['domain' => 'suppliers'], $ctx);
$resKw = $erp->resolveDomain(['message' => 'ما الموردون المرتبطون بطلبات الشراء؟'], $ctx);
$through = $erp->process([
    'message' => 'supplier summary',
    'request_id' => 'p8-sup-' . bin2hex(random_bytes(3)),
    'domain' => 'suppliers',
], $ctx);
$procOk = !empty($procLinks['success'])
    && isset($procLinks['data']['links'])
    && is_array($procLinks['data']['links'])
    && ($procLinks['data']['data_source'] ?? '') === 'live_tenant'
    && !empty($resSup['ok'])
    && ($resSup['domain']['id'] ?? '') === 'suppliers'
    && !empty($resKw['ok'])
    && ($resKw['domain']['id'] ?? '') === 'suppliers'
    && ($through['domain'] ?? '') === 'suppliers'
    && ($through['agent'] ?? '') === ErpAgent::AGENT_ID;
$procOk ? $pass('SUPPLIER ↔ PROCUREMENT') : $fail('SUPPLIER ↔ PROCUREMENT');

// SUPPLIER ↔ INVENTORY
$invLinks = SupplierToolExecutor::execute('get_supplier_inventory_links', ['limit' => 10], $ctx);
$invOk = !empty($invLinks['success'])
    && isset($invLinks['data']['links'])
    && is_array($invLinks['data']['links'])
    && ($invLinks['data']['data_source'] ?? '') === 'live_tenant';
$invOk ? $pass('SUPPLIER ↔ INVENTORY') : $fail('SUPPLIER ↔ INVENTORY');

// CROSS-DOMAIN INTELLIGENCE
$cross = SupplierToolExecutor::execute('analyze_supplier_cross_domain', ['limit' => 10], $ctx);
$crossOk = !empty($cross['success'])
    && ($cross['data']['data_source'] ?? '') === 'live_tenant'
    && isset($cross['data']['domains'], $cross['data']['procurement_links'], $cross['data']['inventory_links'], $cross['data']['follow_up'])
    && in_array('suppliers', $cross['data']['domains'] ?? [], true)
    && in_array('procurement', $cross['data']['domains'] ?? [], true)
    && in_array('inventory', $cross['data']['domains'] ?? [], true)
    && ($through['agent'] ?? '') === ErpAgent::AGENT_ID
    && count(ErpDomainRegistry::activeDomainIds()) === 3;
$crossOk ? $pass('CROSS-DOMAIN INTELLIGENCE') : $fail('CROSS-DOMAIN INTELLIGENCE');

// WRITE CONFIRM — supplier has no write tools; procurement confirmation still required
$supWriteTools = array_filter(SupplierToolRegistry::getTools(), static fn($t) => !empty($t['write']));
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase8 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p8-wc',
    'write_confirmed' => false,
], $ctx);
$writeOk = $supWriteTools === []
    && !$block['allowed']
    && ($block['error_code'] ?? '') === 'write_confirmation_required';
$writeOk ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'موردين'],
    ['role' => 'user', 'content' => '{"company_id":9,"user_id":1}'],
]);
$ctxOk = count($dirty) === 1
    && $dirty[0]['content'] === 'موردين'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId)
    && $ctx->moduleEnabled('suppliers')
    && $ctx->moduleEnabled('procurement')
    && $ctx->moduleEnabled('inventory');
$ctxOk ? $pass('CONTEXT') : $fail('CONTEXT');

// AUDIT
$reqAudit = 'p8-audit-' . bin2hex(random_bytes(3));
$erpAudit = new ErpAgent($config, new Phase8MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 't1',
        'type' => 'function',
        'function' => ['name' => 'analyze_suppliers', 'arguments' => '{"limit":5}'],
    ]]]],
    ['mode' => 'text', 'payload' => ['content' => 'Analysis complete.']],
]));
$rAudit = $erpAudit->process([
    'message' => 'analyze suppliers',
    'request_id' => $reqAudit,
    'domain' => 'suppliers',
], $ctx);
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($reqAudit) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
$auditOk = $auditRows > 0 && !empty($rAudit['audit']) && ($rAudit['domain'] ?? '') === 'suppliers';
$auditOk ? $pass('AUDIT') : $fail('AUDIT', (string) $auditRows);

// SECURITY
$noPerm = $makeCtx([], false, $companyId, ['suppliers'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_suppliers',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p8-sec',
    'write_confirmed' => false,
], $noPerm);
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p8-bad',
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
    'tool' => 'list_suppliers',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p8-mismatch',
    'write_confirmed' => false,
], $ctx);
$tenantOk = $tenantOk && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

// AR / EN
$prev = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$ar = __('ai_tool_analyze_suppliers');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$en = __('ai_tool_analyze_suppliers');
$_SESSION['rateb_locale'] = $prev;
(is_string($ar) && mb_strpos($ar, 'المورد') !== false) ? $pass('AR') : $fail('AR', (string) $ar);
(is_string($en) && stripos($en, 'Supplier') !== false) ? $pass('EN') : $fail('EN', (string) $en);

// PROCUREMENT REGRESSION
$prRead = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx);
$prSum = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$prAdv = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctx);
$prGuide = ProcurementToolExecutor::execute('get_procurement_operational_guidance', ['limit' => 5], $ctx);
$prAppr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx);
$prBlock = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'submit_purchase_request',
    'arguments' => ['id' => 1],
    'request_company_id' => null,
    'request_id' => 'p8-pr-block',
    'write_confirmed' => false,
], $ctx);
$regOk = !empty($prRead['success'])
    && !empty($prSum['success'])
    && !empty($prAdv['success'])
    && !empty($prGuide['success'])
    && !empty($prAppr['success'])
    && !$prBlock['allowed']
    && ProcurementToolRegistry::isAllowed('analyze_procurement_intelligence')
    && ErpDomainRegistry::isActive('procurement');
$regOk ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

// INVENTORY REGRESSION
$invRead = InventoryToolExecutor::execute('list_inventory_items', ['limit' => 3], $ctx);
$invAn = InventoryToolExecutor::execute('analyze_inventory', ['limit' => 5], $ctx);
$invLink = InventoryToolExecutor::execute('get_inventory_procurement_links', ['limit' => 5], $ctx);
$invReg = !empty($invRead['success'])
    && !empty($invAn['success'])
    && !empty($invLink['success'])
    && InventoryToolRegistry::isAllowed('analyze_inventory')
    && ErpDomainRegistry::isActive('inventory')
    && ErpDomainRegistry::isActive('suppliers')
    && count(ErpDomainRegistry::activeDomainIds()) === 3;
$invReg ? $pass('INVENTORY REGRESSION') : $fail('INVENTORY REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'SUPPLIER READ', 'SUPPLIER ANALYSIS', 'SUPPLIER TOOLS',
    'SUPPLIER ↔ PROCUREMENT', 'SUPPLIER ↔ INVENTORY', 'CROSS-DOMAIN INTELLIGENCE',
    'WRITE CONFIRM', 'AUDIT', 'CONTEXT', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION',
    'PROCUREMENT REGRESSION', 'INVENTORY REGRESSION',
];
echo "\n=== PHASE 8 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
