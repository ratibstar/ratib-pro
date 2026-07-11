<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\BidComparisonService;
use Rateb\App\Services\EnterpriseContractService;
use Rateb\App\Services\EnterpriseTenderService;
use Rateb\App\Services\ProcurementAssignmentService;
use Rateb\App\Services\ProcurementCommentService;
use Rateb\App\Services\ProcurementWorkflowService;
use Rateb\App\Services\SupplierPortalService;
use Rateb\App\Services\SupplierProfileService;
use Rateb\App\Services\SupplierQualificationService;
use Rateb\App\Services\SupplierRiskService;
use Rateb\App\Services\SupplierScorecardService;
use Rateb\App\Services\TenderBidService;
use Rateb\App\Services\VendorCollaborationService;

/**
 * Thin EPROC offline replay (Phase 21B) — delegates ONLY to Phase 21A domain services.
 * Tier-1 drafts only. Distinct from legacy ProcurementOfflineReplayService.
 * No delete / payments / approvals / notifications / email / SMS / gov / binary uploads.
 */
final class ProcurementEnterpriseOfflineReplayService
{
    private const PREFIX = 'procurement_enterprise.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'supplier_profile.create',
            'supplier_profile.update',
            'qualification.create',
            'qualification.update',
            'risk.create',
            'scorecard.create',
            'portal_invite.create',
            'tender.create',
            'bid.create',
            'bid_compare.create',
            'contract.create',
            'collaboration.create',
            'assignment.create',
            'comment.create',
            'workflow.transition',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    public function __construct(
        private ?ProcurementEnterpriseOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): ProcurementEnterpriseOfflineTenantGuard
    {
        return $this->guard ??= new ProcurementEnterpriseOfflineTenantGuard();
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

        $canonical = [
            'supplier_profile.create', 'supplier_profile.update',
            'qualification.create', 'qualification.update',
            'risk.create', 'scorecard.create', 'portal_invite.create',
            'tender.create', 'bid.create', 'bid_compare.create', 'contract.create',
            'collaboration.create', 'assignment.create', 'comment.create',
            'workflow.transition', 'note.create',
        ];
        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), $canonical, true)) {
            return ['status' => 'skipped', 'error' => 'unknown_procurement_enterprise_action'];
        }
        $action = $this->normalizeAction($action);

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isProcurementEnterpriseEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_enterprise_offline_disabled'];
        }
        if (in_array($action, [
            'supplier_profile.create', 'supplier_profile.update',
            'qualification.create', 'qualification.update',
            'risk.create', 'scorecard.create', 'portal_invite.create', 'collaboration.create',
        ], true) && !$flags->isProcurementEnterpriseSuppliersEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_enterprise_suppliers_offline_disabled'];
        }
        if (in_array($action, ['tender.create', 'bid.create', 'bid_compare.create'], true)
            && !$flags->isProcurementEnterpriseTendersEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_enterprise_tenders_offline_disabled'];
        }
        if ($action === 'contract.create' && !$flags->isProcurementEnterpriseContractsEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_enterprise_contracts_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isProcurementEnterpriseWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'procurement_enterprise_workflow_offline_disabled'];
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
            'supplier_profile.create' => $this->profileCreate($scope, $inner, $idempotencyKey),
            'supplier_profile.update' => $this->profileUpdate($scope, $inner),
            'qualification.create' => [
                'ok' => true,
                'qualification' => (new SupplierQualificationService())->create($inner),
            ],
            'qualification.update' => $this->qualificationUpdate($scope, $inner),
            'risk.create' => ['ok' => true, 'risk' => (new SupplierRiskService())->create($inner)],
            'scorecard.create' => [
                'ok' => true,
                'scorecard' => (new SupplierScorecardService())->create($inner),
            ],
            'portal_invite.create' => [
                'ok' => true,
                'invite' => (new SupplierPortalService())->create($inner),
            ],
            'tender.create' => ['ok' => true, 'tender' => (new EnterpriseTenderService())->create($inner)],
            'bid.create' => ['ok' => true, 'bid' => (new TenderBidService())->create($inner)],
            'bid_compare.create' => [
                'ok' => true,
                'comparison' => (new BidComparisonService())->create($inner),
            ],
            'contract.create' => [
                'ok' => true,
                'contract' => (new EnterpriseContractService())->create($inner),
            ],
            'collaboration.create' => [
                'ok' => true,
                'collaboration' => (new VendorCollaborationService())->create($inner),
            ],
            'assignment.create' => [
                'ok' => true,
                'assignment' => (new ProcurementAssignmentService())->create($inner),
            ],
            'comment.create', 'note.create' => [
                'ok' => true,
                'comment' => (new ProcurementCommentService())->create($inner),
            ],
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            default => throw new \RuntimeException('unknown_procurement_enterprise_action'),
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function profileCreate(array $scope, array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey !== '') {
            $existing = $this->guard()->profileExistsForKey((int) $scope['company_id'], $idempotencyKey);
            if ($existing !== null && $existing > 0) {
                return ['ok' => true, 'profile_id' => $existing, 'duplicate_replay' => true];
            }
            $notes = trim((string) ($inner['notes'] ?? ''));
            $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');
        }
        $created = (new SupplierProfileService())->create($inner);

        return ['ok' => true, 'profile_id' => $created['id'], 'code' => $created['code']];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function profileUpdate(array $scope, array $inner): array
    {
        $profileId = (int) ($inner['profile_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertProfile($profileId, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['profile'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['profile_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new SupplierProfileService())->update($profileId, $payload);

        return ['ok' => true, 'profile_id' => $profileId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function qualificationUpdate(array $scope, array $inner): array
    {
        $id = (int) ($inner['qualification_id'] ?? $inner['id'] ?? 0);
        $assert = $this->guard()->assertQualification($id, $scope);
        if (!$assert['ok']) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['qualification'] ?? [];
        $this->maybeConflict($inner, $server);
        $payload = $inner;
        unset($payload['qualification_id'], $payload['id'], $payload['expected_status'], $payload['server_version'], $payload['workflow_status']);
        if (isset($inner['expected_version'])) {
            $payload['expected_version'] = (int) $inner['expected_version'];
        }
        (new SupplierQualificationService())->update($id, $payload);

        return ['ok' => true, 'qualification_id' => $id];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? '')));
        if ($entityType === '') {
            throw new \InvalidArgumentException('entity_type_required');
        }
        if (!in_array($entityType, ProcurementWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_eproc_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['id'] ?? 0);
        $assert = match ($entityType) {
            ProcurementWorkflowService::ENTITY_SUPPLIER_PROFILE => $this->guard()->assertProfile($id, $scope),
            ProcurementWorkflowService::ENTITY_TENDER => $this->guard()->assertTender($id, $scope),
            ProcurementWorkflowService::ENTITY_CONTRACT => $this->guard()->assertContract($id, $scope),
            ProcurementWorkflowService::ENTITY_QUALIFICATION => $this->guard()->assertQualification($id, $scope),
            ProcurementWorkflowService::ENTITY_COLLABORATION => $this->guard()->assertCollaboration($id, $scope),
            default => ['ok' => false, 'error' => 'invalid_eproc_entity_type'],
        };
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new ProcurementWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
    }

    /**
     * @param array<string, mixed> $inner
     * @param array<string, mixed> $server
     */
    private function maybeConflict(array $inner, array $server): void
    {
        if (!isset($inner['expected_status'])) {
            return;
        }
        $status = strtolower((string) ($server['workflow_status'] ?? 'draft'));
        $decision = $this->resolver()->resolveProcurementEnterprise(
            [
                'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
                'expected_status' => $inner['expected_status'],
            ],
            [
                'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
                'status' => $status,
            ]
        );
        if (($decision['action'] ?? '') === 'reject_client') {
            throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
        }
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, self::PREFIX)) {
            $action = substr($action, strlen(self::PREFIX));
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
            'version_conflict',
            'tenant_mismatch',
            'branch_mismatch',
            'profile_not_found',
            'tender_not_found',
            'contract_not_found',
            'qualification_not_found',
            'collaboration_not_found',
            'workflow_transition_denied',
        ], true) || str_starts_with($message, 'procurement_enterprise_conflict');
    }
}
