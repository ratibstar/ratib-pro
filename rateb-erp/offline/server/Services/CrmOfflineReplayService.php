<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\CallService;
use Rateb\App\Services\CampaignService;
use Rateb\App\Services\ContactService;
use Rateb\App\Services\CrmAssignmentService;
use Rateb\App\Services\CrmCompanyService;
use Rateb\App\Services\CrmNoteService;
use Rateb\App\Services\CrmWorkflowService;
use Rateb\App\Services\LeadService;
use Rateb\App\Services\MeetingService;
use Rateb\App\Services\OpportunityService;
use Rateb\App\Services\TaskService;

/**
 * Thin CRM offline replay (Phase 17B) — delegates ONLY to Phase 17A domain services.
 * Tier-1 drafts only. No delete / payments / approvals / email / SMS / attachments / gov.
 */
final class CrmOfflineReplayService
{
    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'lead.create',
            'lead.update',
            'workflow.transition',
            'opportunity.create',
            'meeting.create',
            'call.create',
            'task.create',
            'note.create',
            'assignment.create',
            'campaign.create',
            'contact.create',
            'company.create',
            'crm.lead.create',
            'crm.lead.update',
            'crm.workflow.transition',
        ];
    }

    public function __construct(
        private ?CrmOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): CrmOfflineTenantGuard
    {
        return $this->guard ??= new CrmOfflineTenantGuard();
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
            return ['status' => 'skipped', 'error' => 'unknown_crm_action'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isCrmEnabled()) {
            return ['status' => 'skipped', 'error' => 'crm_offline_disabled'];
        }
        if (in_array($action, [
            'lead.create', 'lead.update', 'note.create', 'contact.create', 'company.create',
            'crm.lead.create', 'crm.lead.update',
        ], true) && !$flags->isCrmLeadsEnabled()) {
            return ['status' => 'skipped', 'error' => 'crm_leads_offline_disabled'];
        }
        if (in_array($action, ['workflow.transition', 'crm.workflow.transition'], true)
            && !$flags->isCrmWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'crm_workflow_offline_disabled'];
        }
        if (in_array($action, [
            'meeting.create', 'call.create', 'task.create', 'activity.create',
        ], true) && !$flags->isCrmActivitiesEnabled()) {
            return ['status' => 'skipped', 'error' => 'crm_activities_offline_disabled'];
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
    private function replay(string $action, array $scope, array $inner, string $idempotencyKey): array
    {
        return match ($action) {
            'lead.create', 'crm.lead.create' => $this->leadCreate($scope, $inner, $idempotencyKey),
            'lead.update', 'crm.lead.update' => $this->leadUpdate($scope, $inner),
            'workflow.transition', 'crm.workflow.transition' => $this->workflowTransition($scope, $inner),
            'opportunity.create' => $this->opportunityCreate($inner),
            'meeting.create' => ['ok' => true, 'meeting' => (new MeetingService())->create($inner)],
            'call.create' => ['ok' => true, 'call' => (new CallService())->create($inner)],
            'task.create' => ['ok' => true, 'task' => (new TaskService())->create($inner)],
            'note.create' => ['ok' => true, 'note' => (new CrmNoteService())->create($inner)],
            'assignment.create' => ['ok' => true, 'assignment' => (new CrmAssignmentService())->assign($inner)],
            'campaign.create' => ['ok' => true, 'campaign' => (new CampaignService())->create($inner)],
            'contact.create' => ['ok' => true, 'contact' => (new ContactService())->create($inner)],
            'company.create' => ['ok' => true, 'company' => (new CrmCompanyService())->create($inner)],
            default => throw new \RuntimeException('unknown_crm_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function leadCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->leadExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'lead_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new LeadService())->create($inner);

        return ['ok' => true, 'lead_id' => $created['id'], 'lead_no' => $created['lead_no']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function leadUpdate(array $scope, array $inner): array
    {
        $leadId = (int) ($inner['lead_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertLead($leadId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['lead'] ?? [];
        $status = strtolower((string) ($server['workflow_status'] ?? 'new'));
        if (in_array($status, ['won', 'lost', 'archived'], true) && !empty($inner['expected_status'])) {
            $decision = $this->resolver()->resolveCrm(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_status'] ?? null,
                ],
                [
                    'version' => (int) ($inner['server_version'] ?? 0),
                    'status' => $status,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        if (isset($inner['expected_status'])) {
            $decision = $this->resolver()->resolveCrm(
                [
                    'version' => (int) ($inner['version'] ?? 1),
                    'expected_status' => $inner['expected_status'],
                ],
                [
                    'version' => (int) ($inner['server_version'] ?? 0),
                    'status' => $status,
                ]
            );
            if (($decision['action'] ?? '') === 'reject_client') {
                throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
            }
        }
        $payload = $inner;
        unset($payload['lead_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        (new LeadService())->update($leadId, $payload);

        return ['ok' => true, 'lead_id' => $leadId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $leadId = (int) ($inner['lead_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertLead($leadId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        // Offline may transition any allowed 17A path (including won/lost/archived) —
        // CrmWorkflowService enforces the transition map; no duplicate rules here.
        return (new CrmWorkflowService())->transition(
            $leadId,
            $to,
            isset($inner['reason']) ? (string) $inner['reason'] : null
        );
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function opportunityCreate(array $inner): array
    {
        $created = (new OpportunityService())->create($inner);

        return ['ok' => true, 'opportunity_id' => $created['id'], 'opportunity_no' => $created['opportunity_no']];
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, 'crm.')) {
            $action = substr($action, 4);
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $payload = $queueRow['payload'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'status_changed',
            'server_newer',
            'tenant_mismatch',
            'branch_mismatch',
            'lead_not_found',
            'duplicate_replay',
            'workflow_transition_denied',
        ], true) || str_starts_with($message, 'crm_conflict');
    }
}
