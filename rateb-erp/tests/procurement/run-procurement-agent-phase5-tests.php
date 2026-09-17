<?php
declare(strict_types=1);

/**
 * Procurement Agent Phase 5 — production hardening & controlled autonomy gates.
 * Run: php rateb-erp/tests/procurement/run-procurement-agent-phase5-tests.php
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

final class Phase5MockLlm implements LlmClientInterface
{
    /** @var list<array{mode:string,payload?:array}> */
    private array $script;
    private int $i = 0;

    /** @param list<array{mode:string,payload?:array}> $script */
    public function __construct(array $script)
    {
        $this->script = $script;
    }

    public function chatCompletion(array $messages, array $tools, ?string $toolChoice = 'auto'): array
    {
        $step = $this->script[$this->i] ?? ['mode' => 'text', 'payload' => ['content' => 'done']];
        $this->i++;
        $mode = (string) ($step['mode'] ?? 'text');
        if ($mode === 'throw') {
            throw new RuntimeException((string) ($step['payload']['code'] ?? 'llm_request_failed'));
        }
        if ($mode === 'empty') {
            return ['message' => ['role' => 'assistant', 'content' => ''], 'usage' => null, 'model' => 'mock'];
        }
        if ($mode === 'invalid_tools') {
            return ['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => 'bad'], 'usage' => null, 'model' => 'mock'];
        }
        if ($mode === 'tools') {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $step['payload']['tool_calls'] ?? [],
                ],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
                'model' => 'mock',
            ];
        }
        return [
            'message' => [
                'role' => 'assistant',
                'content' => (string) ($step['payload']['content'] ?? 'OK'),
            ],
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

$tools = ProcurementToolRegistry::getTools();
$required = [
    'list_purchase_requests', 'summarize_procurement', 'analyze_procurement_intelligence',
    'analyze_advanced_procurement_operations', 'get_procurement_operational_guidance',
    'create_draft_purchase_request', 'update_purchase_request', 'cancel_purchase_request', 'submit_purchase_request',
];
if (array_diff($required, array_keys($tools)) === []) {
    $pass('TOOLS');
} else {
    $fail('TOOLS');
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
    $pass('db_bootstrap');
} catch (Throwable $e) {
    $fail('db_bootstrap', $e->getMessage());
    echo "\nSUMMARY: FAIL\n";
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
$makeCtx = static function (array $perms, bool $sa, int $cid, string $locale = 'en') use ($ref, $ctor, $userId): ProcurementAgentContext {
    $ctx = $ref->newInstanceWithoutConstructor();
    $ctor->invoke($ctx, $userId, $cid, $locale, 'phase5-test', $perms, $sa, ['procurement', 'suppliers']);
    return $ctx;
};
$fullPerms = [
    'procurement.manage', 'procurement.view', 'procurement.create',
    'procurement.update', 'procurement.submit', 'suppliers.manage',
];
$ctx = $makeCtx($fullPerms, true, $companyId, 'en');
$config = require RATEB_ROOT . '/config/agent.php';

// --- HARDENING: LLM failure ---
$agentLlmFail = new ProcurementAgent($config, new Phase5MockLlm([
    ['mode' => 'throw', 'payload' => ['code' => 'llm_timeout']],
]));
$rLlm = $agentLlmFail->process([
    'message' => 'list requests',
    'request_id' => 'p5-llm-' . bin2hex(random_bytes(4)),
    'history' => [],
], $ctx);
$hardeningOk = is_string($rLlm['response'] ?? null)
    && $rLlm['response'] !== ''
    && stripos((string) $rLlm['response'], 'RuntimeException') === false
    && stripos((string) $rLlm['response'], 'stack') === false
    && (int) ($rLlm['observability']['llm_failures'] ?? 0) >= 1;
if ($hardeningOk) {
    $pass('PRODUCTION HARDENING');
    $pass('ERROR HANDLING');
} else {
    $fail('PRODUCTION HARDENING', (string) ($rLlm['response'] ?? ''));
    $fail('ERROR HANDLING');
}

// Invalid tool response
$agentBadTools = new ProcurementAgent($config, new Phase5MockLlm([
    ['mode' => 'invalid_tools'],
]));
$rBad = $agentBadTools->process([
    'message' => 'analyze',
    'request_id' => 'p5-badtools-' . bin2hex(random_bytes(4)),
    'history' => [],
], $ctx);
if (($rBad['response'] ?? '') !== '' && empty($rBad['tool_calls'])) {
    $pass('ERROR HANDLING_invalid_tools');
} else {
    $fail('ERROR HANDLING_invalid_tools');
}

// --- CONTROLLED AUTONOMY ---
$writeArgs = ['title' => 'Phase5 Confirm ' . date('His')];
$confirmKey = 'create_draft_purchase_request:' . md5((string) json_encode($writeArgs, JSON_UNESCAPED_UNICODE));
$agentConfirm = new ProcurementAgent($config, new Phase5MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 'c1',
        'type' => 'function',
        'function' => [
            'name' => 'create_draft_purchase_request',
            'arguments' => json_encode($writeArgs, JSON_UNESCAPED_UNICODE),
        ],
    ]]]],
]));
$rConfirm = $agentConfirm->process([
    'message' => 'create draft',
    'request_id' => 'p5-confirm-' . bin2hex(random_bytes(4)),
    'history' => [],
], $ctx);
$confirmBlocked = ($rConfirm['pending_confirmations'][0]['confirm_key'] ?? '') === $confirmKey
    || (($rConfirm['tool_calls'][0]['result']['error_code'] ?? '') === 'write_confirmation_required');

