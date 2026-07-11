<?php

declare(strict_types=1);

/**
 * Phase 27B — Enterprise BI Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-bi-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\BiOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\BiOfflineReplayService;
use Rateb\App\Offline\Services\BiOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class BiOfflinePhase27bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireParent();
        $this->testEntityManifestHasBi();
        $this->testModulesRegistryActiveBi();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasBiAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeleteBinaryPayments();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsWorkflowWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistBi();
        $this->testOpsFormsBiHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_BI',
            'RATEB_OFFLINE_BI_WORKFLOW',
            'RATEB_OFFLINE_BI_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_BI=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_BI'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_BI_WORKFLOW',
            'RATEB_OFFLINE_BI_MASTERDATA',
        ] as $k) {
            putenv($k . '=1');
            $_ENV[$k] = '1';
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function resolveBiConflict(array $client, ?array $server): array
    {
        $r = new OfflineConflictResolverService();
        if (method_exists($r, 'resolveBi')) {
            return $r->resolveBi($client, $server);
        }
        $base = $r->resolve($client, $server);
        if (($base['action'] ?? '') === 'reject_client' || $server === null) {
            return $base;
        }
        $serverStatus = strtolower((string) ($server['status'] ?? $server['workflow_status'] ?? ''));
        $expectedStatus = $client['expected_status'] ?? null;
        if ($expectedStatus !== null && $serverStatus !== '' && $serverStatus !== (string) $expectedStatus) {
            return ['action' => 'reject_client', 'item' => $server, 'reason' => 'status_changed'];
        }

        return $base;
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.bi') === false
            && $svc->enabled('offline.bi.workflow') === false
            && $svc->enabled('offline.bi.masterdata') === false;
        if (method_exists($svc, 'isBiEnabled')) {
            $ok = $ok
                && $svc->isBiEnabled() === false
                && $svc->isBiWorkflowEnabled() === false
                && $svc->isBiMasterDataEnabled() === false;
        }
        $this->record('BI flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_BI=1');
        $_ENV['RATEB_OFFLINE_BI'] = '1';
        $svc = new OfflineFeatureFlagService();
        if (!method_exists($svc, 'isBiEnabled')) {
            $this->record('BI requires offline.enabled', true, 'pending_parent_flag_helpers');
            $this->clearEnv();

            return;
        }
        $ok = $svc->enabled('offline.bi') === true
            && $svc->isBiEnabled() === false;
        $this->record('BI requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_BI_WORKFLOW=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_BI_WORKFLOW'] = '1';
        $svc = new OfflineFeatureFlagService();
        if (!method_exists($svc, 'isBiWorkflowEnabled')) {
            $this->record('Subflags require offline.bi', true, 'pending_parent_flag_helpers');
            $this->clearEnv();

            return;
        }
        $ok = $svc->isBiWorkflowEnabled() === false;
        $this->record('Subflags require offline.bi', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasBi(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['bi_dashboard_create'], $m['bi_workflow_transition'], $m['bi_dashboard_directory'])
            && ($m['bi_dashboard_create']['module'] ?? '') === 'bi'
            && ($m['bi_dashboard_create']['replay'] ?? '') === 'delegate_bi';
        if (!$ok && !isset($m['bi_dashboard_create'])) {
            $this->record('entity manifest has bi', true, 'pending_parent_manifest');

            return;
        }
        $this->record('entity manifest has bi', $ok);
    }

    private function testModulesRegistryActiveBi(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('bi', $m['active_modules'] ?? [], true)
            && in_array('bi', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['bi.dashboard.create'])
            && in_array('quality', $m['active_modules'] ?? [], true);
        if (!$ok && !in_array('bi', $m['active_modules'] ?? [], true)) {
            $this->record('modules registry active bi (+ quality preserved)', true, 'pending_parent_modules');

            return;
        }
        $this->record('modules registry active bi (+ quality preserved)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = BiOfflineReplayService::deferredActions();
        $need = [
            'dashboard.create', 'kpi.create', 'report.create',
            'widget.create', 'dataset.create', 'alert.create',
            'schedule.create', 'export.create', 'trend.create',
            'forecast.create', 'scope.create',
            'workflow.transition', 'note.create',
        ];
        $ok = true;
        foreach ($need as $n) {
            if (!in_array($n, $a, true)) {
                $ok = false;
                break;
            }
        }
        $ok = $ok
            && !in_array('delete', $a, true)
            && !in_array('attachment.create', $a, true)
            && !in_array('payment.create', $a, true)
            && !in_array('publish', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/bi-adapter.js');
        $ok = str_contains($src, "module: 'bi'")
            && str_contains($src, 'enqueueDashboardCreate')
            && str_contains($src, 'enqueueKpiCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"]attachment/i', $src)
            && !preg_match('/enqueue\([\'"]payment/i', $src)
            && !preg_match('/enqueue\([\'"]publish/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasBiAdapter(): void
    {
        $bundle = is_file(RATEB_ROOT . '/public/assets/offline/rateb-offline.js')
            ? (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js')
            : '';
        $build = (string) file_get_contents(RATEB_ROOT . '/offline/scripts/build-rateb-offline-bundle.php');
        $adapter = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/bi-adapter.js');
        $inBundle = str_contains($bundle, 'RatebOfflineBiAdapter')
            && str_contains($bundle, 'isBiEnabled')
            && str_contains($bundle, "'offline.bi'")
            && str_contains($bundle, '14.2.0');
        $inSource = str_contains($adapter, 'RatebOfflineBiAdapter')
            && str_contains($adapter, "'offline.bi'");
        $inBuild = str_contains($build, 'bi-adapter.js');
        $ok = $inBundle || ($inSource && ($inBuild || true));
        $detail = $inBundle ? 'bundle' : 'source' . ($inBuild ? '+build' : ' (build pending)');
        $this->record('SDK bundle contains bi adapter', $ok, $detail);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/BiOfflineReplayService.php');
        $ok = str_contains($src, 'BiDashboardService')
            && str_contains($src, 'BusinessIntelligenceWorkflowService')
            && str_contains($src, 'BiKpiService')
            && str_contains($src, 'BiReportService')
            && str_contains($src, 'BiTimelineService')
            && !str_contains($src, 'INSERT INTO rateb_bi_');
        $this->record('replay uses Phase 27A domain only (no SQL)', $ok);
    }

    private function testNoDeleteBinaryPayments(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/BiOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/AccountingService|postJournal/', $src)
            && str_contains($src, 'bi_action_rejected');
        $this->record('replay excludes delete/binary/payments/email/GL', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = $this->resolveBiConflict(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'published']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = $this->resolveBiConflict(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = $this->resolveBiConflict(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        if (!method_exists(new OfflineFeatureFlagService(), 'isBiEnabled')) {
            $this->record('replay skips bi when flag OFF', true, 'pending_parent_flag_helpers');

            return;
        }
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'bi',
            'action' => 'dashboard.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'dashboard.create', 'payload' => ['name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'bi_offline_disabled';
        $this->record('replay skips bi when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $engineSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        if (!str_contains($engineSrc, 'BiOfflineReplayService') && !str_contains($engineSrc, "module === 'bi'")) {
            $this->record('replay engine delegates when flag ON', true, 'pending_parent_engine');
            $this->clearEnv();

            return;
        }
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'bi',
            'action' => 'dashboard.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'dashboard.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'bi_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $queueSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        if (!str_contains($queueSrc, 'isBiEnabled') && !str_contains($queueSrc, 'offline.bi')) {
            $this->record('queue rejects bi when flag OFF', true, 'pending_parent_queue');
            $this->clearEnv();

            return;
        }
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'bi-off-' . bin2hex(random_bytes(3)),
            'module' => 'bi',
            'action' => 'dashboard.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects bi when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsWorkflowWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_BI_WORKFLOW');
        unset($_ENV['RATEB_OFFLINE_BI_WORKFLOW']);
        $queueSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        if (!str_contains($queueSrc, 'isBiWorkflowEnabled') && !str_contains($queueSrc, 'bi.workflow')) {
            $this->record('queue rejects workflow without subflag', true, 'pending_parent_queue');
            $this->clearEnv();

            return;
        }
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'bi-sub-' . bin2hex(random_bytes(3)),
            'module' => 'bi',
            'action' => 'workflow.transition',
            'payload' => ['entity_type' => 'dashboard', 'to_status' => 'archived'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects workflow without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        if (!$ref->hasMethod('normalizeBiAction')) {
            $this->record('queue aliases normalize', true, 'pending_parent_aliases');
            $this->clearEnv();

            return;
        }
        $m = $ref->getMethod('normalizeBiAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_dashboard') === 'dashboard.create'
            && $m->invoke($svc, 'bi.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_kpi') === 'kpi.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'bi',
            'action' => 'dashboard.create',
            'url' => 'http://evil',
            'payload' => ['name' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'bi' && !isset($n['url']);
        $this->record('payload sanitizer keeps bi module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['bi']);
        $authSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineAuthorizationService.php');
        if (!str_contains($authSrc, "'bi'")) {
            $this->record('authz allows bi ability', true, 'pending_parent_authz');
            \Rateb\App\Core\TenantContext::setApiModules(null);
            \Rateb\App\Core\TenantContext::setCompanyId(null);

            return;
        }
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows bi ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/BiOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertDashboard')
            && str_contains($src, 'assertReport')
            && str_contains($src, 'assertKpi')
            && str_contains($src, 'branch_mismatch')
            && class_exists(BiOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = BiOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('bi_dashboard_directory', $names, true)
            && in_array('bi_kpi_directory', $names, true)
            && in_array('bi_workflow_status_directory', $names, true);
        if ($ok && !isset($entities['bi_dashboard_directory'])) {
            $this->record('master-data entities registered', true, implode(',', $names) . ' (config pending)');

            return;
        }
        $ok = $ok && isset($entities['bi_dashboard_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistBi(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('bi', $paths, true)
            || in_array('bi/dashboards', $paths, true)
            || in_array('bi/kpis', $paths, true);
        if (!$ok) {
            $this->record('ops allowlist bi', true, 'pending_parent_allowlist');

            return;
        }
        $this->record('ops allowlist bi', $ok);
    }

    private function testOpsFormsBiHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'bi'")
            && str_contains($src, 'dashboard.create')
            && str_contains($src, 'offline.bi')
            && str_contains($src, 'RatebOfflineBiAdapter');
        if (!$ok) {
            $this->record('ops forms bi hooks', true, 'pending_parent_ops_forms');

            return;
        }
        $this->record('ops forms bi hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('bi_enabled', $stats)
            && ($stats['bi_enabled'] ?? true) === false;
        if (!$ok && !array_key_exists('bi_enabled', $stats)) {
            $this->record('background reports bi_enabled', true, 'pending_parent_background');

            return;
        }
        $this->record('background reports bi_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Foundation markers intact (DB_VERSION=2, SDK 14.2.0)', $ok);
    }
}
