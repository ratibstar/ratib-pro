<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D.1 — Load branch appliance serve.env before HybridRuntime boots.
 *
 * When storage/branch/serve.env exists (installer-written), apply its variables
 * so HTTP requests via public/index.php activate branch/SQLite mode — same as
 * hybrid-branch-serve.php. Cloud hosts without serve.env are unchanged.
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

        if (self::isServerCloudLocked()) {
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
