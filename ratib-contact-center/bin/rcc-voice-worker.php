#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * RCC Voice Worker — persistent AMI event consumer.
 *
 * Usage: php bin/rcc-voice-worker.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Application\Services\IvrSessionManager;
use Ratib\ContactCenter\App\Application\Services\QueueDeliveryService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Infrastructure\Voice\AsteriskAmiAdapter;
use Ratib\ContactCenter\App\Infrastructure\Voice\AmiClient;

RealtimeOrchestrator::boot();
QueueDeliveryService::registerSubscriber();

$adapter = new AsteriskAmiAdapter(new IvrSessionManager());
$config = (array) require RCC_ROOT . '/config/asterisk.php';
$reconnectDelay = (int) ($config['reconnect_delay_seconds'] ?? 5);

fwrite(STDOUT, "RCC Voice Worker — AMI {$config['host']}:{$config['port']}\n");

while (true) {
    $ami = new AmiClient($config);
    try {
        $ami->connect();
        fwrite(STDOUT, '[' . gmdate('c') . "] AMI connected\n");

        while ($ami->isConnected()) {
            $packet = $ami->readPacket();
            if ($packet === []) {
                usleep(100_000);
                continue;
            }

            if (isset($packet['Event'])) {
                $adapter->dispatch($packet);
                continue;
            }

            if (isset($packet['Response']) && ($packet['Response'] ?? '') === 'Error') {
                error_log('[RCC Voice Worker] AMI error: ' . ($packet['Message'] ?? 'unknown'));
            }
        }
    } catch (\Throwable $e) {
        error_log('[RCC Voice Worker] ' . $e->getMessage());
        fwrite(STDERR, '[' . gmdate('c') . '] ' . $e->getMessage() . "\n");
    } finally {
        $ami->disconnect();
    }

    sleep(max(1, $reconnectDelay));
}
