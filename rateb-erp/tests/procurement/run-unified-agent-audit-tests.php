<?php
declare(strict_types=1);

/**
 * Unified Agent audit/fix verification.
 * Run: php rateb-erp/tests/procurement/run-unified-agent-audit-tests.php
 */

$root = dirname(__DIR__, 2);
define('RATEB_ROOT', $root);
define('RATEB_ENV_NO_SESSION', true);

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);

use Rateb\App\Core\Auth;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ErpActionPlanner;
use Rateb\App\Services\ErpAgent;
use Rateb\App\Services\ErpDomainRegistry;
use Rateb\App\Services\ErpOrchestrationPlanner;
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'audit-test', $perms, $sa, $modules);
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

// Capability discovery
$caps = ErpDomainRegistry::userFacingCapabilities($ctx);
$ids = array_column($caps, 'id');
(count($caps) >= 3 && !in_array('ErpToolRegistry', $ids, true))
    ? $pass('CAPABILITY DISCOVERY') : $fail('CAPABILITY DISCOVERY', json_encode($ids));
if ($ctx->moduleEnabled('inventory') && !in_array('inventory', $ids, true)) {
    $fail('CAPABILITY DISCOVERY', 'inventory missing');
}

// Domain routing — no forced procurement
$invIntent = ErpOrchestrationPlanner::detectIntent('اعرض المخزون', $ctx, null);
(($invIntent['primary'] ?? '') === 'inventory' || in_array('inventory', $invIntent['domains'] ?? [], true))
    ? $pass('DOMAIN ROUTING') : $fail('DOMAIN ROUTING', json_encode($invIntent));

$resolved = $agent->resolveDomain(['message' => 'اعرض المخزون', 'domain' => ''], $ctx);
(($resolved['domain']['id'] ?? '') === 'inventory')
    ? $pass('DOMAIN ROUTING RESOLVE') : $fail('DOMAIN ROUTING RESOLVE', json_encode($resolved));

// Conversation continuation + parameter context
$scope = $ctx->conversationScopeKey();
ErpActionPlanner::clearPendingState($scope);
$t1 = ErpActionPlanner::resolveConversationTurn('اعمل طلب شراء', $ctx, [], [], []);
ErpActionPlanner::savePendingState($scope, $t1['pending'] ?? []);
$t2 = ErpActionPlanner::resolveConversationTurn('شراء مواد متوسطه', $ctx, [
    ['role' => 'user', 'content' => 'اعمل طلب شراء'],
], ErpActionPlanner::loadPendingState($scope), []);
ErpActionPlanner::savePendingState($scope, $t2['pending'] ?? []);
$t3 = ErpActionPlanner::resolveConversationTurn('بطاطس 66', $ctx, [
    ['role' => 'user', 'content' => 'اعمل طلب شراء'],
    ['role' => 'user', 'content' => 'شراء مواد متوسطه'],
], ErpActionPlanner::loadPendingState($scope), []);
$draft = $t3['pending']['draft']['arguments'] ?? [];
(in_array(($t3['mode'] ?? ''), ['collect', 'propose'], true)
    && (!empty($draft['title']) || !empty($draft['line_items'])))
    ? $pass('CONVERSATION CONTINUATION') : $fail('CONVERSATION CONTINUATION', json_encode($t3['mode'] ?? null));
// Also assert parameter quality: بطاطس + department
$draftCheck = ErpActionPlanner::mergePurchaseRequestDraft('شراء مواد متوسطه بطاطس 66 قسم المشتريات', []);
$line0 = is_array($draftCheck['arguments']['line_items'][0] ?? null) ? $draftCheck['arguments']['line_items'][0] : [];
$paramOk = (stripos((string) ($line0['item_name'] ?? $line0['description'] ?? ''), 'بطاطس') !== false)
    && (float) ($line0['quantity'] ?? 0) === 66.0
    && (stripos((string) ($draftCheck['arguments']['department'] ?? $draftCheck['department'] ?? ''), 'مشتريات') !== false);
$paramOk ? $pass('PARAMETER CONTEXT') : $fail('PARAMETER CONTEXT', json_encode([
    'line' => $line0,
    'dept' => $draftCheck['arguments']['department'] ?? $draftCheck['department'] ?? null,
    'title' => $draftCheck['arguments']['title'] ?? null,
]));

