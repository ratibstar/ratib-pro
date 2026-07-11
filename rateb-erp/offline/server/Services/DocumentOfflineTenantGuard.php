<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Models\DmsDocument;
use Rateb\App\Models\DmsRepository;

/**
 * Tenant + branch isolation for Documents offline replay (Phase 26B).
 * Additive — does not alter Phase 26A DMS domain services.
 */
final class DocumentOfflineTenantGuard
{
    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, document?: array<string, mixed>}
     */
    public function assertDocument(int $documentId, array $scope): array
    {
        return $this->assertRow(
            $documentId,
            $scope,
            'rateb_dms_documents',
            new DmsDocument(),
            'document_not_found',
            'invalid_document_id',
            'document'
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @return array{ok: bool, error?: string, repository?: array<string, mixed>}
     */
    public function assertRepository(int $repositoryId, array $scope): array
    {
        return $this->assertRow(
            $repositoryId,
            $scope,
            'rateb_dms_repositories',
            new DmsRepository(),
            'repository_not_found',
            'invalid_repository_id',
            'repository'
        );
    }

    public function documentExistsForKey(int $companyId, string $idempotencyKey): ?int
    {
        return $this->existsForNotesKey($companyId, 'rateb_dms_documents', new DmsDocument(), $idempotencyKey);
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
     * @return array{ok: bool, error?: string, document?: array<string, mixed>, repository?: array<string, mixed>}
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
