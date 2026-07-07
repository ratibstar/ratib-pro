<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;

final class MysqlCompletenessDataReadRepository extends BaseRepository implements CompletenessDataReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_translations';
    }

    public function listProductTranslationsByLocale(string $productUuid): array
    {
        $rows = $this->fetchAll(
            'SELECT pt.language_code, pt.name, pt.short_description, pt.description
             FROM product_translations pt
             INNER JOIN products p ON p.id = pt.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pt.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['language_code']] = $row;
        }

        return $grouped;
    }

    public function listSeoTranslationsByLocale(string $productUuid): array
    {
        if (!$this->tableExists('product_seo_translations')) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords
             FROM product_seo_translations pst
             INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
             INNER JOIN products p ON p.id = ps.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pst.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['language_code']] = $row;
        }

        return $grouped;
    }

    public function listImageTranslationsByLocale(string $productUuid): array
    {
        if (!$this->tableExists('product_image_translations')) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT pit.language_code, pit.alt_text
             FROM product_image_translations pit
             INNER JOIN product_images pi ON pi.id = pit.product_image_id AND pi.deleted_at IS NULL
             INNER JOIN products p ON p.id = pi.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pit.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $locale = (string) $row['language_code'];
            $grouped[$locale] ??= [];
            $grouped[$locale][] = $row;
        }

        return $grouped;
    }

    public function listVariantTranslationsByLocale(string $productUuid): array
    {
        if (!$this->tableExists('product_variant_translations')) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT pvt.language_code, pvt.name, pvt.description
             FROM product_variant_translations pvt
             INNER JOIN product_variants pv ON pv.id = pvt.product_variant_id AND pv.deleted_at IS NULL
             INNER JOIN products p ON p.id = pv.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pvt.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $locale = (string) $row['language_code'];
            $grouped[$locale] ??= [];
            $grouped[$locale][] = $row;
        }

        return $grouped;
    }

    public function listAttributeValuesByLocale(string $productUuid): array
    {
        if (!$this->tableExists('product_attribute_translations')) {
            return [];
        }

        return $this->fetchAll(
            'SELECT a.code AS attribute_code, pat.language_code, pat.value_text
             FROM product_attribute_translations pat
             INNER JOIN product_attributes pa ON pa.id = pat.product_attribute_id AND pa.deleted_at IS NULL
             INNER JOIN attributes a ON a.id = pa.attribute_id AND a.deleted_at IS NULL
             INNER JOIN products p ON p.id = pa.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pat.deleted_at IS NULL',
            ['uuid' => $productUuid]
        );
    }

    private function tableExists(string $table): bool
    {
        $row = $this->fetchOne(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1',
            ['table' => $table]
        );

        return $row !== null;
    }
}
