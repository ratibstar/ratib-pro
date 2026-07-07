<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductBundleReadRepositoryInterface;

final class MysqlProductBundleReadRepository extends BaseRepository implements ProductBundleReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_bundles';
    }

    public function findByUuid(string $uuid, LocaleContext $locale): ?array
    {
        unset($locale);

        return $this->fetchOne(
            'SELECT uuid FROM product_bundles WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $uuid]
        );
    }

    public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
    {
        unset($locale, $limit, $offset);

        return [];
    }

    public function listComponents(string $bundleProductUuid, LocaleContext $locale): array
    {
        $nameSelect = $this->translationCoalesce('pt', 'name');

        return $this->fetchAll(
            "SELECT pb.uuid, cp.uuid AS component_product_uuid, cv.uuid AS component_variant_uuid,
                    pb.quantity, pb.sort_order, pb.is_optional,
                    cp.sku AS component_sku, {$nameSelect} AS component_name
             FROM product_bundles pb
             INNER JOIN products bp ON bp.id = pb.bundle_product_id AND bp.deleted_at IS NULL
             INNER JOIN products cp ON cp.id = pb.component_product_id AND cp.deleted_at IS NULL
             LEFT JOIN product_variants cv ON cv.id = pb.component_variant_id AND cv.deleted_at IS NULL
             LEFT JOIN product_translations pt_loc ON pt_loc.product_id = cp.id
                AND pt_loc.language_code = :locale AND pt_loc.deleted_at IS NULL
             LEFT JOIN product_translations pt_fb ON pt_fb.product_id = cp.id
                AND pt_fb.language_code = :fallback AND pt_fb.deleted_at IS NULL
             WHERE bp.uuid = :bundle_uuid AND pb.deleted_at IS NULL
             ORDER BY pb.sort_order ASC, pb.id ASC",
            array_merge(['bundle_uuid' => $bundleProductUuid], $this->localeParams($locale))
        );
    }

    public function wouldIntroduceCycle(int $bundleProductId, array $componentProductIds): bool
    {
        foreach ($componentProductIds as $componentId) {
            if ((int) $componentId === $bundleProductId) {
                return true;
            }
            if ($this->reachableFromProduct((int) $componentId, $bundleProductId, [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, bool> $visited
     */
    private function reachableFromProduct(int $currentProductId, int $targetProductId, array $visited): bool
    {
        if (isset($visited[$currentProductId])) {
            return false;
        }
        $visited[$currentProductId] = true;

        $product = $this->fetchOne(
            'SELECT is_bundle FROM products WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $currentProductId]
        );
        if ($product === null || (int) ($product['is_bundle'] ?? 0) !== 1) {
            return false;
        }

        $components = $this->fetchAll(
            'SELECT component_product_id FROM product_bundles
             WHERE bundle_product_id = :bundle_id AND deleted_at IS NULL',
            ['bundle_id' => $currentProductId]
        );

        foreach ($components as $component) {
            $componentId = (int) $component['component_product_id'];
            if ($componentId === $targetProductId) {
                return true;
            }
            if ($this->reachableFromProduct($componentId, $targetProductId, $visited)) {
                return true;
            }
        }

        return false;
    }
}
