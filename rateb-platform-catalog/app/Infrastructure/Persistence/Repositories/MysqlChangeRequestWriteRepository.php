<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestWriteRepositoryInterface;

final class MysqlChangeRequestWriteRepository extends BaseRepository implements ChangeRequestWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'change_requests';
    }

    public function create(
        int $productId,
        string $requestType,
        array $proposedChanges,
        int $currentVersion,
        ?int $submittedBy,
        array $items
    ): string {
        return $this->transaction(function () use ($productId, $requestType, $proposedChanges, $currentVersion, $submittedBy, $items): string {
            $uuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO change_requests
                 (uuid, product_id, request_type, status, proposed_changes, current_version, submitted_by)
                 VALUES (:uuid, :product_id, :request_type, :status, :proposed_changes, :current_version, :submitted_by)'
            )->execute([
                'uuid' => $uuid,
                'product_id' => $productId,
                'request_type' => $requestType,
                'status' => 'pending',
                'proposed_changes' => json_encode($proposedChanges, JSON_UNESCAPED_UNICODE) ?: '{}',
                'current_version' => $currentVersion,
                'submitted_by' => $submittedBy,
            ]);

            $requestId = (int) $this->writePdo->lastInsertId();
            $itemStmt = $this->writePdo->prepare(
                'INSERT INTO change_request_items (uuid, change_request_id, field_path, old_value, new_value)
                 VALUES (:uuid, :change_request_id, :field_path, :old_value, :new_value)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'uuid' => $this->newUuid(),
                    'change_request_id' => $requestId,
                    'field_path' => (string) $item['field_path'],
                    'old_value' => json_encode($item['old_value'] ?? null, JSON_UNESCAPED_UNICODE),
                    'new_value' => json_encode($item['new_value'] ?? null, JSON_UNESCAPED_UNICODE),
                ]);
            }

            return $uuid;
        });
    }

    public function assignReviewer(string $uuid, int $reviewerId): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE change_requests
             SET reviewer_id = :reviewer_id, status = :status, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL AND status IN (\'pending\', \'in_review\')'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'reviewer_id' => $reviewerId,
            'status' => 'in_review',
        ]);

        return $stmt->rowCount() > 0;
    }

    public function approve(string $uuid, ?int $reviewedBy, ?string $note): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE change_requests
             SET status = :status, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP(6),
                 review_note = :review_note, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL AND status IN (\'pending\', \'in_review\')'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'status' => 'approved',
            'reviewed_by' => $reviewedBy,
            'review_note' => $note,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function reject(string $uuid, ?int $reviewedBy, ?string $note): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE change_requests
             SET status = :status, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP(6),
                 review_note = :review_note, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL AND status IN (\'pending\', \'in_review\')'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'status' => 'rejected',
            'reviewed_by' => $reviewedBy,
            'review_note' => $note,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markApplied(string $uuid): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE change_requests
             SET status = :status, applied_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL AND status = \'approved\''
        );
        $stmt->execute([
            'uuid' => $uuid,
            'status' => 'applied',
        ]);

        return $stmt->rowCount() > 0;
    }

    public function applyApproved(
        string $uuid,
        string $productUuid,
        int $expectedLockVersion,
        int $expectedCurrentVersion,
        array $productData,
        array $translations,
        ?array $seoData,
        array $versionSnapshot,
        ?int $actorId
    ): array {
        return $this->transaction(function () use (
            $uuid,
            $productUuid,
            $expectedLockVersion,
            $expectedCurrentVersion,
            $productData,
            $translations,
            $seoData,
            $versionSnapshot,
            $actorId
        ): array {
            $request = $this->fetchOne(
                'SELECT id, status, current_version FROM change_requests
                 WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $uuid],
                false
            );
            if ($request === null || (string) $request['status'] !== 'approved') {
                throw new \RuntimeException('Change request not found or not approvable', 404);
            }

            $product = $this->fetchOne(
                'SELECT id, lock_version, version_number FROM products
                 WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $productUuid],
                false
            );
            if ($product === null) {
                throw new \RuntimeException('Product not found', 404);
            }
            if ((int) $product['lock_version'] !== $expectedLockVersion) {
                throw new \RuntimeException('version_conflict', 409);
            }
            if ((int) $product['version_number'] !== $expectedCurrentVersion) {
                throw new \RuntimeException('stale_change_request_version', 409);
            }
            if ((int) $request['current_version'] !== $expectedCurrentVersion) {
                throw new \RuntimeException('stale_change_request_version', 409);
            }

            $sets = ['lock_version = lock_version + 1', 'version_number = version_number + 1', 'updated_by = :actor_id'];
            $params = ['uuid' => $productUuid, 'actor_id' => $actorId, 'expected_lock' => $expectedLockVersion];
            $map = ['sku' => 'sku', 'primary_barcode' => 'primary_barcode', 'status' => 'status'];
            foreach ($map as $key => $column) {
                if (array_key_exists($key, $productData)) {
                    $sets[] = $column . ' = :' . $key;
                    $params[$key] = $productData[$key];
                }
            }

            $stmt = $this->writePdo->prepare(
                'UPDATE products SET ' . implode(', ', $sets) . '
                 WHERE uuid = :uuid AND lock_version = :expected_lock AND deleted_at IS NULL'
            );
            $stmt->execute($params);
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('version_conflict', 409);
            }

            if ($translations !== []) {
                $productId = (int) $product['id'];
                foreach ($translations as $localeKey => $translation) {
                    if (!is_array($translation)) {
                        continue;
                    }
                    $languageCode = (string) ($translation['language_code'] ?? (is_string($localeKey) ? $localeKey : ''));
                    if ($languageCode === '') {
                        continue;
                    }
                    $this->writePdo->prepare(
                        'INSERT INTO product_translations (uuid, product_id, language_code, name, short_description, description, created_by)
                         VALUES (:uuid, :product_id, :language_code, :name, :short_description, :description, :created_by)
                         ON DUPLICATE KEY UPDATE
                            name = VALUES(name),
                            short_description = VALUES(short_description),
                            description = VALUES(description),
                            updated_by = VALUES(created_by),
                            updated_at = CURRENT_TIMESTAMP(6),
                            deleted_at = NULL'
                    )->execute([
                        'uuid' => $this->newUuid(),
                        'product_id' => $productId,
                        'language_code' => $languageCode,
                        'name' => $translation['name'] ?? null,
                        'short_description' => $translation['short_description'] ?? null,
                        'description' => $translation['description'] ?? null,
                        'created_by' => $actorId,
                    ]);
                }
            }

            if ($seoData !== null && $seoData !== []) {
                (new MysqlProductSeoWriteRepository($this->readPdo, $this->writePdo))
                    ->replaceFromSnapshot($productUuid, $seoData, $actorId);
            }

            $refreshed = $this->fetchOne(
                'SELECT id, version_number, lock_version FROM products WHERE uuid = :uuid LIMIT 1',
                ['uuid' => $productUuid],
                false
            );
            $versionNumber = (int) ($refreshed['version_number'] ?? $product['version_number']);
            $versionSnapshot['entity_version'] = $versionNumber;
            $versionUuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_versions
                 (uuid, product_id, version_number, change_type, change_summary, snapshot_json, entity_version, created_by)
                 VALUES (:uuid, :product_id, :version_number, :change_type, :change_summary, :snapshot_json, :entity_version, :created_by)'
            )->execute([
                'uuid' => $versionUuid,
                'product_id' => (int) $product['id'],
                'version_number' => $versionNumber,
                'change_type' => 'update',
                'change_summary' => 'Applied change request ' . $uuid,
                'snapshot_json' => json_encode($versionSnapshot, JSON_UNESCAPED_UNICODE) ?: '{}',
                'entity_version' => $versionNumber,
                'created_by' => $actorId,
            ]);

            $this->writePdo->prepare(
                'UPDATE change_requests
                 SET status = :status, applied_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6), updated_by = :actor_id
                 WHERE uuid = :uuid AND deleted_at IS NULL'
            )->execute(['uuid' => $uuid, 'status' => 'applied', 'actor_id' => $actorId]);

            return [
                'version_number' => $versionNumber,
                'lock_version' => (int) ($refreshed['lock_version'] ?? $expectedLockVersion + 1),
                'version_uuid' => $versionUuid,
            ];
        });
    }
}
