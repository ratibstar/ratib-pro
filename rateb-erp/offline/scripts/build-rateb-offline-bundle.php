<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$order = [
    'offline/client/db/schema.js',
    'offline/client/db/migrations.js',
    'offline/client/core/idempotency.js',
    'offline/client/core/event-bus.js',
    'offline/client/core/connectivity.js',
    'offline/client/sync/queue-manager.js',
    'offline/client/sync/replay-scheduler.js',
    'offline/client/sync/delta-pull.js',
    'offline/client/core/transport.js',
    'offline/client/adapters/pos-adapter.js',
    'offline/client/adapters/inventory-adapter.js',
    'offline/client/adapters/hr-adapter.js',
    'offline/client/adapters/procurement-adapter.js',
    'offline/client/adapters/recruitment-adapter.js',
    'offline/client/adapters/accounting-adapter.js',
    'offline/client/adapters/crm-adapter.js',
    'offline/client/adapters/projects-adapter.js',
    'offline/client/adapters/assets-adapter.js',
    'offline/client/adapters/approval-adapter.js',
    'offline/client/adapters/procurement-enterprise-adapter.js',
    'offline/client/adapters/manufacturing-adapter.js',
    'offline/client/adapters/form-post-adapter.js',
    'offline/client/adapters/shell-adapter.js',
    'offline/client/adapters/auth-lock-adapter.js',
    'offline/client/adapters/rbac-cache-adapter.js',
    'offline/client/adapters/master-data-adapter.js',
    'offline/client/adapters/ops-forms-adapter.js',
    'offline/client/core/sdk.js',
];

$out = "/*! RATEB Enterprise Offline SDK Phase 14.2.0 (includes Phases 10-14.2 + 15B + 16B + 17B CRM + 18B Projects + 19B Assets + 20B Approval + 21B EPROC + 22B MFG; flags default OFF). */\n\n";
foreach ($order as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDERR, "MISSING {$rel}\n");
        exit(1);
    }
    $name = basename($rel);
    $out .= "/* ---- {$name} ---- */\n";
    $out .= file_get_contents($path);
    if (!str_ends_with($out, "\n")) {
        $out .= "\n";
    }
    $out .= "\n";
}

$dest = $root . '/public/assets/offline/rateb-offline.js';
$min = $root . '/public/assets/offline/rateb-offline.min.js';
file_put_contents($dest, $out);
file_put_contents($min, $out);
echo 'Wrote ' . strlen($out) . " bytes\n";
echo (str_contains($out, 'RatebOfflineCrmAdapter') ? 'HAS crm adapter' : 'MISSING crm') . PHP_EOL;
echo (str_contains($out, 'isCrmEnabled') ? 'HAS isCrmEnabled' : 'MISSING crm helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineProjectsAdapter') ? 'HAS projects adapter' : 'MISSING projects') . PHP_EOL;
echo (str_contains($out, 'isProjectsEnabled') ? 'HAS isProjectsEnabled' : 'MISSING projects helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineAssetsAdapter') ? 'HAS assets adapter' : 'MISSING assets') . PHP_EOL;
echo (str_contains($out, 'isAssetsEnabled') ? 'HAS isAssetsEnabled' : 'MISSING assets helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineApprovalAdapter') ? 'HAS approval adapter' : 'MISSING approval') . PHP_EOL;
echo (str_contains($out, 'isApprovalEnabled') ? 'HAS isApprovalEnabled' : 'MISSING approval helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineProcurementEnterpriseAdapter') ? 'HAS eproc adapter' : 'MISSING eproc') . PHP_EOL;
echo (str_contains($out, 'isProcurementEnterpriseEnabled') ? 'HAS isProcurementEnterpriseEnabled' : 'MISSING eproc helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineManufacturingAdapter') ? 'HAS mfg adapter' : 'MISSING mfg') . PHP_EOL;
echo (str_contains($out, 'isManufacturingEnabled') ? 'HAS isManufacturingEnabled' : 'MISSING mfg helper') . PHP_EOL;
