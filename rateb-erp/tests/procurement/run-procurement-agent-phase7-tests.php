<?php
declare(strict_types=1);

/**
 * Phase 7 — Inventory Domain inside Unified RATEB ERP Agent.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase7-tests.php
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

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

final class Phase7MockLlm implements LlmClientInterface
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase7-test', $perms, $sa, $modules);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'inventory.manage',
];
$modules = ['procurement', 'suppliers', 'inventory'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';

// INVENTORY TOOLS registry
$invTools = InventoryToolRegistry::getTools();
$need = [
    'list_inventory_items', 'get_inventory_item', 'list_warehouses',
    'list_stock_movements', 'analyze_inventory', 'get_inventory_procurement_links',
];
$missing = array_diff($need, array_keys($invTools));
$writes = array_keys(array_filter($invTools, static fn($t) => !empty($t['write'])));
$toolsOk = $missing === []
    && $writes === []
    && ErpDomainRegistry::isActive('inventory')
    && !ErpDomainRegistry::isReservedFuture('inventory')
    && ErpToolRegistry::domainForTool('analyze_inventory') === 'inventory'
    && ErpToolRegistry::domainForTool('summarize_procurement') === 'procurement';
$toolsOk ? $pass('INVENTORY TOOLS') : $fail('INVENTORY TOOLS', implode(',', $missing));

// READ
$readChecks = [
    ['list_inventory_items', ['limit' => 5]],
    ['list_warehouses', ['limit' => 5]],
    ['list_stock_movements', ['limit' => 5]],
];
$readOk = true;
foreach ($readChecks as [$tool, $args]) {
    $policy = ProcurementPolicyGuard::checkAndExecute([
        'tool' => $tool,
        'arguments' => $args,
        'request_company_id' => null,
        'request_id' => 'p7-read-' . $tool,
        'write_confirmed' => false,
    ], $ctx);
    if (!$policy['allowed']) {
        $readOk = false;
        break;
    }
    $res = InventoryToolExecutor::execute($tool, $args, $ctx);
    if (empty($res['success']) || !is_array($res['data'] ?? null)) {
        $readOk = false;
        break;
    }
}
$readOk ? $pass('INVENTORY READ') : $fail('INVENTORY READ');

// ANALYSIS
$analysis = InventoryToolExecutor::execute('analyze_inventory', ['limit' => 10], $ctx);
$analysisOk = !empty($analysis['success'])
    && ($analysis['data']['data_source'] ?? '') === 'live_tenant'
    && isset($analysis['data']['summary'], $analysis['data']['low_stock'], $analysis['data']['available'], $analysis['data']['follow_up']);
$analysisOk ? $pass('INVENTORY ANALYSIS') : $fail('INVENTORY ANALYSIS');

// PROCUREMENT ↔ INVENTORY
$links = InventoryToolExecutor::execute('get_inventory_procurement_links', ['limit' => 10], $ctx);
$linkOk = !empty($links['success'])
    && isset($links['data']['links'])
    && is_array($links['data']['links'])
    && ($links['data']['data_source'] ?? '') === 'live_tenant';
$erp = new ErpAgent($config, new Phase7MockLlm([
    ['mode' => 'text', 'payload' => ['content' => 'Inventory overview ready.']],
]));
$resInv = $erp->resolveDomain(['domain' => 'inventory'], $ctx);
$resKw = $erp->resolveDomain(['message' => 'ما الأصناف منخفضة المخزون؟'], $ctx);
$through = $erp->process([
    'message' => 'inventory summary',
    'request_id' => 'p7-inv-' . bin2hex(random_bytes(3)),
    'domain' => 'inventory',
], $ctx);
$bridgeOk = $linkOk
    && !empty($resInv['ok'])
    && ($resInv['domain']['id'] ?? '') === 'inventory'
    && !empty($resKw['ok'])
    && ($resKw['domain']['id'] ?? '') === 'inventory'
    && ($through['domain'] ?? '') === 'inventory'
    && ($through['agent'] ?? '') === ErpAgent::AGENT_ID;
$bridgeOk ? $pass('PROCUREMENT ↔ INVENTORY') : $fail('PROCUREMENT ↔ INVENTORY');

// WRITE CONFIRM — inventory has no write tools; procurement confirmation still required
$invWriteTools = array_filter(InventoryToolRegistry::getTools(), static fn($t) => !empty($t['write']));
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase7 Confirm'],
    'request_company_id' => null,
    'request_id' => 'p7-wc',
    'write_confirmed' => false,
], $ctx);
$writeOk = $invWriteTools === []
    && !$block['allowed']
    && ($block['error_code'] ?? '') === 'write_confirmation_required';
$writeOk ? $pass('WRITE CONFIRM') : $fail('WRITE CONFIRM');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'مخزون'],
    ['role' => 'user', 'content' => '{"company_id":9,"user_id":1}'],
]);
$ctxOk = count($dirty) === 1
    && $dirty[0]['content'] === 'مخزون'
    && str_contains($ctx->conversationScopeKey(), (string) $companyId)
    && $ctx->moduleEnabled('inventory')
    && $ctx->moduleEnabled('procurement');
$ctxOk ? $pass('CONTEXT') : $fail('CONTEXT');

// AUDIT via unified inventory path
$auditBefore = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE company_id = ' . (int) $companyId
)->fetchColumn();
$reqAudit = 'p7-audit-' . bin2hex(random_bytes(3));
$erpAudit = new ErpAgent($config, new Phase7MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 't1',
        'type' => 'function',
        'function' => ['name' => 'analyze_inventory', 'arguments' => '{"limit":5}'],
    ]]]],
    ['mode' => 'text', 'payload' => ['content' => 'Analysis complete.']],
]));
$rAudit = $erpAudit->process([
    'message' => 'analyze inventory',
    'request_id' => $reqAudit,
    'domain' => 'inventory',
], $ctx);
$auditRows = (int) $pdo->query(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ' . $pdo->quote($reqAudit) . ' AND company_id = ' . (int) $companyId
)->fetchColumn();
$auditOk = $auditRows > 0 && !empty($rAudit['audit']) && ($rAudit['domain'] ?? '') === 'inventory';
$auditOk ? $pass('AUDIT') : $fail('AUDIT', (string) $auditRows);

// SECURITY
$noPerm = $makeCtx([], false, $companyId, ['inventory'], 'en');
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_inventory_items',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p7-sec',
    'write_confirmed' => false,
], $noPerm);
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p7-bad',
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
    $foreignInv = (int) $pdo->query(
        'SELECT id FROM rateb_inventory WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreignInv > 0) {
        $iso = InventoryToolExecutor::execute('get_inventory_item', ['id' => $foreignInv], $ctx);
        $tenantOk = empty($iso['success']) && ($iso['error_code'] ?? '') === 'inventory_not_found';
    } else {
        $tenantOk = true;
    }
} else {
    $iso = InventoryToolExecutor::execute('get_inventory_item', ['id' => 999999991], $ctx);
    $tenantOk = empty($iso['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_inventory_items',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p7-mismatch',
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
$ar = __('ai_tool_analyze_inventory');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$en = __('ai_tool_analyze_inventory');
$_SESSION['rateb_locale'] = $prev;
(is_string($ar) && mb_strpos($ar, 'المخزون') !== false) ? $pass('AR') : $fail('AR', (string) $ar);
(is_string($en) && stripos($en, 'Inventory') !== false) ? $pass('EN') : $fail('EN', (string) $en);

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
    'request_id' => 'p7-pr-block',
    'write_confirmed' => false,
], $ctx);
$regOk = !empty($prRead['success'])
    && !empty($prSum['success'])
    && !empty($prAdv['success'])
    && !empty($prGuide['success'])
    && !empty($prAppr['success'])
    && !$prBlock['allowed']
    && ProcurementToolRegistry::isAllowed('analyze_procurement_intelligence')
    && ErpDomainRegistry::isActive('procurement')
    && count(ErpDomainRegistry::activeDomainIds()) >= 2;
$regOk ? $pass('PROCUREMENT REGRESSION') : $fail('PROCUREMENT REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'INVENTORY READ', 'INVENTORY ANALYSIS', 'INVENTORY TOOLS', 'PROCUREMENT ↔ INVENTORY',
    'WRITE CONFIRM', 'AUDIT', 'CONTEXT', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'PROCUREMENT REGRESSION',
];
echo "\n=== PHASE 7 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate));
    $ok = $hits !== [] && !empty($hits[0]['ok']);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
