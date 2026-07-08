<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\DuplicateWriteRepositoryInterface;

final class MysqlDuplicateWriteRepository extends BaseRepository implements DuplicateWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'duplicate_groups';
    }

    public function createGroup(string $groupKey, ?int $ruleId): string
    {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO duplicate_groups (uuid, group_key, match_rule_id, status)
             VALUES (:uuid, :group_key, :match_rule_id, :status)'
        )->execute([
            'uuid' => $uuid,
            'group_key' => $groupKey,
            'match_rule_id' => $ruleId,
            'status' => 'open',
        ]);

        return $uuid;
    }

    public function attachProduct(string $groupUuid, int $productId, ?float $matchScore, bool $isPrimary): void
    {
        $group = $this->fetchOne(
            'SELECT id FROM duplicate_groups WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $groupUuid],
            false
        );
        if ($group === null) {
            throw new \RuntimeException('Duplicate group not found', 404);
        }

        $this->writePdo->prepare(
            'INSERT INTO duplicate_group_products
             (uuid, duplicate_group_id, product_id, match_score, is_primary)
             VALUES (:uuid, :duplicate_group_id, :product_id, :match_score, :is_primary)
             ON DUPLICATE KEY UPDATE
                match_score = VALUES(match_score),
                is_primary = VALUES(is_primary),
                deleted_at = NULL,
                deleted_by = NULL,
                updated_at = CURRENT_TIMESTAMP(6)'
        )->execute([
            'uuid' => $this->newUuid(),
            'duplicate_group_id' => (int) $group['id'],
            'product_id' => $productId,
            'match_score' => $matchScore,
            'is_primary' => (int) $isPrimary,
        ]);
    }

    public function resolveGroup(string $groupUuid, int $resolvedBy, string $status, ?string $note): bool
    {
        $stmt = $this->writePdo->prepare(
            'UPDATE duplicate_groups
             SET status = :status,
                 resolved_by = :resolved_by,
                 resolved_at = CURRENT_TIMESTAMP(6),
                 resolution_note = :resolution_note,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        );
        $stmt->execute([
            'uuid' => $groupUuid,
            'status' => $status,
            'resolved_by' => $resolvedBy,
            'resolution_note' => $note,
        ]);

        return $stmt->rowCount() > 0;
    }
}
