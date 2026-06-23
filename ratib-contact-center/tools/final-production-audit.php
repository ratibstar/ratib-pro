#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC Final Production Audit — Phase 10 comprehensive scorecard (0–100).
 *
 * Usage: php tools/final-production-audit.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

$sections = [];
$totalScore = 0;
$totalMax = 0;

function audit_section(array &$sections, int &$totalScore, int &$totalMax, string $name, array $checks): void
{
    $score = 0;
    $max = 0;
    foreach ($checks as $c) {
        $max += (int) $c['weight'];
        if ($c['pass']) {
            $score += (int) $c['weight'];
        }
    }
    $totalScore += $score;
    $totalMax += $max;
    $sections[] = [
        'name' => $name,
        'score' => $score,
        'max' => $max,
        'pct' => $max > 0 ? round(($score / $max) * 100, 1) : 0,
        'checks' => $checks,
    ];
}

function chk(bool $pass, int $weight, string $label, string $detail = ''): array
{
    return ['pass' => $pass, 'weight' => $weight, 'label' => $label, 'detail' => $detail];
}

// Database
$dbOk = false;
$tableCount = 0;
try {
    $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
    $dbOk = true;
    $tableCount = $pdo->query("SHOW TABLES LIKE 'rcc_%'")->rowCount();
} catch (\Throwable $e) {
    $dbOk = false;
}
$migCount = count(glob(RCC_ROOT . '/migrations/*.sql') ?: []);
audit_section($sections, $totalScore, $totalMax, 'Database', [
    chk($dbOk, 15, 'Database connection', $dbOk ? 'OK' : 'Failed'),
    chk($tableCount >= 50, 10, 'Schema tables (≥50)', (string) $tableCount),
    chk($migCount >= 25, 10, 'Migrations shipped (≥25)', (string) $migCount),
    chk(is_file(RCC_ROOT . '/migrations/020_saas_billing.sql'), 5, 'SaaS billing migration'),
    chk(is_file(RCC_ROOT . '/migrations/019_security_hardening.sql'), 5, 'Security migration'),
]);

// RBAC
audit_section($sections, $totalScore, $totalMax, 'RBAC', [
    chk(is_file(RCC_ROOT . '/app/Core/Security/ApiAuthMiddleware.php'), 10, 'API auth middleware'),
    chk(is_file(RCC_ROOT . '/app/Core/Security/AuthContext.php'), 5, 'AuthContext'),
    chk($dbOk && table_exists($pdo ?? null, 'rcc_permissions'), 5, 'Permissions table'),
]);

// Realtime
$hubPort = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
$hubFp = @fsockopen('127.0.0.1', $hubPort, $errno, $errstr, 1);
$hubUp = is_resource($hubFp);
if ($hubUp) {
    fclose($hubFp);
}
audit_section($sections, $totalScore, $totalMax, 'Realtime', [
    chk(is_file(RCC_ROOT . '/bin/rcc-realtime-hub.php'), 5, 'Realtime hub script'),
    chk($hubUp, 10, 'WebSocket port open', 'port ' . $hubPort),
    chk(is_file(RCC_ROOT . '/public/assets/js/rcc-realtime-client.js'), 5, 'Realtime client JS'),
]);

// AMI / Telephony
$amiHost = getenv('RCC_AMI_HOST') ?: '127.0.0.1';
$amiPort = (int) (getenv('RCC_AMI_PORT') ?: 5038);
$amiFp = @fsockopen($amiHost, $amiPort, $errno, $errstr, 2);
$amiUp = is_resource($amiFp);
if ($amiUp) {
    fclose($amiFp);
}
audit_section($sections, $totalScore, $totalMax, 'AMI & Queues', [
    chk($amiUp, 10, 'AMI reachable', $amiHost . ':' . $amiPort),
    chk(is_file(RCC_ROOT . '/bin/rcc-voice-worker.php'), 5, 'Voice worker'),
    chk(is_file(RCC_ROOT . '/app/Infrastructure/Queue/QueueEngineGateway.php'), 5, 'Queue gateway'),
]);

// WebRTC
audit_section($sections, $totalScore, $totalMax, 'WebRTC', [
    chk(is_file(RCC_ROOT . '/public/assets/js/rcc-softphone.js'), 8, 'Softphone JS'),
    chk(is_file(RCC_ROOT . '/app/Domain/Softphone/CallControlEngine.php'), 7, 'Call control engine'),
]);

