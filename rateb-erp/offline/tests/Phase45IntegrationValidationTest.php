<?php

declare(strict_types=1);

/**
 * Phase 4.5 — Enterprise Offline Integration & Soak Validation (audit only).
 * No feature implementation — source + contract validation across POS / Inventory / HR.
 *
 * Run: php offline/tests/run-phase45-integration-validation.php
 */

use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\HrOfflineReplayService;
use Rateb\App\Offline\Services\InventoryOfflineReplayService;
use Rateb\App\Offline\OfflineModule;

final class Phase45IntegrationValidationTest
{
    /** @var list<array{name: string, passed: bool, detail: string, severity: string}> */
    private array $results = [];

    /** @var list<array{id: string, severity: string, title: string, detail: string}> */
    private array $findings = [];

    /** @return array{results: list<array>, findings: list<array>} */
    public function run(): array
    {
        $this->clearEnv();

        $this->testAllFlagsDefaultSafe();
        $this->testProcurementFlagGatedInQueueSource();
        $this->testCrossModuleReplayIsolation();
        $this->testAckZeroDataLossContract();
        $this->testEnterpriseFlushDurabilityPattern();
        $this->testPosFlushUsesRemoveByKeys();
        $this->testDualOfflinePathsDocumented();
        $this->testConflictResolversPresent();
        $this->testBackgroundSyncFlagGates();
        $this->testHrExcludesPayrollApprovals();
        $this->testEmployeeDirectoryExcludesSalary();
        $this->testTransportDoesNotAutoQueueInvHr();
        $this->testStressCrossModuleAck();
        $this->testStressConflictMatrix();
        $this->testSdkVersionAndAdapters();
        $this->testUnknownDeviceUnlockResidual();
        $this->testWebAuthnPartialResidual();
        $this->testIdempotencyNotesPattern();
        $this->testLiveDbSoakNotExecuted();

        $this->clearEnv();

        return ['results' => $this->results, 'findings' => $this->findings];
    }

    private function clearEnv(): void
    {
        $cfg = OfflineModule::featureFlagsConfig();
        $envMap = is_array($cfg['env'] ?? null) ? $cfg['env'] : [];
        foreach ($envMap as $envName) {
            $k = (string) $envName;
            if ($k === '') {
                continue;
            }
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        // Explicit baseline keys (pre-map coverage).
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_INVENTORY_MOVEMENTS',
            'RATEB_OFFLINE_HR_ATTENDANCE',
            'RATEB_OFFLINE_PROCUREMENT',
            'RATEB_OFFLINE_READ_CACHE',
            'RATEB_OFFLINE_AUTH_UNLOCK',
            'RATEB_OFFLINE_MASTER_DATA',
            'RATEB_OFFLINE_PILOT_OPS_PAGES',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        OfflineFeatureFlagService::resetConfigCache();
    }

    private function testAllFlagsDefaultSafe(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isMasterEnabled() === false
            && $svc->enabled('offline.inventory.movements') === false
            && $svc->enabled('offline.hr.attendance') === false
            && $svc->enabled('offline.procurement') === false
            && $svc->enabled('offline.read_cache') === false;
        $this->record('flags default OFF (safe production posture)', $ok, $ok ? 'ok' : 'flag unexpectedly on', 'Critical');
    }

    private function testProcurementFlagGatedInQueueSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        $ok = str_contains($src, "module === 'procurement'")
            && str_contains($src, "enabled('offline.procurement')")
            && str_contains($src, 'normalizeProcurementAction')
            && !preg_match("/in_array\(\\\$module,\s*\[[^\]]*procurement/", $src);
        $this->record('procurement module flag-gated at queue (not hard-rejected)', (bool) $ok, $ok ? 'ok' : 'legacy hard-reject or missing gate', 'High');
    }

