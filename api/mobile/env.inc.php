<?php
/**
 * Mobile API — early MOBILE_AUTH_SECRET bootstrap from project-root .env.
 *
 * Runs before includes/config.php so JWT/QR routes do not depend on
 * config/env/load.php allowlist or PHP-FPM putenv() retention alone.
 */
declare(strict_types=1);

if (!function_exists('rateb_mobile_env_candidate_paths')) {
    /**
     * @return list<string>
     */
    function rateb_mobile_env_candidate_paths(): array
    {
        $paths = [];
        $projectRoot = dirname(__DIR__, 2);
        $paths[] = $projectRoot . DIRECTORY_SEPARATOR . '.env';

        $envDir = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env';
        if (is_dir($envDir)) {
            $paths[] = dirname($envDir) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $doc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\");
            if ($doc !== '') {
                $paths[] = $doc . DIRECTORY_SEPARATOR . '.env';
                $parent = dirname($doc);
                if ($parent !== $doc && $parent !== '') {
                    $paths[] = $parent . DIRECTORY_SEPARATOR . '.env';
                }
            }
        }

        $uniq = [];
        foreach ($paths as $path) {
            $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if ($norm !== '' && !in_array($norm, $uniq, true)) {
                $uniq[] = $norm;
            }
        }

        return $uniq;
    }
}

if (!function_exists('rateb_mobile_parse_dotenv_key')) {
    function rateb_mobile_parse_dotenv_key(string $path, string $key): ?string
    {
        if ($path === '' || !is_readable($path)) {
            return null;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return null;
        }
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || ($line[0] ?? '') === '#') {
                continue;
            }
            if (strncasecmp($line, 'export ', 7) === 0) {
                $line = trim(substr($line, 7));
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $k = trim((string) ($parts[0] ?? ''));
            if ($k !== $key) {
                continue;
            }
            $val = trim((string) ($parts[1] ?? ''));
            $len = strlen($val);
            if ($len >= 2) {
                $q0 = $val[0];
                $q1 = $val[$len - 1];
                if (($q0 === '"' && $q1 === '"') || ($q0 === "'" && $q1 === "'")) {
                    $val = substr($val, 1, -1);
                }
            }
            return $val;
        }

        return null;
    }
}

if (!function_exists('rateb_mobile_bootstrap_env')) {
    function rateb_mobile_bootstrap_env(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $existing = getenv('MOBILE_AUTH_SECRET');
        if ($existing !== false && trim((string) $existing) !== '') {
            if (!defined('MOBILE_AUTH_SECRET')) {
                define('MOBILE_AUTH_SECRET', (string) $existing);
            }

            return;
        }
        if (defined('MOBILE_AUTH_SECRET') && trim((string) MOBILE_AUTH_SECRET) !== '') {
            return;
        }

        $foundPath = '';
        $usedPath = '';
        foreach (rateb_mobile_env_candidate_paths() as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            if ($foundPath === '') {
                $foundPath = $path;
            }
            $val = rateb_mobile_parse_dotenv_key($path, 'MOBILE_AUTH_SECRET');
            if ($val === null || trim($val) === '') {
                continue;
            }
            $usedPath = $path;
            putenv('MOBILE_AUTH_SECRET=' . $val);
            $_ENV['MOBILE_AUTH_SECRET'] = $val;
            $_SERVER['MOBILE_AUTH_SECRET'] = $val;
            if (!defined('MOBILE_AUTH_SECRET')) {
                define('MOBILE_AUTH_SECRET', $val);
            }
            break;
        }

        $GLOBALS['rateb_mobile_env_file_found'] = $foundPath !== '';
        $GLOBALS['rateb_mobile_env_file_used'] = $usedPath !== '' ? $usedPath : $foundPath;
    }
}