// Phase 10 modules (code)
audit_section($sections, $totalScore, $totalMax, 'CRM', [
    chk(is_file(RCC_ROOT . '/public/api/v1/crm.php'), 8, 'CRM API'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Crm/CustomerProfileService.php'), 7, 'CRM services'),
    chk(is_file(RCC_ROOT . '/../control-panel/pages/control/contact-center-crm.php'), 5, 'CRM CP page'),
]);

audit_section($sections, $totalScore, $totalMax, 'Tickets', [
    chk(is_file(RCC_ROOT . '/public/api/v1/tickets.php'), 8, 'Tickets API'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Tickets/TicketWorkflowService.php'), 7, 'Ticket workflow'),
]);

audit_section($sections, $totalScore, $totalMax, 'QA & Recordings', [
    chk(is_file(RCC_ROOT . '/app/Application/Services/Qa/QaEvaluationService.php'), 6, 'QA service'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Recordings/RecordingService.php'), 6, 'Recording service'),
    chk(is_file(RCC_ROOT . '/public/api/v1/recording-play.php'), 3, 'Recording playback'),
]);

audit_section($sections, $totalScore, $totalMax, 'Analytics & Command', [
    chk(is_file(RCC_ROOT . '/public/api/v1/analytics.php'), 8, 'Analytics API'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Analytics/DashboardBuilder.php'), 7, 'Dashboard builder'),
    chk(is_file(RCC_ROOT . '/../control-panel/pages/control/contact-center-command-center.php'), 5, 'Command center CP'),
]);

audit_section($sections, $totalScore, $totalMax, 'AI & Security', [
    chk(is_file(RCC_ROOT . '/app/Domain/AI/Insights/AiQaEngine.php'), 5, 'AI QA engine'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Security/ApiRateLimitService.php'), 5, 'Rate limiting'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/RccAuditService.php'), 5, 'Central audit service'),
]);

audit_section($sections, $totalScore, $totalMax, 'SaaS Platform (Phase 11)', [
    chk(is_file(RCC_ROOT . '/public/api/v1/billing.php'), 6, 'Billing API'),
    chk(is_file(RCC_ROOT . '/public/api/v1/customer-portal.php'), 6, 'Customer portal API'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/Billing/BillingEngine.php'), 5, 'Billing engine'),
    chk(is_file(RCC_ROOT . '/app/Infrastructure/Payment/Drivers/StripeGateway.php'), 4, 'Stripe gateway'),
    chk(is_file(RCC_ROOT . '/public/portal/index.php'), 4, 'Customer portal UI'),
    chk(is_file(RCC_ROOT . '/../control-panel/pages/control/contact-center-billing.php'), 5, 'Billing CP page'),
]);

audit_section($sections, $totalScore, $totalMax, 'DR & Marketplace', [
    chk(is_file(RCC_ROOT . '/public/api/v1/disaster-recovery.php'), 5, 'DR API'),
    chk(is_file(RCC_ROOT . '/public/api/v1/marketplace.php'), 5, 'Marketplace API'),
    chk(is_file(RCC_ROOT . '/app/Application/Services/DisasterRecovery/BackupRestoreService.php'), 5, 'Backup service'),
    chk(is_file(RCC_ROOT . '/bin/rcc-monitor-runner.php'), 5, 'Monitor runner'),
]);

$overall = $totalMax > 0 ? round(($totalScore / $totalMax) * 100, 1) : 0;
$target = 95.0;

$infraNames = ['Database', 'Realtime', 'AMI & Queues'];
$codeNames = ['RBAC', 'WebRTC', 'CRM', 'Tickets', 'QA & Recordings', 'Analytics & Command', 'AI & Security', 'SaaS Platform (Phase 11)', 'DR & Marketplace'];
$infraScore = 0;
$infraMax = 0;
$codeScore = 0;
$codeMax = 0;
foreach ($sections as $sec) {
    if (in_array($sec['name'], $infraNames, true)) {
        $infraScore += $sec['score'];
        $infraMax += $sec['max'];
    } elseif (in_array($sec['name'], $codeNames, true)) {
        $codeScore += $sec['score'];
        $codeMax += $sec['max'];
    }
}
$codePct = $codeMax > 0 ? round(($codeScore / $codeMax) * 100, 1) : 0;
$infraPct = $infraMax > 0 ? round(($infraScore / $infraMax) * 100, 1) : 0;
$pass = $codePct >= $target;

echo "RATIB Contact Center — Final Production Audit (Phase 10–11)\n";
echo str_repeat('=', 56) . "\n\n";

foreach ($sections as $sec) {
    echo sprintf("%-22s %5.1f%% (%d/%d)\n", $sec['name'] . ':', $sec['pct'], $sec['score'], $sec['max']);
    foreach ($sec['checks'] as $c) {
        $mark = $c['pass'] ? 'PASS' : 'FAIL';
        $detail = $c['detail'] !== '' ? ' — ' . $c['detail'] : '';
        echo "  [$mark] {$c['label']}{$detail}\n";
    }
    echo "\n";
}

echo str_repeat('-', 56) . "\n";
echo sprintf("CODE LAYER:        %.1f%% (%d/%d points)\n", $codePct, $codeScore, $codeMax);
echo sprintf("INFRASTRUCTURE:    %.1f%% (%d/%d points)\n", $infraPct, $infraScore, $infraMax);
echo sprintf("COMBINED SCORE:    %.1f%% (%d/%d points)\n", $overall, $totalScore, $totalMax);
echo 'TARGET (code): ' . $target . "%+\n";
if ($codePct >= $target && $infraPct < $target) {
    echo "RESULT: CODE PASS — configure DB, WebSocket hub, and AMI on production\n";
} elseif ($pass) {
    echo "RESULT: PASS — production ready\n";
} else {
    echo "RESULT: NEEDS WORK — missing application modules\n";
}

exit($pass ? 0 : 1);

/** @param \PDO|null $pdo */
function table_exists(?\PDO $pdo, string $table): bool
{
    if (!$pdo instanceof \PDO) {
        return false;
    }
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
        return $stmt !== false && $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}
