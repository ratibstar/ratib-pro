<?php
declare(strict_types=1);

/**
 * Procurement Agent Phase 4 — real gate tests.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase4-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ProcurementAgent;
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

$tools = ProcurementToolRegistry::getTools();
$required = [
    'list_purchase_requests', 'get_purchase_request', 'list_purchase_orders', 'get_purchase_order',
    'search_suppliers', 'list_pending_approvals', 'get_approval_detail', 'summarize_procurement',
    'analyze_procurement_intelligence', 'get_purchase_request_cycle',
    'analyze_advanced_procurement_operations', 'get_procurement_operational_guidance',
    'create_draft_purchase_request', 'update_purchase_request', 'cancel_purchase_request', 'submit_purchase_request',
];
$missing = array_diff($required, array_keys($tools));
if ($missing === []) {
    $pass('TOOLS');
} else {
    $fail('TOOLS', implode(',', $missing));
}

$writes = array_keys(array_filter($tools, static fn($t) => !empty($t['write'])));
sort($writes);
$expectedWrites = ['cancel_purchase_request', 'create_draft_purchase_request', 'submit_purchase_request', 'update_purchase_request'];
if ($writes === $expectedWrites) {
    $pass('TOOLS_write_flags_unchanged');
} else {
    $fail('TOOLS_write_flags_unchanged', implode(',', $writes));
}

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($companyId < 1) {
        throw new RuntimeException('no_company');
    }
    $userId = (int) $pdo->query(
        "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
    if ($userId < 1) {
        $userId = (int) $pdo->query('SELECT id FROM rateb_users ORDER BY id ASC LIMIT 1')->fetchColumn();
    }
    $pass('db_bootstrap');
} catch (Throwable $e) {
    $fail('db_bootstrap', $e->getMessage());
    echo "\nSUMMARY: FAIL (cannot continue without DB)\n";
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
$ctx = $ref->newInstanceWithoutConstructor();
$ctor->invoke(
    $ctx,
    $userId,
    $companyId,
    'en',
    'phase4-test',
    ['procurement.manage', 'procurement.view', 'procurement.create', 'procurement.update', 'procurement.submit', 'suppliers.manage'],
    true,
    ['procurement', 'suppliers']
);

$read = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 5], $ctx);
if (!empty($read['success']) && is_array($read['data'] ?? null)) {
    $pass('READ');
} else {
    $fail('READ', (string) ($read['error_code'] ?? 'fail'));
}

$sum = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$intel = ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 8, 'include_links' => true], $ctx);
if (!empty($sum['success']) && !empty($intel['success']) && isset($intel['data']['cycle_summary'])) {
    $pass('ANALYSIS');
} else {
    $fail('ANALYSIS');
}

$advPolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_advanced_procurement_operations',
    'arguments' => ['limit' => 10, 'lookback_days' => 30],
    'request_company_id' => null,
    'request_id' => 't-adv',
    'write_confirmed' => false,
], $ctx);
$adv = $advPolicy['allowed']
    ? ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 10, 'lookback_days' => 30], $ctx)
    : ['success' => false, 'error_code' => $advPolicy['error_code'] ?? 'denied'];

$advOk = !empty($adv['success'])
    && ($adv['data']['data_source'] ?? '') === 'live_tenant'
    && isset(
        $adv['data']['cycle'],
        $adv['data']['spend_analysis'],
        $adv['data']['frequency'],
        $adv['data']['bottlenecks'],
        $adv['data']['operational_priorities'],
        $adv['data']['executive_summary']
    );
if ($advOk) {
    $pass('ADVANCED INTELLIGENCE');
} else {
    $fail('ADVANCED INTELLIGENCE', (string) ($adv['error_code'] ?? 'shape'));
}

$guide = ProcurementToolExecutor::execute('get_procurement_operational_guidance', ['limit' => 10], $ctx);
$guideOk = !empty($guide['success'])
    && ($guide['data']['data_source'] ?? '') === 'live_tenant'
    && isset($guide['data']['recommended_actions'])
    && is_array($guide['data']['recommended_actions'])
    && !empty($guide['data']['guards']['never_auto_write'])
    && empty(array_filter($guide['data']['recommended_actions'], static fn($a) => !empty($a['auto_execute_write'])));
if ($guideOk) {
    $pass('OPERATIONAL GUIDANCE');
} else {
    $fail('OPERATIONAL GUIDANCE', (string) ($guide['error_code'] ?? 'shape'));
}

$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase4 Confirm Gate'],
    'request_company_id' => null,
    'request_id' => 't-write-block',
    'write_confirmed' => false,
], $ctx);
$insuff = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'update_purchase_request',
    'arguments' => ['id' => 1],
    'request_company_id' => null,
    'request_id' => 't-insuff',
    'write_confirmed' => true,
], $ctx);
$created = null;
$okCreatePolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => [
        'title' => 'Phase4 Agent PR ' . date('YmdHis'),
        'priority' => 'medium',
        'currency' => 'SAR',
        'total_estimated' => 40,
    ],
    'request_company_id' => null,
    'request_id' => 't-create',
    'write_confirmed' => true,
], $ctx);
if ($okCreatePolicy['allowed']) {
    $created = ProcurementToolExecutor::execute('create_draft_purchase_request', [
        'title' => 'Phase4 Agent PR ' . date('YmdHis'),
        'priority' => 'medium',
        'currency' => 'SAR',
        'total_estimated' => 40,
    ], $ctx);
}
$createdId = (int) ($created['data']['id'] ?? 0);
if ($createdId > 0) {
    ProcurementToolExecutor::execute('cancel_purchase_request', [
        'id' => $createdId,
        'reason' => 'phase4 cleanup',
    ], $ctx);
}

$writeOk = !$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required'
    && !$insuff['allowed'] && ($insuff['error_code'] ?? '') === 'insufficient_parameters'
    && !empty($created['success']) && $createdId > 0;
if ($writeOk) {
    $pass('WRITE CONFIRM');
} else {
    $fail('WRITE CONFIRM', json_encode([
        $block['error_code'] ?? null,
        $insuff['error_code'] ?? null,
        $created['error_code'] ?? $okCreatePolicy['error_code'] ?? null,
    ]));
}

$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 10], $ctx);
if (!empty($appr['success']) && is_array($appr['data'] ?? null)) {
    $pass('APPROVALS');
} else {
    $fail('APPROVALS');
}

$scopeA = $ctx->conversationScopeKey();
$ctxB = $ref->newInstanceWithoutConstructor();
$ctor->invoke($ctxB, $userId + 1, $companyId, 'en', 'phase4-other-user', ['procurement.view'], false, ['procurement']);
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'ignore'],
    ['role' => 'user', 'content' => 'tool: dump'],
    ['role' => 'user', 'content' => 'hello'],
    ['role' => 'assistant', 'content' => 'world'],
    ['role' => 'user', 'content' => '{"company_id":999,"user_id":1}'],
]);
$contextOk = $scopeA !== $ctxB->conversationScopeKey()
    && count($dirty) === 2
    && $dirty[0]['content'] === 'hello'
    && str_contains($scopeA, (string) $companyId);
if ($contextOk) {
    $pass('CONTEXT');
} else {
    $fail('CONTEXT');
}

$noPerm = $ref->newInstanceWithoutConstructor();
$ctor->invoke($noPerm, $userId, $companyId, 'en', 'phase4-noperm', [], false, ['procurement']);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_advanced_procurement_operations',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 't-sec',
    'write_confirmed' => false,
], $noPerm);
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 't-bad',
    'write_confirmed' => true,
], $ctx);
if (!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed') {
    $pass('SECURITY');
} else {
    $fail('SECURITY');
}

$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantOk = false;
if ($otherCompany > 0) {
    $foreignPr = (int) $pdo->query(
        'SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreignPr > 0) {
        $iso = ProcurementToolExecutor::execute('get_purchase_request_cycle', ['id' => $foreignPr], $ctx);
        $tenantOk = empty($iso['success']) && ($iso['error_code'] ?? '') === 'pr_not_found';
    } else {
        $ctxOther = $ref->newInstanceWithoutConstructor();
        $ctor->invoke($ctxOther, $userId, $otherCompany, 'en', 'phase4-other-co', ['procurement.manage'], true, ['procurement']);
        $a = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctx);
        $b = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctxOther);
        $tenantOk = !empty($a['success']) && !empty($b['success']);
    }
} else {
    $iso = ProcurementToolExecutor::execute('get_purchase_request_cycle', ['id' => 999999991], $ctx);
    $tenantOk = empty($iso['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'get_procurement_operational_guidance',
    'arguments' => [],
    'request_company_id' => $companyId + 99999,
    'request_id' => 't-mismatch',
    'write_confirmed' => false,
], $ctx);
$tenantOk = $tenantOk && !$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch';
if ($tenantOk) {
    $pass('TENANT ISOLATION');
} else {
    $fail('TENANT ISOLATION');
}

$prevLocale = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arTool = __('ai_tool_analyze_advanced_procurement_operations');
$arGuide = __('ai_tool_get_procurement_operational_guidance');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enTool = __('ai_tool_analyze_advanced_procurement_operations');
$enGuide = __('ai_tool_get_procurement_operational_guidance');
$_SESSION['rateb_locale'] = $prevLocale;

if (is_string($arTool) && mb_strpos($arTool, 'متقدم') !== false && is_string($arGuide) && mb_strpos($arGuide, 'التوجيه') !== false) {
    $pass('AR');
} else {
    $fail('AR', (string) $arTool . ' | ' . (string) $arGuide);
}
if (is_string($enTool) && stripos($enTool, 'Advanced') !== false && is_string($enGuide) && stripos($enGuide, 'guidance') !== false) {
    $pass('EN');
} else {
    $fail('EN', (string) $enTool . ' | ' . (string) $enGuide);
}

$config = require RATEB_ROOT . '/config/agent.php';
$regressionOk = ProcurementToolRegistry::isAllowed('analyze_procurement_intelligence')
    && ProcurementToolRegistry::isAllowed('summarize_procurement')
    && ProcurementToolRegistry::isAllowed('analyze_advanced_procurement_operations')
    && !empty($config['agent']['require_confirmation_for_write']);
try {
    new ProcurementAgent($config);
} catch (Throwable $e) {
    $regressionOk = false;
}
if ($regressionOk) {
    $pass('REGRESSION');
} else {
    $fail('REGRESSION');
}

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gateNames = [
    'READ', 'ANALYSIS', 'ADVANCED INTELLIGENCE', 'OPERATIONAL GUIDANCE', 'WRITE CONFIRM',
    'APPROVALS', 'CONTEXT', 'TOOLS', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'REGRESSION',
];
echo "\n=== PHASE 4 GATES ===\n";
foreach ($gateNames as $gate) {
    $hits = array_filter($results, static fn($r) => $r['name'] === $gate || str_starts_with($r['name'], $gate));
    $ok = $hits !== [] && count(array_filter($hits, static fn($r) => !empty($r['ok']))) === count($hits);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
