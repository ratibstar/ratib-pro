<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\DTO;

final class CategoryDto
{
    /**
     * @param list<CategoryDto> $children
     */
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $parentUuid,
        public readonly string $slug,
        public readonly int $depth,
        public readonly string $path,
        public readonly int $sortOrder,
        public readonly ?string $imagePath,
        public readonly string $status,
        public readonly string $name,
        public readonly ?string $description,
        public readonly array $children = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'uuid' => $this->uuid,
            'parent_uuid' => $this->parentUuid,
            'slug' => $this->slug,
            'depth' => $this->depth,
            'path' => $this->path,
            'sort_order' => $this->sortOrder,
            'image_path' => $this->imagePath,
            'status' => $this->status,
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ($this->children !== []) {
            $data['children'] = array_map(static fn (CategoryDto $child): array => $child->toArray(), $this->children);
        }

        return $data;
    }
}