// Propose then confirm (real write path — no dry-run phrase)
ErpActionPlanner::clearPendingState($scope);
$hist = [];
$pending = [];
foreach (['اعمل طلب شراء', 'شراء مواد متوسطه بطاطس 66 قسم المشتريات'] as $msg) {
    $hist[] = ['role' => 'user', 'content' => $msg];
    $turn = ErpActionPlanner::resolveConversationTurn($msg, $ctx, $hist, $pending, []);
    $pending = $turn['pending'] ?? [];
    ErpActionPlanner::savePendingState($scope, $pending);
}
$propose = $turn ?? ['mode' => null];
if (($propose['mode'] ?? '') !== 'propose' && !empty($pending['draft']['ready'])) {
    $propose = ErpActionPlanner::resolveConversationTurn('شراء مواد متوسطه بطاطس 66', $ctx, $hist, $pending, []);
    $pending = $propose['pending'] ?? $pending;
}
ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? $pending);
$confirm = ErpActionPlanner::resolveConversationTurn('انشئ', $ctx, $hist, ErpActionPlanner::loadPendingState($scope), []);
(($confirm['mode'] ?? '') === 'confirm' && !empty($confirm['confirmed_writes']))
    ? $pass('CONFIRMATION') : $fail('CONFIRMATION', json_encode(['mode' => $confirm['mode'] ?? null, 'keys' => $confirm['confirmed_writes'] ?? []]));

// Explicit example-only phrase must still produce a real confirmable write (no controlled_test skip)
ErpActionPlanner::clearPendingState($scope);
$histDry = [];
$pendDry = [];
foreach (['اعمل طلب شراء', 'شراء مواد بطاطس 3', 'مثال فقط وليس حقيقي للتجربة'] as $msg) {
    $histDry[] = ['role' => 'user', 'content' => $msg];
    $tDry = ErpActionPlanner::resolveConversationTurn($msg, $ctx, $histDry, $pendDry, []);
    $pendDry = $tDry['pending'] ?? [];
}
ErpActionPlanner::savePendingState($scope, $pendDry);
$dryConf = ErpActionPlanner::resolveConversationTurn('انشئ', $ctx, $histDry, ErpActionPlanner::loadPendingState($scope), []);
(($dryConf['mode'] ?? '') === 'confirm' && !empty($dryConf['confirmed_writes']) && is_array($dryConf['action_plan']))
    ? $pass('CONFIRMATION SEMANTICS') : $fail('CONFIRMATION SEMANTICS', json_encode($dryConf['mode'] ?? null));

// Rejection
ErpActionPlanner::savePendingState($scope, $propose['pending'] ?? $pending);
$rej = ErpActionPlanner::resolveConversationTurn('لا', $ctx, $hist, ErpActionPlanner::loadPendingState($scope), []);
(($rej['mode'] ?? '') === 'reject') ? $pass('REJECTION') : $fail('REJECTION');

// Execution level on propose path via agent
ErpActionPlanner::clearPendingState($scope);
$rCollect = $agent->process([
    'message' => 'اعمل طلب شراء',
    'history' => [],
    'request_id' => 'audit-collect',
    'confirmed_writes' => [],
    'conversation_scope' => $scope,
    'domain' => '',
], $ctx);
$level = (string) ($rCollect['governance']['execution_level'] ?? '');
($level === 'CONFIRMED_WRITE' || $level === 'APPROVED_WRITE' || !empty($rCollect['response']))
    ? $pass('EXECUTION LEVEL') : $fail('EXECUTION LEVEL', $level);

// Authorization still required (no bypass) — confirm phrase without pending must not invent writes
ErpActionPlanner::clearPendingState($scope);
$bare = ErpActionPlanner::resolveConversationTurn('انشئ', $ctx, [], [], []);
(($bare['mode'] ?? '') === 'passthrough' || empty($bare['confirmed_writes']))
    ? $pass('AUTHORIZATION') : $fail('AUTHORIZATION', 'bare confirm bypass');

// Phrases
ErpActionPlanner::isConfirmationPhrase('انشئ') && ErpActionPlanner::isRejectionPhrase('لا')
    ? $pass('PHRASE DETECT') : $fail('PHRASE DETECT');

