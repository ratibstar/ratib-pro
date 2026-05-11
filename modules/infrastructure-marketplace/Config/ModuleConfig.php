<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Config;

/**
 * Reads module flags from environment. No provider credentials or URLs are stored here.
 */
final class ModuleConfig
{
    public static function isModuleEnabled(): bool
    {
        $v = getenv('RATIB_INFRA_MARKETPLACE_ENABLED');

        return $v !== false && $v !== '' && !in_array(strtolower((string) $v), ['0', 'false', 'off', 'no'], true);
    }

    public static function defaultQueueDriver(): string
    {
        $d = getenv('RATIB_INFRA_QUEUE_DRIVER');

        return $d !== false && $d !== '' ? strtolower(trim((string) $d)) : 'sync';
    }

    /**
     * @return array<string, mixed>
     */
    public static function providerBindings(): array
    {
        $raw = getenv('RATIB_INFRA_PROVIDER_BINDINGS');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
