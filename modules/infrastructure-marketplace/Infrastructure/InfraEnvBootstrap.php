<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Infrastructure;

/**
 * Loads RATEB_INFRA_* from config/infra.secrets.php and writable storage/rateb_uploads fallback.
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

        $root = self::projectRoot();
        self::loadConfigFiles($root);

        if (!self::hasSecretKey()) {
            self::loadStorageSecretKey($root);
        }
        if (!self::hasSecretKey()) {
            self::loadLocalDevSecretKey($root);
        }
    }

    /**
     * Creates secret key file when missing (config/ or storage/). Safe to call from verify CLI.
     *
     * @return array{ok:bool, path?:string, message:string}
     */
    public static function ensureSecretKeyProvisioned(): array
    {
        self::load();
        if (self::hasSecretKey()) {
            return ['ok' => true, 'message' => 'key_already_present'];
        }

        $root = self::projectRoot();

        $configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'infra.secrets.php';
        if (self::tryWriteConfigSecretFile($configPath)) {
            self::$loaded = false;
            self::load();

            return self::hasSecretKey()
                ? ['ok' => true, 'path' => $configPath, 'message' => 'created_config_infra_secrets']
                : ['ok' => false, 'message' => 'config_write_failed_load'];
        }

        foreach (self::secretKeyCandidatePaths($root) as $storagePath) {
            if (!self::tryWriteSecretKeyFile($storagePath)) {
                continue;
            }
            self::$loaded = false;
            self::load();
            if (self::hasSecretKey()) {
                return ['ok' => true, 'path' => $storagePath, 'message' => 'created_storage_secret_key'];
            }
        }

        return ['ok' => false, 'message' => 'no_writable_path_for_secret_key'];
    }

    public static function hasSecretKey(): bool
    {
        $v = getenv('RATEB_INFRA_SECRET_KEY');
        if (is_string($v) && trim($v) !== '') {
            return true;
        }
        $v2 = getenv('RATEB_INFRA_PROVIDER_SECRET_KEY');

        return is_string($v2) && trim($v2) !== '';
    }

    public static function storageSecretKeyPath(string $root): string
    {
        $candidates = self::secretKeyCandidatePaths($root);

        return $candidates[0] ?? ($root . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
            . DIRECTORY_SEPARATOR . '.rateb_infra_secret_key');
    }

    /**
     * Same writable roots as runtime-overrides (uploads / parent rateb_infra / storage).
     *
     * @return list<string>
     */
    public static function secretKeyCandidatePaths(string $root): array
    {
        $out = [
            $root . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
                . DIRECTORY_SEPARATOR . '.rateb_infra_secret_key',
        ];

        $uploadsHelper = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'rateb_uploads_base.php';
        if (is_file($uploadsHelper)) {
            require_once $uploadsHelper;
            if (function_exists('rateb_uploads_base_dir')) {
                $base = rateb_uploads_base_dir();
                if (is_string($base) && $base !== '') {
                    $out[] = rtrim($base, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
                        . DIRECTORY_SEPARATOR . '.rateb_infra_secret_key';
                }
            }
            if (function_exists('rateb_uploads_candidate_base_dirs')) {
                foreach (rateb_uploads_candidate_base_dirs(false) as $base) {
                    if (!is_string($base) || $base === '') {
                        continue;
                    }
                    $out[] = rtrim($base, DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR . 'infrastructure-marketplace'
                        . DIRECTORY_SEPARATOR . '.rateb_infra_secret_key';
                }
            }
        }

        $parent = dirname($root);
        if ($parent !== '' && $parent !== '.' && $parent !== $root) {
            $out[] = $parent . DIRECTORY_SEPARATOR . 'rateb_infra' . DIRECTORY_SEPARATOR . '.rateb_infra_secret_key';
        }

        $fromEnv = getenv('RATEB_INFRA_SECRET_KEY_FILE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            array_unshift($out, trim($fromEnv));
        }

        $unique = [];
        foreach ($out as $p) {
            $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
            if (!in_array($norm, $unique, true)) {
                $unique[] = $norm;
            }
        }

        return $unique;
    }

    private static function projectRoot(): string
    {
        $root = dirname(__DIR__, 3);
        $rp = realpath($root);

        return $rp !== false ? $rp : $root;
    }

    private static function loadConfigFiles(string $root): void
    {
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

    private static function loadStorageSecretKey(string $root): void
    {
        foreach (self::secretKeyCandidatePaths($root) as $path) {
            if (!is_readable($path)) {
                continue;
            }
            $raw = trim((string) file_get_contents($path));
            if ($raw === '') {
                continue;
            }
            self::applySecretKey($raw);

            return;
        }
    }

    private static function loadLocalDevSecretKey(string $root): void
    {
        if (!self::isLocalDevHost()) {
            return;
        }
        $path = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.rateb_infra_local_secret_key';
        if (!is_file($path)) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $generated = bin2hex(random_bytes(32));
                @file_put_contents($path, $generated . PHP_EOL, LOCK_EX);
                @chmod($path, 0600);
            }
        }
        if (!is_readable($path)) {
            return;
        }
        $raw = trim((string) file_get_contents($path));
        if ($raw === '') {
            return;
        }
        self::applySecretKey($raw);
    }

    private static function applySecretKey(string $raw): void
    {
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }
        putenv('RATEB_INFRA_SECRET_KEY=' . $raw);
        $_ENV['RATEB_INFRA_SECRET_KEY'] = $raw;
        $_SERVER['RATEB_INFRA_SECRET_KEY'] = $raw;
    }

    private static function tryWriteConfigSecretFile(string $target): bool
    {
        if (is_readable($target)) {
            return true;
        }
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        $key = bin2hex(random_bytes(32));
        $contents = "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "/** Auto-generated — do not commit. */\n"
            . "return [\n"
            . "    'RATEB_INFRA_SECRET_KEY' => '" . $key . "',\n"
            . "];\n";

        if (@file_put_contents($target, $contents, LOCK_EX) === false) {
            return false;
        }
        @chmod($target, 0600);

        return true;
    }

    private static function tryWriteSecretKeyFile(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        if (is_file($path) && is_readable($path)) {
            return true;
        }
        $generated = bin2hex(random_bytes(32));
        if (@file_put_contents($path, $generated . PHP_EOL, LOCK_EX) === false) {
            return false;
        }
        @chmod($path, 0600);
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "# Deny HTTP access\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n",
                LOCK_EX
            );
        }

        return true;
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