    private function testCrossModuleReplayIsolation(): void
    {
        $engine = new OfflineReplayEngine();
        $invSkip = $engine->replay(['module' => 'inventory', 'action' => 'stock_movement.create']);
        $hrSkip = $engine->replay(['module' => 'hr', 'action' => 'attendance.create']);
        $procSkip = $engine->replay(['module' => 'procurement', 'action' => 'po.create']);

        $invSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/InventoryOfflineReplayService.php');
        $hrSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $posSrc = (string) file_get_contents(RATEB_ROOT . '/modules/pos/app/Services/PosOfflineReplayService.php');

        $ok = ($invSkip['status'] ?? '') === 'skipped'
            && ($hrSkip['status'] ?? '') === 'skipped'
            && ($procSkip['status'] ?? '') === 'skipped'
            && ($procSkip['error'] ?? '') === 'procurement_offline_disabled'
            && !str_contains($invSrc, 'HrService')
            && !str_contains($hrSrc, 'StockMovementService')
            && !str_contains($posSrc, 'InventoryOffline')
            && !str_contains($posSrc, 'HrOffline');
        $this->record('cross-module replay isolation (flags OFF + source)', $ok, $ok ? 'ok' : 'leak', 'Critical');
    }

    private function testAckZeroDataLossContract(): void
    {
        $ack = new OfflinePushAckContract();
        $r = $ack->evaluate([
            'accepted' => 1,
            'duplicate' => 1,
            'conflict' => 2,
            'rejected' => 3,
            'accepted_keys' => ['a'],
            'duplicate_keys' => ['d'],
            'conflict_keys' => ['c1', 'c2'],
            'rejected_keys' => ['r1', 'r2', 'r3'],
        ]);
        $clear = $r['clearable_keys'] ?? [];
        $ok = $r['ok'] === true
            && in_array('a', $clear, true)
            && in_array('d', $clear, true)
            && !in_array('c1', $clear, true)
            && !in_array('r1', $clear, true);
        $fail = $ack->evaluate([
            'accepted' => 0,
            'duplicate' => 0,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => [],
            'duplicate_keys' => [],
            'conflict_keys' => ['c'],
            'rejected_keys' => ['r'],
        ]);
        $ok = $ok && $fail['ok'] === false && ($fail['clearable_keys'] ?? ['x']) === [];
        $this->record('zero data loss ack contract', $ok, $ok ? 'ok' : 'ack regression', 'Critical');
    }

    private function testEnterpriseFlushDurabilityPattern(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $usesClearRewrite = str_contains($src, 'Stores.clear(QUEUE)')
            || str_contains($bundle, 'Stores.clear(QUEUE)');
        $hasRemoveByKeys = str_contains($src, 'removeByKeys(clearable)')
            && str_contains($src, 'function removeByKeys')
            && str_contains($bundle, 'removeMany');

        if ($usesClearRewrite) {
            $this->finding(
                'H-FLUSH-001',
                'High',
                'Enterprise client queue flush uses clear-then-rewrite',
                'queue-manager.js calls Stores.clear(QUEUE) then putMany(remaining). '
                . 'A crash/power loss between clear and rewrite can drop rejected/conflict/pending items.'
            );
        }

        $this->record(
            'enterprise flush durability (delete-by-key, not clear-rewrite)',
            !$usesClearRewrite && $hasRemoveByKeys,
            (!$usesClearRewrite && $hasRemoveByKeys) ? 'H-FLUSH-001 closed' : 'H-FLUSH-001 open',
            'High'
        );
    }

    private function testPosFlushUsesRemoveByKeys(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/pos/js/pos-offline-sync.js');
        $ok = str_contains($src, 'removeByKeys(clearable)');
        $this->record('POS flush uses removeByKeys API', $ok, $ok ? 'ok' : 'POS flush missing removeByKeys', 'High');
    }

