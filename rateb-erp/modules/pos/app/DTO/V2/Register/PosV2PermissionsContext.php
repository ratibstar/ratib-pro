<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Register;

/** Granted POS permission slugs for the active cashier. */
final readonly class PosV2PermissionsContext
{
    /**
     * @param list<string> $slugs
     */
    public function __construct(
        public array $slugs,
    ) {
    }

    /** @return array{slugs: list<string>} */
    public function toArray(): array
    {
        return [
            'slugs' => $this->slugs,
        ];
    }
}
