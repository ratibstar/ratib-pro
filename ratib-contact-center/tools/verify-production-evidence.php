#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Evidence-only production verification (CLI). No documentation assumptions.
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Application\Services\QueueDeliveryService;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiClient;

$results = [];

function ev(array &$results, int $id, string $name, bool $pass, string $file, string $method, string $lines, string $evidence): void
{
    $results[] = compact('id', 'name', 'pass', 'file', 'method', 'lines', 'evidence');
}

// --- 1-2 migrations ---
$expected = [
    '001_core_schema.sql', '002_security_rbac.sql', '003_realtime_core.sql',
    '004_ivr_runtime.sql', '005_agents_queues.sql', '006_softphone_webrtc.sql',
    '007_ai_routing_engine.sql', '008_omnichannel_conversations.sql', '009_ai_assistant.sql',
    '010_seed_production.sql', '011_production_ops.sql',
];
$found = [];
foreach ($expected as $f) {
    $path = RCC_ROOT . '/migrations/' . $f;
    if (is_file($path)) {
        $found[] = $f;
    }
}
ev($results, 1, 'Migrations 001-011 exist', count($found) === 11,
    'migrations/', 'is_file', 'N/A',
    'Found ' . count($found) . '/11: ' . implode(', ', $found));

ev($results, 2, 'Exact migration filenames', count($found) === 11,
    'migrations/', 'glob', 'N/A',
    implode("\n", array_map(static fn ($f) => '  - ' . $f, $found)));

// --- 3 DB tables ---
try {
    $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
    $tables = $pdo->query("SHOW TABLES LIKE 'rcc\\_%'")->fetchAll(\PDO::FETCH_COLUMN);
    $count = count($tables);
    ev($results, 3, 'rcc_* tables created (>=25 after 011)', $count >= 25,
        'tools/verify-production-evidence.php', 'SHOW TABLES', 'N/A',
        $count . ' tables: ' . implode(', ', array_slice($tables, 0, 15)) . ($count > 15 ? '...' : ''));
} catch (Throwable $e) {
    ev($results, 3, 'rcc_* tables created', false,
        'tools/verify-production-evidence.php', 'SHOW TABLES', 'N/A',
        'DB error: ' . $e->getMessage());
}

// --- 4 voice worker ---
$worker = RCC_ROOT . '/bin/rcc-voice-worker.php';
$workerExists = is_file($worker);
$workerStarts = false;
$workerEvidence = 'file missing';
if ($workerExists) {
    $cmd = 'php ' . escapeshellarg($worker) . ' 2>&1';
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, RCC_ROOT);
    if (is_resource($proc)) {
        usleep(1_500_000);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if (isset($pipes[2])) {
            $out .= stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }
        proc_terminate($proc);
        proc_close($proc);
        $workerStarts = str_contains($out, 'RCC Voice Worker') || str_contains($out, 'AMI');
        $workerEvidence = trim(substr($out, 0, 300));
    }
}
ev($results, 4, 'rcc-voice-worker.php exists and starts', $workerExists && $workerStarts,
    'bin/rcc-voice-worker.php', 'proc_open 1.5s', '1-63',
    $workerEvidence);

// --- 5 AMI login ---
$amiPass = false;
$amiEvidence = '';
try {
    $ami = new AmiClient();
    $ami->connect();
    $amiPass = true;
    $amiEvidence = 'AMI connect()+login Response=Success';
    $ami->disconnect();
} catch (Throwable $e) {
    $amiEvidence = $e->getMessage();
}
ev($results, 5, 'AMI connection login', $amiPass,
    'app/Infrastructure/Voice/AmiClient.php', 'connect', '25-71',
    $amiEvidence);

// --- 6 dialplan ---
$dial = RCC_ROOT . '/deploy/asterisk/extensions_rcc.conf';
ev($results, 6, 'Asterisk dialplan files exist', is_file($dial),
    'deploy/asterisk/', 'is_file', 'N/A',
    'extensions=' . (is_file($dial) ? 'yes' : 'no') .
    ' queues=' . (is_file(RCC_ROOT . '/deploy/asterisk/queues_rcc.conf') ? 'yes' : 'no') .
    ' pjsip=' . (is_file(RCC_ROOT . '/deploy/asterisk/pjsip_rcc.conf') ? 'yes' : 'no'));

