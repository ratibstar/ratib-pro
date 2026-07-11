<?php

declare(strict_types=1);

/**
 * Phase 15B — Recruitment Offline (Tier 1) tests.
 *
 * Run: php offline/tests/run-recruitment-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;
use Rateb\App\Offline\Services\RecruitmentOfflineReplayService;
use Rateb\App\Offline\Services\RecruitmentOfflineTenantGuard;

final class RecruitmentOfflinePhase15bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireRecruitment();
        $this->testEntityManifestHasRecruitment();
        $this->testModulesRegistryActiveRecruitment();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasRecruitmentAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoApprovalsPaymentsGovBinaryInReplay();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsCandidatesWithoutSubflag();
        $this->testPayloadSanitizerKeepsRecruitmentModule();
        $this->testAuthzAllowsRecruitmentAbility();
        $this->testTenantGuardSource();
        $this->testOpsAllowlistRecruitment();
        $this->testOpsFormsRecruitmentHooks();
        $this->testBackgroundReportsRecruitmentFlag();
        $this->testQueueAliases();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_RECRUITMENT',
            'RATEB_OFFLINE_RECRUITMENT_CANDIDATES',
            'RATEB_OFFLINE_RECRUITMENT_WORKFLOW',
            'RATEB_OFFLINE_RECRUITMENT_ASSIGNMENT',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_RECRUITMENT=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_RECRUITMENT'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_RECRUITMENT_CANDIDATES',
            'RATEB_OFFLINE_RECRUITMENT_WORKFLOW',
            'RATEB_OFFLINE_RECRUITMENT_ASSIGNMENT',
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
        $ok = $svc->enabled('offline.recruitment') === false
            && $svc->isRecruitmentEnabled() === false
            && $svc->isRecruitmentCandidatesEnabled() === false
            && $svc->isRecruitmentWorkflowEnabled() === false
            && $svc->isRecruitmentAssignmentEnabled() === false;
        $this->record('recruitment flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_RECRUITMENT=1');
        $_ENV['RATEB_OFFLINE_RECRUITMENT'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.recruitment') === true && $svc->isRecruitmentEnabled() === false;
        $this->record('recruitment requires master flag', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireRecruitment(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_RECRUITMENT_CANDIDATES=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_RECRUITMENT_CANDIDATES'] = '1';
        putenv('RATEB_OFFLINE_RECRUITMENT');
        unset($_ENV['RATEB_OFFLINE_RECRUITMENT']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isRecruitmentCandidatesEnabled() === false;
        $this->record('candidates subflag requires recruitment', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasRecruitment(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset(
            $cfg['recruitment_candidate_create'],
            $cfg['recruitment_workflow_transition'],
            $cfg['recruitment_assignment_create'],
            $cfg['recruitment_agency_directory']
        );
        $this->record('entity manifest has recruitment ops + agency directory', $ok);
    }

    private function testModulesRegistryActiveRecruitment(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('recruitment', $active, true)
            && isset($ops['recruitment.candidate.create'], $ops['recruitment.workflow.transition'], $ops['recruitment.note.create']);
        $this->record('modules registry activates recruitment ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = RecruitmentOfflineReplayService::deferredActions();
        $ok = in_array('candidate.create', $a, true)
            && in_array('candidate.update', $a, true)
            && in_array('workflow.transition', $a, true)
            && in_array('assignment.create', $a, true)
            && in_array('interview.create', $a, true)
            && in_array('visa.create', $a, true)
            && in_array('medical.create', $a, true)
            && in_array('passport.update', $a, true)
            && in_array('contract.create', $a, true)
            && in_array('note.create', $a, true);
        $this->record('deferred actions cover Tier-1 recruitment', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/recruitment-adapter.js');
        $ok = str_contains($src, 'candidate.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, 'assignment.create')
            && str_contains($src, "module: 'recruitment'")
            && str_contains($src, 'enqueue')
            && str_contains($src, 'draft:')
            && str_contains($src, 'retry:')
            && str_contains($src, 'status:')
            && str_contains($src, 'sync:')
            && !preg_match('/enqueue\([\'"][^\'"]*approv/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src);
        $this->record('client adapter queues Tier-1 only', $ok);
    }

    private function testSdkBundleHasRecruitmentAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineRecruitmentAdapter')
            && str_contains($src, 'isRecruitmentEnabled')
            && str_contains($src, 'recruitment: function')
            && str_contains($src, "'offline.recruitment'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains recruitment adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/RecruitmentOfflineReplayService.php');
        $ok = str_contains($src, 'CandidateService')
            && str_contains($src, 'RecruitmentWorkflowService')
            && str_contains($src, 'AssignmentService')
            && str_contains($src, 'InterviewService')
            && str_contains($src, 'VisaService')
            && str_contains($src, 'MedicalService')
            && str_contains($src, 'PassportService')
            && str_contains($src, 'RecruitmentContractService');
        $this->record('replay uses existing recruitment domain only', $ok);
    }

    private function testNoApprovalsPaymentsGovBinaryInReplay(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/RecruitmentOfflineReplayService.php');
        $ok = !preg_match('/->approve/i', $src)
            && !preg_match('/PaymentService|AccountingService|JournalEntry/', $src)
            && !preg_match('/move_uploaded_file|binary_upload/', $src)
            && !preg_match('/GovernmentSubmission|submitToGovernment/', $src);
        $this->record('replay excludes approvals/payments/gov/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveRecruitment(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'registered']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveRecruitment(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveRecruitment(
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
            'module' => 'recruitment',
            'action' => 'candidate.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'candidate.create', 'payload' => ['full_name' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'recruitment_offline_disabled';
        $this->record('replay skips recruitment when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'recruitment',
            'action' => 'candidate.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'candidate.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'recruitment_offline_disabled';
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
            'client_id' => 'rec-off-' . bin2hex(random_bytes(3)),
            'module' => 'recruitment',
            'action' => 'candidate.create',
            'payload' => ['full_name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects recruitment without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueRejectsCandidatesWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_RECRUITMENT_CANDIDATES');
        unset($_ENV['RATEB_OFFLINE_RECRUITMENT_CANDIDATES']);
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'rec-sub-' . bin2hex(random_bytes(3)),
            'module' => 'recruitment',
            'action' => 'candidate.create',
            'payload' => ['full_name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1;
        $this->record('queue rejects candidate.create without candidates subflag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsRecruitmentModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'recruitment',
            'action' => 'note.create',
            'url' => 'http://evil',
            'payload' => ['body' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'recruitment' && !isset($n['url']);
        $this->record('payload sanitizer keeps recruitment module', $ok, json_encode($n));
    }

    private function testAuthzAllowsRecruitmentAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['recruitment']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows recruitment ability token', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/RecruitmentOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertCandidate')
            && str_contains($src, 'assertAgency')
            && str_contains($src, 'branch_mismatch')
            && class_exists(RecruitmentOfflineTenantGuard::class);
        $this->record('tenant guard asserts candidate/agency ownership', $ok);
    }

    private function testOpsAllowlistRecruitment(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('recruitment/candidates', $paths, true)
            && in_array('recruitment/agencies', $paths, true)
            && !in_array('recruitment/payments', $paths, true);
        $this->record('ops allowlist includes recruitment browse paths', $ok);
    }

    private function testOpsFormsRecruitmentHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'candidate.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, 'offline.recruitment')
            && str_contains($src, 'RatebOfflineRecruitmentAdapter');
        $this->record('ops forms maps recruitment paths', $ok);
    }

    private function testBackgroundReportsRecruitmentFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('recruitment_enabled', $stats)
            && ($stats['recruitment_enabled'] ?? true) === false;
        $this->record('background reports recruitment_enabled', $ok, json_encode($stats));
    }

    private function testQueueAliases(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        $ok = str_contains($src, 'normalizeRecruitmentAction')
            && str_contains($src, 'create_candidate')
            && str_contains($src, 'offline.recruitment.candidates');
        $this->record('queue normalizes recruitment aliases + subflags', $ok);
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $qm = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $engine = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($qm, 'client_id')
            && str_contains($qm, 'idempotency_key')
            && str_contains($engine, 'RecruitmentOfflineReplayService')
            && preg_match('/function replay\(/', $engine);
        $this->record('foundation markers intact (IDB v2 + queue fields + additive engine)', $ok);
    }
}
