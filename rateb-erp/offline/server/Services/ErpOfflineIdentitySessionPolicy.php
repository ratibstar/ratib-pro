<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase P2 — warm unlock / renew session timing policy (env-configurable).
 */
final class ErpOfflineIdentitySessionPolicy
{
    public const DEFAULT_UNLOCK_TTL_MS = 8 * 60 * 60 * 1000;
    public const DEFAULT_IDLE_TIMEOUT_MS = 15 * 60 * 1000;
    public const DEFAULT_MAX_OFFLINE_SESSION_MS = 72 * 60 * 60 * 1000;
    public const DEFAULT_RENEW_BEFORE_SECONDS = 3 * 24 * 60 * 60;
    public const DEFAULT_CLOCK_SKEW_SECONDS = 300;

    /** @return array{
     *   unlock_ttl_ms: int,
     *   idle_timeout_ms: int,
     *   max_offline_session_ms: int,
     *   renew_before_seconds: int,
     *   clock_skew_seconds: int
     * } */
    public function snapshot(): array
    {
        return [
            'unlock_ttl_ms' => $this->unlockTtlMs(),
            'idle_timeout_ms' => $this->idleTimeoutMs(),
            'max_offline_session_ms' => $this->maxOfflineSessionMs(),
            'renew_before_seconds' => $this->renewBeforeSeconds(),
            'clock_skew_seconds' => $this->clockSkewSeconds(),
        ];
    }

    public function renewThresholdSeconds(): int
    {
        return $this->renewBeforeSeconds();
    }

    public function unlockTtlMs(): int
    {
        return $this->envInt('RATEB_OFFLINE_UNLOCK_TTL_MS', self::DEFAULT_UNLOCK_TTL_MS);
    }

    public function idleTimeoutMs(): int
    {
        return $this->envInt('RATEB_OFFLINE_IDLE_TIMEOUT_MS', self::DEFAULT_IDLE_TIMEOUT_MS);
    }

    public function maxOfflineSessionMs(): int
    {
        return $this->envInt('RATEB_OFFLINE_MAX_SESSION_MS', self::DEFAULT_MAX_OFFLINE_SESSION_MS);
    }

    public function renewBeforeSeconds(): int
    {
        return $this->envInt('RATEB_OFFLINE_IDENTITY_RENEW_BEFORE', self::DEFAULT_RENEW_BEFORE_SECONDS);
    }

    public function clockSkewSeconds(): int
    {
        return $this->envInt('RATEB_OFFLINE_CLOCK_SKEW_SECONDS', self::DEFAULT_CLOCK_SKEW_SECONDS);
    }

    private function envInt(string $key, int $default): int
    {
        $env = getenv($key);
        if ($env === false || $env === '') {
            $env = (string) ($_ENV[$key] ?? '');
        }
        if ($env === '') {
            return $default;
        }
        $n = (int) $env;

        return $n > 0 ? $n : $default;
    }
}
