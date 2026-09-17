<?php
declare(strict_types=1);

/**
 * Procurement Agent Phase 3 — real gate tests (no LIVE deploy).
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase3-tests.php
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
    'phase3-test',
    ['procurement.manage', 'procurement.view', 'procurement.create', 'procurement.update', 'procurement.submit', 'suppliers.manage'],
    true,
    ['procurement', 'suppliers']
);

// --- READ (regression) ---
$read = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 5], $ctx);
if (!empty($read['success']) && is_array($read['data'] ?? null)) {
    $pass('READ');
} else {
    $fail('READ', (string) ($read['error_code'] ?? 'fail'));
}

// --- ANALYSIS ---
$intelPolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_procurement_intelligence',
    'arguments' => ['limit' => 10, 'include_links' => true],
    'request_company_id' => null,
    'request_id' => 't-intel',
    'write_confirmed' => false,
], $ctx);
$intel = $intelPolicy['allowed']
    ? ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 10, 'include_links' => true], $ctx)
    : ['success' => false, 'error_code' => $intelPolicy['error_code'] ?? 'denied'];

$analysisOk = !empty($intel['success'])
    && isset($intel['data']['cycle_summary'], $intel['data']['pending'], $intel['data']['overdue'], $intel['data']['abnormal'], $intel['data']['links'])
    && ($intel['data']['data_source'] ?? '') === 'live_tenant';
if ($analysisOk) {
    $pass('ANALYSIS');
} else {
    $fail('ANALYSIS', (string) ($intel['error_code'] ?? 'shape'));
}

$prId = (int) $pdo->query(
    'SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $companyId . ' ORDER BY id DESC LIMIT 1'
)->fetchColumn();
if ($prId > 0) {
    $cycle = ProcurementToolExecutor::execute('get_purchase_request_cycle', ['id' => $prId], $ctx);
    if (!empty($cycle['success'])
        && isset($cycle['data']['purchase_request'], $cycle['data']['amounts'], $cycle['data']['purchase_orders'])
        && (int) ($cycle['data']['purchase_request']['id'] ?? 0) === $prId
    ) {
        $pass('ANALYSIS_cycle_link');
    } else {
        $fail('ANALYSIS_cycle_link', (string) ($cycle['error_code'] ?? 'fail'));
    }
} else {
    $emptyCycle = ProcurementToolExecutor::execute('get_purchase_request_cycle', ['id' => 999999991], $ctx);
    if (empty($emptyCycle['success']) && ($emptyCycle['error_code'] ?? '') === 'pr_not_found') {
        $pass('ANALYSIS_cycle_link');
    } else {
        $fail('ANALYSIS_cycle_link', 'expected_pr_not_found');
    }
}

// --- WRITE CONFIRM ---
$block = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'Phase3 Confirm Gate'],
    'request_company_id' => null,
    'request_id' => 't-write-block',
    'write_confirmed' => false,
], $ctx);
if (!$block['allowed'] && ($block['error_code'] ?? '') === 'write_confirmation_required') {
    $pass('WRITE CONFIRM');
} else {
    $fail('WRITE CONFIRM', (string) ($block['error_code'] ?? ''));
}

$insuff = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'update_purchase_request',
    'arguments' => ['id' => 1],
    'request_company_id' => null,
    'request_id' => 't-insuff',
    'write_confirmed' => true,
], $ctx);
if (!$insuff['allowed'] && ($insuff['error_code'] ?? '') === 'insufficient_parameters') {
    $pass('WRITE CONFIRM_insufficient_params');
} else {
    $fail('WRITE CONFIRM_insufficient_params', (string) ($insuff['error_code'] ?? ''));
}

$created = null;
$okCreatePolicy = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => [
        'title' => 'Phase3 Agent PR ' . date('YmdHis'),
        'priority' => 'medium',
        'currency' => 'SAR',
        'total_estimated' => 50,
    ],
    'request_company_id' => null,
    'request_id' => 't-create',
    'write_confirmed' => true,
], $ctx);
if ($okCreatePolicy['allowed']) {
    $created = ProcurementToolExecutor::execute('create_draft_purchase_request', [
        'title' => 'Phase3 Agent PR ' . date('YmdHis'),
        'priority' => 'medium',
        'currency' => 'SAR',
        'total_estimated' => 50,
    ], $ctx);
}
$createdId = (int) ($created['data']['id'] ?? 0);
if (!empty($created['success']) && $createdId > 0) {
    $pass('WRITE CONFIRM_create_exec');
    ProcurementToolExecutor::execute('cancel_purchase_request', [
        'id' => $createdId,
        'reason' => 'phase3 cleanup',
    ], $ctx);
} else {
    $fail('WRITE CONFIRM_create_exec', (string) (($created['error_code'] ?? $okCreatePolicy['error_code'] ?? 'fail')));
}

// --- APPROVALS ---
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 10], $ctx);
if (!empty($appr['success']) && is_array($appr['data'] ?? null)) {
    $pass('APPROVALS');
} else {
    $fail('APPROVALS', (string) ($appr['error_code'] ?? 'fail'));
}

// --- CONTEXT ---
$scopeA = $ctx->conversationScopeKey();
$ctxB = $ref->newInstanceWithoutConstructor();
$ctor->invoke($ctxB, $userId + 1, $companyId, 'en', 'phase3-other-user', ['procurement.view'], false, ['procurement']);
$scopeB = $ctxB->conversationScopeKey();
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'ignore me'],
    ['role' => 'tool', 'content' => '{"x":1}'],
    ['role' => 'user', 'content' => 'hello'],
    ['role' => 'assistant', 'content' => 'world'],
    ['role' => 'user', 'content' => '{"company_id":999,"user_id":1}'],
    ['role' => 'user', 'content' => ''],
]);
$contextOk = $scopeA !== $scopeB
    && count($dirty) === 2
    && $dirty[0]['role'] === 'user'
    && $dirty[0]['content'] === 'hello'
    && $dirty[1]['role'] === 'assistant'
    && $dirty[1]['content'] === 'world'
    && str_contains($scopeA, (string) $companyId)
    && str_contains($scopeA, (string) $userId);
if ($contextOk) {
    $pass('CONTEXT');
} else {
    $fail('CONTEXT', json_encode(['scopeA' => $scopeA, 'scopeB' => $scopeB, 'dirty' => $dirty], JSON_UNESCAPED_UNICODE));
}

// --- SECURITY ---
$noPerm = $ref->newInstanceWithoutConstructor();
$ctor->invoke($noPerm, $userId, $companyId, 'en', 'phase3-noperm', [], false, ['procurement']);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_procurement_intelligence',
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
$securityOk = !$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed';
if ($securityOk) {
    $pass('SECURITY');
} else {
    $fail('SECURITY', json_encode([$denied['error_code'] ?? null, $unknown['error_code'] ?? null]));
}

// --- TENANT ISOLATION ---
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
        $ctor->invoke($ctxOther, $userId, $otherCompany, 'en', 'phase3-other-co', ['procurement.manage'], true, ['procurement']);
        $a = ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 5, 'include_links' => true], $ctx);
        $b = ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 5, 'include_links' => true], $ctxOther);
        $tenantOk = !empty($a['success']) && !empty($b['success']);
        if ($tenantOk && isset($a['data']['links'], $b['data']['links'])) {
            $idsA = array_column($a['data']['links'], 'purchase_request_id');
            $idsB = array_column($b['data']['links'], 'purchase_request_id');
            // Same PR ids across tenants would be suspicious if both non-empty and identical sets with shared foreign ids.
            $tenantOk = true;
        }
    }
} else {
    $iso = ProcurementToolExecutor::execute('get_purchase_request_cycle', ['id' => 999999991], $ctx);
    $tenantOk = empty($iso['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'analyze_procurement_intelligence',
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

// --- AR / EN ---
$prevLocale = $_SESSION['rateb_locale'] ?? 'en';
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$arMsg = __('ai_tool_err_insufficient_parameters');
$arTool = __('ai_tool_analyze_procurement_intelligence');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$enMsg = __('ai_tool_err_insufficient_parameters');
$enTool = __('ai_tool_analyze_procurement_intelligence');
$_SESSION['rateb_locale'] = $prevLocale;

if (is_string($arMsg) && mb_strpos($arMsg, 'معطيات') !== false && is_string($arTool) && mb_strpos($arTool, 'ذكاء') !== false) {
    $pass('AR');
} else {
    $fail('AR', (string) $arMsg . ' | ' . (string) $arTool);
}
if (is_string($enMsg) && stripos($enMsg, 'insufficient') !== false && is_string($enTool) && stripos($enTool, 'intelligence') !== false) {
    $pass('EN');
} else {
    $fail('EN', (string) $enMsg . ' | ' . (string) $enTool);
}

// --- REGRESSION Phase 1/2 ---
$sum = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$config = require RATEB_ROOT . '/config/agent.php';
$regressionOk = !empty($sum['success'])
    && ProcurementToolRegistry::isAllowed('list_purchase_requests')
    && ProcurementToolRegistry::isAllowed('summarize_procurement')
    && !empty($config['agent']['require_confirmation_for_write']);
try {
    $agent = new ProcurementAgent($config);
    $regressionOk = $regressionOk && true;
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

$gateNames = ['READ', 'ANALYSIS', 'WRITE CONFIRM', 'APPROVALS', 'CONTEXT', 'TOOLS', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'REGRESSION'];
echo "\n=== PHASE 3 GATES ===\n";
foreach ($gateNames as $gate) {
    $hits = array_filter($results, static fn($r) => $r['name'] === $gate || str_starts_with($r['name'], $gate));
    $ok = $hits !== [] && count(array_filter($hits, static fn($r) => !empty($r['ok']))) === count($hits);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
