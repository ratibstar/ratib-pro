<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;

final class MysqlProductSeoReadRepository extends BaseRepository implements ProductSeoReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_seo';
    }

    public function findByProductUuid(string $productUuid, ?LocaleContext $locale = null): ?array
    {
        if (!$this->tableExists('product_seo')) {
            return null;
        }

        $row = $this->fetchOne(
            'SELECT ps.uuid, ps.canonical_url, p.uuid AS product_uuid
             FROM product_seo ps
             INNER JOIN products p ON p.id = ps.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND ps.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $productUuid]
        );
        if ($row === null) {
            return null;
        }

        $translations = $this->fetchTranslationsForSeoUuid((string) $row['uuid'], $locale);

        return [
            'uuid' => (string) $row['uuid'],
            'product_uuid' => (string) $row['product_uuid'],
            'canonical_url' => $row['canonical_url'] ?? null,
            'translations' => $translations,
        ];
    }

    public function buildSnapshotData(string $productUuid): array
    {
        $seo = $this->findByProductUuid($productUuid);
        if ($seo === null) {
            return [
                'canonical_url' => null,
                'translations' => [],
            ];
        }

        $allTranslations = $this->fetchAll(
            'SELECT pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords,
                    pst.og_title, pst.og_description, pst.og_image_path,
                    pst.twitter_title, pst.twitter_description, pst.twitter_image_path
             FROM product_seo_translations pst
             INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
             INNER JOIN products p ON p.id = ps.product_id AND p.deleted_at IS NULL
             WHERE p.uuid = :uuid AND pst.deleted_at IS NULL
             ORDER BY pst.language_code ASC',
            ['uuid' => $productUuid]
        );

        return [
            'canonical_url' => $seo['canonical_url'] ?? null,
            'translations' => array_map(static fn (array $row): array => [
                'language_code' => (string) $row['language_code'],
                'slug' => $row['slug'] ?? null,
                'seo_title' => $row['seo_title'] ?? null,
                'seo_description' => $row['seo_description'] ?? null,
                'keywords' => $row['keywords'] ?? null,
                'og_title' => $row['og_title'] ?? null,
                'og_description' => $row['og_description'] ?? null,
                'og_image_path' => $row['og_image_path'] ?? null,
                'twitter_title' => $row['twitter_title'] ?? null,
                'twitter_description' => $row['twitter_description'] ?? null,
                'twitter_image_path' => $row['twitter_image_path'] ?? null,
            ], $allTranslations),
        ];
    }

    public function listTranslationsByLocale(string $productUuid): array
    {
        if (!$this->tableExists('product_seo_translations')) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords,
                    pst.og_title, pst.og_description, pst.og_image_path,
                    pst.twitter_title, pst.twitter_description, pst.twitter_image_path
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

    public function slugExistsForLanguage(string $slug, string $languageCode, ?string $excludeProductUuid = null): bool
    {
        $sql = 'SELECT pst.id
                FROM product_seo_translations pst
                INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
                INNER JOIN products p ON p.id = ps.product_id AND p.deleted_at IS NULL
                WHERE pst.slug = :slug AND pst.language_code = :language_code AND pst.deleted_at IS NULL';
        $params = [
            'slug' => $slug,
            'language_code' => $languageCode,
        ];
        if ($excludeProductUuid !== null) {
            $sql .= ' AND p.uuid <> :exclude_uuid';
            $params['exclude_uuid'] = $excludeProductUuid;
        }
        $sql .= ' LIMIT 1';

        return $this->fetchOne($sql, $params) !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTranslationsForSeoUuid(string $seoUuid, ?LocaleContext $locale): array
    {
        if ($locale !== null) {
            $row = $this->fetchOne(
                'SELECT pst.uuid, pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords,
                        pst.og_title, pst.og_description, pst.og_image_path,
                        pst.twitter_title, pst.twitter_description, pst.twitter_image_path
                 FROM product_seo_translations pst
                 INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
                 WHERE ps.uuid = :seo_uuid AND pst.language_code = :language_code AND pst.deleted_at IS NULL
                 LIMIT 1',
                ['seo_uuid' => $seoUuid, 'language_code' => $locale->locale]
            );
            if ($row === null && $locale->fallback !== $locale->locale) {
                $row = $this->fetchOne(
                    'SELECT pst.uuid, pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords,
                            pst.og_title, pst.og_description, pst.og_image_path,
                            pst.twitter_title, pst.twitter_description, pst.twitter_image_path
                     FROM product_seo_translations pst
                     INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
                     WHERE ps.uuid = :seo_uuid AND pst.language_code = :language_code AND pst.deleted_at IS NULL
                     LIMIT 1',
                    ['seo_uuid' => $seoUuid, 'language_code' => $locale->fallback]
                );
            }

            return $row !== null ? [$row] : [];
        }

        return $this->fetchAll(
            'SELECT pst.uuid, pst.language_code, pst.slug, pst.seo_title, pst.seo_description, pst.keywords,
                    pst.og_title, pst.og_description, pst.og_image_path,
                    pst.twitter_title, pst.twitter_description, pst.twitter_image_path
             FROM product_seo_translations pst
             INNER JOIN product_seo ps ON ps.id = pst.product_seo_id AND ps.deleted_at IS NULL
             WHERE ps.uuid = :seo_uuid AND pst.deleted_at IS NULL
             ORDER BY pst.language_code ASC',
            ['seo_uuid' => $seoUuid]
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
