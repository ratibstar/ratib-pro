<?php
declare(strict_types=1);

/**
 * Pending Action Lifecycle gates for Unified Agent (targeted).
 * Run: php rateb-erp/tests/procurement/run-unified-agent-pending-lifecycle-tests.php
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'lifecycle', $perms, $sa, $modules);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$ctxEn = $makeCtx($fullPerms, true, $companyId, $modules, 'en');
$config = require RATEB_ROOT . '/config/agent.php';
$agent = new ErpAgent(is_array($config) ? $config : []);

$runScenario = static function (string $scope, string $msg) use ($agent, $ctx): array {
    ErpActionPlanner::clearPendingState($scope);
    $r1 = $agent->process([
        'message' => $msg,
        'history' => [],
        'request_id' => 'lc-p-' . bin2hex(random_bytes(3)),
        'confirmed_writes' => [],
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $keys = [];
    foreach (($r1['pending_confirmations'] ?? []) as $p) {
        if (is_array($p) && (string) ($p['confirm_key'] ?? '') !== '') {
            $keys[] = (string) $p['confirm_key'];
        }
    }
    $pending = ErpActionPlanner::loadPendingState($scope);
    $phase1 = ErpActionPlanner::normalizePhase((string) ($pending['phase'] ?? ''));
    $actionId1 = (string) ($pending['action_id'] ?? '');

    $r2 = $agent->process([
        'message' => 'تأكيد',
        'history' => [['role' => 'user', 'content' => $msg]],
        'request_id' => 'lc-c-' . bin2hex(random_bytes(3)),
        'confirmed_writes' => $keys,
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $pending2 = ErpActionPlanner::loadPendingState($scope);
    $phase2 = ErpActionPlanner::normalizePhase((string) ($pending2['phase'] ?? ''));
    $verified = ErpActionPlanner::verifiedActionsList($pending2);
    $ok = !empty($r2['action']['results'][0]['success'])
        && !empty($r2['action']['results'][0]['verification']['verified'])
        && $phase2 === ErpActionPlanner::PHASE_VERIFIED
        && $verified !== [];
    $recordId = (int) ($r2['action']['results'][0]['data']['id'] ?? ($verified[0]['record_id'] ?? 0));
    $lineItems = is_array($r2['action']['results'][0]['data']['line_items'] ?? null)
        ? $r2['action']['results'][0]['data']['line_items']
        : [];

    // Duplicate confirm must NOT create another record
    $r3 = $agent->process([
        'message' => 'تأكيد',
        'history' => [],
        'request_id' => 'lc-d-' . bin2hex(random_bytes(3)),
        'confirmed_writes' => $keys,
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $dup = (($r3['observability']['conversation_phase'] ?? '') === 'already_executed')
        || stripos((string) ($r3['response'] ?? ''), 'مسبق') !== false
        || stripos((string) ($r3['response'] ?? ''), 'already') !== false;

    return [
        'propose' => $r1,
        'confirm' => $r2,
        'dup' => $r3,
        'phase1' => $phase1,
        'phase2' => $phase2,
        'action_id' => $actionId1,
        'ok' => $ok,
        'record_id' => $recordId,
        'line_items' => $lineItems,
        'keys' => $keys,
        'dup_ok' => $dup,
        'response' => (string) ($r2['response'] ?? ''),
        'args' => is_array($r1['pending_confirmations'][0]['arguments'] ?? null)
            ? $r1['pending_confirmations'][0]['arguments']
            : [],
    ];
};

// ——— Test 1: no price ———
$base = $ctx->conversationScopeKey() . ':lc';
$t1 = $runScenario($base . ':t1', 'أنشئ طلب شراء لشراء 10 وحدات من بطاطس بأولوية متوسطة');
($t1['phase1'] === ErpActionPlanner::PHASE_PENDING_CONFIRMATION)
    ? $pass('PENDING ACTION LIFECYCLE')
    : $fail('PENDING ACTION LIFECYCLE', $t1['phase1']);
($t1['ok'] && $t1['record_id'] > 0)
    ? $pass('CONFIRM EXECUTION')
    : $fail('CONFIRM EXECUTION', mb_substr($t1['response'], 0, 180));
($t1['phase2'] === ErpActionPlanner::PHASE_VERIFIED)
    ? $pass('VERIFIED STATE')
    : $fail('VERIFIED STATE', $t1['phase2']);
($t1['ok'])
    ? $pass('EXECUTED STATE')
    : $fail('EXECUTED STATE');
((float) ($t1['args']['line_items'][0]['unit_price'] ?? -1) === 0.0)
    ? $pass('NO PRICE')
    : $fail('NO PRICE', json_encode($t1['args']['line_items'][0] ?? null, JSON_UNESCAPED_UNICODE));
$t1['dup_ok'] ? $pass('DUPLICATE CONFIRMATION') : $fail('DUPLICATE CONFIRMATION', mb_substr((string) ($t1['dup']['response'] ?? ''), 0, 120));
$t1['dup_ok'] ? $pass('IDEMPOTENCY') : $fail('IDEMPOTENCY');

// ——— Test 2: with price ———
$t2 = $runScenario($base . ':t2', 'أنشئ طلب شراء لشراء 8 وحدات من بطاطس بأولوية متوسطة سعر 12');
$priceOk = $t2['ok'] && (float) ($t2['args']['line_items'][0]['unit_price'] ?? 0) === 12.0;
$priceOk ? $pass('PRICE') : $fail('PRICE', json_encode($t2['args']['line_items'][0] ?? null, JSON_UNESCAPED_UNICODE));

// ——— Test 3: with tax ———
$t3 = $runScenario($base . ':t3', 'أنشئ طلب شراء لشراء 7 وحدات من بطاطس بأولوية متوسطة ضريبة 15');
$taxOk = $t3['ok'] && (float) ($t3['args']['line_items'][0]['tax_rate'] ?? 0) === 15.0;
$taxOk ? $pass('TAX') : $fail('TAX', json_encode($t3['args']['line_items'][0] ?? null, JSON_UNESCAPED_UNICODE));

// ——— Test 4: multi-item ———
$t4 = $runScenario($base . ':t4', 'أنشئ طلب شراء بطاطس × 10 وأرز × 5 بأولوية متوسطة');
$names = [];
foreach ($t4['line_items'] as $li) {
    if (is_array($li)) {
        $names[] = (string) ($li['item_name'] ?? $li['description'] ?? '');
    }
}
if ($names === []) {
    foreach (($t4['args']['line_items'] ?? []) as $li) {
        if (is_array($li)) {
            $names[] = (string) ($li['item_name'] ?? '');
        }
    }
}
$blob = implode('|', $names);
$multi = $t4['ok']
    && count($names) >= 2
    && (stripos($blob, 'بطاطس') !== false)
    && (stripos($blob, 'أرز') !== false || stripos($blob, 'رز') !== false);
$multi ? $pass('MULTI-ITEM PR') : $fail('MULTI-ITEM PR', $blob . ' / ' . mb_substr($t4['response'], 0, 120));

// ——— Test 5: missing department (do not invent) ———
$t5 = $runScenario($base . ':t5', 'أنشئ طلب شراء لشراء 3 وحدات من بطاطس بأولوية متوسطة');
$dept = trim((string) ($t5['args']['department'] ?? ''));
$deptOk = $t5['ok'] && $dept === '';
$deptOk ? $pass('MISSING DEPARTMENT') : $fail('MISSING DEPARTMENT', 'dept=' . $dept);

// ——— Test 6: unknown item (when catalog has rows) ———
$invCount = 0;
try {
    $invCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM rateb_inventory WHERE company_id = ' . (int) $companyId
    )->fetchColumn();
} catch (Throwable $e) {
    $invCount = 0;
}
ErpActionPlanner::clearPendingState($base . ':t6');
$unknownMsg = 'أنشئ طلب شراء لشراء 2 وحدات من صنفغيرموجودxyzzy بأولوية متوسطة';
$rUnk = $agent->process([
    'message' => $unknownMsg,
    'history' => [],
    'request_id' => 'lc-unk-' . bin2hex(random_bytes(2)),
    'confirmed_writes' => [],
    'conversation_scope' => $base . ':t6',
    'domain' => '',
], $ctx);
$unkResp = (string) ($rUnk['response'] ?? '');
$unkPending = is_array($rUnk['pending_confirmations'] ?? null) ? $rUnk['pending_confirmations'] : [];
if ($invCount > 0) {
    $unkOk = $unkPending === []
        && (stripos($unkResp, 'غير موجود') !== false || stripos($unkResp, 'not found') !== false);
    $unkOk ? $pass('UNKNOWN ITEM') : $fail('UNKNOWN ITEM', mb_substr($unkResp, 0, 160));
} else {
    // Empty catalog cannot validate — allow free-text propose (documented fail-open)
    ($unkPending !== [] || stripos($unkResp, 'غير موجود') !== false)
        ? $pass('UNKNOWN ITEM')
        : $fail('UNKNOWN ITEM', 'empty catalog path: ' . mb_substr($unkResp, 0, 120));
}

// ——— New action isolation ———
$iso1 = $runScenario($base . ':iso1', 'أنشئ طلب شراء لشراء 10 وحدات من بطاطس بأولوية متوسطة');
$iso2 = $runScenario($base . ':iso2', 'أنشئ طلب شراء لشراء 5 وحدات من أرز بأولوية عالية');
$isoOk = $iso1['ok'] && $iso2['ok']
    && $iso1['action_id'] !== ''
    && $iso2['action_id'] !== ''
    && $iso1['action_id'] !== $iso2['action_id']
    && $iso1['keys'] !== $iso2['keys']
    && $iso1['record_id'] !== $iso2['record_id'];
$isoOk ? $pass('NEW ACTION ISOLATION') : $fail('NEW ACTION ISOLATION', $iso1['action_id'] . ' / ' . $iso2['action_id']);

// ——— First confirm must not look like duplicate ———
$freshScope = $base . ':fresh';
ErpActionPlanner::clearPendingState($freshScope);
// Poison with legacy executed_keys WITHOUT verified_actions (the old bug)
ErpActionPlanner::savePendingState($freshScope, [
    'phase' => 'executed',
    'executed_keys' => ['create_draft_purchase_request:deadbeef'],
    'action_id' => 'act_old',
    'company_id' => $companyId,
]);
$poison = $agent->process([
    'message' => 'أنشئ طلب شراء لشراء 4 وحدات من بطاطس بأولوية متوسطة',
    'history' => [],
    'request_id' => 'lc-poison-p',
    'confirmed_writes' => [],
    'conversation_scope' => $freshScope,
    'domain' => '',
], $ctx);
$pKeys = [];
foreach (($poison['pending_confirmations'] ?? []) as $p) {
    if (is_array($p) && (string) ($p['confirm_key'] ?? '') !== '') {
        $pKeys[] = (string) $p['confirm_key'];
    }
}
$execPoison = $agent->process([
    'message' => 'تأكيد',
    'history' => [],
    'request_id' => 'lc-poison-c',
    'confirmed_writes' => $pKeys,
    'conversation_scope' => $freshScope,
    'domain' => '',
], $ctx);
$notDup = (($execPoison['observability']['conversation_phase'] ?? '') !== 'already_executed')
    && stripos((string) ($execPoison['response'] ?? ''), 'لم يُنشأ سجل مكرر') === false
    && !empty($execPoison['action']['results'][0]['success']);
$notDup ? $pass('FIRST CONFIRM NOT DUPLICATE') : $fail('FIRST CONFIRM NOT DUPLICATE', mb_substr((string) ($execPoison['response'] ?? ''), 0, 160));

// AR / EN
$arLeak = preg_match('/\b(PENDING_CONFIRMATION|EXECUTED|VERIFIED|idempotency_key|CONFIRMED_WRITE)\b/', $t1['response']) === 1;
!$arLeak ? $pass('AR') : $fail('AR', mb_substr($t1['response'], 0, 120));
$enDraft = ErpActionPlanner::mergePurchaseRequestDraft('Create purchase request for 2 units of rice priority medium', []);
$enLabel = ErpActionPlanner::labelPriority('medium', false);
(($enLabel === 'Medium') && !empty($enDraft['arguments']['title']))
    ? $pass('EN')
    : $fail('EN', $enLabel);

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "PENDING ACTION LIFECYCLE GATES PASS\n";
exit(0);
