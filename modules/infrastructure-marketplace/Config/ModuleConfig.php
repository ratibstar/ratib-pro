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

    public static function queueMaxAttempts(): int
    {
        $v = getenv('RATIB_INFRA_QUEUE_MAX_ATTEMPTS');
        $n = is_string($v) ? (int) $v : 5;
        return $n > 0 ? $n : 5;
    }

    public static function queueDeadLetterState(): string
    {
        $v = getenv('RATIB_INFRA_QUEUE_DEAD_STATE');
        return is_string($v) && trim($v) !== '' ? strtoupper(trim($v)) : 'DEAD_LETTER';
    }

    public static function workerLockTtlSeconds(): int
    {
        $v = getenv('RATIB_INFRA_LOCK_TTL_SECONDS');
        $n = is_string($v) ? (int) $v : 180;
        return $n > 0 ? $n : 180;
    }

    public static function cpanelWhmBaseUrl(): ?string
    {
        $v = getenv('RATIB_INFRA_CPANEL_BASE_URL');
        return is_string($v) && trim($v) !== '' ? rtrim(trim($v), '/') : null;
    }

    public static function cpanelWhmUsername(): ?string
    {
        $v = getenv('RATIB_INFRA_CPANEL_USERNAME');
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    public static function cpanelWhmToken(): ?string
    {
        $v = getenv('RATIB_INFRA_CPANEL_API_TOKEN');
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
