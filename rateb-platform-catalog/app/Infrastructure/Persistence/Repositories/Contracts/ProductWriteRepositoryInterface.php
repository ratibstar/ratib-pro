<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ProductWriteRepositoryInterface extends WriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $productData
     * @param list<array<string, mixed>> $translations
     */
    public function createWithTranslations(array $productData, array $translations, ?int $actorId = null): string;

    /**
     * @param array<string, mixed> $productData
     * @param list<array<string, mixed>> $translations
     */
    public function updateWithTranslations(
        string $uuid,
        array $productData,
        array $translations,
        int $expectedLockVersion,
        ?int $actorId = null
    ): int;
}
