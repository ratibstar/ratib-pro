<?php

declare(strict_types=1);

/**
 * Phase 17B — CRM Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-crm-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\CrmOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\CrmOfflineReplayService;
use Rateb\App\Offline\Services\CrmOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class CrmOfflinePhase17bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireCrm();
        $this->testEntityManifestHasCrm();
        $this->testModulesRegistryActiveCrm();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasCrmAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsLeadsWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsCrmModule();
        $this->testAuthzAllowsCrmAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistCrm();
        $this->testOpsFormsCrmHooks();
        $this->testBackgroundReportsCrmFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_CRM',
            'RATEB_OFFLINE_CRM_LEADS',
            'RATEB_OFFLINE_CRM_WORKFLOW',
            'RATEB_OFFLINE_CRM_ACTIVITIES',
            'RATEB_OFFLINE_CRM_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_CRM=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_CRM'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_CRM_LEADS',
            'RATEB_OFFLINE_CRM_WORKFLOW',
            'RATEB_OFFLINE_CRM_ACTIVITIES',
            'RATEB_OFFLINE_CRM_MASTERDATA',
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
        $ok = $svc->enabled('offline.crm') === false
            && $svc->isCrmEnabled() === false
            && $svc->isCrmLeadsEnabled() === false
            && $svc->isCrmWorkflowEnabled() === false
            && $svc->isCrmActivitiesEnabled() === false
            && $svc->isCrmMasterDataEnabled() === false;
        $this->record('CRM flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_CRM=1');
        $_ENV['RATEB_OFFLINE_CRM'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.crm') === true && $svc->isCrmEnabled() === false;
        $this->record('CRM requires master flag', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireCrm(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_CRM_LEADS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_CRM_LEADS'] = '1';
        putenv('RATEB_OFFLINE_CRM');
        unset($_ENV['RATEB_OFFLINE_CRM']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isCrmLeadsEnabled() === false;
        $this->record('leads subflag requires crm', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasCrm(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset(
            $cfg['crm_lead_create'],
            $cfg['crm_workflow_transition'],
            $cfg['crm_lead_source_directory']
        );
        $this->record('entity manifest has CRM ops + directories', $ok);
    }

    private function testModulesRegistryActiveCrm(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('crm', $active, true)
            && isset($ops['crm.lead.create'], $ops['crm.workflow.transition'], $ops['crm.note.create']);
        $this->record('modules registry activates CRM ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = CrmOfflineReplayService::deferredActions();
        $ok = in_array('lead.create', $a, true)
            && in_array('lead.update', $a, true)
            && in_array('workflow.transition', $a, true)
            && in_array('opportunity.create', $a, true)
            && in_array('meeting.create', $a, true)
            && in_array('call.create', $a, true)
            && in_array('task.create', $a, true)
            && in_array('note.create', $a, true)
            && in_array('assignment.create', $a, true)
            && in_array('campaign.create', $a, true)
            && in_array('contact.create', $a, true)
            && in_array('company.create', $a, true);
        $this->record('deferred actions cover Tier-1 CRM', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/crm-adapter.js');
        $ok = str_contains($src, 'lead.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, "module: 'crm'")
            && str_contains($src, 'enqueue')
            && str_contains($src, 'draft:')
            && str_contains($src, 'retry:')
            && str_contains($src, 'status:')
            && str_contains($src, 'sync:')
            && !preg_match('/enqueue\([\'"][^\'"]*delete/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasCrmAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineCrmAdapter')
            && str_contains($src, 'isCrmEnabled')
            && str_contains($src, 'crm: function')
            && str_contains($src, "'offline.crm'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains CRM adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/CrmOfflineReplayService.php');
        $ok = str_contains($src, 'LeadService')
            && str_contains($src, 'CrmWorkflowService')
            && str_contains($src, 'OpportunityService')
            && str_contains($src, 'MeetingService')
            && str_contains($src, 'TaskService')
            && str_contains($src, 'CampaignService')
            && str_contains($src, 'CrmAssignmentService')
            && !str_contains($src, 'INSERT INTO rateb_crm_leads');
        $this->record('replay uses existing CRM domain only', $ok);
    }

    private function testNoDeletePaymentsApprovalsEmailSmsAttachmentsGov(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/CrmOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|approve|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src);
        $this->record('replay excludes delete/payments/approvals/email/sms/attachments/gov', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveCrm(
            ['version' => 2, 'expected_status' => 'new'],
            ['version' => 1, 'status' => 'qualified']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveCrm(
            ['version' => 1, 'expected_status' => 'new'],
            ['version' => 5, 'status' => 'new']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveCrm(
            ['version' => 3, 'expected_status' => 'new'],
            ['version' => 1, 'status' => 'new']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'crm',
            'action' => 'lead.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'lead.create', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'crm_offline_disabled';
        $this->record('replay skips CRM when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'crm',
            'action' => 'lead.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'lead.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'crm_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'crm-off-' . bin2hex(random_bytes(3)),
            'module' => 'crm',
            'action' => 'lead.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects CRM without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueRejectsLeadsWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_CRM_LEADS');
        unset($_ENV['RATEB_OFFLINE_CRM_LEADS']);
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'crm-sub-' . bin2hex(random_bytes(3)),
            'module' => 'crm',
            'action' => 'lead.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1;
        $this->record('queue rejects lead.create without leads subflag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeCrmAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_lead') === 'lead.create'
            && $m->invoke($svc, 'lead.update') === 'lead.update'
            && $m->invoke($svc, 'create_meeting') === 'meeting.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue CRM action aliases', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsCrmModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'crm',
            'action' => 'note.create',
            'url' => 'http://evil',
            'payload' => ['body' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'crm' && !isset($n['url']);
        $this->record('payload sanitizer keeps CRM module', $ok, json_encode($n));
    }

    private function testAuthzAllowsCrmAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['crm']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows CRM ability token', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/CrmOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertLead')
            && str_contains($src, 'assertOpportunity')
            && str_contains($src, 'branch_mismatch')
            && class_exists(CrmOfflineTenantGuard::class);
        $this->record('tenant guard asserts lead/opportunity ownership', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = CrmOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('crm_lead_source_directory', $names, true)
            && in_array('crm_pipeline_stage_directory', $names, true)
            && isset($entities['crm_tag_directory'], $entities['crm_company_directory']);
        $this->record('master-data CRM directories registered', $ok);
    }

    private function testOpsAllowlistCrm(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('crm/leads', $paths, true)
            && in_array('crm/pipeline', $paths, true)
            && in_array('crm/tasks', $paths, true)
            && in_array('crm/meetings', $paths, true)
            && in_array('crm/campaigns', $paths, true);
        $this->record('ops allowlist includes CRM browse paths', $ok);
    }

    private function testOpsFormsCrmHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'lead.create')
            && str_contains($src, 'offline.crm')
            && str_contains($src, 'RatebOfflineCrmAdapter');
        $this->record('ops forms CRM hooks present', $ok);
    }

    private function testBackgroundReportsCrmFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('crm_enabled', $stats)
            && ($stats['crm_enabled'] ?? true) === false;
        $this->record('background reports crm_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('foundation markers untouched (IDB v2, SDK 14.2.0)', $ok);
    }
}
