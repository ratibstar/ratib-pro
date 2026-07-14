<?php

declare(strict_types=1);

/**
 * Builds:
 *  1) Legacy monolith public/assets/offline/rateb-offline.js (+ .min.js) — certification / fallback
 *  2) Phase OA modules under public/assets/offline/modules/ — critical-path split
 *
 * Source of truth remains offline/client/** (no duplicated business logic).
 */

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
    'offline/client/adapters/warehouse-adapter.js',
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
    'offline/client/adapters/payroll-adapter.js',
    'offline/client/adapters/quality-adapter.js',
    'offline/client/adapters/documents-adapter.js',
    'offline/client/adapters/bi-adapter.js',
    'offline/client/adapters/form-post-adapter.js',
    'offline/client/adapters/shell-adapter.js',
    'offline/client/adapters/auth-lock-adapter.js',
    'offline/client/adapters/offline-local-session-adapter.js',
    'offline/client/adapters/offline-cold-bootstrap-adapter.js',
    'offline/client/adapters/rbac-cache-adapter.js',
    'offline/client/adapters/master-data-adapter.js',
    'offline/client/adapters/ops-forms-adapter.js',
    'offline/client/core/sdk.js',
];

/** Phase OA public module → ordered source files (concat, never rewritten). */
$modules = [
    'offline-storage.js' => [
        'offline/client/db/schema.js',
        'offline/client/db/migrations.js',
    ],
    'offline-core.js' => [
        'offline/client/core/idempotency.js',
        'offline/client/core/event-bus.js',
    ],
    'offline-network.js' => [
        'offline/client/core/connectivity.js',
    ],
    'offline-queue.js' => [
        'offline/client/sync/queue-manager.js',
    ],
    'offline-replay.js' => [
        'offline/client/sync/replay-scheduler.js',
    ],
    'offline-sync.js' => [
        'offline/client/sync/delta-pull.js',
        'offline/client/core/transport.js',
    ],
    'offline-auth.js' => [
        'offline/client/adapters/auth-lock-adapter.js',
        'offline/client/adapters/offline-local-session-adapter.js',
    ],
    'offline-rbac.js' => [
        'offline/client/adapters/rbac-cache-adapter.js',
    ],
    'offline-shell.js' => [
        'offline/client/adapters/shell-adapter.js',
    ],
    'offline-sdk.js' => [
        'offline/client/core/sdk.js',
    ],
    'offline-pos.js' => [
        'offline/client/adapters/pos-adapter.js',
    ],
    'offline-print.js' => [
        // Printing lives in POS assets; placeholder keeps registry stable.
    ],
    'offline-files.js' => [
        'offline/client/adapters/documents-adapter.js',
    ],
    'offline-monitor.js' => [
        'offline/client/adapters/offline-cold-bootstrap-adapter.js',
    ],
    'offline-diagnostics.js' => [
        'offline/client/adapters/offline-cold-bootstrap-adapter.js',
    ],
    'offline-migrations.js' => [
        'offline/client/db/migrations.js',
    ],
    'offline-crypto.js' => [
        // Crypto is inside auth-lock (PBKDF2/WebAuthn); load auth module for crypto.
        'offline/client/adapters/auth-lock-adapter.js',
    ],
    'offline-adapter-inventory.js' => ['offline/client/adapters/inventory-adapter.js'],
    'offline-adapter-warehouse.js' => ['offline/client/adapters/warehouse-adapter.js'],
    'offline-adapter-hr.js' => ['offline/client/adapters/hr-adapter.js'],
    'offline-adapter-procurement.js' => ['offline/client/adapters/procurement-adapter.js'],
    'offline-adapter-recruitment.js' => ['offline/client/adapters/recruitment-adapter.js'],
    'offline-adapter-accounting.js' => ['offline/client/adapters/accounting-adapter.js'],
    'offline-adapter-crm.js' => ['offline/client/adapters/crm-adapter.js'],
    'offline-adapter-projects.js' => ['offline/client/adapters/projects-adapter.js'],
    'offline-adapter-assets.js' => ['offline/client/adapters/assets-adapter.js'],
    'offline-adapter-approval.js' => ['offline/client/adapters/approval-adapter.js'],
    'offline-adapter-eproc.js' => ['offline/client/adapters/procurement-enterprise-adapter.js'],
    'offline-adapter-manufacturing.js' => ['offline/client/adapters/manufacturing-adapter.js'],
    'offline-adapter-payroll.js' => ['offline/client/adapters/payroll-adapter.js'],
    'offline-adapter-quality.js' => ['offline/client/adapters/quality-adapter.js'],
    'offline-adapter-bi.js' => ['offline/client/adapters/bi-adapter.js'],
    'offline-forms.js' => ['offline/client/adapters/form-post-adapter.js'],
    'offline-master-data.js' => ['offline/client/adapters/master-data-adapter.js'],
    'offline-ops-forms.js' => ['offline/client/adapters/ops-forms-adapter.js'],
];