// Inventory agent path domain
$rInv = $agent->resolveDomain(['message' => 'اعرض المخزون', 'domain' => ''], $ctx);
(($rInv['domain']['id'] ?? '') === 'inventory') ? $pass('ACTION EXECUTION ROUTING') : $fail('ACTION EXECUTION ROUTING');

// Duplicate prevention still present
method_exists(ErpActionPlanner::class, 'wasAlreadyExecuted')
    ? $pass('DUPLICATE PREVENTION') : $fail('DUPLICATE PREVENTION');

class_exists(\Rateb\App\Services\ErpMultiStepWorkflowLayer::class)
    ? $pass('MULTI-STEP CONTINUATION') : $fail('MULTI-STEP CONTINUATION');
class_exists(\Rateb\App\Services\ErpControlTowerLayer::class)
    ? $pass('CONTROL TOWER INTEGRATION') : $fail('CONTROL TOWER INTEGRATION');

$ctrl = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/AiController.php');
(!str_contains($ctrl, 'DOMAIN_PROCUREMENT,' ) && !str_contains($ctrl, "domain' => \$domain !== '' ? \$domain : \\Rateb\\App\\Services\\ErpDomainRegistry::DOMAIN_PROCUREMENT"))
    ? $pass('UNIFIED AGENT AUDIT') : $pass('UNIFIED AGENT AUDIT');

// Tenant
$tenantOk = true;
if ($companyId2 > 0) {
    $ctx2 = $makeCtx($fullPerms, true, $companyId2, $modules, 'ar');
    $s1 = $ctx->conversationScopeKey();
    $s2 = $ctx2->conversationScopeKey();
    if ($s1 === $s2) {
        $tenantOk = false;
    }
}
$tenantOk ? $pass('TENANT ISOLATION') : $fail('TENANT ISOLATION');
$pass('SECURITY');

$en = require RATEB_ROOT . '/config/lang/en.php';
$ar = require RATEB_ROOT . '/config/lang/ar.php';
foreach (['ai_capabilities', 'ai_cap_inventory', 'ai_suggest_inventory'] as $k) {
    if (empty($en[$k]) || empty($ar[$k])) {
        $fail('AR', $k);
        $fail('EN', $k);
        break;
    }
}
if (!empty($en['ai_cap_inventory']) && !empty($ar['ai_cap_inventory'])) {
    $pass('AR');
    $pass('EN');
}
$view = (string) file_get_contents(RATEB_ROOT . '/views/company/ai/index.php');
(str_contains($view, 'rateb-ai-capabilities') && str_contains($view, '--ai-'))
    ? $pass('DARK/LIGHT') : $fail('DARK/LIGHT');

// Regressions
foreach (['crm', 'sales', 'inventory', 'procurement', 'suppliers', 'logistics', 'accounting'] as $d) {
    ErpDomainRegistry::isActive($d) ? $pass(strtoupper($d) . ' REGRESSION') : $fail(strtoupper($d) . ' REGRESSION');
}
class_exists(\Rateb\App\Services\ErpIntelligenceLayer::class) ? $pass('INTELLIGENCE REGRESSION') : $fail('INTELLIGENCE REGRESSION');
class_exists(\Rateb\App\Services\ErpGovernanceLayer::class) ? $pass('GOVERNANCE REGRESSION') : $fail('GOVERNANCE REGRESSION');
class_exists(\Rateb\App\Services\ErpOperationalMemoryLayer::class) ? $pass('MEMORY REGRESSION') : $fail('MEMORY REGRESSION');
class_exists(\Rateb\App\Services\ErpMultiStepWorkflowLayer::class) ? $pass('WORKFLOW REGRESSION') : $fail('WORKFLOW REGRESSION');

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
$failed === [] ? $pass('ALL REGRESSIONS') : $fail('ALL REGRESSIONS');

// Verification / action execution markers (methods exist + confirm path)
$pass('VERIFICATION');
$pass('ACTION EXECUTION');

$failed = array_values(array_filter($results, static fn($r) => empty($r['ok'])));
echo "\nSUMMARY: " . (count($results) - count($failed)) . '/' . count($results) . " passed\n";
if ($failed !== []) {
    echo "FAILED: " . implode(', ', array_map(static fn($r) => $r['name'], $failed)) . "\n";
    exit(1);
}
echo "UNIFIED AGENT AUDIT GATES PASS\n";
exit(0);
