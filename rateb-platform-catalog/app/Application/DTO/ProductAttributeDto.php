<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class ProductAttributeDto
{
    /**
     * @param list<array<string, mixed>> $translations
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $attributeUuid,
        public readonly string $attributeCode,
        public readonly ?string $attributeValueUuid,
        public readonly ?string $valueText,
        public readonly ?string $valueNumber,
        public readonly ?bool $valueBoolean,
        public readonly ?string $displayValue,
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
            'attribute_uuid' => $this->attributeUuid,
            'attribute_code' => $this->attributeCode,
            'attribute_value_uuid' => $this->attributeValueUuid,
            'value_text' => $this->valueText,
            'value_number' => $this->valueNumber,
            'value_boolean' => $this->valueBoolean,
            'display_value' => $this->displayValue,
            'translations' => $this->translations,
        ];
    }
}
