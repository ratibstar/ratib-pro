<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSeoReadRepositoryInterface;

final class ProductSeoValidator
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private readonly ProductSeoReadRepositoryInterface $seoReadRepository
    ) {
    }

    /**
     * @param list<array<string, mixed>> $translations
     */
    public function validate(?string $canonicalUrl, array $translations, ?string $excludeProductUuid = null): void
    {
        if ($canonicalUrl !== null && $canonicalUrl !== '' && strlen($canonicalUrl) > 500) {
            throw new \InvalidArgumentException('canonical_url must not exceed 500 characters');
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                throw new \InvalidArgumentException('Each translation must be an object');
            }

            $languageCode = (string) ($translation['language_code'] ?? '');
            if ($languageCode === '') {
                throw new \InvalidArgumentException('language_code is required for each translation');
            }

            $slug = $translation['slug'] ?? null;
            if ($slug !== null && $slug !== '') {
                $slug = (string) $slug;
                if (strlen($slug) > 200) {
                    throw new \InvalidArgumentException('slug must not exceed 200 characters');
                }
                if (!preg_match(self::SLUG_PATTERN, $slug)) {
                    throw new \InvalidArgumentException('slug must be lowercase alphanumeric with hyphens');
                }
                if ($this->seoReadRepository->slugExistsForLanguage($slug, $languageCode, $excludeProductUuid)) {
                    throw new \InvalidArgumentException('slug already exists for language ' . $languageCode);
                }
            }

            $this->assertMaxLength($translation, 'seo_title', 255);
            $this->assertMaxLength($translation, 'seo_description', 500);
            $this->assertMaxLength($translation, 'keywords', 500);
            $this->assertMaxLength($translation, 'og_title', 255);
            $this->assertMaxLength($translation, 'og_description', 500);
            $this->assertMaxLength($translation, 'og_image_path', 500);
            $this->assertMaxLength($translation, 'twitter_title', 255);
            $this->assertMaxLength($translation, 'twitter_description', 500);
            $this->assertMaxLength($translation, 'twitter_image_path', 500);
        }
    }

    /**
     * @param array<string, mixed> $translation
     */
    private function assertMaxLength(array $translation, string $field, int $max): void
    {
        if (!array_key_exists($field, $translation) || $translation[$field] === null) {
            return;
        }

        $value = (string) $translation[$field];
        if (strlen($value) > $max) {
            throw new \InvalidArgumentException($field . ' must not exceed ' . $max . ' characters');
        }
    }
}
