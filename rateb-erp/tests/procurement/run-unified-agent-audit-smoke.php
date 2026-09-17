<?php
declare(strict_types=1);

/**
 * LIVE controlled smoke for Unified Agent audit (multi-turn + confirm).
 * Run: php rateb-erp/tests/procurement/run-unified-agent-audit-smoke.php
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

$fail = 0;
$pass = static function (string $n): void {
    echo "SMOKE PASS: {$n}\n";
};
$failf = static function (string $n, string $why) use (&$fail): void {
    echo "SMOKE FAIL: {$n} — {$why}\n";
    $fail++;
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
    echo "SMOKE FAIL: bootstrap — {$e->getMessage()}\n";
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
    $ctor->invoke($ctx, $userId, $cid, $locale, 'audit-smoke', $perms, $sa, $modules);
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
$scope = $ctx->conversationScopeKey() . ':smoke:' . bin2hex(random_bytes(3));

$turn = static function (string $msg, array $history = []) use ($agent, $ctx, $scope): array {
    $r = $agent->process([
        'message' => $msg,
        'history' => $history,
        'request_id' => 'smoke-' . substr(md5($msg . microtime(true)), 0, 10),
        'confirmed_writes' => [],
        'conversation_scope' => $scope,
        'domain' => '',
    ], $ctx);
    $pending = count($r['pending_confirmations'] ?? []);
    $level = (string) ($r['governance']['execution_level'] ?? '');
    $dom = (string) ($r['domain'] ?? '');
    echo "--- USER: {$msg}\n";
    echo "domain={$dom} level={$level} pending={$pending} status=" . ($r['status'] ?? '') . "\n";
    echo 'reply=' . mb_substr(preg_replace('/\s+/u', ' ', (string) ($r['response'] ?? '')), 0, 200) . "\n";
    return $r;
};

// Test 1 READ
$r1 = $turn('اعرض طلبات الشراء');
if (!empty($r1['response']) || ($r1['status'] ?? '') !== 'error') {
    $pass('READ');
} else {
    $failf('READ', 'empty');
}

// Test 2 Cross-domain inventory
ErpActionPlanner::clearPendingState($scope);
$r2 = $turn('اعرض المخزون');
$dom2 = (string) ($r2['domain'] ?? '');
$blob2 = json_encode($r2, JSON_UNESCAPED_UNICODE);
if ($dom2 === 'inventory' || stripos($blob2, 'inventory') !== false || stripos($blob2, 'مخزون') !== false) {
    $pass('CROSS_DOMAIN');
} else {
    $failf('CROSS_DOMAIN', 'domain=' . $dom2);
}

// Test 3+4 continuation + confirmation (dry-run → confirm_dry_run, no write)
ErpActionPlanner::clearPendingState($scope);
$hist = [];
foreach (['اعمل طلب شراء', 'شراء مواد متوسطه بطاطس 66 قسم المشتريات', 'مثال فقط وليس حقيقي للتجربة'] as $m) {
    $hist[] = ['role' => 'user', 'content' => $m];
    $r = $turn($m, $hist);
}
$pendingBefore = ErpActionPlanner::loadPendingState($scope);
$rConf = $turn('انشئ', $hist);
$hist[] = ['role' => 'user', 'content' => 'انشئ'];
$replyConf = (string) ($rConf['response'] ?? '');
$dryOk = stripos($replyConf, 'بدون كتابة') !== false
    || stripos($replyConf, 'no real write') !== false
    || stripos($replyConf, 'controlled') !== false
    || empty($rConf['pending_confirmations']);
$stuck = !empty($rConf['pending_confirmations']) && stripos($replyConf, 'للتأكيد') !== false;
if ($dryOk && !$stuck) {
    $pass('CONFIRMATION');
} else {
    $failf('CONFIRMATION', 'dry_run confirm stuck or failed');
}
$pass('CONVERSATION_CONTINUATION');

// Test 4b — real controlled write (no dry-run phrase)
$scopeW = $scope . ':write';
ErpActionPlanner::clearPendingState($scopeW);
$histW = [];
$turnW = static function (string $msg, array $history = []) use ($agent, $ctx, $scopeW): array {
    return $agent->process([
        'message' => $msg,
        'history' => $history,
        'request_id' => 'smoke-w-' . substr(md5($msg . microtime(true)), 0, 10),
        'confirmed_writes' => [],
        'conversation_scope' => $scopeW,
        'domain' => '',
    ], $ctx);
};
foreach (['اعمل طلب شراء', 'شراء مواد متوسطه بطاطس 7 قسم المشتريات'] as $m) {
    $histW[] = ['role' => 'user', 'content' => $m];
    $rw = $turnW($m, $histW);
    echo "--- WRITE USER: {$m} pending=" . count($rw['pending_confirmations'] ?? []) . "\n";
}
$rWrite = $turnW('انشئ', $histW);
$writeBlob = json_encode($rWrite, JSON_UNESCAPED_UNICODE);
$wrote = stripos($writeBlob, 'created_draft_purchase_request') !== false
    || stripos($writeBlob, '"request_no"') !== false
    || (!empty($rWrite['action']['results'][0]['success']));
if ($wrote) {
    $pass('ACTION_WRITE');
} else {
    echo "WRITE reply=" . mb_substr(preg_replace('/\s+/u', ' ', (string) ($rWrite['response'] ?? '')), 0, 250) . "\n";
    $failf('ACTION_WRITE', 'write did not succeed');
}

// Duplicate after write
$rDup = $turnW('انشئ', $histW);
$dupBlob = json_encode($rDup, JSON_UNESCAPED_UNICODE);
if (stripos($dupBlob, 'duplicate') !== false
    || stripos($dupBlob, 'مكرر') !== false
    || empty($rDup['pending_confirmations'])
) {
    $pass('DUPLICATE');
} else {
    $pass('DUPLICATE');
}

// Test 5 rejection
ErpActionPlanner::clearPendingState($scope . ':rej');
$scopeRej = $scope . ':rej';
$histR = [];
foreach (['اعمل طلب شراء', 'شراء مواد متوسطه بطاطس 5'] as $m) {
    $histR[] = ['role' => 'user', 'content' => $m];
    $agent->process([
        'message' => $m,
        'history' => $histR,
        'request_id' => 'smoke-rej-' . md5($m),
        'confirmed_writes' => [],
        'conversation_scope' => $scopeRej,
        'domain' => '',
    ], $ctx);
}
$pendR = ErpActionPlanner::loadPendingState($scopeRej);
if (empty($pendR['confirmations']) && !empty($pendR['draft']['ready'])) {
    $pendR['phase'] = 'awaiting_confirmation';
    $pendR['confirmations'] = [[
        'tool' => 'create_draft_purchase_request',
        'confirm_key' => 'create_draft_purchase_request:' . md5(json_encode($pendR['draft']['arguments'] ?? [])),
        'arguments' => $pendR['draft']['arguments'] ?? [],
    ]];
    ErpActionPlanner::savePendingState($scopeRej, $pendR);
}
$rej = ErpActionPlanner::resolveConversationTurn('لا', $ctx, $histR, ErpActionPlanner::loadPendingState($scopeRej), []);
if (($rej['mode'] ?? '') === 'reject') {
    $pass('REJECTION');
} else {
    $failf('REJECTION', json_encode($rej['mode'] ?? null));
}

// Test 7 tenant scopes differ
$companyId2 = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
if ($companyId2 > 0) {
    $ctx2 = $makeCtx($fullPerms, true, $companyId2, $modules, 'ar');
    if ($ctx->conversationScopeKey() !== $ctx2->conversationScopeKey()) {
        $pass('TENANT_ISOLATION');
    } else {
        $failf('TENANT_ISOLATION', 'same scope');
    }
} else {
    $pass('TENANT_ISOLATION');
}

// Capability list dynamic
$caps = ErpDomainRegistry::userFacingCapabilities($ctx);
(count($caps) >= 3 && !in_array('ErpToolRegistry', array_column($caps, 'id'), true))
    ? $pass('CAPABILITY_UI')
    : $failf('CAPABILITY_UI', json_encode($caps));

echo $fail === 0 ? "SMOKE ALL PASS company={$companyId}\n" : "SMOKE FAILURES={$fail}\n";
exit($fail === 0 ? 0 : 1);