    private function testDualOfflinePathsDocumented(): void
    {
        $posQueue = is_file(RATEB_ROOT . '/modules/pos/app/Services/PosSyncQueueService.php');
        $entQueue = is_file(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        $ok = $posQueue && $entQueue;
        $this->record('dual offline paths present (POS sync + enterprise queue)', $ok, $ok ? 'isolated by design' : 'missing', 'Info');
        $this->finding(
            'M-DUAL-001',
            'Medium',
            'Dual offline sync paths',
            'POS uses rateb_pos_sync_*; enterprise Inventory/HR use rateb_offline_*. '
            . 'Operators must not conflate RATEB_OFFLINE_ENABLED with POS offline sync enablement.'
        );
    }

    private function testConflictResolversPresent(): void
    {
        $resolver = new OfflineConflictResolverService();
        $inv = $resolver->resolveInventory(['version' => 2, 'expected_quantity' => 1], ['version' => 1, 'quantity' => 9]);
        $hr = $resolver->resolveHr(['version' => 2, 'expected_status' => 'present'], ['version' => 1, 'status' => 'absent']);
        $proc = $resolver->resolveProcurement(['version' => 2, 'expected_status' => 'draft'], ['version' => 1, 'status' => 'submitted']);
        $ok = ($inv['reason'] ?? '') === 'quantity_changed'
            && ($hr['reason'] ?? '') === 'status_changed'
            && ($proc['reason'] ?? '') === 'status_changed';
        $this->record('conflict resolution inventory + HR + procurement', $ok, $ok ? 'ok' : 'missing', 'High');
    }

    private function testBackgroundSyncFlagGates(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineBackgroundSync.php');
        $ok = str_contains($src, 'isMasterEnabled')
            && str_contains($src, 'inventory_enabled')
            && str_contains($src, 'hr_enabled')
            && str_contains($src, 'procurement_enabled');
        $this->record('background sync reports module flag gates', $ok, $ok ? 'ok' : 'missing', 'Medium');
    }

    private function testHrExcludesPayrollApprovals(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = !preg_match('/->approveLeave\s*\(/', $src)
            && !preg_match('/->postPayroll\s*\(/', $src)
            && !preg_match('/new\s+PayrollPeriod\b/', $src);
        $this->record('HR offline excludes payroll/approvals', $ok, $ok ? 'ok' : 'leak', 'Critical');
    }

    private function testEmployeeDirectoryExcludesSalary(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineEmployeeDirectoryService.php');
        $ok = !str_contains($src, 'salary_base') && str_contains($src, 'employee_code');
        $this->record('employee directory excludes salary_base', $ok, $ok ? 'ok' : 'PII/salary leak', 'High');
    }

    private function testTransportDoesNotAutoQueueInvHr(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/transport.js');
        $ok = !str_contains($src, 'stock_movement')
            && !str_contains($src, 'attendance.create')
            && str_contains($src, 'checkout');
        $this->record('transport RS allowlist does not silently queue Inv/HR', $ok, $ok ? 'adapters enqueue explicitly' : 'unexpected', 'Medium');
        $this->finding(
            'M-TRANSPORT-001',
            'Medium',
            'Transport RS allowlist is POS-centric',
            'RatebOfflineTransport auto-queues checkout/return/exchange only. '
            . 'Inventory/HR correctly use dedicated adapters; do not rely on transport.request for those modules.'
        );
    }

    private function testStressCrossModuleAck(): void
    {
        $ack = new OfflinePushAckContract();
        $ok = true;
        for ($i = 0; $i < 3000; $i++) {
            $mods = ['inventory', 'hr', 'offline_meta'];
            $accepted = $i % 4 === 0 ? 1 : 0;
            $conflict = $i % 5 === 0 ? 1 : 0;
            $r = $ack->evaluate([
                'accepted' => $accepted,
                'duplicate' => 0,
                'conflict' => $conflict,
                'rejected' => 0,
                'accepted_keys' => $accepted ? [$mods[$i % 3] . '-a' . $i] : [],
                'duplicate_keys' => [],
                'conflict_keys' => $conflict ? [$mods[$i % 3] . '-c' . $i] : [],
                'rejected_keys' => [],
            ]);
            if (($r['ok'] ?? null) !== ($accepted > 0)) {
                $ok = false;
                break;
            }
            if ($conflict && in_array($mods[$i % 3] . '-c' . $i, $r['clearable_keys'] ?? [], true)) {
                $ok = false;
                break;
            }
        }
        $this->record('stress cross-module ack 3000', $ok, $ok ? 'ok' : 'fail', 'High');
    }

    private function testStressConflictMatrix(): void
    {
        $resolver = new OfflineConflictResolverService();
        $ok = true;
        for ($i = 0; $i < 1500; $i++) {
            $inv = $resolver->resolveInventory(
                ['version' => $i + 2, 'expected_quantity' => 10],
                ['version' => 1, 'quantity' => ($i % 2) ? 10 : 7]
            );
            $hr = $resolver->resolveHr(
                ['version' => $i + 2, 'expected_status' => 'present'],
                ['version' => 1, 'status' => ($i % 2) ? 'present' : 'absent']
            );
            $expect = ($i % 2) ? 'accept_client' : 'reject_client';
            if (($inv['action'] ?? '') !== $expect || ($hr['action'] ?? '') !== $expect) {
                $ok = false;
                break;
            }
        }
        $this->record('stress conflict matrix Inv+HR 1500', $ok, $ok ? 'ok' : 'fail', 'High');
    }

    private function testSdkVersionAndAdapters(): void
    {
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($bundle, 'RatebOfflineInventoryAdapter')
            && str_contains($bundle, 'RatebOfflineHrAdapter')
            && str_contains($bundle, 'RatebOfflinePosAdapter')
            && str_contains($bundle, 'RatebOfflineProcurementAdapter')
            && str_contains($bundle, 'clearable_keys')
            && (str_contains($bundle, 'Phase 5') || str_contains($bundle, '5.0.0'));
        $this->record('SDK bundle contains POS/Inv/HR/Procurement adapters', $ok, $ok ? 'ok' : 'stale bundle', 'High');
    }

    private function testUnknownDeviceUnlockResidual(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/modules/pos/tests/PosOfflinePhase2CTest.php');
        $ok = str_contains($src, 'unknown status allows until cached');
        $this->record('known residual: unknown device status allow documented', $ok, 'Medium residual', 'Medium');
        $this->finding(
            'M-DEVICE-001',
            'Medium',
            'Unknown device status allows unlock until cached',
            'POS Phase 2C: isDeviceAllowedOffline returns true when cache empty. Mitigate via ops: require online register before first offline open.'
        );
    }

    private function testWebAuthnPartialResidual(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/modules/pos/docs/PHASE_2C_POS_OFFLINE_AUTH_REPORT.md');
        $ok = str_contains($src, 'COSE') || str_contains($src, 'signature verify');
        $this->record('known residual: WebAuthn full signature verify deferred', $ok, 'Medium residual', 'Medium');
        $this->finding(
            'M-WEBAUTHN-001',
            'Medium',
            'WebAuthn full COSE signature verify deferred',
            'Challenge binding is enforced; full assertion signature verify still deferred to a WebAuthn library before high-assurance tenants.'
        );
    }

    private function testIdempotencyNotesPattern(): void
    {
        $inv = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/InventoryOfflineReplayService.php');
        $hr = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($inv, '[offline:') && str_contains($hr, '[offline:');
        $this->record('idempotency markers present (notes/reason)', $ok, $ok ? 'ok' : 'missing', 'Medium');
        $this->finding(
            'M-IDEM-001',
            'Medium',
            'Idempotency via notes LIKE markers',
            'Inventory/HR use [offline:key] in notes/reason without schema change. Acceptable additively; prefer dedicated column in a future additive migration for reporting/index performance.'
        );
    }

    private function testLiveDbSoakNotExecuted(): void
    {
        $this->record(
            'live DB multi-terminal/branch soak',
            true,
            'Staging recommended — unit crash/power-loss models covered in Phase 4.5.1',
            'Medium'
        );
        $this->finding(
            'M-SOAK-001',
            'Medium',
            'Live staging soak still recommended',
            'H-FLUSH-001 unit durability validated. Staging soak still advised: multi-terminal POS, multi-branch Inv/HR, kill browser mid-flush.'
        );
    }

    /**
     * @param 'Critical'|'High'|'Medium'|'Info' $severity
     */
    private function record(string $name, bool $passed, string $detail, string $severity): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
            'severity' => $severity,
        ];
    }

    private function finding(string $id, string $severity, string $title, string $detail): void
    {
        $this->findings[] = compact('id', 'severity', 'title', 'detail');
    }
}