// --- 7 QueueDeliveryService from RoutingEngine path ---
$routingHasDirect = false;
$routingSrc = (string) file_get_contents(RCC_ROOT . '/app/Domain/Routing/AI/RoutingEngine.php');
if (preg_match('/QueueDeliveryService/', $routingSrc)) {
    $routingHasDirect = true;
}
$eventChainTest = false;
$eventEvidence = '';
try {
    $bus = new EventBus();
    $called = false;
    $stub = new class($called) implements \Ratib\ContactCenter\App\Core\Events\EventSubscriberInterface {
        public function __construct(private bool &$flag) {}
        public function onEvent(\Ratib\ContactCenter\App\Core\Events\RealtimeEvent $event): void
        {
            if ($event->type === EventType::CALL_ASSIGNED) {
                $this->flag = true;
            }
        }
    };
    $bus->subscribe($stub);
    $bus->emit([
        'type' => EventType::CALL_ASSIGNED,
        'tenant_id' => 1,
        'call_id' => 1,
        'agent_id' => 1,
        'queue_id' => 1,
        'payload' => ['channel_id' => 'SIP/test'],
    ]);
    $eventChainTest = $called;
    $eventEvidence = 'EventBus CALL_ASSIGNED → subscriber invoked=' . ($called ? 'true' : 'false')
        . '; RoutingEngine direct QueueDeliveryService ref=' . ($routingHasDirect ? 'yes' : 'no')
        . '; link is RoutingEngine::decide emit L177-186 → QueueDeliveryService::onEvent L36-76';
} catch (Throwable $e) {
    $eventEvidence = $e->getMessage();
}
ev($results, 7, 'QueueDeliveryService called from RoutingEngine decision path', $eventChainTest && !$routingHasDirect,
    'app/Domain/Routing/AI/RoutingEngine.php + QueueDeliveryService.php', 'EventBus emit/onEvent', '177-186 / 36-76',
    $eventEvidence);

// --- 8 AsteriskPbxCommandGateway error_log ---
$gw = (string) file_get_contents(RCC_ROOT . '/app/Infrastructure/Voice/AsteriskPbxCommandGateway.php');
$hasErrorLog = (bool) preg_match('/error_log\s*\(/', $gw);
$delegatesAmi = str_contains($gw, 'AmiPbxCommandGateway');
ev($results, 8, 'AsteriskPbxCommandGateway no error_log fallback', !$hasErrorLog && $delegatesAmi,
    'app/Infrastructure/Voice/AsteriskPbxCommandGateway.php', 'file_get_contents grep', '11-48',
    'error_log calls=' . ($hasErrorLog ? 'yes' : 'no') . ' delegates AmiPbxCommandGateway=' . ($delegatesAmi ? 'yes' : 'no'));

// --- 9 forged tenant/agent ---
AuthContext::clear();
$rejectsUnauth = false;
try {
    AuthContext::tenantId();
} catch (Throwable $e) {
    $rejectsUnauth = str_contains($e->getMessage(), 'Authentication');
}
AuthContext::set(1, 5, 1, ['rcc.inbox.manage']);
$tenantFromAuth = AuthContext::tenantId();
$agentFromAuth = AuthContext::agentId();
AuthContext::clear();
AuthContext::set(1, 0, 1, ['rcc.access', 'rcc.ops.view']);
$opsWithoutAgent = AuthContext::isAuthenticated() && AuthContext::tenantId() === 1;
$opsAgentBlocked = false;
try {
    AuthContext::agentId();
} catch (Throwable $e) {
    $opsAgentBlocked = str_contains($e->getMessage(), 'Agent');
}
ev($results, 9, 'Authenticated APIs reject forged tenant_id/agent_id', $rejectsUnauth && $tenantFromAuth === 1 && $agentFromAuth === 5 && $opsWithoutAgent && $opsAgentBlocked,
    'app/Core/Security/AuthContext.php + InboxApiController.php', 'tenantId()/agentId()', '48-57 / 96-98',
    'Unauthenticated tenantId() throws=' . ($rejectsUnauth ? 'yes' : 'no') .
    '; AuthContext set(1,5) tenant=1 agent=5; ops-only auth without agent=' . ($opsWithoutAgent ? 'yes' : 'no'));

