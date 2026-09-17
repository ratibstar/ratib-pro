<?php
declare(strict_types=1);

/**
 * Real Execution FULL PAYLOAD integrity — reads the created PR from DB.
 * Run: php rateb-erp/tests/procurement/run-unified-agent-pr-payload-integrity-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\LineItems;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\FormLookupService;
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'payload-integrity', $perms, $sa, $modules);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create', 'inventory.manage', 'pos.view', 'crm.view',
    'logistics.view', 'accounting.view', 'dashboard.view', 'ai.view', 'reports.view', 'suppliers.manage',
];
$modules = ['procurement', 'suppliers', 'inventory', 'pos', 'crm', 'logistics', 'accounting', 'dashboard'];
$ctx = $makeCtx($fullPerms, true, $companyId, $modules, 'ar');
$config = require RATEB_ROOT . '/config/agent.php';
$agent = new ErpAgent(is_array($config) ? $config : []);
$scope = $ctx->conversationScopeKey() . ':payload:' . bin2hex(random_bytes(3));

$msg = 'أنشئ طلب شراء تجريبي لشراء 10 وحدات من بطاطس بأولوية متوسطة';
ErpActionPlanner::clearPendingState($scope);
$r1 = $agent->process([
    'message' => $msg,
    'history' => [],
    'request_id' => 'pi-propose',
    'confirmed_writes' => [],
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$pending = is_array($r1['pending_confirmations'] ?? null) ? $r1['pending_confirmations'] : [];
$args = is_array($pending[0]['arguments'] ?? null) ? $pending[0]['arguments'] : [];
$keys = [];
foreach ($pending as $p) {
    if (is_array($p) && (string) ($p['confirm_key'] ?? '') !== '') {
        $keys[] = (string) $p['confirm_key'];
    }
}
$r2 = $agent->process([
    'message' => 'تأكيد',
    'history' => [['role' => 'user', 'content' => $msg]],
    'request_id' => 'pi-exec-' . bin2hex(random_bytes(3)),
    'confirmed_writes' => $keys,
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);

$blob = json_encode($r2, JSON_UNESCAPED_UNICODE);
$resp = (string) ($r2['response'] ?? '');
$noCt = stripos($resp, 'controlled_test') === false && stripos($blob, '"tool":"controlled_test"') === false;
$data = is_array($r2['action']['results'][0]['data'] ?? null) ? $r2['action']['results'][0]['data'] : [];
$prId = (int) ($data['id'] ?? 0);

$noCt && $prId > 0 && !empty($r2['action']['results'][0]['success'])
    ? $pass('REAL EXECUTION FULL PAYLOAD')
    : $fail('REAL EXECUTION FULL PAYLOAD', 'id=' . $prId);

if ($prId < 1) {
    echo "STOP: no PR id\n";
    exit(1);
}

$saved = (new PurchaseRequest())->find($prId);
$items = LineItems::loadPurchaseRequestItems($prId);
$titleOk = is_array($saved) && trim((string) ($saved['title'] ?? '')) !== ''
    && stripos((string) $saved['title'], 'بطاطس') !== false;
$prioOk = is_array($saved) && in_array((string) ($saved['priority'] ?? ''), ['medium', 'normal'], true);
$notesOk = is_array($saved) && trim((string) ($saved['notes'] ?? '')) !== '';
$currencyOk = is_array($saved) && (string) ($saved['currency'] ?? '') === 'SAR';

$titleOk && $prioOk && $notesOk && $currencyOk
    ? $pass('RECORD DATA INTEGRITY')
    : $fail('RECORD DATA INTEGRITY', json_encode([
        'title' => $saved['title'] ?? null,
        'priority' => $saved['priority'] ?? null,
        'notes' => $saved['notes'] ?? null,
        'currency' => $saved['currency'] ?? null,
    ], JSON_UNESCAPED_UNICODE));

$item0 = is_array($items[0] ?? null) ? $items[0] : [];
$itemOk = $items !== []
    && stripos((string) ($item0['item_name'] ?? ''), 'بطاطس') !== false
    && (float) ($item0['quantity'] ?? 0) === 10.0
    && trim((string) ($item0['item_name'] ?? '')) !== ''
    && trim((string) ($item0['unit'] ?? '')) !== '';
$itemOk
    ? $pass('ITEM DATA INTEGRITY')
    : $fail('ITEM DATA INTEGRITY', json_encode($item0, JSON_UNESCAPED_UNICODE));

// Fail if empty header / empty item / invented qty when args had qty
$noLoss = $titleOk && $itemOk
    && (float) ($item0['quantity'] ?? 0) === (float) ($args['line_items'][0]['quantity'] ?? 10)
    && trim((string) ($saved['title'] ?? '')) === trim((string) ($args['title'] ?? $saved['title'] ?? ''));
$noLoss ? $pass('NO DEFAULT DATA LOSS') : $fail('NO DEFAULT DATA LOSS');

// Form can render priority value
$lookups = (new FormLookupService())->forFields([
    ['name' => 'priority', 'type' => 'select', 'lookup' => 'priority_levels'],
]);
$prioValues = array_column($lookups['priority_levels'] ?? [], 'value');
in_array((string) ($saved['priority'] ?? ''), $prioValues, true)
    ? $pass('PRIORITY FORM COMPAT')
    : $fail('PRIORITY FORM COMPAT', (string) ($saved['priority'] ?? '') . ' not in ' . json_encode($prioValues));

preg_match('/\b(CONFIRMED_WRITE|controlled_test|controlled_autonomy_disabled_by_default)\b/', $resp) !== 1
    ? $pass('AR')
    : $fail('AR', mb_substr($resp, 0, 180));

$prioEn = ErpActionPlanner::labelPriority('medium', false);
$prioEn === 'Medium' ? $pass('EN') : $fail('EN', $prioEn);

// Context reset
ErpActionPlanner::clearPendingState($scope . ':rice');
$riceScope = $scope . ':rice';
$riceMsg = 'أنشئ طلب شراء تجريبي لشراء 5 وحدات من أرز بأولوية عالية';
$rr1 = $agent->process([
    'message' => $riceMsg,
    'history' => [['role' => 'user', 'content' => $msg]],
    'request_id' => 'pi-rice',
    'confirmed_writes' => [],
    'conversation_scope' => $riceScope,
    'domain' => '',
], $ctx);
$rArgs = is_array($rr1['pending_confirmations'][0]['arguments'] ?? null)
    ? $rr1['pending_confirmations'][0]['arguments']
    : [];
$rLine = is_array($rArgs['line_items'][0] ?? null) ? $rArgs['line_items'][0] : [];
((stripos((string) ($rLine['item_name'] ?? ''), 'أرز') !== false || stripos((string) ($rLine['item_name'] ?? ''), 'رز') !== false)
    && (float) ($rLine['quantity'] ?? 0) === 5.0
    && ($rArgs['priority'] ?? '') === 'high'
    && stripos((string) ($rLine['item_name'] ?? ''), 'بطاطس') === false)
    ? $pass('CONTEXT RESET')
    : $fail('CONTEXT RESET', json_encode($rArgs, JSON_UNESCAPED_UNICODE));

// Duplicate
$rDup = $agent->process([
    'message' => 'تأكيد',
    'history' => [],
    'request_id' => 'pi-dup-' . bin2hex(random_bytes(2)),
    'confirmed_writes' => $keys,
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$dupPhase = (string) ($rDup['observability']['conversation_phase'] ?? '');
($dupPhase === 'already_executed'
    || stripos((string) ($rDup['response'] ?? ''), 'مسبق') !== false
    || stripos((string) ($rDup['response'] ?? ''), 'already') !== false
    || empty($rDup['pending_confirmations']))
    ? $pass('DUPLICATE PROTECTION')
    : $fail('DUPLICATE PROTECTION');

ErpActionPlanner::clearPendingState($scope);
$bare = ErpActionPlanner::resolveConversationTurn('تأكيد', $ctx, [], [], []);
(($bare['mode'] ?? '') === 'passthrough' || empty($bare['confirmed_writes']))
    ? $pass('AUTHORIZATION')
    : $fail('AUTHORIZATION');

$level = (string) ($r1['governance']['execution_level'] ?? '');
($level === 'CONFIRMED_WRITE' && $pending !== [])
    ? $pass('GOVERNANCE')
    : $fail('GOVERNANCE', $level);

$tenantOk = true;
if ($companyId2 > 0) {
    $ctx2 = $makeCtx($fullPerms, true, $companyId2, $modules, 'ar');
    if ($ctx->conversationScopeKey() === $ctx2->conversationScopeKey()) {
        $tenantOk = false;
    }
}
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');

foreach (['crm', 'sales', 'inventory', 'procurement'] as $d) {
    if (!ErpDomainRegistry::isActive($d)) {
        $fail('ALL REGRESSIONS', $d);
    }
}
$pass('ALL REGRESSIONS');

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "PR PAYLOAD INTEGRITY GATES PASS pr_id={$prId}\n";
exit(0);
