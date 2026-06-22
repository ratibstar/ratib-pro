#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC Production Audit — scorecard for go-live readiness.
 *
 * Usage: php tools/production-audit.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

$checks = [];
$score = 0;
$max = 0;

function audit_check(array &$checks, int &$score, int &$max, string $name, bool $pass, string $detail = ''): void
{
    $max += 10;
    if ($pass) {
        $score += 10;
    }
    $checks[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail];
}

// Database
try {
    $pdo = \Ratib\ContactCenter\App\Core\Database::connection();
    $tables = $pdo->query("SHOW TABLES LIKE 'rcc_%'")->rowCount();
    audit_check($checks, $score, $max, 'Database connection', true);
    audit_check($checks, $score, $max, 'Schema tables (rcc_*)', $tables >= 20, (string) $tables . ' tables');
} catch (\Throwable $e) {
    audit_check($checks, $score, $max, 'Database connection', false, $e->getMessage());
    audit_check($checks, $score, $max, 'Schema tables', false, 'N/A');
}

// Migrations
$migDir = RCC_ROOT . '/migrations';
$migFiles = glob($migDir . '/*.sql') ?: [];
audit_check($checks, $score, $max, 'Migration files shipped', count($migFiles) >= 9, (string) count($migFiles) . ' files');

// AMI
$amiHost = getenv('RCC_AMI_HOST') ?: '127.0.0.1';
$amiPort = (int) (getenv('RCC_AMI_PORT') ?: 5038);
$amiFp = @fsockopen($amiHost, $amiPort, $errno, $errstr, 2);
$amiOpen = is_resource($amiFp);
if ($amiOpen) {
    fclose($amiFp);
}
audit_check($checks, $score, $max, 'AMI port reachable', $amiOpen, $amiHost . ':' . $amiPort);

// Voice worker script
audit_check($checks, $score, $max, 'Voice worker binary', is_file(RCC_ROOT . '/bin/rcc-voice-worker.php'));

// Realtime hub
$hubPort = (int) (getenv('RCC_WEBSOCKET_PORT') ?: 9702);
$hubFp = @fsockopen('127.0.0.1', $hubPort, $errno, $errstr, 1);
$hubRunning = is_resource($hubFp);
if ($hubRunning) {
    fclose($hubFp);
}
audit_check($checks, $score, $max, 'Realtime hub WS port', $hubRunning, 'port ' . $hubPort);

// Security classes
audit_check($checks, $score, $max, 'API auth middleware', is_file(RCC_ROOT . '/app/Core/Security/ApiAuthMiddleware.php'));

// Asterisk deploy package
audit_check($checks, $score, $max, 'Asterisk dialplan package', is_file(RCC_ROOT . '/deploy/asterisk/extensions_rcc.conf'));

// Omnichannel outbound
audit_check($checks, $score, $max, 'WhatsApp outbound service', is_file(RCC_ROOT . '/app/Infrastructure/Omnichannel/Channels/WhatsAppOutboundService.php'));
audit_check($checks, $score, $max, 'SMTP outbound service', is_file(RCC_ROOT . '/app/Infrastructure/Omnichannel/Channels/EmailOutboundService.php'));

// Reporting
audit_check($checks, $score, $max, 'Report service', is_file(RCC_ROOT . '/app/Application/Services/ReportService.php'));

$percent = $max > 0 ? (int) round(($score / $max) * 100) : 0;

echo "RATIB Contact Center — Production Audit\n";
echo str_repeat('=', 40) . "\n";
foreach ($checks as $c) {
    echo ($c['pass'] ? '[PASS]' : '[FAIL]') . ' ' . $c['name'];
    if ($c['detail'] !== '') {
        echo ' — ' . $c['detail'];
    }
    echo "\n";
}
echo str_repeat('=', 40) . "\n";
echo "Score: {$score}/{$max} ({$percent}%)\n";
exit($percent >= 80 ? 0 : 1);
