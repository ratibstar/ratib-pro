<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\EapRequest;
use Rateb\App\Models\EapStatusHistory;

/**
 * Sole authority for EAP request workflow_status changes.
 * Future Offline Replay (20B) must call these methods — never mutate status directly.
 * Distinct from legacy WorkflowService.
 */
final class ApprovalWorkflowService
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function statuses(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::PENDING,
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::ARCHIVED,
        ];
    }

    /** @return array<string, list<string>> */
    public static function allowedTransitions(): array
    {
        return [
            self::DRAFT => [self::SUBMITTED, self::CANCELLED, self::ARCHIVED],
            self::SUBMITTED => [self::PENDING, self::CANCELLED, self::ARCHIVED],
            self::PENDING => [self::APPROVED, self::REJECTED, self::CANCELLED],
            self::APPROVED => [self::ARCHIVED],
            self::REJECTED => [self::ARCHIVED, self::DRAFT],
            self::CANCELLED => [self::ARCHIVED],
            self::ARCHIVED => [],
        ];
    }

    /**
     * @return array{ok: bool, request_id: int, from: string, to: string}
     */
    public function transition(int $requestId, string $toStatus, ?string $reason = null, ?int $expectedVersion = null): array
    {
        $companyId = ApprovalSupport::requireCompanyId();
        $req = ApprovalSupport::assertRequest($requestId, $companyId);
        if ($expectedVersion !== null && (int) ($req['version'] ?? 1) !== $expectedVersion) {
            throw new \RuntimeException('version_conflict');
        }
        $from = (string) ($req['workflow_status'] ?? self::DRAFT);
        $to = trim($toStatus);
        if (!in_array($to, self::statuses(), true)) {
            throw new \InvalidArgumentException('invalid_approval_workflow_status');
        }
        $allowed = self::allowedTransitions()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $update = array_merge([
            'workflow_status' => $to,
            'version' => (int) ($req['version'] ?? 1) + 1,
        ], ApprovalSupport::actorFields(false));
        if ($to === self::SUBMITTED) {
            $update['submitted_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($to, [self::APPROVED, self::REJECTED], true)) {
            $update['decided_at'] = date('Y-m-d H:i:s');
        }
        if ($to === self::ARCHIVED) {
            $update['status'] = 'archived';
        }
        (new EapRequest())->update($requestId, $update);

        (new EapStatusHistory())->create([
            'company_id' => $companyId,
            'request_id' => $requestId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'created_by' => ApprovalSupport::userId(),
        ]);

        (new ApprovalTimelineService())->record(
            'workflow',
            'Approval status: ' . $from . ' → ' . $to,
            $reason,
            $requestId
        );
        (new ApprovalAuditService())->log('workflow_transition', 'Status ' . $from . ' → ' . $to, $requestId, [
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);

        if (in_array($to, [self::SUBMITTED, self::PENDING], true)) {
            $actionType = $to === self::SUBMITTED ? 'submit' : 'comment';
            (new ApprovalActionService())->record([
                'request_id' => $requestId,
                'action_type' => $actionType,
                'comment' => $reason,
            ]);
        }
        if ($to === self::APPROVED) {
            (new ApprovalActionService())->record([
                'request_id' => $requestId,
                'action_type' => 'approve',
                'comment' => $reason,
            ]);
        }
        if ($to === self::REJECTED) {
            (new ApprovalActionService())->record([
                'request_id' => $requestId,
                'action_type' => 'reject',
                'comment' => $reason,
            ]);
        }
        if ($to === self::CANCELLED) {
            (new ApprovalActionService())->record([
                'request_id' => $requestId,
                'action_type' => 'cancel',
                'comment' => $reason,
            ]);
        }

        return ['ok' => true, 'request_id' => $requestId, 'from' => $from, 'to' => $to];
    }
}