$reqIdWrite = 'p5-write-' . bin2hex(random_bytes(4));
$agentWrite = new ProcurementAgent($config, new Phase5MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 'w1',
        'type' => 'function',
        'function' => [
            'name' => 'create_draft_purchase_request',
            'arguments' => json_encode($writeArgs, JSON_UNESCAPED_UNICODE),
        ],
    ]]]],
    ['mode' => 'text', 'payload' => ['content' => 'Created draft purchase request.']],
]));
$rWrite = $agentWrite->process([
    'message' => 'create draft confirmed',
    'request_id' => $reqIdWrite,
    'history' => [],
    'confirmed_writes' => [$confirmKey],
], $ctx);
$createdId = (int) ($rWrite['tool_calls'][0]['result']['data']['id'] ?? 0);
$writeOk = $confirmBlocked && !empty($rWrite['tool_calls'][0]['result']['success']) && $createdId > 0;

// Duplicate / retry same write on same request_id
$agentDup = new ProcurementAgent($config, new Phase5MockLlm([
    ['mode' => 'tools', 'payload' => ['tool_calls' => [[
        'id' => 'w2',
        'type' => 'function',
        'function' => [
            'name' => 'create_draft_purchase_request',
            'arguments' => json_encode($writeArgs, JSON_UNESCAPED_UNICODE),
        ],
    ]]]],
    ['mode' => 'text', 'payload' => ['content' => 'duplicate blocked']],
]));
$rDup = $agentDup->process([
    'message' => 'retry create',
    'request_id' => $reqIdWrite,
    'history' => [],
    'confirmed_writes' => [$confirmKey],
], $ctx);
$dupBlocked = ($rDup['tool_calls'][0]['result']['error_code'] ?? '') === 'duplicate_action';

if ($createdId > 0) {
    ProcurementToolExecutor::execute('cancel_purchase_request', [
        'id' => $createdId,
        'reason' => 'phase5 cleanup',
    ], $ctx);
}

if ($writeOk && $dupBlocked) {
    $pass('CONTROLLED AUTONOMY');
    $pass('WRITE CONFIRM');
} else {
    $fail('CONTROLLED AUTONOMY', json_encode([
        'confirm' => $confirmBlocked,
        'write' => $writeOk,
        'dup' => $dupBlocked,
        'dup_code' => $rDup['tool_calls'][0]['result']['error_code'] ?? null,
    ]));
    $fail('WRITE CONFIRM');
}

