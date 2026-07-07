<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class AttributeDto
{
    /**
     * @param list<AttributeValueDto> $values
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $code,
        public readonly string $inputType,
        public readonly bool $isVariantDefining,
        public readonly bool $isFilterable,
        public readonly bool $isVisible,
        public readonly int $sortOrder,
        public readonly string $status,
        public readonly string $name,
        public readonly array $values = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'input_type' => $this->inputType,
            'is_variant_defining' => $this->isVariantDefining,
            'is_filterable' => $this->isFilterable,
            'is_visible' => $this->isVisible,
            'sort_order' => $this->sortOrder,
            'status' => $this->status,
            'name' => $this->name,
        ];

        if ($this->values !== []) {
            $data['values'] = array_map(static fn (AttributeValueDto $v): array => $v->toArray(), $this->values);
        }

        return $data;
    }
}
