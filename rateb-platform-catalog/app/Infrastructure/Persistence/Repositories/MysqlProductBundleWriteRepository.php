<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleWriteRepositoryInterface;

final class MysqlProductBundleWriteRepository extends BaseRepository implements ProductBundleWriteRepositoryInterface
{
    public function __construct(
        ?\PDO $readPdo = null,
        ?\PDO $writePdo = null,
        private readonly ?ProductBundleReadRepositoryInterface $bundleReader = null
    ) {
        parent::__construct($readPdo, $writePdo);
    }

    protected function table(): string
    {
        return 'product_bundles';
    }

    public function create(array $data): string
    {
        throw new \LogicException('Use replaceBundle');
    }

    public function update(string $uuid, array $data): bool
    {
        throw new \LogicException('Use replaceBundle');
    }

    public function softDelete(string $uuid, ?int $actorId = null): bool
    {
        throw new \LogicException('Use replaceBundle');
    }

    public function replaceBundle(string $bundleProductUuid, array $components, ?int $actorId = null): void
    {
        $this->transaction(function () use ($bundleProductUuid, $components, $actorId): void {
            $bundle = $this->fetchOne(
                'SELECT id, is_bundle FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
                ['uuid' => $bundleProductUuid],
                false
            );
            if ($bundle === null) {
                throw new \RuntimeException('Product not found', 404);
            }
            if ((int) ($bundle['is_bundle'] ?? 0) !== 1) {
                throw new \InvalidArgumentException('Product is not marked as a bundle');
            }

            $bundleProductId = (int) $bundle['id'];
            $componentProductIds = [];

            foreach ($components as $component) {
                $componentProductId = $this->resolveProductIdByUuid((string) $component['component_product_uuid']);
                $componentProductIds[] = $componentProductId;

                $variantId = null;
                if (isset($component['component_variant_uuid']) && $component['component_variant_uuid'] !== null) {
                    $variantId = $this->resolveVariantIdForProduct(
                        (string) $component['component_variant_uuid'],
                        $componentProductId
                    );
                }
            }

            if ($this->bundleReader()?->wouldIntroduceCycle($bundleProductId, $componentProductIds) === true) {
                throw new \InvalidArgumentException('Circular bundle reference detected');
            }

            $this->writePdo->prepare(
                'UPDATE product_bundles SET deleted_at = CURRENT_TIMESTAMP(6), deleted_by = :deleted_by
                 WHERE bundle_product_id = :bundle_product_id AND deleted_at IS NULL'
            )->execute(['bundle_product_id' => $bundleProductId, 'deleted_by' => $actorId]);

            foreach ($components as $component) {
                $componentProductId = $this->resolveProductIdByUuid((string) $component['component_product_uuid']);
                $variantId = null;
                if (isset($component['component_variant_uuid']) && $component['component_variant_uuid'] !== null) {
                    $variantId = $this->resolveVariantIdForProduct(
                        (string) $component['component_variant_uuid'],
                        $componentProductId
                    );
                }

                $this->writePdo->prepare(
                    'INSERT INTO product_bundles (
                        uuid, bundle_product_id, component_product_id, component_variant_id,
                        quantity, sort_order, is_optional, created_by
                     ) VALUES (
                        :uuid, :bundle_product_id, :component_product_id, :component_variant_id,
                        :quantity, :sort_order, :is_optional, :created_by
                     )'
                )->execute([
                    'uuid' => $this->newUuid(),
                    'bundle_product_id' => $bundleProductId,
                    'component_product_id' => $componentProductId,
                    'component_variant_id' => $variantId,
                    'quantity' => $component['quantity'] ?? '1.0000',
                    'sort_order' => (int) ($component['sort_order'] ?? 0),
                    'is_optional' => (int) ($component['is_optional'] ?? 0),
                    'created_by' => $actorId,
                ]);
            }
        });
    }

    private function resolveVariantIdForProduct(string $variantUuid, int $productId): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM product_variants
             WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $variantUuid, 'product_id' => $productId],
            false
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Variant does not belong to component product');
        }

        return (int) $row['id'];
    }

    private function bundleReader(): ?ProductBundleReadRepositoryInterface
    {
        return $this->bundleReader ?? null;
    }
}
