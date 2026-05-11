<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Config;

/**
 * Reads module flags from environment. No provider credentials or URLs are stored here.
 */
final class ModuleConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $runtimeOverridesCache = null;

    /**
     * @return array<string, mixed>
     */
    private static function runtimeOverrides(): array
    {
        if (self::$runtimeOverridesCache !== null) {
            return self::$runtimeOverridesCache;
        }
        $path = dirname(__DIR__) . '/Config/runtime-overrides.json';
        if (!is_file($path)) {
            self::$runtimeOverridesCache = [];
            return self::$runtimeOverridesCache;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            self::$runtimeOverridesCache = [];
            return self::$runtimeOverridesCache;
        }
        $decoded = json_decode($raw, true);
        self::$runtimeOverridesCache = is_array($decoded) ? $decoded : [];
        return self::$runtimeOverridesCache;
    }

    /**
     * @return mixed|null
     */
    private static function runtimeOverride(string $key)
    {
        $all = self::runtimeOverrides();
        return array_key_exists($key, $all) ? $all[$key] : null;
    }

    private static function boolFromMixed($value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return $default;
        }
        return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function isModuleEnabled(): bool
    {
        $override = self::runtimeOverride('enabled');
        if ($override !== null) {
            return self::boolFromMixed($override, false);
        }
        $v = getenv('RATIB_INFRA_MARKETPLACE_ENABLED');

        return $v !== false && $v !== '' && !in_array(strtolower((string) $v), ['0', 'false', 'off', 'no'], true);
    }

    public static function defaultQueueDriver(): string
    {
        $override = self::runtimeOverride('queue_driver');
        if (is_string($override) && trim($override) !== '') {
            return strtolower(trim($override));
        }
        $d = getenv('RATIB_INFRA_QUEUE_DRIVER');

        return $d !== false && $d !== '' ? strtolower(trim((string) $d)) : 'sync';
    }

    /**
     * @return array<string, mixed>
     */
    public static function providerBindings(): array
    {
        $override = self::runtimeOverride('provider_bindings');
        if (is_array($override)) {
            return $override;
        }
        $raw = getenv('RATIB_INFRA_PROVIDER_BINDINGS');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function queueMaxAttempts(): int
    {
        $override = self::runtimeOverride('queue_max_attempts');
        if (is_numeric($override)) {
            $o = (int) $override;
            if ($o > 0) {
                return $o;
            }
        }
        $v = getenv('RATIB_INFRA_QUEUE_MAX_ATTEMPTS');
        $n = is_string($v) ? (int) $v : 5;
        return $n > 0 ? $n : 5;
    }

    public static function queueDeadLetterState(): string
    {
        $override = self::runtimeOverride('queue_dead_state');
        if (is_string($override) && trim($override) !== '') {
            return strtoupper(trim($override));
        }
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
        $override = self::runtimeOverride('cpanel_base_url');
        if (is_string($override) && trim($override) !== '') {
            return rtrim(trim($override), '/');
        }
        $v = getenv('RATIB_INFRA_CPANEL_BASE_URL');
        return is_string($v) && trim($v) !== '' ? rtrim(trim($v), '/') : null;
    }

    public static function cpanelWhmUsername(): ?string
    {
        $override = self::runtimeOverride('cpanel_username');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }
        $v = getenv('RATIB_INFRA_CPANEL_USERNAME');
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    public static function cpanelWhmToken(): ?string
    {
        $override = self::runtimeOverride('cpanel_api_token');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }
        $v = getenv('RATIB_INFRA_CPANEL_API_TOKEN');
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }

    public static function defaultMarketplaceCurrency(): string
    {
        $override = self::runtimeOverride('default_currency');
        if (is_string($override) && trim($override) !== '') {
            return strtoupper(trim($override));
        }
        $v = getenv('RATIB_INFRA_DEFAULT_CURRENCY');
        return is_string($v) && trim($v) !== '' ? strtoupper(trim($v)) : 'USD';
    }

    public static function queuePressureThreshold(): int
    {
        $override = self::runtimeOverride('queue_pressure_threshold');
        if (is_numeric($override)) {
            $o = (int) $override;
            if ($o > 100) {
                return $o;
            }
        }
        $v = getenv('RATIB_INFRA_QUEUE_PRESSURE_THRESHOLD');
        $n = is_string($v) ? (int) $v : 2000;
        return $n > 100 ? $n : 2000;
    }

    public static function executionKillSwitch(): bool
    {
        $override = self::runtimeOverride('execution_kill_switch');
        if ($override !== null) {
            return self::boolFromMixed($override, false);
        }
        $v = getenv('RATIB_INFRA_EXECUTION_KILL_SWITCH');
        return is_string($v) && in_array(strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function dryRunMode(): bool
    {
        $override = self::runtimeOverride('dry_run');
        if ($override !== null) {
            return self::boolFromMixed($override, false);
        }
        $v = getenv('RATIB_INFRA_DRY_RUN');
        return is_string($v) && in_array(strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function providerLiveEnabled(string $providerKey): bool
    {
        $v = getenv('RATIB_INFRA_PROVIDER_' . strtoupper(trim($providerKey)) . '_LIVE');
        return is_string($v) && in_array(strtolower(trim($v)), ['1', 'true', 'on', 'yes'], true);
    }

    public static function providerSandboxEnabled(string $providerKey): bool
    {
        $v = getenv('RATIB_INFRA_PROVIDER_' . strtoupper(trim($providerKey)) . '_SANDBOX');
        return !is_string($v) || !in_array(strtolower(trim($v)), ['0', 'false', 'off', 'no'], true);
    }

    public static function rolloutTenantAllowlist(): array
    {
        $override = self::runtimeOverride('tenant_allowlist');
        if (is_array($override)) {
            $parts = array_map(static fn($x): int => (int) $x, $override);
            return array_values(array_filter($parts, static fn(int $x): bool => $x > 0));
        }
        if (is_string($override) && trim($override) !== '') {
            $parts = array_map(static fn(string $x): int => (int) trim($x), explode(',', $override));
            return array_values(array_filter($parts, static fn(int $x): bool => $x > 0));
        }
        $v = getenv('RATIB_INFRA_TENANT_ALLOWLIST');
        if (!is_string($v) || trim($v) === '') {
            return [];
        }
        $parts = array_map(static fn(string $x): int => (int) trim($x), explode(',', $v));
        return array_values(array_filter($parts, static fn(int $x): bool => $x > 0));
    }

    public static function workerMaxLoopJobs(): int
    {
        $override = self::runtimeOverride('worker_max_loop_jobs');
        if (is_numeric($override)) {
            $o = (int) $override;
            if ($o > 0) {
                return $o;
            }
        }
        $v = getenv('RATIB_INFRA_WORKER_MAX_LOOP_JOBS');
        $n = is_string($v) ? (int) $v : 1000;
        return $n > 0 ? $n : 1000;
    }
}
