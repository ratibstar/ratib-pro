<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AssignmentService;
use Rateb\App\Services\CandidateService;
use Rateb\App\Services\InterviewService;
use Rateb\App\Services\MedicalService;
use Rateb\App\Services\PassportService;
use Rateb\App\Services\RecruitmentContractService;
use Rateb\App\Services\RecruitmentWorkflowService;
use Rateb\App\Services\VisaService;

/**
 * Thin Recruitment offline replay (Phase 15B) — delegates ONLY to Phase 15A domain services.
 * No duplicated business rules. No government submission / payments / approvals / binary uploads.
 */
final class RecruitmentOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'candidate.create',
            'candidate.update',
            'workflow.transition',
            'assignment.create',
            'interview.create',
            'visa.create',
            'medical.create',
            'passport.create',
            'passport.update',
            'contract.create',
            'note.create',
            'recruitment.candidate.create',
            'recruitment.candidate.update',
            'recruitment.workflow.transition',
            'recruitment.assignment.create',
        ];
    }

    public function __construct(
        private ?RecruitmentOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): RecruitmentOfflineTenantGuard
    {
        return $this->guard ??= new RecruitmentOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_recruitment_action'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isRecruitmentEnabled()) {
            return ['status' => 'skipped', 'error' => 'recruitment_offline_disabled'];
        }
        if (in_array($action, ['candidate.create', 'candidate.update', 'recruitment.candidate.create', 'recruitment.candidate.update', 'note.create'], true)
            && !$flags->isRecruitmentCandidatesEnabled()) {
            return ['status' => 'skipped', 'error' => 'recruitment_candidates_offline_disabled'];
        }
        if (in_array($action, ['workflow.transition', 'recruitment.workflow.transition'], true)
            && !$flags->isRecruitmentWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'recruitment_workflow_offline_disabled'];
        }
        if (in_array($action, ['assignment.create', 'recruitment.assignment.create'], true)
            && !$flags->isRecruitmentAssignmentEnabled()) {
            return ['status' => 'skipped', 'error' => 'recruitment_assignment_offline_disabled'];
        }

        try {
            $scope = (new OfflineReplayScopeService())->fromQueueRow($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    public function replay(string $action, array $scope, array $inner, string $idempotencyKey = ''): array
    {
        $action = $this->normalizeAction($action);

        return match ($action) {
            'candidate.create', 'recruitment.candidate.create'
                => $this->candidateCreate($scope, $inner, $idempotencyKey),
            'candidate.update', 'recruitment.candidate.update'
                => $this->candidateUpdate($scope, $inner, $idempotencyKey),
            'workflow.transition', 'recruitment.workflow.transition'
                => $this->workflowTransition($scope, $inner, $idempotencyKey),
            'assignment.create', 'recruitment.assignment.create'
                => $this->assignmentCreate($scope, $inner),
            'interview.create'
                => $this->interviewCreate($scope, $inner),
            'visa.create'
                => $this->visaCreate($scope, $inner),
            'medical.create'
                => $this->medicalCreate($scope, $inner),
            'passport.create', 'passport.update'
                => $this->passportCreate($scope, $inner),
            'contract.create'
                => $this->contractCreate($scope, $inner),
            'note.create'
                => $this->noteCreate($scope, $inner),
            default => throw new \RuntimeException('unknown_recruitment_action'),
        };
    }

    /**
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed>|null $serverItem
     * @return array<string, mixed>
     */
    public function resolveConflict(array $clientItem, ?array $serverItem): array
    {
        return $this->resolver()->resolveRecruitment($clientItem, $serverItem);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function candidateCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->candidateExistsForKey($scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'idempotent' => true, 'candidate_id' => $existing];
            }
        }
        $agencyId = isset($inner['agency_id']) ? (int) $inner['agency_id'] : null;
        $ag = $this->guard()->assertAgency($agencyId, $scope);
        if (!$ag['ok']) {
            throw new \RuntimeException((string) ($ag['error'] ?? 'tenant_mismatch'));
        }
        $payload = $inner;
        if ($scope['branch_id'] > 0 && empty($payload['branch_id'])) {
            $payload['branch_id'] = $scope['branch_id'];
        }
        if ($idempotencyKey !== '') {
            $notes = trim((string) ($payload['notes'] ?? ''));
            $payload['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new CandidateService())->create($payload);

        return ['ok' => true, 'candidate_id' => $created['id'], 'candidate_no' => $created['candidate_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function candidateUpdate(array $scope, array $inner, string $idempotencyKey): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['candidate'] ?? [];
        if (isset($inner['expected_status']) || isset($inner['expected_workflow_status'])) {
            $decision = $this->resolver()->resolveRecruitment(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_workflow_status'] ?? $inner['expected_status'] ?? null,
                ],
                [
                    'version' => (int) ($inner['server_version'] ?? 0),
                    'status' => (string) ($server['workflow_status'] ?? ''),
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        $payload = $inner;
        unset($payload['candidate_id'], $payload['id'], $payload['expected_status'], $payload['expected_workflow_status'], $payload['server_version']);
        if ($idempotencyKey !== '' && isset($payload['notes'])) {
            $payload['notes'] = trim((string) $payload['notes'] . ' [offline:' . $idempotencyKey . ']');
        }
        (new CandidateService())->update($candidateId, $payload);

        return ['ok' => true, 'candidate_id' => $candidateId, 'updated' => true];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '' && $this->guard()->workflowTransitionExistsForKey($scope['company_id'], $idempotencyKey)) {
            return ['ok' => true, 'idempotent' => true];
        }
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['workflow_status'] ?? ''));
        $reason = trim((string) ($inner['reason'] ?? ''));
        if ($idempotencyKey !== '') {
            $reason = trim($reason . ' [offline:' . $idempotencyKey . ']');
        }
        $out = (new RecruitmentWorkflowService())->transition($candidateId, $to, $reason !== '' ? $reason : null);

        return array_merge(['ok' => true], $out);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function assignmentCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new AssignmentService())->assign($candidateId, $inner);

        return ['ok' => true, 'assignment_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function interviewCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new InterviewService())->create($candidateId, $inner);

        return ['ok' => true, 'interview_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function visaCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new VisaService())->create($candidateId, $inner);

        return ['ok' => true, 'visa_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function medicalCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new MedicalService())->create($candidateId, $inner);

        return ['ok' => true, 'medical_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function passportCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new PassportService())->create($candidateId, $inner);

        return ['ok' => true, 'passport_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function contractCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $id = (new RecruitmentContractService())->create($candidateId, $inner);

        return ['ok' => true, 'contract_id' => $id, 'candidate_id' => $candidateId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function noteCreate(array $scope, array $inner): array
    {
        $candidateId = (int) ($inner['candidate_id'] ?? 0);
        $assert = $this->guard()->assertCandidate($candidateId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $body = trim((string) ($inner['body'] ?? $inner['notes'] ?? ''));
        $visibility = (string) ($inner['visibility'] ?? 'internal');
        $id = (new CandidateService())->addNote($candidateId, $body, $visibility);

        return ['ok' => true, 'note_id' => $id, 'candidate_id' => $candidateId];
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'server_newer',
            'status_changed',
            'branch_mismatch',
            'tenant_mismatch',
            'workflow_transition_denied',
            'recruitment_conflict',
        ], true);
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        $aliases = [
            'create_candidate' => 'candidate.create',
            'update_candidate' => 'candidate.update',
            'transition_workflow' => 'workflow.transition',
            'create_assignment' => 'assignment.create',
            'create_interview' => 'interview.create',
            'create_visa' => 'visa.create',
            'create_medical' => 'medical.create',
            'create_passport' => 'passport.create',
            'update_passport' => 'passport.update',
            'create_contract' => 'contract.create',
            'create_note' => 'note.create',
        ];

        return $aliases[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $raw = $queueRow['payload'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return ['action' => $queueRow['action'] ?? null, 'payload' => []];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['action' => $queueRow['action'] ?? null, 'payload' => []];
    }
}
