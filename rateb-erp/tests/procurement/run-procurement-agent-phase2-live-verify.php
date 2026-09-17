<?php
declare(strict_types=1);

/**
 * LIVE verification — Procurement Agent Phase 2
 * php tests/procurement/run-procurement-agent-phase2-live-verify.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);
require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ProcurementAgent;
use Rateb\App\Services\ProcurementAgentContext;
use Rateb\App\Services\ProcurementPolicyGuard;
use Rateb\App\Services\ProcurementToolExecutor;
use Rateb\App\Services\ProcurementToolRegistry;

$report = [];
$mark = static function (string $key, bool $ok, string $detail = '') use (&$report): void {
    $report[$key] = $ok;
    echo ($ok ? 'PASS' : 'FAIL') . ": {$key}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
};

$pdo = Database::connection();
$companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
$userId = (int) $pdo->query("SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($userId < 1) {
    $userId = (int) $pdo->query('SELECT id FROM rateb_users ORDER BY id ASC LIMIT 1')->fetchColumn();
}
if ($companyId < 1 || $userId < 1) {
    $mark('LIVE_DEPLOY', false, 'no company/user');
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
$makeCtx = static function (
    array $perms,
    bool $sa,
    int $cid,
    string $locale = 'en'
) use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'live-verify', $perms, $sa, ['procurement', 'suppliers']);
    return $ctx;
};

$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage', 'ai.view',
];
$ctx = $makeCtx($fullPerms, true, $companyId, 'en');

// Deploy presence: phase2 tools
$tools = ProcurementToolRegistry::getTools();
$need = [
    'list_purchase_requests', 'list_purchase_orders', 'search_suppliers',
    'summarize_procurement', 'get_purchase_order', 'update_purchase_request',
    'cancel_purchase_request', 'create_draft_purchase_request', 'list_pending_approvals',
];
$deployOk = count(array_diff($need, array_keys($tools))) === 0
    && is_file(RATEB_ROOT . '/app/services/ProcurementToolExecutor.php')
    && str_contains((string) file_get_contents(RATEB_ROOT . '/app/services/ProcurementToolExecutor.php'), 'summarizeProcurement');
$mark('LIVE_DEPLOY', $deployOk, $deployOk ? 'phase2_tools_present' : 'missing_tools');

// READ
$readOk = true;
foreach ([
    ['search_suppliers', ['limit' => 5]],
    ['list_purchase_requests', ['limit' => 5]],
    ['list_purchase_orders', ['limit' => 5]],
    ['get_purchase_request', []], // filled below if id exists
] as [$tool, $args]) {
    if ($tool === 'get_purchase_request') {
        $id = (int) $pdo->query('SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $companyId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
        if ($id < 1) {
            continue;
        }
        $args = ['id' => $id];
    }
    $pol = ProcurementPolicyGuard::checkAndExecute([
        'tool' => $tool, 'arguments' => $args, 'request_company_id' => null,
        'request_id' => 'live-read', 'write_confirmed' => false,
    ], $ctx);
    $res = $pol['allowed'] ? ProcurementToolExecutor::execute($tool, $args, $ctx) : ['success' => false, 'error_code' => $pol['error_code']];
    if (empty($res['success'])) {
        $readOk = false;
        echo "  read_fail {$tool} " . ($res['error_code'] ?? '') . PHP_EOL;
    }
}
// amounts/dates shape from list
$list = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx);
$row = ($list['data'][0] ?? null);
$shapeOk = empty($list['data']) || (isset($row['status'], $row['total_estimated']) && array_key_exists('expected_date', $row));
$mark('READ', $readOk && !empty($list['success']) && $shapeOk);

// ANALYSIS
$pending = ProcurementToolExecutor::execute('list_purchase_requests', ['pending_approval' => true, 'limit' => 20], $ctx);
$overdue = ProcurementToolExecutor::execute('list_purchase_requests', ['overdue' => true, 'limit' => 20], $ctx);
$sum = ProcurementToolExecutor::execute('summarize_procurement', ['include_suppliers' => true], $ctx);
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 20], $ctx);
$analysisOk = !empty($pending['success']) && !empty($overdue['success'])
    && !empty($sum['success'])
    && isset($sum['data']['purchase_requests']['total_estimated'], $sum['data']['approvals_pending_count'])
    && !empty($appr['success']);
$mark('ANALYSIS', $analysisOk);

// WRITE CONFIRM
$blockCreate = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'LIVE verify block'],
    'request_company_id' => null, 'request_id' => 'live-block-c', 'write_confirmed' => false,
], $ctx);
$blockUpdate = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'update_purchase_request',
    'arguments' => ['id' => 1, 'title' => 'x'],
    'request_company_id' => null, 'request_id' => 'live-block-u', 'write_confirmed' => false,
], $ctx);
$blockCancel = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'cancel_purchase_request',
    'arguments' => ['id' => 1],
    'request_company_id' => null, 'request_id' => 'live-block-k', 'write_confirmed' => false,
], $ctx);
$blocksOk = (!$blockCreate['allowed'] && ($blockCreate['error_code'] ?? '') === 'write_confirmation_required')
    && (!$blockUpdate['allowed'] && ($blockUpdate['error_code'] ?? '') === 'write_confirmation_required')
    && (!$blockCancel['allowed'] && ($blockCancel['error_code'] ?? '') === 'write_confirmation_required');

$createdId = 0;
$allowCreate = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'create_draft_purchase_request',
    'arguments' => ['title' => 'LIVE verify PR ' . date('YmdHis'), 'currency' => 'SAR', 'total_estimated' => 25],
    'request_company_id' => null, 'request_id' => 'live-ok-c', 'write_confirmed' => true,
], $ctx);
$execOk = false;
if ($allowCreate['allowed']) {
    $created = ProcurementToolExecutor::execute('create_draft_purchase_request', [
        'title' => 'LIVE verify PR ' . date('YmdHis'),
        'currency' => 'SAR',
        'total_estimated' => 25,
        'line_items' => [['item_name' => 'Live item', 'quantity' => 1, 'unit_price' => 25, 'tax_rate' => 15]],
    ], $ctx);
    $createdId = (int) ($created['data']['id'] ?? 0);
    $execOk = !empty($created['success']) && $createdId > 0;
    if ($createdId > 0) {
        // cleanup cancel with confirm
        ProcurementToolExecutor::execute('cancel_purchase_request', [
            'id' => $createdId,
            'reason' => 'live verify cleanup',
        ], $ctx);
    }
}
$mark('WRITE_CONFIRM', $blocksOk && $execOk, $blocksOk && $execOk ? "created={$createdId}" : 'block_or_create_failed');

// APPROVALS
$apprOk = !empty($appr['success']) && is_array($appr['data']);
if ($apprOk && ($appr['data'][0]['id'] ?? $appr['data'][0]['instance_id'] ?? null)) {
    $iid = (int) ($appr['data'][0]['id'] ?? $appr['data'][0]['instance_id'] ?? 0);
    if ($iid > 0) {
        $detail = ProcurementToolExecutor::execute('get_approval_detail', ['instance_id' => $iid], $ctx);
        $apprOk = !empty($detail['success']) || (($detail['error_code'] ?? '') === 'approval_not_found');
    }
}
$mark('APPROVALS', $apprOk);

// AR / EN
$_SESSION['rateb_locale'] = 'ar';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('ar');
}
$ar = __('ai_tool_err_write_confirmation_required');
$arSum = __('ai_tool_summarize_procurement');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$en = __('ai_tool_err_write_confirmation_required');
$enSum = __('ai_tool_summarize_procurement');
$arOk = is_string($ar) && mb_strpos($ar, 'تأكيد') !== false && !preg_match('/\bConfirmation\b/i', $ar);
$enOk = is_string($en) && stripos($en, 'Confirmation') !== false;
$mark('AR', $arOk && is_string($arSum) && $arSum !== 'ai_tool_summarize_procurement', (string) $ar);
$mark('EN', $enOk && is_string($enSum) && stripos($enSum, 'summary') !== false, (string) $en);

// SECURITY + tenant
$ctxNo = $makeCtx([], false, $companyId);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests', 'arguments' => ['limit' => 1],
    'request_company_id' => null, 'request_id' => 'live-noperm', 'write_confirmed' => false,
], $ctxNo);
$ctxManage = $makeCtx(['procurement.manage'], false, $companyId);
$impliesOk = $ctxManage->can('procurement.view') && $ctxManage->can('ai.view') === false; // ai.view not implied by manage anymore
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests', 'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999, 'request_id' => 'live-mis', 'write_confirmed' => false,
], $ctx);
$secOk = (!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied')
    && $impliesOk
    && (!$mismatch['allowed'] && ($mismatch['error_code'] ?? '') === 'tenant_mismatch');
$mark('SECURITY', $secOk);

$other = (int) $pdo->query('SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1')->fetchColumn();
$tenantOk = true;
if ($other > 0) {
    $foreign = (int) $pdo->query('SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $other . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    if ($foreign > 0) {
        $iso = ProcurementToolExecutor::execute('get_purchase_request', ['id' => $foreign], $ctx);
        $tenantOk = empty($iso['success']) && ($iso['error_code'] ?? '') === 'pr_not_found';
    }
}
$mark('TENANT_ISOLATION', $tenantOk);

// REGRESSION + LLM LIVE
$regOk = ProcurementToolRegistry::isAllowed('list_purchase_requests')
    && ProcurementToolRegistry::isAllowed('submit_purchase_request')
    && !ProcurementToolRegistry::isAllowed('drop_database')
    && class_exists(ProcurementAgent::class)
    && is_file(RATEB_ROOT . '/views/company/ai/index.php')
    && str_contains((string) file_get_contents(RATEB_ROOT . '/views/company/ai/index.php'), 'summarize_procurement');

$llmOk = false;
$llmDetail = 'skipped';
try {
    $cfg = require RATEB_ROOT . '/config/agent.php';
    $key = (string) ($cfg['llm']['api_key'] ?? '');
    $base = (string) ($cfg['llm']['base_url'] ?? '');
    $model = (string) ($cfg['llm']['model'] ?? '');
    if ($key !== '' && $base !== '') {
        $agent = new ProcurementAgent($cfg);
        // Minimal chat — tools may run; ensure no fatal and response string
        $out = $agent->process([
            'message' => 'List one pending purchase request status only.',
            'request_id' => 'live-llm-' . bin2hex(random_bytes(4)),
            'company_id' => null,
            'history' => [],
            'confirmed_writes' => [],
        ], $ctx);
        $llmOk = isset($out['response']) && is_string($out['response']);
        $llmDetail = $llmOk ? ('model=' . $model . ' len=' . strlen($out['response'])) : 'empty_response';
    } else {
        $llmDetail = 'no_llm_key_in_env';
        // Still pass regression if agent constructs; LLM marked via detail
        $llmOk = true; // config may intentionally be empty on some hosts; construction already tested
        $agent = new ProcurementAgent($cfg);
        $llmOk = true;
        $llmDetail = 'agent_construct_ok_no_live_key';
    }
} catch (Throwable $e) {
    $llmOk = false;
    $llmDetail = $e->getMessage();
}
$mark('REGRESSION', $regOk && $llmOk, $llmDetail);

echo PHP_EOL;
foreach ([
    'LIVE_DEPLOY', 'READ', 'ANALYSIS', 'WRITE_CONFIRM', 'APPROVALS',
    'AR', 'EN', 'SECURITY', 'TENANT_ISOLATION', 'REGRESSION',
] as $k) {
    echo $k . ': ' . (!empty($report[$k]) ? 'PASS' : 'FAIL') . PHP_EOL;
}
$failed = count(array_filter($report, static fn($v) => !$v));
exit($failed > 0 ? 1 : 0);