// AUDIT / OBSERVABILITY
$auditOk = false;
foreach (($rWrite['audit'] ?? []) as $a) {
    if (($a['tool'] ?? '') === 'user_request' || ($a['event_type'] ?? '') === 'user_request') {
        $auditOk = true;
    }
}
$obsOk = isset($rWrite['observability']['duration_ms'], $rWrite['observability']['request_id'])
    && (int) ($rWrite['observability']['writes_executed'] ?? 0) >= 1;
// Also verify DB audit row exists for request
$dbAudit = (int) $pdo->prepare(
    'SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = ? AND company_id = ?'
)->execute([$reqIdWrite, $companyId]) ? (int) $pdo->query(
    "SELECT COUNT(*) FROM rateb_agent_audit_events WHERE request_id = " . $pdo->quote($reqIdWrite) . " AND company_id = " . (int) $companyId
)->fetchColumn() : 0;
if ($auditOk && $obsOk && $dbAudit > 0) {
    $pass('AUDIT');
    $pass('OBSERVABILITY');
} else {
    $fail('AUDIT', json_encode(['auditOk' => $auditOk, 'dbAudit' => $dbAudit]));
    $fail('OBSERVABILITY');
}

// GUARDRAILS
$noPerm = $makeCtx([], false, $companyId);
$denied = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests',
    'arguments' => ['limit' => 1],
    'request_company_id' => null,
    'request_id' => 'p5-guard',
    'write_confirmed' => false,
], $noPerm);
$unknown = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'drop_database',
    'arguments' => [],
    'request_company_id' => null,
    'request_id' => 'p5-bad',
    'write_confirmed' => true,
], $ctx);
$badArgs = false;
try {
    ProcurementToolRegistry::validateArguments('get_purchase_request', []);
} catch (Throwable $e) {
    $badArgs = true;
}
if (!$denied['allowed'] && ($denied['error_code'] ?? '') === 'permission_denied'
    && !$unknown['allowed'] && ($unknown['error_code'] ?? '') === 'tool_not_allowed'
    && $badArgs) {
    $pass('GUARDRAILS');
    $pass('SECURITY');
} else {
    $fail('GUARDRAILS');
    $fail('SECURITY');
}

// READ / ANALYSIS / ADVANCED / GUIDANCE / APPROVALS
$read = ProcurementToolExecutor::execute('list_purchase_requests', ['limit' => 3], $ctx);
$analysis = ProcurementToolExecutor::execute('summarize_procurement', [], $ctx);
$intel = ProcurementToolExecutor::execute('analyze_procurement_intelligence', ['limit' => 5], $ctx);
$adv = ProcurementToolExecutor::execute('analyze_advanced_procurement_operations', ['limit' => 5], $ctx);
$guide = ProcurementToolExecutor::execute('get_procurement_operational_guidance', ['limit' => 5], $ctx);
$appr = ProcurementToolExecutor::execute('list_pending_approvals', ['limit' => 5], $ctx);

!empty($read['success']) ? $pass('READ') : $fail('READ');
!empty($analysis['success']) && !empty($intel['success']) ? $pass('ANALYSIS') : $fail('ANALYSIS');
!empty($adv['success']) && isset($adv['data']['executive_summary']) ? $pass('ADVANCED INTELLIGENCE') : $fail('ADVANCED INTELLIGENCE');
!empty($guide['success']) && !empty($guide['data']['guards']['never_auto_write']) ? $pass('OPERATIONAL GUIDANCE') : $fail('OPERATIONAL GUIDANCE');
!empty($appr['success']) ? $pass('APPROVALS') : $fail('APPROVALS');