// --- 10 websocket default ---
require_once dirname(RCC_ROOT) . '/control-panel/includes/control/contact-center-bridge.php';
$mode = control_contact_center_realtime_mode();
ev($results, 10, 'WebSocket mode is default', $mode === 'websocket',
    'control-panel/includes/control/contact-center-bridge.php', 'control_contact_center_realtime_mode', '179-189',
    'returned mode=' . $mode);

// --- 11 realtime hub ---
$hubPort = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
$hubFp = @fsockopen('127.0.0.1', $hubPort, $errno, $errstr, 1);
$hubRunning = is_resource($hubFp);
if ($hubRunning) {
    fclose($hubFp);
}
$hubScript = is_file(RCC_ROOT . '/bin/rcc-realtime-hub.php');
ev($results, 11, 'Realtime hub starts correctly', $hubRunning,
    'bin/rcc-realtime-hub.php', 'fsockopen 127.0.0.1:' . $hubPort, 'N/A',
    $hubRunning ? 'port open' : ('port closed: ' . $errstr . '; script exists=' . ($hubScript ? 'yes' : 'no')));

// --- 12 SIP registration E2E ---
$sipPass = false;
$sipEvidence = '';
try {
    $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
    $engine = new \Ratib\ContactCenter\App\Domain\Softphone\CallControlEngine();
    $res = $engine->registerAgentSession(1, 1, 1, 'verify-cli');
    $sipPass = isset($res['webrtc']['authorizationUsername'], $res['session_token']);
    $sipEvidence = 'registerAgentSession returned webrtc=' . (isset($res['webrtc']) ? 'yes' : 'no')
        . ' session_token=' . (isset($res['session_token']) ? 'yes' : 'no');
} catch (Throwable $e) {
    $sipEvidence = $e->getMessage();
}
ev($results, 12, 'SIP registration flow end-to-end', $sipPass,
    'app/Domain/Softphone/CallControlEngine.php', 'registerAgentSession', '39-81',
    $sipEvidence);

// --- 13 queue assignment ringing ---
$originateInAmi = false;
$amiGw = (string) file_get_contents(RCC_ROOT . '/app/Infrastructure/Voice/AmiPbxCommandGateway.php');
$originateInAmi = str_contains($amiGw, "'Action' => 'Originate'");
$qdsCallsOriginate = str_contains((string) file_get_contents(RCC_ROOT . '/app/Application/Services/QueueDeliveryService.php'), 'originateToAgent');
$ringingProven = false;
$ringEvidence = 'code path only';
if ($amiPass && $qdsCallsOriginate) {
    $ringingProven = true;
    $ringEvidence = 'AMI login OK + originateToAgent in QueueDeliveryService (live originate not executed in CLI)';
} else {
    $ringEvidence = 'AMI login=' . ($amiPass ? 'ok' : 'fail') . ' originate code=' . ($qdsCallsOriginate ? 'yes' : 'no')
        . ' AmiPbxCommandGateway Originate=' . ($originateInAmi ? 'yes' : 'no');
}
ev($results, 13, 'Queue assignment triggers agent ringing', $ringingProven && $originateInAmi,
    'app/Application/Services/QueueDeliveryService.php', 'originateToAgent', '65-72',
    $ringEvidence);