function rateb_oa_concat(string $root, array $rels): string
{
    $out = '';
    foreach ($rels as $rel) {
        $path = $root . '/' . $rel;
        if (!is_file($path)) {
            throw new RuntimeException('MISSING ' . $rel);
        }
        $name = basename($rel);
        $out .= "/* ---- {$name} ---- */\n";
        $out .= (string) file_get_contents($path);
        if (!str_ends_with($out, "\n")) {
            $out .= "\n";
        }
        $out .= "\n";
    }
    return $out;
}

$out = "/*! RATEB Enterprise Offline SDK Phase 14.2.0 (includes Phases 10-14.2 + 15B + 16B + 17B CRM + 18B Projects + 19B Assets + 20B Approval + 21B EPROC + 22B MFG + 24B Payroll + 25B Quality + 26B Documents + 27B BI; flags default OFF; Phase OA modular build). */\n\n";
$out .= rateb_oa_concat($root, $order);

$dest = $root . '/public/assets/offline/rateb-offline.js';
$min = $root . '/public/assets/offline/rateb-offline.min.js';
file_put_contents($dest, $out);
file_put_contents($min, $out);

$modDir = $root . '/public/assets/offline/modules';
if (!is_dir($modDir) && !mkdir($modDir, 0775, true) && !is_dir($modDir)) {
    fwrite(STDERR, "Cannot create modules dir\n");
    exit(1);
}

$modBytes = 0;
foreach ($modules as $file => $rels) {
    if ($rels === []) {
        $body = "/*! RATEB Offline — {$file} (Phase OA placeholder; no-op). */\n"
            . "(function(){'use strict';})();\n";
    } else {
        $body = "/*! RATEB Offline module {$file} (Phase OA — sourced from offline/client). */\n\n"
            . rateb_oa_concat($root, $rels);
    }
    file_put_contents($modDir . '/' . $file, $body);
    $modBytes += strlen($body);
}

$boot = $root . '/public/assets/offline/offline-bootstrap.js';
$bootBytes = is_file($boot) ? filesize($boot) : 0;

$syncAllowlist = $root . '/offline/scripts/sync-ops-page-allowlist.php';
if (is_file($syncAllowlist)) {
    passthru(PHP_BINARY . ' ' . escapeshellarg($syncAllowlist), $allowlistCode);
    if (($allowlistCode ?? 1) !== 0) {
        fwrite(STDERR, "ops-page-allowlist sync failed\n");
        exit(1);
    }
}

echo 'Wrote monolith ' . strlen($out) . " bytes → rateb-offline.js\n";
echo 'Wrote OA modules ' . count($modules) . ' files / ' . $modBytes . " bytes → modules/\n";
echo 'Bootstrap bytes: ' . (int) $bootBytes . ($bootBytes > 0 && $bootBytes < 20480 ? " OK (<20KB)\n" : "\n");
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
echo (str_contains($out, 'RatebOfflinePayrollAdapter') ? 'HAS payroll adapter' : 'MISSING payroll') . PHP_EOL;
echo (str_contains($out, 'isPayrollEnabled') ? 'HAS isPayrollEnabled' : 'MISSING payroll helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineQualityAdapter') ? 'HAS quality adapter' : 'MISSING quality') . PHP_EOL;
echo (str_contains($out, 'isQualityEnabled') ? 'HAS isQualityEnabled' : 'MISSING quality helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineDocumentsAdapter') ? 'HAS documents adapter' : 'MISSING documents') . PHP_EOL;
echo (str_contains($out, 'isDocumentsEnabled') ? 'HAS isDocumentsEnabled' : 'MISSING documents helper') . PHP_EOL;
echo (str_contains($out, 'RatebOfflineBiAdapter') ? 'HAS bi adapter' : 'MISSING bi') . PHP_EOL;
echo (str_contains($out, 'isBiEnabled') ? 'HAS isBiEnabled' : 'MISSING bi helper') . PHP_EOL;
echo (str_contains($out, 'dbPromise') || str_contains((string) file_get_contents($root . '/offline/client/db/schema.js'), 'dbPromise')
    ? 'HAS idb singleton'
    : 'MISSING idb singleton') . PHP_EOL;
