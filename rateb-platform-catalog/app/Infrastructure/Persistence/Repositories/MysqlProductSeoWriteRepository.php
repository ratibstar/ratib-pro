<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoWriteRepositoryInterface;

final class MysqlProductSeoWriteRepository extends BaseRepository implements ProductSeoWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_seo';
    }

    public function upsertForProduct(
        string $productUuid,
        ?string $canonicalUrl,
        array $translations,
        ?int $actorId = null
    ): string {
        return $this->transaction(function () use ($productUuid, $canonicalUrl, $translations, $actorId): string {
            $product = $this->fetchOne(
                'SELECT id FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['uuid' => $productUuid],
                false
            );
            if ($product === null) {
                throw new \RuntimeException('Product not found', 404);
            }

            $productId = (int) $product['id'];
            $seo = $this->fetchOne(
                'SELECT id, uuid FROM product_seo WHERE product_id = :product_id AND deleted_at IS NULL LIMIT 1',
                ['product_id' => $productId],
                false
            );

            if ($seo === null) {
                $seoUuid = $this->newUuid();
                $this->writePdo->prepare(
                    'INSERT INTO product_seo (uuid, product_id, canonical_url, created_by)
                     VALUES (:uuid, :product_id, :canonical_url, :created_by)'
                )->execute([
                    'uuid' => $seoUuid,
                    'product_id' => $productId,
                    'canonical_url' => $canonicalUrl,
                    'created_by' => $actorId,
                ]);
                $seoId = (int) $this->writePdo->lastInsertId();
            } else {
                $seoUuid = (string) $seo['uuid'];
                $seoId = (int) $seo['id'];
                $this->writePdo->prepare(
                    'UPDATE product_seo SET canonical_url = :canonical_url, updated_by = :updated_by
                     WHERE id = :id'
                )->execute([
                    'id' => $seoId,
                    'canonical_url' => $canonicalUrl,
                    'updated_by' => $actorId,
                ]);
            }

            $this->upsertTranslations($seoId, $translations, $actorId);

            return $seoUuid;
        });
    }

    public function replaceFromSnapshot(string $productUuid, array $seoData, ?int $actorId = null): void
    {
        $canonicalUrl = array_key_exists('canonical_url', $seoData) ? $seoData['canonical_url'] : null;
        if ($canonicalUrl !== null) {
            $canonicalUrl = (string) $canonicalUrl;
            if ($canonicalUrl === '') {
                $canonicalUrl = null;
            }
        }

        $translations = $this->normalizeTranslations($seoData);
        if ($translations === [] && $canonicalUrl === null) {
            return;
        }

        $this->upsertForProduct($productUuid, $canonicalUrl, $translations, $actorId);
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    private function upsertTranslations(int $seoId, array $translations, ?int $actorId): void
    {
        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }
            $languageCode = (string) ($translation['language_code'] ?? '');
            if ($languageCode === '') {
                continue;
            }

            $existing = $this->fetchOne(
                'SELECT id FROM product_seo_translations
                 WHERE product_seo_id = :product_seo_id AND language_code = :language_code AND deleted_at IS NULL
                 LIMIT 1',
                ['product_seo_id' => $seoId, 'language_code' => $languageCode],
                false
            );

            $fields = [
                'slug' => $translation['slug'] ?? null,
                'seo_title' => $translation['seo_title'] ?? null,
                'seo_description' => $translation['seo_description'] ?? null,
                'keywords' => $translation['keywords'] ?? null,
                'og_title' => $translation['og_title'] ?? null,
                'og_description' => $translation['og_description'] ?? null,
                'og_image_path' => $translation['og_image_path'] ?? null,
                'twitter_title' => $translation['twitter_title'] ?? null,
                'twitter_description' => $translation['twitter_description'] ?? null,
                'twitter_image_path' => $translation['twitter_image_path'] ?? null,
            ];

            if ($existing === null) {
                $this->writePdo->prepare(
                    'INSERT INTO product_seo_translations (
                        uuid, product_seo_id, language_code, slug, seo_title, seo_description, keywords,
                        og_title, og_description, og_image_path, twitter_title, twitter_description, twitter_image_path,
                        created_by
                     ) VALUES (
                        :uuid, :product_seo_id, :language_code, :slug, :seo_title, :seo_description, :keywords,
                        :og_title, :og_description, :og_image_path, :twitter_title, :twitter_description, :twitter_image_path,
                        :created_by
                     )'
                )->execute(array_merge(['uuid' => $this->newUuid(), 'product_seo_id' => $seoId, 'language_code' => $languageCode, 'created_by' => $actorId], $fields));
                continue;
            }

            $this->writePdo->prepare(
                'UPDATE product_seo_translations SET
                    slug = :slug,
                    seo_title = :seo_title,
                    seo_description = :seo_description,
                    keywords = :keywords,
                    og_title = :og_title,
                    og_description = :og_description,
                    og_image_path = :og_image_path,
                    twitter_title = :twitter_title,
                    twitter_description = :twitter_description,
                    twitter_image_path = :twitter_image_path,
                    updated_by = :updated_by
                 WHERE id = :id'
            )->execute(array_merge(['id' => (int) $existing['id'], 'updated_by' => $actorId], $fields));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeTranslations(array $seoData): array
    {
        if (isset($seoData['translations']) && is_array($seoData['translations'])) {
            $translations = [];
            foreach ($seoData['translations'] as $localeKey => $translation) {
                if (!is_array($translation)) {
                    continue;
                }
                if (!isset($translation['language_code']) && is_string($localeKey)) {
                    $translation['language_code'] = $localeKey;
                }
                $translations[] = $translation;
            }

            return $translations;
        }

        $legacy = [];
        foreach ($seoData as $localeKey => $translation) {
            if (!is_string($localeKey) || !is_array($translation)) {
                continue;
            }
            if (in_array($localeKey, ['canonical_url', 'translations'], true)) {
                continue;
            }
            $translation['language_code'] = (string) ($translation['language_code'] ?? $localeKey);
            $legacy[] = $translation;
        }

        return $legacy;
    }
}