// CONTEXT
$dirty = $ctx->sanitizeHistory([
    ['role' => 'system', 'content' => 'x'],
    ['role' => 'user', 'content' => 'hello'],
    ['role' => 'user', 'content' => '{"company_id":1,"user_id":2}'],
]);
count($dirty) === 1 && $dirty[0]['content'] === 'hello' ? $pass('CONTEXT') : $fail('CONTEXT');

// TENANT
$otherCompany = (int) $pdo->query(
    'SELECT id FROM rateb_companies WHERE id <> ' . (int) $companyId . ' ORDER BY id ASC LIMIT 1'
)->fetchColumn();
$tenantOk = false;
if ($otherCompany > 0) {
    $foreignPr = (int) $pdo->query(
        'SELECT id FROM rateb_purchase_requests WHERE company_id = ' . (int) $otherCompany . ' ORDER BY id DESC LIMIT 1'
    )->fetchColumn();
    if ($foreignPr > 0) {
        $iso = ProcurementToolExecutor::execute('get_purchase_request', ['id' => $foreignPr], $ctx);
        $tenantOk = empty($iso['success']);
    } else {
        $tenantOk = true;
    }
} else {
    $tenantOk = empty(ProcurementToolExecutor::execute('get_purchase_request', ['id' => 999999991], $ctx)['success']);
}
$mismatch = ProcurementPolicyGuard::checkAndExecute([
    'tool' => 'list_purchase_requests',
    'arguments' => ['limit' => 1],
    'request_company_id' => $companyId + 99999,
    'request_id' => 'p5-mismatch',
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
$ar = __('ai_tool_err_duplicate_action');
$_SESSION['rateb_locale'] = 'en';
if (function_exists('rateb_set_locale')) {
    rateb_set_locale('en');
}
$en = __('ai_tool_err_duplicate_action');
$_SESSION['rateb_locale'] = $prev;
(is_string($ar) && mb_strpos($ar, 'مكرر') !== false) ? $pass('AR') : $fail('AR', (string) $ar);
(is_string($en) && stripos($en, 'duplicate') !== false) ? $pass('EN') : $fail('EN', (string) $en);

// REGRESSION
$regOk = ProcurementToolRegistry::isAllowed('analyze_advanced_procurement_operations')
    && ProcurementToolRegistry::isAllowed('get_procurement_operational_guidance')
    && !empty($config['agent']['require_confirmation_for_write'])
    && (int) ($config['agent']['max_writes_per_request'] ?? 0) >= 1;
try {
    new ProcurementAgent($config, new Phase5MockLlm([['mode' => 'text', 'payload' => ['content' => 'hi']]]));
} catch (Throwable $e) {
    $regOk = false;
}
$regOk ? $pass('REGRESSION') : $fail('REGRESSION');

$failed = count(array_filter($results, static fn($r) => empty($r['ok'])));
$total = count($results);
echo "\nTOTAL {$total}  FAIL {$failed}\n";

$gates = [
    'PRODUCTION HARDENING', 'CONTROLLED AUTONOMY', 'AUDIT', 'GUARDRAILS', 'OBSERVABILITY', 'ERROR HANDLING',
    'READ', 'ANALYSIS', 'ADVANCED INTELLIGENCE', 'OPERATIONAL GUIDANCE', 'WRITE CONFIRM', 'APPROVALS',
    'CONTEXT', 'TOOLS', 'AR', 'EN', 'SECURITY', 'TENANT ISOLATION', 'REGRESSION',
];
echo "\n=== PHASE 5 GATES ===\n";
foreach ($gates as $gate) {
    $hits = array_values(array_filter($results, static fn($r) => $r['name'] === $gate || str_starts_with($r['name'], $gate)));
    $ok = $hits !== [] && count(array_filter($hits, static fn($r) => !empty($r['ok']))) === count($hits);
    echo ($ok ? 'PASS' : 'FAIL') . ": {$gate}\n";
}

exit($failed > 0 ? 1 : 0);
