<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Infrastructure;

/**
 * Loads RATIB_INFRA_* from config/infra.secrets.php and optional local dev key file.
 */
final class InfraEnvBootstrap
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $root = dirname(__DIR__, 3);
        $paths = [
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'infra.secrets.php',
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env' . DIRECTORY_SEPARATOR . 'infra.secrets.php',
        ];
        foreach ($paths as $path) {
            if (!is_readable($path)) {
                continue;
            }
            try {
                $cfg = require $path;
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($cfg)) {
                continue;
            }
            self::applyConfig($cfg);
            break;
        }

        if (!self::hasSecretKey()) {
            self::loadLocalDevSecretKey($root);
        }
    }

    public static function hasSecretKey(): bool
    {
        $v = getenv('RATIB_INFRA_SECRET_KEY');
        if (is_string($v) && trim($v) !== '') {
            return true;
        }
        $v2 = getenv('RATIB_INFRA_PROVIDER_SECRET_KEY');

        return is_string($v2) && trim($v2) !== '';
    }

    /**
     * @param array<string, mixed> $cfg
     */
    private static function applyConfig(array $cfg): void
    {
        foreach ($cfg as $key => $value) {
            if (!is_string($key) || !is_string($value) || trim($value) === '') {
                continue;
            }
            if (getenv($key) !== false && trim((string) getenv($key)) !== '') {
                continue;
            }
            $trimmed = trim($value);
            putenv($key . '=' . $trimmed);
            $_ENV[$key] = $trimmed;
            $_SERVER[$key] = $trimmed;
        }
    }

    private static function loadLocalDevSecretKey(string $root): void
    {
        if (!self::isLocalDevHost()) {
            return;
        }
        $path = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.ratib_infra_local_secret_key';
        if (!is_file($path)) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $generated = bin2hex(random_bytes(32));
            @file_put_contents($path, $generated . PHP_EOL, LOCK_EX);
            @chmod($path, 0600);
        }
        if (!is_readable($path)) {
            return;
        }
        $raw = trim((string) file_get_contents($path));
        if ($raw === '') {
            return;
        }
        putenv('RATIB_INFRA_SECRET_KEY=' . $raw);
        $_ENV['RATIB_INFRA_SECRET_KEY'] = $raw;
        $_SERVER['RATIB_INFRA_SECRET_KEY'] = $raw;
    }

    private static function isLocalDevHost(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.local')) {
            return true;
        }
        $appEnv = getenv('APP_ENV');
        if (is_string($appEnv) && in_array(strtolower(trim($appEnv)), ['local', 'development', 'dev'], true)) {
            return true;
        }

        return false;
    }
}
