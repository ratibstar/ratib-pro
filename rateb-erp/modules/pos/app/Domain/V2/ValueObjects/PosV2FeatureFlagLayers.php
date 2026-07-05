<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

/**
 * Raw V2 config slices from each resolution layer (terminal → branch → company).
 *
 * Each layer holds the decoded `v2` object from settings_json or device_meta.
 */
final readonly class PosV2FeatureFlagLayers
{
    /**
     * @param array<string, mixed>|null $terminalV2 From rateb_pos_terminals.device_meta.v2
     * @param array<string, mixed>|null $branchV2   From branch-scoped rateb_pos_settings
     * @param array<string, mixed>|null $companyV2  From company-scoped rateb_pos_settings
     */
    public function __construct(
        public ?array $terminalV2,
        public ?array $branchV2,
        public ?array $companyV2,
    ) {
    }

    /**
     * @return list<array<string, mixed>|null>
     */
    public function orderedLayers(): array
    {
        return [$this->terminalV2, $this->branchV2, $this->companyV2];
    }
}
