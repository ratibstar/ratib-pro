<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Pos\Services\V2\Contracts\PosV2EnvironmentFlagReaderInterface;

/**
 * Reads POS V2 environment variable defaults (cached per instance).
 */
final class PosV2EnvironmentFlagReader implements PosV2EnvironmentFlagReaderInterface
{
    /** @var array<string, bool|null> */
    private array $boolCache = [];

    /** @var array<string, string|null> */
    private array $stringCache = [];

    public function getOptionalBool(string $envKey): ?bool
    {
        if (array_key_exists($envKey, $this->boolCache)) {
            return $this->boolCache[$envKey];
        }

        $raw = getenv($envKey);
        if ($raw === false || trim((string) $raw) === '') {
            $this->boolCache[$envKey] = null;

            return null;
        }

        $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->boolCache[$envKey] = $parsed;

        return $parsed;
    }

    public function getOptionalString(string $envKey): ?string
    {
        if (array_key_exists($envKey, $this->stringCache)) {
            return $this->stringCache[$envKey];
        }

        $raw = getenv($envKey);
        if ($raw === false) {
            $this->stringCache[$envKey] = null;

            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            $this->stringCache[$envKey] = null;

            return null;
        }

        $this->stringCache[$envKey] = $value;

        return $value;
    }
}
