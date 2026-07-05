<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Contracts;

/**
 * Reads POS V2 deployment defaults from environment variables.
 */
interface PosV2EnvironmentFlagReaderInterface
{
    public function getOptionalBool(string $envKey): ?bool;

    public function getOptionalString(string $envKey): ?string;
}
