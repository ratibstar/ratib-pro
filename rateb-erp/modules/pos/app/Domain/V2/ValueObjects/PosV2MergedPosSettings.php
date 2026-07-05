<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

/** Decoded and merged rateb_pos_settings payload for one bootstrap scope. */
final readonly class PosV2MergedPosSettings
{
    /**
     * @param array<string, mixed> $root
     * @param array<string, mixed>|null $v2
     */
    public function __construct(
        public int $companyId,
        public int $branchId,
        public bool $found,
        public array $root,
        public ?array $v2,
    ) {
    }

    public function hasV2(): bool
    {
        return is_array($this->v2) && $this->v2 !== [];
    }
}
