<?php

declare(strict_types=1);

/**
 * Verify local Offline activation (flags from project-root .env).
 *
 *   php rateb-erp/offline/scripts/verify-offline-activation.php
 */

$projectRoot = dirname(__DIR__, 3);
$erpRoot = dirname(__DIR__, 2);

require_once $projectRoot . '/config/env/dotenv_bridge.php';
rateb_env_load_bridge_dotenv($projectRoot . DIRECTORY_SEPARATOR . '.env');

require_once $erpRoot . '/offline/OfflineModule.php';
\Rateb\App\Offline\OfflineModule::init();

$flags = new \Rateb\App\Offline\Services\OfflineFeatureFlagService();

$checks = [
    'offline.enabled' => $flags->isMasterEnabled(),
    'offline.read_cache' => $flags->isReadCacheEnabled(),
    'offline.auth.unlock' => $flags->isAuthUnlockEnabled(),
    'offline.auth.cold' => $flags->isColdIdentityEnabled(),
    'offline.rbac.cache' => $flags->isRbacCacheEnabled(),
    'offline.master_data' => $flags->isMasterDataEnabled(),
    'offline.pilot.ops_pages' => $flags->isPilotOpsPagesEnabled(),
    'offline.monitoring' => $flags->isMonitoringEnabled(),
    'offline.inventory.movements' => $flags->isInventoryMovementsEnabled(),
    'offline.hr.attendance' => $flags->isHrAttendanceEnabled(),
    'offline.hr (HRMS)' => $flags->isHumanResourcesEnabled(),
    'offline.procurement' => $flags->isProcurementEnabled(),
    'offline.recruitment' => $flags->isRecruitmentEnabled(),
    'offline.accounting' => $flags->isAccountingEnabled(),
    'offline.crm' => $flags->isCrmEnabled(),
    'offline.projects' => $flags->isProjectsEnabled(),
    'offline.assets' => $flags->isAssetsEnabled(),
    'offline.approval' => $flags->isApprovalEnabled(),
    'offline.procurement_enterprise' => $flags->isProcurementEnterpriseEnabled(),
    'offline.manufacturing' => $flags->isManufacturingEnabled(),
    'offline.payroll' => $flags->isPayrollEnabled(),
    'offline.quality' => $flags->isQualityEnabled(),
    'offline.documents' => $flags->isDocumentsEnabled(),
    'offline.bi' => $flags->isBiEnabled(),
    'offline.pos.complete' => $flags->enabled('offline.pos.complete'),
];

$failed = 0;
echo "RATEB Offline Activation Verify\n";
echo 'dotenv: ' . ($projectRoot . DIRECTORY_SEPARATOR . '.env') . "\n\n";

foreach ($checks as $name => $ok) {
    $label = $ok ? 'ON ' : 'OFF';
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . " [{$label}] {$name}\n";
}

$bundle = $erpRoot . '/public/assets/offline/rateb-offline.js';
$sw = $erpRoot . '/public/rateb-offline-sw.js';
$shell = $erpRoot . '/public/offline-shell.html';
$manifest = $erpRoot . '/public/manifest.webmanifest';

echo "\nAssets\n";
foreach ([
    'SDK bundle' => $bundle,
    'ERP SW' => $sw,
    'offline-shell.html' => $shell,
    'manifest.webmanifest' => $manifest,
] as $label => $path) {
    $ok = is_file($path);
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . " {$label}: {$path}\n";
}

echo "\nNote: offline.sync / offline.background are NOT separate flags.\n";
echo "  Sync/replay: gated by offline.enabled (push/process/OfflineBackgroundSync).\n";
echo "  Warehouse: no dedicated flag — use inventory.movements + master_data.\n";

echo "\n" . ($failed === 0 ? 'ACTIVATION: READY' : 'ACTIVATION: BLOCKED') . " ({$failed} issues)\n";
exit($failed === 0 ? 0 : 1);
