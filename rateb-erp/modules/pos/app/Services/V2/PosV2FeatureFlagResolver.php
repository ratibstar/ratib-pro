<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2ResolvedFeatureFlags;
use Rateb\App\Pos\Services\V2\Contracts\PosV2EnvironmentFlagReaderInterface;

/**
 * Resolves feature flags from layers with priority: terminal → branch → company → env → defaults.
 */
final class PosV2FeatureFlagResolver
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(
        private readonly PosV2EnvironmentFlagReaderInterface $environmentReader,
        ?array $config = null,
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    public function resolve(PosV2FeatureFlagLayers $layers): PosV2ResolvedFeatureFlags
    {
        $ordered = $layers->orderedLayers();

        return new PosV2ResolvedFeatureFlags(
            enabled: $this->resolveEnabled($ordered),
            profile: $this->resolveProfile($ordered),
            scanMode: $this->resolveScanMode($ordered),
            offline: $this->resolveOffline($ordered),
            cardTerminal: $this->resolveCardTerminal($ordered),
        );
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveEnabled(array $ordered): bool
    {
        $fromLayers = $this->resolveBoolFromLayers($ordered, 'enabled');
        if ($fromLayers !== null) {
            return $fromLayers;
        }

        $envKey = (string) ($this->config['env']['POS_V2_ENABLED'] ?? 'POS_V2_ENABLED');
        $fromEnv = $this->environmentReader->getOptionalBool($envKey);
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return (bool) ($this->config['defaults']['POS_V2_ENABLED'] ?? false);
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveProfile(array $ordered): string
    {
        $fromLayers = $this->resolveStringFromLayers($ordered, 'profile');
        if ($fromLayers !== null) {
            return $this->normalizeProfile($fromLayers);
        }

        $envKey = (string) ($this->config['env']['POS_V2_PROFILE'] ?? 'POS_V2_PROFILE');
        $fromEnv = $this->environmentReader->getOptionalString($envKey);
        if ($fromEnv !== null) {
            return $this->normalizeProfile($fromEnv);
        }

        return $this->normalizeProfile((string) ($this->config['defaults']['POS_V2_PROFILE'] ?? 'retail'));
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveScanMode(array $ordered): bool
    {
        $fromLayers = $this->resolveBoolFromLayers($ordered, 'scan_mode');
        if ($fromLayers === null) {
            $fromLayers = $this->resolveNestedBoolFromLayers($ordered, ['features', 'scan_mode']);
        }
        if ($fromLayers !== null) {
            return $fromLayers;
        }

        $envKey = (string) ($this->config['env']['POS_V2_SCAN_MODE'] ?? 'POS_V2_SCAN_MODE');
        $fromEnv = $this->environmentReader->getOptionalBool($envKey);
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return (bool) ($this->config['defaults']['POS_V2_SCAN_MODE'] ?? false);
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveOffline(array $ordered): bool
    {
        $explicit = $this->resolveBoolFromLayers($ordered, 'offline');
        if ($explicit === null) {
            $explicit = $this->resolveNestedBoolFromLayers($ordered, ['features', 'offline']);
        }
        if ($explicit !== null) {
            return $explicit;
        }

        $mode = $this->resolveNestedStringFromLayers($ordered, ['offline', 'mode']);
        if ($mode !== null && $mode !== 'disabled') {
            return true;
        }

        $envKey = (string) ($this->config['env']['POS_V2_OFFLINE'] ?? 'POS_V2_OFFLINE');
        $fromEnv = $this->environmentReader->getOptionalBool($envKey);
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return (bool) ($this->config['defaults']['POS_V2_OFFLINE'] ?? false);
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveCardTerminal(array $ordered): bool
    {
        $fromLayers = $this->resolveBoolFromLayers($ordered, 'card_terminal');
        if ($fromLayers === null) {
            $fromLayers = $this->resolveNestedBoolFromLayers($ordered, ['features', 'card_terminal']);
        }
        if ($fromLayers !== null) {
            return $fromLayers;
        }

        $envKey = (string) ($this->config['env']['POS_V2_CARD_TERMINAL'] ?? 'POS_V2_CARD_TERMINAL');
        $fromEnv = $this->environmentReader->getOptionalBool($envKey);
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        return (bool) ($this->config['defaults']['POS_V2_CARD_TERMINAL'] ?? false);
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveBoolFromLayers(array $ordered, string $key): ?bool
    {
        foreach ($ordered as $layer) {
            if (!is_array($layer)) {
                continue;
            }
            if (array_key_exists($key, $layer)) {
                return (bool) $layer[$key];
            }
            $flags = $layer['flags'] ?? null;
            if (is_array($flags) && array_key_exists($key, $flags)) {
                return (bool) $flags[$key];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     * @param list<string> $path
     */
    private function resolveNestedBoolFromLayers(array $ordered, array $path): ?bool
    {
        foreach ($ordered as $layer) {
            if (!is_array($layer)) {
                continue;
            }
            $value = $this->getNestedValue($layer, $path);
            if ($value !== null) {
                return (bool) $value;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     */
    private function resolveStringFromLayers(array $ordered, string $key): ?string
    {
        foreach ($ordered as $layer) {
            if (!is_array($layer)) {
                continue;
            }
            if (array_key_exists($key, $layer) && is_scalar($layer[$key])) {
                return (string) $layer[$key];
            }
            $flags = $layer['flags'] ?? null;
            if (is_array($flags) && array_key_exists($key, $flags) && is_scalar($flags[$key])) {
                return (string) $flags[$key];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>|null> $ordered
     * @param list<string> $path
     */
    private function resolveNestedStringFromLayers(array $ordered, array $path): ?string
    {
        foreach ($ordered as $layer) {
            if (!is_array($layer)) {
                continue;
            }
            $value = $this->getNestedValue($layer, $path);
            if ($value !== null && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $layer
     * @param list<string> $path
     */
    private function getNestedValue(array $layer, array $path): mixed
    {
        $cursor = $layer;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function normalizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        $allowed = $this->config['profiles'] ?? ['retail'];

        if (in_array($profile, $allowed, true)) {
            return $profile;
        }

        return (string) ($this->config['defaults']['POS_V2_PROFILE'] ?? 'retail');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $path = \Rateb\App\Pos\PosModule::rootPath() . '/config/v2/feature-flags.php';
        if (!is_file($path)) {
            return [
                'defaults' => [],
                'env' => [],
                'profiles' => ['retail'],
            ];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}
