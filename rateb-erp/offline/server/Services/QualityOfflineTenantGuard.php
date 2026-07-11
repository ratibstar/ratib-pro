<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\QmsChecklist;
use Rateb\App\Models\QmsCorrectiveAction;
use Rateb\App\Models\QmsInspection;

/**
 * Tenant + branch isolation for Quality offline replay (Phase 25B).
 * Additive — does not alter Phase 25A QMS domain services or MFG/EAM quality.
 */
final class QualityOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, inspection?: array<string, mixed>}
     */
    public function assertInspection(int $inspectionId, array $scope): array
    {
        return $this->assertRow(
            $inspectionId,
            $scope,
            'rateb_qms_inspections',
            new QmsInspection(),
            'inspection_not_found',
            'invalid_inspection_id',
            'inspection'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, corrective_action?: array<string, mixed>}
     */
    public function assertCorrectiveAction(int $id, array $scope): array
    {
        return $this->assertRow(
            $id,
            $scope,
            'rateb_qms_corrective_actions',
            new QmsCorrectiveAction(),
            'corrective_action_not_found',
            'invalid_corrective_action_id',
            'corrective_action'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, checklist?: array<string, mixed>}
     */
    public function assertChecklist(int $id, array $scope): array
    {
        return $this->assertRow(
            $id,
            $scope,
            'rateb_qms_checklists',
            new QmsChecklist(),
            'checklist_not_found',
            'invalid_checklist_id',
            'checklist'
        );
    }

    public function inspectionExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_qms_inspections', new QmsInspection(), $idempotencyKey);
    }

    public function checklistExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_qms_checklists', new QmsChecklist(), $idempotencyKey);
    }

    private function existsForNotesKey(int $companyId, string $table, object $model, string $idempotencyKey): ?int
    {
        $key = trim($idempotencyKey);
        if ($companyId < 1 || $key === '') {
            return null;
        }
        $marker = '%[offline:' . $key . ']%';
        $row = $model->queryOne(
            'SELECT id FROM ' . $table . '
             WHERE company_id = :cid AND deleted_at IS NULL AND notes LIKE :m
             LIMIT 1',
            ['cid' => $companyId, 'm' => $marker]
        );

        return $row !== null ? (int) ($row['id'] ?? 0) : null;
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, inspection?: array<string, mixed>, corrective_action?: array<string, mixed>, checklist?: array<string, mixed>}
     */
    private function assertRow(
        int $id,
        array $scope,
        string $table,
        object $model,
        string $notFound,
        string $invalidId,
        string $key
    ): array {
        if ($id < 1) {
            return ['ok' => false, 'error' => $invalidId];
        }
        $companyId = (int) ($scope['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['ok' => false, 'error' => 'company_required'];
        }
        /** @var array<string, mixed>|null $row */
        $row = $model->queryOne(
            'SELECT * FROM ' . $table . ' WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
        if ($row === null) {
            return ['ok' => false, 'error' => $notFound];
        }
        $branchId = (int) ($scope['branch_id'] ?? 0);
        if ($branchId > 0 && isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
            if ((int) $row['branch_id'] !== $branchId) {
                return ['ok' => false, 'error' => 'branch_mismatch'];
            }
        }

        return ['ok' => true, $key => $row];
    }
}
