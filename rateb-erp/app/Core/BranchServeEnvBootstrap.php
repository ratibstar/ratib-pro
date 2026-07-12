<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D.1 — Load branch appliance serve.env before HybridRuntime boots.
 *
 * ONLY for local Branch Appliance (php -S / loopback) or explicit allow.
 * Cloud hosts (rateb.sa / *.rateb.sa) never auto-load serve.env — even if the
 * file exists on disk — so SaaS MySQL runtime stays identical.
 */
final class BranchServeEnvBootstrap
{
    private static bool $applied = false;

    public static function reset(): void
    {
        self::$applied = false;
    }

    public static function apply(string $erpRoot): void
    {
        if (self::$applied) {
            return;
        }
        self::$applied = true;

        if (self::isServerCloudLocked() || self::isCloudHostname()) {
            return;
        }

        if (!self::mayLoadServeEnvFromHttp()) {
            return;
        }

        $erpRoot = str_replace('\\', '/', rtrim($erpRoot, '/'));
        $serveEnv = $erpRoot . '/storage/branch/serve.env';
        if (!is_readable($serveEnv)) {
            return;
        }

        foreach (file($serveEnv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $value = trim($value);
            if (self::hasTrustedServerValue($key)) {
                continue;
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }

    /**
     * Local php -S / loopback may load serve.env.
     * Production Branch Appliance should inject env via systemd/Windows service
     * (EnvironmentFile), not by reading the file from untrusted public HTTP hosts.
     */
    private static function mayLoadServeEnvFromHttp(): bool
    {
        if (self::envFlagTruthy('RATEB_ALLOW_SERVE_ENV_HTTP')) {
            return true;
        }

        if (PHP_SAPI === 'cli-server') {
            return true;
        }

        if (PHP_SAPI === 'cli') {
            return false;
        }

        $host = self::requestHost();

        return $host === '127.0.0.1'
            || $host === 'localhost'
            || $host === '::1'
            || $host === '0.0.0.0';
    }

    private static function isCloudHostname(): bool
    {
        $host = self::requestHost();
        if ($host === '') {
            return false;
        }
        if (in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
            return true;
        }

        return str_ends_with($host, '.rateb.sa');
    }

    private static function requestHost(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    }

    private static function isServerCloudLocked(): bool
    {
        $deployment = self::readServerScalar('RATEB_DEPLOYMENT');
        if (in_array($deployment, ['cloud', 'saas', 'production_cloud'], true)) {
            return true;
        }

        return in_array(self::readServerScalar('RATEB_CLOUD_LOCK'), ['1', 'true', 'yes', 'on'], true);
    }

    private static function hasTrustedServerValue(string $key): bool
    {
        $fromServer = self::readServerScalar($key);

        return $fromServer !== null && $fromServer !== '';
    }

    private static function envFlagTruthy(string $name): bool
    {
        $raw = self::readServerScalar($name);

        return $raw !== null && in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private static function readServerScalar(string $key): ?string
    {
        if (array_key_exists($key, $_SERVER)) {
            $raw = $_SERVER[$key];
            if (is_string($raw) && trim($raw) !== '') {
                return strtolower(trim($raw));
            }
        }
        $fromEnv = getenv($key);
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return strtolower(trim($fromEnv));
        }

        return null;
    }
}