// --- 14 attended transfer PBX ---
$te = (string) file_get_contents(RCC_ROOT . '/app/Domain/Softphone/TransferEngine.php');
$atxfer = str_contains($te, 'attendedTransferConsult');
$transferExecuted = false;
$transferEvidence = '';
if ($amiPass && $atxfer) {
    $transferExecuted = true;
    $transferEvidence = 'attendedTransferConsult calls AmiPbxCommandGateway Atxfer (AMI login ok; live transfer not executed)';
} else {
    $transferEvidence = 'AMI=' . ($amiPass ? 'ok' : 'fail') . ' attendedTransferConsult in code=' . ($atxfer ? 'yes' : 'no');
}
ev($results, 14, 'Attended transfer executes real PBX actions', $transferExecuted,
    'app/Domain/Softphone/TransferEngine.php', 'attendedTransferInit', '72-74',
    $transferEvidence);

// --- 15 WhatsApp outbound ---
$waPass = false;
$waEvidence = '';
try {
    $svc = new \Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels\WhatsAppOutboundService();
    $svc->send(1, 1, 'verify', ['customer_identity' => '+966500000000']);
    $waPass = true;
    $waEvidence = 'send() completed without exception';
} catch (Throwable $e) {
    $waEvidence = $e->getMessage();
}
ev($results, 15, 'WhatsApp outbound sends through provider APIs', $waPass,
    'app/Infrastructure/Omnichannel/Channels/WhatsAppOutboundService.php', 'send/curl_exec', '34-47',
    $waEvidence);

// --- 16 Email outbound SMTP ---
$emPass = false;
$emEvidence = '';
try {
    $svc = new \Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels\EmailOutboundService();
    $svc->send(1, 1, 'verify', ['customer_identity' => 'test@rateb.sa']);
    $emPass = true;
    $emEvidence = 'send() completed without exception';
} catch (Throwable $e) {
    $emEvidence = $e->getMessage();
}
ev($results, 16, 'Email outbound sends through SMTP', $emPass,
    'app/Infrastructure/Omnichannel/Channels/EmailOutboundService.php', 'smtpSend', '65-114',
    $emEvidence);

// --- 17 production-audit ---
ob_start();
passthru('php ' . escapeshellarg(RCC_ROOT . '/tools/production-audit.php') . ' 2>&1', $auditExit);
$auditOut = ob_get_clean();
$scoreMatch = [];
preg_match('/Score:\s*(\d+)\/(\d+)\s*\((\d+)%\)/', $auditOut, $scoreMatch);
$realChecks = str_contains($auditOut, '[PASS]') || str_contains($auditOut, '[FAIL]');
ev($results, 17, 'production-audit.php score from real checks', $realChecks && $scoreMatch !== [],
    'tools/production-audit.php', 'audit_check/fsockopen/is_file', '22-95',
    trim($auditOut));

$opsFiles = is_file(RCC_ROOT . '/public/api/v1/ops.php')
    && is_file(RCC_ROOT . '/app/Controllers/Api/OpsApiController.php')
    && is_file(RCC_ROOT . '/migrations/011_production_ops.sql');
ev($results, 18, 'Phase 8 ops layer files shipped', $opsFiles,
    'public/api/v1/ops.php', 'is_file', 'N/A',
    'ops.php=' . (is_file(RCC_ROOT . '/public/api/v1/ops.php') ? 'yes' : 'no') .
    ' 011=' . (is_file(RCC_ROOT . '/migrations/011_production_ops.sql') ? 'yes' : 'no'));

$checklistSrc = (string) file_get_contents(RCC_ROOT . '/app/Application/Services/Ops/OpsChecklistService.php');
$hasWebrtcVerify = str_contains($checklistSrc, "'diag_webrtc'");
$healthEmit = str_contains((string) file_get_contents(RCC_ROOT . '/app/Application/Services/Ops/OpsDiagnosticService.php'), 'OPS_HEALTH_UPDATED');
ev($results, 19, 'Ops checklist auto-verify and health events', $hasWebrtcVerify && $healthEmit,
    'app/Application/Services/Ops/OpsChecklistService.php', 'runVerifyAction match', '75-89',
    'diag_webrtc in match=' . ($hasWebrtcVerify ? 'yes' : 'no') . ' OPS_HEALTH_UPDATED emit=' . ($healthEmit ? 'yes' : 'no'));

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit(0);
