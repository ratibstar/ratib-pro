<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Supported POS V2 profiles for UI selection. */
final readonly class PosV2ProfilesContext
{
    /**
     * @param list<string> $available
     */
    public function __construct(
        public string $active,
        public array $available,
    ) {
    }

    /** @return array{active: string, available: list<string>} */
    public function toArray(): array
    {
        return [
            'active' => $this->active,
            'available' => $this->available,
        ];
    }
}
