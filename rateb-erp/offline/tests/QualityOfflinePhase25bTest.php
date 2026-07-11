<?php

declare(strict_types=1);

/**
 * Phase 25B — Enterprise Quality Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-quality-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\QualityOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\QualityOfflineReplayService;
use Rateb\App\Offline\Services\QualityOfflineTenantGuard;

final class QualityOfflinePhase25bTest
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
        $this->testEntityManifestHasQuality();
        $this->testModulesRegistryActiveQuality();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasQualityAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeleteAttachmentsPayments();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsInspectionWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistQuality();
        $this->testOpsFormsQualityHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_QUALITY',
            'RATEB_OFFLINE_QUALITY_INSPECTIONS',
            'RATEB_OFFLINE_QUALITY_AUDIT',
            'RATEB_OFFLINE_QUALITY_WORKFLOW',
            'RATEB_OFFLINE_QUALITY_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_QUALITY=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_QUALITY'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_QUALITY_INSPECTIONS',
            'RATEB_OFFLINE_QUALITY_AUDIT',
            'RATEB_OFFLINE_QUALITY_WORKFLOW',
            'RATEB_OFFLINE_QUALITY_MASTERDATA',
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

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.quality') === false
            && $svc->isQualityEnabled() === false
            && $svc->isQualityInspectionsEnabled() === false
            && $svc->isQualityAuditEnabled() === false
            && $svc->isQualityWorkflowEnabled() === false
            && $svc->isQualityMasterDataEnabled() === false;
        $this->record('Quality flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_QUALITY=1');
        $_ENV['RATEB_OFFLINE_QUALITY'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.quality') === true
            && $svc->isQualityEnabled() === false;
        $this->record('Quality requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_QUALITY_INSPECTIONS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_QUALITY_INSPECTIONS'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isQualityInspectionsEnabled() === false;
        $this->record('Subflags require offline.quality', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasQuality(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['quality_inspection_create'], $m['quality_workflow_transition'], $m['quality_plan_directory'])
            && ($m['quality_inspection_create']['module'] ?? '') === 'quality'
            && ($m['quality_inspection_create']['replay'] ?? '') === 'delegate_quality';
        $this->record('entity manifest has quality', $ok);
    }

    private function testModulesRegistryActiveQuality(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('quality', $m['active_modules'] ?? [], true)
            && in_array('quality', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['quality.inspection.create'])
            && in_array('payroll', $m['active_modules'] ?? [], true);
        $this->record('modules registry active quality (+ payroll preserved)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = QualityOfflineReplayService::deferredActions();
        $need = [
            'inspection.create', 'inspection.update',
            'checklist.create', 'audit.create',
            'defect.create', 'nonconformity.create',
            'corrective_action.create', 'preventive_action.create',
            'supplier_quality.create', 'complaint.create',
            'assignment.create', 'comment.create',
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
            && !in_array('payment.create', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/quality-adapter.js');
        $ok = str_contains($src, "module: 'quality'")
            && str_contains($src, 'enqueueInspectionCreate')
            && str_contains($src, 'enqueueCorrectiveActionCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"]attachment/i', $src)
            && !preg_match('/enqueue\([\'"]payment/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasQualityAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineQualityAdapter')
            && str_contains($src, 'isQualityEnabled')
            && str_contains($src, 'quality: function')
            && str_contains($src, "'offline.quality'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains quality adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/QualityOfflineReplayService.php');
        $ok = str_contains($src, 'QualityInspectionService')
            && str_contains($src, 'QualityWorkflowService')
            && str_contains($src, 'QualityChecklistService')
            && str_contains($src, 'QmsCorrectiveActionService')
            && str_contains($src, 'QualityAssignmentService')
            && !str_contains($src, 'INSERT INTO rateb_qms_')
            && !str_contains($src, 'QualityCheckService');
        $this->record('replay uses Phase 25A domain only (no SQL)', $ok);
    }

    private function testNoDeleteAttachmentsPayments(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/QualityOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/AccountingService|postJournal/', $src)
            && str_contains($src, 'quality_action_rejected');
        $this->record('replay excludes delete/attachments/payments/GL/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveQuality(
            ['version' => 2, 'expected_status' => 'planned'],
            ['version' => 1, 'status' => 'approved']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveQuality(
            ['version' => 1, 'expected_status' => 'planned'],
            ['version' => 5, 'status' => 'planned']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveQuality(
            ['version' => 3, 'expected_status' => 'planned'],
            ['version' => 1, 'status' => 'planned']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'quality',
            'action' => 'inspection.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'inspection.create', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'quality_offline_disabled';
        $this->record('replay skips quality when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'quality',
            'action' => 'inspection.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'inspection.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'quality_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'qms-off-' . bin2hex(random_bytes(3)),
            'module' => 'quality',
            'action' => 'inspection.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects quality when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsInspectionWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_QUALITY_INSPECTIONS');
        unset($_ENV['RATEB_OFFLINE_QUALITY_INSPECTIONS']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'qms-sub-' . bin2hex(random_bytes(3)),
            'module' => 'quality',
            'action' => 'inspection.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects inspection without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeQualityAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_inspection') === 'inspection.create'
            && $m->invoke($svc, 'quality.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_checklist') === 'checklist.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'quality',
            'action' => 'inspection.create',
            'url' => 'http://evil',
            'payload' => ['title' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'quality' && !isset($n['url']);
        $this->record('payload sanitizer keeps quality module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['quality']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows quality ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/QualityOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertInspection')
            && str_contains($src, 'assertCorrectiveAction')
            && str_contains($src, 'assertChecklist')
            && str_contains($src, 'inspectionExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(QualityOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = QualityOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('quality_plan_directory', $names, true)
            && in_array('quality_checklist_directory', $names, true)
            && isset($entities['quality_plan_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistQuality(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('qms', $paths, true)
            && in_array('qms/inspections', $paths, true)
            && in_array('qms/checklists', $paths, true)
            && in_array('qms/corrective-actions', $paths, true);
        $this->record('ops allowlist quality', $ok);
    }

    private function testOpsFormsQualityHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'quality'")
            && str_contains($src, 'inspection.create')
            && str_contains($src, 'offline.quality')
            && str_contains($src, 'RatebOfflineQualityAdapter');
        $this->record('ops forms quality hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('quality_enabled', $stats)
            && ($stats['quality_enabled'] ?? true) === false;
        $this->record('background reports quality_enabled', $ok, json_encode($stats));
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
