<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\EapRequest;

/**
 * Tenant + branch isolation for Approval offline replay (Phase 20B).
 * Additive — does not alter Phase 20A EAP domain services.
 */
final class ApprovalOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, request?: array<string, mixed>}
     */
    public function assertRequest(int $requestId, array $scope): array
    {
        if ($requestId < 1) {
            return ['ok' => false, 'error' => 'invalid_request_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new EapRequest())->queryOne(
            'SELECT * FROM rateb_eap_requests WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $requestId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'request_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'request' => $row];
    }

    public function requestExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = (new EapRequest())->queryOne(
            'SELECT id FROM rateb_eap_requests
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }
}
