#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('RCC_ROOT', dirname(__DIR__));
define('RCC_SKIP_ORCHESTRATOR_BOOT', true);
require RCC_ROOT . '/bootstrap.php';

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Infrastructure\Omnichannel\Channels\EmailImapSyncService;

$tenantId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($tenantId < 1) {
    $stmt = Database::connection()->query("SELECT id FROM rcc_tenants WHERE status = 'active'");
    foreach ($stmt->fetchAll() as $row) {
        $n = (new EmailImapSyncService())->syncTenant((int) $row['id']);
        fwrite(STDOUT, 'Tenant ' . $row['id'] . ': synced ' . $n . " messages\n");
    }
    exit(0);
}

$n = (new EmailImapSyncService())->syncTenant($tenantId);
fwrite(STDOUT, "Synced {$n} messages for tenant {$tenantId}\n");
