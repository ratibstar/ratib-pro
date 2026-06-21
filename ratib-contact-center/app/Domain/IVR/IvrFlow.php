<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\IVR;

final class IvrFlow
{
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly bool $isActive,
        public readonly ?int $entryNodeId,
        public readonly string $defaultLocale = 'ar'
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tenant_id'],
            (string) $row['name'],
            (int) ($row['is_active'] ?? 0) === 1,
            isset($row['entry_node_id']) ? (int) $row['entry_node_id'] : null,
            (string) ($row['default_locale'] ?? 'ar')
        );
    }
}
