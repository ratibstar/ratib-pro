<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductSeoDto
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $productUuid,
        public readonly ?string $canonicalUrl,
        public readonly array $translations = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'product_uuid' => $this->productUuid,
            'canonical_url' => $this->canonicalUrl,
            'translations' => $this->translations,
        ];
    }
}
