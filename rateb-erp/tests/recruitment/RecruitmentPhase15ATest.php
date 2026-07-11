<?php

declare(strict_types=1);

/**
 * Phase 15A — Online Recruitment Platform gate tests.
 * Baseline v1.2: online domain remains source of business logic;
 * Offline Foundation contracts stay frozen (15B is additive only).
 *
 * Run: php tests/recruitment/run-recruitment-phase15a-tests.php
 */

final class RecruitmentPhase15ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testFoundationContractsIntact();
        $this->testMigrationExists();
        $this->testModelsExist();
        $this->testServicesExist();
        $this->testControllersExist();
        $this->testRoutesRegistered();
        $this->testPermissionsConfig();
        $this->testWorkflowTransitions();
        $this->testUuidHelper();
        $this->testViewsExist();
        $this->testOfflineReadinessDoc();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    /**
     * Baseline v1.2 — certify frozen Offline Foundation contracts + online domain ownership.
     * Phase 15B may add recruitment offline; it must not redesign IDB/SDK/queue.
     */
    private function testFoundationContractsIntact(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $qm = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $replay = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/RecruitmentOfflineReplayService.php');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($sdk, "version: '14.2.0'")
            && str_contains($qm, 'client_id')
            && str_contains($qm, 'idempotency_key')
            && str_contains($qm, 'occurred_at')
            && is_file(RATEB_ROOT . '/app/Services/CandidateService.php')
            && str_contains($replay, 'CandidateService')
            && str_contains($replay, 'RecruitmentWorkflowService')
            && !preg_match('/function\s+transition\s*\(/', $replay);
        $this->record('foundation contracts intact (online domain owns business rules)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/181_recruitment_platform.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_recruitment_candidates')
            && str_contains($sql, 'rateb_recruitment_visas')
            && str_contains($sql, 'rateb_recruitment_status_history')
            && str_contains($sql, 'recruitment.manage')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'public_uuid');
        $this->record('migration 181 schema + permissions', $ok);
    }

    private function testModelsExist(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/models/RecruitmentModels.php');
        $ok = str_contains($src, 'class RecruitmentCandidate')
            && str_contains($src, 'class RecruitmentVisa')
            && str_contains($src, 'class RecruitmentMedical')
            && str_contains($src, 'class RecruitmentContract')
            && str_contains($src, 'class RecruitmentInterview')
            && str_contains($src, 'class RecruitmentAgency');
        $this->record('recruitment models present', $ok);
    }

    private function testServicesExist(): void
    {
        $files = [
            'CandidateService.php',
            'VisaService.php',
            'MedicalService.php',
            'RecruitmentContractService.php',
            'InterviewService.php',
            'RecruitmentWorkflowService.php',
            'AssignmentService.php',
            'RecruitmentTimelineService.php',
            'RecruitmentAgencyService.php',
            'RecruitmentSupport.php',
        ];
        $missing = [];
        foreach ($files as $f) {
            if (!is_file(RATEB_ROOT . '/app/services/' . $f)) {
                $missing[] = $f;
            }
        }
        $wf = (string) file_get_contents(RATEB_ROOT . '/app/services/RecruitmentWorkflowService.php');
        $ok = $missing === []
            && str_contains($wf, 'function transition')
            && str_contains($wf, 'STATUS_DEPLOYED');
        $doc = (string) file_get_contents(RATEB_ROOT . '/app/services/AssignmentService.php');
        $ok = $ok && str_contains($doc, 'class RecruitmentDocumentMetaService')
            && str_contains($doc, 'DocumentService');
        $this->record('domain services present', $ok, $missing === [] ? 'ok' : implode(',', $missing));
    }

    private function testControllersExist(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/controllers/Company/RecruitmentControllers.php');
        $ok = str_contains($src, 'CandidateService')
            && str_contains($src, 'RecruitmentWorkflowService')
            && str_contains($src, 'VisaService')
            && !str_contains($src, 'OfflineReplay')
            && !str_contains($src, 'RatebOffline');
        $this->record('controllers use domain services only', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($src, "recruitment/candidates")
            && str_contains($src, 'RecruitmentCandidatesController')
            && str_contains($src, 'transition')
            && str_contains($src, "rateb_erp_mw('recruitment'");
        $this->record('routes registered under recruitment', $ok);
    }

    private function testPermissionsConfig(): void
    {
        $ep = require RATEB_ROOT . '/config/entity-permissions.php';
        $ps = require RATEB_ROOT . '/config/permissions-system.php';
        $mp = require RATEB_ROOT . '/config/module-permissions.php';
        $ok = isset($ep['recruitment-candidates'])
            && in_array('recruitment', $ps['company_modules'] ?? [], true)
            && ($mp['recruitment'] ?? '') === 'recruitment.manage'
            && isset(($ps['permission_implies'] ?? [])['recruitment.manage']);
        $this->record('RBAC config wired', $ok);
    }

    private function testWorkflowTransitions(): void
    {
        $map = \Rateb\App\Services\RecruitmentWorkflowService::allowedTransitions();
        $ok = isset($map['draft'])
            && in_array('registered', $map['draft'], true)
            && isset($map['ready'])
            && in_array('deployed', $map['ready'], true)
            && ($map['archived'] ?? null) === [];
        $this->record('workflow transition map', $ok);
    }

    private function testUuidHelper(): void
    {
        $a = \Rateb\App\Services\RecruitmentSupport::uuidV4();
        $b = \Rateb\App\Services\RecruitmentSupport::uuidV4();
        $ok = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $a) === 1
            && $a !== $b;
        $this->record('uuid v4 helper', $ok, $a);
    }

    private function testViewsExist(): void
    {
        $ok = is_file(RATEB_ROOT . '/views/company/recruitment/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/recruitment/candidates/index.php')
            && is_file(RATEB_ROOT . '/views/company/recruitment/candidates/form.php')
            && is_file(RATEB_ROOT . '/views/company/recruitment/candidates/show.php')
            && is_file(RATEB_ROOT . '/views/company/recruitment/agencies/index.php');
        $this->record('views present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_15A_RECRUITMENT_ONLINE.md';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'Offline Replay Compatible')
            && str_contains($src, 'CandidateService')
            && (str_contains($src, 'Baseline v1.2') || str_contains($src, 'ONLY source of recruitment business logic'));
        $this->record('offline readiness matrix doc', $ok);
    }
}
