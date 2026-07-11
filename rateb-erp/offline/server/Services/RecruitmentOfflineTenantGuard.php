<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\RecruitmentAgency;
use Rateb\App\Models\RecruitmentCandidate;

/**
 * Tenant + branch isolation for Recruitment offline replay (Phase 15B).
 * Additive — does not alter Recruitment domain services.
 */
final class RecruitmentOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, candidate?: array<string, mixed>}
     */
    public function assertCandidate(int $candidateId, array $scope): array
    {
        if ($candidateId < 1) {
            return ['ok' => false, 'error' => 'invalid_candidate_id'];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT * FROM rateb_recruitment_candidates
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $candidateId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'candidate_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'candidate' => $row];
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, agency?: array<string, mixed>}
     */
    public function assertAgency(?int $agencyId, array $scope): array
    {
        if ($agencyId === null || $agencyId < 1) {
            return ['ok' => true];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        $row = (new RecruitmentAgency())->queryOne(
            'SELECT * FROM rateb_recruitment_agencies
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $agencyId, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => 'agency_not_found'];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, 'agency' => $row];
    }

    public function candidateExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_recruitment_candidates', 'notes')) {
            return null;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT id FROM rateb_recruitment_candidates
             WHERE company_id = :cid AND notes LIKE :marker AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId, 'marker' => $marker]
        );

        return $row ? (int) ($row['id'] ?? 0) : null;
    }

    public function workflowTransitionExistsForKey(int $companyId, string $idempotencyKey): bool
    {
        if ($companyId < 1 || $idempotencyKey === '' || !OfflineSchema::hasColumn('rateb_recruitment_status_history', 'reason')) {
            return false;
        }
        $marker = '%[offline:' . $idempotencyKey . ']%';
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT id FROM rateb_recruitment_status_history
             WHERE company_id = :cid AND reason LIKE :marker
             ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'marker' => $marker]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (bool) $row;
    }
}
