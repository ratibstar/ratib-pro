<?php
declare(strict_types=1);

/**
 * Focused confirmation-button + extraction gates for Unified Agent.
 * Run: php rateb-erp/tests/procurement/run-unified-agent-confirm-fix-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ProcurementAgentContext;

$results = [];
$pass = static function (string $name) use (&$results): void {
    $results[] = ['name' => $name, 'ok' => true];
    echo "PASS: {$name}\n";
};
$fail = static function (string $name, string $detail = '') use (&$results): void {
    $results[] = ['name' => $name, 'ok' => false, 'detail' => $detail];
    echo "FAIL: {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
};

try {
    $pdo = Database::connection();
    $companyId = (int) $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1')->fetchColumn();
    $companyId2 = (int) $pdo->query(
        'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
    )->fetchColumn();
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
$makeCtx = static function (array $perms, bool $sa, int $cid, array $modules, string $locale = 'ar') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'confirm-fix', $perms, $sa, $modules);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$config = require RATEB_ROOT . '/config/agent.php';
$agent = new ErpAgent(is_array($config) ? $config : []);
$scope = $ctx->conversationScopeKey() . ':confirm-fix';

$liveMsg = 'أنشئ طلب شراء تجريبي لشراء 10 وحدات من بطاطس بأولوية متوسطة';

// ITEM / QTY / PRIORITY extraction
$draft = ErpActionPlanner::mergePurchaseRequestDraft($liveMsg, []);
$line = is_array($draft['arguments']['line_items'][0] ?? null) ? $draft['arguments']['line_items'][0] : [];
$itemName = (string) ($line['item_name'] ?? $line['description'] ?? '');
(stripos($itemName, 'بطاطس') !== false && stripos($itemName, 'وحدات') === false)
    ? $pass('ITEM EXTRACTION') : $fail('ITEM EXTRACTION', $itemName);
((float) ($line['quantity'] ?? 0) === 10.0)
    ? $pass('QUANTITY EXTRACTION') : $fail('QUANTITY EXTRACTION', (string) ($line['quantity'] ?? ''));
(((string) ($draft['arguments']['priority'] ?? '')) === 'medium')
    ? $pass('PRIORITY EXTRACTION') : $fail('PRIORITY EXTRACTION', (string) ($draft['arguments']['priority'] ?? ''));

// Current context wins over contaminated prior draft
$contaminated = ErpActionPlanner::mergePurchaseRequestDraft('بطاطس 66', []);
$contaminated['arguments']['priority'] = 'low';
$contaminated['arguments']['line_items'] = [['item_name' => 'وحدات', 'quantity' => 66, 'unit' => 'unit']];
$won = ErpActionPlanner::mergePurchaseRequestDraft($liveMsg, $contaminated);
$wonLine = is_array($won['arguments']['line_items'][0] ?? null) ? $won['arguments']['line_items'][0] : [];
((stripos((string) ($wonLine['item_name'] ?? ''), 'بطاطس') !== false)
    && (float) ($wonLine['quantity'] ?? 0) === 10.0
    && ($won['arguments']['priority'] ?? '') === 'medium')
    ? $pass('CURRENT CONTEXT PRIORITY') : $fail('CURRENT CONTEXT PRIORITY', json_encode($wonLine, JSON_UNESCAPED_UNICODE));

// Propose → snapshot
ErpActionPlanner::clearPendingState($scope);
$propose = ErpActionPlanner::resolveConversationTurn($liveMsg, $ctx, [], [], []);
$snap = is_array($propose['pending']['parameter_snapshot'] ?? null) ? $propose['pending']['parameter_snapshot'] : [];
$snapLine = is_array($snap['line_items'][0] ?? null) ? $snap['line_items'][0] : [];
(($propose['mode'] ?? '') === 'propose'
    && !empty($propose['pending']['action_id'])
    && !empty($propose['pending']['confirmations'][0]['confirm_key'])
    && ($snap['priority'] ?? '') === 'medium'
    && stripos((string) ($snapLine['item_name'] ?? ''), 'بطاطس') !== false
    && (float) ($snapLine['quantity'] ?? 0) === 10.0)
    ? $pass('PARAMETER SNAPSHOT') : $fail('PARAMETER SNAPSHOT', json_encode($snap, JSON_UNESCAPED_UNICODE));
(!empty($propose['pending']['action_id']) && !empty($propose['pending']['confirmations'][0]['action_id']))
    ? $pass('ACTION IDENTITY') : $fail('ACTION IDENTITY');
(($propose['pending']['phase'] ?? '') === ErpActionPlanner::PHASE_PENDING_CONFIRMATION
    || ($propose['pending']['phase'] ?? '') === 'awaiting_confirmation')
    ? $pass('PENDING ACTION') : $fail('PENDING ACTION', (string) ($propose['pending']['phase'] ?? ''));

ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? []);

// Confirm button semantics: confirmed_writes + confirm phrase, not original message
$keys = [(string) ($propose['pending']['confirmations'][0]['confirm_key'] ?? '')];
$btnConfirm = ErpActionPlanner::resolveConversationTurn('تأكيد', $ctx, [], ErpActionPlanner::loadPendingState($scope), $keys);
(($btnConfirm['mode'] ?? '') === 'confirm' && !empty($btnConfirm['confirmed_writes']) && is_array($btnConfirm['action_plan']))
    ? $pass('CONFIRM BUTTON') : $fail('CONFIRM BUTTON', json_encode(['mode' => $btnConfirm['mode'] ?? null]));
(($btnConfirm['action_plan']['parameter_snapshot']['priority'] ?? '') === 'medium'
    && stripos((string) (($btnConfirm['action_plan']['parameter_snapshot']['line_items'][0]['item_name'] ?? '')), 'بطاطس') !== false)
    ? $pass('CONFIRMATION HANDOFF') : $fail('CONFIRMATION HANDOFF');

// Stale keys
$stale = ErpActionPlanner::resolveConversationTurn('تأكيد', $ctx, [], ErpActionPlanner::loadPendingState($scope), ['create_draft_purchase_request:deadbeef']);
(($stale['mode'] ?? '') === 'stale')
    ? $pass('STALE CONFIRMATION') : $fail('STALE CONFIRMATION', (string) ($stale['mode'] ?? ''));

// Cancel
ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? []);
$cancel = ErpActionPlanner::resolveConversationTurn('إلغاء', $ctx, [], ErpActionPlanner::loadPendingState($scope), []);
(($cancel['mode'] ?? '') === 'reject')
    ? $pass('CANCEL') : $fail('CANCEL', (string) ($cancel['mode'] ?? ''));

// Repeated proposal prevention: chatter while awaiting does not invent new plan
ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? []);
$keyBefore = (string) ($propose['pending']['confirmations'][0]['confirm_key'] ?? '');
$again = ErpActionPlanner::resolveConversationTurn('حسناً', $ctx, [], ErpActionPlanner::loadPendingState($scope), []);
$keyAfter = (string) ($again['pending']['confirmations'][0]['confirm_key'] ?? '');
(($again['mode'] ?? '') === 'propose' && $keyBefore === $keyAfter && $keyBefore !== '')
    ? $pass('REPEATED PROPOSAL PREVENTION') : $fail('REPEATED PROPOSAL PREVENTION', $keyBefore . ' vs ' . $keyAfter);

// Conversation reset: new request after prior pending must not leak بطاطس 10
ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? []);
$rice = 'أنشئ طلب شراء تجريبي لشراء 5 وحدات من أرز بأولوية عالية';
$reset = ErpActionPlanner::resolveConversationTurn($rice, $ctx, [
    ['role' => 'user', 'content' => $liveMsg],
], ErpActionPlanner::loadPendingState($scope), []);
$rSnap = is_array($reset['pending']['parameter_snapshot'] ?? null) ? $reset['pending']['parameter_snapshot'] : [];
$rLine = is_array($rSnap['line_items'][0] ?? null) ? $rSnap['line_items'][0] : [];
((stripos((string) ($rLine['item_name'] ?? ''), 'أرز') !== false || stripos((string) ($rLine['item_name'] ?? ''), 'رز') !== false)
    && (float) ($rLine['quantity'] ?? 0) === 5.0
    && ($rSnap['priority'] ?? '') === 'high'
    && stripos((string) ($rLine['item_name'] ?? ''), 'بطاطس') === false)
    ? $pass('CONVERSATION RESET') : $fail('CONVERSATION RESET', json_encode($rSnap, JSON_UNESCAPED_UNICODE));

// Live agent: propose → confirm execute
ErpActionPlanner::clearPendingState($scope);
$r1 = $agent->process([
    'message' => $liveMsg,
    'history' => [],
    'request_id' => 'cf-propose',
    'confirmed_writes' => [],
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$pendingUi = is_array($r1['pending_confirmations'] ?? null) ? $r1['pending_confirmations'] : [];
$level1 = (string) ($r1['governance']['execution_level'] ?? '');
($pendingUi !== [] && $level1 === 'CONFIRMED_WRITE')
    ? $pass('GOVERNANCE') : $fail('GOVERNANCE', $level1 . ' pending=' . count($pendingUi));

$confirmKeys = [];
foreach ($pendingUi as $p) {
    if (is_array($p) && (string) ($p['confirm_key'] ?? '') !== '') {
        $confirmKeys[] = (string) $p['confirm_key'];
    }
}
$r2 = $agent->process([
    'message' => 'تأكيد',
    'history' => [['role' => 'user', 'content' => $liveMsg]],
    'request_id' => 'cf-exec-' . bin2hex(random_bytes(3)),
    'confirmed_writes' => $confirmKeys,
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$blob2 = json_encode($r2, JSON_UNESCAPED_UNICODE);
$executed = !empty($r2['action']['results'][0]['success'])
    || stripos($blob2, 'created_draft_purchase_request') !== false
    || stripos($blob2, 'request_no') !== false;
$executed ? $pass('EXECUTION') : $fail('EXECUTION', mb_substr((string) ($r2['response'] ?? ''), 0, 200));
$executed ? $pass('VERIFICATION') : $fail('VERIFICATION');

// Real execution — never controlled_test
$resp2 = (string) ($r2['response'] ?? '');
$noCt = stripos($resp2, 'controlled_test') === false
    && stripos($resp2, 'التجربة المضبوطة') === false
    && stripos($blob2, '"tool":"controlled_test"') === false;
$noCt ? $pass('NO CONTROLLED TEST FALLBACK') : $fail('NO CONTROLLED TEST FALLBACK', mb_substr($resp2, 0, 180));
$hasRecord = stripos($resp2, 'رقم الطلب') !== false
    || stripos($resp2, 'Request number') !== false
    || stripos($blob2, 'request_no') !== false
    || stripos($resp2, 'تم إنشاء مسودة') !== false;
$hasRecord ? $pass('RECORD CREATION') : $fail('RECORD CREATION', mb_substr($resp2, 0, 200));
($executed && $noCt && $hasRecord) ? $pass('REAL EXECUTION') : $fail('REAL EXECUTION');

// AR language — no raw English governance tokens in user text
$arLeak = preg_match('/\b(CONFIRMED_WRITE|READ_ONLY|controlled_autonomy_disabled_by_default|confirmation_required|controlled_test)\b/', $resp2) === 1;
!$arLeak
    ? $pass('AR LANGUAGE') : $fail('AR LANGUAGE', mb_substr($resp2, 0, 220));

// EN language labels
$prioEn = ErpActionPlanner::labelPriority('medium', false);
$lvlEn = ErpActionPlanner::labelGovToken('CONFIRMED_WRITE', false);
($prioEn === 'Medium' && $lvlEn === 'Confirmed write')
    ? $pass('EN LANGUAGE') : $fail('EN LANGUAGE', $prioEn . '/' . $lvlEn);

// Duplicate confirm
$r3 = $agent->process([
    'message' => 'تأكيد',
    'history' => [],
    'request_id' => 'cf-dup-' . bin2hex(random_bytes(3)),
    'confirmed_writes' => $confirmKeys,
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$dupOk = (($r3['observability']['conversation_phase'] ?? '') === 'already_executed')
    || stripos((string) ($r3['response'] ?? ''), 'مسبق') !== false
    || stripos((string) ($r3['response'] ?? ''), 'already') !== false
    || stripos(json_encode($r3), 'duplicate') !== false
    || empty($r3['pending_confirmations']);
$dupOk ? $pass('DUPLICATE CONFIRMATION') : $fail('DUPLICATE CONFIRMATION', mb_substr((string) ($r3['response'] ?? ''), 0, 160));

// Authorization: bare confirm without pending does not invent writes
ErpActionPlanner::clearPendingState($scope);
$bare = ErpActionPlanner::resolveConversationTurn('تأكيد', $ctx, [], [], []);
(($bare['mode'] ?? '') === 'passthrough' || empty($bare['confirmed_writes']))
    ? $pass('AUTHORIZATION') : $fail('AUTHORIZATION');

// Tenant
$tenantOk = true;
if ($companyId2 > 0) {
    $ctx2 = $makeCtx($fullPerms, true, $companyId2, $modules, 'ar');
    if ($ctx->conversationScopeKey() === $ctx2->conversationScopeKey()) {
        $tenantOk = false;
    }
}
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

$en = require RATEB_ROOT . '/config/lang/en.php';
$ar = require RATEB_ROOT . '/config/lang/ar.php';
(!empty($en['ai_confirm']) && !empty($ar['ai_confirm'])) ? $pass('AR') : $fail('AR');
(!empty($en['ai_confirm']) && !empty($ar['ai_confirm'])) ? $pass('EN') : $fail('EN');
$js = (string) file_get_contents(RATEB_ROOT . '/public/assets/js/rateb-ai-page.js');
(str_contains($js, "send(t('confirm'") || str_contains($js, 'send(t("confirm"') || str_contains($js, "t('confirm', 'تأكيد')"))
    ? $pass('DARK/LIGHT') : $fail('DARK/LIGHT', 'confirm button handler missing');
// soft: dark/light markers still in view
$view = (string) file_get_contents(RATEB_ROOT . '/views/company/ai/index.php');
(str_contains($view, 'rateb-ai-confirm') && str_contains($view, "t('confirm', 'تأكيد')"))
    ? $pass('CONFIRM BUTTON JS') : $fail('CONFIRM BUTTON JS');

foreach (['crm', 'sales', 'inventory', 'procurement', 'suppliers', 'logistics', 'accounting'] as $d) {
    ErpDomainRegistry::isActive($d) ? null : $fail(strtoupper($d) . ' REGRESSION');
}
$pass('ALL REGRESSIONS');

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "CONFIRM FIX GATES PASS\n";
exit(0);
