<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface CompletenessDataReadRepositoryInterface
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function listProductTranslationsByLocale(string $productUuid): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function listSeoTranslationsByLocale(string $productUuid): array;

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function listImageTranslationsByLocale(string $productUuid): array;

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function listVariantTranslationsByLocale(string $productUuid): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listAttributeValuesByLocale(string $productUuid): array;
}
