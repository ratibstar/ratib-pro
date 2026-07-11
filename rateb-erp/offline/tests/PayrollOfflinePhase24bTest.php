<?php

declare(strict_types=1);

/**
 * Phase 24B — Enterprise Payroll Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-payroll-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\PayrollOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\PayrollOfflineReplayService;
use Rateb\App\Offline\Services\PayrollOfflineTenantGuard;

final class PayrollOfflinePhase24bTest
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
        $this->testEntityManifestHasPayroll();
        $this->testModulesRegistryActivePayroll();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasPayrollAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeleteCalculateApprovePostPayments();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsBatchWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistPayroll();
        $this->testOpsFormsPayrollHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_PAYROLL',
            'RATEB_OFFLINE_PAYROLL_EMPLOYEE',
            'RATEB_OFFLINE_PAYROLL_BATCH',
            'RATEB_OFFLINE_PAYROLL_WORKFLOW',
            'RATEB_OFFLINE_PAYROLL_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PAYROLL=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PAYROLL'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_PAYROLL_EMPLOYEE',
            'RATEB_OFFLINE_PAYROLL_BATCH',
            'RATEB_OFFLINE_PAYROLL_WORKFLOW',
            'RATEB_OFFLINE_PAYROLL_MASTERDATA',
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
        $ok = $svc->enabled('offline.payroll') === false
            && $svc->isPayrollEnabled() === false
            && $svc->isPayrollEmployeeEnabled() === false
            && $svc->isPayrollBatchEnabled() === false
            && $svc->isPayrollWorkflowEnabled() === false
            && $svc->isPayrollMasterDataEnabled() === false;
        $this->record('Payroll flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_PAYROLL=1');
        $_ENV['RATEB_OFFLINE_PAYROLL'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.payroll') === true
            && $svc->isPayrollEnabled() === false;
        $this->record('Payroll requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_PAYROLL_BATCH=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_PAYROLL_BATCH'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isPayrollBatchEnabled() === false;
        $this->record('Subflags require offline.payroll', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasPayroll(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['payroll_batch_create'], $m['payroll_workflow_transition'], $m['payroll_structure_directory'])
            && ($m['payroll_batch_create']['module'] ?? '') === 'payroll'
            && ($m['payroll_batch_create']['replay'] ?? '') === 'delegate_payroll';
        $this->record('entity manifest has payroll', $ok);
    }

    private function testModulesRegistryActivePayroll(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('payroll', $m['active_modules'] ?? [], true)
            && in_array('payroll', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['payroll.payroll_batch.create'])
            && in_array('manufacturing', $m['active_modules'] ?? [], true);
        $this->record('modules registry active payroll (+ MFG preserved)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = PayrollOfflineReplayService::deferredActions();
        $need = [
            'salary_structure.create', 'salary_structure.update',
            'employee_salary.create', 'employee_salary.update',
            'payroll_batch.create', 'payroll_batch.update',
            'workflow.transition',
            'loan.create', 'advance.create', 'bonus.create',
            'overtime.create', 'settlement.create',
            'comment.create', 'note.create',
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
            && !in_array('calculate', $a, true)
            && !in_array('approve', $a, true)
            && !in_array('post', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/payroll-adapter.js');
        $ok = str_contains($src, "module: 'payroll'")
            && str_contains($src, 'enqueueSalaryStructureCreate')
            && str_contains($src, 'enqueuePayrollBatchCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"]calculate/i', $src)
            && !preg_match('/enqueue\([\'"]approve/i', $src)
            && !preg_match('/enqueue\([\'"]post/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasPayrollAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflinePayrollAdapter')
            && str_contains($src, 'isPayrollEnabled')
            && str_contains($src, 'payroll: function')
            && str_contains($src, "'offline.payroll'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains payroll adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/PayrollOfflineReplayService.php');
        $ok = str_contains($src, 'PayrollStructureService')
            && str_contains($src, 'PayrollWorkflowService')
            && str_contains($src, 'PayrollBatchService')
            && str_contains($src, 'LoanService')
            && str_contains($src, 'EmployeeSalaryService')
            && !str_contains($src, 'INSERT INTO rateb_payroll_')
            && !str_contains($src, 'PayrollCalculationService')
            && !str_contains($src, 'calculateBatch');
        $this->record('replay uses Phase 24A domain only (no calculate)', $ok);
    }

    private function testNoDeleteCalculateApprovePostPayments(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/PayrollOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/AccountingService|postJournal/', $src)
            && str_contains($src, 'payroll_action_rejected');
        $this->record('replay excludes delete/calculate/approve/post/payments/GL/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolvePayroll(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'approved']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolvePayroll(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolvePayroll(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'payroll',
            'action' => 'payroll_batch.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'payroll_batch.create', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'payroll_offline_disabled';
        $this->record('replay skips payroll when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'payroll',
            'action' => 'payroll_batch.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'payroll_batch.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'payroll_offline_disabled';
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
            'client_id' => 'pay-off-' . bin2hex(random_bytes(3)),
            'module' => 'payroll',
            'action' => 'payroll_batch.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects payroll when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsBatchWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_PAYROLL_BATCH');
        unset($_ENV['RATEB_OFFLINE_PAYROLL_BATCH']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'pay-sub-' . bin2hex(random_bytes(3)),
            'module' => 'payroll',
            'action' => 'payroll_batch.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects batch without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizePayrollAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_payroll_batch') === 'payroll_batch.create'
            && $m->invoke($svc, 'payroll.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_salary_structure') === 'salary_structure.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'payroll',
            'action' => 'payroll_batch.create',
            'url' => 'http://evil',
            'payload' => ['title' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'payroll' && !isset($n['url']);
        $this->record('payload sanitizer keeps payroll module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['payroll']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows payroll ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/PayrollOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertStructure')
            && str_contains($src, 'assertBatch')
            && str_contains($src, 'assertEmployeeSalary')
            && str_contains($src, 'batchExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(PayrollOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = PayrollOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('payroll_structure_directory', $names, true)
            && in_array('payroll_cycle_directory', $names, true)
            && isset($entities['payroll_structure_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistPayroll(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('payroll', $paths, true)
            && in_array('payroll/batches', $paths, true)
            && in_array('payroll/salary-structures', $paths, true)
            && in_array('payroll/loans', $paths, true);
        $this->record('ops allowlist payroll', $ok);
    }

    private function testOpsFormsPayrollHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'payroll'")
            && str_contains($src, 'payroll_batch.create')
            && str_contains($src, 'offline.payroll')
            && str_contains($src, 'RatebOfflinePayrollAdapter');
        $this->record('ops forms payroll hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('payroll_enabled', $stats)
            && ($stats['payroll_enabled'] ?? true) === false;
        $this->record('background reports payroll_enabled', $ok, json_encode($stats));
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
