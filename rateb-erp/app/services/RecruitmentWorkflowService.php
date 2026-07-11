<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\RecruitmentCandidate;
use Rateb\App\Models\RecruitmentStatusHistory;

/**
 * Candidate lifecycle transitions — sole authority for workflow_status changes.
 * Future Offline Replay must call transition() — never mutate status directly.
 */
final class RecruitmentWorkflowService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_DOCUMENTS_PENDING = 'documents_pending';
    public const STATUS_INTERVIEW = 'interview';
    public const STATUS_MEDICAL = 'medical';
    public const STATUS_VISA = 'visa';
    public const STATUS_CONTRACT = 'contract';
    public const STATUS_READY = 'ready';
    public const STATUS_DEPLOYED = 'deployed';
    public const STATUS_ARCHIVED = 'archived';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_REGISTERED,
            self::STATUS_DOCUMENTS_PENDING,
            self::STATUS_INTERVIEW,
            self::STATUS_MEDICAL,
            self::STATUS_VISA,
            self::STATUS_CONTRACT,
            self::STATUS_READY,
            self::STATUS_DEPLOYED,
            self::STATUS_ARCHIVED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_REGISTERED, self::STATUS_ARCHIVED],
            self::STATUS_REGISTERED => [self::STATUS_DOCUMENTS_PENDING, self::STATUS_INTERVIEW, self::STATUS_ARCHIVED],
            self::STATUS_DOCUMENTS_PENDING => [self::STATUS_INTERVIEW, self::STATUS_REGISTERED, self::STATUS_ARCHIVED],
            self::STATUS_INTERVIEW => [self::STATUS_MEDICAL, self::STATUS_DOCUMENTS_PENDING, self::STATUS_ARCHIVED],
            self::STATUS_MEDICAL => [self::STATUS_VISA, self::STATUS_INTERVIEW, self::STATUS_ARCHIVED],
            self::STATUS_VISA => [self::STATUS_CONTRACT, self::STATUS_MEDICAL, self::STATUS_ARCHIVED],
            self::STATUS_CONTRACT => [self::STATUS_READY, self::STATUS_VISA, self::STATUS_ARCHIVED],
            self::STATUS_READY => [self::STATUS_DEPLOYED, self::STATUS_CONTRACT, self::STATUS_ARCHIVED],
            self::STATUS_DEPLOYED => [self::STATUS_ARCHIVED],
            self::STATUS_ARCHIVED => [],
        ];
    }

    /**
     * @return array{ok: bool, candidate_id: int, from: string, to: string}
     */
    public function transition(int $candidateId, string $toStatus, ?string $reason = null): array
    {
        $companyId = RecruitmentSupport::requireCompanyId();
        $candidate = RecruitmentSupport::assertCandidate($candidateId, $companyId);
        $from = (string) ($candidate['workflow_status'] ?? self::STATUS_DRAFT);
        $to = trim($toStatus);
        if (!in_array($to, self::statuses(), true)) {
            throw new \InvalidArgumentException('invalid_workflow_status');
        }
        $allowed = self::allowedTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        (new RecruitmentCandidate())->update($candidateId, array_merge([
            'workflow_status' => $to,
        ], RecruitmentSupport::actorFields(false)));

        (new RecruitmentStatusHistory())->create([
            'company_id' => $companyId,
            'candidate_id' => $candidateId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => RecruitmentSupport::userId(),
        ]);

        (new RecruitmentTimelineService())->record(
            $candidateId,
            'workflow',
            'Status: ' . $from . ' → ' . $to,
            $reason,
            'candidate',
            $candidateId
        );

        (new AuditService())->log('recruitment.workflow', 'recruitment_candidate', $candidateId, [
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);

        return ['ok' => true, 'candidate_id' => $candidateId, 'from' => $from, 'to' => $to];
    }
}
