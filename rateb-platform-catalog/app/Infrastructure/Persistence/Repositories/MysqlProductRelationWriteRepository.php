<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationWriteRepositoryInterface;

final class MysqlProductRelationWriteRepository extends BaseRepository implements ProductRelationWriteRepositoryInterface
{
    private const RELATION_TYPES = ['related', 'accessory', 'replacement', 'upsell', 'cross_sell'];

    protected function table(): string
    {
        return 'product_relations';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use addRelation');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Relation updates are not supported in Phase 2.5');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Relation delete is not supported in Phase 2.5');
    }

    public function addRelation(string $productUuid, array $data, ?int $actorId = null): string
    {
        return $this->transaction(function () use ($productUuid, $data, $actorId): string {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $relatedUuid = (string) $data['related_product_uuid'];
            if ($relatedUuid === $productUuid) {
                throw new \InvalidArgumentException('Product cannot relate to itself');
            }

            $relatedId = $this->resolveProductIdByUuid($relatedUuid);
            $relationType = (string) ($data['relation_type'] ?? 'related');
            if (!in_array($relationType, self::RELATION_TYPES, true)) {
                throw new \InvalidArgumentException('Invalid relation_type');
            }

            $uuid = $this->newUuid();
            $this->writePdo->prepare(
                'INSERT INTO product_relations (
                    uuid, product_id, related_product_id, relation_type, sort_order, is_bidirectional, created_by
                 ) VALUES (
                    :uuid, :product_id, :related_product_id, :relation_type, :sort_order, :is_bidirectional, :created_by
                 )'
            )->execute([
                'uuid' => $uuid,
                'product_id' => $productId,
                'related_product_id' => $relatedId,
                'relation_type' => $relationType,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_bidirectional' => (int) ($data['is_bidirectional'] ?? 0),
                'created_by' => $actorId,
            ]);

            if ((int) ($data['is_bidirectional'] ?? 0) === 1) {
                $reverseUuid = $this->newUuid();
                $this->writePdo->prepare(
                    'INSERT INTO product_relations (
                        uuid, product_id, related_product_id, relation_type, sort_order, is_bidirectional, created_by
                     ) VALUES (
                        :uuid, :product_id, :related_product_id, :relation_type, :sort_order, :is_bidirectional, :created_by
                     )
                     ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP(6), deleted_at = NULL, deleted_by = NULL'
                )->execute([
                    'uuid' => $reverseUuid,
                    'product_id' => $relatedId,
                    'related_product_id' => $productId,
                    'relation_type' => $relationType,
                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                    'is_bidirectional' => 1,
                    'created_by' => $actorId,
                ]);
            }

            return $uuid;
        });
    }

    public function replaceForProduct(string $productUuid, array $relations, ?int $actorId = null): void
    {
        $this->transaction(function () use ($productUuid, $relations, $actorId): void {
            $productId = $this->resolveProductIdByUuid($productUuid);

            $this->writePdo->prepare(
                'UPDATE product_relations
                 SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE product_id = :product_id AND deleted_at IS NULL'
            )->execute(['product_id' => $productId, 'deleted_by' => $actorId]);

            foreach ($relations as $relation) {
                $relatedUuid = (string) ($relation['related_product_uuid'] ?? '');
                if ($relatedUuid === '') {
                    continue;
                }
                $this->addRelation($productUuid, [
                    'related_product_uuid' => $relatedUuid,
                    'relation_type' => $relation['relation_type'] ?? 'related',
                    'sort_order' => $relation['sort_order'] ?? 0,
                    'is_bidirectional' => $relation['is_bidirectional'] ?? 0,
                ], $actorId);
            }
        });
    }
}
